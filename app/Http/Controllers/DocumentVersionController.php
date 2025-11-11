<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentVersionController extends Controller
{
    /**
     * Tampilkan form create (opsional ?document_id=)
     */
    public function create(Request $request)
    {
        $document = null;

        if ($request->filled('document_id')) {
            $document = Document::find($request->query('document_id'));
        }

        return view('versions.create', compact('document'));
    }

    /**
     * Simpan versi baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'document_id'   => ['required', 'integer', 'exists:documents,id'],
            'version_label' => ['required', 'string', 'max:50'],

            // wajib salah satu: file atau pasted_text
            'file'        => ['required_without:pasted_text', 'nullable', 'file', 'mimes:pdf,doc,docx', 'max:51200'],
            'pasted_text' => ['required_without:file', 'nullable', 'string', 'max:500000'],

            'change_note' => ['nullable', 'string', 'max:2000'],
            'signed_by'   => ['nullable', 'string', 'max:191'],
            'signed_at'   => ['nullable', 'date'],
        ]);

        $document = Document::findOrFail($request->input('document_id'));
        $userId   = optional($request->user())->id;

        $filePath = null;
        $fileMime = null;
        $checksum = null;

        if ($request->hasFile('file')) {
            $file   = $request->file('file');
            $folder = 'documents/' . ($document->doc_code ?: 'misc');

            $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext      = $file->getClientOriginalExtension();
            $safeName = time() . '_' . Str::random(6) . '_' . Str::slug($original, '_') . ($ext ? ".{$ext}" : '');

            $filePath = $file->storeAs($folder, $safeName, 'local');
            $fileMime = $file->getMimeType();
            $checksum = hash_file('sha256', $file->getRealPath());
        }

        $version = new DocumentVersion();
        $version->document_id   = $document->id;
        $version->version_label = (string) $request->string('version_label');
        $version->status        = 'draft';
        $version->approval_stage = 'KABAG';
        $version->created_by    = $userId;

        $version->file_path = $filePath;
        $version->file_mime = $fileMime;
        $version->checksum  = $checksum;

        $version->change_note = $request->input('change_note');
        $version->signed_by   = $request->input('signed_by');
        $version->signed_at   = $request->filled('signed_at')
            ? Carbon::parse($request->input('signed_at'))
            : null;

        // simpan teks jika ada (plain_text & pasted_text disamakan)
        if ($request->filled('pasted_text')) {
            $text = (string) $request->input('pasted_text');
            $version->plain_text  = $text;
            $version->pasted_text = $text;
        }

        $version->save();

        return redirect()
            ->route('versions.show', $version)
            ->with('success', 'Version created (draft). You can Submit for Approval when ready.');
    }

    /**
     * Tampilkan form edit
     */
    public function edit(DocumentVersion $version)
    {
        $document = $version->document;

        return view('versions.edit', compact('version', 'document'));
    }

    /**
     * Update versi (boleh ganti file, teks, catatan)
     */
    public function update(Request $request, DocumentVersion $version)
    {
        $request->validate([
            'version_label' => ['required', 'string', 'max:50'],

            // file opsional; jika tidak upload, file lama dipertahankan
            'file'        => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:51200'],
            'pasted_text' => ['nullable', 'string', 'max:500000'],

            'change_note' => ['nullable', 'string', 'max:2000'],
            'signed_by'   => ['nullable', 'string', 'max:191'],
            'signed_at'   => ['nullable', 'date'],
        ]);

        $filePath = $version->file_path;
        $fileMime = $version->file_mime;
        $checksum = $version->checksum;

        if ($request->hasFile('file')) {
            // hapus file lama jika ada
            if ($version->file_path && Storage::disk('local')->exists($version->file_path)) {
                Storage::disk('local')->delete($version->file_path);
            }

            $file   = $request->file('file');
            $folder = 'documents/' . ($version->document->doc_code ?: 'misc');

            $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext      = $file->getClientOriginalExtension();
            $safeName = time() . '_' . Str::random(6) . '_' . Str::slug($original, '_') . ($ext ? ".{$ext}" : '');

            $filePath = $file->storeAs($folder, $safeName, 'local');
            $fileMime = $file->getMimeType();
            $checksum = hash_file('sha256', $file->getRealPath());
        }

        $version->version_label = (string) $request->string('version_label');

        if ($request->has('change_note')) {
            $version->change_note = $request->input('change_note');
        }
        if ($request->has('signed_by')) {
            $version->signed_by = $request->input('signed_by');
        }
        if ($request->has('signed_at')) {
            $version->signed_at = $request->filled('signed_at')
                ? Carbon::parse($request->input('signed_at'))
                : null;
        }
        if ($request->has('pasted_text')) {
            $text = $request->input('pasted_text') ?: null;
            $version->plain_text  = $text;
            $version->pasted_text = $text;
        }

        $version->file_path = $filePath;
        $version->file_mime = $fileMime;
        $version->checksum  = $checksum;

        $version->save();

        return redirect()
            ->route('versions.show', $version)
            ->with('success', 'Version updated.');
    }

    /**
     * Detail satu versi
     */
    public function show(DocumentVersion $version)
    {
        // eager load relasi penting
        $version->load(['document.department', 'creator']);

        // versi lain dari dokumen yang sama (sidebar/list)
        $otherVersions = DocumentVersion::where('document_id', $version->document_id)
            ->orderByDesc('id')
            ->get();

        return view('versions.show', [
            'version'       => $version,
            'document'      => $version->document,
            'otherVersions' => $otherVersions,
        ]);
    }

    /**
     * Submit versi untuk approval (KABAG -> MR)
     * Route name yang diharapkan: versions.submit
     */
    public function submitForApproval($id)
    {
        $user = Auth::user();

        // Hanya kabag/admin yang boleh submit ke MR
        if (! $user || ! method_exists($user, 'hasAnyRole') || ! $user->hasAnyRole(['kabag', 'admin'])) {
            return back()->with('error', 'Anda tidak memiliki izin untuk mengirimkan versi ini.');
        }

        $version = DocumentVersion::with('document')->findOrFail($id);

        // Cegah submit ulang versi final
        if (in_array($version->status, ['approved', 'rejected', 'superseded'], true)) {
            return back()->with('warning', 'Versi ini sudah final dan tidak dapat dikirim.');
        }

        // Set status & stage ke MR
        $version->status = 'submitted';
        $version->approval_stage = 'MR';
        // opsional: kapan dikirim
        if (property_exists($version, 'submitted_at')) {
            $version->submitted_at = now();
        }
        $version->save();

        return back()->with('success', 'Dokumen telah dikirim ke MR untuk persetujuan.');
    }
}
