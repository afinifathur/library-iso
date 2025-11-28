<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentVersion;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DraftController extends Controller
{
    /**
     * Show list of drafts (Draft Container).
     * Optionally filter by 'draft' or 'rejected' via ?filter=
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        // base query: show only versions in draft or rejected status (KABAG stage)
        $q = DocumentVersion::with(['document', 'creator'])
            ->whereIn('status', ['draft', 'rejected'])
            ->orderByDesc('updated_at');

        // filter by type
        $filter = $request->query('filter', null);
        if ($filter === 'rejected') {
            $q->where('status', 'rejected');
        } elseif ($filter === 'draft') {
            $q->where('status', 'draft');
        }

        // If user is not admin/mr/director, limit to versions created by user (optional policy)
        if (method_exists($user, 'hasAnyRole')) {
            if (! $user->hasAnyRole(['admin','mr','director'])) {
                // Non-moderators see only their own drafts
                $q->where('created_by', $user->id);
            }
        } else {
            // no roles system: show only user's drafts as safety
            $q->where('created_by', $user->id);
        }

        $drafts = $q->paginate(25)->appends($request->query());

        return view('drafts.index', compact('drafts', 'filter'));
    }

    /**
     * Show single draft version detail
     */
    public function show(Request $request, $versionId)
    {
        $version = DocumentVersion::with(['document', 'creator'])->findOrFail($versionId);

        // authorization: only visible if draft/rejected OR user is admin/mr/director
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if (! in_array($version->status, ['draft','rejected'], true)) {
            // allow MR/admin/director to view any non-final version in some setups
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin','mr','director'])) {
                // ok
            } else {
                abort(403, 'Version is not in Draft/Rejected state.');
            }
        }

        return view('drafts.show', ['version' => $version]);
    }

    /**
     * Edit draft form (if needed) - reuses documents._form in show modal usually.
     */
    public function edit(Request $request, $versionId)
    {
        $version = DocumentVersion::with(['document'])->findOrFail($versionId);
        $user = $request->user();
        if (! $user) abort(403);

        // only owner or admin/mr can edit draft
        $canEdit = false;
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin','mr'])) {
            $canEdit = true;
        } elseif ($version->created_by == $user->id) {
            $canEdit = true;
        }

        if (! $canEdit) abort(403);

        // Prepare related lists required by form: departments, categories
        $departments = \App\Models\Department::orderBy('code')->get();
        $categories = [];
        if (class_exists(\App\Models\Category::class)) {
            $categories = \App\Models\Category::orderBy('name')->get();
        }

        // show view (you can reuse documents.edit or a drafts.edit as you have)
        return view('drafts.edit', [
            'version' => $version,
            'document' => $version->document,
            'departments' => $departments,
            'categories' => $categories,
        ]);
    }

    /**
     * Destroy draft (soft move to recycle/trashed or permanent delete depending on route used).
     * In routes we keep POST compatibility for forms.
     */
    public function destroy(Request $request, $versionId)
    {
        $user = $request->user();
        if (! $user) abort(403);

        $version = DocumentVersion::findOrFail($versionId);

        // only allow delete if status is draft or rejected; and check roles / ownership
        if (! in_array($version->status, ['draft','rejected'], true)) {
            return back()->with('error', 'Hanya draft/rejected yang dapat dihapus dari Draft Container.');
        }

        $canDelete = false;
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin','mr'])) {
            $canDelete = true;
        } elseif ($version->created_by == $user->id) {
            // allow owner to delete their own draft
            $canDelete = true;
        }

        if (! $canDelete) {
            abort(403);
        }

        // We will move to 'trashed' (recycle) rather than permanent delete here.
        // If you prefer permanent delete, change to $version->delete() (with file cleanup).
        $oldStatus = $version->status;
        $version->status = 'trashed';
        $version->approval_stage = null;
        $version->save();

        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'event'               => 'move_to_recycle',
                'user_id'             => $user->id,
                'document_id'         => $version->document_id,
                'document_version_id' => $version->id,
                'detail'              => json_encode(['from' => $oldStatus]),
                'ip'                  => $request->ip(),
            ]);
        }

        return redirect()->route('drafts.index')->with('success', 'Draft berhasil dipindahkan ke Recycle Bin.');
    }

    /**
     * Submit draft for approval (moves status draft -> submitted and sets proper stage)
     */
    public function submit(Request $request, $versionId)
    {
        $user = $request->user();
        if (! $user) abort(403);

        $version = DocumentVersion::with('document')->findOrFail($versionId);

        // only drafts/rejected can be submitted
        if (! in_array($version->status, ['draft','rejected'], true)) {
            return back()->with('error', 'Hanya draft yang dapat diajukan.');
        }

        // prevent double submission if already pending
        $pending = DocumentVersion::where('document_id', $version->document_id)
            ->whereIn('status', ['submitted','pending'])
            ->exists();

        if ($pending) {
            return back()->with('error', 'Terdapat revisi lain yang sedang diajukan. Batalkan atau tunggu prosesnya.');
        }

        // update status -> submitted, set next stage (MR)
        $version->status = 'submitted';
        $version->submitted_by = $user->id;
        $version->submitted_at = now();
        $version->approval_stage = 'MR';
        $version->save();

        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'event'               => 'submit_draft',
                'user_id'             => $user->id,
                'document_id'         => $version->document_id,
                'document_version_id' => $version->id,
                'detail'              => json_encode(['note' => 'submitted from draft container']),
                'ip'                  => $request->ip(),
            ]);
        }

        return redirect()->route('approval.index')->with('success', 'Draft berhasil diajukan ke approval queue.');
    }

    /**
     * Reopen a draft: convert rejected -> draft (or reset some fields).
     */
    public function reopen(Request $request, $versionId)
    {
        $user = $request->user();
        if (! $user) abort(403);

        $version = DocumentVersion::findOrFail($versionId);

        // only allow reopen if status is rejected or trashed (optionally)
        if (! in_array($version->status, ['rejected','trashed'], true)) {
            return back()->with('error', 'Hanya versi yang berstatus rejected/trashed yang dapat dibuka kembali.');
        }

        $canReopen = false;
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin','mr'])) {
            $canReopen = true;
        } elseif ($version->created_by == $user->id) {
            $canReopen = true;
        }

        if (! $canReopen) abort(403);

        $version->status = 'draft';
        $version->approval_stage = 'KABAG';
        $version->rejected_reason = null;
        $version->rejected_at = null;
        $version->rejected_by = null;
        $version->save();

        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'event'               => 'reopen_draft',
                'user_id'             => $user->id,
                'document_id'         => $version->document_id,
                'document_version_id' => $version->id,
                'detail'              => json_encode(['note' => 'reopened from recycle/rejected']),
                'ip'                  => $request->ip(),
            ]);
        }

        return redirect()->route('drafts.show', $version->id)->with('success', 'Versi dibuka kembali sebagai draft.');
    }
}
