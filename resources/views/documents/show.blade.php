@extends('layouts.iso')

@section('title', $document->doc_code . ' — ' . $document->title)

@section('content')
<div class="app-container" style="max-width:1200px;margin:18px auto;">
  <div style="display:flex;align-items:flex-start;gap:18px;">
    <div style="flex:1">
      <h1 style="margin:0 0 8px 0;">{{ $document->doc_code }} — {{ $document->title }}</h1>
      <div class="small-muted" style="margin-bottom:12px;">Department: {{ $document->department->name ?? '-' }}</div>

      <div style="display:flex;gap:8px;margin-bottom:12px;">
        <button class="btn" id="btnEditDoc">✏️ Edit</button>

        @if($version && $version->file_path)
          <a class="btn" href="{{ route('documents.versions.download', $version->id) }}">Download PDF</a>
        @else
          <button class="btn-muted" disabled>Download PDF</button>
        @endif

        {{-- compare route: if not present, hide or adjust --}}
        @if(Route::has('documents.compare'))
          <a class="btn-muted" href="{{ route('documents.compare', $document->id ?? 0) }}">Compare</a>
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
                  <div class="small-muted" style="font-size:12px;">{{ $v->status }} — {{ $v->created_at ? $v->created_at->format('Y-m-d') : '-' }}</div>
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

<div id="editModal" style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:880px;max-width:95%;z-index:999;background:#fff;padding:18px;border-radius:8px;box-shadow:0 8px 30px rgba(0,0,0,0.15);">
  <form method="post" action="{{ route('documents.updateCombined', $document->id) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div style="display:flex;gap:12px;">
      <div style="flex:1">
        <label>Document code</label><br>
        <input type="text" name="doc_code" value="{{ old('doc_code', $document->doc_code) }}" class="input" required>

        <label style="margin-top:8px">Title</label><br>
        <input type="text" name="title" value="{{ old('title', $document->title) }}" class="input" required>

        <label style="margin-top:8px">Department</label><br>
        <select name="department_id" class="input" required>
          @foreach(\App\Models\Department::orderBy('code')->get() as $dep)
            <option value="{{ $dep->id }}" {{ $document->department_id == $dep->id ? 'selected' : '' }}>{{ $dep->code }} — {{ $dep->name }}</option>
          @endforeach
        </select>

        <label style="margin-top:8px">Change note (version)</label><br>
        <input name="change_note" value="{{ old('change_note', $version->change_note ?? '') }}" class="input">
      </div>

      <div style="width:360px">
        <input type="hidden" name="version_id" value="{{ old('version_id', $version->id ?? '') }}">
        <label>Version label</label><br>
        <input name="version_label" value="{{ old('version_label', $version->version_label ?? 'v1') }}" class="input" required>

        <label style="margin-top:8px">Upload file (replace)</label><br>
        <input type="file" name="file" accept=".pdf,.doc,.docx">

        <label style="margin-top:8px">Paste text (for search / display)</label><br>
        <textarea name="pasted_text" rows="8" style="width:100%">{{ old('pasted_text', $version->pasted_text ?? $version->plain_text ?? '') }}</textarea>

        <label style="margin-top:8px">Signed by</label><br>
        <input name="signed_by" value="{{ old('signed_by', $version->signed_by ?? '') }}" class="input">

        <label style="margin-top:8px">Signed date</label><br>
        <input type="date" name="signed_at" value="{{ old('signed_at', $version->signed_at ? \Carbon\Carbon::parse($version->signed_at)->format('Y-m-d') : '') }}" class="input">

        <div style="margin-top:10px;display:flex;gap:8px;">
          <button class="btn" type="submit" name="submit_for" value="save">Save Draft</button>
          <button class="btn" type="submit" name="submit_for" value="submit">Save & Submit</button>
          <button type="button" class="btn-muted" id="cancelEdit">Cancel</button>
        </div>
      </div>
    </div>
  </form>
</div>

<style>
.btn-small { display:inline-block;padding:6px 8px;border-radius:6px;background:#eef7ff;color:#0b63d4;text-decoration:none;font-size:13px }
.input { width:100%;padding:8px;border-radius:6px;border:1px solid #e6eef8;margin-top:6px }
</style>

<script>
document.getElementById('btnEditDoc').addEventListener('click', function(){ document.getElementById('editModal').style.display='block'; });
document.getElementById('cancelEdit').addEventListener('click', function(){ document.getElementById('editModal').style.display='none'; });

(function(){
  const params = new URLSearchParams(window.location.search);
  const v = params.get('version_id');
  if(v) {
    const input = document.querySelector('input[name="version_id"]');
    if(input) input.value = v;
  }
})();
</script>
@endsection
