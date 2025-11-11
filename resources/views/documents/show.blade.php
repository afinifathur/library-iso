{{-- resources/views/documents/show.blade.php --}}
@extends('layouts.iso')

@section('title', ($document->doc_code ? $document->doc_code.' — ' : '').$document->title)

@section('content')
@php
    // Tentukan target versi untuk submit approval:
    // urutan prioritas: versi yang sedang dibuka -> relasi currentVersion -> field current_version_id
    $submitVersionId = $version->id
        ?? ($document->currentVersion->id ?? null)
        ?? ($document->current_version_id ?? null);

    $canShowSubmit =
        auth()->check()
        && auth()->user()->hasRole('kabag')
        && $submitVersionId;

    // Cek status final untuk sembunyikan tombol bila sudah final
    $currentStatus = $version->status
        ?? ($document->currentVersion->status ?? null);
    $isFinal = in_array($currentStatus, ['approved', 'rejected'], true);
@endphp

<div class="app-container" style="max-width:1200px;margin:18px auto;">
  <div style="display:flex;align-items:flex-start;gap:18px;">
    <div style="flex:1">
      <h1 style="margin:0 0 8px 0;">{{ $document->doc_code ? $document->doc_code.' — ' : '' }}{{ $document->title }}</h1>
      <div class="small-muted" style="margin-bottom:12px;">
        Department: {{ $document->department->name ?? '-' }}
        @if(!empty($document->category))
          · Category: {{ $document->category }}
        @endif
      </div>

      {{-- Action bar --}}
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;">
        <button class="btn" id="btnEditDoc" type="button">✏️ Edit</button>

        @if($version && $version->file_path)
          <a class="btn" href="{{ route('documents.versions.download', $version->id) }}">Download PDF</a>
        @else
          <button class="btn-muted" type="button" disabled>Download PDF</button>
        @endif

        @if(Route::has('documents.compare'))
          <a class="btn-muted" href="{{ route('documents.compare', $document->id ?? 0) }}">Compare</a>
        @endif

        {{-- NEW: Submit for Approval (role: kabag) --}}
        @if($canShowSubmit && ! $isFinal)
          <form method="POST" action="{{ route('versions.submit', $submitVersionId) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn-primary">Submit for Approval</button>
          </form>
        @endif
      </div>

      <div style="background:#fff;border:1px solid #eef3f8;border-radius:8px;padding:18px;min-height:300px;">
        @if($version && ($version->pasted_text || $version->plain_text))
          <pre style="white-space:pre-wrap;font-family:inherit;">{!! nl2br(e($version->pasted_text ?? $version->plain_text)) !!}</pre>
        @elseif($version && $version->file_path)
          <div>
            File attached. <a href="{{ route('documents.versions.download', $version->id) }}">Download</a> to view.
          </div>
        @else
          <div class="small-muted">Belum ada isi versi. Klik <b>Edit</b> lalu tambahkan isi (paste text) atau upload file PDF.</div>
        @endif
      </div>
    </div>

    <div style="width:320px;">
      <div style="background:#fff;border:1px solid #eef3f8;border-radius:8px;padding:12px;">
        <h4 style="margin-top:0;margin-bottom:8px">Versions</h4>
        <ul style="list-style:none;padding:0;margin:0">
          @forelse($versions as $v)
            <li style="padding:8px 0;border-bottom:1px solid #f4f6f8;">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                  <a href="{{ route('documents.show', $document->id) }}?version_id={{ $v->id }}">{{ $v->version_label }}</a>
                  <div class="small-muted" style="font-size:12px;">
                    {{ $v->status }} — {{ $v->created_at ? $v->created_at->format('Y-m-d') : '-' }}
                  </div>
                </div>
                <div style="text-align:right;">
                  <a class="btn-small" href="{{ route('versions.show', $v->id) }}">Open</a>
                  <a class="btn-small btn-muted" href="{{ route('documents.versions.download', $v->id) }}">DL</a>
                </div>
              </div>
            </li>
          @empty
            <li style="padding:8px 0">No versions found.</li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
</div>

