@extends('layouts.iso')

@section('title', 'Compare Versions: ' . ($doc->doc_code ?? 'Document'))

@section('content')
<div class="container mt-4 max-w-6xl mx-auto px-4 pb-12">
    <!-- Header Navigation Back Link -->
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('documents.show', $doc->id) }}" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800 transition-colors">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Kembali ke Detail Dokumen
        </a>
        <div class="text-xs text-gray-500">
            Document ID: #{{ $doc->id }}
        </div>
    </div>

    <!-- Document Info Card -->
    <div class="bg-gradient-to-r from-blue-800 to-indigo-900 rounded-xl shadow-md text-white p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <span class="bg-blue-500 text-xs font-semibold px-2.5 py-1 rounded-full uppercase tracking-wider">
                    {{ $doc->doc_code ?? 'Document Code' }}
                </span>
                <h1 class="text-2xl md:text-3xl font-bold mt-2 tracking-tight">
                    {{ $doc->title ?? 'Document Title' }}
                </h1>
                <p class="text-blue-100 text-sm mt-1 flex items-center gap-2">
                    @if(isset($doc->department) && $doc->department->name)
                        <span>Departemen: <strong>{{ $doc->department->name }}</strong></span>
                    @endif
                    @if(isset($doc->category))
                        <span class="opacity-50">|</span>
                        <span>Kategori: <strong>{{ is_object($doc->category) ? $doc->category->name : $doc->category }}</strong></span>
                    @endif
                </p>
            </div>
            
            <div class="flex items-center gap-3">
                <span class="bg-white/10 backdrop-blur-sm border border-white/20 text-xs rounded-lg px-4 py-2 text-right hidden sm:block">
                    <div class="opacity-75">Total Revisi</div>
                    <div class="text-base font-bold">{{ $versions->count() }} Versi</div>
                </span>
            </div>
        </div>
    </div>

    <!-- Dropdown Selector Panel -->
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wider">Pilih Versi Perbandingan</h2>
        <form id="compareForm" action="{{ route('documents.compare', $doc->id) }}" method="get" class="grid gap-4 md:grid-cols-3 items-end">
            <div>
                <label for="v1" class="block text-xs font-bold text-gray-500 uppercase tracking-wide">Base (Versi Lebih Lama)</label>
                <select id="v1" name="v1" class="mt-1.5 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" aria-label="Base version">
                    <option value="">-- Pilih Base (Lebih Lama) --</option>
                    @foreach($versions as $ver)
                        <option value="{{ $ver->id }}" @if(optional($ver1)->id == $ver->id) selected @endif
                            data-status="{{ strtolower($ver->status ?? '') }}"
                            data-created-at="{{ optional($ver->created_at)->toDateString() }}">
                            {{ $ver->version_label }} [{{ strtoupper($ver->status ?? 'N/A') }}] — {{ optional($ver->created_at)->format('d M Y') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label placeholder="v2" for="v2" class="block text-xs font-bold text-gray-500 uppercase tracking-wide">Target (Versi Lebih Baru)</label>
                <select id="v2" name="v2" class="mt-1.5 block w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" aria-label="Target version">
                    <option value="">-- Pilih Target (Lebih Baru) --</option>
                    @foreach($versions as $ver)
                        <option value="{{ $ver->id }}" @if(optional($ver2)->id == $ver->id) selected @endif
                            data-status="{{ strtolower($ver->status ?? '') }}"
                            data-created-at="{{ optional($ver->created_at)->toDateString() }}">
                            {{ $ver->version_label }} [{{ strtoupper($ver->status ?? 'N/A') }}] — {{ optional($ver->created_at)->format('d M Y') }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm px-4 py-2.5 rounded-lg transition-colors shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    Bandingkan
                </button>
            </div>
        </form>
    </div>

    <!-- Comparison Content Area -->
    <div id="diffResult">
        @if(isset($ver1) && isset($ver2))
            
            <!-- Side-by-Side Metadata Cards -->
            <div class="grid md:grid-cols-2 gap-6 mb-6">
                <!-- Version A (Base) Card -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 hover:border-gray-300 transition-colors">
                    <div class="flex items-center justify-between border-b pb-3 mb-4">
                        <div>
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">VERSION A (BASE)</span>
                            <h3 class="text-xl font-bold text-gray-800">{{ $ver1->version_label }}</h3>
                        </div>
                        <div>
                            @php
                                $status1 = strtolower($ver1->status ?? 'draft');
                                $badgeColor1 = 'bg-gray-100 text-gray-800 border border-gray-200';
                                if($status1 === 'approved') $badgeColor1 = 'bg-green-100 text-green-800 border border-green-200';
                                elseif(in_array($status1, ['submitted', 'pending'])) $badgeColor1 = 'bg-blue-100 text-blue-800 border border-blue-200';
                                elseif($status1 === 'rejected') $badgeColor1 = 'bg-red-100 text-red-800 border border-red-200';
                            @endphp
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full uppercase {{ $badgeColor1 }}">
                                {{ $ver1->status ?? 'N/A' }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-sm mb-4">
                        <div>
                            <span class="text-xs text-gray-400 block">Dibuat Oleh</span>
                            <span class="font-medium text-gray-700">{{ $ver1CreatorName }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block">Tanggal Dibuat</span>
                            <span class="font-medium text-gray-700">{{ optional($ver1->created_at)->format('d M Y, H:i') ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block">Disetujui Oleh</span>
                            <span class="font-medium text-gray-700">{{ $ver1ApproverName }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block">Tanggal Disetujui</span>
                            <span class="font-medium text-gray-700">{{ $ver1->approved_at ? optional($ver1->approved_at)->format('d M Y, H:i') : 'N/A' }}</span>
                        </div>
                    </div>

                    <div class="border-t pt-3">
                        <span class="text-xs text-gray-400 block mb-1">Catatan Revisi</span>
                        <div class="bg-gray-50 rounded-lg p-3 text-xs text-gray-600 border italic">
                            {{ $ver1->change_note ?: 'Tidak ada catatan revisi.' }}
                        </div>
                    </div>
                </div>

                <!-- Version B (Target) Card -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 hover:border-gray-300 transition-colors">
                    <div class="flex items-center justify-between border-b pb-3 mb-4">
                        <div>
                            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">VERSION B (TARGET)</span>
                            <h3 class="text-xl font-bold text-gray-800">{{ $ver2->version_label }}</h3>
                        </div>
                        <div>
                            @php
                                $status2 = strtolower($ver2->status ?? 'draft');
                                $badgeColor2 = 'bg-gray-100 text-gray-800 border border-gray-200';
                                if($status2 === 'approved') $badgeColor2 = 'bg-green-100 text-green-800 border border-green-200';
                                elseif(in_array($status2, ['submitted', 'pending'])) $badgeColor2 = 'bg-blue-100 text-blue-800 border border-blue-200';
                                elseif($status2 === 'rejected') $badgeColor2 = 'bg-red-100 text-red-800 border border-red-200';
                            @endphp
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full uppercase {{ $badgeColor2 }}">
                                {{ $ver2->status ?? 'N/A' }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-y-3 gap-x-4 text-sm mb-4">
                        <div>
                            <span class="text-xs text-gray-400 block">Dibuat Oleh</span>
                            <span class="font-medium text-gray-700">{{ $ver2CreatorName }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block">Tanggal Dibuat</span>
                            <span class="font-medium text-gray-700">{{ optional($ver2->created_at)->format('d M Y, H:i') ?? '-' }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block">Disetujui Oleh</span>
                            <span class="font-medium text-gray-700">{{ $ver2ApproverName }}</span>
                        </div>
                        <div>
                            <span class="text-xs text-gray-400 block">Tanggal Disetujui</span>
                            <span class="font-medium text-gray-700">{{ $ver2->approved_at ? optional($ver2->approved_at)->format('d M Y, H:i') : 'N/A' }}</span>
                        </div>
                    </div>

                    <div class="border-t pt-3">
                        <span class="text-xs text-gray-400 block mb-1">Catatan Revisi</span>
                        <div class="bg-gray-50 rounded-lg p-3 text-xs text-gray-600 border italic">
                            {{ $ver2->change_note ?: 'Tidak ada catatan revisi.' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Panel & Executive Summary Card -->
            <div class="grid md:grid-cols-3 gap-6 mb-6">
                <!-- Statistics Card -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 md:col-span-1 flex flex-col justify-between">
                    <h3 class="text-sm font-bold text-gray-700 mb-4 uppercase tracking-wider">Statistik Perubahan</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between border-b pb-2">
                            <span class="text-sm font-medium text-gray-500">Kata Ditambahkan</span>
                            <span class="inline-flex items-center bg-green-50 text-green-700 px-2.5 py-0.5 rounded-full text-sm font-bold border border-green-200">
                                +{{ $stats['added_words'] ?? 0 }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between border-b pb-2">
                            <span class="text-sm font-medium text-gray-500">Kata Dihapus</span>
                            <span class="inline-flex items-center bg-red-50 text-red-700 px-2.5 py-0.5 rounded-full text-sm font-bold border border-red-200">
                                -{{ $stats['removed_words'] ?? 0 }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between pb-2">
                            <span class="text-sm font-medium text-gray-500">Blok Dimodifikasi</span>
                            <span class="inline-flex items-center bg-blue-50 text-blue-700 px-2.5 py-0.5 rounded-full text-sm font-bold border border-blue-200">
                                ~{{ $stats['modified_blocks'] ?? 0 }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Executive Summary Bullet Points -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 md:col-span-2">
                    <h3 class="text-sm font-bold text-gray-700 mb-3 uppercase tracking-wider">Ringkasan Perubahan Utama (Otomatis)</h3>
                    <ul class="space-y-2">
                        @if(empty($summary))
                            <li class="text-sm text-gray-500 italic">Tidak ditemukan perubahan signifikan.</li>
                        @else
                            @foreach($summary as $bullet)
                                <li class="text-sm text-gray-600 flex items-start">
                                    <svg class="w-4 h-4 mr-2 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"/></svg>
                                    <span>{{ $bullet }}</span>
                                </li>
                            @endforeach
                        @endif
                    </ul>
                </div>
            </div>

            <!-- Diff Content View -->
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-5 overflow-hidden">
                <div class="flex items-center justify-between border-b pb-3 mb-4">
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wider">Perbandingan Teks Dokumen</h3>
                    <div class="text-xs text-gray-400">
                        {{ $ver1->version_label }} vs {{ $ver2->version_label }}
                    </div>
                </div>

                <style>
                    /* Custom visual enhancement for jfcherng/php-diff HTML */
                    .diff-output-wrapper pre {
                        white-space: pre-wrap;
                        word-wrap: break-word;
                        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, "Roboto Mono", "Courier New", monospace;
                        font-size: 13px;
                        line-height: 1.6;
                        color: #1f2937;
                    }
                    .diff-output-wrapper ins {
                        background-color: #d1fae5;
                        color: #065f46;
                        text-decoration: none;
                        padding: 0.125rem 0.25rem;
                        border-radius: 0.25rem;
                        border: 1px solid #a7f3d0;
                        font-weight: 500;
                    }
                    .diff-output-wrapper del {
                        background-color: #fee2e2;
                        color: #991b1b;
                        text-decoration: none;
                        padding: 0.125rem 0.25rem;
                        border-radius: 0.25rem;
                        border: 1px solid #fca5a5;
                        font-weight: 500;
                    }
                </style>

                <div class="diff-output-wrapper bg-gray-50 rounded-xl p-5 border border-gray-150 overflow-auto max-h-[600px]">
                    <pre class="diff-output">@if(!empty($diffHtml)) {!! $diffHtml !!} @elseif(!empty($diff)) {!! $diff !!} @else<span class="text-gray-400 italic">- tidak ada perbandingan -</span>@endif</pre>
                </div>
            </div>

        @else
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-8 text-center text-sm text-gray-500">
                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
                Pilih dua versi untuk menampilkan laporan perbandingan.
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
    // - target (v2): latest option dengan status 'submitted' or 'pending' or 'draft'
    function autoSelectDefaults() {
        // jika user sudah memilih, jangan override
        const v1Selected = selectV1.value;
        const v2Selected = selectV2.value;

        if (!v1Selected) {
            let approved = optionsArray(selectV1).filter(o => o.dataset.status === 'approved');
            if (approved.length === 0) {
                approved = optionsArray(selectV1);
            }
            if (approved.length) {
                const chosen = approved[approved.length - 1];
                selectV1.value = chosen.value;
            }
        }

        if (!v2Selected) {
            const targetStatuses = ['submitted', 'pending', 'in_progress', 'draft'];
            let targets = optionsArray(selectV2).filter(o => targetStatuses.includes(o.dataset.status));
            if (targets.length === 0) {
                targets = optionsArray(selectV2);
            }
            if (targets.length) {
                const chosen = targets[targets.length - 1];
                if (chosen.value === selectV1.value && targets.length > 1) {
                    selectV2.value = targets[targets.length - 2].value;
                } else {
                    selectV2.value = chosen.value;
                }
            }
        }
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('v1') && !urlParams.has('v2')) {
        autoSelectDefaults();
    }

    document.getElementById('compareForm').addEventListener('submit', function (e) {
        if (selectV1.value && selectV2.value && selectV1.value === selectV2.value) {
            e.preventDefault();
            alert('Pilih dua versi yang berbeda untuk membandingkan.');
        }
    });
});
</script>
@endsection
