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

    <section class="table-responsive card-section card-inner" aria-labelledby="pendingTableTitle" style="margin-top:1rem;">
        <h2 id="pendingTableTitle" class="sr-only">Pending approvals</h2>

        @php
            // canonical rows variable + current user + role helpers
            $rows = $rows ?? $pending ?? $pendingVersions ?? collect();
            $currentUser = auth()->user();

            $userHasRole = function ($user, $roleName) {
                if (! $user) return false;
                $rLower = strtolower($roleName);

                if (method_exists($user, 'hasRole')) {
                    try { if ($user->hasRole($roleName)) return true; } catch (\Throwable $e) {}
                }
                if (method_exists($user, 'hasAnyRole')) {
                    try { if ($user->hasAnyRole([$roleName])) return true; } catch (\Throwable $e) {}
                }
                if (method_exists($user, 'getRoleNames')) {
                    try {
                        $names = $user->getRoleNames()->map(fn($n)=>strtolower((string)$n))->toArray();
                        if (in_array($rLower, $names, true)) return true;
                    } catch (\Throwable $e) {}
                }
                if (method_exists($user, 'roles')) {
                    try {
                        $names = $user->roles()->pluck('name')->map(fn($n)=>strtolower((string)$n))->toArray();
                        if (in_array($rLower, $names, true)) return true;
                    } catch (\Throwable $e) {}
                }
                if (isset($user->roles) && is_iterable($user->roles)) {
                    try {
                        $names = collect($user->roles)->pluck('name')->map(fn($n)=>strtolower((string)$n))->toArray();
                        if (in_array($rLower, $names, true)) return true;
                    } catch (\Throwable $e) {}
                }

                $whitelist = ['direktur@peroniks.com', 'adminqc@peroniks.com'];
                if (! empty($user->email) && in_array(strtolower($user->email), $whitelist, true)) return true;

                return false;
            };

            $isAdmin = $userHasRole($currentUser, 'admin');
            $isDirector = $userHasRole($currentUser, 'director');
            $isMr = $userHasRole($currentUser, 'mr');
            $isKabag = $userHasRole($currentUser, 'kabag');

            $normalizeStage = fn($s) => strtoupper(trim((string)($s ?? '')));
        @endphp

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
                @forelse($rows as $v)
                    @php
                        $doc = $v->document ?? null;
                        $docId = $doc->id ?? $v->document_id ?? null;
                        $docCode = $doc->doc_code ?? $v->document_code ?? '-';
                        $title = $doc->title ?? $v->title ?? '-';
                        $dept = optional($doc->department)->code ?? optional($doc->department)->name ?? '-';
                        $versionLabel = $v->version_label ?? $v->label ?? '-';
                        $creator = optional($v->creator ?? (isset($v->created_by_user) ? $v->created_by_user : null));
                        $creatorDisplay = $creator->email ?? $creator->name ?? '-';
                        $when = optional($v->created_at)->format('Y-m-d') ?? '-';

                        $approvalStage = $normalizeStage($v->approval_stage ?? '');
                        $status = strtolower((string)($v->status ?? ''));

                        $statusOkForMr = in_array($status, ['submitted','pending','draft'], true);
                        $statusOkForDirector = in_array($status, ['to_dir','submitted'], true);

                        $canMrForward = $isMr && $approvalStage === 'MR' && $statusOkForMr;
                        $canDirectorApprove = ($isDirector || $isAdmin) && (str_starts_with($approvalStage, 'DIR') || $approvalStage === 'DIRECTOR') && $statusOkForDirector;
                        $canReject = $canMrForward || $canDirectorApprove || ($isKabag && $approvalStage === 'KABAG');
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

                        {{-- ACTIONS: Open / Approve / Reject --}}
                        <td>
                            <div class="action-buttons" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                                {{-- Open --}}
                                @if($v->id)
                                    <a href="{{ route('versions.show', $v->id) }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm action-open" aria-label="Open version {{ e($versionLabel) }}">Open</a>
                                @else
                                    <button class="btn btn-outline-primary btn-sm" disabled>Open</button>
                                @endif

                                {{-- Approve (green) - MR and Director/Admin both see a green Approve button when allowed --}}
                                @if($canMrForward || $canDirectorApprove)
                                    <form method="POST" action="{{ route('approval.approve', $v->id) }}" class="d-inline-block" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-success btn-sm" title="Approve">
                                            Approve
                                        </button>
                                    </form>
                                @else
                                    <button class="btn btn-success btn-sm" disabled>Approve</button>
                                @endif

                                {{-- Reject --}}
                                @if($canReject)
                                    <button
                                        type="button"
                                        class="btn btn-danger btn-sm btn-reject"
                                        data-version-id="{{ e($v->id) }}"
                                        data-doc-code="{{ e($docCode) }}"
                                        aria-label="Reject version {{ e($versionLabel) }}">
                                        Reject
                                    </button>
                                @else
                                    <button class="btn btn-danger btn-sm" disabled>Reject</button>
                                @endif

                                {{-- small info --}}
                                @if(!empty($v->mr_viewed_at))
                                    <span class="text-success" style="font-size:12px;margin-left:6px;" title="Viewed by MR">Viewed</span>
                                @else
                                    <span class="small-muted" title="Menunggu MR membuka dokumen" style="font-size:12px;margin-left:6px;">(Waiting MR)</span>
                                @endif
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

        <div class="d-flex justify-content-end" style="margin-top:12px">
            @if(method_exists($rows, 'links')) {!! $rows->links() !!} @endif
        </div>
    </section>
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
  const baseApprovalUrl = "{{ rtrim(url('/approval'), '/') }}";
  const csrfToken = "{{ csrf_token() }}";

  // Approve forms: allow normal submission (optionally you can add confirm)
  document.querySelectorAll('form[action^="{{ url('/approval') }}"]').forEach(form => {
    form.addEventListener('submit', function (e) {
      // uncomment to enable a confirm prompt:
      // if (!confirm('Yakin ingin meneruskan / menyetujui versi ini?')) e.preventDefault();
    });
  });

  // Reject buttons open modal
  document.querySelectorAll('.btn-reject').forEach(btn => {
    btn.addEventListener('click', function () {
      const vid = this.getAttribute('data-version-id');
      const docCode = this.getAttribute('data-doc-code') || '';
      document.getElementById('reject_version_id').value = vid || '';
      document.getElementById('rejectDocCode').textContent = docCode ? `(${docCode})` : '';
      document.getElementById('reject_reason').value = '';
      showRejectModal();
    });
  });

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

  // Submit reject via fetch to {baseApprovalUrl}/{id}/reject (POST)
  const rejectBtn = document.getElementById('rejectSubmitBtn');
  if (rejectBtn) {
    rejectBtn.addEventListener('click', function () {
      const btn = this;
      const vid = document.getElementById('reject_version_id').value;
      const reason = document.getElementById('reject_reason').value.trim();

      if (!reason) { alert('Alasan reject wajib diisi.'); return; }
      if (!vid) { alert('Version ID tidak terdeteksi.'); return; }

      btn.disabled = true;
      const prevText = btn.textContent;
      btn.textContent = 'Submitting...';

      fetch(`${baseApprovalUrl}/${encodeURIComponent(vid)}/reject`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ rejected_reason: reason })
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
        btn.textContent = prevText;
      });
    });
  }

  // Select all
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
