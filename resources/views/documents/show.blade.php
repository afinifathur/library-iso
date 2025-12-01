{{-- resources/views/documents/show.blade.php --}}
@extends('layouts.iso')

@section('title', (($document->doc_code ?? '') ? ($document->doc_code.' — ') : '') . ($document->title ?? 'Document'))

@section('content')
@php
    use Illuminate\Support\Str;
    use Illuminate\Support\Facades\Route;
    use Illuminate\Support\Facades\Storage;

    $user = auth()->user();

    // Resolve current version with several fallbacks
    $currentVersion = $version ?? ($document->currentVersion ?? null);
    if (! $currentVersion) {
        $currentVersion = $latestVersion ?? null;
    }
    if (! $currentVersion && isset($document->versions) && $document->versions instanceof \Illuminate\Support\Collection) {
        $currentVersion = $document->versions->first() ?? null;
    }

    $submitVersionId = optional($currentVersion)->id ?? ($document->current_version_id ?? null);

    // permission: canShowSubmit (kabag)
    $canShowSubmit = false;
    if ($user && $submitVersionId) {
        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['kabag'])) $canShowSubmit = true;
            if (method_exists($user, 'hasRole') && $user->hasRole('kabag')) $canShowSubmit = true;
        } catch (\Throwable $e) { /* ignore permission check errors */ }
    }

    $currentStatus = optional($currentVersion)->status;
    $isFinal = in_array($currentStatus, ['approved','rejected'], true);

    // normalize relatedLinks -> array of ['url','label']
    $relatedLinks = $relatedLinks ?? [];
    if (! is_array($relatedLinks) || empty($relatedLinks)) {
        $relatedLinks = [];
        if (!empty($document->related_links)) {
            if (is_array($document->related_links)) {
                foreach ($document->related_links as $ln) {
                    $ln = trim((string)$ln);
                    if ($ln === '') continue;
                    $relatedLinks[] = ['url' => $ln, 'label' => $ln];
                }
            } elseif (is_string($document->related_links)) {
                $decoded = json_decode($document->related_links, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    foreach ($decoded as $ln) {
                        $ln = trim((string)$ln);
                        if ($ln === '') continue;
                        $relatedLinks[] = ['url' => $ln, 'label' => $ln];
                    }
                } else {
                    $lines = preg_split('/\r\n|\r|\n/', trim($document->related_links));
                    foreach ($lines as $ln) {
                        $ln = trim((string)$ln);
                        if ($ln === '') continue;
                        $relatedLinks[] = ['url' => $ln, 'label' => $ln];
                    }
                }
            }
        }
    }

    // permission: canTrash (mr / director / admin)
    $canTrash = false;
    if ($user) {
        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['mr','director','admin'])) {
                $canTrash = true;
            } elseif (method_exists($user, 'roles')) {
                $roles = (array) optional($user->roles()->pluck('name'))->toArray();
                $canTrash = count(array_intersect($roles, ['mr','director','admin'])) > 0;
            }
        } catch (\Throwable $e) { /* ignore */ }
    }

    // permission: canEditDocument (kabag / mr / admin or policy)
    $canEditDocument = false;
    if ($user) {
        try {
            if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['mr','admin','kabag'])) $canEditDocument = true;
            elseif (method_exists($user, 'hasRole') && $user->hasRole('kabag')) $canEditDocument = true;
            elseif (method_exists($user, 'can') && $user->can('update', $document)) $canEditDocument = true;
        } catch (\Throwable $e) { /* ignore */ }
    }

    // versions collection fallback
    $versions = $versions ?? ($document->versions ?? collect());

    // Build flags for master/pdf availability (defensive disk checks)
    $masterAvailable = false;
    $pdfAvailable = false;
    $pdfUrl = null;

    if ($currentVersion) {
        $masterCandidates = [
            $currentVersion->master_path ?? null,
            $currentVersion->file_path ?? null,
            $document->master_path ?? null,
            $document->file_path ?? null,
        ];
        $pdfCandidates = [
            $currentVersion->file_path ?? null,
            $currentVersion->pdf_path ?? null,
        ];

        foreach ($masterCandidates as $p) {
            if (empty($p)) continue;
            $p = ltrim($p, '/');
            try {
                $disk = Storage::disk('documents');
                if (method_exists($disk, 'exists') && $disk->exists($p)) { $masterAvailable = true; break; }
            } catch (\Throwable $e) {
                try {
                    $disk = Storage::disk('public');
                    if (method_exists($disk, 'exists') && $disk->exists($p)) { $masterAvailable = true; break; }
                } catch (\Throwable $_) { /* ignore */ }
            }
        }

        foreach ($pdfCandidates as $p) {
            if (empty($p)) continue;
            $p = ltrim($p, '/');
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') continue;
            // prefer preview route if defined
            if (Route::has('documents.versions.preview')) {
                $pdfUrl = route('documents.versions.preview', optional($currentVersion)->id);
                $pdfAvailable = true;
                break;
            }
            // else try to build disk url (best-effort)
            try {
                $disk = Storage::disk('documents');
                if (method_exists($disk, 'exists') && $disk->exists($p) && method_exists($disk, 'url')) {
                    $pdfUrl = $disk->url($p);
                    $pdfAvailable = true;
                    break;
                }
            } catch (\Throwable $e) {
                try {
                    $pub = Storage::disk('public');
                    if (method_exists($pub, 'exists') && $pub->exists($p) && method_exists($pub, 'url')) {
                        $pdfUrl = $pub->url($p);
                        $pdfAvailable = true;
                        break;
                    }
                } catch (\Throwable $_) { /* ignore */ }
            }
        }
    }

    // departments & categories fallbacks (prefer controller to pass)
    try {
        $departments = $departments ?? (\App\Models\Department::orderBy('code')->get() ?? collect());
    } catch (\Throwable $e) {
        $departments = collect();
    }
    try {
        $categories = $categories ?? (class_exists(\App\Models\Category::class) ? \App\Models\Category::orderBy('name')->get() : collect());
    } catch (\Throwable $e) {
        $categories = collect();
    }
