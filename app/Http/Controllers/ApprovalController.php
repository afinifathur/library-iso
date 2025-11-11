<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApprovalController extends Controller
{
    /** ===============================
     *  LIST APPROVAL QUEUE
     *  Tampilkan daftar versi berdasarkan role & status.
     *  Menyediakan juga $stage dan $pending (untuk MR/DIRECTOR)
     *  =============================== */
    public function index(Request $request)
    {
        $user   = $request->user();
        $status = $request->query('status', 'pending');

        // Tentukan stage default yang relevan untuk MR/DIRECTOR
        $stage = match ($this->primaryRole($user)) {
            'mr'       => 'MR',
            'director' => 'DIRECTOR',
            default    => null,
        };

        // Kumpulan pending spesifik stage (untuk tampilan cepat MR/DIRECTOR)
        $pending = DocumentVersion::with('document')
            ->when($stage, fn ($q) => $q->where('approval_stage', $stage))
            ->whereIn('status', ['submitted', 'under_review'])
            ->orderByDesc('created_at')
            ->get();

        // Query utama daftar versi (paginasi + filter status/role)
        $query = DocumentVersion::with(['document', 'creator'])
            ->orderByDesc('created_at');

        // Filter berdasarkan status
        if ($status === 'pending') {
            $query->whereIn('status', ['draft', 'pending', 'submitted', 'under_review']);
        } elseif (in_array($status, ['approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        // Filter berdasarkan role
        if ($user->hasAnyRole(['admin'])) {
            // admin lihat semua
        } elseif ($user->hasAnyRole(['mr'])) {
            $query->where('approval_stage', 'MR');
        } elseif ($user->hasAnyRole(['director'])) {
            $query->where('approval_stage', 'DIRECTOR');
        } else {
            // kabag & user biasa lihat punya sendiri + pending stage kabag
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('approval_stage', 'KABAG');
            });
        }

        $versions = $query->paginate(25)->withQueryString();

        return view('approval.index', compact('versions', 'status', 'stage', 'pending'));
    }

    /** ===============================
     *  APPROVE DOCUMENT VERSION
     *  Workflow: KABAG → MR → DIRECTOR → DONE
     *  =============================== */
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

        // Terima "note" atau "notes" (keduanya diterima)
        $note = $validated['note'] ?? $validated['notes'] ?? '';

        $currentStage = strtoupper($version->approval_stage ?? 'KABAG');
        $nextStage    = $this->nextApprovalStage($currentStage);

        // Pastikan role sesuai stage saat ini
        if (! $this->roleMatchesStage($user, $currentStage)) {
            return back()->with('error', 'Anda tidak memiliki izin untuk tindakan ini.');
        }

        DB::transaction(function () use ($version, $user, $note, $nextStage) {

            // LOG
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
                // Move to next stage (KABAG->MR, MR->DIRECTOR)
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

        // Pesan khusus bila MR meneruskan ke Direktur
        if ($currentStage === 'MR' && $nextStage === 'DIRECTOR') {
            return back()->with('success', 'Dokumen diteruskan ke Direktur untuk persetujuan akhir.');
        }

        return back()->with('success', $nextStage === 'DONE'
            ? 'Dokumen disetujui dan versi ini kini menjadi aktif.'
            : "Approved — next stage: {$nextStage}"
        );
    }

    /** ===============================
     *  REJECT DOCUMENT VERSION (UPDATED)
     *  Server-side: notes required + log
     *  =============================== */
    public function reject(Request $request, $versionId)
    {
        // Validasi: notes wajib diisi
        $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        $user = $request->user();

        if (! $this->canApprove($user)) {
            abort(403, 'Unauthorized');
        }

        $version = DocumentVersion::findOrFail($versionId);

        // Cegah aksi pada versi final
        if (in_array($version->status, ['approved', 'rejected'], true)) {
            return back()->with('warning', 'Version already finalized.');
        }

        DB::transaction(function () use ($version, $user, $request) {

            // Catat log (jika tabel ada)
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

            // Update status & stage
            $version->status         = 'rejected';
            $version->approval_stage = 'KABAG';

            // Simpan ke kolom catatan yang tersedia (plural/singular)
            if (Schema::hasColumn('document_versions', 'approval_notes')) {
                $version->approval_notes = $request->input('notes');
            }
            if (Schema::hasColumn('document_versions', 'approval_note')) {
                $version->approval_note = $request->input('notes');
            }

            $version->save();
        });

        Cache::forget('dashboard.payload');

        return redirect()
            ->route('approval.index')
            ->with('success', 'Dokumen direject dan dikembalikan ke Kabag.');
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
            'DONE'     => false, // sudah final
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
