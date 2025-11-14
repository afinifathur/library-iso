<?php

namespace App\Http\Controllers;

use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApprovalController extends Controller
{
    /**
     * Tampilkan daftar versi yang menunggu approval.
     *
     * Rules:
     * - 'kabag'    => stage KABAG
     * - 'mr'       => stage MR
     * - 'director' => stage DIRECTOR
     * - 'admin'    => semua stage
     *
     * Kompatibilitas:
     * - spatie/laravel-permission (hasRole / hasAnyRole)
     * - relation roles() di model User
     * - fallback: whitelist by email
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $user = $request->user() ?? Auth::user();

        // mapping role -> approval_stage (urutkan prioritas)
        $roleToStage = [
            'kabag'    => 'KABAG',
            'mr'       => 'MR',
            'director' => 'DIRECTOR',
            'admin'    => null, // admin melihat semua
        ];

        // helper: cek role (kompatibel spatie atau relation)
        $hasRole = function ($user, string $roleName): bool {
            if (! $user) return false;

            // 1) spatie
            if (method_exists($user, 'hasRole')) {
                try {
                    if ($user->hasRole($roleName)) return true;
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            if (method_exists($user, 'hasAnyRole')) {
                try {
                    if ($user->hasAnyRole([$roleName])) return true;
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // 2) relation roles()
            if (method_exists($user, 'roles')) {
                try {
                    $names = $user->roles()->pluck('name')->map(fn($n) => Str::lower($n))->toArray();
                    if (in_array(Str::lower($roleName), $names, true)) return true;
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // 3) property roles (collection/array)
            if (isset($user->roles) && is_iterable($user->roles)) {
                try {
                    $names = collect($user->roles)->pluck('name')->map(fn($n) => Str::lower($n))->toArray();
                    if (in_array(Str::lower($roleName), $names, true)) return true;
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // 4) whitelist email
            $whitelist = [
                'direktur@peroniks.com',
                'adminqc@peroniks.com',
            ];
            if (! empty($user->email) && in_array(Str::lower($user->email), array_map('strtolower', $whitelist), true)) {
                if (in_array(Str::lower($roleName), ['admin','director','mr','kabag'], true)) return true;
            }

            return false;
        };

        // tentukan stage berdasarkan role (first match)
        $userStage = null;
        foreach ($roleToStage as $role => $stage) {
            if ($hasRole($user, $role)) {
                $userStage = $stage; // null => admin => semua stage
                break;
            }
        }

        // base query: eager load relasi yang sering dipakai di view
        $query = DocumentVersion::with(['document.department', 'creator', 'document'])
            ->whereIn('status', ['submitted', 'pending', 'in_progress']);

        if ($userStage !== null) {
            // filter sesuai stage (case-insensitive)
            $query->whereRaw('UPPER(COALESCE(approval_stage, \'\')) = ?', [Str::upper($userStage)]);
        }

        $pending = $query->orderByDesc('created_at')->paginate(15);

        // variabel bantu untuk kompatibilitas view lama
        $pendingVersions = $pending;
        $stage = $userStage;
        $userRoleLabel = $userStage ? Str::upper($userStage) : 'ALL';

        return view('approval.index', compact('pending', 'pendingVersions', 'stage', 'userRoleLabel'));
    }

    /**
     * Approve a document version.
     *
     * Route-model binding: DocumentVersion $version
     *
     * @param Request $request
     * @param DocumentVersion $version
     * @return RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function approve(Request $request, DocumentVersion $version)
    {
        $user = $request->user() ?? Auth::user();

        if (! $this->canApprove($user)) {
            return $this->forbiddenResponse($request, 'Unauthorized');
        }

        if (in_array($version->status, ['approved', 'rejected'], true)) {
            return $this->finalizedResponse($request, 'Version already finalized.');
        }

        $validated = $request->validate([
            'note'  => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
        ]);
        $note = $validated['note'] ?? $validated['notes'] ?? null;

        $currentStage = Str::upper($version->approval_stage ?? 'KABAG');
        $nextStage = $this->nextApprovalStage($currentStage);

        if (! $this->roleMatchesStage($user, $currentStage)) {
            return $this->forbiddenResponse($request, 'Anda tidak memiliki izin untuk tindakan ini pada tahap saat ini.');
        }

        DB::transaction(function () use ($version, $user, $note, $nextStage) {
            $this->insertApprovalLog(
                $version->id,
                $user->id,
                $this->getCurrentRoleName($user),
                'approve',
                $note
            );

            if ($nextStage === 'DONE') {
                // final approval
                $version->status = 'approved';
                $version->approval_stage = 'DONE';
                $version->approved_by = $user->id;
                $version->approved_at = now();

                if (Schema::hasColumn($version->getTable(), 'approval_note')) {
                    $version->approval_note = $note;
                }
                if (Schema::hasColumn($version->getTable(), 'approval_notes')) {
                    $version->approval_notes = $note;
                }

                $version->save();

                // update document current version atomically
                $doc = $version->document()->lockForUpdate()->first();
                if ($doc) {
                    if ($doc->current_version_id && $doc->current_version_id != $version->id) {
                        $old = DocumentVersion::find($doc->current_version_id);
                        if ($old && ! in_array($old->status, ['approved', 'rejected', 'superseded'], true)) {
                            $old->status = 'superseded';
                            $old->save();
                        }
                    }

                    // update pointer & revision date if kolom tersedia
                    if (Schema::hasColumn($doc->getTable(), 'current_version_id')) {
                        $doc->current_version_id = $version->id;
                    }
                    if (Schema::hasColumn($doc->getTable(), 'revision_date')) {
                        $doc->revision_date = $version->approved_at ?? now();
                    }
                    $doc->save();
                }
            } else {
                // lanjut ke tahap berikutnya (MR / DIRECTOR)
                $version->status = 'submitted';
                $version->approval_stage = $nextStage;

                if (Schema::hasColumn($version->getTable(), 'approval_note')) {
                    $version->approval_note = $note;
                }
                if (Schema::hasColumn($version->getTable(), 'approval_notes')) {
                    $version->approval_notes = $note;
                }

                $version->save();
            }
        });

        // invalidate cache dashboard
        Cache::forget('dashboard.payload');

        if ($currentStage === 'MR' && $nextStage === 'DIRECTOR') {
            return $this->successResponse($request, 'Dokumen diteruskan ke Direktur untuk persetujuan akhir.');
        }

        return $this->successResponse($request, $nextStage === 'DONE'
            ? 'Dokumen disetujui dan versi ini kini menjadi aktif.'
            : "Approved — next stage: {$nextStage}");
    }

    /**
     * Reject a version and return to Kabag / draft container.
     *
     * @param Request $request
     * @param DocumentVersion $version
     * @return RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function reject(Request $request, DocumentVersion $version)
    {
        $data = $request->validate([
            'notes'  => 'nullable|string|max:2000',
            'reason' => 'nullable|string|max:2000',
        ]);
        $messageNote = $data['notes'] ?? $data['reason'] ?? null;

        $user = $request->user() ?? Auth::user();

        if (in_array($version->status, ['approved', 'rejected'], true)) {
            return $this->finalizedResponse($request, 'Version already finalized.');
        }

        $currentStage = Str::upper($version->approval_stage ?? 'KABAG');
        if (! $this->roleMatchesStage($user, $currentStage)) {
            return $this->forbiddenResponse($request, 'Unauthorized for this stage.');
        }

        DB::transaction(function () use ($version, $user, $messageNote) {
            $this->insertApprovalLog(
                $version->id,
                $user->id,
                $this->getCurrentRoleName($user),
                'reject',
                $messageNote
            );

            $version->status = 'rejected';
            $version->approval_stage = 'KABAG';

            if (Schema::hasColumn($version->getTable(), 'approval_notes')) {
                $version->approval_notes = $messageNote;
            }
            if (Schema::hasColumn($version->getTable(), 'approval_note')) {
                $version->approval_note = $messageNote;
            }

            if (Schema::hasColumn($version->getTable(), 'rejected_by')) {
                $version->rejected_by = $user->id;
            }
            if (Schema::hasColumn($version->getTable(), 'rejected_at')) {
                $version->rejected_at = now();
            }

            $version->save();
        });

        Cache::forget('dashboard.payload');

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Version rejected and returned to Kabag.']);
        }

        return redirect()->route('approval.index')->with('success', 'Dokumen direject dan dikembalikan ke Kabag.');
    }

    /* ----------------------
       Helper / utility
       ---------------------- */

    /**
     * Cek apakah user punya permission approve (spatie or whitelist)
     *
     * @param mixed $user
     * @return bool
     */
    protected function canApprove($user): bool
    {
        if (! $user) return false;

        if (method_exists($user, 'hasAnyRole')) {
            try {
                return $user->hasAnyRole(['mr', 'director', 'admin', 'kabag']);
            } catch (\Throwable $e) {
                // fallback ke whitelist
            }
        }

        // fallback whitelist
        return in_array(Str::lower($user->email ?? ''), [
            'direktur@peroniks.com',
            'adminqc@peroniks.com',
        ], true);
    }

    /**
     * Apakah role user cocok dengan sebuah stage
     */
    protected function roleMatchesStage($user, string $stage): bool
    {
        $s = Str::upper($stage);

        return match ($s) {
            'KABAG'    => $this->userHasAnyRole($user, ['kabag', 'admin']),
            'MR'       => $this->userHasAnyRole($user, ['mr', 'admin']),
            'DIRECTOR' => $this->userHasAnyRole($user, ['director', 'admin']),
            'DONE'     => false,
            default    => false,
        };
    }

    /**
     * Cek beberapa cara untuk mengetahui role user
     */
    protected function userHasAnyRole($user, array $roles): bool
    {
        if (! $user) return false;

        if (method_exists($user, 'hasAnyRole')) {
            try {
                return $user->hasAnyRole($roles);
            } catch (\Throwable $e) {
                // continue fallback
            }
        }

        if (method_exists($user, 'roles')) {
            try {
                $names = $user->roles()->pluck('name')->map(fn($n) => Str::lower($n))->toArray();
                foreach ($roles as $r) {
                    if (in_array(Str::lower($r), $names, true)) return true;
                }
            } catch (\Throwable $e) {
                // fallback
            }
        }

        if (isset($user->roles) && is_iterable($user->roles)) {
            $names = collect($user->roles)->pluck('name')->map(fn($n) => Str::lower($n))->toArray();
            foreach ($roles as $r) {
                if (in_array(Str::lower($r), $names, true)) return true;
            }
        }

        // whitelist
        $whitelist = ['direktur@peroniks.com', 'adminqc@peroniks.com'];
        if (! empty($user->email) && in_array(Str::lower($user->email), $whitelist, true)) {
            return true;
        }

        return false;
    }

    /**
     * Next approval stage after current
     */
    protected function nextApprovalStage(string $current): string
    {
        return match (Str::upper($current)) {
            'KABAG'    => 'MR',
            'MR'       => 'DIRECTOR',
            'DIRECTOR' => 'DONE',
            default    => 'KABAG',
        };
    }

    /**
     * Ambil nama role/label saat ini (untuk logging). Berupa string.
     */
    protected function getCurrentRoleName($user): string
    {
        if ($user && method_exists($user, 'roles')) {
            try {
                return $user->roles()->pluck('name')->first() ?? 'unknown';
            } catch (\Throwable $e) {
                // ignore
            }
        }
        if ($user && isset($user->roles) && is_iterable($user->roles)) {
            return collect($user->roles)->pluck('name')->first() ?? 'unknown';
        }
        if ($user && method_exists($user, 'getRoleNames')) {
            try {
                $names = $user->getRoleNames(); // spatie Collection
                return $names->first() ?? 'unknown';
            } catch (\Throwable $e) {
                // ignore
            }
        }
        return 'unknown';
    }

    /**
     * Insert row ke approval_logs jika tabel tersedia.
     */
    protected function insertApprovalLog(int $versionId, int $userId, string $role, string $action, ?string $note = null): void
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
       Response helper
       ----------------------- */

    protected function forbiddenResponse(Request $request, string $message = 'Forbidden')
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], Response::HTTP_FORBIDDEN);
        }
        abort(Response::HTTP_FORBIDDEN, $message);
    }

    protected function finalizedResponse(Request $request, string $message = 'Already finalized')
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], Response::HTTP_BAD_REQUEST);
        }
        return back()->with('warning', $message);
    }

    protected function successResponse(Request $request, string $message = 'OK')
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }
        return back()->with('success', $message);
    }
}
