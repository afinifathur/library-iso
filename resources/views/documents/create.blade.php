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
      - Partial documents._form tetap tidak diubah dari versi terakhir.
      - Di sini kita tambahkan dropdown jenis pengajuan (kontrol utama).
    --}}

    
    {{-- include existing form partial (partial merender <form> dan field lain) --}}
    @include('documents._form', compact('action','method','departments','categories','submitLabel'))

</div>
@endsection


@section('scripts')
@parent
<script>
document.addEventListener('DOMContentLoaded', function () {
    const topSelect = document.getElementById('upload_type_top'); // dropdown di create view
    const form = document.querySelector('form[action="{{ $action }}"]') || document.querySelector('form');
    if (!form) {
        console.warn('Form not found — pastikan documents._form merender <form>.');
        return;
    }

    // If partial included its own upload_type select (id="upload_type"), keep references
    const partialSelect = form.querySelector('#upload_type'); // may exist inside partial
    const mainSelect = partialSelect || topSelect; // primary control inside form (if exists)
    const mainBtn = form.querySelector('#mainSubmitBtn'); // tombol utama di partial
    const submitForHidden = form.querySelector('input[name="submit_for"]') || (function(){
        const el = document.createElement('input'); el.type='hidden'; el.name='submit_for'; el.value = 'publish'; form.appendChild(el); return el;
    })();
    const modeHidden = form.querySelector('input[name="mode"]') || (function(){
        const el = document.createElement('input'); el.type='hidden'; el.name='mode'; el.value = 'new'; form.appendChild(el); return el;
    })();

    // helper to set both partial select and top select (keamanan sync)
    function setUploadType(value) {
        if (partialSelect) {
            partialSelect.value = value;
            partialSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (topSelect) {
            topSelect.value = value;
            topSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    // initialize based on old() values if present: try to read from partialSelect first
    let initial = '';
    // Try server-side old value rendered in partial's select (if any)
    if (partialSelect && partialSelect.value) {
        initial = partialSelect.value;
    }
    // else try top select (if user has preselected)
    if (!initial && topSelect && topSelect.value) {
        initial = topSelect.value;
    }
    // else use old submitted hidden input if exists
    const hiddenSubmitForValue = form.querySelector('input[name="submit_for"]')?.value;
    if (!initial && hiddenSubmitForValue) {
        // derive upload_type from submit_for: if 'draft' or 'save' => replace; if 'publish'/'submit' => new
        if (['draft','save'].includes(hiddenSubmitForValue)) initial = 'replace';
        else initial = 'new';
    }

    if (initial) {
        setUploadType(initial);
    }

    // Update UI (button label + hidden inputs) according to chosen upload_type
    function updateStateByType(v) {
        if (!v || v === '') {
            submitForHidden.value = 'publish';
            modeHidden.value = 'new';
            if (mainBtn) {
                mainBtn.disabled = true;
                mainBtn.textContent = 'Pilih jenis pengajuan dahulu';
            }
            return;
        }

        if (v === 'new') {
            submitForHidden.value = 'publish';
            modeHidden.value = 'new';
            if (mainBtn) {
                mainBtn.disabled = false;
                mainBtn.textContent = 'Save Baseline (v1) & Publish';
            }
        } else if (v === 'replace') {
            submitForHidden.value = 'draft';
            modeHidden.value = 'replace';
            if (mainBtn) {
                mainBtn.disabled = false;
                mainBtn.textContent = 'Save as Draft (New Version)';
            }
        } else {
            // fallback
            submitForHidden.value = 'publish';
            modeHidden.value = v;
            if (mainBtn) {
                mainBtn.disabled = false;
                mainBtn.textContent = 'Submit';
            }
        }
    }

    // wire change listeners: topSelect -> sync to partialSelect, partialSelect -> update UI
    if (topSelect) {
        topSelect.addEventListener('change', function (e) {
            const v = e.target.value;
            // propagate to partial select if exists
            if (partialSelect) {
                partialSelect.value = v;
                partialSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            updateStateByType(v);
        });
    }

    if (partialSelect) {
        partialSelect.addEventListener('change', function (e) {
            const v = e.target.value;
            // propagate to top select for visual consistency
            if (topSelect) {
                topSelect.value = v;
            }
            updateStateByType(v);
        });
    }

    // If neither select exists (edge case), ensure button enabled
    if (!topSelect && !partialSelect && mainBtn) {
        mainBtn.disabled = false;
    }

    // Final guard before submit: require upload_type chosen
    form.addEventListener('submit', function (ev) {
        const v = (partialSelect && partialSelect.value) || (topSelect && topSelect.value) || '';
        if (!v) {
            ev.preventDefault();
            alert('Silakan pilih jenis pengajuan: Dokumen Baru atau Ganti Versi Lama.');
            if (topSelect) topSelect.focus();
            return false;
        }
        // ensure hidden synced
        updateStateByType(v);
    });

    // initial UI update
    updateStateByType((partialSelect && partialSelect.value) || (topSelect && topSelect.value) || '');
});
</script>
@endsection
