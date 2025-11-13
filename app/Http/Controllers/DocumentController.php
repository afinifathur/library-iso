<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentVersion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
// Optional diff library
use Jfcherng\Diff\DiffHelper;

class DocumentController extends Controller
{
    /**
     * List dokumen + filter.
     */
    public function index(Request $request)
    {
        $departments = Department::orderBy('code')->get();

        // load categories if model exists (optional)
        $categories = [];
        if (class_exists(\App\Models\Category::class)) {
            $categories = \App\Models\Category::orderBy('name')->get();
        }

        $docs = Document::with(['department', 'currentVersion'])
            // filter by department (code or id)
            ->when($request->filled('department'), function ($q) use ($request) {
                $dept = $request->input('department');
                $q->whereHas('department', function ($qb) use ($dept) {
                    $qb->where('code', $dept)->orWhere('id', $dept);
                });
            })
            // filter by category (support both category_id or category string)
            ->when($request->filled('category'), function ($q) use ($request) {
                $cat = $request->input('category');
                $q->where(function ($qq) use ($cat) {
                    $qq->where('category_id', $cat)
                       ->orWhere('category', $cat);
                });
            })
            // search across doc_code, title, and version text (plain/pasted)
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->input('search');
                $q->where(function ($qq) use ($s) {
                    $qq->where('doc_code', 'like', "%{$s}%")
                       ->orWhere('title', 'like', "%{$s}%")
                       ->orWhereHas('versions', function ($qv) use ($s) {
                           $qv->where('plain_text', 'like', "%{$s}%")
                              ->orWhere('pasted_text', 'like', "%{$s}%");
                       });
                });
            })
            ->orderBy('doc_code')
            ->paginate(25)
            ->appends($request->query());

        return view('documents.index', [
            'docs' => $docs,
            'departments' => $departments,
            'categories' => $categories,
        ]);
    }

    /**
     * Form create dokumen.
     */
    public function create()
    {
        $departments = Department::orderBy('code')->get();
        return view('documents.create', compact('departments'));
    }

    /**
     * Form edit info dokumen.
     */
    public function edit($id)
    {
        $document    = Document::findOrFail($id);
        $departments = Department::orderBy('code')->get();

        return view('documents.edit', compact('document', 'departments'));
    }

    /**
     * Update metadata dokumen (bukan versi).
     */
    public function update(Request $request, $id)
    {
        $document = Document::findOrFail($id);

        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'doc_code'      => [
                'required','string','max:80',
                Rule::unique('documents', 'doc_code')->ignore($document->id),
            ],
            'department_id' => ['required','integer','exists:departments,id'],
        ]);

        $document->update($validated);

        return redirect()
            ->route('documents.show', $document->id)
            ->with('success', 'Document info updated.');
    }

    /**
     * Upload versi: prioritas teks pasted -> master(docx) -> pdf.
     */
    public function uploadPdf(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Login required to upload documents.');
        }
        if (! $this->userCanUpload($user)) {
            abort(403, 'Anda tidak memiliki hak untuk mengunggah dokumen.');
        }

        $request->validate([
            'file'           => 'nullable|file|mimes:pdf|max:51200',
            'master_file'    => 'nullable|file|mimes:doc,docx,xls,xlsx|max:102400',
            'version_label'  => 'required|string|max:50',
            'doc_code'       => 'nullable|string|max:80',
            'document_id'    => 'nullable|integer',
            'title'          => 'required|string|max:255',
            'department_id'  => 'required|integer|exists:departments,id',
            'change_note'    => 'nullable|string|max:2000',
            'signed_by'      => 'nullable|string|max:255',
            'signed_at'      => 'nullable|date',
            'pasted_text'    => 'nullable|string|max:200000',
        ]);

        // Find / create document
        if ($request->filled('document_id')) {
            $document = Document::findOrFail((int) $request->input('document_id'));
        } else {
            $docCode = $request->input('doc_code') ?: strtoupper(Str::slug($request->input('title'), '-'));
            $document = Document::firstOrCreate(
                ['doc_code' => $docCode],
                ['title' => $request->input('title'), 'department_id' => (int) $request->input('department_id')]
            );
        }

        $disk = Storage::disk('documents');

        // Save master (optional)
        $master_path = null;
        if ($request->hasFile('master_file')) {
            $master      = $request->file('master_file');
            $safeName    = $this->safeFilename($master->getClientOriginalName());
            $master_name = now()->timestamp.'_master_'.Str::random(6).'_'.$safeName;
            $master_path = $document->doc_code.'/master/'.$master_name;
            $disk->put($master_path, file_get_contents($master->getRealPath()));
        }

        // Save PDF (optional)
        $file_path = null;
        $file_mime = null;
        $checksum  = null;
        if ($request->hasFile('file')) {
            $file      = $request->file('file');
            $safeName  = $this->safeFilename($file->getClientOriginalName());
            $filename  = now()->timestamp.'_'.Str::random(6).'_'.$safeName;
            $file_path = $document->doc_code.'/'.$request->input('version_label').'/'.$filename;
            $content   = file_get_contents($file->getRealPath());
            $disk->put($file_path, $content);
            $file_mime = $file->getClientMimeType() ?: 'application/pdf';
            $checksum  = hash('sha256', $content);
        }

        // Create version
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
            'signed_at'     => $request->date('signed_at'),
        ]);

        // Text extraction priority
        if ($request->filled('pasted_text')) {
            $pasted = $this->normalizeText($request->input('pasted_text'));
            $version->pasted_text     = $pasted;
            $version->plain_text      = $pasted;
            $version->summary_changed = 'Text provided by uploader (pasted).';
            $version->save();
        } else {
            $extracted = null;

            if (! $extracted && $master_path && $disk->exists($master_path) && Str::endsWith(strtolower($master_path), '.docx')) {
                $extracted = $this->extractDocxText($disk->get($master_path));
            }

            if (! $extracted && $version->file_path && $disk->exists($version->file_path)) {
                $extracted = $this->extractPdfText($version);
            }

            if ($extracted) {
                $version->plain_text      = $extracted;
                $version->summary_changed = 'Text extracted automatically from uploaded master/pdf.';
            } else {
                $version->summary_changed = 'No text available (please paste or run extractor).';
            }
            $version->save();
        }

        // Update document meta
        $document->revision_number = max(1, (int)($document->revision_number ?? 0) + 1);
        $document->revision_date   = now();
        $document->title           = $request->input('title');
        $document->department_id   = (int) $request->input('department_id');
        $document->save();

        // Optional audit
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
     * Compare dua versi. Jika kosong, ambil 2 terbaru.
     *
     * Accepts:
     * - ?versions[]=7&versions[]=8
     * - ?version=7 (single)
     * - ?v1=7&v2=8 (legacy)
     */
    public function compare(Request $request, $documentId)
    {
        $doc = Document::with('versions')->findOrFail($documentId);

        // prefer array parameter 'versions[]', fallback to v1/v2 or single 'version' or 'v[]'
        $versionsQuery = $request->query('versions', null);

        if (is_null($versionsQuery)) {
            if ($request->filled('v1') && $request->filled('v2')) {
                $versionsQuery = [$request->query('v1'), $request->query('v2')];
            } elseif ($request->filled('version')) {
                $versionsQuery = [$request->query('version')];
            } elseif ($request->filled('v')) {
                $versionsQuery = $request->query('v');
            }
        }

        // Normalize: ensure array of numeric ids (unique)
        $versions = collect($versionsQuery ?? [])
            ->flatten()
            ->map(fn($v) => is_numeric($v) ? (int)$v : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        // If no selections, fallback to two latest versions
        if (count($versions) < 2) {
            $latest = $doc->versions()->orderByDesc('id')->take(2)->get();
            if ($latest->count() < 2) {
                return back()->with('error', 'Dokumen ini belum punya 2 versi untuk dibandingkan.');
            }
            $ver1 = $latest->last();
            $ver2 = $latest->first();
        } else {
            $versionsData = DocumentVersion::whereIn('id', $versions)
                ->where('document_id', $documentId)
                ->orderBy('id')
                ->get();

            if ($versionsData->count() < 2) {
                return back()->with('error', 'Beberapa versi yang dipilih tidak ditemukan pada dokumen ini atau tidak valid.');
            }

            $ver1 = $versionsData->first();
            $ver2 = $versionsData->last();
        }

        $text1 = $ver1->plain_text ?: ($ver1->pasted_text ?: '(Tidak ada teks)');
        $text2 = $ver2->plain_text ?: ($ver2->pasted_text ?: '(Tidak ada teks)');

        $diff = $this->buildDiff($text1, $text2);

        $selectedVersions = $versions;

        return view('documents.compare', compact('doc', 'ver1', 'ver2', 'diff', 'selectedVersions'));
    }

    /**
     * Detail dokumen + latest version + semua versi.
     */
    public function show(Document $document)
    {
        $document->load(['department', 'versions.creator' => fn ($q) => $q->orderByDesc('id')]);
        $versions = $document->versions->sortByDesc('id')->values();
        $version  = $versions->first();

        return view('documents.show', [
            'document' => $document,
            'versions' => $versions,
            'version'  => $version,
            'doc'      => $document,
        ]);
    }

    /**
     * Update metadata dokumen + buat/update versi terbaru.
     */
    public function updateCombined(Request $request, Document $document)
    {
        $validated = $request->validate([
            'doc_code'       => ['required','string','max:80', Rule::unique('documents','doc_code')->ignore($document->id)],
            'title'          => 'required|string|max:255',
            'department_id'  => 'required|integer|exists:departments,id',
            'version_id'     => 'nullable|integer|exists:document_versions,id',
            'version_label'  => 'required|string|max:50',
            'file'           => 'nullable|file|mimes:pdf,doc,docx|max:51200',
            'pasted_text'    => 'nullable|string|max:800000',
            'change_note'    => 'nullable|string|max:2000',
            'signed_by'      => 'nullable|string|max:191',
            'signed_at'      => 'nullable|date',
            'submit_for'     => 'nullable|in:save,submit',
        ]);

        $document->update([
            'doc_code'      => $validated['doc_code'],
            'title'         => $validated['title'],
            'department_id' => (int) $validated['department_id'],
        ]);

        $version = null;
        if (!empty($validated['version_id'])) {
            $version = DocumentVersion::where('document_id', $document->id)
                        ->findOrFail((int) $validated['version_id']);
        }

        $disk = Storage::disk('documents');

        $file_path = $version->file_path ?? null;
        $file_mime = $version->file_mime ?? null;
        $checksum  = $version->checksum  ?? null;

        if ($request->hasFile('file')) {
            if ($file_path && $disk->exists($file_path)) {
                $disk->delete($file_path);
            }

            $file     = $request->file('file');
            $safeName = $this->safeFilename($file->getClientOriginalName());
            $filename = now()->timestamp.'_'.Str::random(6).'_'.$safeName;
            $folder   = $document->doc_code.'/'.$validated['version_label'];
            $file_path = $folder.'/'.$filename;

            $content   = file_get_contents($file->getRealPath());
            $disk->put($file_path, $content);
            $file_mime = $file->getClientMimeType() ?: 'application/octet-stream';
            $checksum  = hash('sha256', $content);
        }

        if (! $version) {
            $version = new DocumentVersion();
            $version->document_id    = $document->id;
            $version->created_by     = $request->user()->id ?? null;
            $version->status         = ($validated['submit_for'] ?? 'save') === 'submit' ? 'submitted' : 'draft';
            $version->approval_stage = 'KABAG';
        }

        $version->version_label = $validated['version_label'];
        $version->file_path     = $file_path;
        $version->file_mime     = $file_mime;
        $version->checksum      = $checksum;
        $version->change_note   = $validated['change_note'] ?? null;
        $version->signed_by     = $validated['signed_by']   ?? null;
        $version->signed_at     = !empty($validated['signed_at']) ? Carbon::parse($validated['signed_at']) : null;

        if ($request->filled('pasted_text')) {
            $clean = $this->normalizeText($request->input('pasted_text'));
            $version->plain_text  = $clean;
            $version->pasted_text = $clean;
        }

        $version->save();

        Cache::forget('dashboard.payload');

        $msg = 'Document and version saved.';
        if (($validated['submit_for'] ?? 'save') === 'submit') {
            $version->update([
                'status'       => 'submitted',
                'submitted_by' => $request->user()->id ?? null,
                'submitted_at' => now(),
            ]);
            $msg = 'Version submitted for approval.';
        }

        return redirect()->route('documents.show', $document->id)->with('success', $msg);
    }

    /**
     * Unduh berkas versi.
     */
    public function downloadVersion(DocumentVersion $version)
    {
        $disk = Storage::disk('documents');
        if (! $version->file_path || ! $disk->exists($version->file_path)) {
            abort(404);
        }

        return $disk->download($version->file_path, basename($version->file_path));
    }

    /**
     * BUAT DOKUMEN BARU + BASELINE V1 APPROVED.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'doc_code'       => 'nullable|string|max:120',
            'title'          => 'required|string|max:255',
            'category'       => 'required|string|max:20',
            'department_id'  => 'required|integer|exists:departments,id',
            'file'           => 'nullable|file|mimes:pdf,doc,docx|max:51200',
            'pasted_text'    => 'nullable|string',
            'version_label'  => 'nullable|string|max:50',
            'approved_at'    => 'nullable|date',
            'approved_by'    => 'nullable|email',
            'created_at'     => 'nullable|date',
            'change_note'    => 'nullable|string|max:2000',
            'doc_number'     => 'nullable|string|max:120',
        ]);

        if (empty($data['doc_code'])) {
            $dept      = Department::find($data['department_id']);
            $deptCode  = $dept?->code ?? 'MISC';
            if (method_exists(Document::class, 'generateDocCode')) {
                $data['doc_code'] = Document::generateDocCode($data['category'], $deptCode);
            } else {
                $data['doc_code'] = strtoupper($data['category']).'.'.$deptCode.'.'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
            }
        }

        $disk = Storage::disk('documents');

        $file_path = null;
        $file_mime = null;
        $checksum  = null;
        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $safeName = $this->safeFilename($file->getClientOriginalName());
            $filename = time().'_'.Str::slug(pathinfo($safeName, PATHINFO_FILENAME)).'.'.pathinfo($safeName, PATHINFO_EXTENSION);
            $tmpVersionLabel = $data['version_label'] ?? 'v1';
            $folder   = $data['doc_code'].'/'.$tmpVersionLabel;
            $file_path = $folder.'/'.$filename;

            $content   = file_get_contents($file->getRealPath());
            $disk->put($file_path, $content);
            $file_mime = $file->getClientMimeType() ?: 'application/octet-stream';
            $checksum  = hash('sha256', $content);
        }

        $approvedByUserId = $user?->id;
        if (!empty($data['approved_by'])) {
            $u = \App\Models\User::where('email', $data['approved_by'])->first();
            if ($u) {
                $approvedByUserId = $u->id;
            }
        }

        $versionLabel     = $data['version_label'] ?? 'v1';
        $versionCreatedAt = !empty($data['created_at']) ? Carbon::parse($data['created_at']) : now();
        $approvedAt       = !empty($data['approved_at']) ? Carbon::parse($data['approved_at']) : now();

        return DB::transaction(function () use ($request, $data, $versionLabel, $versionCreatedAt, $approvedAt, $approvedByUserId, $file_path, $file_mime, $checksum) {
            $document = Document::create([
                'doc_code'      => $data['doc_code'],
                'title'         => $data['title'],
                'department_id' => (int) $data['department_id'],
                'category'      => $data['category'] ?? null,
                'doc_number'    => $data['doc_number'] ?? null,
            ]);

            $version = new DocumentVersion();
            $version->document_id    = $document->id;
            $version->version_label  = $versionLabel;
            $version->status         = 'approved';
            $version->approval_stage = 'DONE';
            $version->created_by     = $request->user()->id ?? null;
            $version->file_path      = $file_path;
            $version->file_mime      = $file_mime;
            $version->checksum       = $checksum;
            $version->change_note    = $data['change_note'] ?? null;

            if ($request->filled('pasted_text')) {
                $clean = $this->normalizeText($request->input('pasted_text'));
                $version->pasted_text = $clean;
                $version->plain_text  = $clean;
            }

            $version->timestamps  = false;
            $version->created_at  = $versionCreatedAt;
            $version->updated_at  = $versionCreatedAt;
            $version->approved_by = $approvedByUserId;
            $version->approved_at = $approvedAt;
            $version->save();

            $document->current_version_id = $version->id;
            $document->revision_number    = 1;
            $document->revision_date      = $approvedAt;
            $document->save();

            return redirect()
                ->route('documents.show', $document->id)
                ->with('success','Document created as baseline ('.$versionLabel.').');
        });
    }

    /* =========================
     * Helpers
     * ========================= */

    protected function userCanUpload($user): bool
    {
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['mr', 'admin', 'kabag']);
        }

        try {
            $roles = method_exists($user, 'roles') ? $user->roles()->pluck('name')->toArray() : [];
            return (bool) array_intersect($roles, ['mr', 'admin', 'kabag']);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function extractDocxText(string $binary): ?string
    {
        try {
            $tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'docx_'.uniqid().'.docx';
            file_put_contents($tmp, $binary);

            $zip  = new \ZipArchive();
            $text = null;

            if ($zip->open($tmp) === true) {
                $idx = $zip->locateName('word/document.xml');
                if ($idx !== false) {
                    $xml  = $zip->getFromIndex($idx);
                    $zip->close();
                    $text = strip_tags($xml);
                } else {
                    $zip->close();
                }
            }

            @unlink($tmp);
            return $text ? $this->normalizeText($text) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function extractPdfText(DocumentVersion $version): ?string
    {
        try {
            $extractor = app()->make(\App\Console\Commands\ExtractDocumentTextCommand::class);
            $text = $extractor->extractTextForVersion($version, env('PDFTOTEXT_PATH', 'pdftotext'));
            return $text ? $this->normalizeText($text) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function buildDiff(string $text1, string $text2): string
    {
        if (class_exists(DiffHelper::class)) {
            $diffOptions = [
                'context'          => 2,
                'ignoreWhitespace' => true,
                'ignoreCase'       => false,
            ];
            $rendererOptions = [
                'detailLevel'       => 'line',
                'showHeader'        => false,
                'mergeThreshold'    => 0.8,
                'cliColorization'   => false,
                'outputTagAsString' => false,
            ];

            return DiffHelper::calculate(
                $text1,
                $text2,
                'Combined',
                $diffOptions,
                $rendererOptions
            );
        }

        return '<div class="alert alert-warning mb-0">Diff library not installed. Run: <code>composer require jfcherng/php-diff</code></div>';
    }

    protected function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[^\PC\n\t]/u', ' ', $text);
        $text = preg_replace('/[ \t]{2,}/', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    protected function safeFilename(string $original): string
    {
        $name = preg_replace('/[^\w\.\-]+/u', '_', $original);
        return $name ?: ('file_'.Str::random(8));
    }
}
