<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class RecycleController extends Controller
{
    // show list of trashed versions
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) abort(403);

        // only show to admin/mr/director (adjust as needed)
        if (method_exists($user, 'hasAnyRole') && ! $user->hasAnyRole(['admin','mr','director'])) {
            abort(403);
        }

        $rows = DocumentVersion::with('document','creator')
            ->where('status', 'trashed')
            ->orderByDesc('updated_at')
            ->paginate(25);

        return view('recycle.index', compact('rows'));
    }

    // restore a trashed version back to draft (KABAG) — only limited roles
    public function restore(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if (! $user) abort(403);
        if (method_exists($user, 'hasAnyRole') && ! $user->hasAnyRole(['admin','mr','director'])) {
            abort(403);
        }

        if ($version->status !== 'trashed') {
            return back()->with('error','Version is not in Recycle Bin.');
        }

        $version->update([
            'status' => 'draft',
            'approval_stage' => 'KABAG',
        ]);

        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'event'               => 'restore_version',
                'user_id'             => $user->id,
                'document_id'         => $version->document_id,
                'document_version_id' => $version->id,
                'detail'              => json_encode(['note'=>'restored from recycle']),
                'ip' => $request->ip(),
            ]);
        }

        return back()->with('success','Version restored from Recycle Bin.');
    }

    // permanently delete — deletes DB row and file on disk if exists
    public function destroy(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if (! $user) abort(403);
        if (method_exists($user, 'hasAnyRole') && ! $user->hasAnyRole(['admin','mr','director'])) {
            abort(403);
        }

        if ($version->status !== 'trashed') {
            return back()->with('error','Only trashed versions can be permanently deleted.');
        }

        DB::transaction(function () use ($version, $user, $request) {
            // delete physical file if exists
            try {
                if ($version->file_path && Storage::disk('documents')->exists($version->file_path)) {
                    Storage::disk('documents')->delete($version->file_path);
                }
            } catch (\Throwable $e) {
                // ignore disk errors
            }

            // optional audit
            if (class_exists(\App\Models\AuditLog::class)) {
                \App\Models\AuditLog::create([
                    'event'               => 'destroy_version',
                    'user_id'             => $user->id,
                    'document_id'         => $version->document_id,
                    'document_version_id' => $version->id,
                    'detail'              => json_encode(['note'=>'permanently deleted']),
                    'ip' => $request->ip(),
                ]);
            }

            $version->delete();
        });

        return back()->with('success','Version permanently deleted.');
    }
}
