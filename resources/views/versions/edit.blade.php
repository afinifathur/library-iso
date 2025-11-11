@extends('layouts.iso')

@section('title','Edit Version')

@section('content')
<div style="max-width:800px;margin:auto;">
  <h2>Edit Version {{ $version->version_label }} — {{ $document->doc_code }}</h2>

  <form action="{{ route('versions.update', $version->id) }}" method="post" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div style="margin-bottom:10px;">
      <label>Version label</label><br>
      <input name="version_label" value="{{ old('version_label', $version->version_label) }}" class="input" required>
    </div>

    <div style="margin-bottom:10px;">
      <label>Replace file (optional)</label><br>
      <input type="file" name="file" accept=".pdf,.doc,.docx">
      @if($version->file_path)
        <div class="small-muted">Current file: {{ $version->file_path }}</div>
      @endif
    </div>

    <div style="margin-bottom:10px;">
      <label>Pasted text</label><br>
      <textarea name="pasted_text" rows="8" style="width:100%">{{ old('pasted_text', $version->pasted_text) }}</textarea>
    </div>

    <div style="margin-bottom:10px;">
      <label>Change note</label><br>
      <input name="change_note" value="{{ old('change_note', $version->change_note) }}" class="input">
    </div>

    <div style="margin-bottom:10px;">
      <label>Signed by</label><br>
      <input name="signed_by" value="{{ old('signed_by', $version->signed_by) }}" class="input">
    </div>

    <div style="margin-bottom:10px;">
      <label>Signed date</label><br>
      <input type="date" name="signed_at" value="{{ old('signed_at', $version->signed_at ? \Carbon\Carbon::parse($version->signed_at)->format('Y-m-d') : date('Y-m-d')) }}" class="input">
    </div>

    <button class="btn" type="submit">Save Changes</button>
    <a class="btn-muted" href="{{ route('documents.show', $document->id) }}">Cancel</a>
  </form>
</div>
@endsection
