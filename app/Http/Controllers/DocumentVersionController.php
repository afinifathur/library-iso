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
    protected string $diskName = 'documents';

    /* ------------------------
     * Form: create
     * ------------------------ */
    public function create(Request $request)
    {
        $document = null;
        if ($request->filled('document_id')) {
            $document = Document::find($request->query('document_id'));
        }
        return view('versions.create', compact('document'));
    }

    /* ------------------------
     * Store new version (draft or publish if explicitly requested)
     * ------------------------ */
    public function store(Request $request)
{
    $request->validate([
        'document_id'   => ['required','integer','exists:documents,id'],
        'version_label' => ['nullable','string','max:50'],
        'file'          => ['required_without:pasted_text','nullable','file','mimes:pdf','max:51200'],
        'master_file'   => ['nullable','file','mimes:doc,docx','max:102400'],
        'pasted_text'   => ['nullable','string','max:500000'],
        'action'        => ['nullable','in:draft,submit,publish'],
        'change_note'   => ['nullable','string','max:2000'],
        'signed_by'     => ['nullable','string','max:191'],
        'signed_at'     => ['nullable','date'],
    ]);

    $document = Document::findOrFail($request->input('document_id'));
    $user = $request->user();
    $disk = $this->getDisk();

    // determine version label
    $preferred = $request->input('version_label') ? trim($request->input('version_label')) : null;
    $versionLabel = $this->normalizeVersionLabel($document, $preferred);

    // folder path
    $docCode = $document->doc_code ?: ('doc_'.$document->id);
    $folder = trim($docCode . '/' . $versionLabel, '/');

    $pdf_path = null;
    $file_mime = null;
    $checksum = null;
    $master_path = null;

    // master file
    if ($request->hasFile('master_file')) {
        $master = $request->file('master_file');
        $safe = $this->safeFilename($master->getClientOriginalName());
        $masterName = now()->timestamp . '_master_' . Str::random(6) . '_' . $safe;
        $master_path = trim($folder . '/master/' . $masterName, '/');

        try { 
            $disk->put($master_path, file_get_contents($master->getRealPath())); 
        } catch (\Throwable) { 
            $master_path = null; 
        }
    }

    // pdf file
    if ($request->hasFile('file')) {
        $file = $request->file('file');
        $safe = $this->safeFilename($file->getClientOriginalName());
        $fileName = now()->timestamp . '_pdf_' . Str::random(6) . '_' . $safe;
        $pdf_path = trim($folder . '/' . $fileName, '/');

        try {
            $content = file_get_contents($file->getRealPath());
            $disk->put($pdf_path, $content);
            $file_mime = $file->getClientMimeType() ?: 'application/pdf';
            $checksum = hash('sha256', $content);
        } catch (\Throwable) {
            $pdf_path = null;
            $file_mime = null;
            $checksum = null;
        }
    }

    // remove previous draft
    DocumentVersion::where('document_id', $document->id)
        ->where('created_by', $user->id)
        ->whereIn('status', ['draft','rejected'])
        ->delete();

    // create version
    $version = DocumentVersion::create([
        'document_id'   => $document->id,
        'version_label' => $versionLabel,
        'status'        => 'draft',
        'approval_stage'=> 'KABAG',
        'created_by'    => $user->id ?? null,
        'file_path'     => $pdf_path,
        'pdf_path'      => $pdf_path,
        'master_path'   => $master_path,
        'file_mime'     => $file_mime,
        'checksum'      => $checksum,
        'change_note'   => $request->input('change_note') ?? null,
        'plain_text'    => $request->input('pasted_text') ? trim($request->input('pasted_text')) : null,
        'pasted_text'   => $request->input('pasted_text') ? trim($request->input('pasted_text')) : null,
        'signed_by'     => $request->input('signed_by') ?? null,
        'signed_at'     => $request->filled('signed_at') ? Carbon::parse($request->input('signed_at')) : null,
    ]);

    // optional publish
    if ($request->input('action') === 'publish' && $this->userHasAnyRole($user, ['director','admin'])) {
        DB::transaction(function() use ($version, $user, $document) {
            $version->update(['status'=>'approved','approval_stage'=>'DONE','approved_by'=>$user->id,'approved_at'=>now()]);
            $document->update(['current_version_id'=>$version->id, 'revision_date'=>now()]);
        });
    }

    $this->maybeAudit('version_created', $user->id ?? null, $document->id, $version->id, $request->ip(), [
        'pdf' => $pdf_path, 'master' => $master_path, 'version' => $versionLabel
    ]);

    return redirect()->route('versions.show', $version)->with('success', 'Versi tersimpan sebagai draft.');
}


    /* ------------------------
     * Edit form
     * ------------------------ */
    public function edit(DocumentVersion $version)
    {
        $document = $version->document;
        return view('versions.edit', compact('version','document'));
    }

    /* ------------------------
     * Update existing version
     * - keep old masters (do not delete historical files)
     * - if master_file uploaded -> add new master_path
     * - if file uploaded -> add new pdf_path and file_path
     * ------------------------ */
    public function update(Request $request, DocumentVersion $version)
    {
        $request->validate([
            'version_label' => ['required','string','max:50'],
            'file'          => ['nullable','file','mimes:pdf','max:51200'],
            'master_file'   => ['nullable','file','mimes:doc,docx','max:102400'],
            'pasted_text'   => ['nullable','string','max:500000'],
            'change_note'   => ['nullable','string','max:2000'],
            'signed_by'     => ['nullable','string','max:191'],
            'signed_at'     => ['nullable','date'],
            'action'        => ['nullable','in:save,submit'],
        ]);

        $user = $request->user();
        $disk = $this->getDisk();

        // decide final version label: coerce to next if user tried to reuse older label
        $preferred = trim($request->input('version_label'));
        $versionLabel = $this->normalizeVersionLabel($version->document, $preferred, $version->id);

        $docCode = $version->document->doc_code ?: ('doc_'.$version->document_id);
        $folder = trim($docCode . '/' . $versionLabel, '/');

        // master
        if ($request->hasFile('master_file')) {
            $master = $request->file('master_file');
            $safe = $this->safeFilename($master->getClientOriginalName());
            $masterName = now()->timestamp . '_master_' . Str::random(6) . '_' . $safe;
            $masterPath = trim($folder . '/master/' . $masterName, '/');
            try {
                $disk->put($masterPath, file_get_contents($master->getRealPath()));
                $version->master_path = $masterPath;
            } catch (\Throwable) {
                // ignore write errors
            }
        }

        // pdf
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $safe = $this->safeFilename($file->getClientOriginalName());
            $fileName = now()->timestamp . '_pdf_' . Str::random(6) . '_' . $safe;
            $pdfPath = trim($folder . '/' . $fileName, '/');
            try {
                $content = file_get_contents($file->getRealPath());
                $disk->put($pdfPath, $content);
                $version->pdf_path = $pdfPath;
                $version->file_path = $pdfPath; // backward compatible
                $version->file_mime = $file->getClientMimeType() ?: 'application/pdf';
                $version->checksum = hash('sha256', $content);
            } catch (\Throwable) {
                // ignore write errors (keep old)
            }
        }

        // metadata
        $version->version_label = $versionLabel;
        if ($request->has('change_note')) $version->change_note = $request->input('change_note');
        if ($request->has('signed_by')) $version->signed_by = $request->input('signed_by');
        if ($request->has('signed_at')) $version->signed_at = $request->filled('signed_at') ? Carbon::parse($request->input('signed_at')) : null;
        if ($request->has('pasted_text')) {
            $t = $request->input('pasted_text') ?: null;
            $version->plain_text = $t;
            $version->pasted_text = $t;
        }

        $version->save();

        // if requested to submit
        if ($request->input('action') === 'submit') {
            return $this->submitForApproval($request, $version->id);
        }

        $this->maybeAudit('version_updated', $user->id ?? null, $version->document_id, $version->id, $request->ip(), [
            'pdf' => $version->pdf_path, 'master' => $version->master_path
        ]);

        return redirect()->route('versions.show', $version)->with('success','Version updated.');
    }

    /* ------------------------
     * Show single version
     * ------------------------ */
    public function show(DocumentVersion $version)
    {
        $version->load(['document.department','creator']);
        $otherVersions = DocumentVersion::where('document_id', $version->document_id)->orderByDesc('id')->get();
        return view('versions.show', ['version'=>$version,'document'=>$version->document,'otherVersions'=>$otherVersions]);
    }

    /* ------------------------
     * Submit for approval (KABAG -> MR etc)
     * ------------------------ */
    public function submitForApproval(Request $request, $id)
    {
        $user = Auth::user();
        if (! $user) abort(403);

        $version = DocumentVersion::with('document')->findOrFail($id);

        // only drafts/rejected allowed
        if (! in_array($version->status, ['draft','rejected'], true)) {
            return back()->with('error','Hanya draft/rejected yang dapat diajukan.');
        }

        // prevent double submission
        $pending = DocumentVersion::where('document_id', $version->document_id)
            ->whereIn('status', ['submitted','pending'])
            ->exists();
        if ($pending) {
            return back()->with('error','Terdapat revisi lain yang sedang diajukan.');
        }

        // determine next stage
        $nextStage = 'MR';
        if ($this->userHasAnyRole($user, ['mr'])) $nextStage = 'DIRECTOR';
        if ($this->userHasAnyRole($user, ['director'])) $nextStage = 'DONE';

        DB::transaction(function() use ($version, $user, $nextStage) {
            $version->status = 'submitted';
            $version->approval_stage = $nextStage;
            if ($this->hasColumn($version, 'submitted_by')) $version->submitted_by = $user->id;
            if ($this->hasColumn($version, 'submitted_at')) $version->submitted_at = now();
            $version->save();

            $this->insertApprovalLog($version->id, $user->id, $this->getCurrentRoleName($user), 'submit', 'Submitted to ' . $nextStage);
        });

        $this->maybeAudit('version_submitted', $user->id ?? null, $version->document_id, $version->id, $request->ip(), ['stage'=>$nextStage]);

        return redirect()->route('approval.index')->with('success','Draft berhasil diajukan ke ' . $nextStage);
    }

    /* ------------------------
     * Helpers
     * ------------------------ */

    protected function getDisk()
    {
        try { return Storage::disk($this->diskName); } catch (\Throwable) { return Storage::disk('public'); }
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

    protected function userHasAnyRole($user, array $roles): bool
    {
        if (! $user) return false;
        if (method_exists($user, 'hasAnyRole')) {
            try { if ($user->hasAnyRole($roles)) return true; } catch (\Throwable) {}
        }
        if (method_exists($user, 'roles')) {
            try {
                $names = $user->roles()->pluck('name')->map(fn($n)=>strtolower($n))->toArray();
                foreach ($roles as $r) if (in_array(strtolower($r), $names, true)) return true;
            } catch (\Throwable) {}
        }
        if (isset($user->roles) && is_iterable($user->roles)) {
            $names = collect($user->roles)->pluck('name')->map(fn($n)=>strtolower($n))->toArray();
            foreach ($roles as $r) if (in_array(strtolower($r), $names, true)) return true;
        }
        $whitelist = ['direktur@peroniks.com','adminqc@peroniks.com'];
        if (! empty($user->email) && in_array(strtolower($user->email), $whitelist, true)) return true;
        return false;
    }

    protected function getCurrentRoleName($user): string
    {
        if (! $user) return 'unknown';
        if (method_exists($user, 'getRoleNames')) {
            try { $names = $user->getRoleNames()->toArray(); return $names[0] ?? 'unknown'; } catch (\Throwable) {}
        }
        if (method_exists($user, 'roles')) {
            try { return $user->roles()->pluck('name')->first() ?? 'unknown'; } catch (\Throwable) {}
        }
        if (isset($user->roles) && is_iterable($user->roles)) {
            return collect($user->roles)->pluck('name')->first() ?? 'unknown';
        }
        return 'unknown';
    }

    protected function insertApprovalLog(int $versionId, int $userId, string $role, string $action, ?string $note = null): void
    {
        if (! Schema::hasTable('approval_logs')) return;
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

    protected function safeFilename(string $original): string
    {
        $name = preg_replace('/[^\w\.\-]+/u','_', $original);
        return $name ?: ('file_'.Str::random(8));
    }

    /**
     * Normalize/generate version label:
     * - If preferred is null -> next vN
     * - If preferred provided but <= current max -> coerce to next vN (to avoid collisions)
     * - Optionally pass $ignoreId to allow reusing same label for updating that version
     */
    protected function normalizeVersionLabel(Document $document, ?string $preferred = null, ?int $ignoreId = null): string
    {
        // find max numeric suffix among existing versions for this document
        $existing = DocumentVersion::where('document_id', $document->id)
            ->when($ignoreId, fn($q) => $q->where('id', '<>', $ignoreId))
            ->pluck('version_label')->filter()->values()->all();

        $max = 0;
        foreach ($existing as $lbl) {
            if (preg_match('/v(\d+)$/i', trim($lbl), $m)) {
                $n = (int)$m[1];
                if ($n > $max) $max = $n;
            }
        }

        $next = $max + 1;

        // if user didn't provide label -> next
        if (empty($preferred)) {
            return 'v' . $next;
        }

        // normalize user's input: if it's like "v3" accept; else force to lowercase and trim
        $p = trim($preferred);
        if (preg_match('/^v(\d+)$/i', $p, $m)) {
            $num = (int)$m[1];
            if ($num > $max) {
                return 'v' . $num;
            } else {
                // user tried to create older or duplicate version -> force next
                return 'v' . $next;
            }
        }

        // if not in vN pattern, don't allow free arbitrary labels -> use next
        return 'v' . $next;
    }
}
