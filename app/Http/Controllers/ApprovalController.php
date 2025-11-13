<?php

namespace App\Http\Controllers;

use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalController extends Controller
{
    /**
     * Tampilkan daftar versi yang menunggu approval.
     *
     * Rules:
     * - Jika user punya role 'kabag' => tampilkan approval_stage = KABAG
     * - 'mr' => MR
     * - 'director' => DIRECTOR
     * - 'admin' (atau management) => tampilkan semua pending
     *
     * Controller ini berusaha kompatibel dengan:
     * - spatie/laravel-permission (hasRole / hasAnyRole)
     * - relation roles() di model User (roles->pluck('name'))
     * - fallback: whitelist by email
     */
    public function index(Request $request)
    {
        $user = $request->user() ?? Auth::user();

        // mapping role -> approval_stage (ubah bila nama stage di DB beda)
        $roleToStage = [
            'kabag'    => 'KABAG',
            'mr'       => 'MR',
            'director' => 'DIRECTOR',
            'admin'    => null, // admin dapat melihat semua
        ];

        // fungsi util untuk cek apakah user punya role (kompatibel spatie atau relation)
        $hasRole = function ($user, string $roleName): bool {
            if (! $user) {
                return false;
            }

            // spatie: hasRole or hasAnyRole
            if (method_exists($user, 'hasRole')) {
                try {
                    if ($user->hasRole($roleName)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    // ignore — fallback to other checks
                }
            }
            if (method_exists($user, 'hasAnyRole')) {
                try {
                    if ($user->hasAnyRole([$roleName])) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // relation-based roles: roles()->pluck('name')
            if (method_exists($user, 'roles')) {
                try {
                    $names = $user->roles()->pluck('name')->map(fn($n) => strtolower($n))->toArray();
                    if (in_array(strtolower($roleName), $names, true)) {
                        return true;
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // if user has property roles as collection
            if (isset($user->roles) && is_iterable($user->roles)) {
                $names = collect($user->roles)->pluck('name')->map(fn($n) => strtolower($n))->toArray();
                if (in_array(strtolower($roleName), $names, true)) {
                    return true;
                }
            }

            // fallback: email whitelist for critical accounts (opsional)
            $whitelist = [
                'direktur@peroniks.com',
                'adminqc@peroniks.com',
            ];
            if (! empty($user->email) && in_array(strtolower($user->email), array_map('strtolower', $whitelist), true)) {
                // treat whitelist users as admin
                if (in_array(strtolower($roleName), ['admin', 'director', 'mr', 'kabag'], true)) {
                    return true;
                }
            }

            return false;
        };

        // determine user stage: pick first matching role in mapping order
        $userStage = null;
        foreach ($roleToStage as $role => $stage) {
            if ($hasRole($user, $role)) {
                $userStage = $stage; // null means admin -> all stages
                break;
            }
        }

        // Build base query: eager load relations used by view
        $query = DocumentVersion::with(['document.department', 'creator', 'document'])
            // hanya status yang benar-benar sedang diajukan
            ->whereIn('status', ['submitted','pending']);

        // jika userStage bukan null => filter by approval_stage (KABAG/MR/DIRECTOR)
        if ($userStage !== null) {
            // Normalize stage to uppercase for safety
            $query->whereRaw('UPPER(COALESCE(approval_stage, \'\')) = ?', [strtoupper($userStage)]);
        }

        // order & paginate
        $pending = $query->orderByDesc('created_at')->paginate(15);

        // provide backward-compatible variable names for view:
        // - $pending (original)
        // - $pendingVersions (used in some blades)
        // - $stage and $userRoleLabel for display
        $pendingVersions = $pending;
        $stage = $userStage;
        $userRoleLabel = $userStage ? strtoupper($userStage) : 'ALL';

        return view('approval.index', compact('pending', 'pendingVersions', 'stage', 'userRoleLabel'));
    }

    /**
     * Approve a document version. Route-model binding for $version.
     */
    public function approve(Request $request, DocumentVersion $version)
    {
        $user = $request->user();

        if (! $this->canApprove($user)) {
            abort(403, 'Unauthorized');
        }

        if (in_array($version->status, ['approved', 'rejected'], true)) {
            return back()->with('warning', 'Version already finalized.');
        }

        $validated = $request->validate([
            'note'  => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
        ]);
        $note = $validated['note'] ?? $validated['notes'] ?? null;

        $currentStage = strtoupper($version->approval_stage ?? 'KABAG');
        $nextStage = $this->nextApprovalStage($currentStage);

        if (! $this->roleMatchesStage($user, $currentStage)) {
            return back()->with('error', 'Anda tidak memiliki izin untuk tindakan ini pada tahap saat ini.');
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
                $version->status = 'approved';
                $version->approval_stage = 'DONE';
                $version->approved_by = $user->id;
                $version->approved_at = now();

                if (Schema::hasColumn('document_versions', 'approval_note')) {
                    $version->approval_note = $note;
                }
                if (Schema::hasColumn('document_versions', 'approval_notes')) {
                    $version->approval_notes = $note;
                }
                $version->save();

                $doc = $version->document()->lockForUpdate()->first();
                if ($doc) {
                    if ($doc->current_version_id && $doc->current_version_id != $version->id) {
                        $old = DocumentVersion::find($doc->current_version_id);
                        if ($old && ! in_array($old->status, ['approved', 'rejected', 'superseded'], true)) {
                            $old->status = 'superseded';
                            $old->save();
                        }
                    }

                    $doc->current_version_id = $version->id;
                    $doc->revision_date = $version->approved_at ?? now();
                    $doc->save();
                }
            } else {
                $version->status = 'submitted';
                $version->approval_stage = $nextStage;

                if (Schema::hasColumn('document_versions', 'approval_note')) {
                    $version->approval_note = $note;
                }
                if (Schema::hasColumn('document_versions', 'approval_notes')) {
                    $version->approval_notes = $note;
                }

                $version->save();
            }
        });

        Cache::forget('dashboard.payload');

        if ($currentStage === 'MR' && $nextStage === 'DIRECTOR') {
            return back()->with('success', 'Dokumen diteruskan ke Direktur untuk persetujuan akhir.');
        }

        return back()->with('success', $nextStage === 'DONE'
            ? 'Dokumen disetujui dan versi ini kini menjadi aktif.'
            : "Approved — next stage: {$nextStage}");
    }

    /**
     * Reject a version.
     */
    public function reject(Request $request, DocumentVersion $version)
    {
        $data = $request->validate([
            // note: frontend menggunakan 'reason' atau 'notes', sesuaikan transform jika perlu
            'notes'  => 'nullable|string|max:2000',
            'reason' => 'nullable|string|max:2000',
        ]);

        $messageNote = $data['notes'] ?? $data['reason'] ?? null;

        $user = $request->user();

        if (in_array($version->status, ['approved', 'rejected'], true)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Version already finalized.'], 400);
            }
            return back()->with('warning', 'Version already finalized.');
        }

        $currentStage = strtoupper($version->approval_stage ?? 'KABAG');
        if (! $this->roleMatchesStage($user, $currentStage)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized for this stage.'], 403);
            }
            abort(403, 'Unauthorized for this stage.');
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

            if (Schema::hasColumn('document_versions', 'approval_notes')) {
                $version->approval_notes = $messageNote;
            }
            if (Schema::hasColumn('document_versions', 'approval_note')) {
                $version->approval_note = $messageNote;
            }

            if (Schema::hasColumn('document_versions', 'rejected_by')) {
                $version->rejected_by = $user->id;
            }
            if (Schema::hasColumn('document_versions', 'rejected_at')) {
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
       Helper functions
       ---------------------- */

    protected function canApprove($user): bool
    {
        if (! $user) return false;

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['mr', 'director', 'admin', 'kabag']);
        }

        // fallback whitelist if no role system
        return in_array(strtolower($user->email ?? ''), [
            'direktur@peroniks.com',
            'adminqc@peroniks.com',
        ], true);
    }

    protected function roleMatchesStage($user, string $stage): bool
    {
        $s = strtoupper($stage);
        return match ($s) {
            'KABAG'    => $this->userHasAnyRole($user, ['kabag', 'admin']),
            'MR'       => $this->userHasAnyRole($user, ['mr', 'admin']),
            'DIRECTOR' => $this->userHasAnyRole($user, ['director', 'admin']),
            'DONE'     => false,
            default    => false,
        };
    }

    protected function userHasAnyRole($user, array $roles): bool
    {
        if (! $user) return false;

        if (method_exists($user, 'hasAnyRole')) {
            try {
                return $user->hasAnyRole($roles);
            } catch (\Throwable $e) {
                // continue to other checks
            }
        }

        if (method_exists($user, 'roles')) {
            try {
                $names = $user->roles()->pluck('name')->map(fn($n) => strtolower($n))->toArray();
                foreach ($roles as $r) {
                    if (in_array(strtolower($r), $names, true)) return true;
                }
            } catch (\Throwable $e) {
                // fallback
            }
        }

        if (isset($user->roles) && is_iterable($user->roles)) {
            $names = collect($user->roles)->pluck('name')->map(fn($n) => strtolower($n))->toArray();
            foreach ($roles as $r) {
                if (in_array(strtolower($r), $names, true)) return true;
            }
        }

        // fallback whitelist
        $whitelist = ['direktur@peroniks.com', 'adminqc@peroniks.com'];
        if (! empty($user->email) && in_array(strtolower($user->email), $whitelist, true)) {
            return true;
        }

        return false;
    }

    protected function nextApprovalStage(string $current): string
    {
        return match (strtoupper($current)) {
            'KABAG'    => 'MR',
            'MR'       => 'DIRECTOR',
            'DIRECTOR' => 'DONE',
            default    => 'KABAG',
        };
    }

    protected function getCurrentRoleName($user): string
    {
        if ($user && method_exists($user, 'roles')) {
            return $user->roles()->pluck('name')->first() ?? 'unknown';
        }
        if ($user && isset($user->roles) && is_iterable($user->roles)) {
            return collect($user->roles)->pluck('name')->first() ?? 'unknown';
        }
        return 'unknown';
    }

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
}
