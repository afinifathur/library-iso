<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentVersion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
// Optional diff renderer
use Jfcherng\Diff\DiffHelper;

class DocumentController extends Controller
{
    /**
     * List dokumen + filter + pagination.
     */
    public function index(Request $request)
    {
        $departments = Department::orderBy('code')->get();

        $categories = [];
        if (class_exists(\App\Models\Category::class)) {
            $categories = \App\Models\Category::orderBy('name')->get();
        }

        $docs = Document::with(['department', 'currentVersion'])
            ->when($request->filled('department'), function ($q) use ($request) {
                $dept = $request->input('department');
                $q->whereHas('department', function ($qb) use ($dept) {
                    $qb->where('code', $dept)->orWhere('id', $dept);
                });
            })
            ->when($request->filled('category_id') || $request->filled('category'), function ($q) use ($request) {
                $cat = $request->filled('category_id') ? $request->input('category_id') : $request->input('category');
                $q->where(function ($qq) use ($cat) {
                    $qq->where('category_id', $cat)->orWhere('category', $cat);
                });
            })
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

        return view('documents.index', compact('docs', 'departments', 'categories'));
    }

    /**
     * Show form untuk buat dokumen baru (baseline).
     */
    public function create()
    {
        $departments = Department::orderBy('code')->get();
        $categories = class_exists(\App\Models\Category::class) ? \App\Models\Category::orderBy('name')->get() : [];
        return view('documents.create', compact('departments', 'categories'));
    }

