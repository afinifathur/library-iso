<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    /**
     * Approval Queue
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) abort(403);

        // roles: MR, director, admin boleh lihat
        if (!method_exists($user, 'hasAnyRole') || !$user->hasAnyRole(['mr','director','admin'])) {
            abort(403);
        }

        // versi yang masih menunggu approval atau under review
        $rows = DocumentVersion::with(['document', 'creator'])
            ->whereIn('status', ['submitted', 'under_review'])
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('approval.index', [
            'rows' => $rows,
        ]);
    }

    /**
     * APPROVE version
     */
    public function approve(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if (!$user) abort(403);

        // Only MR, Director, Admin
        if (!method_exists($user, 'hasAnyRole') || !$user->hasAnyRole(['mr','director','admin'])) {
            return $this->forbidden($request);
        }

        DB::transaction(function () use ($version, $user) {

            // If MR → forward to Director
            if ($user->hasAnyRole(['mr'])) {
                $version->update([
                    'status'         => 'submitted',
                    'approval_stage' => 'DIR',
                    'submitted_by'   => $user->id,
                    'submitted_at'   => now(),
                ]);

                return;
            }

            // If Director/Admin → final approval
            $version->update([
                'status'         => 'approved',
                'approval_stage' => 'DONE',
                'approved_by'    => $user->id,
                'approved_at'    => now(),
            ]);

            // promote to current version
            $doc = $version->document;
            if ($doc) {
                $doc->update([
                    'current_version_id' => $version->id,
                    'revision_number'    => intval($doc->revision_number ?? 0) + 1,
                    'revision_date'      => now(),
                    'approved_by'        => $user->id,
                    'approved_at'        => now(),
                ]);
            }
        });

        // If AJAX → return JSON
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => 'Version approved'], 200);
        }

        return back()->with('success', 'Version approved.');
    }

    /**
     * REJECT version
     */
    public function reject(Request $request, DocumentVersion $version)
    {
        $request->validate(['rejected_reason' => 'required|string|max:2000']);

        $user = $request->user();
        if (!$user) abort(403);

        if (!method_exists($user, 'hasAnyRole') || !$user->hasAnyRole(['mr','director','admin'])) {
            return $this->forbidden($request);
        }

        $version->update([
            'status'          => 'rejected',
            'approval_stage'  => 'KABAG',
            'rejected_by'     => $user->id,
            'rejected_at'     => now(),
            'rejected_reason' => $request->input('rejected_reason'),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => 'Version rejected'], 200);
        }

        return back()->with('success', 'Version rejected.');
    }

    /**
     * View Version (Read-only)
     */
    public function view(Request $request, DocumentVersion $version)
    {
        return view('approval.view', ['version' => $version]);
    }

    private function forbidden(Request $request)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        abort(403);
    }
}
