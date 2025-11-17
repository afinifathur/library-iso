{{-- resources/views/approval/index.blade.php --}}
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
                    $rows = $pendingVersions ?? $pending ?? collect();

                    // compute current user roles once (robust)
                    $currentUser = auth()->user();
                    $userRoleNames = [];
                    if ($currentUser) {
                        if (method_exists($currentUser, 'getRoleNames')) {
                            try { $userRoleNames = $currentUser->getRoleNames()->map(fn($n)=>strtolower($n))->toArray(); } catch (\Throwable $e) {}
                        } elseif (method_exists($currentUser, 'roles')) {
                            try { $userRoleNames = $currentUser->roles()->pluck('name')->map(fn($n)=>strtolower($n))->toArray(); } catch (\Throwable $e) {}
                        } elseif (isset($currentUser->roles) && is_iterable($currentUser->roles)) {
                            $userRoleNames = collect($currentUser->roles)->pluck('name')->map(fn($n)=>strtolower($n))->toArray();
                        }
                    }
                    $globalCanApprove = !empty($currentUser) && (in_array('mr', $userRoleNames, true) || in_array('director', $userRoleNames, true) || in_array('admin', $userRoleNames, true));
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

                        // determine if MR has viewed this version
                        $seenByMr = !empty($v->mr_viewed_at);
                        // row-level permission to approve/reject
                        $canApproveThis = $globalCanApprove && $seenByMr;
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
                            <div class="action-buttons" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                                <!-- Open -->
                                @if($v->id)
                                    <a href="{{ route('versions.show', $v->id) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm action-open" aria-label="Open version {{ e($versionLabel) }}">Open</a>
                                @else
                                    <button class="btn btn-outline-primary btn-sm" disabled>Open</button>
                                @endif

                                <!-- Approve (form) -->
                                <form method="POST" action="{{ route('approval.approve', $v->id) }}" class="d-inline-block action-form-approve" style="display:inline">
                                    @csrf
                                    <button type="submit"
                                            class="btn btn-success btn-sm btn-approve"
                                            @unless($canApproveThis) disabled aria-disabled="true" @endunless>
                                        Approve
                                    </button>
                                </form>

                                <!-- Reject -->
                                <button type="button"
                                        class="btn btn-danger btn-sm btn-reject"
                                        @unless($canApproveThis) disabled aria-disabled="true" @endunless
                                        data-version-id="{{ e($v->id) }}"
                                        data-doc-code="{{ e($docCode) }}"
                                        aria-label="Reject version {{ e($versionLabel) }}">
                                    Reject
                                </button>

                                {{-- Indicator when mr_viewed_at is missing --}}
                                @unless($seenByMr)
                                    <span class="small-muted" title="Menunggu MR membuka dokumen" style="font-size:12px;margin-left:6px;">(Waiting MR)</span>
                                @else
                                    <span class="text-success" style="font-size:12px;margin-left:6px;" title="Viewed by MR">Viewed</span>
                                @endunless
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

{{-- Reject Modal --}}
<div id="rejectModal" class="modal-overlay" aria-hidden="true" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,0.35);z-index:2000;">
  <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="rejectModalTitle" style="background:#fff;padding:16px;border-radius:8px;max-width:520px;width:90%;">
    <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center">
      <h3 id="rejectModalTitle" style="margin:0">Alasan Reject <span id="rejectDocCode" class="fw-bold"></span></h3>
      <button class="modal-close" type="button" onclick="closeRejectModal()" aria-label="Close">×</button>
    </div>

    <div class="modal-body" style="margin-top:12px;">
      <form id="rejectForm" onsubmit="return false;">
        <div class="form-row">
          <label for="reject_reason">Alasan reject <small class="text-muted">(wajib diisi)</small></label>
          <textarea id="reject_reason" name="reason" class="form-textarea" rows="6" required style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;"></textarea>
        </div>
        <input type="hidden" id="reject_version_id" name="version_id" value="">
      </form>
    </div>

    <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
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

  // helpers
  const enableRowActions = (tr) => {
    if (!tr) return;
    tr.querySelectorAll('.btn-approve, .btn-reject').forEach(btn => {
      btn.removeAttribute('disabled');
      btn.removeAttribute('aria-disabled');
    });
  };

  // When "Open" is clicked (opens in new tab), enable approve/reject buttons for that row immediately.
  document.querySelectorAll('.action-open').forEach(link => {
    link.addEventListener('click', function () {
      const tr = this.closest('tr');
      if (!tr) return;
      // small delay to avoid race with tab opening
      setTimeout(() => enableRowActions(tr), 250);
    });
  });

  // Approve: submit form normally. For extra safety, prevent if buttons still disabled
  document.querySelectorAll('.action-form-approve').forEach(form => {
    form.addEventListener('submit', function (e) {
      const btn = this.querySelector('.btn-approve');
      if (btn && btn.disabled) {
        e.preventDefault();
        alert('Anda tidak boleh menyetujui sebelum MR membuka dokumen (Viewed).');
        return false;
      }
      // allow normal POST
    });
  });

  // Reject: open modal only if button enabled
  document.querySelectorAll('.btn-reject').forEach(btn => {
    btn.addEventListener('click', function () {
      if (this.disabled) {
        alert('Anda tidak boleh menolak sebelum MR membuka dokumen (Viewed).');
        return;
      }
      const vid = this.getAttribute('data-version-id');
      const docCode = this.getAttribute('data-doc-code') || '';
      document.getElementById('reject_version_id').value = vid || '';
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
    setTimeout(() => document.getElementById('reject_reason').focus(), 50);
  };
  window.closeRejectModal = function () {
    const el = document.getElementById('rejectModal');
    if (!el) return;
    el.style.display = 'none';
    el.setAttribute('aria-hidden', 'true');
  };

  // Submit reject via fetch to approval.reject
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
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
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

  // Close modal on Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      const modal = document.getElementById('rejectModal');
      if (modal && modal.style.display === 'flex') closeRejectModal();
    }
  });
})();
</script>
@endsection
