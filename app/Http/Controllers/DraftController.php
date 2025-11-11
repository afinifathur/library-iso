<?php

namespace App\Http\Controllers;

use App\Models\DocumentVersion;
use Illuminate\Http\Request;

class DraftController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * INDEX
     * - MR:     stage = MR,        status in [submitted, draft]
     * - DIRECTOR: stage = DIRECTOR, status in [submitted]
     * - ADMIN:  status in [draft, submitted, rejected] (+opsional ?stage=KABAG|MR|DIRECTOR)
     * - KABAG/USER biasa (non-privileged):
     *      status in [draft, rejected], approval_stage = KABAG,
     *      dan jika user punya department_id → filter by department
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $q = DocumentVersion::with(['document', 'creator']);

        $hasRoles = fn(array $roles) =>
            method_exists($user, 'hasAnyRole') ? $user->hasAnyRole($roles) : false;

        if ($hasRoles(['mr'])) {
            // MR: lihat yang siap/terkait di stage MR
            $q->where('approval_stage', 'MR')
              ->whereIn('status', ['submitted', 'draft'])
              ->orderByDesc('created_at');

        } elseif ($hasRoles(['director'])) {
            // Direktur: hanya yang submitted di stage DIRECTOR
            $q->where('approval_stage', 'DIRECTOR')
              ->whereIn('status', ['submitted'])
              ->orderByDesc('created_at');

        } elseif ($hasRoles(['admin'])) {
            // Admin: bebas lihat status umum + optional filter stage via query
            $q->whereIn('status', ['draft', 'submitted', 'rejected'])
              ->orderByDesc('created_at');

            if ($request->filled('stage')) {
                $q->where('approval_stage', strtoupper((string) $request->query('stage')));
            }

        } else {
            // Non-privileged (kabag/user biasa):
            // Tampilkan draft atau rejected yang masih di stage KABAG
            $q->whereIn('status', ['draft', 'rejected'])
              ->where('approval_stage', 'KABAG')
              ->orderByDesc('updated_at');

            // Jika user punya department_id → batasi per department
            if (!empty($user->department_id)) {
                $q->whereHas('document', function($sub) use ($user) {
                    $sub->where('department_id', $user->department_id);
                });
            }
        }

        $versions = $q->paginate(30)->withQueryString();

        return view('drafts.index', compact('versions'));
    }

    /**
     * SHOW: detail satu draft/version.
     */
    public function show(DocumentVersion $version)
    {
        $version->load(['document', 'creator']);
        return view('drafts.show', compact('version'));
    }

    /**
     * DESTROY: hapus draft (MR/Admin).
     */
    public function destroy(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        $allowed = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['mr', 'admin'])
            : false;

        if (! $allowed) {
            abort(403);
        }

        // cegah hapus versi final
        if (in_array($version->status, ['approved'], true)) {
            return redirect()->route('drafts.index')->with('warning', 'Cannot delete an approved version.');
        }

        $version->delete();

        return redirect()->route('drafts.index')->with('success', 'Draft removed.');
    }

    /**
     * REOPEN: kembalikan ke draft (pemilik atau MR/Admin).
     */
    public function reopen(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        $isOwner = (int) $version->created_by === (int) $user->id;

        $isMrOrAdmin = method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['mr', 'admin'])
            : false;

        if (! $isOwner && ! $isMrOrAdmin) {
            abort(403);
        }

        // tidak boleh reopen yang sudah approved
        if (in_array($version->status, ['approved'], true)) {
            return redirect()
                ->route('drafts.show', $version->id)
                ->with('warning', 'Approved version cannot be reopened.');
        }

        $version->status = 'draft';
        $version->approval_stage = 'KABAG';
        $version->save();

        return redirect()
            ->route('drafts.show', $version->id)
            ->with('success', 'Draft reopened for edit.');
        }
}
