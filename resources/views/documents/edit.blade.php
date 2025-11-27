{{-- resources/views/documents/edit.blade.php --}}
@extends('layouts.iso')

@section('title', 'Edit Document — '.($document->doc_code ?: $document->title))

@section('content')
<div class="container-narrow" style="max-width:920px;margin:18px auto;">
    <h2 style="margin-bottom:8px;">Edit Document — {{ $document->doc_code ?? $document->title }}</h2>

    @php
        // Action menuju updateCombined (sesuai DocumentController@updateCombined)
        $action       = route('documents.updateCombined', $document->id);
        $method       = 'PUT';
        $submitLabel  = 'Save Changes';
        // supaya link "Open Drafts" tetap muncul (opsional)
        $showDraftLink = true;
    @endphp

    {{-- Short helper text --}}
    <div class="form-row" style="margin-bottom:12px;">
        <div class="small-muted" style="margin-top:6px;">
            Edit metadata dokumen atau upload versi baru. Pilih <strong>Ganti Versi Lama</strong> bila ingin menambahkan draft/versi pengganti.
        </div>
    </div>

    {{-- Top-level Upload Type control (agar UX konsisten dengan create) --}}
    <div class="mb-4">
      <label for="upload_type_top" class="block text-sm font-medium text-gray-700">Jenis Pengajuan <span class="text-red-500">*</span></label>
      <select id="upload_type_top" name="upload_type_top" class="mt-1 block w-full rounded border p-2" required>
        @php
            $pref = old('upload_type') ?: '';
        @endphp
        <option value="" {{ $pref==='' ? 'selected' : '' }}>-- silahkan pilih jenis pengajuan (wajib) --</option>
        <option value="new" {{ $pref==='new' ? 'selected' : '' }}>Dokumen Baru</option>
        <option value="replace" {{ $pref==='replace' ? 'selected' : '' }}>Ganti Versi Lama</option>
      </select>
      <p id="uploadTypeHelp" class="mt-2 text-sm text-gray-500">
        Pilih <strong>Dokumen Baru</strong> untuk membuat baseline v1. Pilih <strong>Ganti Versi Lama</strong> untuk membuat versi baru sebagai draft.
      </p>
    </div>

    {{-- Include the shared form partial used by create --}}
    @include('documents._form', [
        'action' => $action,
        'method' => $method,
        'document' => $document,
        'departments' => $departments ?? \App\Models\Department::orderBy('code')->get(),
        'categories' => $categories ?? (class_exists(\App\Models\Category::class) ? \App\Models\Category::orderBy('name')->get() : []),
        // optional flags
        'submitLabel' => $submitLabel,
        'showDraftLink' => $showDraftLink,
    ])
</div>
@endsection

@section('scripts')
@parent
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Mirror logic from create view so both pages behave same.
    const topSelect = document.getElementById('upload_type_top');
    const form = document.querySelector('form[action="{{ $action }}"]') || document.querySelector('form');
    if (!form) {
        console.warn('Form not found — pastikan documents._form merender <form>.');
        return;
    }

    // partial may include an input/select#upload_type; prefer it
    const partialSelect = form.querySelector('[name="mode"], #upload_type') || null;

    // find the publish/draft buttons in partial by id or by value fallback
    const publishBtn = form.querySelector('#publish-btn') || form.querySelector('button[name="submit_for"][value="publish"]');
    const draftBtn   = form.querySelector('#draft-btn')   || form.querySelector('button[name="submit_for"][value="draft"]');

    // ensure hidden fields exist
    let submitForHidden = form.querySelector('input[name="submit_for"]');
    if (!submitForHidden) {
        submitForHidden = document.createElement('input');
        submitForHidden.type = 'hidden';
        submitForHidden.name = 'submit_for';
        submitForHidden.value = 'publish';
        form.appendChild(submitForHidden);
    }

    let modeHidden = form.querySelector('input[name="mode"]');
    if (!modeHidden) {
        modeHidden = document.createElement('input');
        modeHidden.type = 'hidden';
        modeHidden.name = 'mode';
        modeHidden.value = 'new';
        form.appendChild(modeHidden);
    }

    function setUploadType(value) {
        // set both partial select and top select
        if (partialSelect) {
            try { partialSelect.value = value; partialSelect.dispatchEvent(new Event('change',{bubbles:true})); } catch(e){}
        }
        if (topSelect) {
            try { topSelect.value = value; topSelect.dispatchEvent(new Event('change',{bubbles:true})); } catch(e){}
        }
    }

    function updateStateByType(v) {
        if (!v || v === '') {
            submitForHidden.value = 'publish';
            modeHidden.value = 'new';
            if (publishBtn) { publishBtn.disabled = true; publishBtn.textContent = 'Pilih jenis pengajuan dahulu'; }
            if (draftBtn) { draftBtn.disabled = true; }
            return;
        }

        if (v === 'new') {
            submitForHidden.value = 'publish';
            modeHidden.value = 'new';
            if (publishBtn) { publishBtn.disabled = false; publishBtn.textContent = 'Save Baseline (v1) & Publish'; }
            if (draftBtn) { draftBtn.disabled = false; }
        } else if (v === 'replace') {
            submitForHidden.value = 'draft';
            modeHidden.value = 'replace';
            if (publishBtn) { publishBtn.disabled = false; publishBtn.textContent = 'Save Baseline (v1) & Publish'; }
            if (draftBtn) { draftBtn.disabled = false; draftBtn.textContent = 'Save as Draft (New Version)'; }
        } else {
            // fallback
            submitForHidden.value = 'publish';
            modeHidden.value = v;
            if (publishBtn) publishBtn.disabled = false;
            if (draftBtn) draftBtn.disabled = false;
        }
    }

    // wire clicks: ensure hidden gets correct value before submit
    if (publishBtn) {
        publishBtn.addEventListener('click', function (ev) {
            submitForHidden.value = this.dataset?.submit || 'publish';
            if (topSelect) topSelect.value = 'new';
            if (partialSelect && typeof partialSelect.value !== 'undefined') partialSelect.value = 'new';
            form.submit();
        });
    }
    if (draftBtn) {
        draftBtn.addEventListener('click', function (ev) {
            submitForHidden.value = this.dataset?.submit || 'draft';
            if (topSelect) topSelect.value = 'replace';
            if (partialSelect && typeof partialSelect.value !== 'undefined') partialSelect.value = 'replace';
            form.submit();
        });
    }

    // keep selects in sync
    if (topSelect) {
        topSelect.addEventListener('change', function (e) {
            const v = e.target.value;
            if (partialSelect) { try { partialSelect.value = v; } catch(e){} }
            updateStateByType(v);
        });
    }
    if (partialSelect) {
        partialSelect.addEventListener('change', function (e) {
            const v = e.target.value;
            if (topSelect) topSelect.value = v;
            updateStateByType(v);
        });
    }

    // initialize UI
    let initial = '';
    if (partialSelect && partialSelect.value) initial = partialSelect.value;
    if (!initial && topSelect && topSelect.value) initial = topSelect.value;
    const hiddenSubmitForValue = submitForHidden?.value;
    if (!initial && hiddenSubmitForValue) {
        initial = (['draft','save'].includes(hiddenSubmitForValue) ? 'replace' : 'new');
    }
    updateStateByType(initial);
});
</script>
@endsection
