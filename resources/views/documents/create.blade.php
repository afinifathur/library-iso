{{-- resources/views/documents/create.blade.php --}}
@extends('layouts.iso')

@section('content')
<div class="container-narrow" style="max-width:920px;margin:18px auto;">
    <h2 style="margin-bottom:8px;">New Document (Upload Baseline)</h2>

    {{-- Mode selector: New / Replace --}}
    <div class="form-row" style="margin-bottom:12px;">
        <label for="mode" class="small-muted" style="display:block;margin-bottom:6px;">Jenis Pengajuan Dokumen</label>
        <select name="mode" id="mode" class="form-input input" required style="padding:8px;border-radius:6px;border:1px solid #e6eef8;min-width:200px;">
            <option value="new">Dokumen Baru</option>
            <option value="replace">Ganti Versi Lama</option>
        </select>
        <div class="small-muted" style="margin-top:6px;">Pilih <b>Dokumen Baru</b> untuk membuat baseline v1 — pilih <b>Ganti Versi Lama</b> jika ingin mengganti versi sebuah dokumen yang sudah ada.</div>
    </div>

    @php
        $action = route('documents.store');
        $method = 'POST';
        $submitLabel = 'Save baseline (v1) & Publish';
    @endphp

    {{-- include existing form partial (keamanan: partial tidak perlu diubah) --}}
    @include('documents._form', compact('action','method','departments','categories','submitLabel'))

</div>
@endsection

@section('scripts')
@parent
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modeSelect = document.getElementById('mode');

    // Find the form rendered by documents._form (assume only one form on page)
    const form = document.querySelector('form[action="{{ $action }}"]') || document.querySelector('form');

    // Helper to create a button
    function createButton(id, text, name, value, classes) {
        const btn = document.createElement('button');
        btn.type = 'submit';
        btn.id = id;
        btn.name = name;
        btn.value = value;
        btn.textContent = text;
        btn.className = classes || 'btn';
        btn.style.marginRight = '8px';
        return btn;
    }

    // Ensure we have publish & draft buttons inside the form.
    let publishBtn = form ? form.querySelector('#publish-btn') : null;
    let draftBtn   = form ? form.querySelector('#draft-btn')   : null;

    if (!form) {
        // no form found — nothing to wire
        console.warn('Create view: form not found for documents._form include.');
        return;
    }

    // Try to find existing submit buttons by name/value (common pattern)
    if (!publishBtn) {
        const btn = form.querySelector('button[type="submit"][value="submit"], button[name="submit_for"][value="submit"]');
        if (btn) publishBtn = btn;
    }
    if (!draftBtn) {
        const btn = form.querySelector('button[type="submit"][value="save"], button[name="submit_for"][value="save"]');
        if (btn) draftBtn = btn;
    }

    // If still missing, try to find any submit button and clone it for the other role
    // Otherwise create fallback buttons.
    if (!publishBtn && !draftBtn) {
        // create both fallback buttons and append to form
        publishBtn = createButton('publish-btn', 'Save baseline (v1) & Publish', 'submit_for', 'submit', 'btn btn-primary');
        draftBtn   = createButton('draft-btn',   'Save Draft',                 'submit_for', 'save',   'btn btn-muted');
        const wrapper = document.createElement('div');
        wrapper.style.marginTop = '12px';
        wrapper.appendChild(publishBtn);
        wrapper.appendChild(draftBtn);
        form.appendChild(wrapper);
    } else {
        // ensure publishBtn has id publish-btn
        if (publishBtn && !publishBtn.id) publishBtn.id = 'publish-btn';
        if (draftBtn && !draftBtn.id) draftBtn.id = 'draft-btn';

        // If one exists but the other doesn't, create the missing one
        if (!publishBtn) {
            publishBtn = createButton('publish-btn', 'Save baseline (v1) & Publish', 'submit_for', 'submit', 'btn btn-primary');
            form.appendChild(publishBtn);
        }
        if (!draftBtn) {
            draftBtn = createButton('draft-btn', 'Save Draft', 'submit_for', 'save', 'btn btn-muted');
            form.appendChild(draftBtn);
        }
    }

    // Set initial visibility: default = 'new' -> show publish, hide draft
    function updateButtons() {
        if (modeSelect.value === 'new') {
            publishBtn.style.display = 'inline-block';
            draftBtn.style.display = 'none';
        } else {
            publishBtn.style.display = 'none';
            draftBtn.style.display = 'inline-block';
        }
    }

    // Attach change handler
    modeSelect.addEventListener('change', updateButtons);

    // Initialize
    updateButtons();

    // Optional: when mode is 'replace', we set a hidden input indicating replace mode
    function ensureModeHidden() {
        let hidden = form.querySelector('input[name="mode"]');
        if (!hidden) {
            hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'mode';
            form.appendChild(hidden);
        }
        hidden.value = modeSelect.value;
    }

    // Keep hidden input updated
    modeSelect.addEventListener('change', ensureModeHidden);
    ensureModeHidden();

    // Ensure buttons submit the correct value (for browsers that ignore button.value)
    publishBtn.addEventListener('click', function () {
        // set a hidden input 'submit_for' to 'submit' before submitting
        let h = form.querySelector('input[name="submit_for"]');
        if (!h) {
            h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'submit_for';
            form.appendChild(h);
        }
        h.value = 'submit';

        // also set mode hidden
        let m = form.querySelector('input[name="mode"]');
        if (m) m.value = modeSelect.value;
    });

    draftBtn.addEventListener('click', function () {
        let h = form.querySelector('input[name="submit_for"]');
        if (!h) {
            h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'submit_for';
            form.appendChild(h);
        }
        h.value = 'save';

        let m = form.querySelector('input[name="mode"]');
        if (m) m.value = modeSelect.value;
    });

});
</script>
@endsection
