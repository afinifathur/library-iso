@extends('layouts.iso')

@section('title', 'Edit Document')

@section('content')
<div style="max-width:800px;margin:auto;">
  <h2>Edit Document Info</h2>

  @if($errors->any())
    <div style="background:#fee2e2;color:#b91c1c;padding:10px;margin-bottom:10px;border-radius:6px;">
      <ul>
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('documents.update', $document->id) }}">
    @csrf
    @method('PUT')

    <div style="margin-bottom:10px;">
      <label>Document Code</label><br>
      <input type="text" name="doc_code" value="{{ old('doc_code', $document->doc_code) }}" class="input" required>
    </div>

    <div style="margin-bottom:10px;">
      <label>Title</label><br>
      <input type="text" name="title" value="{{ old('title', $document->title) }}" class="input" required>
    </div>

    <div style="margin-bottom:10px;">
      <label>Department</label><br>
      <select name="department_id" class="input" required>
        @foreach($departments as $dep)
          <option value="{{ $dep->id }}" {{ $document->department_id == $dep->id ? 'selected' : '' }}>
            {{ $dep->name }}
          </option>
        @endforeach
      </select>
    </div>

    <button class="btn" type="submit">💾 Save Changes</button>
    <a href="{{ route('documents.show', $document->id) }}" class="btn-muted">Cancel</a>
  </form>
</div>
@endsection
