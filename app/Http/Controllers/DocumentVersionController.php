<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\DocumentVersion;
use App\Models\Document;
use App\Models\AuditLog;

class DocumentVersionController extends Controller
{
    /**
     * Download a document version file from "documents" disk.
     */
    public function download($versionId)
    {
        $version = DocumentVersion::findOrFail($versionId);

        $disk = Storage::disk('documents');
        if (! $disk->exists($version->file_path)) {
            abort(404, 'File not found');
        }

        // Optional: gunakan nama file asli dari path
        $downloadName = basename($version->file_path);

        return $disk->download($version->file_path, $downloadName);
    }

    /**
     * Approve version (allowed roles: mr, admin, kabag).
     */
    public function approve(Request $request, $versionId)
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        $version = DocumentVersion::findOrFail($versionId);

        // authorization
        if (! $user->hasAnyRole(['mr', 'admin', 'kabag'])) {
            abort(403, 'Unauthorized');
        }

        // update version
        $version->status       = 'approved';
        $version->reviewed_at  = now();
        $version->reviewed_by  = $user->id;
        $version->review_comment = $request->input('comment'); // opsional
        $version->save();

        // update parent document as current approved revision
        $doc = $version->document;
        if ($doc instanceof Document) {
            $doc->revision_number   = max(1, (int)($doc->revision_number ?? 0) + 1);
            $doc->revision_date     = now();
            if (property_exists($doc, 'current_version_id') || $doc->isFillable('current_version_id')) {
                $doc->current_version_id = $version->id;
            }
            $doc->save();
        }

        // audit log
        AuditLog::create([
            'event'                => 'approve_version',
            'user_id'              => $user->id,
            'document_id'          => $version->document_id,
            'document_version_id'  => $version->id,
            'detail'               => json_encode([
                'message' => 'Version approved',
                'comment' => $request->input('comment'),
            ]),
            'ip'                   => $request->ip(),
        ]);

        return back()->with('success', 'Version approved.');
    }

    /**
     * Reject version (allowed roles: mr, admin, kabag).
     */
    public function reject(Request $request, $versionId)
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        $version = DocumentVersion::findOrFail($versionId);

        // authorization
        if (! $user->hasAnyRole(['mr', 'admin', 'kabag'])) {
            abort(403, 'Unauthorized');
        }

        // require a comment for rejection
        $request->validate([
            'comment' => 'required|string|max:2000',
        ]);

        $version->status         = 'rejected';
        $version->review_comment = $request->input('comment');
        $version->reviewed_at    = now();
        $version->reviewed_by    = $user->id;
        $version->save();

        // audit log
        AuditLog::create([
            'event'                => 'reject_version',
            'user_id'              => $user->id,
            'document_id'          => $version->document_id,
            'document_version_id'  => $version->id,
            'detail'               => json_encode([
                'message' => 'Version rejected',
                'comment' => $request->input('comment'),
            ]),
            'ip'                   => $request->ip(),
        ]);

        return back()->with('success', 'Version rejected and comment saved.');
    }
}
