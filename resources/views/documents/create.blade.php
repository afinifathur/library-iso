@extends('layouts.iso')

@section('content')
<div style="max-width:900px;margin:0 auto;">
  <h2 style="margin-top:0">Upload Signed PDF</h2>

  @if(session('success'))
    <div style="padding:10px;background:#ecfdf5;border:1px solid #d1fae5;margin-bottom:12px;border-radius:8px;color:#065f46;">
      {{ session('success') }}
    </div>
  @endif

  @if(session('error'))
    <div style="padding:10px;background:#fee2e2;border:1px solid #fca5a5;margin-bottom:12px;border-radius:8px;color:#7f1d1d;">
      {{ session('error') }}
    </div>
  @endif

  {{-- Tampilkan error validasi --}}
  @if ($errors->any())
    <div style="padding:10px;background:#fff7ed;border:1px solid #fdba74;margin-bottom:12px;border-radius:8px;color:#9a3412;">
      <ul style="margin:0;padding-left:18px;">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('documents.uploadPdf') }}" enctype="multipart/form-data">
    @csrf

    <div class="form-row">
      <label>Document code (optional)</label>
      <input class="form-input" type="text" name="doc_code" value="{{ old('doc_code', $doc->doc_code ?? '') }}">
    </div>

    <div class="form-row">
      <label>Title</label>
      <input class="form-input" type="text" name="title" value="{{ old('title', $doc->title ?? '') }}" required>
    </div>

    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
      <div style="flex:1;min-width:240px;">
        <label>Department</label>
        <select class="form-input" name="department_id" required>
          <option value="">-- pilih --</option>
          @foreach($departments as $dept)
            <option value="{{ $dept->id }}" {{ (string)old('department_id') === (string)$dept->id ? 'selected' : '' }}>
              {{ $dept->code }} — {{ $dept->name }}
            </option>
          @endforeach
        </select>
      </div>

      <div style="width:180px;min-width:160px;">
        <label>Version label</label>
        <input class="form-input" type="text" name="version_label" value="{{ old('version_label','v1') }}" required>
      </div>
    </div>

    <div class="form-row" style="display:flex;gap:12px;flex-wrap:wrap;">
      <div style="flex:1;min-width:240px;">
        <label>Signed by (Kabag)</label>
        <input class="form-input" type="text" name="signed_by" value="{{ old('signed_by') }}">
      </div>
      <div style="width:200px;min-width:180px;">
        <label>Signed at (date)</label>
        <input class="form-input" type="date" name="signed_at" value="{{ old('signed_at') }}">
      </div>
    </div>

    <div class="form-row">
      <label>Change note</label>
      <textarea class="form-textarea" name="change_note" rows="3">{{ old('change_note') }}</textarea>
    </div>

    <div class="form-row">
      <label>Master file (Word or Excel) — optional</label>
      <input class="form-input" type="file" name="master_file" accept=".doc,.docx,.xls,.xlsx">
      <div class="small-muted">Upload editable master (docx/xlsx) if available.</div>
    </div>

    <div class="form-row">
      <label>Signed PDF file (for download)</label>
      <input class="form-input" type="file" name="file" accept="application/pdf">
      <div class="small-muted">Signed PDF will be stored for user download.</div>
    </div>

    <div class="form-row">
      <label>Paste document text (optional) — overrides extraction</label>
      <textarea class="form-textarea" name="pasted_text" rows="8" placeholder="Paste the document text here…">{{ old('pasted_text') }}</textarea>
      <div class="small-muted">If provided, this text will be used for indexing and diffs.</div>
    </div>

    <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
      <button type="submit" class="btn">Upload PDF</button>
      <a href="{{ route('documents.index') }}" class="btn-muted">Back</a>
    </div>
  </form>
</div>
@endsection