    /**
     * Show edit form metadata dokumen.
     */
    public function edit($id)
    {
        $document = Document::findOrFail($id);
        $departments = Department::orderBy('code')->get();
        $categories = class_exists(\App\Models\Category::class) ? \App\Models\Category::orderBy('name')->get() : [];
        return view('documents.edit', compact('document','departments','categories'));
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
            'category_id'   => ['nullable','integer','exists:categories,id'],
        ]);

        $document->update($validated);

        return redirect()->route('documents.show', $document->id)->with('success', 'Document info updated.');
    }

    /**
     * Upload PDF/master file -> create new version (draft/submit).
     * (Used by uploader form where user uploads a version)
     */
    public function uploadPdf(Request $request)
    {
        $user = $request->user();
        if (! $user) return redirect()->route('login')->with('error', 'Login required.');

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
            'submit_for'     => 'nullable|in:save,submit',
        ]);

        // find or create document (safe)
        if ($request->filled('document_id')) {
            $document = Document::findOrFail((int)$request->input('document_id'));
        } else {
            // generate doc_code if not provided
            $docCode = $request->input('doc_code') ?: strtoupper(Str::slug($request->input('title'), '-'));
            $document = Document::firstOrCreate(
                ['doc_code' => $docCode],
                ['title' => $request->input('title'), 'department_id' => (int)$request->input('department_id'), 'category_id' => $request->input('category_id') ?? null]
            );
        }

        $disk = Storage::disk('documents');

        // master file
        $master_path = null;
        if ($request->hasFile('master_file')) {
            $master = $request->file('master_file');
            $safeName = $this->safeFilename($master->getClientOriginalName());
            $master_name = now()->timestamp.'_master_'.Str::random(6).'_'.$safeName;
            $master_path = $document->doc_code.'/master/'.$master_name;
            $disk->put($master_path, file_get_contents($master->getRealPath()));
        }

        // pdf file
        $file_path = null;
        $file_mime = null;
        $checksum  = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $safeName = $this->safeFilename($file->getClientOriginalName());
            $filename = now()->timestamp.'_'.Str::random(6).'_'.$safeName;
            $file_path = $document->doc_code.'/'.$request->input('version_label').'/'.$filename;
            $content = file_get_contents($file->getRealPath());
            $disk->put($file_path, $content);
            $file_mime = $file->getClientMimeType() ?: 'application/pdf';
            $checksum = hash('sha256', $content);
        }

        // create version (draft by default)
        $version = DocumentVersion::create([
            'document_id'   => $document->id,
            'version_label' => $request->input('version_label'),
            'status'        => 'draft',
            'approval_stage' => 'KABAG',
            'created_by'    => $user->id,
            'file_path'     => $file_path,
            'file_mime'     => $file_mime,
            'checksum'      => $checksum,
            'change_note'   => $request->input('change_note'),
            'signed_by'     => $request->input('signed_by') ?: $user->name,
            'signed_at'     => $request->date('signed_at'),
        ]);

        // priority: pasted -> extract from master/docx -> extract from pdf (file)
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
                $version->plain_text = $extracted;
                $version->summary_changed = 'Text extracted automatically from uploaded master/pdf.';
            } else {
                $version->summary_changed = 'No text available (please paste or run extractor).';
            }
            $version->save();
        }

        // update document meta
        $document->revision_number = max(1, (int)($document->revision_number ?? 0) + 1);
        $document->revision_date   = now();
        $document->title           = $request->input('title');
        $document->department_id   = (int)$request->input('department_id');
        if ($request->filled('category_id')) {
            $document->category_id = (int)$request->input('category_id');
        }
        $document->save();

        // audit log (optional)
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

        return redirect()->route('documents.show', $document->id)
            ->with('success', 'Version uploaded. Text indexing: ' . ($version->plain_text ? 'available' : 'not available') . '.');
    }

    /**
     * Create NEW DOCUMENT (baseline v1 approved)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'doc_code'       => 'nullable|string|max:120',
            'title'          => 'required|string|max:255',
            'category_id'    => 'required|integer|exists:categories,id',
            'department_id'  => 'required|integer|exists:departments,id',
            'file'           => 'nullable|file|mimes:pdf,doc,docx|max:51200',
            'pasted_text'    => 'nullable|string',
            'version_label'  => 'nullable|string|max:50',
            'approved_at'    => 'nullable|string',
            'approved_by'    => 'nullable|email',
            'created_at'     => 'nullable|string',
            'change_note'    => 'nullable|string|max:2000',
            'doc_number'     => 'nullable|string|max:120',
        ]);

        // generate doc_code if missing
        if (empty($data['doc_code'])) {
            $dept = Department::find($data['department_id']);
            $deptCode = $dept?->code ?? 'MISC';

            $cat = class_exists(\App\Models\Category::class) ? \App\Models\Category::find($data['category_id']) : null;
            $catCode = $cat?->code ?? 'CAT';

            if (method_exists(Document::class, 'generateDocCode')) {
                $data['doc_code'] = Document::generateDocCode($catCode, $deptCode);
            } else {
                $data['doc_code'] = strtoupper($catCode).".".$deptCode.".".
                    str_pad((string)random_int(1,999),3,'0',STR_PAD_LEFT);
            }
        }

        $disk = Storage::disk('documents');

        // save uploaded file (optional)
        $file_path = null;
        $file_mime = null;
        $checksum  = null;

        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $safeName = $this->safeFilename($file->getClientOriginalName());
            $filename = time().'_'.Str::slug(pathinfo($safeName,PATHINFO_FILENAME))
                .'.'.pathinfo($safeName,PATHINFO_EXTENSION);

            $versionLabel = $data['version_label'] ?? 'v1';
            $folder = $data['doc_code'].'/'.$versionLabel;

            $file_path = $folder.'/'.$filename;
            $content   = file_get_contents($file->getRealPath());

            $disk->put($file_path, $content);
            $file_mime = $file->getClientMimeType() ?: 'application/octet-stream';
            $checksum  = hash('sha256', $content);
        }

        // normalize timestamp fields
        $createdAt = $this->parseDateString($this->cleanString($data['created_at'] ?? null)) ?? now();
        $approvedAt = $this->parseDateString($this->cleanString($data['approved_at'] ?? null)) ?? now();

        $approvedByUserId = $user?->id;
        if (!empty($data['approved_by'])) {
            $u = \App\Models\User::where('email', $data['approved_by'])->first();
            if ($u) $approvedByUserId = $u->id;
        }

        $versionLabel = $data['version_label'] ?? 'v1';

        return DB::transaction(function () use ($request, $data, $versionLabel, $createdAt, $approvedAt, $approvedByUserId, $file_path, $file_mime, $checksum, $user) {

          $document = Document::firstOrCreate(
    ['doc_code' => $data['doc_code']],
    [
        'title' => $data['title'],
        'department_id' => (int)$data['department_id'],
        'category_id' => (int)$data['category_id'],
        'doc_number' => $data['doc_number'] ?? null,
    ]
);

// jika sudah ada, kamu bisa update metadata:
$document->update([
    'title' => $data['title'],
    'department_id' => (int)$data['department_id'],
    'category_id' => (int)$data['category_id'],
]);

            // baseline v1 APPROVED
            $version = new DocumentVersion();
            $version->document_id    = $document->id;
            $version->version_label  = $versionLabel;
            $version->status         = 'approved';
            $version->approval_stage = 'DONE';
            $version->created_by     = $user?->id;
            $version->file_path      = $file_path;
            $version->file_mime      = $file_mime;
            $version->checksum       = $checksum;
            $version->change_note    = $data['change_note'] ?? null;

            if ($request->filled('pasted_text')) {
                $clean = $this->normalizeText($request->input('pasted_text'));
                $version->plain_text  = $clean;
                $version->pasted_text = $clean;
            }

            // manual timestamps
            $version->timestamps  = false;
            $version->created_at  = $createdAt;
            $version->updated_at  = $createdAt;
            $version->approved_by = $approvedByUserId;
            $version->approved_at = $approvedAt;
            $version->save();

            // update doc meta
            $document->current_version_id = $version->id;
            $document->revision_number    = 1;
            $document->revision_date      = $approvedAt;
            $document->save();

            return redirect()
                ->route('documents.show', $document->id)
                ->with('success','Document created as baseline ('.$versionLabel.').');
        });
    }

    /**
     * Compare dua versi (fallback to last 2 versi).
     *
     * Accepts:
     * - versions[]=7&versions[]=8
     * - ?v1=7&v2=8
     * - ?version=7 (single -> fallback use latest + provided)
     */
    public function compare(Request $request, $documentId)
    {
        $doc = Document::with('versions')->findOrFail($documentId);

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

        $versions = collect($versionsQuery ?? [])
            ->flatten()
            ->map(fn($v) => is_numeric($v) ? (int)$v : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (count($versions) < 2) {
            // take two latest versions
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
     * Show document detail + versions.
     */
    public function show(Document $document)
    {
        $document->load(['department', 'versions.creator' => fn($q) => $q->orderByDesc('id')]);
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
     * Update metadata + create/update a version (combined form).
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
                ->findOrFail((int)$validated['version_id']);
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
     * Download a version file.
     */
    public function downloadVersion(DocumentVersion $version)
    {
        $disk = Storage::disk('documents');
        if (! $version->file_path || ! $disk->exists($version->file_path)) {
            abort(404);
        }

        return $disk->download($version->file_path, basename($version->file_path));
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
            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'docx_' . uniqid() . '.docx';
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

            return DiffHelper::calculate($text1, $text2, 'Combined', $diffOptions, $rendererOptions);
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

    protected function cleanString(?string $s): ?string
    {
        if ($s === null) return null;
        $s = preg_replace('/\x{FEFF}|\p{C}/u', '', $s);
        return trim($s);
    }

    protected function parseDateString(?string $s): ?\Carbon\Carbon
    {
        if (empty($s)) return null;
        try {
            return Carbon::parse($s);
        } catch (\Throwable) {
            return null;
        }
    }
}
