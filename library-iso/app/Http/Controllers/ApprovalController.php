<?php

namespace App\Http\Controllers;

use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

class ApprovalController extends Controller
{
    /**
     * Tampilkan antrean approval dengan filter status opsional.
     */
    public function index(Request $request)
    {
        // Selalu ada nilai default
        $status = $request->query('status', 'pending') ?? 'pending';

        $query = DocumentVersion::with(['document', 'creator'])
            ->orderByDesc('created_at');

        // Filter berdasarkan status
        if ($status === 'pending') {
            $query->whereIn('status', ['draft', 'pending', 'submitted']);
        } elseif (in_array($status, ['approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $versions = $query->paginate(20)->withQueryString();

        return view('approval.index', compact('versions', 'status'));
    }

    /**
     * APPROVE: jalankan workflow bertahap (KABAG -> MR -> DIRECTOR -> DONE).
     */
    public function approve(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if (! $this->canApprove($user)) {
            abort(403, 'Unauthorized');
        }

        // Cegah approve ulang pada versi final
        if (in_array($version->status, ['approved', 'rejected'], true)) {
            return back()->with('warning', 'Version already finalized.');
        }

        $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $currentRole = $this->getCurrentRoleName($user);
        $note        = (string) $request->input('note', '');

        // Tentukan next stage berdasarkan stage saat ini
        $nextStage = $this->nextApprovalStage((string) $version->approval_stage);

        DB::transaction(function () use ($version, $user, $note, $nextStage, $currentRole) {
            // Simpan log (aman bila tabel ada)
            $this->insertApprovalLog($version->id, $user->id, $currentRole, 'approve', $note);

            if ($nextStage === 'DONE') {
                // Final approval
                $version->status         = 'approved';
                $version->approval_stage = 'DONE';
                $version->approved_by    = $user->id;
                $version->approved_at    = now();
            } else {
                // Belum final, lempar ke stage berikutnya
                $version->status         = 'submitted';
                $version->approval_stage = $nextStage;
            }

            $version->approval_note = $note;
            $version->save();

            // (Opsional) update revision_date di parent document saat final
            if ($nextStage === 'DONE' && $version->document) {
                $version->document->update([
                    'revision_date' => $version->approved_at ?? now(),
                ]);
            }
        });

        Cache::forget('dashboard.payload');

        return back()->with('success', "Approved as {$currentRole}. Next stage: {$nextStage}");
    }

    /**
     * REJECT: langsung final (DONE) dengan status rejected.
     */
    public function reject(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if (! $this->canApprove($user)) {
            abort(403, 'Unauthorized');
        }

        // Cegah aksi pada versi final
        if (in_array($version->status, ['approved', 'rejected'], true)) {
            return back()->with('warning', 'Version already finalized.');
        }

        $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $currentRole = $this->getCurrentRoleName($user);
        $note        = (string) $request->input('note', '');

        DB::transaction(function () use ($version, $user, $note, $currentRole) {
            $this->insertApprovalLog($version->id, $user->id, $currentRole, 'reject', $note);

            $version->status         = 'rejected';
            $version->approval_stage = 'DONE';
            $version->approval_note  = $note;
            $version->approved_by    = $user->id;
            $version->approved_at    = now();
            $version->save();
        });

        Cache::forget('dashboard.payload');

        return back()->with('success', "Rejected by {$currentRole}.");
    }

    /**
     * Cek otorisasi kemampuan approve.
     */
    protected function canApprove($user): bool
    {
        if (! $user) {
            return false;
        }

        // Jika menggunakan Spatie Roles
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['mr', 'director', 'admin', 'kabag']);
        }

        // Fallback: whitelist email
        return in_array($user->email, [
            'direktur@peroniks.com',
            'adminqc@peroniks.com',
        ], true);
    }

    /**
     * Tentukan stage berikutnya.
     */
    private function nextApprovalStage(string $current): string
    {
        return match (strtoupper($current)) {
            'KABAG'    => 'MR',
            'MR'       => 'DIRECTOR',
            'DIRECTOR' => 'DONE',
            default    => 'KABAG', // jika belum ada stage, mulai dari KABAG
        };
    }

    /**
     * Ambil nama role aktif user (Spatie) bila ada, untuk logging.
     */
    private function getCurrentRoleName($user): string
    {
        if ($user && method_exists($user, 'roles')) {
            return (string) ($user->roles()->pluck('name')->first() ?? 'unknown');
        }
        return 'unknown';
    }

    /**
     * Insert log approval bila tabel tersedia.
     */
    private function insertApprovalLog(int $versionId, int $userId, string $role, string $action, ?string $note = null): void
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
