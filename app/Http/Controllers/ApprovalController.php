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
     * LIST APPROVAL QUEUE (versi simple)
     * - Tentukan $stage dari peran user (MR/DIRECTOR)
     * - Ambil daftar $pending berdasarkan status umum dan (jika ada) stage
     * - Paginasi 15 item
     */
    public function index()
    {
        $user = Auth::user();

        // tentukan stage berdasarkan peran pengguna
        $stage = null;
        if (method_exists($user, 'hasRole') && $user->hasRole('mr')) {
            $stage = 'MR';
        } elseif (method_exists($user, 'hasRole') && ($user->hasRole('director') || $user->hasRole('direktur'))) {
            $stage = 'DIRECTOR';
        }

        // build query (include creator relation for display)
        $query = DocumentVersion::with(['document', 'creator'])
            ->whereIn('status', ['submitted', 'under_review', 'draft', 'rejected']);

        // jika ada stage (MR/DIRECTOR) filter per stage
        if ($stage) {
            $query->where('approval_stage', $stage);
        }

        // order and paginate
        $pending = $query->orderByDesc('created_at')->paginate(15);

        return view('approval.index', compact('pending', 'stage'));
    }

    /**
     * APPROVE DOCUMENT VERSION
     * Workflow: KABAG → MR → DIRECTOR → DONE
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

        $note = $validated['note'] ?? $validated['notes'] ?? '';

        $currentStage = strtoupper($version->approval_stage ?? 'KABAG');
        $nextStage    = $this->nextApprovalStage($currentStage);

        if (! $this->roleMatchesStage($user, $currentStage)) {
            return back()->with('error', 'Anda tidak memiliki izin untuk tindakan ini.');
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
                // FINAL APPROVAL
                $version->status         = 'approved';
                $version->approval_stage = 'DONE';
                $version->approved_by    = $user->id;
                $version->approved_at    = now();

                // simpan ke kolom catatan yang tersedia
                if (Schema::hasColumn('document_versions', 'approval_note')) {
                    $version->approval_note = $note;
                }
                if (Schema::hasColumn('document_versions', 'approval_notes')) {
                    $version->approval_notes = $note;
                }

                $version->save();

                // Lock dokumen dan set current_version + supersede versi lama
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
                    $doc->revision_date      = $version->approved_at ?? now();
                    $doc->save();
                }
            } else {
                // Move to next stage
                $version->status         = 'submitted';
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
            : "Approved — next stage: {$nextStage}"
        );
    }

    /**
     * REJECT DOCUMENT VERSION (Opsi A: parameter $versionId)
     */
    public function reject(Request $request, $versionId)
    {
        $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        $user    = $request->user();
        $version = DocumentVersion::with('document')->findOrFail($versionId);

        if (in_array($version->status, ['approved', 'rejected'], true)) {
            return back()->with('warning', 'Version already finalized.');
        }

        DB::transaction(function () use ($version, $user, $request) {

            if (Schema::hasTable('approval_logs')) {
                DB::table('approval_logs')->insert([
                    'document_version_id' => $version->id,
                    'user_id'             => $user->id,
                    'role'                => $user->roles()->pluck('name')->first() ?? null,
                    'action'              => 'reject',
                    'note'                => $request->input('notes'),
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            $version->status         = 'rejected';
            $version->approval_stage = 'KABAG';

            if (Schema::hasColumn('document_versions', 'approval_notes')) {
                $version->approval_notes = $request->input('notes');
            }
            if (Schema::hasColumn('document_versions', 'approval_note')) {
                $version->approval_note = $request->input('notes');
            }

            $version->save();
        });

        Cache::forget('dashboard.payload');

        return redirect()->route('approval.index')
                         ->with('success','Dokumen direject dan dikembalikan ke Kabag.');
    }

    /* ======================================================
       HELPER FUNCTIONS
       ====================================================== */

    protected function canApprove($user): bool
    {
        if (! $user) return false;

        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['mr', 'director', 'admin', 'kabag']);
        }

        // fallback whitelist
        return in_array($user->email, [
            'direktur@peroniks.com',
            'adminqc@peroniks.com',
        ], true);
    }

    /**
     * Apakah role user sesuai dengan stage saat ini?
     * KABAG: kabag/admin; MR: mr/admin; DIRECTOR: director/admin
     */
    protected function roleMatchesStage($user, string $stage): bool
    {
        return match (strtoupper($stage)) {
            'KABAG'    => $user->hasAnyRole(['kabag', 'admin']),
            'MR'       => $user->hasAnyRole(['mr', 'admin']),
            'DIRECTOR' => $user->hasAnyRole(['director', 'admin']),
            'DONE'     => false,
            default    => false,
        };
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
        return 'unknown';
    }

    protected function primaryRole($user): ?string
    {
        if ($user && method_exists($user, 'roles')) {
            return $user->roles()->pluck('name')->first();
        }
        return null;
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
