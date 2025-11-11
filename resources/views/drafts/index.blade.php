{{-- resources/views/drafts/index.blade.php --}}
@extends('layouts.iso')

@section('title','Draft Container')

@section('content')
<div class="container" style="max-width:1100px;margin:24px auto;">
  <h2 class="mb-1">Draft Container</h2>
  <p class="text-muted">Daftar versi yang masih draft atau dikembalikan untuk revisi.</p>

  {{-- Flash messages --}}
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  <div class="card p-3">
    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead>
          <tr>
            <th>Doc</th>
            <th>Version</th>
            <th>By</th>
            <th>Updated</th>
            <th>Stage</th>
            <th style="width:220px;">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($versions as $v)
            <tr>
              <td>
                <a href="{{ route('documents.show', $v->document->id) }}">
                  {{ $v->document->doc_code }} —
                  {{ \Illuminate\Support\Str::limit($v->document->title, 50) }}
                </a>
              </td>
              <td>{{ $v->version_label }}</td>
              <td>{{ optional($v->creator)->email ?? '-' }}</td>
              <td>{{ optional($v->updated_at)?->format('Y-m-d') ?? '-' }}</td>
              <td>{{ ucfirst($v->status) }} — {{ strtoupper($v->approval_stage ?? '-') }}</td>
              <td>
                <a class="btn btn-sm btn-primary" href="{{ route('documents.show', $v->document->id) }}">Open</a>
                <a class="btn btn-sm btn-secondary" href="{{ route('versions.edit', $v->id) }}">Edit</a>
                {{-- Opsional: hapus draft (khusus MR/Admin) --}}
                @if(auth()->user()->hasAnyRole(['mr','admin']))
                  <form action="{{ route('drafts.destroy', $v->id) }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Delete this draft?')">Delete</button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-muted">No drafts found.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="mt-3">
      {{ $versions->links() }}
    </div>
  </div>
</div>
@endsection