@endphp

<div class="app-container" style="max-width:1200px;margin:18px auto;">
  <div style="display:flex;align-items:flex-start;gap:18px;flex-wrap:wrap;">

    {{-- LEFT: main content --}}
    <div style="flex:1;min-width:320px">
      <h1 style="margin:0 0 8px 0;">
        {{ $document->doc_code ? $document->doc_code.' — ' : '' }}{{ $document->title ?? '-' }}
      </h1>

      <div class="small-muted" style="margin-bottom:12px;">
        Department: {{ optional($document->department)->name ?? '-' }}
        @if(!empty($document->category) || !empty($document->category_id))
          · Category: {{ $document->category ?? $document->category_id }}
        @endif
      </div>

      {{-- Action bar --}}
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;align-items:center;">

        {{-- Edit --}}
        @if($canEditDocument)
          <button class="btn" id="btnEditDoc" type="button">✏️ Edit</button>
        @endif

        {{-- Download master --}}
        @if($currentVersion && $masterAvailable && Route::has('documents.versions.downloadMaster'))
          <a class="btn" href="{{ route('documents.versions.downloadMaster', $currentVersion->id) }}">Download master</a>
        @elseif($currentVersion && Route::has('documents.versions.download') && $currentVersion->file_path)
          <a class="btn" href="{{ route('documents.versions.download', $currentVersion->id) }}">Download file</a>
        @else
          <button class="btn-muted" type="button" disabled>Download master</button>
        @endif

        {{-- Download PDF separate button (if available) --}}
        @if($currentVersion && $currentVersion->file_path && Route::has('documents.versions.download'))
          <a class="btn" href="{{ route('documents.versions.download', $currentVersion->id) }}">Download PDF</a>
        @endif

        {{-- Compare --}}
        @if(Route::has('documents.compare'))
          <a class="btn-muted" href="{{ route('documents.compare', $document->id ?? 0) }}">Compare</a>
        @endif

        {{-- Submit for Approval --}}
        @if($canShowSubmit && ! $isFinal && $currentVersion)
          <form method="POST" action="{{ route('versions.submit', $submitVersionId) }}" style="display:inline;margin-left:6px;">
            @csrf
            <button type="submit" class="btn btn-primary">Submit for Approval</button>
          </form>
        @endif

        {{-- Trash --}}
        @if($canTrash && $currentVersion && Route::has('versions.trash'))
          <form method="POST"
                action="{{ route('versions.trash', $currentVersion->id) }}"
                style="display:inline;margin-left:6px;"
                onsubmit="return confirm('Move this version to Recycle Bin?');">
            @csrf
            <button type="submit" class="btn" style="background:#ef4444;color:#fff;border:none;border-radius:8px;padding:.45rem .75rem;">Delete</button>
          </form>
        @endif
      </div>

      {{-- PDF VIEWER (only when pdfAvailable and preview route or URL exists) --}}
      @if($pdfAvailable && $pdfUrl)
        <div id="pdfViewerWrap" style="margin-bottom:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <div style="display:flex;gap:8px;align-items:center;">
              <button id="pdfZoomIn" type="button" class="btn-small" title="Zoom in">+</button>
              <button id="pdfZoomOut" type="button" class="btn-small" title="Zoom out">−</button>
              <span id="pdfZoomPct" class="small-muted" style="margin-left:6px;">100%</span>
            </div>
            <div style="display:flex;gap:8px;">
              <a id="pdfOpenNew" href="{{ $pdfUrl }}" target="_blank" rel="noopener noreferrer" class="btn-small">Open in new tab</a>
              @if(Route::has('documents.versions.download'))
                <a id="pdfDownload" href="{{ route('documents.versions.download', optional($currentVersion)->id) }}" class="btn-small" style="margin-left:6px;">Download</a>
              @endif
              <button id="pdfClose" type="button" class="btn-small" style="margin-left:6px;">Close</button>
            </div>
          </div>

          <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
            <iframe id="pdfIframe"
                    src="{{ $pdfUrl }}"
                    width="100%"
                    height="700"
                    frameborder="0"
                    style="display:block;border:0;transform-origin:top left;"></iframe>
          </div>

          <div class="small-muted" style="margin-top:6px;">
            Jika PDF tidak tampil (browser memblokir), gunakan tombol "Open in new tab" atau "Download".
          </div>
        </div>
      @else
        @if($currentVersion && $currentVersion->file_path)
          <div style="margin-bottom:12px;color:#6b7280;">
            PDF preview tidak tersedia. Gunakan tombol <b>Download PDF</b> untuk melihat.
          </div>
        @endif
      @endif

      {{-- Version content (plain text / pasted text) --}}
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:8px;padding:18px;min-height:300px;">
        @if($currentVersion && ($currentVersion->pasted_text || $currentVersion->plain_text))
          <div style="white-space:pre-wrap;font-family:inherit;border:0;background:transparent;padding:0;margin:0;">
            {!! nl2br(e($currentVersion->pasted_text ?? $currentVersion->plain_text)) !!}
          </div>
        @elseif($currentVersion && $currentVersion->file_path && ! $pdfAvailable)
          <div>
            File attached.
            @if(Route::has('documents.versions.download'))
              <a href="{{ route('documents.versions.download', $currentVersion->id) }}">Download</a> to view.
            @endif
          </div>
        @else
          <div class="small-muted">
            Belum ada isi versi. Klik <b>Edit</b> lalu tambahkan isi (paste text) atau upload file.
          </div>
        @endif
      </div>
    </div>

    {{-- RIGHT: sidebar --}}
    <div style="width:320px;min-width:260px;flex-shrink:0;">

      {{-- Versions list --}}
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:8px;padding:12px;">
        <h4 style="margin-top:0;margin-bottom:8px">Versions</h4>
        <ul style="list-style:none;padding:0;margin:0">
          @forelse($versions as $ver)
            @php $verIdAttr = e((string)($ver->id ?? '')); @endphp
            <li style="padding:8px 0;border-bottom:1px solid #f4f6f8;">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                  <a href="{{ route('documents.show', $document->id) }}?version_id={{ $ver->id }}">
                    {{ $ver->version_label }}
                  </a>
                  <div class="small-muted" style="font-size:12px;">
                    {{ $ver->status }} — {{ $ver->created_at ? $ver->created_at->format('Y-m-d') : '-' }}
                  </div>
                </div>
                <div style="text-align:right;">
                  @if(Route::has('versions.show'))
                    <a class="btn-small action-open"
                       href="{{ route('versions.show', $ver->id) }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       data-version-id="{{ $verIdAttr }}">
                      Open
                    </a>
                  @endif

                  @if($ver->file_path && Route::has('documents.versions.download'))
                    <a class="btn-small btn-muted" href="{{ route('documents.versions.download', $ver->id) }}">DL</a>
                  @else
                    <span class="btn-small btn-muted" style="opacity:.6;">No file</span>
                  @endif
                </div>
              </div>
            </li>
          @empty
            <li style="padding:8px 0">No versions found.</li>
          @endforelse
        </ul>
      </div>

      {{-- Related Documents --}}
      <div class="card" style="margin-top:12px;padding:14px;border-radius:8px;background:#fff;border:1px solid #eef3f8;">
        <div style="font-weight:700;color:#0b5ed7;margin-bottom:8px;">Dokumen terkait</div>

        @if(!empty($relatedLinks))
          <ul style="list-style:none;padding:0;margin:0;">
            @foreach($relatedLinks as $link)
              @php
                $lkUrl = is_array($link) ? ($link['url'] ?? '#') : (is_object($link) ? ($link->url ?? '#') : (string)$link);
                $lkLabel = is_array($link) ? ($link['label'] ?? $lkUrl) : (is_object($link) ? ($link->label ?? $lkUrl) : $lkUrl);
              @endphp
              <li style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-top:1px solid #f1f5f9;">
                <div style="flex:1;margin-right:8px;word-break:break-word;color:#0b5ed7;">
                  <a href="{{ $lkUrl }}" target="_blank" rel="noopener noreferrer" style="text-decoration:none;color:#0b5ed7;">
                    {{ $lkLabel }}
                  </a>
                </div>
                <div style="white-space:nowrap;">
                  <a href="{{ $lkUrl }}" target="_blank" rel="noopener noreferrer" class="btn" style="background:#eef7ff;border:1px solid #dbeefd;padding:.35rem .6rem;border-radius:6px;color:#0b5ed7;text-decoration:none;font-size:.85rem;">
                    Open
                  </a>
                </div>
              </li>
            @endforeach
          </ul>
        @else
          <div style="color:#6b7280;font-size:.95rem;padding:6px 0;">
            Tidak ada dokumen terkait.
          </div>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Edit / Create Version Modal (only for editors) --}}
