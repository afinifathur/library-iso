@extends('layouts.iso')

@section('title', 'Approval Queue')

@section('content')
<div class="container page-card">
    <header class="site-header" role="banner" aria-labelledby="approvalTitle" style="display:flex;justify-content:space-between;align-items:center;">
        <div class="brand" style="display:flex;gap:12px;align-items:center">
            <div class="logo">ISO</div>
            <div class="brand-text">
                <h1 id="approvalTitle" style="margin:0">Approval Queue</h1>
                <div class="sub" style="font-size:0.95rem;color:#6b7280">
                    Stage — {{ e($stage ?? ($userRoleLabel ?? 'ALL')) }}
                </div>
            </div>
        </div>

        <nav class="main-nav" aria-label="actions" style="display:flex;gap:8px">
            <a href="{{ route('drafts.index') }}" class="btn">Draft Container</a>
            <button type="button" class="btn btn-muted" onclick="location.reload()">Refresh</button>
        </nav>
    </header>

    {{-- toolbar --}}
    <div class="mb-3" style="display:flex;gap:8px;align-items:center;margin-top:1rem">
        <a href="{{ route('drafts.index') }}" class="btn">Draft Container</a>
        <button class="btn btn-muted" onclick="location.reload()">Refresh</button>

        <button id="compareSelectedBtn" class="btn btn-outline-secondary" style="margin-left:10px;">Compare selected</button>
    </div>

    <section class="table-responsive card-section card-inner" aria-labelledby="pendingTableTitle">
        <h2 id="pendingTableTitle" class="sr-only">Pending approvals</h2>

        <table class="table" role="table" aria-label="Pending approvals" style="width:100%;border-collapse:collapse">
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
                    // controller may provide $pendingVersions or $pending
                    $rows = $pendingVersions ?? $pending ?? collect();
                @endphp

                @forelse($rows as $v)
                    @php
                        $doc = $v->document ?? null;
                        $docId = $doc->id ?? $v->document_id ?? null;
                        $docCode = $doc->doc_code ?? $v->document_code ?? '-';
                        $title = $doc->title ?? $v->title ?? '-';
                        $dept = optional($doc->department)->code ?? optional($doc->department)->name ?? '-';
                        $versionLabel = $v->version_label ?? $v->label ?? '-';
                        $creator = optional($v->creator ?? $v->created_by_user);
                        $creatorDisplay = $creator->email ?? $creator->name ?? '-';
                        $when = optional($v->created_at)->format('Y-m-d') ?? '-';
                    @endphp

                    <tr data-version-id="{{ e($v->id) }}" data-doc-id="{{ e($docId) }}">
                        <td>
                            <input class="select-version" type="checkbox" value="{{ e($v->id) }}" data-doc="{{ e($docId) }}" aria-label="Select version {{ e($versionLabel) }}">
                        </td>

                        <td>
                            @if($docId)
                                <a href="{{ route('documents.show', $docId) }}" target="_blank" rel="noopener noreferrer">
                                    {{ e($docCode) }}
                                </a>
                            @else
                                {{ e($docCode) }}
                            @endif
                        </td>

                        <td>{{ \Illuminate\Support\Str::limit($title, 120) }}</td>
                        <td>{{ e($dept) }}</td>
                        <td>{{ e($versionLabel) }}</td>
                        <td>{{ e($creatorDisplay) }}</td>
                        <td>{{ e($when) }}</td>

                        <td>
                            <div class="action-buttons" style="display:flex;gap:6px;flex-wrap:wrap">
                                <!-- Open -->
                                @if($v->id)
                                    <a href="{{ route('versions.show', $v->id) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm action-open" aria-label="Open version {{ e($versionLabel) }}">Open</a>
                                @else
                                    <button class="btn btn-outline-primary btn-sm" disabled>Open</button>
                                @endif

                                <!-- Compare (single) -> link to document compare with version param -->
                                @if($docId)
                                    <a href="{{ route('documents.compare', $docId) }}?v2={{ $v->id }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary btn-sm action-compare" aria-label="Compare version {{ e($versionLabel) }}">Compare</a>
                                @else
                                    <button class="btn btn-outline-secondary btn-sm" disabled>Compare</button>
                                @endif

                                <!-- Approve (form) -->
                                <form method="POST" action="{{ route('approval.approve', $v->id) }}" class="d-inline-block action-form-approve" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-success btn-sm btn-approve" disabled aria-disabled="true">Approve</button>
                                </form>

                                <!-- Reject -->
                                <button type="button"
                                        class="btn btn-danger btn-sm btn-reject"
                                        disabled
                                        data-version-id="{{ e($v->id) }}"
                                        data-doc-code="{{ e($docCode) }}"
                                        aria-label="Reject version {{ e($versionLabel) }}">
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
    </section>

    <div class="d-flex justify-content-end" style="margin-top:12px">
        @if(method_exists($rows, 'links')) {{ $rows->links() }} @endif
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal-overlay" aria-hidden="true" style="display:none;">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle">
    <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center">
      <h3 id="rejectModalTitle" style="margin:0">Alasan Reject <span id="rejectDocCode" class="fw-bold"></span></h3>
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

    <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:8px">
      <button class="btn btn-muted" type="button" onclick="closeRejectModal()">Batal</button>
      <button id="rejectSubmitBtn" class="btn btn-danger" type="button">Submit Reject</button>
    </div>
  </div>
