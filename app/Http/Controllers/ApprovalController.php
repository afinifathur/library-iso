<?php
// app/Http/Controllers/ApprovalController.php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApprovalController extends Controller
{
    /**
     * Show approval queue (filtered by role/stage).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) abort(403);

        // determine stage by role
        $stage = null;
        if (method_exists($user, 'hasAnyRole')) {
            if ($user->hasAnyRole(['mr'])) {
                $stage = 'MR';
            } elseif ($user->hasAnyRole(['director'])) {
                $stage = 'DIR';
            } elseif ($user->hasAnyRole(['kabag'])) {
                $stage = 'KABAG';
            } else {
                // admins see everything
                if ($user->hasAnyRole(['admin'])) {
                    $stage = null;
                }
            }
        }

        $query = DocumentVersion::with(['document', 'creator'])
            ->where('status', 'submitted');

        if (! is_null($stage)) {
            $query->where('approval_stage', $stage);
        }

        $pending = $query->orderByDesc('created_at')->paginate(25);

        // friendly label for blade
        $userRoleLabel = $stage ?? 'ALL';

        return view('approval.index', [
            'pendingVersions' => $pending,
            'stage' => $userRoleLabel,
            'userRoleLabel' => $userRoleLabel,
        ]);
    }

    /**
     * View single version in approval flow
     */
    public function view(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if (! $user) abort(403);

        $version->load(['document', 'creator']);
        return view('approval.view', compact('version'));
    }

    /**
     * Approve / forward a version.
     *
     * MR -> forward to Director (status remains 'submitted' but approval_stage => 'DIR', submitted_by set)
     * Director/Admin -> approve (status => 'approved', approval_stage => 'DONE', promote to document current_version)
     */
    public function approve(Request $request, $versionId)
{
    $user = $request->user();
    if (! $user) abort(403);

    $version = \App\Models\DocumentVersion::findOrFail($versionId);

    // simple permission check (sesuaikan dengan roles kamu)
    if (method_exists($user, 'hasAnyRole') && ! $user->hasAnyRole(['mr','director','admin'])) {
        abort(403);
    }

    // Mark approved (MVP — adapt flow setelahnya)
    $version->update([
        'status' => 'approved',
        'approval_stage' => 'DONE',
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);

    // promote to document current version (optional, keep transaction in prod)
    $doc = $version->document;
    if ($doc) {
        $doc->update([
            'current_version_id' => $version->id,
            'revision_date' => now(),
        ]);
    }

    return redirect()->back()->with('success','Version approved (MVP).');
}


    /**
     * Common denied response
     */
    protected function respondDenied(Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        return back()->with('error', 'Unauthorized');
    }

    protected function incRevision($rev)
    {
        if (is_numeric($rev)) {
            return (int) $rev + 1;
        }
        return 1;
    }
}
