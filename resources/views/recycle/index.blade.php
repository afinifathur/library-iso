{{-- resources/views/recycle/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Recycle Bin')

@section('content')
  <div class="card" style="padding:18px;">
    <h3>Recycle Bin — Trashed Versions</h3>
    <p>Daftar versi yang dipindahkan ke Recycle Bin. Restore atau hapus permanen.</p>

    <table class="table" style="width:100%; margin-top:12px;">
      <thead>
        <tr>
          <th></th>
          <th>Doc Code</th>
          <th>Title</th>
          <th>Version</th>
          <th>Pengaju</th>
          <th>When</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $row)
          <tr>
            <td><input type="checkbox" /></td>
            <td>{{ optional($row->document)->doc_code }}</td>
            <td>{{ optional($row->document)->title }}</td>
            <td>{{ $row->version_label }}</td>
            <td>{{ optional($row->creator)->email ?? '—' }}</td>
            <td>{{ optional($row->updated_at)->format('Y-m-d') }}</td>
            <td style="display:flex;gap:8px;">
              <form method="POST" action="{{ route('recycle.restore', $row->id) }}">
                @csrf
                <button class="btn" type="submit" style="background:#10b981;color:#fff;border:none;border-radius:6px;padding:.3rem .6rem;">Restore</button>
              </form>

              <form method="POST" action="{{ route('recycle.destroy', $row->id) }}" onsubmit="return confirm('Permanently delete this version? This action cannot be undone.');">
                @csrf
                @method('DELETE')
                <button class="btn" type="submit" style="background:#ef4444;color:#fff;border:none;border-radius:6px;padding:.3rem .6rem;">Delete Permanently</button>
              </form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div style="margin-top:12px;">
      {{ $rows->links() }}
    </div>

  </div>
@endsection
