<?php
// File: app/Http/Controllers/DraftController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentVersion;
use App\Models\Document;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DraftController extends Controller
{
    // list drafts visible to current user (kabag/admin see their department drafts too)
    public function index(Request $request)
    {
        $user = $request->user();

        // Jika user admin, tunjukkan semua draft; jika kabag tunjukkan yg department-nya atau created_by
        $query = DocumentVersion::query()
            ->whereIn('status', ['draft','rejected']);

        // optionally filter by department via relationship document.department_id
        if (! $user->hasAnyRole(['admin','mr','director'] ?? [])) {
            // for plain kabag users: show drafts they created OR drafts for their department
            // assuming User has department_id column (optional)
            if ($user->department_id ?? false) {
                $deptId = $user->department_id;
                $query->where(function ($q) use ($deptId, $user) {
                    $q->whereHas('document', fn($qq) => $qq->where('department_id', $deptId))
                      ->orWhere('created_by', $user->id);
                });
            } else {
                $query->where('created_by', $user->id);
            }
        }

        $drafts = $query->with(['document','creator'])
            ->orderByDesc('created_at')
            ->paginate(25)
            ->appends($request->query());

        return view('drafts.index', compact('drafts'));
    }

    // show one draft version (full editor view reused from documents.edit/show)
    public function show(DocumentVersion $version)
    {
        $version->load(['document','creator']);
        return view('drafts.show', compact('version'));
    }

    // submit a draft -> set status submitted, approval_stage=MR, submitted_by/at
    public function submit(Request $request, DocumentVersion $version)
    {
        $user = $request->user();

        // permission check (kabag can submit their draft; admin can too)
        if (! ($user->hasAnyRole(['kabag','admin','mr']) ?? true) && $version->created_by !== $user->id) {
            abort(403);
        }

        DB::transaction(function () use ($version, $user) {
            $version->status = 'submitted';
            $version->approval_stage = 'MR';
            $version->submitted_by = $user->id;
            $version->submitted_at = Carbon::now();
            $version->rejected_reason = null;
            $version->rejected_by = null;
            $version->rejected_at = null;
            $version->save();
        });

        return redirect()->route('drafts.index')->with('success','Draft submitted — muncul di approval queue MR.');
    }

    // reopen a rejected version (move back to draft) - used by kabag to reopen after fix
    public function reopen(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if ($version->status !== 'rejected') {
            return back()->with('error','Only rejected drafts can be reopened.');
        }

        if ($version->created_by !== $user->id && ! $user->hasAnyRole(['admin'])) {
            abort(403);
        }

        $version->status = 'draft';
        $version->rejected_reason = null;
        $version->rejected_by = null;
        $version->rejected_at = null;
        $version->save();

        return redirect()->route('drafts.show', $version->id)->with('success','Draft reopened for edit.');
    }

    // delete draft
    public function destroy(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if ($version->created_by !== $user->id && ! $user->hasAnyRole(['admin'])) {
            abort(403);
        }

        // keep record? here we'll delete version record and optionally file from disk
        try {
            // delete file if exists
            if ($version->file_path && \Storage::disk('documents')->exists($version->file_path)) {
                \Storage::disk('documents')->delete($version->file_path);
            }
        } catch (\Throwable $e) {
            // ignore disk errors
        }

        $version->delete();

        return redirect()->route('drafts.index')->with('success','Draft deleted.');
    }
}