@if($canEditDocument)
  <div id="editModal"
       style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);
              width:880px;max-width:95%;z-index:999;background:#fff;padding:18px;
              border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,0.15);">

    <form method="post"
          action="{{ route('documents.updateCombined', $document->id) }}"
          enctype="multipart/form-data"
          novalidate>
      @csrf
      @method('PUT')

      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <div style="flex:1;min-width:260px;">
          {{-- Category --}}
          <label for="category">Kategori</label>
          @php $cat = old('category_id', $document->category_id ?? ''); @endphp
          <select id="category" name="category_id" class="input">
            <option value="" {{ $cat ? '' : 'selected' }}>Pilih kategori…</option>

            @if($categories && count($categories))
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" {{ (string)$cat === (string)$c->id ? 'selected' : '' }}>
                        {{ $c->code ?? $c->name }}
                    </option>
                @endforeach
            @else
                <option value="IK"  {{ $cat==='IK'  ? 'selected' : '' }}>IK - Instruksi Kerja</option>
                <option value="UT"  {{ $cat==='UT'  ? 'selected' : '' }}>UT - Uraian Tugas</option>
                <option value="FR"  {{ $cat==='FR'  ? 'selected' : '' }}>FR - Formulir</option>
                <option value="PJM" {{ $cat==='PJM' ? 'selected' : '' }}>PJM - Prosedur Jaminan Mutu</option>
                <option value="MJM" {{ $cat==='MJM' ? 'selected' : '' }}>MJM - Manual Jaminan Mutu</option>
                <option value="DP"  {{ $cat==='DP'  ? 'selected' : '' }}>DP - Dokumen Pendukung</option>
                <option value="DE"  {{ $cat==='DE'  ? 'selected' : '' }}>DE - Dokumen Eksternal</option>
            @endif
          </select>

          {{-- Document code --}}
          <label for="doc_code" style="margin-top:8px">Document code</label>
          <input id="doc_code" type="text" name="doc_code" value="{{ old('doc_code', $document->doc_code) }}" class="input" placeholder="Kosongkan untuk auto-generate">

          {{-- Title --}}
          <label for="title" style="margin-top:8px">Title</label>
          <input id="title" type="text" name="title" value="{{ old('title', $document->title) }}" class="input" required>

          {{-- Department --}}
          <label for="department_id" style="margin-top:8px">Department</label>
          @php $selectedDept = old('department_id', $document->department_id ?? ($user->department_id ?? null)); @endphp
          <select id="department_id" name="department_id" class="input" required>
            @foreach($departments as $dep)
              <option value="{{ $dep->id }}" {{ (string)$selectedDept === (string)$dep->id ? 'selected' : '' }}>
                {{ $dep->code }} — {{ $dep->name }}
              </option>
            @endforeach
          </select>

          {{-- Change note --}}
          <label for="change_note" style="margin-top:8px">Change note (version)</label>
          <input id="change_note" name="change_note" value="{{ old('change_note', optional($currentVersion)->change_note ?? '') }}" class="input">

          {{-- Related links --}}
          @php
            $relatedDefault = old('related_links');
            if ($relatedDefault === null) {
                if (is_array($document->related_links)) {
                    $relatedDefault = implode("\n", $document->related_links);
                } elseif (is_string($document->related_links) && $document->related_links !== '') {
                    $decoded = json_decode($document->related_links, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $relatedDefault = implode("\n", $decoded);
                    } else {
                        $relatedDefault = $document->related_links;
                    }
                } else {
                    $relatedDefault = '';
                }
            }
          @endphp

          <label for="related_links" style="margin-top:8px" class="small-muted">Dokumen terkait (satu URL per baris)</label>
          <textarea id="related_links" name="related_links" rows="4" class="input" style="min-height:80px;">{{ $relatedDefault }}</textarea>
        </div>

        <div style="width:360px;min-width:220px">
          <input type="hidden" name="version_id" value="{{ old('version_id', optional($currentVersion)->id ?? '') }}">

          {{-- Version label --}}
          <label for="version_label">Version label</label>
          <input id="version_label" name="version_label" value="{{ old('version_label', optional($currentVersion)->version_label ?? 'v1') }}" class="input" required>

          {{-- Master file --}}
          <label for="master_file" style="margin-top:8px">Master file (.doc/.docx) — opsional</label>
          <input id="master_file" type="file" name="master_file" accept=".doc,.docx" class="input">
          @if(optional($currentVersion)->file_path && Str::endsWith(strtolower(optional($currentVersion)->file_path), '.docx') )
            <div class="small-muted" style="margin-top:6px;">Master saat ini: {{ basename(optional($currentVersion)->file_path) }}</div>
          @endif

          {{-- PDF file --}}
          <label for="file" style="margin-top:8px">Upload PDF (optional)</label>
          <input id="file" type="file" name="file" accept="application/pdf" class="input">
          @if(optional($currentVersion)->file_path && Str::endsWith(strtolower(optional($currentVersion)->file_path), '.pdf') )
            <div class="small-muted" style="margin-top:6px;">PDF saat ini: {{ basename(optional($currentVersion)->file_path) }}</div>
          @endif

          {{-- Pasted text --}}
          <label for="pasted_text" style="margin-top:8px">Paste text (for search / display)</label>
          @php
            $pastedForModal = old('pasted_text', optional($currentVersion)->pasted_text ?? optional($currentVersion)->plain_text ?? '');
          @endphp
          <textarea id="pasted_text" name="pasted_text" rows="6" class="input">{{ $pastedForModal }}</textarea>

          {{-- Signed by / date --}}
          <label for="signed_by" style="margin-top:8px">Signed by</label>
          <input id="signed_by" name="signed_by" value="{{ old('signed_by', optional($currentVersion)->signed_by ?? '') }}" class="input">

          <label for="signed_at" style="margin-top:8px">Signed date</label>
          @php
            $signedAtOld = old('signed_at');
            $signedAtDefault = $signedAtOld !== null ? $signedAtOld : (optional(optional($currentVersion)->signed_at)->format('Y-m-d') ?? '');
          @endphp
          <input id="signed_at" type="date" name="signed_at" value="{{ $signedAtDefault }}" class="input">

          <div style="margin-top:10px;display:flex;gap:8px;">
            <button class="btn" type="submit" name="submit_for" value="save">Save Draft</button>
            <button class="btn" type="submit" name="submit_for" value="submit">Save & Submit</button>
            <button type="button" class="btn-muted" id="cancelEdit">Cancel</button>
          </div>
        </div>
      </div>

      @if ($errors->any())
        <div style="margin-top:10px;color:#b42318;background:#fee4e2;border:1px solid #fecdca;border-radius:6px;padding:8px;">
          <ul style="margin:0;padding-left:18px;">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif
    </form>
  </div>
