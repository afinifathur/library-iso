{{-- resources/views/documents/create.blade.php --}}
@extends('layouts.iso')

@section('content')
<div class="container-narrow" style="max-width:920px;margin:18px auto;">
    <h2 style="margin-bottom:8px;">New Document (Upload Baseline)</h2>

    {{-- Keterangan singkat --}}
    <div class="form-row" style="margin-bottom:12px;">
        <div class="small-muted" style="margin-top:6px;">
            Pilih <b>Dokumen Baru</b> untuk membuat baseline v1 — pilih <b>Ganti Versi Lama</b> jika ingin mengganti versi sebuah dokumen yang sudah ada.
        </div>
    </div>

    @php
        $action = route('documents.store');
        $method = 'POST';
        $submitLabel = 'Simpan';
    @endphp

    {{-- 
      IMPORTANT:
      - Partial documents._form tetap tidak diubah.
      - Kita akan menambahkan dropdown jenis pengajuan di luar partial (sebelum form fields).
      - Partial diasumsikan merender <form ...> dan field lain.
    --}}

    {{-- Block dropdown wajib: letakkan di create view (jangan ubah partial) --}}
    <div class="mb-4">
      <label for="upload_type" class="block text-sm font-medium text-gray-700">Jenis Pengajuan <span class="text-red-500">*</span></label>
      <select id="upload_type" name="upload_type" class="mt-1 block w-full rounded border p-2" required>
        <option value="" selected>-- silahkan pilih jenis pengajuan (wajib) --</option>
        <option value="new">Dokumen Baru</option>
        <option value="replace">Ganti Versi Lama</option>
      </select>
      <p id="uploadTypeHelp" class="mt-2 text-sm text-gray-500">
        Pilih <strong>Dokumen Baru</strong> untuk membuat baseline v1. Pilih <strong>Ganti Versi Lama</strong> untuk membuat versi baru sebagai draft.
      </p>
    </div>

    {{-- include existing form partial (partial tidak diubah) --}}
    @include('documents._form', compact('action','method','departments','categories','submitLabel'))

    {{-- NOTE: partial harus merender sebuah <form>. Jika partial tidak merender form,
              maka kamu harus memasukkan <form id="uploadForm" enctype="multipart/form-data" action="..." method="POST"> ...</form>
              sendiri di sini. --}}
</div>
@endsection

@section('scripts')
@parent
<script>
document.addEventListener('DOMContentLoaded', function () {
    const uploadType = document.getElementById('upload_type');
    const form = document.querySelector('form[action="{{ $action }}"]') || document.querySelector('form');

    if (!form) {
        console.warn('Form not found — pastikan documents._form merender <form>.');
        return;
    }

    // Pastikan form punya id & enctype
    form.id = form.id || 'uploadForm';
    if (!form.enctype || form.enctype.toLowerCase() !== 'multipart/form-data') {
        form.enctype = 'multipart/form-data';
    }

    // Sembunyikan tombol submit existing (biasanya ada dua)
    const existingSubmitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
    existingSubmitButtons.forEach(btn => {
        btn.style.display = 'none';
        btn.disabled = true;
    });

    // Buat wrapper dan single submit button
    let submitWrapper = form.querySelector('#submit-wrapper');
    if (!submitWrapper) {
        submitWrapper = document.createElement('div');
        submitWrapper.id = 'submit-wrapper';
        submitWrapper.style.marginTop = '12px';
        form.appendChild(submitWrapper);
    }

    let submitBtn = document.getElementById('submitBtn');
    if (!submitBtn) {
        submitBtn = document.createElement('button');
        submitBtn.type = 'submit';
        submitBtn.id = 'submitBtn';
        submitBtn.className = 'btn btn-primary';
        submitBtn.textContent = 'Simpan';
        submitBtn.disabled = true;
        submitWrapper.appendChild(submitBtn);
    }

    // helper hidden inputs
    function ensureHidden(name, val) {
        let inp = form.querySelector('input[name="'+name+'"]');
        if (!inp) {
            inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = name;
            form.appendChild(inp);
        }
        if (typeof val !== 'undefined') inp.value = val;
        return inp;
    }

    // inisialisasi hidden if not exists
    ensureHidden('submit_for', 'submit');
    ensureHidden('mode', 'new');

    function updateFromType() {
        const v = uploadType ? uploadType.value : '';
        const note = document.getElementById('submitNote');

        if (!v) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Simpan';
            if (note) note.textContent = 'Pilih jenis pengajuan terlebih dahulu.';
            return;
        }
        submitBtn.disabled = false;

        if (v === 'new') {
            // default: publish
            ensureHidden('submit_for', 'submit').value = 'submit';
            ensureHidden('mode', 'new').value = 'new';
            submitBtn.textContent = 'Simpan dan Publish';
            if (note) note.textContent = 'Akan disimpan sebagai baseline (v1) dan langsung publish.';
        } else if (v === 'replace') {
            // saat replace -> set save (draft) supaya tidak langsung publish
            ensureHidden('submit_for', 'save').value = 'save';
            ensureHidden('mode', 'replace').value = 'replace';
            submitBtn.textContent = 'Simpan sebagai Draft';
            if (note) note.textContent = 'Akan disimpan sebagai draft dan masuk mekanisme approval.';
        } else {
            ensureHidden('submit_for', 'submit').value = 'submit';
            ensureHidden('mode', v).value = v;
            submitBtn.textContent = 'Simpan';
            if (note) note.textContent = '';
        }
    }

    // add small note span
    if (!document.getElementById('submitNote')) {
        const span = document.createElement('span');
        span.id = 'submitNote';
        span.style.marginLeft = '12px';
        span.style.color = '#6b7280';
        submitWrapper.appendChild(span);
    }

    if (uploadType) {
        uploadType.addEventListener('change', updateFromType);
        updateFromType();
    } else {
        console.warn('#upload_type tidak ditemukan di DOM.');
        // enable submit only if hidden inputs already ter-set (defensive)
        submitBtn.disabled = false;
    }

    // final check before submit
    form.addEventListener('submit', function (ev) {
        if (uploadType && uploadType.value === '') {
            ev.preventDefault();
            alert('Silakan pilih jenis pengajuan: Dokumen Baru atau Ganti Versi Lama.');
            uploadType.focus();
            return false;
        }
        // ensure hidden values valid (redundan)
        updateFromType();
    });
});
</script>
@endsection

