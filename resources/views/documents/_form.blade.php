{{-- resources/views/documents/_form.blade.php --}}
{{-- Partial form untuk create / edit document --}}
<!-- ISO-FORM-PARTIAL-LOADED -->


<form method="POST"
      action="{{ $action }}"
      enctype="multipart/form-data"
      class="page-card">

    @csrf
    @if(!empty($method) && strtoupper($method) !== 'POST')
        @method($method)
    @endif

    {{-- Hidden control fields (required by controller) --}}
    <input type="hidden" name="submit_for" value="{{ old('submit_for', 'publish') }}">
    <input type="hidden" name="mode" id="upload_type" value="{{ old('upload_type', '') }}">

    {{-- CATEGORY --}}
    <div class="form-row">
        <label class="small-muted">Category</label>
        <select name="category_id" class="form-input" required>
            <option value="">-- pilih kategori --</option>
            @php
                $selectedCategory = old('category_id', $document->category_id ?? null);
            @endphp
            @if(!empty($categories) && count($categories) > 0)
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ (string)$selectedCategory === (string)$cat->id ? 'selected' : '' }}>
                        {{ $cat->code ?? $cat->name ?? $cat->id }}
                    </option>
                @endforeach
            @else
                {{-- fallback: jika tidak ada model Category, tampilkan beberapa kode umum --}}
                <option value="IK" {{ $selectedCategory === 'IK' ? 'selected' : '' }}>IK - Instruksi Kerja</option>
                <option value="UT" {{ $selectedCategory === 'UT' ? 'selected' : '' }}>UT - Uraian Tugas</option>
                <option value="FR" {{ $selectedCategory === 'FR' ? 'selected' : '' }}>FR - Formulir</option>
                <option value="PJM" {{ $selectedCategory === 'PJM' ? 'selected' : '' }}>PJM - Prosedur Jaminan Mutu</option>
                <option value="MJM" {{ $selectedCategory === 'MJM' ? 'selected' : '' }}>MJM - Manual Jaminan Mutu</option>
                <option value="DP" {{ $selectedCategory === 'DP' ? 'selected' : '' }}>DP - Dokumen Pendukung</option>
                <option value="DE" {{ $selectedCategory === 'DE' ? 'selected' : '' }}>DE - Dokumen Eksternal</option>
            @endif
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
                <option value="{{ $d->id }}" {{ (string)$selectedDept === (string)$d->id ? 'selected' : '' }}>
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
        <label class="small-muted">Dokumen terkait (lampiran, form dll) (satu URL per baris)</label>

        @php
            $relatedLinksDefault = old('related_links', '');
            if ($relatedLinksDefault === '' && isset($document) && is_array($document->related_links)) {
                $relatedLinksDefault = implode("\n", $document->related_links);
            }
        @endphp

        <textarea name="related_links"
                  rows="4"
                  class="form-textarea"
                  placeholder="http://...">{{ $relatedLinksDefault }}</textarea>

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
            $pastedDefault = old('pasted_text', '');
            if ($pastedDefault === '' && isset($document)) {
                $pastedDefault = optional(
                    $document->relationLoaded('currentVersion')
                        ? $document->currentVersion
                        : ($document->currentVersion ?? null)
                )->pasted_text ?? '';
            }
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
        <label class="small-muted">Change note (optional)</label>
        <textarea name="change_note"
                  rows="3"
                  class="form-textarea">{{ old('change_note', '') }}</textarea>
    </div>

    {{-- hidden metadata fields (optional) --}}
    <input type="hidden" name="doc_number" value="{{ old('doc_number', $document->doc_number ?? '') }}">
    <input type="hidden" name="approved_by" value="{{ old('approved_by', $document->approved_by ?? '') }}">

    {{-- BUTTONS --}}
    <div style="margin-top:16px; display:flex; gap:10px; align-items:center;">

        {{-- PUBLISH BUTTON (default for New Document) --}}
        <button class="btn btn-primary"
                id="publish-btn"
                type="button"
                data-submit="publish">
            Save Baseline (v1) & Publish
        </button>

        {{-- DRAFT BUTTON (only appears when selecting "Replace Version") --}}
        <button class="btn btn-warning"
                id="draft-btn"
                type="button"
                data-submit="draft">
            Save as Draft (New Version)
        </button>

        {{-- optional cancel for modal flows --}}
        <button type="button" id="cancelBtnForm" class="btn btn-muted" style="margin-left:auto; display:none;">Cancel</button>
    </div>
</form>

{{-- small inline script to wire the two buttons to set hidden input and submit --}}
<script>
(function () {
    try {
        const form = document.currentScript ? document.currentScript.closest('form') : document.querySelector('form');
        // robust: locate the form containing this partial
        const publishBtn = document.getElementById('publish-btn');
        const draftBtn = document.getElementById('draft-btn');
        const submitFor = form ? form.querySelector('input[name="submit_for"]') : null;
        const modeInput = form ? form.querySelector('input[name="mode"], input[name="upload_type"], input#upload_type') : null;
        const cancelBtn = document.getElementById('cancelBtnForm');

        function doSubmit(val) {
            if (submitFor) submitFor.value = val;
            // set mode for server compatibility
            if (modeInput) modeInput.value = (val === 'draft' ? 'replace' : 'new');
            // final submit
            if (form) form.submit();
        }

        if (publishBtn) {
            publishBtn.addEventListener('click', function (e) {
                doSubmit(this.dataset.submit || 'publish');
            });
        }
        if (draftBtn) {
            draftBtn.addEventListener('click', function (e) {
                doSubmit(this.dataset.submit || 'draft');
            });
        }

        // if the form is shown as a modal in other pages, show cancel button to close modal
        // detect a modal container with id editModal
        const modal = document.getElementById('editModal');
        if (modal && cancelBtn) {
            cancelBtn.style.display = 'inline-block';
            cancelBtn.addEventListener('click', function () {
                modal.style.display = 'none';
            });
        } else if (cancelBtn) {
            // hide cancel if not modal
            cancelBtn.style.display = 'none';
        }
    } catch (e) {
        // ignore to avoid breaking UI
        console.warn('form wiring error', e);
    }
})();
</script>
