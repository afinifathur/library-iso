@extends('layouts.iso')

@section('content')
<div class="container-narrow">
  <h2>New Document (Upload Baseline)</h2>

  {{-- Flash --}}
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  {{-- Validation Errors --}}
  @if ($errors->any())
    <div class="alert alert-warn">
      <ul class="mb-0 ps-3">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="post" action="{{ route('documents.store') }}" enctype="multipart/form-data" novalidate>
    @csrf

    {{-- Category --}}
    <div class="form-row">
      <label for="category_id">Category <span class="req">*</span></label>
      <select id="category_id" name="category_id" class="form-input" required>
        <option value="">-- pilih kategori --</option>
        @foreach(\App\Models\Category::orderBy('code')->get() as $cat)
          <option value="{{ $cat->id }}"
            @selected(old('category_id') == $cat->id)>
            {{ $cat->code }} — {{ $cat->name }}
          </option>
        @endforeach
      </select>
      @error('category_id') <div class="field-error">{{ $message }}</div> @enderror
    </div>

    {{-- Department --}}
    <div class="form-row">
      <label for="department_id">Department <span class="req">*</span></label>
      <select id="department_id" name="department_id" class="form-input" required>
        @foreach($departments as $dep)
          <option value="{{ $dep->id }}"
            @selected((string) old('department_id', auth()->user()->department_id ?? '') === (string) $dep->id)>
            {{ $dep->code }} — {{ $dep->name }}
          </option>
        @endforeach
      </select>
      @error('department_id') <div class="field-error">{{ $message }}</div> @enderror
    </div>

    {{-- Document Code --}}
    <div class="form-row">
      <label for="doc_code">Document code (optional)</label>
      <input id="doc_code" type="text" name="doc_code" class="form-input"
             value="{{ old('doc_code') }}" placeholder="IK.QA-FL.001 (optional)">
      @error('doc_code') <div class="field-error">{{ $message }}</div> @enderror
    </div>

    {{-- Title --}}
    <div class="form-row">
      <label for="title">Title <span class="req">*</span></label>
      <input id="title" type="text" name="title" class="form-input" value="{{ old('title') }}" required>
      @error('title') <div class="field-error">{{ $message }}</div> @enderror
    </div>

    {{-- File --}}
    <div class="form-row">
      <label for="file">Upload PDF / DOCX (optional)</label>
      <input id="file" type="file" name="file" class="form-input" accept=".pdf,.doc,.docx">
      <div class="small-muted">Maks 50MB. Bisa dikosongkan bila hanya ingin paste teks.</div>
      @error('file') <div class="field-error">{{ $message }}</div> @enderror
    </div>

    {{-- Pasted Text --}}
    <div class="form-row">
      <label for="pasted_text">Paste text content (optional, untuk search)</label>
      <textarea id="pasted_text" name="pasted_text" rows="8" class="form-textarea">{{ old('pasted_text') }}</textarea>
      @error('pasted_text') <div class="field-error">{{ $message }}</div> @enderror
    </div>

    {{-- Version Label --}}
    <div class="form-row">
      <label for="version_label">Version label (default v1)</label>
      <input id="version_label" type="text" name="version_label"
             value="{{ old('version_label','v1') }}" class="form-input">
      @error('version_label') <div class="field-error">{{ $message }}</div> @enderror
    </div>

    {{-- Version Dates --}}
    <div class="row-flex gap">
      <div class="col">
        <label for="created_at">Version date (optional)</label>
        <input id="created_at" type="date" name="created_at" class="form-input" value="{{ old('created_at') }}">
        @error('created_at') <div class="field-error">{{ $message }}</div> @enderror
      </div>

      <div class="col">
        <label for="approved_at">Approved date (optional)</label>
        <input id="approved_at" type="date" name="approved_at" class="form-input" value="{{ old('approved_at') }}">
        @error('approved_at') <div class="field-error">{{ $message }}</div> @enderror
      </div>
    </div>

    {{-- Submit --}}
    <div class="actions">
      <button class="btn" type="submit">Save baseline (v1) & Publish</button>
      <a href="{{ route('documents.index') }}" class="btn-muted">Cancel</a>
    </div>

  </form>
</div>
@endsection
