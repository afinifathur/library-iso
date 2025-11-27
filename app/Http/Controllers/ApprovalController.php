<?php
// File: app/Http/Controllers/ApprovalController.php
namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApprovalController extends Controller
{
    /**
     * Tampilkan queue approval (simple)
     */
    public function index(Request $request)
    {
        // Versi yang perlu tindakan: submitted / under_review (stage bisa berbeda)
        $queue = DocumentVersion::with('document', 'creator')
            ->whereIn('status', ['submitted', 'under_review', 'draft', 'rejected'])
            ->orderByDesc('created_at')
            ->paginate(30);

        // optional label for view
        $stage = $request->query('stage', null);

        return view('approval.index', [
            'pendingVersions' => $queue,
            'stage' => $stage,
        ]);
    }

    /**
     * Approve / forward version.
     * Logic (MVP):
     * - kabag  -> submit (status submitted) -> next stage 'MR'
     * - mr     -> submit -> next stage 'DIR'
     * - director/admin -> approve -> mark version approved and promote to document current_version_id
     */
    public function approve(Request $request, $versionId)
    {
        $user = $request->user();
        if (! $user) {
            return $this->respond($request, false, 'Authentication required.', 403);
        }

        $version = DocumentVersion::with('document')->find($versionId);
        if (! $version) {
            return $this->respond($request, false, 'Version not found.', 404);
        }

        // permission check: kabag/mr/director/admin allowed to act (MVP)
        if (! $this->userHasAnyRole($user, ['kabag','mr','director','admin'])) {
            return $this->respond($request, false, 'You are not authorized to approve.', 403);
        }

        try {
            DB::transaction(function () use ($version, $user) {
                // determine role priority
                if ($this->userHasAnyRole($user, ['director','admin'])) {
                    // final approval
                    $version->status = 'approved';
                    $version->approval_stage = 'DONE';
                    $version->approved_by = $user->id;
                    $version->approved_at = now();
                    $version->save();

                    // promote to document current version
                    $doc = $version->document;
                    if ($doc) {
                        $doc->current_version_id = $version->id;
                        $doc->revision_number = is_numeric($doc->revision_number) ? ((int)$doc->revision_number + 1) : 1;
                        $doc->revision_date = now();
                        $doc->approved_by = $user->id;
                        $doc->approved_at = now();
                        $doc->save();
                    }
                } elseif ($this->userHasAnyRole($user, ['mr'])) {
                    // MR forwards to Director
                    $version->status = 'submitted';
                    $version->approval_stage = 'DIR';
                    $version->submitted_by = $user->id;
                    $version->submitted_at = now();
                    $version->save();
                } else {
                    // KABAG or fallback -> forward to MR
                    $version->status = 'submitted';
                    $version->approval_stage = 'MR';
                    $version->submitted_by = $user->id;
                    $version->submitted_at = now();
                    $version->save();
                }
            });
        } catch (\Throwable $e) {
            return $this->respond($request, false, 'Failed to update version: ' . $e->getMessage(), 500);
        }

        return $this->respond($request, true, 'Version processed successfully.');
    }

    /**
     * Reject version (expects reason). Returns JSON for AJAX or redirect fallback.
     */
    public function reject(Request $request, $versionId)
    {
        $user = $request->user();
        if (! $user) {
            return $this->respond($request, false, 'Authentication required.', 403);
        }

        $version = DocumentVersion::find($versionId);
        if (! $version) {
            return $this->respond($request, false, 'Version not found.', 404);
        }

        // permission check
        if (! $this->userHasAnyRole($user, ['mr','director','admin','kabag'])) {
            return $this->respond($request, false, 'You are not authorized to reject.', 403);
        }

        // Accept reason from body: check common names (notes, reason, rejected_reason)
        $reason = $request->input('notes') ?? $request->input('reason') ?? $request->input('rejected_reason') ?? null;
        if (! $reason || trim($reason) === '') {
            return $this->respond($request, false, 'Alasan reject wajib diisi.', 422);
        }

        try {
            $version->status = 'rejected';
            $version->approval_stage = 'KABAG';
            $version->rejected_by = $user->id;
            $version->rejected_at = now();
            // store in whichever column exists
            if (isset($version->rejected_reason)) {
                $version->rejected_reason = $reason;
            } else {
                $version->reject_reason = $reason;
            }
            $version->save();
        } catch (\Throwable $e) {
            return $this->respond($request, false, 'Failed to reject version: ' . $e->getMessage(), 500);
        }

        return $this->respond($request, true, 'Version rejected and returned to draft.');
    }

    /* ----------------------
       Helpers
       ---------------------- */

    protected function respond(Request $request, bool $ok, string $message, int $status = 200)
    {
        // If AJAX / fetch expects JSON
        if ($request->wantsJson() || $request->ajax() || str_contains($request->header('Accept',''), 'application/json')) {
            return response()->json([
                'success' => $ok,
                'message' => $message,
            ], $status);
        }

        // fallback redirect with flash
        if ($ok) {
            return redirect()->back()->with('success', $message);
        }
        return redirect()->back()->with('error', $message);
    }

    protected function userHasAnyRole($user, array $roles): bool
    {
        if (! $user) return false;

        if (method_exists($user, 'hasAnyRole')) {
            try {
                if ($user->hasAnyRole($roles)) return true;
            } catch (\Throwable) { /* ignore */ }
        }

        if (method_exists($user, 'roles')) {
            try {
                $names = $user->roles()->pluck('name')->map(fn($n) => strtolower($n))->toArray();
                foreach ($roles as $r) {
                    if (in_array(strtolower($r), $names, true)) return true;
                }
            } catch (\Throwable) { /* ignore */ }
        }

        if (isset($user->roles) && is_iterable($user->roles)) {
            $names = collect($user->roles)->pluck('name')->map(fn($n) => strtolower($n))->toArray();
            foreach ($roles as $r) {
                if (in_array(strtolower($r), $names, true)) return true;
            }
        }

        // whitelist emails fallback (opsional)
        $wl = ['direktur@peroniks.com','adminqc@peroniks.com'];
        if (! empty($user->email) && in_array(strtolower($user->email), $wl, true)) return true;

        return false;
    }
}
