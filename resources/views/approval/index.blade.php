{{-- resources/views/approval/index.blade.php --}}
@extends('layouts.iso')

@section('title','Approval Queue')

@section('content')
<div class="container" style="max-width:1200px;margin:24px auto;">
  <h2 class="mb-3">Approval Queue — {{ strtoupper($stage ?? 'ALL') }}</h2>

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

  <div class="mb-4 d-flex gap-2">
    <a href="{{ route('drafts.index') }}" class="btn btn-outline-secondary">Draft Container</a>
    <a href="{{ route('approval.index') }}" class="btn btn-primary">Refresh</a>
  </div>

  @if(empty($pending) || $pending->isEmpty())
    <div class="card p-4">Tidak ada dokumen yang menunggu persetujuan.</div>
  @else
    <div class="card p-3 mb-4">
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 align-middle">
          <thead>
            <tr>
              <th>Kode Dokumen</th>
              <th>Judul</th>
              <th>Departemen</th>
              <th>Versi</th>
              <th>Pengaju</th>
              <th>When</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @foreach($pending as $ver)
              <tr>
                <td>
                  <a href="{{ route('documents.show', $ver->document->id) }}">
                    {{ $ver->document->doc_code }}
                  </a>
                </td>
                <td>{{ \Illuminate\Support\Str::limit($ver->document->title, 60) }}</td>
                <td>{{ $ver->document->department->code ?? '-' }}</td>
                <td>{{ $ver->version_label }}</td>
                <td>{{ optional($ver->creator)->email ?? '-' }}</td>
                <td>{{ $ver->created_at?->format('Y-m-d') ?? '-' }}</td>
                <td class="text-center">
                  {{-- Open detail (anchor ke versi) --}}
                  <a href="{{ route('documents.show', $ver->document->id).'#v'.$ver->id }}" class="btn btn-sm btn-outline-primary">Open</a>

                  {{-- Compare (jika route tersedia) --}}
                  @if(Route::has('documents.compare'))
                    <a href="{{ route('documents.compare', $ver->document->id) }}" class="btn btn-sm btn-outline-info">Compare</a>
                  @endif

                  {{-- Approve --}}
                  <form action="{{ route('approval.approve', $ver->id) }}" method="POST" class="d-inline">
                    @csrf
                    <input type="hidden" name="note" value="Approved by {{ auth()->user()->name ?? auth()->user()->email }}">
                    <button class="btn btn-sm btn-success" onclick="return confirm('Approve version {{ $ver->version_label }} ?')">Approve</button>
                  </form>

                  {{-- Reject (modal) --}}
                  <button
                    class="btn btn-sm btn-danger"
                    data-bs-toggle="modal"
                    data-bs-target="#rejectModal"
                    data-version-id="{{ $ver->id }}"
                    data-doc-code="{{ $ver->document->doc_code }}"
                    data-version-label="{{ $ver->version_label }}"
                  >Reject</button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
</div>

{{-- Reject Modal (reusable) --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <form id="rejectForm" method="POST" action="">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">
            Reject <span id="rejectDocCode" class="fw-semibold"></span>
            <small class="text-muted" id="rejectVersionLabel"></small>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label for="rejectNotes" class="form-label">Alasan reject <small class="text-muted">(wajib)</small></label>
          <textarea name="notes" id="rejectNotes" class="form-control" rows="5" required></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-danger">Submit Reject</button>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Bootstrap 5 modal: isi form action & label secara dinamis
  const rejectModal = document.getElementById('rejectModal');
  if (rejectModal) {
    rejectModal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      const versionId = button.getAttribute('data-version-id');
      const docCode = button.getAttribute('data-doc-code') || '';
      const verLabel = button.getAttribute('data-version-label') || '';

      const form = document.getElementById('rejectForm');
      const notes = document.getElementById('rejectNotes');
      const codeEl = document.getElementById('rejectDocCode');
      const labelEl = document.getElementById('rejectVersionLabel');

      form.action = "{{ url('approval') }}/" + versionId + "/reject";
      notes.value = '';
      codeEl.textContent = docCode ? `(${docCode})` : '';
      labelEl.textContent = verLabel ? `— ${verLabel}` : '';
    });
  }
</script>
@endpush
