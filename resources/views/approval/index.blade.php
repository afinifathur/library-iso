@extends('layouts.iso')

@section('title', 'Approval Queue')

@section('content')
<div class="container page-card">
    <div class="site-header">
        <div class="brand">
            <div class="logo">ISO</div>
            <div class="brand-text">
                <h1>Approval Queue</h1>
                <div class="sub">Stage — {{ $stage ?? ($userRoleLabel ?? 'ALL') }}</div>
            </div>
        </div>

        <nav class="main-nav" aria-label="actions">
            <a href="{{ route('drafts.index') }}" class="btn">Draft Container</a>
            <a href="{{ route('approval.index') }}" class="btn btn-muted">Refresh</a>
        </nav>
    </div>

    {{-- actions above table: includes Compare selected --}}
    <div class="mb-3" style="display:flex;gap:8px;align-items:center;">
      <a href="{{ route('drafts.index') }}" class="btn">Draft Container</a>
      <button class="btn btn-muted" onclick="location.reload()">Refresh</button>

      <button id="compareSelectedBtn" class="btn btn-outline-secondary" style="margin-left:10px;">Compare selected</button>
    </div>

    <div class="table-responsive card-section card-inner">
        <table class="table" role="table" aria-label="Pending approvals">
            <thead>
                <tr>
                    <th style="width:30px"><input id="selectAll" type="checkbox" aria-label="Select all versions"></th>
                    <th>Kode Dokumen</th>
                    <th>Judul</th>
                    <th>Departemen</th>
                    <th>Versi</th>
                    <th>Pengaju</th>
                    <th>When</th>
                    <th style="width:270px">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @php
                    // accept multiple variable names from controller for compatibility
                    $rows = $pendingVersions ?? $pending ?? collect();
                @endphp

                @forelse($rows as $v)
                    <tr data-version-id="{{ $v->id }}" data-doc-id="{{ $v->document->id ?? $v->document_id }}">
                        <td>
                            <input class="select-version" type="checkbox" value="{{ $v->id }}" data-doc="{{ $v->document->id ?? $v->document_id }}">
                        </td>

                        <td>
                            <a href="{{ route('documents.show', $v->document->id ?? $v->document_id) }}" target="_blank" rel="noopener">
                                {{ $v->document->doc_code ?? ($v->document_code ?? '-') }}
                            </a>
                        </td>

                        <td>{{ \Illuminate\Support\Str::limit($v->document->title ?? ($v->title ?? '-'), 120) }}</td>
                        <td>{{ $v->document->department->code ?? $v->document->department->name ?? '-' }}</td>
                        <td>{{ $v->version_label ?? ($v->label ?? '-') }}</td>
                        <td>{{ optional($v->creator ?? $v->created_by_user)->email ?? optional($v->creator ?? $v->created_by_user)->name ?? '-' }}</td>
                        <td>{{ optional($v->created_at)->format('Y-m-d') ?? '-' }}</td>

                        <td>
                            <div class="action-buttons">
                                <!-- Open (always enabled) -->
                                <a href="{{ route('versions.show', $v->id) }}" target="_blank" class="btn btn-outline-primary btn-sm action-open">Open</a>

                                <!-- Compare (single) -->
                                <a href="{{ route('documents.compare', $v->document->id ?? $v->document_id ?? 0) }}?version={{ $v->id }}" target="_blank" class="btn btn-outline-secondary btn-sm action-compare">Compare</a>

                                <!-- Approve (form) -->
                                <form method="POST" action="{{ route('approval.approve', $v->id) }}" class="d-inline-block action-form-approve" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm btn-approve" disabled>Approve</button>
                                </form>

                                <!-- Reject (open modal) -->
                                <button type="button"
                                        class="btn btn-danger btn-sm btn-reject"
                                        disabled
                                        data-version-id="{{ $v->id }}"
                                        data-doc-code="{{ $v->document->doc_code ?? '' }}">
                                    Reject
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="small-muted">Tidak ada versi yang menunggu persetujuan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-end" style="margin-top:12px">
        @if(method_exists($rows, 'links')) {{ $rows->links() }} @endif
    </div>
</div>

<!-- Reject Modal (custom) -->
<div id="rejectModal" class="modal-overlay" aria-hidden="true" style="display:none;">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle">
    <div class="modal-header">
      <h3 id="rejectModalTitle">Alasan Reject <span id="rejectDocCode" class="fw-bold"></span></h3>
      <button class="modal-close" type="button" onclick="closeRejectModal()" aria-label="Close">×</button>
    </div>

    <div class="modal-body">
      <form id="rejectForm" onsubmit="return false;">
        <div class="form-row">
          <label for="reject_reason">Alasan reject <small class="text-muted">(wajib diisi)</small></label>
          <textarea id="reject_reason" name="reason" class="form-textarea" rows="6" required></textarea>
        </div>
        <input type="hidden" id="reject_version_id" name="version_id" value="">
      </form>
    </div>

    <div class="modal-footer">
      <button class="btn btn-muted" type="button" onclick="closeRejectModal()">Batal</button>
      <button id="rejectSubmitBtn" class="btn btn-danger" type="button">Submit Reject</button>
    </div>
  </div>
</div>
@endsection

