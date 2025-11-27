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
            ->when($request->filled('department'), function ($q) use ($request) {
                $dept = $request->input('department');
                $q->whereHas('department', function ($dq) use ($dept) {
                    $dq->where('code', $dept)->orWhere('id', $dept);
                });
            })
            ->when($request->filled('category_id') || $request->filled('category'), function ($q) use ($request) {
                $cat = $request->filled('category_id')
                    ? $request->input('category_id')
                    : $request->input('category');

                $q->where(function ($qq) use ($cat) {
                    $qq->where('category_id', $cat)
                       ->orWhere('category', $cat);
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

        return view('documents.index', [
            'docs'        => $docs,
            'departments' => $departments,
            'categories'  => $categories,
        ]);
    }

    /**
     * Form create dokumen.
     */
    public function create()
    {
        $departments = Department::orderBy('code')->get();
        $categories = [];
        if (class_exists(\App\Models\Category::class)) {
            $categories = \App\Models\Category::orderBy('name')->get();
        }
        return view('documents.create', compact('departments', 'categories'));
    }

    /**
     * Form edit info dokumen.
     */
    public function edit($id)
    {
        $document    = Document::findOrFail($id);
        $departments = Department::orderBy('code')->get();
        $categories  = [];
        if (class_exists(\App\Models\Category::class)) {
            $categories = \App\Models\Category::orderBy('name')->get();
        }

        return view('documents.edit', compact('document', 'departments', 'categories'));
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
                'required',
                'string',
                'max:120',
                Rule::unique('documents', 'doc_code')->ignore($document->id),
            ],
            'department_id' => ['required','integer','exists:departments,id'],
            'category_id'   => ['nullable','integer','exists:categories,id'],
            'related_links' => ['nullable','string'],
        ]);

        $document->update([
            'title'         => $validated['title'],
            'doc_code'      => $validated['doc_code'],
            'department_id' => $validated['department_id'],
            'category_id'   => $validated['category_id'] ?? $document->category_id,
        ]);

        if ($request->has('related_links')) {
            $raw   = $request->input('related_links', '');
            $links = $this->parseRelatedLinksInput($raw);
            $document->related_links = $links;
            $document->save();
        }

        return redirect()
            ->route('documents.show', $document->id)
            ->with('success', 'Document info updated.');
    }

    /**
     * Upload versi: prioritas teks pasted -> master(docx) -> pdf.
     * Prevent multiple drafts by same uploader for same document.
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
            'master_file'    => 'nullable|file|mimes:doc,docx|max:102400',
            'version_label'  => 'required|string|max:50',
            'doc_code'       => 'nullable|string|max:80',
            'document_id'    => 'nullable|integer',
            'title'          => 'required|string|max:255',
            'department_id'  => 'required|integer|exists:departments,id',
            'change_note'    => 'nullable|string|max:2000',
            'signed_by'      => 'nullable|string|max:255',
            'signed_at'      => 'nullable|date',
            'pasted_text'    => 'nullable|string|max:200000',
            'related_links'  => 'nullable|string',
        ]);

        // Find / create document
        if ($request->filled('document_id')) {
            $document = Document::findOrFail((int) $request->input('document_id'));
        } else {
            $docCode = $request->input('doc_code') ?: strtoupper(Str::slug($request->input('title'), '-'));
            $document = Document::firstOrCreate(
                ['doc_code' => $docCode],
                [
                    'title'         => $request->input('title'),
                    'department_id' => (int) $request->input('department_id'),
                ]
            );
        }

        // simpan related_links kalau dikirim
        if ($request->has('related_links')) {
            $raw   = $request->input('related_links', '');
            $links = $this->parseRelatedLinksInput($raw);
            $document->related_links = $links;
            $document->save();
        }

        // choose disk (prefer 'documents' disk, fallback to 'public')
        $disk = null;
        try {
            $disk = Storage::disk('documents');
        } catch (\Throwable $e) {
            $disk = Storage::disk('public');
        }

        // Save master (optional)
        $master_path = null;
        if ($request->hasFile('master_file')) {
            $master      = $request->file('master_file');
            $safeName    = $this->safeFilename($master->getClientOriginalName());
            $master_name = now()->timestamp . '_master_' . Str::random(6) . '_' . $safeName;
            $master_path = $document->doc_code . '/master/' . $master_name;
            $disk->put($master_path, file_get_contents($master->getRealPath()));
        }

        // Save PDF (optional)
        $file_path = null;
        $file_mime = null;
        $checksum  = null;
        if ($request->hasFile('file')) {
            $file      = $request->file('file');
            $safeName  = $this->safeFilename($file->getClientOriginalName());
            $filename  = now()->timestamp . '_' . Str::random(6) . '_' . $safeName;
            $file_path = $document->doc_code . '/' . $request->input('version_label') . '/' . $filename;
            $content   = file_get_contents($file->getRealPath());
            $disk->put($file_path, $content);
            $file_mime = $file->getClientMimeType() ?: 'application/pdf';
            $checksum  = hash('sha256', $content);
        }

        // === Prevent previous draft by same user for this document ===
        DocumentVersion::where('document_id', $document->id)
            ->where('created_by', $user->id)
            ->whereIn('status', ['draft','rejected'])
            ->delete();

        // Create version
        $version = DocumentVersion::create([
            'document_id'   => $document->id,
            'version_label' => $request->input('version_label'),
            'status'        => 'draft',
            'approval_stage'=> 'KABAG',
            'created_by'    => $user->id,
            'file_path'     => $file_path,
            'file_mime'     => $file_mime,
            'checksum'      => $checksum,
            'change_note'   => $request->input('change_note'),
            'signed_by'     => $request->input('signed_by') ?: $user->name,
            'signed_at'     => $this->parseDateString($request->input('signed_at')),
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

        // Update document meta (do NOT set as current version here)
        $document->revision_number = max(1, (int) ($document->revision_number ?? 0) + 1);
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
                'ip'                  => $request->ip(),
            ]);
        }

        return redirect()
            ->route('documents.show', $document->id)
            ->with('success', 'Version uploaded. Text indexing: ' . ($version->plain_text ? 'available' : 'not available') . '.');
    }

    /**
     * Compare dua versi. Jika kosong, ambil 2 terbaru.
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
            ->map(fn($v) => is_numeric($v) ? (int) $v : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

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
     * Choose compare page: show approved/current versions to pick as baseline.
     */
    public function chooseCompare($versionId)
    {
        $version  = DocumentVersion::with('document')->findOrFail($versionId);
        $document = $version->document;

        $candidates = $document->versions()
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->get();

        return view('versions.choose_compare', compact('version', 'document', 'candidates'));
    }

    /**
     * Approve a version (MR/Director).
     */
    public function approveVersion(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['mr'])) {
            $version->update([
                'status'         => 'submitted',
                'approval_stage' => 'DIR',
                'submitted_by'   => $user->id,
                'submitted_at'   => now(),
            ]);

            return back()->with('success', 'Version forwarded to Director.');
        }

        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['director', 'admin'])) {
            DB::transaction(function () use ($version, $user) {
                $version->update([
                    'status'         => 'approved',
                    'approval_stage' => 'DONE',
                    'approved_by'    => $user->id,
                    'approved_at'    => now(),
                ]);

                $doc = $version->document;
                $doc->update([
                    'current_version_id' => $version->id,
                    'revision_number'    => $this->incRevision($doc->revision_number),
                    'revision_date'      => now(),
                    'approved_by'        => $user->id,
                    'approved_at'        => now(),
                ]);
            });

            return back()->with('success', 'Version approved and promoted to current.');
        }

        return back()->with('error', 'You are not authorized to approve.');
    }

    /**
     * Reject a version (MR/Director).
     */
    public function rejectVersion(Request $request, DocumentVersion $version)
    {
        $request->validate(['rejected_reason' => 'required|string|max:2000']);
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if (method_exists($user, 'hasAnyRole') && ! $user->hasAnyRole(['mr', 'director', 'admin'])) {
            abort(403);
        }

        $version->update([
            'status'          => 'rejected',
            'approval_stage'  => 'KABAG',
            'rejected_by'     => $user->id,
            'rejected_at'     => now(),
            'rejected_reason' => $request->input('rejected_reason'),
        ]);

        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'event'               => 'reject_version',
                'user_id'             => $user->id,
                'document_id'         => $version->document_id,
                'document_version_id' => $version->id,
                'detail'              => json_encode(['reason' => $request->input('rejected_reason')]),
                'ip'                  => $request->ip(),
            ]);
        }

        return back()->with('success', 'Version rejected and returned to draft.');
    }

    /**
     * Detail dokumen + latest version + semua versi.
     *
     * NOTE: This method injects defensive placeholders for potentially-null
     * relations/properties (e.g. signature) so views that access them directly
     * won't crash. It's recommended to update views to use optional() instead.
     */
    public function show(Document $document)
    {
        $document->load(['department', 'versions.creator' => fn ($q) => $q->orderByDesc('id')]);

        $versions = $document->versions->sortByDesc('id')->values();
        $version  = $versions->first() ?: null;

        // Build related links (unchanged)
        $relatedLinks = [];
        $rawLinks     = null;

        if (isset($document->related_links)) {
            $raw = $document->related_links;

            if (is_array($raw)) {
                $rawLinks = $raw;
            } elseif (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $rawLinks = $decoded;
                } elseif (trim($raw) !== '') {
                    // single URL string lama
                    $rawLinks = [$raw];
                }
            }
        }

        if (!empty($rawLinks) && is_array($rawLinks)) {
            foreach ($rawLinks as $url) {
                if (!is_string($url) || trim($url) === '') {
                    continue;
                }

                $url   = trim($url);
                $label = $url;
                $docId = null;

                // try detect /documents/{id}
                if (preg_match('#/documents/(\d+)#', $url, $m)) {
                    $docId = (int) $m[1];
                    $docRef = Document::find($docId);
                    if ($docRef) {
                        $code  = $docRef->doc_code ?: '';
                        $title = $docRef->title ?: '';
                        $label = trim(($code ? $code.' — ' : '').$title) ?: $url;
                    } else {
                        $label = $url;
                        $docId = null;
                    }
                } else {
                    $short = preg_replace('#^https?://#i', '', $url);
                    $label = strlen($short) > 48 ? substr($short, 0, 45).'...' : $short;
                }

                $relatedLinks[] = [
                    'url'    => $url,
                    'label'  => $label,
                    'doc_id' => $docId,
                ];
            }
        }

        // Defensive placeholders: if views access ->signature or similar relations directly,
        // ensure an object exists to avoid "Attempt to read property ... on null".
        // Prefer to update views to use optional() — this is a backward-compatible safety.
        if ($version && (! property_exists($version, 'signature') || $version->signature === null)) {
            // inject lightweight placeholder object
            $version->signature = (object) [
                'signed_at' => null,
                'signed_by' => null,
                // add other expected props if needed
            ];
        }

        if (! property_exists($document, 'signature') || $document->signature === null) {
            // add placeholder at document-level as well (if view uses $document->signature)
            $document->signature = (object) [
                'signed_at' => null,
                'signed_by' => null,
            ];
        }

        return view('documents.show', [
            'document'     => $document,
            'versions'     => $versions,
            'version'      => $version,
            'doc'          => $document,
            'relatedLinks' => $relatedLinks,
        ]);
    }

    /**
     * Update metadata dokumen + buat/update versi terbaru.
     */
    public function updateCombined(Request $request, Document $document)
    {
        $validated = $request->validate([
            'doc_code'      => ['required', 'string', 'max:80', Rule::unique('documents', 'doc_code')->ignore($document->id)],
            'title'         => 'required|string|max:255',
            'department_id' => 'required|integer|exists:departments,id',
            'category_id'   => 'nullable|integer|exists:categories,id',
            'version_id'    => 'nullable|integer|exists:document_versions,id',
            'version_label' => 'required|string|max:50',
            'file'          => 'nullable|file|mimes:pdf,doc,docx|max:51200',
            'pasted_text'   => 'nullable|string|max:800000',
            'change_note'   => 'nullable|string|max:2000',
            'signed_by'     => 'nullable|string|max:191',
            'signed_at'     => 'nullable|date',
            'submit_for'    => 'nullable|in:save,submit',
            'related_links' => 'nullable|string',
        ]);

        $document->update([
            'doc_code'      => $validated['doc_code'],
            'title'         => $validated['title'],
            'department_id' => (int) $validated['department_id'],
            'category_id'   => $validated['category_id'] ?? $document->category_id,
        ]);

        if ($request->has('related_links')) {
            $raw   = $request->input('related_links', '');
            $links = $this->parseRelatedLinksInput($raw);
            $document->related_links = $links;
            $document->save();
        }

        $user = $request->user();
        $disk = null;
        try {
            $disk = Storage::disk('documents');
        } catch (\Throwable $e) {
            $disk = Storage::disk('public');
        }

        $version = null;

        if (!empty($validated['version_id'])) {
            $version = DocumentVersion::where('document_id', $document->id)
                ->where('id', $validated['version_id'])
                ->first();
        }

        if (! $version) {
            $version = DocumentVersion::where('document_id', $document->id)
                ->where('status', 'draft')
                ->where('approval_stage', 'KABAG')
                ->where('created_by', $user->id)
                ->latest('id')
                ->first();
        }

        if (! $version) {
            if (($validated['submit_for'] ?? 'save') === 'submit') {
                $pending = DocumentVersion::where('document_id', $document->id)
                    ->whereIn('status', ['submitted', 'pending'])
                    ->exists();
                if ($pending) {
                    return redirect()->route('documents.show', $document->id)
                        ->with('error', 'Tidak dapat mengajukan sekarang. Terdapat revisi lain dalam antrian. Silakan cek halaman Draft atau Approval Queue untuk melihat status atau batalkan pengajuan sebelumnya.');
                }
            }

            $version                  = new DocumentVersion();
            $version->document_id     = $document->id;
            $version->created_by      = $user->id;
            $version->status          = 'draft';
            $version->approval_stage  = 'KABAG';
        }

        $file_path = $version->file_path ?? null;
        $file_mime = $version->file_mime ?? null;
        $checksum  = $version->checksum  ?? null;

        if ($request->hasFile('file')) {
            if ($file_path && $disk->exists($file_path)) {
                $disk->delete($file_path);
            }

            $file     = $request->file('file');
            $safeName = $this->safeFilename($file->getClientOriginalName());
            $filename = now()->timestamp . '_' . Str::random(6) . '_' . $safeName;
            $folder   = $document->doc_code . '/' . $validated['version_label'];
            $file_path = $folder . '/' . $filename;

            $content   = file_get_contents($file->getRealPath());
            $disk->put($file_path, $content);
            $file_mime = $file->getClientMimeType() ?: 'application/octet-stream';
            $checksum  = hash('sha256', $content);
        }

        $version->version_label = $validated['version_label'];
        $version->file_path     = $file_path;
        $version->file_mime     = $file_mime;
        $version->checksum      = $checksum;
        $version->change_note   = $validated['change_note'] ?? null;
        $version->signed_by     = $validated['signed_by']   ?? null;
        $version->signed_at     = ! empty($validated['signed_at'])
            ? Carbon::parse($validated['signed_at'])
            : null;

        if ($request->filled('pasted_text')) {
            $clean               = $this->normalizeText($request->input('pasted_text'));
            $version->plain_text = $clean;
            $version->pasted_text = $clean;
        }

        $version->save();
        Cache::forget('dashboard.payload');

        if (($validated['submit_for'] ?? 'save') === 'submit') {
            $pending = DocumentVersion::where('document_id', $document->id)
                ->whereIn('status', ['submitted', 'pending'])
                ->exists();

            if ($pending) {
                return redirect()->route('documents.show', $document->id)
                    ->with('error', 'Submission blocked: another version already pending.');
            }

            $version->update([
                'status'         => 'submitted',
                'submitted_by'   => $user->id,
                'submitted_at'   => now(),
                'approval_stage' => 'MR',
            ]);

            return redirect()
                ->route('documents.show', $document->id)
                ->with('success', 'Draft submitted for approval.');
        }

        return redirect()
            ->route('documents.show', $document->id)
            ->with('success', 'Draft saved.');
    }

    /**
     * Unduh berkas versi.
     */
    public function downloadVersion(DocumentVersion $version)
    {
        $disk = null;
        try {
            $disk = Storage::disk('documents');
        } catch (\Throwable $e) {
            $disk = Storage::disk('public');
        }

        if (! $version->file_path || ! $disk->exists($version->file_path)) {
            abort(404);
        }

        return $disk->download($version->file_path, basename($version->file_path));
    }

    /**
     * Mark version as trashed (non-destructive).
     */
    public function trashVersion(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if (method_exists($user, 'hasAnyRole') && ! $user->hasAnyRole(['mr','director','admin'])) {
            abort(403, 'Unauthorized to trash versions.');
        }

        $oldStatus = $version->status ?? null;

        $version->update([
            'status'         => 'trashed',
            'approval_stage' => null,
        ]);

        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'event'               => 'trash_version',
                'user_id'             => $user->id,
                'document_id'         => $version->document_id,
                'document_version_id' => $version->id,
                'detail'              => json_encode(['from' => $oldStatus]),
                'ip'                  => $request->ip(),
            ]);
        }

        return back()->with('success', 'Version moved to Recycle Bin.');
    }

    /**
     * BUAT DOKUMEN BARU + BASELINE V1 APPROVED or DRAFT.
     *
     * Supports:
     * - upload_type/mode = 'new' (create document)
     * - upload_type/mode = 'replace' (create draft version for existing document)
     *
     * submit_for: 'publish'|'submit' => publish; 'save'|'draft' => draft
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')->with('error', 'Login required.');
        }

        // support both new names and legacy name
        $uploadType = $request->input('upload_type', $request->input('mode', 'new'));
        $submitRaw  = $request->input('submit_for', $request->input('submit', 'publish'));

        // normalize submit action
        $submit = in_array($submitRaw, ['save','draft'], true) ? 'draft' : (in_array($submitRaw, ['publish','submit'], true) ? 'publish' : $submitRaw);

        // === validation rules differ for new vs replace; handle Category presence optional ===
        $categoryRule = class_exists(\App\Models\Category::class) ? 'required|integer|exists:categories,id' : 'nullable';

        if ($uploadType === 'replace') {
            $validated = $request->validate([
                'doc_code'      => ['required','string','exists:documents,doc_code'],
                'version_label' => ['nullable','string','max:50'],
                'file'          => 'nullable|file|mimes:pdf,doc,docx|max:51200',
                'pasted_text'   => 'nullable|string',
                'change_note'   => 'nullable|string|max:2000',
                'related_links' => 'nullable|string',
            ]);

            $document = Document::where('doc_code', $validated['doc_code'])->first();

            if (! $document) {
                return back()->withInput()->with('error', 'Dokumen tidak ditemukan untuk diganti versinya.');
            }

            // choose disk
            try {
                $disk = Storage::disk('documents');
            } catch (\Throwable $e) {
                $disk = Storage::disk('public');
            }

            $file_path = null;
            $file_mime = null;
            $checksum  = null;

            if ($request->hasFile('file')) {
                $file     = $request->file('file');
                $safeName = $this->safeFilename($file->getClientOriginalName());
                $filename = time() . '_' . $safeName;
                $folder   = $document->doc_code . '/' . ($validated['version_label'] ?? 'draft');
                $file_path = $folder . '/' . $filename;

                $content   = file_get_contents($file->getRealPath());
                $disk->put($file_path, $content);
                $file_mime = $file->getClientMimeType() ?: 'application/octet-stream';
                $checksum  = hash('sha256', $content);
            }

            // create draft version (replace flow always creates draft)
            $version = new DocumentVersion();
            $version->document_id   = $document->id;
            $version->version_label = $validated['version_label'] ?? 'vX';
            $version->status        = 'draft';
            $version->approval_stage= 'KABAG';
            $version->created_by    = $user->id;
            $version->file_path     = $file_path;
            $version->file_mime     = $file_mime;
            $version->checksum      = $checksum;
            $version->change_note   = $validated['change_note'] ?? null;
            $version->pasted_text   = $request->input('pasted_text') ?? null;
            $version->plain_text    = $request->input('pasted_text') ?? null;
            $version->save();

            if ($request->has('related_links')) {
                $document->related_links = $this->parseRelatedLinksInput($request->input('related_links'));
                $document->save();
            }

            if (class_exists(\App\Models\AuditLog::class)) {
                \App\Models\AuditLog::create([
                    'event'               => 'create_replace_draft',
                    'user_id'             => $user->id,
                    'document_id'         => $document->id,
                    'document_version_id' => $version->id,
                    'detail'              => json_encode(['via' => 'replace_mode']),
                    'ip'                  => $request->ip(),
                ]);
            }

            return redirect()->route('drafts.index')
                ->with('success', 'Draft versi baru berhasil dibuat & masuk Draft Container.');
        }

        // Default: create new document
        $validated = $request->validate([
            'doc_code'      => 'required|string|max:120|unique:documents,doc_code',
            'title'         => 'required|string|max:255',
            'category_id'   => $categoryRule,
            'department_id' => 'required|integer|exists:departments,id',
            'file'          => 'nullable|file|mimes:pdf,doc,docx|max:51200',
            'pasted_text'   => 'nullable|string',
            'version_label' => 'nullable|string|max:50',
            'change_note'   => 'nullable|string|max:2000',
            'related_links' => 'nullable|string',
        ]);

        $document = Document::create([
            'doc_code'      => $validated['doc_code'],
            'title'         => $validated['title'],
            'department_id' => $validated['department_id'],
            'category_id'   => $validated['category_id'] ?? null,
        ]);

        if ($request->has('related_links')) {
            $raw   = $request->input('related_links', '');
            $links = $this->parseRelatedLinksInput($raw);
            $document->related_links = $links;
            $document->save();
        }

        try {
            $disk = Storage::disk('documents');
        } catch (\Throwable $e) {
            $disk = Storage::disk('public');
        }

        $file_path = null;
        $file_mime = null;
        $checksum  = null;

        if ($request->hasFile('file')) {
            $file     = $request->file('file');
            $safeName = $this->safeFilename($file->getClientOriginalName());
            $filename = time() . '_' . $safeName;
            $folder   = $document->doc_code . '/v1';
            $file_path = $folder . '/' . $filename;

            $content   = file_get_contents($file->getRealPath());
            $disk->put($file_path, $content);
            $file_mime = $file->getClientMimeType() ?: 'application/octet-stream';
            $checksum  = hash('sha256', $content);
        }

        // if user chose to publish baseline immediately
        if ($submit === 'publish') {
            $version = DocumentVersion::create([
                'document_id'   => $document->id,
                'version_label' => $validated['version_label'] ?? 'v1',
                'status'        => 'approved',
                'approval_stage'=> 'DONE',
                'file_path'     => $file_path,
                'file_mime'     => $file_mime,
                'checksum'      => $checksum,
                'change_note'   => $validated['change_note'] ?? null,
                'plain_text'    => $request->input('pasted_text') ?? null,
                'created_by'    => $user->id ?? null,
                'approved_by'   => $user->id ?? null,
                'approved_at'   => now(),
            ]);

            $document->update([
                'current_version_id' => $version->id,
                'revision_number'    => 1,
                'revision_date'      => now(),
            ]);

            if (class_exists(\App\Models\AuditLog::class)) {
                \App\Models\AuditLog::create([
                    'event'               => 'create_baseline_publish',
                    'user_id'             => $user->id,
                    'document_id'         => $document->id,
                    'document_version_id' => $version->id,
                    'detail'              => json_encode(['published' => true]),
                    'ip'                  => $request->ip(),
                ]);
            }

            return redirect()
                ->route('documents.show', $document->id)
                ->with('success', 'Baseline uploaded and published.');
        }

        // otherwise save as draft — create draft version and go to drafts
        $version = DocumentVersion::create([
            'document_id'   => $document->id,
            'version_label' => $validated['version_label'] ?? 'v1',
            'status'        => 'draft',
            'approval_stage'=> 'KABAG',
            'file_path'     => $file_path,
            'file_mime'     => $file_mime,
            'checksum'      => $checksum,
            'change_note'   => $validated['change_note'] ?? null,
            'plain_text'    => $request->input('pasted_text') ?? null,
            'created_by'    => $user->id ?? null,
        ]);

        if (class_exists(\App\Models\AuditLog::class)) {
            \App\Models\AuditLog::create([
                'event'               => 'create_baseline_draft',
                'user_id'             => $user->id,
                'document_id'         => $document->id,
                'document_version_id' => $version->id,
                'detail'              => json_encode(['published' => false]),
                'ip'                  => $request->ip(),
            ]);
        }

        return redirect()
            ->route('drafts.index')
            ->with('success', 'Baseline created as draft.');
    }

    /* =========================
     * Helpers & utilities
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

    protected function parseRelatedLinksInput(?string $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, fn($l) => ! empty($l));

        return array_values($lines);
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
            $text      = $extractor->extractTextForVersion($version, env('PDFTOTEXT_PATH', 'pdftotext'));
            return $text ? $this->normalizeText($text) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function buildDiff(string $text1, string $text2): string
    {
        if (class_exists(\Jfcherng\Diff\DiffHelper::class)) {
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

            return \Jfcherng\Diff\DiffHelper::calculate(
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
        $text = preg_replace('/[^\PC\n\t]/u', ' ', $text);        // control chars
        $text = preg_replace('/[ \t]{2,}/', ' ', $text);          // multi spaces
        $text = preg_replace("/\n{3,}/", "\n\n", $text);          // multi blank lines
        return trim($text);
    }

    protected function safeFilename(string $original): string
    {
        $name = preg_replace('/[^\w\.\-]+/u', '_', $original);
        return $name ?: ('file_' . Str::random(8));
    }

    protected function cleanString(?string $s): ?string
    {
        if ($s === null) {
            return null;
        }
        $s = preg_replace('/\x{FEFF}|\p{C}/u', '', $s);
        return trim($s);
    }

    protected function parseDateString(?string $s): ?\Carbon\Carbon
    {
        if (empty($s)) {
            return null;
        }
        try {
            return Carbon::parse($s);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function incRevision($rev)
    {
        if (is_numeric($rev)) {
            return (int) $rev + 1;
        }
        return 1;
    }
}
