@extends('layouts.iso')

@section('content')
<div class="container" style="max-width:1100px;margin:24px auto;">
  <h2 class="mb-2">Draft Container</h2>
  <p class="text-muted">Daftar versi yang masih <strong>draft</strong> atau <strong>dikembalikan</strong> untuk revisi.</p>

  <div class="card p-3">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Doc</th>
          <th>Version</th>
          <th>By</th>
          <th>Updated</th>
          <th>Stage</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($versions as $v)
        <tr>
          <td>
            <a href="{{ route('documents.show', $v->document->id) }}">
              {{ $v->document->doc_code }} — {{ \Illuminate\Support\Str::limit($v->document->title,50) }}
            </a>
          </td>
          <td>{{ $v->version_label }}</td>
          <td>{{ optional($v->creator)->email ?? '-' }}</td>
          <td>{{ $v->updated_at ? $v->updated_at->format('Y-m-d') : '-' }}</td>
          <td>{{ $v->status }} — {{ $v->approval_stage }}</td>
          <td>
            <a class="btn btn-sm btn-primary" href="{{ route('documents.show', $v->document->id) }}">Open</a>
            <a class="btn btn-sm btn-secondary" href="{{ route('versions.edit', $v->id) }}">Edit</a>
          </td>
        </tr>
        @empty
        <tr><td colspan="6">No drafts found.</td></tr>
        @endforelse
      </tbody>
    </table>

    <div class="mt-3">{{ $versions->links() }}</div>
  </div>
</div>
@endsection
