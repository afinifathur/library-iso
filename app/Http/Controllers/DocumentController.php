<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Department;
// Opsional (aktif jika paket terpasang): composer require jfcherng/php-diff
use Jfcherng\Diff\DiffHelper;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $departments = Department::orderBy('code')->get();

        $q = Document::with(['department', 'currentVersion']);

        // Filter by department (code atau id)
        if ($request->filled('department')) {
            $dept = $request->input('department');
            $q->whereHas('department', function ($qb) use ($dept) {
                $qb->where('code', $dept)->orWhere('id', $dept);
            });
        }

        // Search: doc_code, title, dan text versi (plain_text / pasted_text)
        if ($request->filled('search')) {
            $s = $request->input('search');
            $q->where(function ($qq) use ($s) {
                $qq->where('doc_code', 'like', "%{$s}%")
                   ->orWhere('title', 'like', "%{$s}%")
                   ->orWhereHas('versions', function ($qv) use ($s) {
                       $qv->where('plain_text', 'like', "%{$s}%")
                          ->orWhere('pasted_text', 'like', "%{$s}%");
                   });
            });
        }

        $docs = $q->orderBy('doc_code')
                  ->paginate(25)
                  ->appends($request->query());

        return view('documents.index', compact('docs', 'departments'));
    }

    public function create()
    {
        $departments = Department::orderBy('code')->get();
        return view('documents.create', compact('departments'));
    }

    /**
     * Upload version:
     * Prioritas teks: pasted_text -> master (docx/xlsx) -> PDF
     */
    public function uploadPdf(Request $request)
    {
        // ==== PATCH: Auth & Role Check (aman, tanpa ketergantungan middleware) ====
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Login required to upload documents.');
        }
        // Jika User pakai Spatie\Permission\HasRoles
        if (method_exists($user, 'hasAnyRole')) {
            if (! $user->hasAnyRole(['mr', 'admin', 'kabag'])) {
                abort(403, 'Anda tidak memiliki hak untuk mengunggah dokumen.');
            }
        } else {
            // Fallback aman jika relasi roles ada tapi tanpa trait
            try {
                $roles = method_exists($user, 'roles') ? $user->roles()->pluck('name')->toArray() : [];
                if (! array_intersect($roles, ['mr', 'admin', 'kabag'])) {
                    abort(403, 'Anda tidak memiliki hak untuk mengunggah dokumen.');
                }
            } catch (\Throwable $e) {
                // Jika tidak ada sistem role sama sekali, default tolak (lebih aman)
                abort(403, 'Role check failed; contact admin.');
            }
        }
        // ==== END PATCH ====

        $request->validate([
            'file'           => 'nullable|file|mimes:pdf|max:51200',
            'master_file'    => 'nullable|file|mimes:doc,docx,xls,xlsx|max:102400',
            'version_label'  => 'required|string|max:50',
            'doc_code'       => 'nullable|string',
            'document_id'    => 'nullable|integer',
            'title'          => 'required|string|max:255',
            'department_id'  => 'required|integer|exists:departments,id',
            'change_note'    => 'nullable|string|max:2000',
            'signed_by'      => 'nullable|string|max:255',
            'signed_at'      => 'nullable|date',
            'pasted_text'    => 'nullable|string|max:200000',
        ]);

        // Find or create document
        if ($request->filled('document_id')) {
            $document = Document::findOrFail($request->input('document_id'));
        } else {
            $docCode = $request->input('doc_code') ?: strtoupper(Str::slug($request->input('title'), '-'));
            $document = Document::firstOrCreate(
                ['doc_code' => $docCode],
                ['title' => $request->input('title'), 'department_id' => $request->input('department_id')]
            );
        }

        $disk = Storage::disk('documents'); // pastikan disk 'documents' terkonfigurasi

        // Simpan master (opsional)
        $master_path = null;
        if ($request->hasFile('master_file')) {
            $master      = $request->file('master_file');
            $master_name = time().'_master_'.Str::random(6).'_'.$master->getClientOriginalName();
            $master_path = $document->doc_code.'/master/'.$master_name;
            $disk->put($master_path, file_get_contents($master->getRealPath()));
            // opsional: $document->master_path = $master_path; $document->save();
        }

        // Simpan signed PDF (opsional)
        $file_path = null;
        $file_mime = null;
        $checksum  = null;
        if ($request->hasFile('file')) {
            $file      = $request->file('file');
            $filename  = time().'_'.Str::random(6).'_'.$file->getClientOriginalName();
            $file_path = $document->doc_code.'/'.$request->input('version_label').'/'.$filename;
            $content   = file_get_contents($file->getRealPath());
            $disk->put($file_path, $content);
            $file_mime = $file->getClientMimeType() ?: 'application/pdf';
            $checksum  = hash('sha256', $content);
        }

        // Buat versi
        $version = DocumentVersion::create([
            'document_id'   => $document->id,
            'version_label' => $request->input('version_label'),
            'status'        => 'draft',
            'created_by'    => $user->id,
            'file_path'     => $file_path,
            'file_mime'     => $file_mime,
            'checksum'      => $checksum,
            'change_note'   => $request->input('change_note'),
            'signed_by'     => $request->input('signed_by') ?: $user->name,
            'signed_at'     => $request->input('signed_at') ?: null,
        ]);

        // ==== Prioritas teks ====
        $extracted = null;

        // 1) pasted_text
        if ($request->filled('pasted_text')) {
            $pasted = $this->normalizeText($request->input('pasted_text'));
            $version->pasted_text     = $pasted;
            $version->plain_text      = $pasted;
            $version->summary_changed = 'Text provided by uploader (pasted).';
            $version->save();
        } else {
            // 2) dari master (DOCX basic unzip)
            if ($master_path && $disk->exists($master_path) && Str::endsWith(strtolower($master_path), '.docx')) {
                try {
                    $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'master_'.$version->id.'.docx';
                    file_put_contents($tmp, $disk->get($master_path));

                    $zip = new \ZipArchive;
                    if ($zip->open($tmp) === true) {
                        $idx = $zip->locateName('word/document.xml');
                        if ($idx !== false) {
                            $data = $zip->getFromIndex($idx);
                            $zip->close();
                            $extracted = $this->normalizeText(strip_tags($data));
                        } else {
                            $zip->close();
                        }
                    }
                    @unlink($tmp);
                } catch (\Throwable $e) {
                    $extracted = null; // lanjut ke PDF
                }
            }

            // 3) dari PDF (jika ada)
            if (!$extracted && $version->file_path && $disk->exists($version->file_path)) {
                try {
                    // gunakan service/command internal bila tersedia
                    $extractor = app()->make(\App\Console\Commands\ExtractDocumentTextCommand::class);
                    $text = $extractor->extractTextForVersion($version, env('PDFTOTEXT_PATH', 'pdftotext'));
                    if ($text) {
                        $extracted = $this->normalizeText($text);
                    }
                } catch (\Throwable $e) {
                    $extracted = null;
                }
            }

            if ($extracted) {
                $version->plain_text      = $extracted;
                $version->summary_changed = 'Text extracted automatically from uploaded master/pdf.';
            } else {
                $version->summary_changed = 'No text available (please paste or run extractor).';
            }
            $version->save();
        }

        // Update metadata dokumen
        $document->revision_number = max(1, (int)($document->revision_number ?? 0) + 1);
        $document->revision_date   = now();
        $document->title           = $request->input('title');
        $document->department_id   = $request->input('department_id');
        $document->save();

        // Audit (opsional)
        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'event'               => 'upload_version',
                'user_id'             => $user->id,
                'document_id'         => $document->id,
                'document_version_id' => $version->id,
                'detail'              => json_encode([
                    'file'   => $file_path,
                    'master' => $master_path,
                    'pasted' => $request->filled('pasted_text'),
                ]),
                'ip' => $request->ip(),
            ]);
        }

        return redirect()
            ->route('documents.show', $document->id)
            ->with('success', 'Version uploaded. Text indexing: ' . ($version->plain_text ? 'available' : 'not available') . '.');
    }

    /**
     * Compare dua versi dokumen.
     * Jika v1/v2 tidak diberikan, otomatis ambil 2 versi terakhir.
     */
    public function compare(Request $request, $documentId)
    {
        $doc = Document::with('versions')->findOrFail($documentId);

        $v1 = $request->query('v1');
        $v2 = $request->query('v2');

        if (!$v1 || !$v2) {
            $versions = $doc->versions()->orderByDesc('id')->take(2)->get();
            if ($versions->count() < 2) {
                return back()->with('error', 'Dokumen ini belum punya 2 versi untuk dibandingkan.');
            }
            $v1 = $versions[1]->id;
            $v2 = $versions[0]->id;
        }

        $ver1 = DocumentVersion::findOrFail($v1);
        $ver2 = DocumentVersion::findOrFail($v2);

        $text1 = $ver1->plain_text ?: ($ver1->pasted_text ?: '(Tidak ada teks)');
        $text2 = $ver2->plain_text ?: ($ver2->pasted_text ?: '(Tidak ada teks)');

        // Generate diff (pakai jfcherng/php-diff jika tersedia)
        $diff = null;
        if (class_exists(DiffHelper::class)) {
            $diffOptions = [
                'context'           => 2,
                'ignoreWhitespace'  => true,
                'ignoreCase'        => false,
            ];
            $rendererOptions = [
                'detailLevel'       => 'line',
                'showHeader'        => false,
                'mergeThreshold'    => 0.8,
                'cliColorization'   => false,
                'outputTagAsString' => false,
            ];

            $diff = DiffHelper::calculate(
                $text1,
                $text2,
                'Combined',
                $diffOptions,
                $rendererOptions
            );
        } else {
            // Fallback bila library belum terpasang
            $diff = '<div style="color:#b45309;background:#fffbeb;border:1px solid #fde68a;padding:8px;border-radius:6px;">
                        Diff library not installed. Run: <code>composer require jfcherng/php-diff</code>
                     </div>';
        }

        return view('documents.compare', [
            'doc'  => $doc,
            'ver1' => $ver1,
            'ver2' => $ver2,
            'diff' => $diff,
        ]);
    }

    public function show($id)
    {
        $doc = Document::with(['versions', 'department'])->findOrFail($id);
        return view('documents.show', compact('doc'));
    }

    // Helper
    protected function normalizeText($text)
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\PC\n\t]/u', ' ', $text);
        $text = preg_replace('/[ \t]{2,}/', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}
