@extends('layouts.iso')

@section('content')
<div class="h2">Dashboard</div>

<div class="dashboard-top">
  <div class="stat-card">
    <div class="label">Total Documents</div>
    <div class="value">{{ $totalDocs }}</div>
  </div>
  <div class="stat-card">
    <div class="label">Total Versions</div>
    <div class="value">{{ $totalVersions }}</div>
  </div>
  <div class="stat-card">
    <div class="label">Pending Revisions</div>
    <div class="value">{{ $pendingRevisions }}</div>
  </div>
  <div class="stat-card">
    <div class="label">Published (approved)</div>
    <div class="value">{{ $published }}</div>
  </div>
</div>

<div class="dashboard-grid">
  <div class="card-section">
    <div class="card-inner">
      <h3 style="margin-top:0">Documents per Department</h3>
      <table class="table" role="table">
        <thead><tr><th>Dept</th><th style="width:80px;text-align:right">Count</th></tr></thead>
        <tbody>
          @foreach($byDept as $d)
            <tr>
              <td><a href="{{ route('departments.show', $d->id) }}">{{ $d->code }} — {{ $d->name }}</a></td>
              <td style="text-align:right">{{ $d->documents_count }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <div style="margin-top:12px;display:flex;gap:8px;">
        <a class="btn" href="{{ route('documents.create') }}">Upload Signed PDF</a>
        <a class="btn-muted" href="{{ route('documents.index') }}">All Documents</a>
      </div>
    </div>
  </div>

  <div class="card-section">
    <div class="card-inner">
      <h3 style="margin-top:0">Recent versions</h3>
      <table class="table">
        <thead><tr><th>When</th><th>Doc</th><th>Version</th><th>Status</th></tr></thead>
        <tbody>
          @forelse($recent as $r)
            <tr>
              <td style="white-space:nowrap">{{ $r->created_at->format('Y-m-d H:i') }}</td>
              <td><a href="{{ route('documents.show', $r->document->id) }}">{{ $r->document->doc_code }}</a></td>
              <td>{{ $r->version_label }}</td>
              <td>
                @if($r->status=='approved') <span class="badge badge-success">approved</span>
                @elseif($r->status=='rejected') <span class="badge badge-danger">rejected</span>
                @elseif($r->status=='draft') <span class="badge badge-warning">draft</span>
                @else <span class="badge">{{ $r->status }}</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="small-muted">No recent activity</td></tr>
          @endforelse
        </tbody>
      </table>

      <div style="margin-top:12px;text-align:right">
        <a class="btn-muted" href="{{ route('revision.index') }}">See full history</a>
      </div>
    </div>
  </div>
</div>
@endsection
