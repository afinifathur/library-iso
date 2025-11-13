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
                            <a href="{{ route('documents.index', ['category' => $cat->id]) }}" class="btn-muted">Buka Dokumen</a>
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
