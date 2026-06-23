{{-- resources/views/documents/edit.blade.php --}}
@extends('layouts.iso')

@section('title', 'Edit Document — '.($document->doc_code ?: $document->title))

@section('content')
<div class="container-narrow" style="max-width:920px; margin: 24px auto; padding: 0 16px;">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 24px; font-weight: 800; color: #111827; margin-bottom: 6px;">Edit Document — {{ $document->doc_code ?? $document->title }}</h1>
        <p style="font-size: 14px; color: #6b7280; margin: 0;">
            Edit metadata dokumen atau upload versi baru. Pilih <strong>Ganti Versi Lama</strong> bila ingin menambahkan draft/versi pengganti.
        </p>
    </div>

    @php
        // Action menuju updateCombined (sesuai DocumentController@updateCombined)
        $action       = route('documents.updateCombined', $document->id);
        $method       = 'PUT';
        $submitLabel  = 'Save Changes';
        // supaya link "Open Drafts" tetap muncul (opsional)
        $showDraftLink = true;
    @endphp

    <style>
    .modern-label {
        font-size: 13px;
        font-weight: 600;
        color: #4b5563;
        margin-bottom: 6px;
        display: block;
    }
    .modern-select-wrapper {
        position: relative;
        display: block;
        width: 100%;
    }
    .modern-select {
        width: 100%;
        height: 44px;
        border-radius: 10px;
        border: 1px solid #d1d5db;
        background-color: #ffffff;
        padding: 0 40px 0 16px;
        font-size: 14px;
        color: #1f2937;
        appearance: none;
        -webkit-appearance: none;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .modern-select:hover {
        border-color: #9ca3af;
    }
    .modern-select:focus {
        outline: none;
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }
    .modern-select-arrow {
        position: absolute;
        top: 50%;
        right: 16px;
        transform: translateY(-50%);
        pointer-events: none;
        width: 18px;
        height: 18px;
        color: #6b7280;
    }
    .helper-text {
        font-size: 12px;
        color: #6b7280;
        margin-top: 6px;
        line-height: 1.4;
    }
    </style>

    {{-- Top-level Upload Type control (agar UX konsisten dengan create) --}}
    <div style="margin-bottom: 20px;">
      <label for="upload_type_top" class="modern-label">Jenis Pengajuan <span style="color:#ef4444;">*</span></label>
      <div class="modern-select-wrapper">
          <select id="upload_type_top" name="upload_type_top" class="modern-select" required>
            @php
                $pref = old('upload_type') ?: '';
            @endphp
            <option value="" {{ $pref==='' ? 'selected' : '' }}>-- silahkan pilih jenis pengajuan (wajib) --</option>
            <option value="new" {{ $pref==='new' ? 'selected' : '' }}>Dokumen Baru</option>
            <option value="replace" {{ $pref==='replace' ? 'selected' : '' }}>Ganti Versi Lama</option>
          </select>
          <span class="modern-select-arrow">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
              </svg>
          </span>
      </div>
      <p id="uploadTypeHelp" class="helper-text">
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
            if (publishBtn) { publishBtn.disabled = false; publishBtn.textContent = 'Simpan Dokumen Baru'; }
            if (draftBtn) { draftBtn.disabled = false; }
        } else if (v === 'replace') {
            submitForHidden.value = 'draft';
            modeHidden.value = 'replace';
            if (publishBtn) { publishBtn.disabled = false; publishBtn.textContent = 'Simpan Dokumen Baru'; }
            if (draftBtn) { draftBtn.disabled = false; draftBtn.textContent = 'Kirim Revisi ke Draft Container'; }
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
