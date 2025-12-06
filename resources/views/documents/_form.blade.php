{{-- resources/views/documents/_form.blade.php --}}
{{-- Partial form untuk create / edit document
     Menyediakan single Save button + upload_type selector yang mengontrol submit_for & mode.
--}}

@php
    // default upload type: if editing an existing document, prefer 'replace'
    $defaultUploadType = old('upload_type', isset($document) ? 'replace' : 'new');
@endphp

<form method="POST"
      action="{{ $action }}"
      enctype="multipart/form-data"
      class="page-card">

    @csrf
    @if(!empty($method) && strtoupper($method) !== 'POST')
        @method($method)
    @endif

    {{-- Hidden metadata fields used by JS --}}
    <input type="hidden" name="mode" value="{{ old('mode', $defaultUploadType === 'replace' ? 'replace' : 'new') }}">
    <input type="hidden" name="submit_for" value="{{ old('submit_for', $defaultUploadType === 'replace' ? 'draft' : 'publish') }}">

    {{-- UPLOAD TYPE (New | Replace) --}}
    <div class="form-row">
        <label class="small-muted">Jenis pengajuan</label>
        <select id="upload_type_select" name="upload_type" class="form-input" required>
            <option value="">-- pilih jenis pengajuan --</option>
            <option value="new" {{ $defaultUploadType === 'new' ? 'selected' : '' }}>Dokumen Baru (Baseline v1)</option>
            <option value="replace" {{ $defaultUploadType === 'replace' ? 'selected' : '' }}>Ganti Versi Lama (Buat Draft)</option>
        </select>
        <div class="small-muted" style="margin-top:6px;">
            Pilih <strong>Dokumen Baru</strong> untuk membuat baseline v1 dan menerbitkannya langsung.
            Pilih <strong>Ganti Versi Lama</strong> untuk membuat versi pengganti sebagai draft yang masuk Draft Container.
        </div>
    </div>

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
            $relatedLinksDefault = '';
            if (isset($document) && is_array($document->related_links)) {
                $relatedLinksDefault = implode("\n", $document->related_links);
            } elseif (old('related_links') !== null) {
                $relatedLinksDefault = old('related_links');
            }
        @endphp

        <textarea name="related_links"
                  rows="3"
                  class="form-textarea"
                  placeholder="http://...">{{ old('related_links', $relatedLinksDefault) }}</textarea>

        <div class="small-muted">
            Masukkan URL/hyperlink dokumen terkait, satu baris = satu link.
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
               accept=".doc,.docx,.xls,.xlxs"
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
        <label class="small-muted">Upload PDF (optional)</label>
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
        <label class="small-muted">Tolong Copy paste kan isi dokumen disini (wajib)</label>
        @php
            $pastedDefault = old('pasted_text', isset($document) ? optional($document->relationLoaded('currentVersion') ? $document->currentVersion : ($document->currentVersion ?? null))->pasted_text ?? '' : '');
        @endphp
        <textarea name="pasted_text"
                  rows="8"
                  class="form-textarea">{{ $pastedDefault }}</textarea>
        @error('pasted_text')
            <div class="small-muted text-danger">{{ $message }}</div>
        @enderror
    </div>

    {{-- CHANGE NOTE --}}
    <div class="form-row">
        <label class="small-muted">Catatan apa yang dirubah diversi ini (optional)</label>
        <textarea name="change_note"
                  rows="3"
                  class="form-textarea">{{ old('change_note') }}</textarea>
    </div>

    {{-- hidden metadata fields (optional) --}}
    <input type="hidden" name="doc_number" value="{{ old('doc_number', $document->doc_number ?? '') }}">
    <input type="hidden" name="approved_by" value="{{ old('approved_by', $document->approved_by ?? '') }}">

    {{-- BUTTON SINGLE (dinamis) --}}
    <div style="margin-top:16px; display:flex; gap:10px; align-items:center;">
        <button class="btn btn-primary"
                id="mainSubmitBtn"
                type="submit">
            <!-- label set by JS -->
            Save
        </button>

        <button type="button" class="btn btn-muted" id="cancelFormBtn">Cancel</button>
    </div>
</form>

{{-- Inline JS fallback: mengatur button & hidden fields --}}
<script>
(function () {
    try {
        const form = document.currentScript && document.currentScript.previousElementSibling && document.currentScript.previousElementSibling.tagName === 'FORM'
            ? document.currentScript.previousElementSibling
            : document.querySelector('form.page-card') || document.querySelector('form');

        if (!form) return;

        const uploadSelect = form.querySelector('select[name="upload_type"]') || document.getElementById('upload_type_select');
        const modeHidden = form.querySelector('input[name="mode"]');
        const submitHidden = form.querySelector('input[name="submit_for"]');
        const mainBtn = form.querySelector('#mainSubmitBtn');
        const cancelBtn = form.querySelector('#cancelFormBtn');

        // if uploadSelect is not found, try the top-level id
        if (!uploadSelect) {
            // graceful: create one at top of form
            // (but _form already renders upload_type_select)
        }

        function setStateByType(type) {
            if (!type || type === '') {
                if (mainBtn) mainBtn.textContent = 'Save';
                if (modeHidden) modeHidden.value = 'new';
                if (submitHidden) submitHidden.value = 'publish';
                return;
            }
            if (type === 'new') {
                if (mainBtn) mainBtn.textContent = 'Save Baseline (v1) & Publish';
                if (modeHidden) modeHidden.value = 'new';
                if (submitHidden) submitHidden.value = 'publish';
            } else if (type === 'replace') {
                if (mainBtn) mainBtn.textContent = 'Save as Draft (New Version)';
                if (modeHidden) modeHidden.value = 'replace';
                if (submitHidden) submitHidden.value = 'draft';
            } else {
                // default fallback
                if (mainBtn) mainBtn.textContent = 'Save';
                if (modeHidden) modeHidden.value = type;
                if (submitHidden) submitHidden.value = (type === 'replace' ? 'draft' : 'publish');
            }
        }

        // init from select value or hidden
        const initialType = (uploadSelect && uploadSelect.value) || modeHidden && modeHidden.value || '';
        setStateByType(initialType);

        if (uploadSelect) {
            uploadSelect.addEventListener('change', function (e) {
                setStateByType(e.target.value);
            }, { passive: true });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                try {
                    if (window.history && window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = document.referrer || '{{ url()->current() }}';
                    }
                } catch (e) {
                    // fallback
                    window.location.reload();
                }
            }, { passive: true });
        }

        // final guard: ensure upload_type chosen on submit
        form.addEventListener('submit', function (ev) {
            const type = (uploadSelect && uploadSelect.value) || (modeHidden && modeHidden.value) || '';
            if (!type) {
                ev.preventDefault();
                alert('Silakan pilih jenis pengajuan: Dokumen Baru atau Ganti Versi Lama.');
                if (uploadSelect) uploadSelect.focus();
                return false;
            }
            // allow submit (hidden inputs already set)
            return true;
        });
    } catch (err) {
        console.error('form script error', err);
    }
})();
</script>
