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
use Illuminate\Support\Facades\Schema;

class DocumentController extends Controller
{
    protected string $diskName = 'documents';

    /* -------------------------
     * LIST / FORM
     * ------------------------- */

    public function index(Request $request)
    {
        $departments = Department::orderBy('code')->get();
        $categories = class_exists(\App\Models\Category::class) ? \App\Models\Category::orderBy('name')->get() : [];

        $docs = Document::with(['department', 'currentVersion'])
            ->when($request->filled('department'), fn($q) => $q->where('department_id', $request->input('department')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->input('search');
                $q->where(function ($qq) use ($s) {
                    $qq->where('doc_code', 'like', "%{$s}%")
                       ->orWhere('title', 'like', "%{$s}%")
                       ->orWhereHas('versions', fn($qv) => $qv->where('plain_text', 'like', "%{$s}%"));
                });
            })
            ->orderBy('doc_code')
            ->paginate(25)
            ->appends($request->query());

        return view('documents.index', compact('docs', 'departments', 'categories'));
    }

    public function create()
    {
        $departments = Department::orderBy('code')->get();
        $categories = class_exists(\App\Models\Category::class) ? \App\Models\Category::orderBy('name')->get() : [];
        return view('documents.create', compact('departments', 'categories'));
    }

    public function edit($id)
    {
        $document = Document::findOrFail($id);
        $departments = Department::orderBy('code')->get();
        $categories = class_exists(\App\Models\Category::class) ? \App\Models\Category::orderBy('name')->get() : [];
        return view('documents.edit', compact('document', 'departments', 'categories'));
    }

