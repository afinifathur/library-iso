@extends('layouts.iso')

@section('title','New Version')

@section('content')
<div style="max-width:800px;margin:auto;">
  <h2>{{ $document ? 'New Version for '.$document->doc_code : 'New Document Version' }}</h2>

  <form action="{{ route('versions.store') }}" method="post" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="document_id" value="{{ old('document_id', $document->id ?? '') }}">

    <div style="margin-bottom:10px;">
      <label>Version label (ex: v1, v2)</label><br>
      <input name="version_label" value="{{ old('version_label','v1') }}" class="input" required>
    </div>

    <div style="margin-bottom:10px;">
      <label>Upload file (PDF / DOCX) — optional if you paste text</label><br>
      <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx">
    </div>

    <div style="margin-bottom:10px;">
      <label>Or paste text (for indexing & search)</label><br>
      <textarea name="pasted_text" rows="8" style="width:100%">{{ old('pasted_text') }}</textarea>
    </div>

    <div style="margin-bottom:10px;">
      <label>Change note</label><br>
      <input name="change_note" value="{{ old('change_note') }}" class="input">
    </div>

    <div style="margin-bottom:10px;">
      <label>Signed by (name)</label><br>
      <input name="signed_by" value="{{ old('signed_by') }}" class="input">
    </div>

    <div style="margin-bottom:10px;">
      <label>Signed date</label><br>
      <input type="date" name="signed_at" value="{{ old('signed_at', date('Y-m-d')) }}" class="input">
    </div>

    <button class="btn" type="submit">Save Version (Draft)</button>
    <a class="btn-muted" href="{{ $document ? route('documents.show', $document->id) : route('documents.index') }}">Cancel</a>
  </form>
</div>
@endsection
