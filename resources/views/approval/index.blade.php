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
                        $creator = optional($v->creator ?? null);
                        $creatorDisplay = $creator->email ?? $creator->name ?? $v->created_by ?? '-';
                        $when = $v->created_at ? $v->created_at->format('Y-m-d') : '-';
                        $versionIdAttr = e((string) ($v->id ?? ''));
                        $docIdAttr = e((string) ($docId ?? ''));
                    @endphp

                    <tr data-version-id="{{ $versionIdAttr }}" data-doc-id="{{ $docIdAttr }}">
                        <td>
                            <input class="select-version" type="checkbox"
                                   value="{{ $versionIdAttr }}"
                                   data-doc="{{ $docIdAttr }}"
                                   aria-label="Select version {{ e($versionLabel) }}"
                                   disabled>
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
                                {{-- Open (no inline JS; data attributes used) --}}
                                @if(!empty($v->id))
                                    <a href="{{ route('versions.show', $v->id) }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="btn btn-outline-primary btn-sm action-open"
                                       aria-label="Open version {{ e($versionLabel) }}"
                                       data-version-id="{{ $versionIdAttr }}">
                                        Open
                                    </a>
                                @else
                                    <button class="btn btn-outline-primary btn-sm" disabled>Open</button>
                                @endif

                                {{-- Compare (single) --}}
                                @if($docId)
                                    <a href="{{ route('documents.compare', $docId) }}?v2={{ $v->id }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="btn btn-outline-secondary btn-sm action-compare"
                                       aria-label="Compare version {{ e($versionLabel) }}">
                                        Compare
                                    </a>
                                @else
                                    <button class="btn btn-outline-secondary btn-sm" disabled>Compare</button>
                                @endif

                                {{-- Approve (form) - tombol sekarang default AKTIF --}}
                                <form method="POST"
                                      action="{{ route('approval.approve', $v->id) }}"
                                      class="d-inline-block action-form-approve"
                                      style="display:inline">
                                    @csrf
                                    <button type="submit"
                                            class="btn btn-success btn-sm btn-approve">
                                        Approve
                                    </button>
                                </form>

                                {{-- Reject - tombol sekarang default AKTIF --}}
                                <button type="button"
                                        class="btn btn-danger btn-sm btn-reject"
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

{{-- Reject Modal --}}
<div id="rejectModal"
     class="modal-overlay"
     aria-hidden="true"
     style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;background:rgba(0,0,0,0.35);z-index:9999;">
  <div class="modal-card"
       role="dialog"
       aria-modal="true"
       aria-labelledby="rejectModalTitle"
       style="background:#fff;border-radius:8px;width:90%;max-width:680px;padding:18px;">
    <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center">
      <h3 id="rejectModalTitle" style="margin:0">
          Alasan Reject <span id="rejectDocCode" class="fw-bold"></span>
      </h3>
      <button class="modal-close"
              type="button"
              onclick="closeRejectModal()"
              aria-label="Close"
              style="background:none;border:0;font-size:22px;line-height:1;cursor:pointer">
        ×
      </button>
    </div>

    <div class="modal-body" style="margin-top:12px;">
      <form id="rejectForm" onsubmit="return false;">
        <div class="form-row">
          <label for="reject_reason">
              Alasan reject <small class="text-muted">(wajib diisi)</small>
          </label>
          <textarea id="reject_reason"
                    name="rejected_reason"
                    class="form-textarea"
                    rows="6"
                    required
                    style="width:100%;padding:8px;border:1px solid #e6eef8;border-radius:6px;"></textarea>
        </div>
        <input type="hidden" id="reject_version_id" name="version_id" value="">
      </form>
    </div>

    <div class="modal-footer"
         style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px;">
      <button class="btn btn-muted" type="button" onclick="closeRejectModal()">Batal</button>
      <button id="rejectSubmitBtn" class="btn btn-danger" type="button">Submit Reject</button>
    </div>
  </div>
