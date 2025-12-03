@extends('layouts.iso')

@section('title', $document->title)

@section('content')
<div class="container" style="max-width:1000px;">
    
    {{-- HEADER --}}
    <h2 class="mb-1">{{ $document->doc_code }} — {{ $document->title }}</h2>
    <p class="text-muted mb-3">
        Department: {{ $document->department->name ?? '-' }}
    </p>

    {{-- ACTION BUTTONS --}}
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a href="{{ route('documents.edit', $document->id) }}" class="btn btn-primary">
            ✏️ Edit Document Info
        </a>

        @if($version)
            <a href="{{ route('versions.create', ['document_id' => $document->id]) }}"
               class="btn btn-outline-primary">
                ➕ New Version
            </a>

            <a href="{{ route('versions.edit', $version->id) }}"
               class="btn btn-outline-secondary">
                🛠️ Edit Latest Version
            </a>
        @else
            <a href="{{ route('versions.create', ['document_id' => $document->id]) }}"
               class="btn btn-outline-primary">
                📄 Add First Version
            </a>
        @endif
    </div>


    {{-- NO VERSION YET --}}
    @if(!$version)
        <div class="alert alert-info">
            <strong>Belum ada versi dokumen.</strong><br>
            Klik <b>Add First Version</b> untuk menambahkan versi pertama.
        </div>
    @else

        {{-- LATEST VERSION SUMMARY --}}
        <div class="card mb-4">
            <div class="card-header">
                <strong>Latest Version — {{ $version->version_label }}</strong>
            </div>
            <div class="card-body">
                <p>Status:
                    <span class="badge bg-secondary">{{ $version->status }}</span>
                </p>

                @if($version->change_note)
                    <p class="mb-1"><strong>Change Note:</strong></p>
                    <p class="text-muted">{{ $version->change_note }}</p>
                @endif
            </div>
        </div>

    @endif


    {{-- LIST OF ALL VERSIONS --}}
    <h4>All Versions</h4>
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Version</th>
                <th>Status</th>
                <th>Uploaded</th>
                <th>Signed By</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($versions as $v)
                <tr>
                    <td>{{ $v->version_label }}</td>
                    <td><span class="badge bg-info">{{ $v->status }}</span></td>
                    <td>{{ optional($v->created_at)->format('Y-m-d') }}</td>
                    <td>{{ $v->signed_by ?? '-' }}</td>
                    <td class="text-end">
                        <a href="{{ route('versions.show', $v->id) }}"
                           class="btn btn-sm btn-outline-primary">View</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-muted text-center py-3">
                        No versions found.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>


    {{-- APPROVAL HISTORY (HANYA JIKA ADA VERSION) --}}
    @if($version && \Schema::hasTable('approval_logs'))
        @php
            $logs = \DB::table('approval_logs')
                ->join('users', 'users.id', '=', 'approval_logs.user_id')
                ->select('approval_logs.*', 'users.name as user_name')
                ->where('document_version_id', $version->id)
                ->orderBy('approval_logs.created_at')
                ->get();
        @endphp

        <div class="card mt-4">
            <div class="card-header">
                <strong>Approval History</strong>
            </div>

            <div class="card-body p-0">
                @if($logs->isEmpty())
                    <div class="p-3 text-muted">No approval activity yet.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Action</th>
                                    <th>Note</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($logs as $log)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i') }}</td>
                                        <td>{{ $log->user_name }}</td>
                                        <td>{{ $log->role ?: '-' }}</td>
                                        <td>{{ ucfirst($log->action) }}</td>
                                        <td>{{ $log->note ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif

</div>
@endsection
