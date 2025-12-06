@extends('layouts.iso') {{-- atau layouts.app sesuai projectmu --}}

@section('content')
<div class="page-card">
    <div class="site-header" style="align-items:flex-start;">
        <div>
            <h2 class="h2">Daftar Kategori</h2>
            <p class="small-muted">Tipe dokumen dan jumlah per status</p>
        </div>
        <div style="margin-left:auto">
            <a href="{{ route('documents.create') }}" class="btn">+ New Document</a>
        </div>
    </div>

    <div class="card-section">
        <div class="card-inner">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Tipe Dokumen</th>
                        <th style="width:120px">Dokumen Aktif</th>
                        <th style="width:120px">In Progress</th>
                        <th style="width:120px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $cat)
                    <tr>
                        <td><strong>{{ $cat->code }}</strong></td>
                        <td>{{ $cat->name }}</td>
                        <td>
                            <span class="badge badge-success">{{ $cat->active_count ?? 0 }}</span>
                        </td>
                        <td>
                            <span class="badge badge-warning">{{ $cat->in_progress_count ?? 0 }}</span>
                        </td>
                        <td>
                            <a href="{{ url('documents') . '?search=' . urlencode($cat->code . '.') }}" style="
        display:inline-block;
        padding:6px 18px;
        background:#1d4ed8;
        color:#ffffff;
        border-radius:999px;
        font-weight:500;
        text-decoration:none;
        font-size:0.88rem;
        border:1px solid #1d4ed8;
        transition:0.15s;
    "
    onmouseover="this.style.background='#1e40af'"
    onmouseout="this.style.background='#1d4ed8'">Buka Dokumen</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5">Belum ada kategori.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
