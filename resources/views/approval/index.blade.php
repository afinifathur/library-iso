@extends('layouts.iso')

@section('content')
<div class="container" style="max-width:1200px;margin:24px auto;">
  <h2 class="mb-3">Approval Queue — {{ $stage ?? 'ALL' }}</h2>

  <div class="mb-4 d-flex gap-2">
    <a href="{{ route('drafts.index') }}" class="btn btn-outline-secondary">Draft Container</a>
    <a href="{{ route('approval.index') }}" class="btn btn-primary">Refresh</a>
  </div>

  @if($pending->isEmpty())
    <div class="card p-4">Tidak ada dokumen yang menunggu persetujuan.</div>
  @else
    <div class="card p-3 mb-6">
      <table class="table table-striped table-hover mb-0">
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
              <td><a href="{{ route('documents.show', $ver->document->id) }}">{{ $ver->document->doc_code }}</a></td>
              <td>{{ \Illuminate\Support\Str::limit($ver->document->title, 60) }}</td>
              <td>{{ $ver->document->department->code ?? '-' }}</td>
              <td>{{ $ver->version_label }}</td>
              <td>{{ optional($ver->creator)->email ?? '-' }}</td>
              <td>{{ $ver->created_at ? $ver->created_at->format('Y-m-d') : '-' }}</td>
              <td class="text-center approval-actions">
                <a href="{{ route('documents.show', $ver->document->id).'#v'.$ver->id }}" class="btn btn-sm btn-outline-primary">Open</a>

                <a href="{{ route('documents.compare', $ver->document->id) }}" class="btn btn-sm btn-outline-info">Compare</a>

                <form action="{{ route('approval.approve', $ver->id) }}" method="POST" style="display:inline;">
                  @csrf
                  <button class="btn btn-sm btn-success">Approve</button>
                </form>

                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-version-id="{{ $ver->id }}" data-doc-code="{{ $ver->document->doc_code }}">Reject</button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="text-right">{{ $pending->links() }}</div>
  @endif
</div>

<!-- Reject Modal (single modal reused) -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <form id="rejectForm" method="POST" action="">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Reject Version <span id="rejectDocCode"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-2">Alasan reject <small class="text-muted">(wajib diisi)</small></div>
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
  (function(){
    var rejectModalEl = document.getElementById('rejectModal');
    if (!rejectModalEl) return;

    rejectModalEl.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      var versionId = button.getAttribute('data-version-id');
      var docCode = button.getAttribute('data-doc-code');
      var form = document.getElementById('rejectForm');
      // build action url
      form.action = "{{ url()->current() }}/../approval/" + versionId + "/reject";
      // Safe alternative if route helper needed:
      // form.action = "{{ url('approval') }}/" + versionId + "/reject";
      var span = document.getElementById('rejectDocCode');
      if (span) span.innerText = docCode;
      var ta = document.getElementById('rejectNotes');
      if (ta) ta.value = '';
    });
  })();
</script>
@endpush