</div>
@endsection

@section('footerscripts')
<script>
(() => {
  // configuration values from blade (safe)
  const baseApprovalUrl = "{{ rtrim(url('/approval'), '/') }}"; // e.g. /approval
  const csrfToken = "{{ csrf_token() }}";

  // track opened versions
  const opened = new Set();

  // helpers
  const enableRowActions = (tr) => {
    if (!tr) return;
    tr.querySelectorAll('.btn-approve, .btn-reject').forEach(btn => {
      btn.removeAttribute('disabled');
      btn.removeAttribute('aria-disabled');
    });
  };

  // MARK: Open handling - when user clicks Open link, mark opened and enable buttons
  document.querySelectorAll('.action-open').forEach(link => {
    link.addEventListener('click', function () {
      const tr = this.closest('tr');
      const vid = tr?.dataset?.versionId;
      if (!vid) return;
      opened.add(String(vid));
      // small delay to allow browser to open new tab
      setTimeout(() => enableRowActions(tr), 250);
    });
  });

  // Approve: block if not opened
  document.querySelectorAll('.action-form-approve').forEach(form => {
    form.addEventListener('submit', function (e) {
      const tr = this.closest('tr');
      const vid = tr?.dataset?.versionId;
      if (!vid || !opened.has(String(vid))) {
        e.preventDefault();
        alert('Silakan buka dokumen (Open) terlebih dahulu sebelum menyetujui.');
        return false;
      }
      // otherwise allow submit (normal POST form)
    });
  });

  // Reject: open modal only if opened
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
      document.getElementById('rejectDocCode').textContent = docCode ? `(${docCode})` : '';
      document.getElementById('reject_reason').value = '';
      showRejectModal();
    });
  });

  // Modal controls
  window.showRejectModal = function () {
    const el = document.getElementById('rejectModal');
    if (!el) return;
    el.style.display = 'flex';
    el.setAttribute('aria-hidden', 'false');
    // focus textarea after modal shown
    setTimeout(() => document.getElementById('reject_reason').focus(), 50);
  };
  window.closeRejectModal = function () {
    const el = document.getElementById('rejectModal');
    if (!el) return;
    el.style.display = 'none';
    el.setAttribute('aria-hidden', 'true');
  };

  // Submit reject via fetch
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
      btn.textContent = 'Submitting...';

      fetch(`${baseApprovalUrl}/${encodeURIComponent(vid)}/reject`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ notes: reason })
      })
      .then(async res => {
        let payload = {};
        const ct = res.headers.get('content-type') || '';
        if (ct.includes('application/json')) payload = await res.json();
        else payload = { success: res.ok };

        if (res.ok && (payload.success === undefined || payload.success)) {
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
        btn.textContent = 'Submit Reject';
      });
    });
  }

  // Select All checkbox
  const selectAll = document.getElementById('selectAll');
  if (selectAll) {
    selectAll.addEventListener('change', (e) => {
      document.querySelectorAll('.select-version').forEach(ch => ch.checked = e.target.checked);
    });
  }

  // Compare selected: require >=2 from same document
  const compareBtn = document.getElementById('compareSelectedBtn');
  if (compareBtn) {
    compareBtn.addEventListener('click', () => {
      const checked = Array.from(document.querySelectorAll('.select-version:checked'));
      if (checked.length < 2) {
        alert('Pilih minimal 2 versi dari dokumen yang sama untuk membandingkan.');
        return;
      }

      const docs = checked.map(c => c.dataset.doc);
      const firstDoc = docs[0];
      const allSame = docs.every(d => d === firstDoc);
      if (!allSame) {
        alert('Silakan pilih versi yang berasal dari DOKUMEN yang SAMA untuk membandingkan.');
        return;
      }

      const versionIds = checked.map(c => c.value);
      // build url: /documents/{docId}/compare?versions[]=7&versions[]=8
      const docId = firstDoc;
      const base = "{{ rtrim(url('/documents'), '/') }}";
      const url = new URL(`${base}/${encodeURIComponent(docId)}/compare`, window.location.origin);
      versionIds.forEach(id => url.searchParams.append('versions[]', id));
      window.open(url.toString(), '_blank', 'noopener');
    });
  }

  // Escape key closes modal
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      const modal = document.getElementById('rejectModal');
      if (modal && modal.style.display === 'flex') closeRejectModal();
    }
  });

})();
</script>
@endsection
