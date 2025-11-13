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
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Tentukan stage berdasarkan role user (opsional, jika sistem role tersedia)
        $stage = null;
        if (method_exists($user, 'hasRole') && $user->hasRole('mr')) {
            $stage = 'MR';
        } elseif (method_exists($user, 'hasRole') && ($user->hasRole('director') || $user->hasRole('direktur'))) {
            $stage = 'DIRECTOR';
        }

        // Build query: ambil versi yang relevan untuk approval
        $query = DocumentVersion::with(['document.department', 'creator'])
            ->whereIn('status', ['submitted', 'under_review', 'draft']);

        if ($stage) {
            $query->where('approval_stage', $stage);
        }

        $pending = $query->orderByDesc('created_at')->paginate(15);

        return view('approval.index', compact('pending', 'stage'));
    }

    /**
     * Approve a document version. Route-model binding for $version.
     *
     * Workflow: KABAG -> MR -> DIRECTOR -> DONE
     */
    public function approve(Request $request, DocumentVersion $version)
    {
        $user = $request->user();

        // basic permission
        if (! $this->canApprove($user)) {
            abort(403, 'Unauthorized');
        }

        // don't allow approving already finalized versions
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

        // role-stage check
        if (! $this->roleMatchesStage($user, $currentStage)) {
            return back()->with('error', 'Anda tidak memiliki izin untuk tindakan ini pada tahap saat ini.');
        }

        DB::transaction(function () use ($version, $user, $note, $nextStage) {
            // insert approval log if table exists
            $this->insertApprovalLog(
                $version->id,
                $user->id,
                $this->getCurrentRoleName($user),
                'approve',
                $note
            );

            if ($nextStage === 'DONE') {
                // final approve -> set approved fields and update document current_version
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

                // lock document and update current_version_id, supersede old if needed
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
                // move to next stage but still not final
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

        // refresh any cached dashboard payload
        Cache::forget('dashboard.payload');

        // friendly message
        if ($currentStage === 'MR' && $nextStage === 'DIRECTOR') {
            return back()->with('success', 'Dokumen diteruskan ke Direktur untuk persetujuan akhir.');
        }

        return back()->with('success', $nextStage === 'DONE'
            ? 'Dokumen disetujui dan versi ini kini menjadi aktif.'
            : "Approved — next stage: {$nextStage}");
    }

    /**
     * Reject a version.
     * Accepts JSON/AJAX or regular form POST.
     */
    public function reject(Request $request, DocumentVersion $version)
    {
        $data = $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        $user = $request->user();

        if (in_array($version->status, ['approved', 'rejected'], true)) {
            // respond according to request type
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Version already finalized.'], 400);
            }
            return back()->with('warning', 'Version already finalized.');
        }

        // optional: check if user allowed to reject at current stage
        $currentStage = strtoupper($version->approval_stage ?? 'KABAG');
        if (! $this->roleMatchesStage($user, $currentStage)) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Unauthorized for this stage.'], 403);
            }
            abort(403, 'Unauthorized for this stage.');
        }

        DB::transaction(function () use ($version, $user, $data) {
            // log to approval_logs if table exists
            if (Schema::hasTable('approval_logs')) {
                DB::table('approval_logs')->insert([
                    'document_version_id' => $version->id,
                    'user_id'             => $user->id,
                    'role'                => $this->getCurrentRoleName($user),
                    'action'              => 'reject',
                    'note'                => $data['notes'],
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);
            }

            // update version
            $version->status = 'rejected';
            // reset stage to KABAG (so creator sees it), bisa disesuaikan
            $version->approval_stage = 'KABAG';
            if (Schema::hasColumn('document_versions', 'approval_notes')) {
                $version->approval_notes = $data['notes'];
            }
            if (Schema::hasColumn('document_versions', 'approval_note')) {
                $version->approval_note = $data['notes'];
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
        return in_array($user->email, [
            'direktur@peroniks.com',
            'adminqc@peroniks.com',
        ], true);
    }

    /**
     * Cek apakah role user cocok untuk stage ini.
     */
    protected function roleMatchesStage($user, string $stage): bool
    {
        return match (strtoupper($stage)) {
            'KABAG'    => method_exists($user, 'hasAnyRole') ? $user->hasAnyRole(['kabag', 'admin']) : true,
            'MR'       => method_exists($user, 'hasAnyRole') ? $user->hasAnyRole(['mr', 'admin']) : true,
            'DIRECTOR' => method_exists($user, 'hasAnyRole') ? $user->hasAnyRole(['director', 'admin']) : true,
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

    /**
     * Insert an approval log row if table exists.
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
}
