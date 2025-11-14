{{-- resources/views/documents/_form.blade.php --}}
{{-- Partial form untuk create / edit document --}}
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="page-card">
    @csrf
    @if(!empty($method) && strtoupper($method) !== 'POST')
        @method($method)
    @endif

    <div class="form-row">
        <label class="small-muted">Category</label>
        <select name="category_id" class="form-input" required>
            <option value="">-- pilih kategori --</option>
            @foreach($categories ?? [] as $cat)
                <option value="{{ $cat->id }}" {{ (old('category_id', $document->category_id ?? '') == $cat->id) ? 'selected' : '' }}>
                    {{ $cat->code ?? $cat->name }}
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
                <option value="{{ $d->id }}" {{ (old('department_id', $document->department_id ?? '') == $d->id) ? 'selected' : '' }}>
                    {{ $d->code }} — {{ $d->name }}
                </option>
            @endforeach
        </select>
        @error('department_id') <div class="small-muted text-danger">{{ $message }}</div> @enderror
    </div>

    <div class="form-row">
        <label class="small-muted">Doc Code (optional)</label>
        <input type="text" name="doc_code" value="{{ old('doc_code', $document->doc_code ?? '') }}" class="form-input" placeholder="IK.QA-FL.01 ...">
        @error('doc_code') <div class="small-muted text-danger">{{ $message }}</div> @enderror
    </div>

    <div class="form-row">
        <label class="small-muted">Title</label>
        <input type="text" name="title" value="{{ old('title', $document->title ?? '') }}" class="form-input" required>
        @error('title') <div class="small-muted text-danger">{{ $message }}</div> @enderror
    </div>

    <div class="form-row">
        <label class="small-muted">Master file <small>(.doc / .docx) — required</small></label>
        <input type="file" name="master_file" class="form-input" {{ empty($document) ? 'required' : '' }}>
        @if(!empty($document) && ($document->current_version?->file_path ?? false))
            <div class="small-muted">Master sebelumnya: {{ optional($document->current_version)->file_path }}</div>
        @endif
        @error('master_file') <div class="small-muted text-danger">{{ $message }}</div> @enderror
    </div>

    <div class="form-row">
        <label class="small-muted">PDF (optional)</label>
        <input type="file" name="file" class="form-input" accept="application/pdf">
        @error('file') <div class="small-muted text-danger">{{ $message }}</div> @enderror
    </div>

    <div class="form-row">
        <label class="small-muted">Version label</label>
        <input type="text" name="version_label" value="{{ old('version_label', $version_label ?? 'v1') }}" class="form-input" placeholder="v1">
        @error('version_label') <div class="small-muted text-danger">{{ $message }}</div> @enderror
    </div>

    <div class="form-row">
        <label class="small-muted">Pasted text (optional)</label>
        <textarea name="pasted_text" rows="8" class="form-textarea">{{ old('pasted_text', $document->current_version->pasted_text ?? '') }}</textarea>
        @error('pasted_text') <div class="small-muted text-danger">{{ $message }}</div> @enderror
    </div>

    <div class="form-row">
        <label class="small-muted">Change note (optional)</label>
        <textarea name="change_note" rows="3" class="form-textarea">{{ old('change_note') }}</textarea>
    </div>

    {{-- hidden metadata fields (optional) --}}
    <input type="hidden" name="doc_number" value="{{ old('doc_number', $document->doc_number ?? '') }}">
    <input type="hidden" name="approved_by" value="{{ old('approved_by') }}">

    <div class="form-row" style="display:flex; gap:8px; align-items:center;">
        <button class="btn" type="submit" name="submit_for" value="save">Save</button>
        <button class="btn" type="submit" name="submit_for" value="submit">Save &amp; Submit</button>
        <a href="{{ route('documents.index') }}" class="btn-muted">Cancel</a>
    </div>
</form>