    /* -------------------------
     * STORE (create new OR replace)
     * ------------------------- */

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login')->with('error', 'Login required.');
        }

        $uploadType = strtolower(trim((string)($request->input('upload_type', $request->input('mode', 'new')))));
        $submitRaw = strtolower(trim((string)($request->input('submit_for', $request->input('submit', 'publish')))));
        $submit = in_array($submitRaw, ['save','draft'], true) ? 'draft' : 'publish';
        if ($uploadType === 'replace') $submit = 'draft';

        if ($uploadType === 'replace') {
            return $this->handleReplace($request, $user);
        }

        return $this->handleCreateNew($request, $user, $submit);
    }

    /* -------------------------
     * HANDLE: Replace (upload replacement / new draft for existing doc)
     * ------------------------- */
    protected function handleReplace(Request $request, $user)
    {
        $validated = $request->validate([
            'doc_code'      => ['required','string','exists:documents,doc_code'],
            'version_label' => ['nullable','string','max:50'],
            'file'          => 'nullable|file|mimes:pdf|max:51200',
            'master_file'   => 'nullable|file|mimes:doc,docx,xlx,xlxs|max:102400',
            'pasted_text'   => 'nullable|string',
            'change_note'   => 'nullable|string|max:2000',
            'related_links' => 'nullable|string',
        ]);

        $disk = $this->getDisk();

        $pdf_path = null;
        $master_path = null;
        $file_mime = null;
        $checksum = null;

        $document = Document::where('doc_code', $validated['doc_code'])->firstOrFail();

        // choose version label: if provided, try to coerce to newest vN; else auto next
        $versionLabel = $this->resolveVersionLabelForNewVersion($document, $validated['version_label'] ?? null);

        $folder = trim($document->doc_code . '/' . $versionLabel, '/');

        // store master_file if provided
        if ($request->hasFile('master_file')) {
            $master = $request->file('master_file');
            $safe = $this->safeFilename($master->getClientOriginalName());
            $name = now()->timestamp . '_master_' . Str::random(6) . '_' . $safe;
            $master_path = trim($folder . '/master/' . $name, '/');
            try { $disk->put($master_path, file_get_contents($master->getRealPath())); } catch (\Throwable) { /* ignore */ }
        }

        // store pdf if provided
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $safe = $this->safeFilename($file->getClientOriginalName());
            $name = now()->timestamp . '_pdf_' . Str::random(6) . '_' . $safe;
            $pdf_path = trim($folder . '/' . $name, '/');
            $content = file_get_contents($file->getRealPath());
            try { $disk->put($pdf_path, $content); } catch (\Throwable) { /* ignore */ }
            $file_mime = $file->getClientMimeType() ?: 'application/pdf';
            $checksum = hash('sha256', $content);
        }

        // find existing draft/rejected to update else create a new draft version
        $draft = DocumentVersion::where('document_id', $document->id)
            ->whereIn('status', ['draft','rejected'])
            ->latest('id')
            ->first();

        if ($draft) {
            if ($master_path) $draft->master_path = $master_path;
            if ($pdf_path) $draft->pdf_path = $pdf_path;
            if ($file_mime) $draft->file_mime = $file_mime;
            if ($checksum) $draft->checksum = $checksum;

            $draft->version_label = $versionLabel;
            $draft->change_note = $validated['change_note'] ?? $draft->change_note;
            $draft->pasted_text = $request->input('pasted_text') ?? $draft->pasted_text;
            $draft->plain_text = $request->input('pasted_text') ?? $draft->plain_text;
            $draft->status = 'draft';
            $draft->approval_stage = 'KABAG';
            $draft->created_by = $user->id;
            $this->nullifySubmittedFieldsIfExist($draft);
            $draft->save();
        } else {
            $draft = DocumentVersion::create([
                'document_id'   => $document->id,
                'version_label' => $versionLabel,
                'status'        => 'draft',
                'approval_stage'=> 'KABAG',
                'created_by'    => $user->id,
                'master_path'   => $master_path,
                'pdf_path'      => $pdf_path,
                'file_mime'     => $file_mime,
                'checksum'      => $checksum,
                'change_note'   => $validated['change_note'] ?? null,
                'pasted_text'   => $request->input('pasted_text') ?? null,
                'plain_text'    => $request->input('pasted_text') ?? null,
            ]);
        }

        if ($request->filled('related_links')) {
            $document->related_links = $this->parseRelatedLinksInput($request->input('related_links'));
            $document->save();
        }

        $this->maybeAudit('create_replace_draft', $user->id, $document->id, $draft->id, $request->ip());

        return redirect()->route('drafts.index')->with('success', 'Draft versi baru berhasil dibuat & masuk Draft Container.');
    }

    /* -------------------------
     * HANDLE: Create New Document (baseline or draft)
     * ------------------------- */
    protected function handleCreateNew(Request $request, $user, string $submit)
{
    $categoryRule = class_exists(\App\Models\Category::class) ? 'required|integer|exists:categories,id' : 'nullable';

    $validated = $request->validate([
        'doc_code'      => 'required|string|max:120|unique:documents,doc_code',
        'title'         => 'required|string|max:255',
        'category_id'   => $categoryRule,
        'department_id' => 'required|integer|exists:departments,id',
        'file'          => 'nullable|file|mimes:pdf|max:51200',
        'master_file'   => 'nullable|file|mimes:doc,docx,xls,xlxs|max:102400',
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

    if ($request->filled('related_links')) {
        $document->related_links = $this->parseRelatedLinksInput($request->input('related_links'));
        $document->save();
    }

    $disk = $this->getDisk();

    // determine version label (auto v1)
    $versionLabel = $validated['version_label'] ?? $this->nextVersionLabelForDocument($document);

    $folder = trim($document->doc_code . '/' . $versionLabel, '/');

    $master_path = null;
    if ($request->hasFile('master_file')) {
        $master = $request->file('master_file');
        $safe = $this->safeFilename($master->getClientOriginalName());
        $master_name = now()->timestamp . '_master_' . Str::random(6) . '_' . $safe;
        $master_path = trim($folder . '/master/' . $master_name, '/');
        try { 
            $disk->put($master_path, file_get_contents($master->getRealPath())); 
        } catch (\Throwable) { }
    }

    $pdf_path = null;
    $file_mime = null;
    $checksum = null;
    if ($request->hasFile('file')) {
        $file = $request->file('file');
        $safe = $this->safeFilename($file->getClientOriginalName());
        $name = now()->timestamp . '_pdf_' . Str::random(6) . '_' . $safe;
        $pdf_path = trim($folder . '/' . $name, '/');
        $content = file_get_contents($file->getRealPath());

        try { 
            $disk->put($pdf_path, $content); 
        } catch (\Throwable) { }

        $file_mime = $file->getClientMimeType() ?: 'application/pdf';
        $checksum = hash('sha256', $content);
    }

    if ($submit === 'publish') {
        $version = DocumentVersion::create([
            'document_id'   => $document->id,
            'version_label' => $versionLabel,
            'status'        => 'approved',
            'approval_stage'=> 'DONE',
            'pdf_path'      => $pdf_path,
            'file_path'     => $pdf_path,
            'master_path'   => $master_path,
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

        $this->maybeAudit('create_baseline_publish', $user->id, $document->id, $version->id, $request->ip());

        return redirect()->route('documents.show', $document->id)->with('success', 'Baseline uploaded and published.');
    }

    // Save as draft
    $version = DocumentVersion::create([
        'document_id'   => $document->id,
        'version_label' => $versionLabel,
        'status'        => 'draft',
        'approval_stage'=> 'KABAG',
        'pdf_path'      => $pdf_path,
        'file_path'     => $pdf_path,
        'master_path'   => $master_path,
        'file_mime'     => $file_mime,
        'checksum'      => $checksum,
        'change_note'   => $validated['change_note'] ?? null,
        'plain_text'    => $request->input('pasted_text') ?? null,
        'created_by'    => $user->id ?? null,
    ]);

    $this->maybeAudit('create_baseline_draft', $user->id, $document->id, $version->id, $request->ip());

    return redirect()->route('drafts.index')->with('success', 'Baseline created as draft.');
}



    /* -------------------------
     * UPDATE COMBINED (metadata + version)
     * ------------------------- */

    public function updateCombined(Request $request, Document $document)
    {
        $validated = $request->validate([
            'doc_code'      => ['required','string','max:80', Rule::unique('documents','doc_code')->ignore($document->id)],
            'title'         => 'required|string|max:255',
            'department_id' => 'required|integer|exists:departments,id',
            'category_id'   => 'nullable|integer|exists:categories,id',
            'version_id'    => 'nullable|integer|exists:document_versions,id',
            'version_label' => 'required|string|max:50',
            'file'          => 'nullable|file|mimes:pdf|max:51200',
            'master_file'   => 'nullable|file|mimes:doc,docx,xls,xlsx|max:102400',
            'pasted_text'   => 'nullable|string|max:800000',
            'change_note'   => 'nullable|string|max:2000',
            'signed_by'     => 'nullable|string|max:191',
            'signed_at'     => 'nullable|date',
            'submit_for'    => 'nullable|in:save,submit,publish,draft',
            'related_links' => 'nullable|string',
        ]);

        $rawSubmit = strtolower(trim((string)($validated['submit_for'] ?? $request->input('submit_for', ''))));
        $submitFor = in_array($rawSubmit, ['publish','submit'], true) ? 'submit' : 'save';

        // metadata update
        $document->update([
            'doc_code'      => $validated['doc_code'],
            'title'         => $validated['title'],
            'department_id' => (int) $validated['department_id'],
            'category_id'   => $validated['category_id'] ?? $document->category_id,
        ]);

        if ($request->filled('related_links')) {
            $document->related_links = $this->parseRelatedLinksInput($request->input('related_links'));
            $document->save();
        }

        $user = $request->user();
        $disk = $this->getDisk();

        // find existing version to update (draft by this user) or use provided version id
        $version = null;
        if (!empty($validated['version_id'])) {
            $version = DocumentVersion::where('document_id', $document->id)->where('id', $validated['version_id'])->first();
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
            if ($submitFor === 'submit') {
                $pending = DocumentVersion::where('document_id', $document->id)->whereIn('status', ['submitted','pending'])->exists();
                if ($pending) {
                    return redirect()->route('documents.show', $document->id)
                        ->with('error', 'Tidak dapat mengajukan sekarang. Terdapat revisi lain dalam antrian.');
                }
            }
            $version = new DocumentVersion();
            $version->document_id = $document->id;
            $version->created_by = $user->id;
            $version->status = 'draft';
            $version->approval_stage = 'KABAG';
        }

        // keep existing paths/metadata if present
        $master_path = $version->master_path ?? null;
        $pdf_path = $version->pdf_path ?? null;
        $file_mime = $version->file_mime ?? null;
        $checksum  = $version->checksum ?? null;

        // determine final version label (we enforce next vN if user-provided label conflicts)
        $requestedLabel = $validated['version_label'];
        $versionLabel = $this->resolveVersionLabelForNewVersion($document, $requestedLabel);

        $folder = trim($document->doc_code . '/' . $versionLabel, '/');

        // store master_file if uploaded
        if ($request->hasFile('master_file')) {
            $master = $request->file('master_file');
            $safe = $this->safeFilename($master->getClientOriginalName());
            $name = now()->timestamp . '_master_' . Str::random(6) . '_' . $safe;
            $master_path = trim($folder . '/master/' . $name, '/');
            try { $disk->put($master_path, file_get_contents($master->getRealPath())); } catch (\Throwable) { /* ignore */ }
        }

        // store pdf if uploaded
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $safe = $this->safeFilename($file->getClientOriginalName());
            $name = now()->timestamp . '_pdf_' . Str::random(6) . '_' . $safe;
            $pdf_path = trim($folder . '/' . $name, '/');
            $content = file_get_contents($file->getRealPath());
            try { $disk->put($pdf_path, $content); } catch (\Throwable) { /* ignore */ }
            $file_mime = $file->getClientMimeType() ?: 'application/pdf';
            $checksum = hash('sha256', $content);
        }

        $version->version_label = $versionLabel;
        $version->master_path   = $master_path ?? $version->master_path;
        $version->pdf_path      = $pdf_path ?? $version->pdf_path;
        // keep file_path for backward compatibility: point to pdf_path if present
        $version->file_path     = $version->pdf_path ?? $version->file_path;
        $version->file_mime     = $file_mime ?? $version->file_mime;
        $version->checksum      = $checksum ?? $version->checksum;
        $version->change_note   = $validated['change_note'] ?? $version->change_note;
        $version->signed_by     = $validated['signed_by'] ?? $version->signed_by;
        $version->signed_at     = !empty($validated['signed_at']) ? Carbon::parse($validated['signed_at']) : $version->signed_at;

        if ($request->filled('pasted_text')) {
            $clean = $this->normalizeText($request->input('pasted_text'));
            $version->plain_text = $clean;
            $version->pasted_text = $clean;
        }

        $version->save();
        Cache::forget('dashboard.payload');

        if ($submitFor === 'submit') {
            $pending = DocumentVersion::where('document_id', $document->id)->whereIn('status', ['submitted','pending'])->exists();
            if ($pending) {
                return redirect()->route('documents.show', $document->id)
                    ->with('error', 'Submission blocked: another version pending.');
            }

            $update = ['status' => 'submitted', 'approval_stage' => 'MR'];
            if ($this->hasColumn($version, 'submitted_by')) $update['submitted_by'] = $user->id;
            if ($this->hasColumn($version, 'submitted_at')) $update['submitted_at'] = now();
            $version->update($update);

            return redirect()->route('documents.show', $document->id)->with('success', 'Draft submitted for approval.');
        }

        return redirect()->route('documents.show', $document->id)->with('success', 'Draft saved.');
    }

    /* -------------------------
     * UPLOAD PDF (ALTERNATIVE FLOW kept for compatibility)
     * ------------------------- */
    public function uploadPdf(Request $request)
    {
        // kept for backward compatibility / small uploader forms
        $user = $request->user();
        if (! $user) return redirect()->route('login')->with('error', 'Login required to upload documents.');
        if (! $this->userCanUpload($user)) abort(403, 'Anda tidak memiliki hak untuk mengunggah dokumen.');

        $validated = $request->validate([
            'file'           => 'nullable|file|mimes:pdf|max:51200',
            'master_file'    => 'nullable|file|mimes:doc,docx,xls,xlxs|max:102400',
            'version_label'  => 'required|string|max:50',
            'document_id'    => 'nullable|integer',
            'doc_code'       => 'nullable|string|max:80',
            'title'          => 'required|string|max:255',
            'department_id'  => 'required|integer|exists:departments,id',
            'change_note'    => 'nullable|string|max:2000',
            'signed_by'      => 'nullable|string|max:255',
            'signed_at'      => 'nullable|date',
            'pasted_text'    => 'nullable|string|max:200000',
            'related_links'  => 'nullable|string',
        ]);

        if (!empty($validated['document_id'])) {
            $document = Document::findOrFail((int) $validated['document_id']);
        } else {
            $docCode = $validated['doc_code'] ?: strtoupper(Str::slug($validated['title'], '-'));
            $document = Document::firstOrCreate(
                ['doc_code' => $docCode],
                ['title' => $validated['title'], 'department_id' => (int)$validated['department_id']]
            );
        }

        if ($request->filled('related_links')) {
            $document->related_links = $this->parseRelatedLinksInput($request->input('related_links'));
            $document->save();
        }

        $disk = $this->getDisk();

        $versionLabel = $validated['version_label'] ?? $this->nextVersionLabelForDocument($document);
        $folder = trim($document->doc_code . '/' . $versionLabel, '/');

        $master_path = null;
        if ($request->hasFile('master_file')) {
            $master = $request->file('master_file');
            $safeName = $this->safeFilename($master->getClientOriginalName());
            $master_name = now()->timestamp . '_master_' . Str::random(6) . '_' . $safeName;
            $master_path = trim($folder . '/master/' . $master_name, '/');
            try { $disk->put($master_path, file_get_contents($master->getRealPath())); } catch (\Throwable) { /* ignore */ }
        }

        $pdf_path = null; $file_mime = null; $checksum = null;
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $safeName = $this->safeFilename($file->getClientOriginalName());
            $filename = now()->timestamp . '_pdf_' . Str::random(6) . '_' . $safeName;
            $pdf_path = trim($folder . '/' . $filename, '/');
            $content = file_get_contents($file->getRealPath());
            try { $disk->put($pdf_path, $content); } catch (\Throwable) { /* ignore */ }
            $file_mime = $file->getClientMimeType() ?: 'application/pdf';
            $checksum = hash('sha256', $content);
        }

        // remove previous draft by same user (prevent duplicates)
        DocumentVersion::where('document_id', $document->id)
            ->where('created_by', $user->id)
            ->whereIn('status', ['draft','rejected'])
            ->delete();

        $version = DocumentVersion::create([
            'document_id'   => $document->id,
            'version_label' => $versionLabel,
            'status'        => 'draft',
            'approval_stage'=> 'KABAG',
            'created_by'    => $user->id,
            'master_path'   => $master_path,
            'pdf_path'      => $pdf_path,
            'file_path'     => $pdf_path,
            'file_mime'     => $file_mime,
            'checksum'      => $checksum,
            'change_note'   => $validated['change_note'] ?? null,
            'signed_by'     => $validated['signed_by'] ?? $user->name,
            'signed_at'     => !empty($validated['signed_at']) ? Carbon::parse($validated['signed_at']) : null,
        ]);

        // prefer pasted_text only (lightweight)
        if ($request->filled('pasted_text')) {
            $pasted = $this->normalizeText($request->input('pasted_text'));
            $version->pasted_text = $pasted;
            $version->plain_text  = $pasted;
            $version->summary_changed = 'Text provided by uploader (pasted).';
            $version->save();
        } else {
            // do not run heavy extraction automatically — skip to keep upload lightweight.
            $version->summary_changed = 'No text provided (pasted_text preferred).';
            $version->save();
        }

        // do NOT alter current_version_id here; only metadata update allowed
        $document->title = $validated['title'];
        $document->department_id = (int)$validated['department_id'];
        $document->save();

        $this->maybeAudit('upload_version', $user->id, $document->id, $version->id, $request->ip(), [
            'pdf' => $pdf_path, 'master' => $master_path, 'pasted' => $request->filled('pasted_text')
        ]);

        return redirect()->route('documents.show', $document->id)
            ->with('success', 'Version uploaded. Text indexing: ' . ($version->plain_text ? 'available' : 'not available') . '.');
    }

    /* -------------------------
     * COMPARE / SHOW / APPROVAL / REJECT / TRASH
     * ------------------------- */

    public function compare(Request $request, $documentId)
    {
        $doc = Document::with('versions')->findOrFail($documentId);

        $versions = collect($request->query('versions', []))
            ->flatten()
            ->map(fn($v) => is_numeric($v) ? (int)$v : null)
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
            $versionsData = DocumentVersion::whereIn('id', $versions)->where('document_id', $documentId)->orderBy('id')->get();
            if ($versionsData->count() < 2) {
                return back()->with('error', 'Beberapa versi yang dipilih tidak ditemukan atau tidak valid.');
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

    public function chooseCompare($versionId)
    {
        $version = DocumentVersion::with('document')->findOrFail($versionId);
        $document = $version->document;
        $candidates = $document->versions()->where('status','approved')->orderByDesc('id')->get();
        return view('versions.choose_compare', compact('version','document','candidates'));
    }

    public function approveVersion(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if (! $user) abort(403);

        // MR forwards to DIRECTOR
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['mr'])) {
            $version->update([
                'status' => 'submitted',
                'approval_stage' => 'DIRECTOR',
                'submitted_by' => $this->hasColumn($version, 'submitted_by') ? $user->id : null,
                'submitted_at' => $this->hasColumn($version, 'submitted_at') ? now() : null,
            ]);
            return back()->with('success','Version forwarded to Director.');
        }

        // Director/Admin final approval
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['director','admin'])) {
            DB::transaction(function () use ($version, $user) {
                $update = ['status' => 'approved', 'approval_stage' => 'DONE'];
                if ($this->hasColumn($version, 'approved_by')) $update['approved_by'] = $user->id;
                if ($this->hasColumn($version, 'approved_at')) $update['approved_at'] = now();
                $version->update($update);

                $doc = $version->document;
                $docUpdate = [
                    'current_version_id' => $version->id,
                    'revision_number' => $this->incRevision($doc->revision_number),
                    'revision_date' => now(),
                ];
                if ($this->hasColumn($doc, 'approved_by')) $docUpdate['approved_by'] = $user->id;
                if ($this->hasColumn($doc, 'approved_at')) $docUpdate['approved_at'] = now();
                $doc->update($docUpdate);
            });

            return back()->with('success','Version approved and promoted to current.');
        }

        return back()->with('error','You are not authorized to approve.');
    }

    public function rejectVersion(Request $request, DocumentVersion $version)
    {
        $request->validate(['rejected_reason' => 'required|string|max:2000']);
        $user = $request->user();
        if (! $user) abort(403);
        if (method_exists($user,'hasAnyRole') && ! $user->hasAnyRole(['mr','director','admin'])) abort(403);

        $update = ['status' => 'rejected', 'approval_stage' => 'KABAG'];
        if ($this->hasColumn($version, 'rejected_by')) $update['rejected_by'] = $user->id;
        if ($this->hasColumn($version, 'rejected_at')) $update['rejected_at'] = now();
        if ($this->hasColumn($version, 'rejected_reason')) $update['rejected_reason'] = $request->input('rejected_reason');
        if ($this->hasColumn($version, 'reject_reason')) $update['reject_reason'] = $request->input('rejected_reason');

        $version->update($update);
        $this->maybeAudit('reject_version', $user->id, $version->document_id, $version->id, $request->ip(), ['reason'=>$request->input('rejected_reason')]);

        return back()->with('success','Version rejected and returned to draft.');
    }

    public function trashVersion(Request $request, DocumentVersion $version)
    {
        $user = $request->user();
        if (! $user) abort(403);
        if (method_exists($user,'hasAnyRole') && ! $user->hasAnyRole(['mr','director','admin'])) abort(403, 'Unauthorized to trash versions.');

        $oldStatus = $version->status ?? null;
        $version->update(['status' => 'trashed', 'approval_stage' => null]);

        $this->maybeAudit('trash_version', $user->id, $version->document_id, $version->id, $request->ip(), ['from'=>$oldStatus]);

        return back()->with('success','Version moved to Recycle Bin.');
    }

    /* -------------------------
     * SHOW / DOWNLOAD / PREVIEW
     * ------------------------- */

    public function show(Document $document)
    {
        $document->load(['department', 'versions.creator']);

        // prefer explicit version if requested
        $requestedVersionId = null;
        try { $requestedVersionId = request()->query('version_id') ? (int) request()->query('version_id') : null; } catch (\Throwable) { $requestedVersionId = null; }

        $version = null;

        if ($requestedVersionId) {
            $version = $document->versions()->where('id', $requestedVersionId)->first();
        }

        // prefer document.current_version_id (approved)
        if (! $version && isset($document->current_version_id) && $document->current_version_id) {
            $version = $document->versions()->where('id', $document->current_version_id)->first();
        }

        // else latest approved
        if (! $version) {
            $version = $document->versions()->where('status', 'approved')->orderByDesc('id')->first();
        }

        // fallback: any version (draft/rejected)
        if (! $version) {
            $version = $document->versions()->orderByDesc('id')->first();
        }

        $versions = $document->versions->sortByDesc('id')->values();

        $relatedLinks = $this->normalizeRelatedLinks($document);

        if ($version && (! property_exists($version,'signature') || $version->signature === null)) {
            $version->signature = (object)['signed_at'=>null,'signed_by'=>null];
        }
        if (! property_exists($document,'signature') || $document->signature === null) {
            $document->signature = (object)['signed_at'=>null,'signed_by'=>null];
        }

        return view('documents.show', compact('document','versions','version','relatedLinks'));
    }

    public function downloadVersion(DocumentVersion $version)
    {
        $disk = $this->getDisk();

        // prefer pdf_path, then file_path, then master_path as fallback
        $candidates = [$version->pdf_path ?? null, $version->file_path ?? null, $version->master_path ?? null];

        foreach ($candidates as $p) {
            if (empty($p)) continue;
            $path = ltrim($p, '/');
            if (! $disk->exists($path)) continue;

            try {
                return $disk->download($path, basename($path));
            } catch (\Throwable $e) {
                $stream = $disk->readStream($path);
                if ($stream === false) continue;
                $size = $this->safeDiskCall(fn() => $disk->size($path));
                $mime = $this->safeDiskCall(fn() => $disk->mimeType($path));
                return response()->stream(function() use ($stream) {
                    fpassthru($stream);
                    if (is_resource($stream)) fclose($stream);
                }, 200, array_filter([
                    'Content-Type' => $mime ?: 'application/octet-stream',
                    'Content-Length' => $size,
                    'Content-Disposition' => 'attachment; filename="'.basename($path).'"',
                ]));
            }
        }

        abort(404);
    }

    public function downloadMaster(DocumentVersion $version)
    {
        $user = request()->user();
        if (! $user) abort(403, 'Login required.');

        $disks = $this->getAvailableDisks();

        // prefer master_path -> pdf_path -> file_path
        $candidates = [
            $version->master_path ?? null,
            $version->pdf_path ?? null,
            $version->file_path ?? null,
        ];

        foreach ($disks as $disk) {
            foreach ($candidates as $p) {
                if (empty($p)) continue;
                $path = ltrim($p, '/');

                $exists = (method_exists($this, 'safeDiskCall')) ? $this->safeDiskCall(fn() => $disk->exists($path)) : (function() use ($disk,$path){ try{ return $disk->exists($path); }catch(\Throwable){return false;} })();
                if (! $exists) continue;

                if (method_exists($this, 'maybeAudit')) {
                    $this->maybeAudit('download_master', $user->id ?? null, $version->document_id ?? null, $version->id, request()->ip(), ['path' => $path]);
                }

                try {
                    return $disk->download($path, basename($path));
                } catch (\Throwable $e) {
                    $stream = (method_exists($this, 'safeDiskCall')) ? $this->safeDiskCall(fn() => $disk->readStream($path)) : (function() use ($disk,$path){ try{ return $disk->readStream($path);}catch(\Throwable){return false;} })();
                    if ($stream === false || $stream === null) continue;
                    $size = (method_exists($this, 'safeDiskCall')) ? $this->safeDiskCall(fn() => $disk->size($path)) : null;
                    $mime = (method_exists($this, 'safeDiskCall')) ? $this->safeDiskCall(fn() => $disk->mimeType($path)) : null;
                    return response()->stream(function () use ($stream) {
                        @fpassthru($stream);
                        if (is_resource($stream)) @fclose($stream);
                    }, 200, array_filter([
                        'Content-Type' => $mime ?: 'application/octet-stream',
                        'Content-Length' => $size,
                        'Content-Disposition' => 'attachment; filename="'.basename($path).'"',
                        'Cache-Control' => 'private, max-age=0, must-revalidate',
                    ]));
                }
            }
        }

        abort(404, 'Master file not found.');
    }

    // previewVersion: only inline PDF
    public function previewVersion(DocumentVersion $version)
    {
        $user = request()->user();
        if (! $user) abort(403, 'Login required.');

        $disks = $this->getAvailableDisks();

        $candidates = [$version->pdf_path ?? null, $version->file_path ?? null, $version->master_path ?? null];

        foreach ($disks as $disk) {
            foreach ($candidates as $p) {
                if (empty($p)) continue;
                $path = ltrim($p, '/');

                $exists = (method_exists($this, 'safeDiskCall')) ? $this->safeDiskCall(fn() => $disk->exists($path)) : (function() use ($disk,$path){ try{ return $disk->exists($path);}catch(\Throwable){return false;} })();
                if (! $exists) continue;

                $mime = (method_exists($this, 'safeDiskCall')) ? $this->safeDiskCall(fn() => $disk->mimeType($path)) : null;
                $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                if ($mime === 'application/pdf' || $ext === 'pdf') {
                    $stream = (method_exists($this, 'safeDiskCall')) ? $this->safeDiskCall(fn() => $disk->readStream($path)) : (function() use ($disk,$path){ try{ return $disk->readStream($path);}catch(\Throwable){return false;} })();
                    if ($stream === false || $stream === null) continue;
                    $size = (method_exists($this, 'safeDiskCall')) ? $this->safeDiskCall(fn() => $disk->size($path)) : null;

                    if (method_exists($this, 'maybeAudit')) {
                        $this->maybeAudit('preview_pdf', $user->id ?? null, $version->document_id ?? null, $version->id, request()->ip(), ['path' => $path]);
                    }

                    return response()->stream(function () use ($stream) {
                        @fpassthru($stream);
                        if (is_resource($stream)) @fclose($stream);
                    }, 200, array_filter([
                        'Content-Type' => 'application/pdf',
                        'Content-Length' => $size,
                        'Content-Disposition' => 'inline; filename="'.basename($path).'"',
                        'Cache-Control' => 'public, max-age=0, must-revalidate',
                    ]));
                }
            }
        }

        abort(404, 'PDF preview not available for this version.');
    }

    /* -------------------------
     * HELPERS & UTILITIES
     * ------------------------- */

    protected function getDisk()
    {
        try { return Storage::disk($this->diskName); } catch (\Throwable $e) { return Storage::disk('public'); }
    }

    protected function getAvailableDisks(): array
    {
        $list = [];
        try { $list[] = Storage::disk($this->diskName); } catch (\Throwable) {}
        try { $list[] = Storage::disk('public'); } catch (\Throwable) {}
        return $list;
    }

    protected function safeDiskCall(callable $fn)
    {
        try { return $fn(); } catch (\Throwable) { return null; }
    }

    protected function hasColumn($modelOrInstance, string $column): bool
    {
        try {
            $table = is_object($modelOrInstance) ? $modelOrInstance->getTable() : (string)$modelOrInstance;
            return Schema::hasColumn($table, $column);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function nullifySubmittedFieldsIfExist(DocumentVersion $v): void
    {
        if ($this->hasColumn($v, 'submitted_at')) $v->submitted_at = null;
        if ($this->hasColumn($v, 'submitted_by')) $v->submitted_by = null;
        if ($this->hasColumn($v, 'rejected_reason')) $v->rejected_reason = null;
        if ($this->hasColumn($v, 'reject_reason')) $v->reject_reason = null;
    }

    protected function maybeAudit(string $event, $userId = null, $documentId = null, $documentVersionId = null, $ip = null, $detail = [])
    {
        if (! class_exists(\App\Models\AuditLog::class)) return;
        try {
            \App\Models\AuditLog::create([
                'event' => $event,
                'user_id' => $userId,
                'document_id' => $documentId,
                'document_version_id' => $documentVersionId,
                'detail' => json_encode($detail),
                'ip' => $ip,
            ]);
        } catch (\Throwable) { /* ignore */ }
    }

    protected function userCanUpload($user): bool
    {
        if (method_exists($user,'hasAnyRole')) {
            return $user->hasAnyRole(['mr','admin','kabag']);
        }
        try {
            $roles = method_exists($user,'roles') ? $user->roles()->pluck('name')->toArray() : [];
            return (bool) array_intersect($roles, ['mr','admin','kabag']);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function parseRelatedLinksInput(?string $raw): array
    {
        if (empty($raw)) return [];
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $lines = array_map('trim', $lines);
        return array_values(array_filter($lines, fn($l) => ! empty($l)));
    }

    protected function normalizeRelatedLinks(Document $document): array
    {
        $relatedLinks = [];
        $rawLinks = $document->related_links ?? null;
        if ($rawLinks !== null) {
            if (is_string($rawLinks)) {
                $decoded = json_decode($rawLinks, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $rawLinks = $decoded;
                else $rawLinks = [$rawLinks];
            } elseif (!is_array($rawLinks)) {
                $rawLinks = [];
            }

            foreach ($rawLinks as $url) {
                if (!is_string($url) || trim($url) === '') continue;
                $url = trim($url);
                $label = $url;
                if (preg_match('#/documents/(\d+)#', $url, $m)) {
                    $docRef = Document::find((int)$m[1]);
                    if ($docRef) $label = trim(($docRef->doc_code ? $docRef->doc_code.' — ' : '').$docRef->title) ?: $url;
                } else {
                    $short = preg_replace('#^https?://#i', '', $url);
                    $label = strlen($short) > 48 ? substr($short,0,45).'...' : $short;
                }
                $relatedLinks[] = ['url'=>$url,'label'=>$label];
            }
        }
        return $relatedLinks;
    }

    // NOTE: extraction helpers kept but not used automatically (we prefer pasted_text)
    protected function extractDocxText(string $binary): ?string
    {
        try {
            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'docx_' . uniqid() . '.docx';
            file_put_contents($tmp, $binary);
            $zip = new \ZipArchive();
            $text = null;
            if ($zip->open($tmp) === true) {
                $idx = $zip->locateName('word/document.xml');
                if ($idx !== false) {
                    $xml = $zip->getFromIndex($idx);
                    $text = strip_tags($xml);
                }
                $zip->close();
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
            if (! class_exists(\App\Console\Commands\ExtractDocumentTextCommand::class)) return null;
            $extractor = app()->make(\App\Console\Commands\ExtractDocumentTextCommand::class);
            $text = $extractor->extractTextForVersion($version, env('PDFTOTEXT_PATH', 'pdftotext'));
            return $text ? $this->normalizeText($text) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function buildDiff(string $text1, string $text2): string
    {
        if (class_exists(\Jfcherng\Diff\DiffHelper::class)) {
            $diffOptions = ['context'=>2,'ignoreWhitespace'=>true,'ignoreCase'=>false];
            $rendererOptions = ['detailLevel'=>'line','showHeader'=>false,'mergeThreshold'=>0.8];
            return \Jfcherng\Diff\DiffHelper::calculate($text1, $text2, 'Combined', $diffOptions, $rendererOptions);
        }
        return '<div class="alert alert-warning mb-0">Diff library not installed. Run: <code>composer require jfcherng/php-diff</code></div>';
    }

    protected function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n","\r"], "\n", $text);
        $text = preg_replace('/[^\PC\n\t]/u', ' ', $text);
        $text = preg_replace('/[ \t]{2,}/', ' ', $text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }

    protected function safeFilename(string $original): string
    {
        $name = preg_replace('/[^\w\.\-]+/u','_',$original);
        return $name ?: ('file_'.Str::random(8));
    }

    protected function incRevision($rev)
    {
        if (is_numeric($rev)) return (int)$rev + 1;
        return 1;
    }

    /**
     * Determine next version label for a document: v1, v2, v3...
     */
    protected function nextVersionLabelForDocument(Document $document): string
    {
        $latest = DocumentVersion::where('document_id', $document->id)
            ->whereRaw("version_label REGEXP '^v[0-9]+'")
            ->orderByDesc('id')
            ->get()
            ->pluck('version_label')
            ->map(fn($v) => (int) preg_replace('/[^0-9]/', '', $v))
            ->filter()
            ->max();

        $next = ($latest ? $latest + 1 : 1);
        return 'v' . $next;
    }

    /**
     * Resolve version label for new upload: if user-supplied label conflicts with existing older v-number,
     * force using next available vN (to avoid accidental overwrites).
     */
    protected function resolveVersionLabelForNewVersion(Document $document, ?string $requested): string
    {
        if (empty($requested)) return $this->nextVersionLabelForDocument($document);

        // normalize requested like 'v2' or arbitrary string; if it's vN and N > existing max -> accept, else force next.
        $requested = trim($requested);
        if (preg_match('/^v([0-9]+)$/i', $requested, $m)) {
            $num = (int)$m[1];
            $max = DocumentVersion::where('document_id', $document->id)
                ->whereRaw("version_label REGEXP '^v[0-9]+'")
                ->get()
                ->pluck('version_label')
                ->map(fn($v) => (int) preg_replace('/[^0-9]/', '', $v))
                ->filter()
                ->max();

            $max = $max ?: 0;
            if ($num > $max) {
                return 'v' . $num;
            } else {
                // user requested an older/equal version number — force next to avoid collision
                return 'v' . ($max + 1);
            }
        }

        // non-standard requested label: append to next vN for safety, but keep user label as suffix
        $next = $this->nextVersionLabelForDocument($document);
        return $next;
    }
}
