{{-- resources/views/approval/index.blade.php --}}
@extends('layouts.iso')

@section('title', 'Approval Queue')

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
    <div class="card p-3 mb-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
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
              <tr data-version-id="{{ $ver->id }}">
                <td>
                    <a href="{{ route('documents.show', $ver->document->id) }}" target="_blank" rel="noopener">
                        {{ $ver->document->doc_code }}
                    </a>
                </td>
                <td>{{ \Illuminate\Support\Str::limit($ver->document->title, 80) }}</td>
                <td>{{ $ver->document->department->code ?? $ver->document->department->name ?? '-' }}</td>
                <td>{{ $ver->version_label }}</td>
                <td>{{ optional($ver->creator)->email ?? optional($ver->creator)->name ?? '-' }}</td>
                <td>{{ $ver->created_at ? $ver->created_at->format('Y-m-d') : '-' }}</td>
                <td class="text-center">
                  {{-- Open: selalu aktif. Buka versi di tab baru --}}
                  <a href="{{ route('versions.show', $ver->id) }}" target="_blank" rel="noopener"
                     class="btn btn-sm btn-outline-primary action-open">Open</a>

                  {{-- Compare --}}
                  <a href="{{ route('documents.compare', [$ver->document->id, 'version' => $ver->id]) }}" target="_blank"
                     class="btn btn-sm btn-outline-secondary">Compare</a>

                  {{-- Approve (disabled until Open clicked) --}}
                  <form action="{{ route('approval.approve', $ver->id) }}" method="POST"
                        class="d-inline-block action-form-approve">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success action-approve" disabled>
                      Approve
                    </button>
                  </form>

                  {{-- Reject (disabled until Open clicked) --}}
                  <button type="button"
                          class="btn btn-sm btn-danger action-reject-btn"
                          disabled
                          data-bs-toggle="modal"
                          data-bs-target="#rejectModal"
                          data-version-id="{{ $ver->id }}"
                          data-doc-code="{{ $ver->document->doc_code }}">
                    Reject
                  </button>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="d-flex justify-content-end">
      {{ $pending->links() }}
    </div>
  @endif
</div>

{{-- Reject Modal (Bootstrap 5) --}}
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <form id="rejectForm" method="POST" action="">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="rejectModalLabel">Reject Version <span id="rejectDocCode" class="fw-bold"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div class="mb-2">Alasan reject <small class="text-muted">(wajib diisi)</small></div>
          <textarea name="reason" id="rejectNotes" class="form-control" rows="5" required></textarea>
          <input type="hidden" id="rejectVersionId" name="version_id" value="">
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" id="rejectSubmitBtn" class="btn btn-danger">Submit Reject</button>
        </div>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // track opened versions
  const opened = new Set();

  // When Open is clicked (it opens in a new tab), mark version as opened and enable actions
  document.querySelectorAll('.action-open').forEach(btn => {
    btn.addEventListener('click', function () {
      const tr = this.closest('tr');
      if (!tr) return;
      const vid = tr.dataset.versionId;
      if (!vid) return;
      // mark as opened
      opened.add(String(vid));
      // enable action buttons for this row after a short delay (user might cancel popup blockers)
      setTimeout(() => enableActionsForRow(tr), 200);
      // allow default behavior (open new tab)
    });
  });

  function enableActionsForRow(tr) {
    tr.querySelectorAll('.action-approve, .action-reject-btn').forEach(el => {
      el.removeAttribute('disabled');
    });
  }

  // Prevent Approve form submit if not opened yet
  document.querySelectorAll('.action-form-approve').forEach(form => {
    form.addEventListener('submit', function (e) {
      const tr = this.closest('tr');
      const vid = tr?.dataset.versionId;
      if (!vid || !opened.has(String(vid))) {
        e.preventDefault();
        alert('Silakan buka dokumen (Open) terlebih dahulu sebelum menyetujui.');
        return false;
      }
      // allow submit
    });
  });

  // Setup reject modal: fill fields when modal shown (Bootstrap 5 event)
  const rejectModalEl = document.getElementById('rejectModal');
  if (rejectModalEl) {
    rejectModalEl.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;
      const versionId = button.getAttribute('data-version-id');
      const docCode = button.getAttribute('data-doc-code');
      const tr = button.closest('tr');

      // Ensure the row was opened first
      if (!versionId || !opened.has(String(versionId))) {
        // Prevent modal from showing and warn
        event.preventDefault();
        // Using Bootstrap modal event, we need to hide immediately if shown; but preventDefault should stop.
        alert('Silakan buka dokumen (Open) terlebih dahulu sebelum menolak.');
        return;
      }

      // fill modal
      document.getElementById('rejectVersionId').value = versionId || '';
      document.getElementById('rejectDocCode').innerText = docCode || '';
      document.getElementById('rejectNotes').value = '';

      // set form action (AJAX will use url, but setting action helps fallback)
      const form = document.getElementById('rejectForm');
      if (form) {
        // route: /approval/{version}/reject
        form.action = "{{ url('/approval') }}/" + encodeURIComponent(versionId) + "/reject";
      }
    });
  }

  // Submit reject via AJAX to keep page responsive
  const rejectForm = document.getElementById('rejectForm');
  if (rejectForm) {
    rejectForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const vid = document.getElementById('rejectVersionId').value;
      const reason = document.getElementById('rejectNotes').value.trim();
      if (!reason) {
        alert('Alasan reject wajib diisi.');
        return;
      }
      if (!vid) {
        alert('Version ID tidak terdeteksi.');
        return;
      }

      const submitBtn = document.getElementById('rejectSubmitBtn');
      submitBtn.disabled = true;
      submitBtn.innerText = 'Submitting...';

      // build url (matches route /approval/{version}/reject)
      const url = "{{ url('/approval') }}/" + encodeURIComponent(vid) + "/reject";

      fetch(url, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ reason })
      })
      .then(async res => {
        const contentType = res.headers.get('content-type') || '';
        let data = {};
        if (contentType.includes('application/json')) {
          data = await res.json();
        } else {
          data = { success: res.ok };
        }

        if (res.ok && (data.success === undefined || data.success)) {
          // close modal (Bootstrap)
          const bsModal = bootstrap.Modal.getInstance(document.getElementById('rejectModal'));
          if (bsModal) bsModal.hide();
          alert(data.message || 'Version berhasil direject.');
          // reload page to reflect changes
          window.location.reload();
        } else {
          throw new Error(data.message || 'Gagal menolak versi.');
        }
      })
      .catch(err => {
        console.error(err);
        alert(err.message || 'Terjadi error saat reject. Cek console.');
      })
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Submit Reject';
      });
    });
  }

});
</script>
@endpush
