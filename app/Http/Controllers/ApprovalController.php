<?php
// File: app/Http/Controllers/ApprovalController.php

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

class ApprovalController extends Controller
{
    /**
     * Show approval queue according to current user's role.
     *
     * - MR sees approval_stage = 'MR' && status in ['submitted','pending','draft']
     * - Director sees approval_stage = 'DIR' && status = 'to_dir'
     * - Admin sees broad pending set (optional)
     *
     * Returns view 'approval.index' with a paginated $rows variable.
     */
    public function index(Request $request): View
    {
        $user = $request->user() ?? Auth::user();

        // role checks cached locally
        $isDirector = $this->userHasAnyRole($user, ['director']);
        $isMr       = $this->userHasAnyRole($user, ['mr']);
        $isKabag    = $this->userHasAnyRole($user, ['kabag']);
        $isAdmin    = $this->userHasAnyRole($user, ['admin']);

        $query = DocumentVersion::with(['document', 'creator', 'document.department']);

        if ($isDirector) {
            // Director: only items forwarded to director
            $query->whereRaw("UPPER(TRIM(COALESCE(approval_stage, ''))) IN (?, ?)", ['DIRECTOR', 'DIR'])
                  ->where('status', 'to_dir');
        } elseif ($isMr) {
            // MR: only MR-stage items
            $query->whereRaw("UPPER(TRIM(COALESCE(approval_stage, ''))) = ?", ['MR'])
                  ->whereIn('status', ['submitted', 'draft', 'pending']);
        } elseif ($isKabag) {
            $query->whereRaw("UPPER(TRIM(COALESCE(approval_stage, ''))) = ?", ['KABAG'])
                  ->whereIn('status', ['draft', 'pending', 'submitted']);
        } elseif ($isAdmin) {
            // Admin: broad pending set
            $query->whereIn('status', ['submitted', 'pending', 'in_progress', 'to_dir']);
        } else {
            // Other users: show own submissions
            $query->where('created_by', $user->id)->whereIn('status', ['submitted', 'pending']);
        }

        $rows = $query->orderByDesc('submitted_at')
                      ->orderByDesc('created_at')
                      ->paginate(15)
                      ->appends($request->query());

        $stage = $isDirector ? 'DIRECTOR' : ($isMr ? 'MR' : ($isKabag ? 'KABAG' : 'ALL'));
        $userRoleLabel = strtoupper($stage);

        return view('approval.index', [
            'rows' => $rows,
            'stage' => $stage,
            'userRoleLabel' => $userRoleLabel,
        ]);
    }

    /**
     * MR-specific queue (non-paginated list). Useful for direct links.
     */
    public function mrQueue(Request $request): View
    {
        $this->authorizeQueueAccess('mr');

        $rows = DocumentVersion::whereRaw("UPPER(TRIM(COALESCE(approval_stage, ''))) = ?", ['MR'])
            ->whereIn('status', ['submitted', 'pending', 'draft'])
            ->with(['document', 'creator', 'document.department'])
            ->orderByDesc('submitted_at')
            ->get();

        return view('approval.index', [
            'rows' => $rows,
            'stage' => 'MR',
            'userRoleLabel' => 'MR',
        ]);
    }

    /**
     * Director-specific queue (non-paginated list). Useful for direct links.
     */
    public function directorQueue(Request $request): View
    {
        $this->authorizeQueueAccess('director');

        $rows = DocumentVersion::whereRaw("UPPER(TRIM(COALESCE(approval_stage, ''))) IN (?, ?)", ['DIRECTOR', 'DIR'])
            ->where('status', 'to_dir')
            ->with(['document', 'creator', 'document.department'])
            ->orderByDesc('submitted_at')
            ->get();

        return view('approval.index', [
            'rows' => $rows,
            'stage' => 'DIRECTOR',
            'userRoleLabel' => 'DIRECTOR',
        ]);
    }