@section('footerscripts')
<script>
(() => {
  // Track opened versions
  const opened = new Set();

  // Helper to enable action buttons in a row
  function enableRowActions(tr) {
    if (!tr) return;
    tr.querySelectorAll('.btn-approve, .btn-reject').forEach(btn => btn.removeAttribute('disabled'));
  }

  // --- Open link handling: mark version opened and enable row actions ---
  document.querySelectorAll('.action-open').forEach(a => {
    a.addEventListener('click', function () {
      const tr = this.closest('tr');
      const vid = tr?.dataset?.versionId;
      if (!vid) return;
      opened.add(String(vid));
      // small delay to allow browser tab opening
      setTimeout(() => enableRowActions(tr), 300);
    });
  });

  // --- Approve: prevent submit unless version opened ---
  document.querySelectorAll('.action-form-approve').forEach(form => {
    form.addEventListener('submit', function (e) {
      const tr = this.closest('tr');
      const vid = tr?.dataset?.versionId;
      if (!vid || !opened.has(String(vid))) {
        e.preventDefault();
        alert('Silakan buka dokumen (Open) terlebih dahulu sebelum menyetujui.');
        return false;
      }
      // allow submit
    });
  });

  // --- Reject: require opened, then open modal ---
  document.querySelectorAll('.btn-reject').forEach(btn => {
    btn.addEventListener('click', function () {
      const tr = this.closest('tr');
      const vid = tr?.dataset?.versionId;
      const docCode = this.getAttribute('data-doc-code') || '';
      if (!vid || !opened.has(String(vid))) {
        alert('Silakan buka dokumen (Open) terlebih dahulu sebelum menolak.');
        return;
      }
      document.getElementById('reject_version_id').value = vid;
      document.getElementById('rejectDocCode').innerText = docCode ? `(${docCode})` : '';
      document.getElementById('reject_reason').value = '';
      showRejectModal();
    });
  });

  // --- Modal controls ---
  window.showRejectModal = function () {
    const el = document.getElementById('rejectModal');
    if (!el) return;
    el.style.display = 'flex';
    el.setAttribute('aria-hidden', 'false');
    setTimeout(() => document.getElementById('reject_reason').focus(), 50);
  };
  window.closeRejectModal = function () {
    const el = document.getElementById('rejectModal');
    if (!el) return;
    el.style.display = 'none';
    el.setAttribute('aria-hidden', 'true');
  };

  // --- Submit reject via fetch (AJAX) ---
  const rejectBtn = document.getElementById('rejectSubmitBtn');
  if (rejectBtn) {
    rejectBtn.addEventListener('click', function () {
      const btn = this;
      const vid = document.getElementById('reject_version_id').value;
      const reason = document.getElementById('reject_reason').value.trim();

      if (!reason) {
        alert('Alasan reject wajib diisi.');
        return;
      }
      if (!vid) {
        alert('Version ID tidak terdeteksi.');
        return;
      }

      btn.disabled = true;
      btn.innerText = 'Submitting...';

      fetch("{{ url('/approval') }}/" + encodeURIComponent(vid) + "/reject", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ notes: reason })
      })
      .then(async r => {
        const ct = r.headers.get('content-type') || '';
        let payload = {};
        if (ct.includes('application/json')) payload = await r.json();
        else payload = { success: r.ok };

        if (r.ok && (payload.success === undefined || payload.success)) {
          closeRejectModal();
          alert(payload.message || 'Version berhasil direject.');
          window.location.reload();
        } else {
          throw new Error(payload.message || 'Gagal menolak versi.');
        }
      })
      .catch(err => {
        console.error(err);
        alert(err.message || 'Terjadi error. Cek console.');
      })
      .finally(() => {
        btn.disabled = false;
        btn.innerText = 'Submit Reject';
      });
    });
  }

  // --- Select All / Compare Selected functionality ---
  const selectAll = document.getElementById('selectAll');
  const compareBtn = document.getElementById('compareSelectedBtn');

  if (selectAll) {
    selectAll.addEventListener('change', function (e) {
      document.querySelectorAll('.select-version').forEach(ch => ch.checked = e.target.checked);
    });
  }

  if (compareBtn) {
    compareBtn.addEventListener('click', function () {
      const checked = Array.from(document.querySelectorAll('.select-version:checked'));
      if (checked.length < 2) {
        alert('Pilih minimal 2 versi dari dokumen yang sama untuk membandingkan.');
        return;
      }

      // ensure all selected versions belong to same document
      const docs = checked.map(c => c.dataset.doc);
      const firstDoc = docs[0];
      const allSame = docs.every(d => d === firstDoc);
      if (!allSame) {
        alert('Silakan pilih versi yang berasal dari DOKUMEN yang SAMA untuk membandingkan.');
        return;
      }

      const versionIds = checked.map(c => c.value);
      // build url: /documents/{docId}/compare?versions[]=7&versions[]=8
      const base = "{{ url('/documents') }}/";
      const url = new URL(base + encodeURIComponent(firstDoc) + "/compare", window.location.origin);
      versionIds.forEach(id => url.searchParams.append('versions[]', id));
      window.open(url.toString(), '_blank');
    });
  }

  // --- Escape key closes modal ---
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      const modal = document.getElementById('rejectModal');
      if (modal && modal.style.display === 'flex') closeRejectModal();
    }
  });

})();
</script>
@endsection
