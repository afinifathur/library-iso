@extends('layouts.iso')

@section('content')
<h2>Audit Log</h2>
<p>Recent file/version events (proxy for audit trail).</p>

<style>
  .table { width:100%; border-collapse: collapse; }
  .table th, .table td { border:1px solid #e5e5e5; padding:8px; text-align:left; }
  .table thead th { background:#f7f7f7; }
  .btn { padding:6px 10px; border:1px solid #ccc; background:#fff; cursor:pointer; text-decoration:none; display:inline-block; }
  .btn:hover { background:#f0f0f0; }
  .muted { color:#666; font-size:.9rem; }
</style>

<div style="margin-bottom:12px;">
  <a class="btn" href="{{ route('audit.index', ['export' => 'csv']) }}">Export CSV</a>
</div>

@if($events->count() === 0)
  <p class="muted">No audit events.</p>
@else
  <table class="table">
    <thead>
      <tr>
        <th>When</th>
        <th>Event</th>
        <th>Doc</th>
        <th>Version</th>
        <th>User</th>
        <th>Details</th>
        <th>IP</th>
      </tr>
    </thead>
    <tbody>
      @foreach($events as $e)
        <tr>
          <td>{{ $e->created_at?->format('Y-m-d H:i') }}</td>
          <td>{{ $e->event }}</td>
          <td>{{ $e->document->doc_code ?? '-' }}</td>
          <td>{{ $e->version->version_label ?? '-' }}</td>
          <td>{{ $e->user->email ?? $e->user->name ?? '-' }}</td>
          <td>
            @php
              // tampilkan detail pendek; jika JSON panjang, potong
              $detail = is_string($e->detail) ? $e->detail : json_encode($e->detail);
              $short  = Str::limit($detail, 120);
            @endphp
            {{ $short ?: '-' }}
          </td>
          <td>{{ $e->ip ?? '-' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  @if(method_exists($events, 'links'))
    <div style="margin-top:12px;">
      {{ $events->links() }}
    </div>
  @endif
@endif
@endsection