    /**
     * Approve action:
     *  - MR: forward -> set status = 'to_dir', approval_stage = 'DIR'
     *  - Director: final approve -> set status = 'approved', approval_stage = 'DONE' and promote document
     */
    public function approve(Request $request, DocumentVersion $version)
    {
        $user = $request->user() ?? Auth::user();
        if (! $user) abort(Response::HTTP_FORBIDDEN);

        // MR: forward to Director
        if ($this->userHasAnyRole($user, ['mr'])) {
            $currentStage = $this->normalizeStage($version->approval_stage ?? '');
            $status = strtolower((string)($version->status ?? ''));

            if ($currentStage !== 'MR' && ! in_array($status, ['submitted', 'draft', 'pending'], true)) {
                return back()->with('error', 'Item tidak berada pada antrian MR atau sudah diproses.');
            }

            DB::transaction(function () use ($version, $user, $request) {
                $version->update([
                    'status' => 'to_dir',
                    'approval_stage' => 'DIR',
                    'submitted_by' => $user->id,
                    'submitted_at' => now(),
                ]);

                $this->maybeInsertApprovalLog($version->id, $user->id, $this->getCurrentRoleName($user), 'forward_to_dir', $request->input('note'));
            });

            Cache::forget('dashboard.payload');

            return back()->with('success', 'Version berhasil diteruskan ke Director.');
        }

        // Director: final approve
        if ($this->userHasAnyRole($user, ['director', 'admin'])) {
            $status = strtolower((string)($version->status ?? ''));
            $stageNorm = $this->normalizeStage($version->approval_stage ?? '');

            if ($status !== 'to_dir' && $stageNorm !== 'DIRECTOR' && $stageNorm !== 'DIR') {
                return back()->with('error', 'Versi belum diteruskan ke Director atau tidak siap untuk diapprove.');
            }

            DB::transaction(function () use ($version, $user) {
                $version->update([
                    'status' => 'approved',
                    'approval_stage' => 'DONE',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);

                $doc = $version->document;
                if ($doc) {
                    if (! empty($doc->current_version_id) && $doc->current_version_id != $version->id) {
                        $old = DocumentVersion::find($doc->current_version_id);
                        if ($old && ! in_array($old->status, ['approved','rejected','superseded'], true)) {
                            $old->status = 'superseded';
                            $old->save();
                        }
                    }

                    if (Schema::hasColumn($doc->getTable(), 'current_version_id')) {
                        $doc->current_version_id = $version->id;
                    }
                    if (Schema::hasColumn($doc->getTable(), 'revision_number')) {
                        $doc->revision_number = is_numeric($doc->revision_number) ? ((int)$doc->revision_number + 1) : 1;
                    }
                    if (Schema::hasColumn($doc->getTable(), 'revision_date')) {
                        $doc->revision_date = now();
                    }
                    if (Schema::hasColumn($doc->getTable(), 'approved_by')) {
                        $doc->approved_by = $user->id;
                    }
                    if (Schema::hasColumn($doc->getTable(), 'approved_at')) {
                        $doc->approved_at = now();
                    }
                    $doc->save();
                }

                $this->maybeInsertApprovalLog($version->id, $user->id, $this->getCurrentRoleName($user), 'approve_final', request()->input('note'));
            });

            Cache::forget('dashboard.payload');

            $docTitle = optional($version->document)->title ?? $version->document->doc_code ?? 'Dokumen';
            return redirect()->route('approval.index')->with('success', "Berhasil diapprove: dokumen \"{$docTitle}\" menjadi versi resmi.");
        }

        return back()->with('error', 'Anda tidak berwenang melakukan aksi ini.');
    }

    /**
     * Alias kept for routes that previously called approveVersion.
     */
    public function approveVersion(Request $request, DocumentVersion $version)
    {
        return $this->approve($request, $version);
    }

    /**
     * Reject a version and return to Kabag / draft container.
     */
    public function reject(Request $request, DocumentVersion $version)
    {
        // accept multiple possible input keys, normalize at top
        $note = $request->input('rejected_reason') ?? $request->input('notes') ?? $request->input('reason') ?? $request->input('note') ?? null;
        if ($note === null) {
            // validate presence if not provided
            $request->validate([
                'rejected_reason' => 'required|string|max:2000',
            ]);
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
                $version->rejected_at = now();
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
            return response()->json(['success' => true, 'message' => 'Version rejected and returned to Kabag.']);
        }

        return redirect()->route('approval.index')->with('success', 'Version direject dan dikembalikan ke Kabag/draft.');
    }

    /**
     * Alias for reject (keeps compatibility with older route names).
     */
    public function rejectVersion(Request $request, DocumentVersion $version)
    {
        // normalize payload names used across different callers
        $note = $request->input('rejected_reason') ?? $request->input('notes') ?? $request->input('reason') ?? $request->input('note') ?? null;
        if ($note !== null) {
            $request->merge(['rejected_reason' => $note]);
        }

        return $this->reject($request, $version);
    }

    /* ----------------------
       Helper / utility
       ---------------------- */

    /**
     * Simple authorization helper for queue endpoints.
     */
    protected function authorizeQueueAccess(string $role): void
    {
        $user = auth()->user();
        if (! $user || ! $this->userHasAnyRole($user, [$role]) ) {
            abort(Response::HTTP_FORBIDDEN, 'Access denied.');
        }
    }

    /**
     * Safe role check helper: supports spatie hasAnyRole, roles() relation, roles attribute, or email whitelist.
     */
    protected function userHasAnyRole($user, array $roles): bool
    {
        if (! $user) return false;

        // spatie hasAnyRole
        if (method_exists($user, 'hasAnyRole')) {
            try {
                return $user->hasAnyRole($roles);
            } catch (\Throwable $e) { /* fallback */ }
        }

        // relation roles()
        if (method_exists($user, 'roles')) {
            try {
                $names = $user->roles()->pluck('name')->map(fn($n) => Str::lower($n))->toArray();
                foreach ($roles as $r) {
                    if (in_array(Str::lower($r), $names, true)) return true;
                }
            } catch (\Throwable $e) { /* fallback */ }
        }

        // roles attribute (array/collection)
        if (isset($user->roles) && is_iterable($user->roles)) {
            try {
                $names = collect($user->roles)->pluck('name')->map(fn($n) => Str::lower($n))->toArray();
                foreach ($roles as $r) {
                    if (in_array(Str::lower($r), $names, true)) return true;
                }
            } catch (\Throwable $e) { /* fallback */ }
        }

        // email whitelist fallback
        $whitelist = ['direktur@peroniks.com', 'adminqc@peroniks.com'];
        if (! empty($user->email) && in_array(Str::lower($user->email), $whitelist, true)) {
            return true;
        }

        return false;
    }

    protected function normalizeStage(string $stage): string
    {
        $s = Str::upper(trim($stage));
        if (Str::startsWith($s, 'DIR')) return 'DIRECTOR';
        if (Str::startsWith($s, 'MR')) return 'MR';
        if (Str::startsWith($s, 'KAB')) return 'KABAG';
        if ($s === 'DONE') return 'DONE';
        return $s;
    }

    protected function getCurrentRoleName($user): string
    {
        if ($user && method_exists($user, 'getRoleNames')) {
            try {
                $names = $user->getRoleNames();
                return $names->first() ?? 'unknown';
            } catch (\Throwable $e) { /* ignore */ }
        }
        if ($user && method_exists($user, 'roles')) {
            try {
                return $user->roles()->pluck('name')->first() ?? 'unknown';
            } catch (\Throwable $e) { /* ignore */ }
        }
        if ($user && isset($user->roles) && is_iterable($user->roles)) {
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

    /* -----------------------
       Response helpers
       ----------------------- */

    protected function forbiddenResponse(Request $request, string $message = 'Forbidden')
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], Response::HTTP_FORBIDDEN);
        }
        abort(Response::HTTP_FORBIDDEN, $message);
    }
}
