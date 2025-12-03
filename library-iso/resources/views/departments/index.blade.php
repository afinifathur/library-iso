@extends('layouts.iso')

@section('content')
<div style="max-width:800px;margin:0 auto;">
  <h2 style="margin-top:0">Daftar Departemen</h2>

  @if($departments->isEmpty())
    <div class="small-muted">Belum ada departemen terdaftar.</div>
  @else
    <table class="table" style="width:100%">
      <thead>
        <tr><th>Kode</th><th>Nama Departemen</th><th style="width:150px;">Aksi</th></tr>
      </thead>
      <tbody>
        @foreach($departments as $dept)
        <tr>
          <td>{{ $dept->code }}</td>
          <td>{{ $dept->name }}</td>
          <td>
            <a class="btn" href="{{ route('departments.show', $dept->id) }}">Lihat Dokumen</a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
