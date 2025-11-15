{{-- resources/views/drafts/index.blade.php --}}
@extends('layouts.app')

@section('title', 'Drafts')

@section('content')
<div class="page-card">
    <h2 class="h2">Draft Container</h2>

    <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ route('drafts.index', ['filter' => 'draft']) }}" class="btn btn-muted" aria-pressed="{{ request('filter') === 'draft' ? 'true' : 'false' }}">Drafts</a>
        <a href="{{ route('drafts.index', ['filter' => 'rejected']) }}" class="btn btn-muted" aria-pressed="{{ request('filter') === 'rejected' ? 'true' : 'false' }}">Rejected</a>
        <a href="{{ route('documents.create') }}" class="btn">New Document</a>
    </div>

    <table class="table" role="table" aria-describedby="drafts-table">
        <thead>
            <tr>
                <th>Doc Code</th>
                <th>Title</th>
                <th>Version</th>
                <th>Creator</th>
                <th>Status</th>
                <th>Rejected Reason</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($drafts as $v)
            <tr>
                <td>{{ $v->document->doc_code ?? '-' }}</td>
                <td>{{ $v->document->title ?? '-' }}</td>
                <td>{{ $v->version_label }}</td>
                <td>{{ $v->creator->name ?? '-' }}</td>
                <td>
                    <span class="badge {{ $v->status === 'rejected' ? 'badge-danger' : 'badge-warning' }}">
                        {{ ucfirst($v->status) }}
                    </span>
                </td>

                <td style="max-width:240px;">
                    @if(!empty($v->rejected_reason))
                        {{ \Illuminate\Support\Str::limit($v->rejected_reason, 120) }}
                    @else
                        -
                    @endif
                </td>

                <td>
                    <a href="{{ route('drafts.show', $v->id) }}" class="btn btn-muted">Open</a>

                    @can('edit', $v)
                        <a href="{{ route('drafts.edit', $v->id) }}" class="btn">Edit</a>
                    @endcan

                    @if($v->status !== 'submitted')
                        {{-- Submit (POST) --}}
                        <form action="{{ route('drafts.submit', $v->id) }}" method="POST" style="display:inline">
                            @csrf
                            <button class="btn" type="submit" onclick="return confirm('Submit ke MR?')">Submit</button>
                        </form>

                        {{-- Delete (keputusan: tetap POST supaya sesuai route yang ada) --}}
                        <form action="{{ route('drafts.destroy', $v->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus draft?')">
                            @csrf
                            <button class="btn btn-muted" type="submit">Delete</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center">No drafts found.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <div style="margin-top:12px;">
        {{ $drafts->withQueryString()->links() }}
    </div>
</div>
@endsection
