<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class RecycleController extends Controller
{
    // show list of trashed versions
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) abort(403);

        if (method_exists($user, 'hasAnyRole') && ! $user->hasAnyRole(['admin','mr','director'])) {
            abort(403);
        }

        $rows = DocumentVersion::with('document','creator')
            ->where('status', 'trashed')
            ->orderByDesc('updated_at')
            ->paginate(25);

        return view('recycle.index', compact('rows'));
    }

    // restore a trashed version back to draft (KABAG)
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

        try {
            DB::transaction(function () use ($version, $user, $request) {
                // reload relations
                $version->load('document');

                $doc = $version->document;

                // 1) If document currently points to this version, unset or fallback
                if ($doc && $doc->current_version_id == $version->id) {
                    $fallback = DocumentVersion::where('document_id', $doc->id)
                        ->where('id', '<>', $version->id)
                        ->where('status', 'approved')
                        ->orderByDesc('id')
                        ->first();

                    if ($fallback) {
                        $doc->current_version_id = $fallback->id;
                    } else {
                        $doc->current_version_id = null;
                    }
                    $doc->save();
                }

                // 2) Delete dependent rows in optional tables to avoid FK issues
                if (Schema::hasTable('approval_logs')) {
                    DB::table('approval_logs')->where('document_version_id', $version->id)->delete();
                }
                if (Schema::hasTable('audit_logs')) {
                    DB::table('audit_logs')->where('document_version_id', $version->id)->delete();
                }
                if (Schema::hasTable('revision_history')) {
                    DB::table('revision_history')->where('document_version_id', $version->id)->delete();
                }
                // add other dependent tables here if your app stores references elsewhere

                // 3) delete physical file if exists (safe)
                try {
                    if ($version->file_path && Storage::disk('documents')->exists($version->file_path)) {
                        Storage::disk('documents')->delete($version->file_path);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed deleting file for version '.$version->id.': '.$e->getMessage());
                    // continue anyway
                }

                // 4) Finally delete the version row
                $deleted = $version->delete();

                if (! $deleted) {
                    // fail-safe: throw to rollback if delete didn't occur
                    throw new \Exception('Unable to delete DocumentVersion id='.$version->id);
                }

                // create audit log if available
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
            });

            return back()->with('success','Version permanently deleted.');
        } catch (QueryException $qe) {
            // log detailed DB error for debugging
            Log::error('QueryException while deleting DocumentVersion '.$version->id.': '.$qe->getMessage(), [
                'sql' => $qe->getSql(),
                'bindings' => $qe->getBindings()
            ]);

            // LAST RESORT: attempt to disable FK checks (MySQL) and try again
            try {
                DB::beginTransaction();
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
                // try delete dependent rows again defensively
                if (Schema::hasTable('approval_logs')) {
                    DB::table('approval_logs')->where('document_version_id', $version->id)->delete();
                }
                if (Schema::hasTable('audit_logs')) {
                    DB::table('audit_logs')->where('document_version_id', $version->id)->delete();
                }
                if (Schema::hasTable('revision_history')) {
                    DB::table('revision_history')->where('document_version_id', $version->id)->delete();
                }
                // delete version row
                $version->delete();
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
                DB::commit();

                return back()->with('success','Version permanently deleted (FK_CHECKS disabled).');
            } catch (\Throwable $ex) {
                DB::rollBack();
                Log::error('Final attempt failed deleting DocumentVersion '.$version->id.': '.$ex->getMessage());
                return back()->with('error','Gagal menghapus versi secara permanen. Cek log untuk detail.');
            }
        } catch (\Throwable $e) {
            Log::error('Error deleting DocumentVersion '.$version->id.': '.$e->getMessage());
            return back()->with('error','Gagal menghapus versi: '.$e->getMessage());
        }
    }
}