</div>
@endsection

@section('footerscripts')
<script>
(() => {
  // ---------- config (injected by blade) ----------
  const BASE_APPROVAL_URL = "{{ rtrim(url('/approval'), '/') }}";
  const BASE_DOCUMENTS_URL = "{{ rtrim(url('/documents'), '/') }}";
  const CSRF_TOKEN = "{{ csrf_token() }}";

  // ---------- helpers ----------
  const qs = (sel, ctx = document) => ctx.querySelector(sel);
  const qsa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

  function markAttached(el, key) {
    if (!el) return false;
    if (el.dataset[key]) return false;
    el.dataset[key] = '1';
    return true;
  }

  // Enable action buttons for a row
  function enableRowActions(tr) {
    if (!tr) return;
    tr.querySelectorAll('.btn-approve, .btn-reject').forEach(btn => {
      btn.removeAttribute('disabled');
      btn.removeAttribute('aria-disabled');
    });
    tr.querySelectorAll('.select-version').forEach(cb => cb.disabled = false);
    tr.classList.add('iso-opened-row');
  }

  function enableAllRows() {
    qsa('tr[data-version-id]').forEach(enableRowActions);
  }

  function enableByVersionId(vid) {
    if (!vid) return;
    qsa(`tr[data-version-id="${vid}"]`).forEach(enableRowActions);
  }

  // Persist open state (still kept, optional)
  function persistOpened(vid) {
    if (!vid) return;
    try {
      localStorage.setItem('iso_opened_version_' + vid, '1');
    } catch (e) {}
  }

  function isPersistedOpened(vid) {
    try {
      return !!localStorage.getItem('iso_opened_version_' + vid);
    } catch (e) {
      return false;
    }
  }

  // ---------- Open handlers ----------
  function attachOpenHandlers() {
    qsa('.action-open').forEach(link => {
      if (!markAttached(link, 'isoOpenAttached')) return;
      link.addEventListener('click', function () {
        try {
          const tr = this.closest('tr');
          const vid = tr?.dataset?.versionId;
          if (!vid) return;
          persistOpened(vid);
          enableByVersionId(vid);
          try {
            if (window.opener && !window.opener.closed) {
              window.opener.postMessage({ iso_action: 'version_opened', version_id: vid }, '*');
            }
          } catch (e) {}
        } catch (e) {
          console.warn('open handler error', e);
        }
      }, { passive: true });

      link.addEventListener('auxclick', function (ev) {
        if (ev.button === 1) {
          try {
            const tr = this.closest('tr');
            const vid = tr?.dataset?.versionId;
            if (vid) {
              persistOpened(vid);
              enableByVersionId(vid);
            }
          } catch (e) {}
        }
      }, { passive: true });
    });
  }

  // ---------- Approve guard (DISABLED for MVP) ----------
  function attachApproveGuards() {
    qsa('form.action-form-approve').forEach(form => {
      if (!markAttached(form, 'isoApproveGuard')) return;
      form.addEventListener('submit', function () {
        // no guard: allow submit
      });
    });
  }

  // ---------- Reject modal (open + submit) ----------
  function attachRejectButtons() {
    qsa('.btn-reject').forEach(btn => {
      if (!markAttached(btn, 'isoRejectAttached')) return;
      btn.addEventListener('click', function () {
        const tr = this.closest('tr');
        const vid = tr?.dataset?.versionId;
        const docCode = this.getAttribute('data-doc-code') || '';
        if (!vid) {
          alert('Version ID tidak terdeteksi.');
          return;
        }

        const versionInput = qs('#reject_version_id');
        const reasonTextarea = qs('#reject_reason');
        const docLabel = qs('#rejectDocCode');

        if (versionInput) versionInput.value = vid;
        if (reasonTextarea) reasonTextarea.value = '';
        if (docLabel) docLabel.textContent = docCode ? `(${docCode})` : '';

        showRejectModal();
      });
    });

    window.showRejectModal = () => {
      const el = qs('#rejectModal');
      if (!el) return;
      el.style.display = 'flex';
      el.setAttribute('aria-hidden', 'false');
      setTimeout(() => {
        const t = qs('#reject_reason');
        if (t) t.focus();
      }, 50);
    };

    window.closeRejectModal = () => {
      const el = qs('#rejectModal');
      if (!el) return;
      el.style.display = 'none';
      el.setAttribute('aria-hidden', 'true');
    };

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        const modal = qs('#rejectModal');
        if (modal && modal.style.display === 'flex') {
          closeRejectModal();
        }
      }
    });

    const rejectSubmitBtn = qs('#rejectSubmitBtn');
    if (rejectSubmitBtn && markAttached(rejectSubmitBtn, 'isoRejectSubmit')) {
      rejectSubmitBtn.addEventListener('click', function () {
        const btn = this;
        const vid = qs('#reject_version_id')?.value;
        const reason = qs('#reject_reason')?.value?.trim();

        if (!vid) {
          alert('Version ID tidak terdeteksi.');
          return;
        }
        if (!reason) {
          alert('Alasan reject wajib diisi.');
          return;
        }

        btn.disabled = true;
        btn.textContent = 'Submitting...';

        fetch(`${BASE_APPROVAL_URL}/${encodeURIComponent(vid)}/reject`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN
          },
          body: JSON.stringify({ rejected_reason: reason })
        })
        .then(async res => {
          const ct = res.headers.get('content-type') || '';
          const payload = ct.includes('application/json') ? await res.json().catch(() => ({})) : {};
          if (res.ok) {
            closeRejectModal();
            alert(payload.message || 'Version berhasil direject.');
            window.location.reload();
          } else {
            throw new Error(payload.message || `Gagal menolak versi (${res.status})`);
          }
        })
        .catch(err => {
          console.error('Reject error', err);
          alert(err.message || 'Terjadi error saat menolak. Cek console.');
        })
        .finally(() => {
          btn.disabled = false;
          btn.textContent = 'Submit Reject';
        });
      });
    }
  }

  // ---------- Select All / Compare Selected ----------
  function attachSelectAll() {
    const selectAll = qs('#selectAll');
    if (!selectAll || !markAttached(selectAll, 'isoSelectAll')) return;
    selectAll.addEventListener('change', function () {
      qsa('.select-version').forEach(cb => {
        cb.checked = this.checked;
      });
    });
  }

  function attachCompareSelected() {
    const compareBtn = qs('#compareSelectedBtn');
    if (!compareBtn || !markAttached(compareBtn, 'isoCompareSelected')) return;

    compareBtn.addEventListener('click', function () {
      const checked = qsa('.select-version:checked');
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
      const docId = firstDoc;
      const url = new URL(
        `${BASE_DOCUMENTS_URL}/${encodeURIComponent(docId)}/compare`,
        window.location.origin
      );
      versionIds.forEach(id => url.searchParams.append('versions[]', id));
      window.open(url.toString(), '_blank', 'noopener');
    });
  }

  // ---------- initialization ----------
  function init() {
    attachOpenHandlers();
    attachApproveGuards();
    attachRejectButtons();
    attachSelectAll();
    attachCompareSelected();

    // MVP change: enable all rows immediately (no need to open first)
    enableAllRows();
    // still apply persisted flags visually if present
    qsa('tr[data-version-id]').forEach(tr => {
      const vid = tr.dataset.versionId;
      if (vid && isPersistedOpened(vid)) {
        enableByVersionId(vid);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // expose minimal API for debugging if needed
  window.__isoApproval = {
    enableByVersionId,
    persistOpened,
    applyPersistedFlags: () => qsa('tr[data-version-id]').forEach(tr => {
      const vid = tr.dataset.versionId;
      if (vid && isPersistedOpened(vid)) enableByVersionId(vid);
    })
  };

})();
</script>
@endsection
