<?php

namespace App\Http\Controllers;

use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ApprovalController extends Controller
{
    /**
     * Show approval queue based on user role.
     */
    public function index(Request $request): View
    {
        $user = $request->user() ?? Auth::user();

        $isDirector = $this->userHasAnyRole($user, ['director']);
        $isMr       = $this->userHasAnyRole($user, ['mr']);
        $isKabag    = $this->userHasAnyRole($user, ['kabag']);
        $isAdmin    = $this->userHasAnyRole($user, ['admin']);

        $query = DocumentVersion::with(['document', 'creator', 'document.department']);

        if ($isDirector) {
            $query->whereRaw("UPPER(TRIM(COALESCE(approval_stage,''))) IN (?,?)", ['DIRECTOR', 'DIR'])
                  ->where('status', 'to_dir');

        } elseif ($isMr) {
            $query->whereRaw("UPPER(TRIM(COALESCE(approval_stage,''))) = ?", ['MR'])
                  ->whereIn('status', ['submitted', 'draft', 'pending']);

        } elseif ($isKabag) {
            $query->whereRaw("UPPER(TRIM(COALESCE(approval_stage,''))) = ?", ['KABAG'])
                  ->whereIn('status', ['draft', 'pending', 'submitted']);

        } elseif ($isAdmin) {
            $query->whereIn('status', ['submitted', 'pending', 'in_progress', 'to_dir']);

        } else {
            $query->where('created_by', $user->id)
                  ->whereIn('status', ['submitted', 'pending']);
        }

        $rows = $query->orderByDesc('submitted_at')
                      ->orderByDesc('created_at')
                      ->paginate(15)
                      ->appends($request->query());

        $stage = $isDirector ? 'DIRECTOR'
               : ($isMr ? 'MR'
               : ($isKabag ? 'KABAG' : 'ALL'));

        return view('approval.index', [
            'rows'          => $rows,
            'stage'         => $stage,
            'userRoleLabel' => strtoupper($stage),
        ]);
    }

    /**
     * MR queue.
     */
    public function mrQueue(Request $request): View
    {
        $this->authorizeQueueAccess('mr');

        $rows = DocumentVersion::whereRaw("UPPER(TRIM(COALESCE(approval_stage,''))) = ?", ['MR'])
            ->whereIn('status', ['submitted', 'pending', 'draft'])
            ->with(['document', 'creator', 'document.department'])
            ->orderByDesc('submitted_at')
            ->get();

        return view('approval.index', [
            'rows'          => $rows,
            'stage'         => 'MR',
            'userRoleLabel' => 'MR',
        ]);
    }

    /**
     * Director queue.
     */
    public function directorQueue(Request $request): View
    {
        $this->authorizeQueueAccess('director');

        $rows = DocumentVersion::whereRaw("UPPER(TRIM(COALESCE(approval_stage,''))) IN (?,?)", ['DIRECTOR', 'DIR'])
            ->where('status', 'to_dir')
            ->with(['document', 'creator', 'document.department'])
            ->orderByDesc('submitted_at')
            ->get();

        return view('approval.index', [
            'rows'          => $rows,
            'stage'         => 'DIRECTOR',
            'userRoleLabel' => 'DIRECTOR',
        ]);
    }

    /**
     * APPROVE action (safe & idempotent).
     *
     * - MR: forward -> set status = 'to_dir', approval_stage = 'DIR'
     * - Director/Admin: final approve -> set status = 'approved', approval_stage = 'DONE' and promote document
     */
    public function approve(Request $request, DocumentVersion $version)
    {
        $user = $request->user() ?? Auth::user();
        if (! $user) {
            abort(Response::HTTP_FORBIDDEN, 'Unauthorized');
        }

        // Debug log
        Log::info('approval.approve called', [
            'user_id' => $user?->id,
            'roles' => $user && method_exists($user, 'getRoleNames') ? $user->getRoleNames() : null,
            'version_id' => $version->id,
            'status_before' => $version->status,
            'stage_before' => $version->approval_stage,
        ]);

        // Permission checks (use existing helper)
        $isMr  = $this->userHasAnyRole($user, ['mr']);
        $isDir = $this->userHasAnyRole($user, ['director', 'admin']);

        if (! $isMr && ! $isDir) {
            abort(Response::HTTP_FORBIDDEN, 'You are not allowed to approve.');
        }

        DB::transaction(function () use ($version, $user, $isMr, $isDir, $request) {

            // MR: forward to Director
            if ($isMr) {
                $stage  = $this->normalizeStage($version->approval_stage ?? '');
                $status = strtolower((string)($version->status ?? ''));

                // allow idempotent forward: if already to_dir, do nothing
                if ($status === 'to_dir' || $stage === 'DIR' || $stage === 'DIRECTOR') {
                    // already forwarded — nothing to change
                    return;
                }

                // require that MR is operating on MR-stage items (per business rules)
                if ($stage !== 'MR' && ! in_array($status, ['submitted', 'draft', 'pending'], true)) {
                    // throw to rollback transaction and allow outer caller to handle
                    throw new \RuntimeException('Item not eligible for MR forward.');
                }

                $version->status = 'to_dir';
                $version->approval_stage = 'DIR';
                if (Schema::hasColumn($version->getTable(), 'submitted_by')) {
                    $version->submitted_by = $user->id;
                }
                if (Schema::hasColumn($version->getTable(), 'submitted_at')) {
                    $version->submitted_at = Carbon::now();
                }
                $version->save();

                // write approval log if table exists (safe)
                $this->maybeInsertApprovalLog(
                    $version->id,
                    $user->id,
                    $this->getCurrentRoleName($user),
                    'forward_to_director',
                    $request->input('note')
                );

                return;
            }

            // Director/Admin: final approve
            if ($isDir) {
                $status = strtolower((string)($version->status ?? ''));
                $stage  = $this->normalizeStage($version->approval_stage ?? '');

                // idempotent: if already approved -> do nothing
                if ($status === 'approved' && $stage === 'DONE') {
                    return;
                }

                // ensure the item is ready for director approval
                if ($status !== 'to_dir' && ! in_array($stage, ['DIRECTOR', 'DIR'], true)) {
                    throw new \RuntimeException('Version not ready for Director approval.');
                }

                // mark version approved
                $version->status = 'approved';
                $version->approval_stage = 'DONE';
                if (Schema::hasColumn($version->getTable(), 'approved_by')) {
                    $version->approved_by = $user->id;
                }
                if (Schema::hasColumn($version->getTable(), 'approved_at')) {
                    $version->approved_at = Carbon::now();
                }
                $version->save();

                // promote to document
                $doc = $version->document;
                if ($doc) {
                    // supersede previous current_version if applicable
                    if (! empty($doc->current_version_id) && $doc->current_version_id != $version->id) {
                        $old = DocumentVersion::find($doc->current_version_id);
                        if ($old && ! in_array($old->status, ['approved', 'rejected', 'superseded'], true)) {
                            $old->status = 'superseded';
                            $old->save();
                        }
                    }

                    if (Schema::hasColumn($doc->getTable(), 'current_version_id')) {
                        $doc->current_version_id = $version->id;
                    }
                    if (Schema::hasColumn($doc->getTable(), 'revision_number')) {
                        $doc->revision_number = $this->incRevision($doc->revision_number ?? 0);
                    }
                    if (Schema::hasColumn($doc->getTable(), 'revision_date')) {
                        $doc->revision_date = Carbon::now();
                    }
                    if (Schema::hasColumn($doc->getTable(), 'approved_by')) {
                        $doc->approved_by = $user->id;
                    }
                    if (Schema::hasColumn($doc->getTable(), 'approved_at')) {
                        $doc->approved_at = Carbon::now();
                    }
                    $doc->save();
                }

                // write approval log
                $this->maybeInsertApprovalLog(
                    $version->id,
                    $user->id,
                    $this->getCurrentRoleName($user),
                    'approve_final',
                    $request->input('note')
                );

                return;
            }
        });

        // Clear caches / payloads
        Cache::forget('dashboard.payload');

        // determine response
        if ($isMr) {
            return back()->with('success', 'Version berhasil diteruskan ke Director.');
        }

        // Director/Admin
        $docTitle = optional($version->document)->title
            ?? optional($version->document)->doc_code
            ?? 'Dokumen';

        return redirect()
            ->route('approval.index')
            ->with('success', "Berhasil diapprove: dokumen \"{$docTitle}\" menjadi versi resmi.");
    }

    public function approveVersion(Request $request, DocumentVersion $version)
    {
        return $this->approve($request, $version);
    }

    /**
     * Reject document and send back to Kabag/draft.
     */
    public function reject(Request $request, DocumentVersion $version)
    {
        $note = $request->input('rejected_reason')
             ?? $request->input('notes')
             ?? $request->input('reason')
             ?? $request->input('note');

        if ($note === null) {
            $request->validate(['rejected_reason' => 'required|string|max:2000']);
            $note = $request->input('rejected_reason');
        }

        $user = $request->user() ?? Auth::user();
        if (! $user) abort(Response::HTTP_FORBIDDEN);

        if (! $this->userHasAnyRole($user, ['mr', 'director', 'admin', 'kabag'])) {
            return back()->with('error', 'Anda tidak berwenang mereject versi ini.');
        }

        DB::transaction(function () use ($version, $user, $note) {
            $version->status = 'rejected';
            $version->approval_stage = 'KABAG';

            if (Schema::hasColumn($version->getTable(), 'rejected_by')) {
                $version->rejected_by = $user->id;
            }
            if (Schema::hasColumn($version->getTable(), 'rejected_at')) {
                $version->rejected_at = Carbon::now();
            }
            if (Schema::hasColumn($version->getTable(), 'approval_notes')) {
                $version->approval_notes = $note;
            }
            if (Schema::hasColumn($version->getTable(), 'approval_note')) {
                $version->approval_note = $note;
            }

            $version->save();

            $this->maybeInsertApprovalLog($version->id, $user->id, $this->getCurrentRoleName($user), 'reject', $note);
        });

        Cache::forget('dashboard.payload');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Version rejected and returned to Kabag.',
            ]);
        }

        return redirect()
            ->route('approval.index')
            ->with('success', 'Version direject dan dikembalikan ke Kabag/draft.');
    }

    public function rejectVersion(Request $request, DocumentVersion $version)
    {
        $note = $request->input('rejected_reason')
             ?? $request->input('notes')
             ?? $request->input('reason')
             ?? $request->input('note');

        if ($note !== null) {
            $request->merge(['rejected_reason' => $note]);
        }

        return $this->reject($request, $version);
    }

    /* --------------------------------
     * Helper functions
     * -------------------------------- */

    protected function authorizeQueueAccess(string $role): void
    {
        $user = auth()->user();

        if (! $user || ! $this->userHasAnyRole($user, [$role])) {
            abort(Response::HTTP_FORBIDDEN, 'Access denied.');
        }
    }

    protected function userHasAnyRole($user, array $roles): bool
    {
        if (! $user) return false;

        if (method_exists($user, 'hasAnyRole')) {
            try {
                return $user->hasAnyRole($roles);
            } catch (\Throwable $e) {}
        }

        if (method_exists($user, 'roles')) {
            try {
                $names = $user->roles()->pluck('name')->map(fn ($n) => strtolower($n))->toArray();
                return collect($roles)->contains(fn ($r) => in_array(strtolower($r), $names));
            } catch (\Throwable $e) {}
        }

        if (isset($user->roles) && is_iterable($user->roles)) {
            $names = collect($user->roles)->pluck('name')->map(fn ($n) => strtolower($n))->toArray();
            return collect($roles)->contains(fn ($r) => in_array(strtolower($r), $names));
        }

        // Email fallback
        $whitelist = ['direktur@peroniks.com', 'adminqc@peroniks.com'];
        return ! empty($user->email) && in_array(strtolower($user->email), $whitelist, true);
    }

    protected function normalizeStage(string $stage): string
    {
        $s = strtoupper(trim($stage));
        return match (true) {
            str_starts_with($s, 'DIR') => 'DIRECTOR',
            str_starts_with($s, 'MR')  => 'MR',
            str_starts_with($s, 'KAB') => 'KABAG',
            $s === 'DONE'              => 'DONE',
            default                   => $s,
        };
    }

    protected function getCurrentRoleName($user): string
    {
        if ($user && method_exists($user, 'getRoleNames')) {
            return $user->getRoleNames()->first() ?? 'unknown';
        }

        if ($user && method_exists($user, 'roles')) {
            return $user->roles()->pluck('name')->first() ?? 'unknown';
        }

        if ($user && isset($user->roles)) {
            return collect($user->roles)->pluck('name')->first() ?? 'unknown';
        }

        return 'unknown';
    }

    /**
     * Insert a row to approval_logs if table exists (safe helper).
     */
    protected function maybeInsertApprovalLog(int $versionId, int $userId, string $role, string $action, ?string $note = null): void
    {
        if (! Schema::hasTable('approval_logs')) {
            return;
        }

        try {
            DB::table('approval_logs')->insert([
                'document_version_id' => $versionId,
                'user_id'             => $userId,
                'role'                => $role,
                'action'              => $action,
                'note'                => $note,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to insert approval_log: {$e->getMessage()}");
        }
    }

    /**
     * Safely increment revision number (returns int).
     */
    protected function incRevision($current): int
    {
        $n = (int) $current;
        return $n > 0 ? ($n + 1) : 1;
    }
}
