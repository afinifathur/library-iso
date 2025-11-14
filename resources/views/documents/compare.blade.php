@extends('layouts.iso')

@section('title', 'Compare Versions: ' . ($doc->doc_code ?? 'Document'))

@section('content')
<div class="container mt-4 max-w-5xl mx-auto px-4">
    <h1 class="mb-3 text-2xl font-semibold">Perbandingan Dokumen: {{ $doc->doc_code ?? $doc->title }}</h1>

    <p class="text-sm text-gray-600 mb-4">
        {{-- informasi ringkas --}}
        Dokumen: <strong>{{ $doc->title ?? '-' }}</strong>
        @if(isset($doc->department) && $doc->department->name)
            — Departemen: <em>{{ $doc->department->name }}</em>
        @endif
    </p>

    <div class="bg-white border rounded-lg shadow-sm p-4 mb-4">
        <form id="compareForm" action="{{ route('documents.compare', $doc->id) }}" method="get" class="grid gap-3 md:grid-cols-3 items-end">
            <div>
                <label for="v1" class="block text-sm font-medium text-gray-700">Base (older)</label>
                <select id="v1" name="v1" class="mt-1 block w-full border rounded px-3 py-2" aria-label="Base version">
                    <option value="">-- pilih base (lebih lama) --</option>
                    @foreach($versions as $ver)
                        <option value="{{ $ver->id }}" @if(optional($v1)->id == $ver->id) selected @endif
                            data-status="{{ strtolower($ver->status ?? '') }}"
                            data-created-at="{{ optional($ver->created_at)->toDateString() }}">
                            {{ $ver->version_label }} — {{ $ver->status ?? 'N/A' }} — {{ optional($ver->created_at)->format('Y-m-d') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="v2" class="block text-sm font-medium text-gray-700">Target (newer)</label>
                <select id="v2" name="v2" class="mt-1 block w-full border rounded px-3 py-2" aria-label="Target version">
                    <option value="">-- pilih target (lebih baru) --</option>
                    @foreach($versions as $ver)
                        <option value="{{ $ver->id }}" @if(optional($v2)->id == $ver->id) selected @endif
                            data-status="{{ strtolower($ver->status ?? '') }}"
                            data-created-at="{{ optional($ver->created_at)->toDateString() }}">
                            {{ $ver->version_label }} — {{ $ver->status ?? 'N/A' }} — {{ optional($ver->created_at)->format('Y-m-d') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary w-full">Compare</button>
                <a href="{{ route('documents.show', $doc->id) }}" class="btn btn-secondary w-full">← Kembali</a>
            </div>
        </form>

        <p class="mt-3 text-xs text-gray-500">
            Pilih versi base (lebih lama) dan versi target (lebih baru). Jika tidak memilih, sistem akan mencoba memilih default:
            latest approved sebagai base, latest submitted/draft sebagai target.
        </p>
    </div>

    {{-- Hasil perbandingan --}}
    <div id="diffResult">
        @if(isset($v1) && isset($v2))
            <div class="mb-3 text-sm text-gray-600">
                Membandingkan:
                <strong>{{ $v1->version_label }} ({{ optional($v1->created_at)->format('d M Y') }})</strong>
                vs
                <strong>{{ $v2->version_label }} ({{ optional($v2->created_at)->format('d M Y') }})</strong>
                — Diupload oleh: <em>{{ optional($v2->creator)->name ?? 'Tidak diketahui' }}</em>
            </div>

            <div class="bg-white border rounded-lg shadow-sm p-4 overflow-auto">
                <style>
                    /* style minimal untuk diff inline */
                    ins { background-color: #d1fae5; text-decoration: none; }
                    del { background-color: #fee2e2; text-decoration: none; }
                    pre { white-space: pre-wrap; word-wrap: break-word; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, "Roboto Mono", "Courier New", monospace; font-size: 13px; }
                </style>

                {{-- 
                    PERINGATAN KEAMANAN:
                    - $diffHtml diasumsikan sudah di-sanitize di controller (trusted HTML).
                    - Jika ada teks mentah, gunakan e() atau nl2br(e(...)) untuk mencegah XSS.
                --}}
                <pre class="diff-output">@if(!empty($diffHtml)) {!! $diffHtml !!} @elseif(!empty($diff)) {!! $diff !!} @else - tidak ada perbandingan - @endif</pre>
            </div>
        @else
            <div class="bg-white border rounded-lg shadow-sm p-4 text-sm text-gray-600">
                Pilih dua versi untuk menampilkan perbandingan.
            </div>
        @endif
    </div>
</div>

@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectV1 = document.getElementById('v1');
    const selectV2 = document.getElementById('v2');

    // helper: cari option berdasarkan predicate (NodeList -> array)
    const optionsArray = (select) => Array.from(select.options).filter(o => o.value);

    // fungsi auto-select:
    // - base (v1): latest option dengan status 'approved' (jika ada)
    // - target (v2): latest option with status 'submitted' or 'pending' or 'draft'
    function autoSelectDefaults() {
        // jika user sudah memilih, jangan override
        const v1Selected = selectV1.value;
        const v2Selected = selectV2.value;

        if (!v1Selected) {
            // cari semua options kecuali placeholder
            let approved = optionsArray(selectV1).filter(o => o.dataset.status === 'approved');
            if (approved.length === 0) {
                // fallback: pilih paling awal (termost recent berdasarkan urutan)
                approved = optionsArray(selectV1);
            }
            // pilih yang paling baru (opsi terakhir dianggap terbaru jika daftar di-controller diurutkan asc)
            if (approved.length) {
                const chosen = approved[approved.length - 1];
                selectV1.value = chosen.value;
            }
        }

        if (!v2Selected) {
            const targetStatuses = ['submitted', 'pending', 'in_progress', 'draft'];
            let targets = optionsArray(selectV2).filter(o => targetStatuses.includes(o.dataset.status));
            if (targets.length === 0) {
                // fallback ke yang paling baru
                targets = optionsArray(selectV2);
            }
            if (targets.length) {
                // pick latest (last in list)
                const chosen = targets[targets.length - 1];
                // prevent same selection as v1: if same, try previous
                if (chosen.value === selectV1.value && targets.length > 1) {
                    selectV2.value = targets[targets.length - 2].value;
                } else {
                    selectV2.value = chosen.value;
                }
            }
        }
    }

    // run auto-select only when both selects have not been explicitly chosen in URL (server may have preselected)
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('v1') && !urlParams.has('v2')) {
        autoSelectDefaults();
    }

    // optionally: prevent form submission if v1 == v2
    document.getElementById('compareForm').addEventListener('submit', function (e) {
        if (selectV1.value && selectV2.value && selectV1.value === selectV2.value) {
            e.preventDefault();
            alert('Pilih dua versi yang berbeda untuk membandingkan.');
        }
    });
});
</script>
@endsection
