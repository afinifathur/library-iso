<?php
// app/Http/Controllers/ApprovalController.php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Schema;
use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApprovalController extends Controller
{
    /**
     * Canonical approval stage tokens used across controllers/views.
     * Keep these consistent: 'KABAG', 'MR', 'DIRECTOR', 'DONE'
     */

    /**
     * Show approval queue (filtered by role/stage).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) abort(403);

        // determine stage by role (use canonical tokens)
        $stage = null;
        if (method_exists($user, 'hasAnyRole')) {
            if ($user->hasAnyRole(['mr'])) {
                $stage = 'MR';
            } elseif ($user->hasAnyRole(['director'])) {
                $stage = 'DIRECTOR';
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

        return redirect()->route('versions.show', $version->id);
    }

    /**
     * Approve / forward a version.
     *
     * Flow implemented (MVP / canonical tokens):
     * - KABAG  -> forward to MR         (approval_stage = 'MR', keep status='submitted')
     * - MR     -> forward to DIRECTOR   (approval_stage = 'DIRECTOR', keep status='submitted')
     * - DIRECTOR or ADMIN -> finalize approval (status = 'approved', approval_stage = 'DONE', promote)
     *
     * Returns JSON when requested, otherwise redirects back with flash message.
     */
    public function approve(Request $request, $versionId)
    {
        $user = $request->user();
        if (! $user) abort(403);

        $version = DocumentVersion::with('document')->findOrFail($versionId);

        // basic permission: only these roles can act in this endpoint
        if (method_exists($user, 'hasAnyRole') && ! $user->hasAnyRole(['kabag','mr','director','admin'])) {
            return $this->respondDenied($request);
        }

        // Role-based handling
        try {
            // KABAG -> forward to MR
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['kabag'])) {
                $version->update([
                    'approval_stage' => 'MR',
                    'status' => 'submitted',
                    'submitted_by' => $user->id,
                    'submitted_at' => Carbon::now(),
                ]);

                $message = 'Version forwarded to MR.';
                if ($request->wantsJson()) {
                    return response()->json(['message' => $message, 'version_id' => $version->id]);
                }
                return redirect()->back()->with('success', $message);
            }

            // MR -> forward to DIRECTOR
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['mr'])) {
                if ($version->created_by === $user->id) {
                    $error = 'Self-approval is prohibited for MR reviews.';
                    if ($request->wantsJson()) {
                        return response()->json(['message' => $error], 403);
                    }
                    return redirect()->back()->with('error', $error);
                }

                DB::transaction(function () use ($version, $user, $request) {
                    $version->update([
                        'approval_stage' => 'DIRECTOR',
                        'status' => 'submitted',
                        'submitted_by' => $user->id,
                        'submitted_at' => Carbon::now(),
                    ]);

                    $this->insertApprovalLog($version->id, $user->id, 'mr', 'forward_to_director', 'Forwarded to Director');
                    $this->maybeAudit('mr_forward_version', $user->id, $version->document_id, $version->id, $request->ip(), ['stage' => 'DIRECTOR']);
                });

                $message = 'Version forwarded to Director.';
                if ($request->wantsJson()) {
                    return response()->json(['message' => $message, 'version_id' => $version->id]);
                }
                return redirect()->back()->with('success', $message);
            }

            // Director or Admin -> finalize approval
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['director','admin'])) {
                if ($version->created_by === $user->id) {
                    $error = 'Self-approval is prohibited for Director reviews.';
                    if ($request->wantsJson()) {
                        return response()->json(['message' => $error], 403);
                    }
                    return redirect()->back()->with('error', $error);
                }

                DB::transaction(function () use ($version, $user, $request) {
                    $version->approveByDirector($user->id);
                    $this->maybeAudit('director_approve_version', $user->id, $version->document_id, $version->id, $request->ip());
                });

                $message = 'Version approved and promoted.';
                if ($request->wantsJson()) {
                    return response()->json(['message' => $message, 'version_id' => $version->id]);
                }
                return redirect()->back()->with('success', $message);
            }

            // Any other role falls back to denied
            return $this->respondDenied($request);

        } catch (\Throwable $e) {
            // Log if desired; return friendly error
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Error processing approval', 'error' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Error processing approval: ' . $e->getMessage());
        }
    }

    /**
     * Reject a version (called from JS).
     * Expects 'rejected_reason' (string) in request body.
     */
    public function reject(Request $request, $versionId)
    {
        $user = $request->user();
        if (! $user) abort(403);

        $version = DocumentVersion::findOrFail($versionId);

        // basic permission check
        if (method_exists($user, 'hasAnyRole') && ! $user->hasAnyRole(['kabag','mr','director','admin'])) {
            return $this->respondDenied($request);
        }

        $reason = $request->input('rejected_reason', null);
        if (! $reason) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'rejected_reason is required'], 422);
            }
            return redirect()->back()->with('error', 'Alasan reject wajib diisi.');
        }

        try {
            DB::transaction(function () use ($version, $user, $reason, $request) {
                $version->rejectByRole($reason, $user->id);
                $this->maybeAudit('reject_version', $user->id, $version->document_id, $version->id, $request->ip(), ['reason' => $reason]);
            });

            $message = 'Version rejected.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $message, 'version_id' => $version->id]);
            }
            return redirect()->back()->with('success', $message);
        } catch (\Throwable $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Error rejecting version', 'error' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Error rejecting version: ' . $e->getMessage());
        }
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

    protected function getCurrentRoleName($user): string
    {
        if (! $user) return 'unknown';
        if (method_exists($user, 'getRoleNames')) {
            try { $names = $user->getRoleNames()->toArray(); return $names[0] ?? 'unknown'; } catch (\Throwable) {}
        }
        if (method_exists($user, 'roles')) {
            try { return $user->roles()->pluck('name')->first() ?? 'unknown'; } catch (\Throwable) {}
        }
        if (isset($user->roles) && is_iterable($user->roles)) {
            return collect($user->roles)->pluck('name')->first() ?? 'unknown';
        }
        return 'unknown';
    }

    protected function insertApprovalLog(int $versionId, int $userId, string $role, string $action, ?string $note = null): void
    {
        if (! Schema::hasTable('approval_logs')) return;
        DB::table('approval_logs')->insert([
            'document_version_id' => $versionId,
            'user_id'             => $userId,
            'role'                => $role,
            'action'              => $action,
            'note'                => $note,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    protected function maybeAudit(string $event, $userId = null, $documentId = null, $documentVersionId = null, $ip = null, $detail = [])
    {
        if (! class_exists(\App\Models\AuditLog::class)) return;
        try {
            \App\Models\AuditLog::create([
                'event' => $event,
                'user_id' => $userId,
                'document_id' => $documentId,
                'document_version_id' => $documentVersionId,
                'detail' => json_encode($detail),
                'ip' => $ip,
            ]);
        } catch (\Throwable) { /* ignore */ }
    }
}
