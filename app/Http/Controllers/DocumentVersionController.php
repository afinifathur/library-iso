<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

        // gunakan disk 'documents' untuk konsistensi
        $disk = Storage::disk('documents');

        if ($request->hasFile('file')) {
            $file   = $request->file('file');
            $folder = ($document->doc_code ?: 'misc') . '/' . now()->format('Ymd');

            $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext      = $file->getClientOriginalExtension();
            $safeName = $this->safeFilename($original) . ($ext ? ".{$ext}" : '');
            $safeName = now()->timestamp . '_' . Str::random(6) . '_' . $safeName;

            // simpan file di disk 'documents'
            $filePath = trim($folder . '/' . $safeName, '/');
            $disk->put($filePath, file_get_contents($file->getRealPath()));

            $fileMime = $file->getClientMimeType() ?: 'application/octet-stream';
            $checksum = hash_file('sha256', $file->getRealPath());
        }

        $version = new DocumentVersion();
        $version->document_id    = $document->id;
        $version->version_label  = (string) $request->string('version_label');
        $version->status         = 'draft';
        $version->approval_stage = 'KABAG'; // default initial stage
        $version->created_by     = $userId;

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

        $disk = Storage::disk('documents');

        if ($request->hasFile('file')) {
            // hapus file lama jika ada
            if ($version->file_path && $disk->exists($version->file_path)) {
                $disk->delete($version->file_path);
            }

            $file   = $request->file('file');
            $folder = ($version->document->doc_code ?: 'misc') . '/' . now()->format('Ymd');

            $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext      = $file->getClientOriginalExtension();
            $safeName = $this->safeFilename($original) . ($ext ? ".{$ext}" : '');
            $safeName = now()->timestamp . '_' . Str::random(6) . '_' . $safeName;

            $filePath = trim($folder . '/' . $safeName, '/');
            $disk->put($filePath, file_get_contents($file->getRealPath()));

            $fileMime = $file->getClientMimeType() ?: 'application/octet-stream';
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
     *
     * Improved:
     * - cek permission (kabag/admin)
     * - cek status (hanya draft boleh submit)
     * - set next stage sesuai role pengaju (KABAG -> MR, MR -> DIRECTOR, director -> DONE)
     * - simpan submitted_by / submitted_at jika kolom ada
     * - log ke approval_logs bila tersedia
     */
    public function submitForApproval(Request $request, $id)
    {
        $user = Auth::user();

        // permission check: kabag or admin (tolerant)
        if (! $this->userHasAnyRole($user, ['kabag', 'admin'])) {
            return back()->with('error', 'Anda tidak memiliki izin untuk mengirimkan versi ini.');
        }

        $version = DocumentVersion::with('document')->findOrFail($id);

        // Cegah submit ulang versi final
        if (in_array($version->status, ['approved', 'rejected', 'superseded'], true)) {
            return back()->with('warning', 'Versi ini sudah final dan tidak dapat dikirim.');
        }

        // hanya izinkan submit jika masih draft (atau sesuai kebijakan)
        if ($version->status !== 'draft') {
            return back()->with('error', 'Versi ini bukan berstatus draft dan tidak dapat disubmit lagi.');
        }

        // tentukan next stage berdasarkan role user yang mengirim
        $nextStage = 'MR';
        if ($this->userHasAnyRole($user, ['mr'])) {
            $nextStage = 'DIRECTOR';
        } elseif ($this->userHasAnyRole($user, ['director'])) {
            $nextStage = 'DONE';
        }

        DB::transaction(function () use ($version, $user, $nextStage) {
            $version->status = 'submitted';
            $version->approval_stage = $nextStage;

            if (Schema::hasColumn('document_versions', 'submitted_by')) {
                $version->submitted_by = $user->id;
            }
            if (Schema::hasColumn('document_versions', 'submitted_at')) {
                $version->submitted_at = now();
            }

            $version->save();

            // insert into approval_logs table if exists
            $this->insertApprovalLog(
                $version->id,
                $user->id,
                $this->getCurrentRoleName($user),
                'submit',
                'Submitted for approval to ' . $nextStage
            );
        });

        return back()->with('success', 'Dokumen telah dikirim ke ' . $nextStage . ' untuk persetujuan.');
    }

    /* ----------------------
       Helper functions
       ---------------------- */

    /**
     * Cek apakah user punya salah satu role (tolerant: Spatie, relation, property)
     */
    protected function userHasAnyRole($user, array $roles): bool
    {
        if (! $user) return false;

        // spatie:
        if (method_exists($user, 'hasAnyRole')) {
            try {
                if ($user->hasAnyRole($roles)) return true;
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // relation roles()
        if (method_exists($user, 'roles')) {
            try {
                $names = $user->roles()->pluck('name')->map(fn($n) => strtolower($n))->toArray();
                foreach ($roles as $r) {
                    if (in_array(strtolower($r), $names, true)) return true;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // property roles collection
        if (isset($user->roles) && is_iterable($user->roles)) {
            $names = collect($user->roles)->pluck('name')->map(fn($n) => strtolower($n))->toArray();
            foreach ($roles as $r) {
                if (in_array(strtolower($r), $names, true)) return true;
            }
        }

        // fallback whitelist by email (opsional)
        $whitelist = [
            'direktur@peroniks.com',
            'adminqc@peroniks.com',
        ];
        if (! empty($user->email) && in_array(strtolower($user->email), $whitelist, true)) {
            return true;
        }

        return false;
    }

    protected function getCurrentRoleName($user): string
    {
        if (! $user) return 'unknown';

        if (method_exists($user, 'getRoleNames')) {
            try {
                $names = $user->getRoleNames()->toArray();
                return $names[0] ?? 'unknown';
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (method_exists($user, 'roles')) {
            try {
                return $user->roles()->pluck('name')->first() ?? 'unknown';
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (isset($user->roles) && is_iterable($user->roles)) {
            return collect($user->roles)->pluck('name')->first() ?? 'unknown';
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

    /**
     * Amankan nama file (tanpa karakter aneh)
     */
    protected function safeFilename(string $original): string
    {
        $name = preg_replace('/[^\w\.\-]+/u', '_', $original);
        return $name ?: ('file_' . Str::random(8));
    }
}