@endif

{{-- Page styles --}}
<style>
  .btn-small{ display:inline-block;padding:6px 8px;border-radius:6px;background:#eef7ff;color:#0b63d4;text-decoration:none;font-size:13px; }
  .input{ width:100%;padding:8px;border-radius:6px;border:1px solid #e6eef8;margin-top:6px;box-sizing:border-box; }
  .small-muted{ color:#6b7280; font-size:.95rem; }
  .btn{ display:inline-block;padding:.45rem .75rem;border-radius:6px;background:#eef7ff;color:#0b63d4;border:1px solid #dbeefd;text-decoration:none;cursor:pointer; }
  .btn-muted{ display:inline-block;padding:.45rem .75rem;border-radius:6px;background:#f3f4f6;color:#6b7280;border:1px solid #e6eef8;text-decoration:none;cursor:default; }
  .btn-primary{ background:#0b5ed7; color:#fff; border:1px solid #0b5ed7; }
  #pdfIframe { transition: transform .12s ease; }
</style>
@endsection {{-- end content --}}

@section('scripts')
<script>
  (function () {
    // Modal open/close
    var editBtn = document.getElementById('btnEditDoc');
    var modal = document.getElementById('editModal');
    var cancelBtn = document.getElementById('cancelEdit');
    if (editBtn && modal) editBtn.addEventListener('click', function () { modal.style.display = 'block'; });
    if (cancelBtn && modal) cancelBtn.addEventListener('click', function () { modal.style.display = 'none'; });

    // propagate version_id query param into hidden input
    try {
      var params = new URLSearchParams(window.location.search);
      var v = params.get('version_id');
      if (v) {
        var input = document.querySelector('input[name="version_id"]');
        if (input) input.value = v;
      }
    } catch (e) { /* ignore */ }

    // prevent double submit
    try {
      var editForm = modal ? modal.querySelector('form') : null;
      if (editForm) {
        editForm.addEventListener('submit', function () {
          Array.from(editForm.querySelectorAll('button[type="submit"]')).forEach(function(b){
            b.disabled = true;
            b.dataset.origText = b.textContent;
            b.textContent = 'Processing...';
          });
        });
      }
    } catch (e) { /* ignore */ }

    // PDF viewer controls
    var pdfWrapper = document.getElementById('pdfViewerWrap');
    var pdfIframe = document.getElementById('pdfIframe');
    var pdfClose = document.getElementById('pdfClose');
    var pdfZoomIn = document.getElementById('pdfZoomIn');
    var pdfZoomOut = document.getElementById('pdfZoomOut');
    var pdfZoomPct = document.getElementById('pdfZoomPct');
    var currentZoom = 1;
    function setZoom(z) {
      currentZoom = Math.max(0.5, Math.min(2.5, z));
      if (pdfIframe) {
        pdfIframe.style.transform = 'scale(' + currentZoom + ')';
        // maintain visible height (avoid overflow by scaling container height)
        pdfIframe.style.height = (700 / currentZoom) + 'px';
      }
      if (pdfZoomPct) pdfZoomPct.textContent = Math.round(currentZoom * 100) + '%';
    }
    if (pdfClose) pdfClose.addEventListener('click', function () { if (pdfWrapper) pdfWrapper.style.display = 'none'; });
    if (pdfZoomIn) pdfZoomIn.addEventListener('click', function () { setZoom(currentZoom + 0.1); });
    if (pdfZoomOut) pdfZoomOut.addEventListener('click', function () { setZoom(currentZoom - 0.1); });

    // Attach handlers for "Open" links in Versions list (persist to localStorage)
    function qsa(selector, ctx) { return Array.from((ctx || document).querySelectorAll(selector)); }
    function persistOpened(vid) { if (!vid) return; try { localStorage.setItem('iso_opened_version_' + vid, '1'); } catch(e){} }
    function attachVersionOpenHandlers() {
      qsa('.action-open').forEach(function(link) {
        if (link.dataset && link.dataset.isoOpenAttached) return;
        link.dataset.isoOpenAttached = '1';
        link.addEventListener('click', function () {
          try {
            var vid = this.getAttribute('data-version-id') || this.dataset.versionId;
            if (!vid) {
              var href = this.getAttribute('href') || '';
              var m = href.match(/\/(\d+)(?:$|[?#])/);
              if (m) vid = m[1];
            }
            if (vid) persistOpened(vid);
            try { if (window.opener && !window.opener.closed) window.opener.postMessage({ iso_action: 'version_opened', version_id: vid }, '*'); } catch(e){}
          } catch(e){}
        }, { passive: true });

        link.addEventListener('auxclick', function (ev) {
          if (ev.button === 1) {
            try {
              var vid = this.getAttribute('data-version-id') || this.dataset.versionId;
              if (!vid) {
                var href = this.getAttribute('href') || '';
                var m = href.match(/\/(\d+)(?:$|[?#])/);
                if (m) vid = m[1];
              }
              if (vid) persistOpened(vid);
            } catch(e){}
          }
        }, { passive: true });
      });
    }
    attachVersionOpenHandlers();

    // listen for external messages to persist opened versions
    window.addEventListener('message', function (ev) {
      try {
        var d = ev.data || {};
        if (d && d.iso_action === 'version_opened' && d.version_id) {
          try { localStorage.setItem('iso_opened_version_' + String(d.version_id), '1'); } catch(e){}
        }
      } catch (e) {}
    }, false);

    // expose helper
    window.__docShow = { attachVersionOpenHandlers: attachVersionOpenHandlers };
  })();
</script>
@endsection
