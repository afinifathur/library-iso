@extends('layouts.iso')

@section('content')
<h2>Approval Queue</h2>
<p>Versions awaiting review / approval.</p>

<style>
  /* opsional: styling sederhana jika belum pakai framework CSS */
  .table { width:100%; border-collapse:collapse; }
  .table th, .table td { border:1px solid #e5e5e5; padding:8px; text-align:left; }
  .table thead th { background:#f7f7f7; }
  .btn { padding:6px 10px; border:1px solid #ccc; background:#fff; cursor:pointer; }
  .btn:hover { background:#f0f0f0; }
  .btn-muted { padding:4px 8px; color:#444; text-decoration:none; }
  .btn-muted:hover { text-decoration:underline; }
  textarea { font-family:inherit; font-size:0.95rem; }
</style>

<table class="table">
  <thead>
    <tr>
      <th>When</th>
      <th>Doc</th>
      <th>Version</th>
      <th>Dept</th>
      <th>Status</th>
      <th style="width:320px;">Action</th>
    </tr>
  </thead>
  <tbody>
    @forelse($queue as $v)
    <tr>
      <td>{{ $v->created_at?->format('Y-m-d H:i') }}</td>
      <td>{{ $v->document->doc_code }} — {{ $v->document->title }}</td>
      <td>{{ $v->version_label }}</td>
      <td>{{ $v->document->department->code ?? '-' }}</td>
      <td style="text-transform:capitalize;">{{ $v->status }}</td>
      <td>
        {{-- Jika kamu pakai Gate/Permission untuk tombol, aktifkan @can.
             Jika tidak, hapus @can/@endcan – controller sudah cek role saat submit. --}}
        @can('approve-versions')
        @endcan

        <form method="post" action="{{ route('versions.approve', $v->id) }}" style="display:inline">
          @csrf
          <button class="btn" type="submit" onclick="return confirm('Approve this version?')">Approve</button>
        </form>

        <button
          type="button"
          class="btn-muted"
          onclick="document.getElementById('reject-{{ $v->id }}').style.display='block'">
          Reject
        </button>

        <div id="reject-{{ $v->id }}" style="display:none; margin-top:6px;">
          <form method="post" action="{{ route('versions.reject', $v->id) }}">
            @csrf
            <textarea name="comment" required placeholder="Alasan reject..." rows="2" style="width:260px"></textarea><br>
            <button class="btn" type="submit">Submit Reject</button>
            <button type="button" class="btn-muted"
              onclick="document.getElementById('reject-{{ $v->id }}').style.display='none'">
              Cancel
            </button>
          </form>
        </div>

        <a class="btn-muted" href="{{ route('documents.show', $v->document->id) }}">Open</a>
      </td>
    </tr>
    @empty
    <tr>
      <td colspan="6"><em>No pending versions.</em></td>
    </tr>
    @endforelse
  </tbody>
</table>

@if(method_exists($queue, 'links'))
  <div style="margin-top:12px;">
    {{ $queue->links() }}
  </div>
@endif
@endsection
