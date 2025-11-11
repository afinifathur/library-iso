@extends('layouts.iso')

@section('title', 'Daftar Departemen')

@section('content')
<div style="max-width:1000px;margin:24px auto;">
  <h2>📁 Daftar Departemen</h2>

  @if($departments->isEmpty())
    <p style="color:#777;">Belum ada departemen terdaftar.</p>
  @else
    <table style="width:100%;border-collapse:collapse;background:#fff;">
      <thead>
        <tr style="border-bottom:1px solid #ddd;text-align:left;">
          <th style="padding:8px;">Kode</th>
          <th style="padding:8px;">Nama Departemen</th>
          <th style="padding:8px;">PIC</th>
        </tr>
      </thead>
      <tbody>
        @foreach($departments as $dep)
          <tr style="border-bottom:1px solid #f0f0f0;">
            <td style="padding:8px;">{{ $dep->code }}</td>
            <td style="padding:8px;">{{ $dep->name }}</td>
            <td style="padding:8px;">{{ $dep->pic_name }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
