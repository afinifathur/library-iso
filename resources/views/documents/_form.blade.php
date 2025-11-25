{{-- resources/views/documents/_form.blade.php --}}
{{-- Partial form untuk create / edit document --}}

<form method="POST"
      action="{{ $action }}"
      enctype="multipart/form-data"
      class="page-card">

    @csrf
    @if(!empty($method) && strtoupper($method) !== 'POST')
        @method($method)
    @endif

    {{-- CATEGORY --}}
    <div class="form-row">
        <label class="small-muted">Category</label>
        <select name="category_id" class="form-input" required>
            <option value="">-- pilih kategori --</option>
            @foreach($categories ?? [] as $cat)
                @php
                    $selectedCategory = old('category_id', $document->category_id ?? null);
                @endphp
                <option value="{{ $cat->id }}" {{ $selectedCategory == $cat->id ? 'selected' : '' }}>
                    {{ $cat->code ?? $cat->name }}
                </option>
            @endforeach
        </select>
        @error('category_id')
            <div class="small-muted text-danger">{{ $message }}</div>
        @enderror
    </div>

    {{-- DEPARTMENT --}}
    <div class="form-row">
        <label class="small-muted">Department</label>
        <select name="department_id" class="form-input" required>
            <option value="">-- pilih department --</option>
            @foreach($departments as $d)
                @php
                    $selectedDept = old('department_id', $document->department_id ?? null);
                @endphp
                <option value="{{ $d->id }}" {{ $selectedDept == $d->id ? 'selected' : '' }}>
                    {{ $d->code }} — {{ $d->name }}
                </option>
            @endforeach
        </select>
        @error('department_id')
            <div class="small-muted text-danger">{{ $message }}</div>
        @enderror
    </div>

    {{-- DOC CODE --}}
    <div class="form-row">
        <label class="small-muted">Doc Code (optional)</label>
        <input type="text"
               name="doc_code"
               value="{{ old('doc_code', $document->doc_code ?? '') }}"
               class="form-input"
               placeholder="IK.QA-FL.001 ...">
        @error('doc_code')
            <div class="small-muted text-danger">{{ $message }}</div>
        @enderror
    </div>

    {{-- TITLE --}}
    <div class="form-row">
        <label class="small-muted">Title</label>
        <input type="text"
               name="title"
               value="{{ old('title', $document->title ?? '') }}"
               class="form-input"
               required>
        @error('title')
            <div class="small-muted text-danger">{{ $message }}</div>
        @enderror
    </div>

    {{-- RELATED LINKS --}}
    <div class="form-row">
        <label class="small-muted">Dokumen terkait (satu URL per baris)</label>

        @php
            // default value untuk edit: implode array related_links jadi multiline string
            $relatedLinksDefault = '';
            if (isset($document) && is_array($document->related_links)) {
                $relatedLinksDefault = implode("\n", $document->related_links);
            }
        @endphp

        <textarea name="related_links"
                  rows="4"
                  class="form-textarea"
                  placeholder="http://...">{{ old('related_links', $relatedLinksDefault) }}</textarea>

        <div class="small-muted">
            Masukkan URL/hyperlink dokumen terkait, satu baris = satu link.<br>
            Contoh: http://10.88.8.97/Library-ISO/public/index.php/documents/5
        </div>

        @error('related_links')
            <div class="small-muted text-danger">{{ $message }}</div>
        @enderror
    </div>

    {{-- MASTER FILE --}}
    <div class="form-row">
        <label class="small-muted">
            Master file <small>(.doc / .docx) — {{ empty($document) ? 'required' : 'opsional (kosongkan jika tidak ganti)' }}</small>
        </label>
        <input type="file"
               name="master_file"
               class="form-input"
               accept=".doc,.docx"
               {{ empty($document) ? 'required' : '' }}>

        @if(!empty($document))
            @php
                $currentVersion = $document->relationLoaded('currentVersion')
                    ? $document->currentVersion
                    : ($document->currentVersion ?? null);
            @endphp

            @if($currentVersion && $currentVersion->file_path)
                <div class="small-muted">
                    Master sebelumnya: {{ $currentVersion->file_path }}
                </div>
            @endif
        @endif

        @error('master_file')
            <div class="small-muted text-danger">{{ $message }}</div>
        @enderror
    </div>

    {{-- PDF FILE --}}
    <div class="form-row">
        <label class="small-muted">PDF (optional)</label>
        <input type="file"
               name="file"
               class="form-input"
               accept="application/pdf">
        @error('file')
            <div class="small-muted text-danger">{{ $message }}</div>
        @enderror
    </div>

    {{-- VERSION LABEL --}}
    <div class="form-row">
        <label class="small-muted">Version label</label>
        <input type="text"
               name="version_label"
               value="{{ old('version_label', $version_label ?? 'v1') }}"
               class="form-input"
               placeholder="v1">
        @error('version_label')
            <div class="small-muted text-danger">{{ $message }}</div>
        @enderror
    </div>

    {{-- PASTED TEXT --}}
    <div class="form-row">
        <label class="small-muted">Pasted text (optional)</label>
        @php
            $pastedDefault = '';
            if (isset($document)) {
                $pastedDefault = optional(
                    $document->relationLoaded('currentVersion')
                        ? $document->currentVersion
                        : ($document->currentVersion ?? null)
                )->pasted_text ?? '';
            }
        @endphp
        <textarea name="pasted_text"
                  rows="8"
                  class="form-textarea">{{ old('pasted_text', $pastedDefault) }}</textarea>
        @error('pasted_text')
            <div class="small-muted text-danger">{{ $message }}</div>
        @enderror
    </div>

    {{-- CHANGE NOTE --}}
    <div class="form-row">
        <label class="small-muted">Change note (optional)</label>
        <textarea name="change_note"
                  rows="3"
                  class="form-textarea">{{ old('change_note') }}</textarea>
    </div>

    {{-- hidden metadata fields (optional) --}}
    <input type="hidden" name="doc_number" value="{{ old('doc_number', $document->doc_number ?? '') }}">
    <input type="hidden" name="approved_by" value="{{ old('approved_by', $document->approved_by ?? '') }}">

    {{-- BUTTONS --}}
    <div class="form-row" style="display:flex; gap:8px; align-items:center;">
        <button class="btn btn-primary"
                type="submit"
                name="submit_for"
                value="save">
            {{ $submitLabel ?? 'Save Draft' }}
        </button>

        @isset($showDraftLink)
            @if($showDraftLink)
                <a href="{{ route('drafts.index') }}" class="btn btn-muted">Open Drafts</a>
            @endif
        @else
            {{-- default: tampilkan link drafts --}}
            <a href="{{ route('drafts.index') }}" class="btn btn-muted">Open Drafts</a>
        @endisset

        <a href="{{ route('documents.index') }}" class="btn-muted">Cancel</a>
    </div>
</form>