{{-- Modal Edit/Create Version --}}
<div id="editModal" style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:880px;max-width:95%;z-index:999;background:#fff;padding:18px;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,0.15);">
  <form method="post" action="{{ route('documents.updateCombined', $document->id) }}" enctype="multipart/form-data" novalidate>
    @csrf
    @method('PUT')

    <div style="display:flex;gap:12px;">
      <div style="flex:1">
        {{-- Category --}}
        <label for="category">Kategori</label>
        <select id="category" name="category" class="input" required>
          @php $cat = old('category', $document->category ?? ''); @endphp
          <option value="" disabled {{ $cat ? '' : 'selected' }}>Pilih kategori…</option>
          <option value="IK"  {{ $cat==='IK'  ? 'selected' : '' }}>IK - Instruksi Kerja</option>
          <option value="UT"  {{ $cat==='UT'  ? 'selected' : '' }}>UT - Uraian Tugas</option>
          <option value="FR"  {{ $cat==='FR'  ? 'selected' : '' }}>FR - Formulir</option>
          <option value="PJM" {{ $cat==='PJM' ? 'selected' : '' }}>PJM - Prosedur Jaminan Mutu</option>
          <option value="MJM" {{ $cat==='MJM' ? 'selected' : '' }}>MJM - Manual Jaminan Mutu</option>
          <option value="DP"  {{ $cat==='DP'  ? 'selected' : '' }}>DP - Dokumen Pendukung</option>
          <option value="DE"  {{ $cat==='DE'  ? 'selected' : '' }}>DE - Dokumen Eksternal</option>
        </select>

        {{-- Document code (optional; akan di-generate jika kosong) --}}
        <label for="doc_code" style="margin-top:8px">Document code</label>
        <input id="doc_code" type="text" name="doc_code" value="{{ old('doc_code', $document->doc_code) }}" class="input" placeholder="Kosongkan untuk auto-generate">

        {{-- Title --}}
        <label for="title" style="margin-top:8px">Title</label>
        <input id="title" type="text" name="title" value="{{ old('title', $document->title) }}" class="input" required>

        {{-- Department --}}
        <label for="department_id" style="margin-top:8px">Department</label>
        <select id="department_id" name="department_id" class="input" required>
          @php $selectedDept = old('department_id', $document->department_id ?? auth()->user()->department_id ?? null); @endphp
          @foreach(\App\Models\Department::orderBy('code')->get() as $dep)
            <option value="{{ $dep->id }}" {{ (string)$selectedDept === (string)$dep->id ? 'selected' : '' }}>
              {{ $dep->code }} — {{ $dep->name }}
            </option>
          @endforeach
        </select>

        {{-- Change note --}}
        <label for="change_note" style="margin-top:8px">Change note (version)</label>
        <input id="change_note" name="change_note" value="{{ old('change_note', $version->change_note ?? '') }}" class="input">
      </div>

      <div style="width:360px">
        <input type="hidden" name="version_id" value="{{ old('version_id', $version->id ?? '') }}">

        {{-- Version label --}}
        <label for="version_label">Version label</label>
        <input id="version_label" name="version_label" value="{{ old('version_label', $version->version_label ?? 'v1') }}" class="input" required>

        {{-- File upload --}}
        <label for="file" style="margin-top:8px">Upload file (replace)</label>
        <input id="file" type="file" name="file" accept=".pdf,.doc,.docx">

        {{-- Pasted text --}}
        <label for="pasted_text" style="margin-top:8px">Paste text (for search / display)</label>
        <textarea id="pasted_text" name="pasted_text" rows="8" style="width:100%">{{ old('pasted_text', $version->pasted_text ?? $version->plain_text ?? '') }}</textarea>

        {{-- Signed by --}}
        <label for="signed_by" style="margin-top:8px">Signed by</label>
        <input id="signed_by" name="signed_by" value="{{ old('signed_by', $version->signed_by ?? '') }}" class="input">

        {{-- Signed date --}}
        <label for="signed_at" style="margin-top:8px">Signed date</label>
        <input id="signed_at" type="date" name="signed_at" value="{{ old('signed_at', $version->signed_at ? \Carbon\Carbon::parse($version->signed_at)->format('Y-m-d') : '') }}" class="input">

        <div style="margin-top:10px;display:flex;gap:8px;">
          <button class="btn" type="submit" name="submit_for" value="save">Save Draft</button>
          <button class="btn" type="submit" name="submit_for" value="submit">Save & Submit</button>
          <button type="button" class="btn-muted" id="cancelEdit">Cancel</button>
        </div>
      </div>
    </div>

    {{-- Simple errors --}}
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
.btn-small{display:inline-block;padding:6px 8px;border-radius:6px;background:#eef7ff;color:#0b63d4;text-decoration:none;font-size:13px}
.input{width:100%;padding:8px;border-radius:6px;border:1px solid #e6eef8;margin-top:6px}
</style>

<script>
document.getElementById('btnEditDoc').addEventListener('click', function(){
  document.getElementById('editModal').style.display='block';
});
document.getElementById('cancelEdit').addEventListener('click', function(){
  document.getElementById('editModal').style.display='none';
});

// Sync version_id from query string to hidden input (deep-link ke versi tertentu)
(function(){
  const params = new URLSearchParams(window.location.search);
  const v = params.get('version_id');
  if(v){
    const input = document.querySelector('input[name="version_id"]');
    if(input) input.value = v;
  }
})();
</script>
@endsection
