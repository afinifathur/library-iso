{{-- resources/views/documents/create.blade.php --}}
@extends('layouts.iso')

@section('content')
<div class="container-narrow">
    {{-- New document heading --}}
    <h2>New Document (Upload Baseline)</h2>

    {{-- Validation summary --}}
    @if($errors->any())
      <div style="margin-bottom:12px;padding:10px;border-radius:8px;background:#fff1f2;color:#9f1239;">
        <strong>There were problems with your submission:</strong>
        <ul style="margin:8px 0 0 18px;">
          @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    {{-- Form --}}
    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="page-card">
      @csrf

      <div class="form-row">
          <label class="small-muted">Category</label>
          <select name="category_id" class="form-input" required>
              <option value="">-- pilih kategori --</option>
              @foreach($categories ?? [] as $c)
                <option value="{{ $c->id }}" {{ (int)old('category_id') === (int)$c->id ? 'selected' : '' }}>
                  {{ $c->code ?? $c->name }}
                </option>
              @endforeach
          </select>
          @error('category_id') <div class="small-muted text-danger">{{ $message }}</div> @enderror
      </div>

      <div class="form-row">
          <label class="small-muted">Department</label>
          <select name="department_id" class="form-input" required>
              <option value="">-- pilih department --</option>
              @foreach($departments as $d)
                <option value="{{ $d->id }}" {{ (int)old('department_id', $defaultDepartment ?? '') === (int)$d->id ? 'selected' : '' }}>
                  {{ $d->code }} — {{ $d->name }}
                </option>
              @endforeach
          </select>
          @error('department_id') <div class="small-muted text-danger">{{ $message }}</div> @enderror
      </div>

      <div class="form-row">
          <label class="small-muted">Doc Code (optional)</label>
          <input type="text" name="doc_code" value="{{ old('doc_code', $doc_code ?? '') }}" class="form-input" placeholder="IK.QA-FL.01 ...">
          @error('doc_code') <div class="small-muted text-danger">{{ $message }}</div> @enderror
      </div>

      <div class="form-row">
          <label class="small-muted">Title</label>
          <input type="text" name="title" value="{{ old('title', $title ?? '') }}" class="form-input" required>
          @error('title') <div class="small-muted text-danger">{{ $message }}</div> @enderror
      </div>

      <div class="form-row">
          <label class="small-muted">Master file <small>(.doc / .docx) — required</small></label>
          <input type="file" name="master_file" class="form-input" required>
          @error('master_file') <div class="small-muted text-danger">{{ $message }}</div> @enderror
      </div>

      <div class="form-row">
          <label class="small-muted">PDF (optional)</label>
          <input type="file" name="file" class="form-input" accept="application/pdf">
          @error('file') <div class="small-muted text-danger">{{ $message }}</div> @enderror
      </div>

      <div class="form-row">
          <label class="small-muted">Version label</label>
          <input type="text" name="version_label" value="{{ old('version_label', 'v1') }}" class="form-input" placeholder="v1">
          @error('version_label') <div class="small-muted text-danger">{{ $message }}</div> @enderror
      </div>

      <div class="form-row">
          <label class="small-muted">Pasted text (optional)</label>
          <textarea name="pasted_text" rows="8" class="form-textarea">{{ old('pasted_text') }}</textarea>
          @error('pasted_text') <div class="small-muted text-danger">{{ $message }}</div> @enderror
      </div>

      <div class="form-row">
          <label class="small-muted">Change note (optional)</label>
          <textarea name="change_note" rows="3" class="form-textarea">{{ old('change_note') }}</textarea>
      </div>

      {{-- Hidden fields --}}
      <input type="hidden" name="doc_number" value="{{ old('doc_number','') }}">
      <input type="hidden" name="approved_by" value="{{ old('approved_by','') }}">

      <div class="form-row" style="display:flex; gap:8px; align-items:center;">
          <button class="btn btn-primary" type="submit" name="submit_for" value="save">Save Draft</button>
          <a href="{{ route('drafts.index') }}" class="btn btn-muted">Open Drafts</a>
          <a href="{{ route('documents.index') }}" class="btn-muted">Cancel</a>
      </div>
    </form>
</div>
@endsection
