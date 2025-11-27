{{-- resources/views/documents/show.blade.php --}}
@extends('layouts.iso')

@section('title', ($document->doc_code ? $document->doc_code.' — ' : '').$document->title)

@section('content')
@php
    use Illuminate\Support\Facades\Storage;

    $user = auth()->user();

    // Versi yang dipakai untuk tampilan utama (defensive)
    $currentVersion = $version ?? ($document->currentVersion ?? null);

    // fallback: try latestVersion if provided separately
    if (! $currentVersion) {
        $currentVersion = $latestVersion ?? null;
    }

    // Tentukan target versi untuk submit approval:
    $submitVersionId = optional($currentVersion)->id ?? ($document->current_version_id ?? null);

    // cek hak submit (kabag)
    $canShowSubmit = false;
    if ($user && $submitVersionId) {
        if (method_exists($user, 'hasRole') && $user->hasRole('kabag')) {
            $canShowSubmit = true;
        } elseif (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['kabag'])) {
            $canShowSubmit = true;
        }
    }

    $currentStatus = optional($currentVersion)->status;
    $isFinal = in_array($currentStatus, ['approved', 'rejected'], true);

    // pastikan $relatedLinks selalu array
    if (!isset($relatedLinks) || !is_array($relatedLinks)) {
        // try decode if it's JSON stored in document
        $relatedLinks = [];
        if (!empty($document->related_links)) {
            if (is_array($document->related_links)) {
                $relatedLinks = $document->related_links;
            } elseif (is_string($document->related_links)) {
                $decoded = json_decode($document->related_links, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $relatedLinks = $decoded;
                } else {
                    // as fallback, split by newlines (some places store one-per-line)
                    $lines = preg_split('/\r\n|\r|\n/', trim($document->related_links));
                    foreach ($lines as $ln) {
                        $ln = trim($ln);
                        if ($ln === '') continue;
                        $relatedLinks[] = ['label' => $ln, 'url' => $ln];
                    }
                }
            }
        }
    }

    // cek hak delete (trash) untuk tombol Recycle Bin (mr/director/admin)
    $canTrash = false;
    if ($user) {
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['mr','director','admin'])) {
            $canTrash = true;
        } else {
            // fallback: if roles relation exists
            try {
                if (method_exists($user, 'roles') && is_callable([$user, 'roles'])) {
                    $roles = (array) optional($user->roles()->pluck('name'))->toArray();
                    if (count(array_intersect($roles, ['mr','director','admin'])) > 0) {
                        $canTrash = true;
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    // Prepare versions list defensively
    $versions = $versions ?? ($document->versions ?? collect());

    // Determine a version object to check pdf_path - prefer currentVersion then latestVersion then first of relation
    $v = $currentVersion ?? ($latestVersion ?? null);
    if (! $v) {
        try {
            $v = ($document->versions && $document->versions->count()) ? $document->versions->first() : null;
        } catch (\Throwable $e) {
            $v = null;
        }
    }

    // Prepare PDF URL safely (assumes files stored on 'public' disk or path stored as 'pdf_path')
    $pdfUrl = null;
    $pdfExists = false;
    if ($v && !empty($v->pdf_path)) {
        try {
            $pdfExists = Storage::disk('public')->exists(ltrim($v->pdf_path, '/'));
        } catch (\Throwable $e) {
            $pdfExists = false;
        }
        if ($pdfExists) {
            // generate URL using storage disk (works when php artisan storage:link is set)
            try {
                $pdfUrl = Storage::disk('public')->url(ltrim($v->pdf_path, '/'));
            } catch (\Throwable $e) {
                // fallback to asset('storage/...')
                $pdfUrl = asset('storage/' . ltrim($v->pdf_path, '/'));
            }
        }
    }
@endphp

<div class="app-container" style="max-width:1200px;margin:18px auto;">
  <div style="display:flex;align-items:flex-start;gap:18px;">
    {{-- LEFT: main content --}}
    <div style="flex:1">
      <h1 style="margin:0 0 8px 0;">
        {{ $document->doc_code ? $document->doc_code.' — ' : '' }}{{ $document->title }}
      </h1>

      <div class="small-muted" style="margin-bottom:12px;">
        Department: {{ optional($document->department)->name ?? '-' }}
        @if(!empty($document->category))
          · Category: {{ $document->category }}
        @endif
      </div>

      {{-- Action bar --}}
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;align-items:center;">
        {{-- Edit opens modal --}}
        <button class="btn" id="btnEditDoc" type="button">✏️ Edit</button>

        {{-- Download current version --}}
        @if($currentVersion && $currentVersion->file_path)
          <a class="btn" href="{{ route('documents.versions.download', $currentVersion->id) }}">Download PDF</a>
        @else
          <button class="btn-muted" type="button" disabled>Download PDF</button>
        @endif

        {{-- Compare --}}
        @if(Route::has('documents.compare'))
          <a class="btn-muted" href="{{ route('documents.compare', $document->id ?? 0) }}">Compare</a>
        @endif

        {{-- Submit for Approval (Kabag) --}}
        @if($canShowSubmit && ! $isFinal)
          <form method="POST" action="{{ route('versions.submit', $submitVersionId) }}" style="display:inline;margin-left:6px;">
            @csrf
            <button type="submit" class="btn btn-primary">Submit for Approval</button>
          </form>
        @endif

        {{-- Delete -> move to Recycle Bin (only for roles allowed) --}}
        @if($canTrash && $currentVersion)
          <form method="POST"
                action="{{ route('versions.trash', $currentVersion->id) }}"
                style="display:inline;margin-left:6px;"
                onsubmit="return confirm('Move this version to Recycle Bin?');">
            @csrf
            <button type="submit" class="btn" style="background:#ef4444;color:#fff;border:none;border-radius:8px;padding:.45rem .75rem;">
              Delete
            </button>
          </form>
        @endif

        {{-- === PDF viewer / download inline buttons (inserted here) === --}}
        @if($pdfUrl)
          <button id="openPdfBtn" class="btn" type="button" style="margin-left:6px;" data-pdf-url="{{ $pdfUrl }}">Open PDF Viewer</button>
          <a href="{{ $pdfUrl }}" class="btn" target="_blank" rel="noopener noreferrer" style="margin-left:6px;">Download PDF</a>
        @else
          <div class="small-muted" style="margin-left:6px;">PDF belum di-upload untuk versi ini.</div>
        @endif
      </div>

      {{-- Version content --}}
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:8px;padding:18px;min-height:300px;">
        @if($currentVersion && ($currentVersion->pasted_text || $currentVersion->plain_text))
          {{-- show text (preserve line breaks). Use nl2br(e(...)) wrapped with {!! !!} --}}
          <pre style="white-space:pre-wrap;font-family:inherit;border:0;background:transparent;padding:0;margin:0;">
{!! nl2br(e($currentVersion->pasted_text ?? $currentVersion->plain_text)) !!}
          </pre>
        @elseif($currentVersion && $currentVersion->file_path)
          <div>
            File attached.
            <a href="{{ route('documents.versions.download', $currentVersion->id) }}">Download</a> to view.
          </div>
        @else
          <div class="small-muted">
            Belum ada isi versi. Klik <b>Edit</b> lalu tambahkan isi (paste text) atau upload file.
          </div>
        @endif
      </div>

      {{-- Inline PDF viewer (hidden by default) --}}
      @if($pdfUrl)
        <div id="pdfWrapper" style="display:none; margin-top:16px;">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <div style="display:flex;gap:8px;align-items:center;">
              <button id="pdfZoomIn" type="button" class="btn-small" title="Zoom in">+</button>
              <button id="pdfZoomOut" type="button" class="btn-small" title="Zoom out">−</button>
              <span id="pdfZoomPct" class="small-muted" style="margin-left:6px;">100%</span>
            </div>
            <div>
              <a id="pdfOpenNew" href="{{ $pdfUrl }}" target="_blank" rel="noopener noreferrer" class="btn-small">Open in new tab</a>
              <button id="pdfClose" type="button" class="btn-small" style="margin-left:8px;">Close</button>
            </div>
          </div>

          <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">
            <iframe id="pdfIframe"
                    src=""
                    width="100%"
                    height="700"
                    frameborder="0"
                    style="display:block;border:0;transform-origin:top left;"></iframe>
          </div>
        </div>
      @endif
    </div>

    {{-- RIGHT: sidebar --}}
    <div style="width:320px;">
      {{-- Versions --}}
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:8px;padding:12px;">
        <h4 style="margin-top:0;margin-bottom:8px">Versions</h4>
        <ul style="list-style:none;padding:0;margin:0">
          @forelse($versions as $ver)
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
                    <a class="btn-small" href="{{ route('versions.show', $ver->id) }}">Open</a>
                  @endif
                  @if($ver->file_path)
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

      {{-- Dokumen terkait (sidebar) --}}
      <div class="card" style="margin-top:12px;padding:14px;border-radius:8px;background:#fff;border:1px solid #eef3f8;">
        <div style="font-weight:700;color:#0b5ed7;margin-bottom:8px;">Dokumen terkait</div>

        @if(!empty($relatedLinks))
          <ul style="list-style:none;padding:0;margin:0;">
            @foreach($relatedLinks as $link)
              @php
                // ensure shape safety: label/url
                $lkLabel = is_array($link) && isset($link['label']) ? $link['label'] : (is_string($link) ? $link : ($link->label ?? 'Link'));
                $lkUrl = is_array($link) && isset($link['url']) ? $link['url'] : (is_string($link) ? $link : ($link->url ?? '#'));
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

{{-- Modal Edit/Create Version --}}
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

    <div style="display:flex;gap:12px;">
      <div style="flex:1">
        {{-- Category (kode singkat, bukan category_id) --}}
        <label for="category">Kategori</label>
        @php $cat = old('category', $document->category ?? ''); @endphp
        <select id="category" name="category" class="input" required>
          <option value="" disabled {{ $cat ? '' : 'selected' }}>Pilih kategori…</option>
          <option value="IK"  {{ $cat==='IK'  ? 'selected' : '' }}>IK - Instruksi Kerja</option>
          <option value="UT"  {{ $cat==='UT'  ? 'selected' : '' }}>UT - Uraian Tugas</option>
          <option value="FR"  {{ $cat==='FR'  ? 'selected' : '' }}>FR - Formulir</option>
          <option value="PJM" {{ $cat==='PJM' ? 'selected' : '' }}>PJM - Prosedur Jaminan Mutu</option>
          <option value="MJM" {{ $cat==='MJM' ? 'selected' : '' }}>MJM - Manual Jaminan Mutu</option>
          <option value="DP"  {{ $cat==='DP'  ? 'selected' : '' }}>DP - Dokumen Pendukung</option>
          <option value="DE"  {{ $cat==='DE'  ? 'selected' : '' }}>DE - Dokumen Eksternal</option>
        </select>

        {{-- Document code --}}
        <label for="doc_code" style="margin-top:8px">Document code</label>
        <input id="doc_code"
               type="text"
               name="doc_code"
               value="{{ old('doc_code', $document->doc_code) }}"
               class="input"
               placeholder="Kosongkan untuk auto-generate">

        {{-- Title --}}
        <label for="title" style="margin-top:8px">Title</label>
        <input id="title"
               type="text"
               name="title"
               value="{{ old('title', $document->title) }}"
               class="input"
               required>

        {{-- Department --}}
        <label for="department_id" style="margin-top:8px">Department</label>
        @php
          $selectedDept = old('department_id', $document->department_id ?? ($user->department_id ?? null));
        @endphp
        <select id="department_id" name="department_id" class="input" required>
          @foreach(\App\Models\Department::orderBy('code')->get() as $dep)
            <option value="{{ $dep->id }}" {{ (string)$selectedDept === (string)$dep->id ? 'selected' : '' }}>
              {{ $dep->code }} — {{ $dep->name }}
            </option>
          @endforeach
        </select>

        {{-- Change note --}}
        <label for="change_note" style="margin-top:8px">Change note (version)</label>
        <input id="change_note"
               name="change_note"
               value="{{ old('change_note', optional($currentVersion)->change_note ?? '') }}"
               class="input">

        {{-- Related links (textarea) --}}
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

        <label for="related_links" style="margin-top:8px" class="small-muted">
          Dokumen terkait (satu URL per baris)
        </label>
        <textarea id="related_links"
                  name="related_links"
                  rows="4"
                  class="input"
                  style="min-height:80px;">{{ $relatedDefault }}</textarea>
        <div class="small-muted" style="margin-top:2px;">
          Satu baris = satu link. Contoh: http://10.88.8.97/Library-ISO/public/index.php/documents/5
        </div>
      </div>

      <div style="width:360px">
        <input type="hidden" name="version_id" value="{{ old('version_id', optional($currentVersion)->id ?? '') }}">

        {{-- Version label --}}
        <label for="version_label">Version label</label>
        <input id="version_label"
               name="version_label"
               value="{{ old('version_label', optional($currentVersion)->version_label ?? 'v1') }}"
               class="input"
               required>

        {{-- File upload --}}
        <label for="file" style="margin-top:8px">Upload file (replace)</label>
        <input id="file" type="file" name="file" accept=".pdf,.doc,.docx">

        {{-- Pasted text --}}
        <label for="pasted_text" style="margin-top:8px">Paste text (for search / display)</label>
        @php
          $pastedForModal = old(
              'pasted_text',
              optional($currentVersion)->pasted_text ?? optional($currentVersion)->plain_text ?? ''
          );
        @endphp
        <textarea id="pasted_text"
                  name="pasted_text"
                  rows="8"
                  style="width:100%">{{ $pastedForModal }}</textarea>

        {{-- Signed by --}}
        <label for="signed_by" style="margin-top:8px">Signed by</label>
        <input id="signed_by"
               name="signed_by"
               value="{{ old('signed_by', optional($currentVersion)->signed_by ?? '') }}"
               class="input">

        {{-- Signed date --}}
        <label for="signed_at" style="margin-top:8px">Signed date</label>
        <input id="signed_at"
               type="date"
               name="signed_at"
               value="{{ old('signed_at', optional($currentVersion->signed_at ?? null) ? optional($currentVersion->signed_at)->format('Y-m-d') : '') }}"
               class="input">

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

<style>
  .btn-small{
    display:inline-block;
    padding:6px 8px;
    border-radius:6px;
    background:#eef7ff;
    color:#0b63d4;
    text-decoration:none;
    font-size:13px;
  }
  .input{
    width:100%;
    padding:8px;
    border-radius:6px;
    border:1px solid #e6eef8;
    margin-top:6px;
  }
  /* simple utilities */
  .small-muted{ color:#6b7280; font-size:.95rem; }
  .btn{ display:inline-block; padding:.45rem .75rem; border-radius:6px; background:#eef7ff; color:#0b63d4; border:1px solid #dbeefd; text-decoration:none; cursor:pointer; }
  .btn-muted{ display:inline-block; padding:.45rem .75rem; border-radius:6px; background:#f3f4f6; color:#6b7280; border:1px solid #e6eef8; text-decoration:none; cursor:default; }
  .btn-primary{ background:#0b5ed7; color:#fff; border:1px solid #0b5ed7; }
</style>

<script>
  (function () {
    var editBtn   = document.getElementById('btnEditDoc');
    var modal     = document.getElementById('editModal');
    var cancelBtn = document.getElementById('cancelEdit');

    if (editBtn && modal) {
      editBtn.addEventListener('click', function () {
        modal.style.display = 'block';
      });
    }

    if (cancelBtn && modal) {
      cancelBtn.addEventListener('click', function () {
        modal.style.display = 'none';
      });
    }

    // Sync version_id from query string ke hidden input (kalau buka ?version_id=xx)
    try {
      var params = new URLSearchParams(window.location.search);
      var v = params.get('version_id');
      if (v) {
        var input = document.querySelector('input[name="version_id"]');
        if (input) input.value = v;
      }
    } catch (e) { /* ignore */ }

    // PDF viewer controls
    var openBtn = document.getElementById('openPdfBtn');
    var pdfWrapper = document.getElementById('pdfWrapper');
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
        // adjust height so scaled content remains visible reasonably
        pdfIframe.style.height = (700 / currentZoom) + 'px';
      }
      if (pdfZoomPct) pdfZoomPct.textContent = Math.round(currentZoom * 100) + '%';
    }

    if (openBtn && pdfIframe && pdfWrapper) {
      openBtn.addEventListener('click', function () {
        var pdfUrl = openBtn.getAttribute('data-pdf-url') || openBtn.dataset.pdfUrl;
        if (!pdfUrl) {
          alert('PDF URL tidak tersedia.');
          return;
        }
        if (!pdfIframe.getAttribute('src')) {
          pdfIframe.setAttribute('src', pdfUrl);
          var pdfOpenNew = document.getElementById('pdfOpenNew');
          if (pdfOpenNew) pdfOpenNew.setAttribute('href', pdfUrl);
        }
        pdfWrapper.style.display = (pdfWrapper.style.display === 'none' || pdfWrapper.style.display === '') ? 'block' : 'none';
        // reset zoom to default when opening
        setZoom(1);
        if (pdfWrapper.style.display === 'block') pdfWrapper.scrollIntoView({behavior:'smooth'});
      });
    }

    if (pdfClose) {
      pdfClose.addEventListener('click', function () {
        if (pdfWrapper) pdfWrapper.style.display = 'none';
      });
    }
    if (pdfZoomIn) {
      pdfZoomIn.addEventListener('click', function () { setZoom(currentZoom + 0.1); });
    }
    if (pdfZoomOut) {
      pdfZoomOut.addEventListener('click', function () { setZoom(currentZoom - 0.1); });
    }
  })();
</script>
@endsection
