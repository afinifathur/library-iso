{{-- resources/views/layouts/iso.blade.php --}}
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'ISO Library')</title>

  <link rel="stylesheet" href="{{ asset('css/style.css') }}">

  <style>
    /* -------------------------
       Minimal layout styles
       ------------------------- */
    :root {
      --accent: #1e88ff;
      --muted: #6b7280;
      --bg: #f7fbff;
      --card-radius: 12px;
    }

    html,body { height:100%; margin:0; font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; color:#0f172a; background:var(--bg); }

    .card { border-radius:var(--card-radius); box-shadow:0 6px 18px rgba(20,40,70,0.05); background:#fff; }

    /* header */
    .site-header {
      display:flex; align-items:center; justify-content:space-between;
      padding:12px 18px; border-bottom:1px solid #eef6fb; background:#fff; z-index:50;
    }
    .brand { display:flex; align-items:center; gap:12px; text-decoration:none; color:inherit; }
    .logo-img { width:46px; height:46px; border-radius:8px; object-fit:cover; }
    .brand-text .title { font-weight:700; font-size:16px; }
    .brand-text .sub { font-size:12px; color:var(--muted); }

    .main-nav { display:flex; gap:14px; align-items:center; }
    .main-nav a { padding:8px 12px; border-radius:8px; color:var(--accent); text-decoration:none; font-weight:500; }
    .main-nav a.active { background:#eef7ff; font-weight:600; }

    /* Buttons */
    .btn { border-radius:8px; padding:.45rem .75rem; display:inline-flex; align-items:center; gap:.4rem; cursor:pointer; border:1px solid transparent; background:transparent; }
    .btn-sm { padding:.25rem .6rem; font-size:.85rem; }
    .btn-primary { background:var(--accent); border-color:var(--accent); color:#fff; }
    .btn-primary:hover { filter:brightness(.95); }

    .btn-muted { background:#f3f6fb; border-color:transparent; color:#0f172a; }

    .table { width:100%; border-collapse:collapse; }
    .table thead th { background:#f8fafc; border-bottom:1px solid #e6eef6; padding:.6rem .75rem; text-align:left; }
    .table td, .table th { padding:.6rem .75rem; vertical-align:middle; }

    .page-card { max-width:1200px; margin:0 auto; }

    .footer-small { margin-top:18px; font-size:13px; color:var(--muted); text-align:center; padding:10px 0; }

    /* Dropdown menu appended to body */
    .dropdown-menu {
      display:none;
      background:#fff;
      border:1px solid #d1d5db;
      border-radius:6px;
      min-width:160px;
      box-shadow:0 6px 20px rgba(15,23,42,0.12);
      position:fixed;
      z-index:99999;
    }
    .dropdown-menu button {
      width:100%; text-align:left; padding:10px 14px; border:none; background:transparent; font-size:14px; cursor:pointer;
    }
    .dropdown-menu button:hover { background:#eef7ff; color:var(--accent); }

    /* Modal helper */
    .modal-overlay { display:none; position:fixed; inset:0; align-items:center; justify-content:center; background:rgba(0,0,0,0.35); z-index:99998; }
    .modal-card { background:#fff; border-radius:8px; padding:18px; width:90%; max-width:680px; }

    /* small responsive tweaks */
    @media (max-width:720px) {
      .main-nav { gap:8px; }
      .brand-text .title { font-size:15px; }
    }

    /* small visual when row is opened */
    .iso-opened-row { background: #f7fff4 !important; }
  </style>
</head>

<body>
  <div class="app-container" style="min-height:100vh;">

    {{-- NAVBAR (hidden on login route) --}}
    @if(!Request::is('login') && !Route::is('login'))
      <header class="site-header">
        {{-- Brand --}}
        <a href="{{ url('/') }}" class="brand" aria-label="Document Control — Management System">
          <img src="{{ asset('images/logo.png') }}" class="logo-img" alt="Logo">
          <div class="brand-text">
            <div class="title">Document Control</div>
            <div class="sub">Management System</div>
          </div>
        </a>

        {{-- Navigation --}}
        <nav class="main-nav" role="navigation" aria-label="Main navigation">
          <a href="{{ route('dashboard.index') }}" class="{{ request()->routeIs('dashboard.*') ? 'active' : '' }}">Dashboard</a>
          <a href="{{ route('documents.index') }}" class="{{ request()->routeIs('documents.*') ? 'active' : '' }}">Documents</a>
          <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active' : '' }}">Categories</a>
          <a href="{{ route('departments.index') }}" class="{{ request()->routeIs('departments.*') ? 'active' : '' }}">Departments</a>

          {{-- Audit Log — ONLY MR & DIRECTOR --}}
          @auth
            @php
              $u = auth()->user();
              $showAudit = false;
              if ($u) {
                  if (method_exists($u, 'hasAnyRole')) {
                      try { $showAudit = $u->hasAnyRole(['mr','director']); } catch (\Throwable $e) { $showAudit = false; }
                  } else {
                      try {
                          if (method_exists($u, 'roles')) {
                              $roles = (array) optional($u->roles()->pluck('name'))->toArray();
                              $showAudit = count(array_intersect($roles, ['mr','director'])) > 0;
                          }
                      } catch (\Throwable $e) { $showAudit = false; }
                  }
              }
            @endphp

            @if($showAudit && Route::has('audit.index'))
              <a href="{{ route('audit.index') }}" class="{{ request()->routeIs('audit.*') ? 'active' : '' }}">Audit Log</a>
            @endif
          @endauth

          {{-- Drafts --}}
          @auth
            @php
              $u = auth()->user();
              $showDrafts = false;
              if ($u) {
                  if (method_exists($u, 'hasAnyRole')) {
                      try { $showDrafts = $u->hasAnyRole(['kabag','admin','mr','director']); } catch (\Throwable $e) { $showDrafts = false; }
                  } else {
                      try {
                          if (method_exists($u, 'roles')) {
                              $roles = (array) optional($u->roles()->pluck('name'))->toArray();
                              $showDrafts = count(array_intersect($roles, ['kabag','admin','mr','director'])) > 0;
                          }
                      } catch (\Throwable $e) { $showDrafts = false; }
                  }
              }
            @endphp

            @if($showDrafts && Route::has('drafts.index'))
              <a href="{{ route('drafts.index') }}" class="{{ request()->routeIs('drafts.*') ? 'active' : '' }}">Drafts</a>
            @endif
          @endauth

          {{-- Approval Queue --}}
          @auth
            @php
              $u = auth()->user();
              $showApproval = false;
              if ($u) {
                  if (method_exists($u, 'hasAnyRole')) {
                      try { $showApproval = $u->hasAnyRole(['mr','director']); } catch (\Throwable $e) { $showApproval = false; }
                  } else {
                      try {
                          if (method_exists($u, 'roles')) {
                              $roles = (array) optional($u->roles()->pluck('name'))->toArray();
                              $showApproval = count(array_intersect($roles, ['mr','director'])) > 0;
                          }
                      } catch (\Throwable $e) { $showApproval = false; }
                  }
              }
            @endphp

            @if($showApproval && Route::has('approval.index'))
              <a href="{{ route('approval.index') }}" class="{{ request()->routeIs('approval.*') ? 'active' : '' }}">Approval Queue</a>
            @endif
          @endauth

          {{-- Recycle --}}
          @auth
            @php
              $u = auth()->user();
              $showRecycle = false;
              if ($u) {
                  if (method_exists($u, 'hasAnyRole')) {
                      try { $showRecycle = $u->hasAnyRole(['mr','director','admin']); } catch (\Throwable $e) { $showRecycle = false; }
                  } else {
                      try {
                          if (method_exists($u, 'roles')) {
                              $roles = (array) optional($u->roles()->pluck('name'))->toArray();
                              $showRecycle = count(array_intersect($roles, ['mr','director','admin'])) > 0;
                          }
                      } catch (\Throwable $e) { $showRecycle = false; }
                  }
              }
            @endphp

            @if($showRecycle && Route::has('recycle.index'))
              <a href="{{ route('recycle.index') }}" class="{{ request()->routeIs('recycle.*') ? 'active' : '' }}" title="Recycle Bin" aria-label="Recycle Bin" style="display:inline-flex;align-items:center;gap:8px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <rect x="3" y="6" width="18" height="14" rx="2" fill="#e6f0ff"/>
                  <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" stroke="#1e88ff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M8 10v6M12 10v6M16 10v6" stroke="#1e88ff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                  <rect x="7" y="4" width="10" height="2" rx="1" fill="#1e88ff"/>
                </svg>
              </a>
            @endif
          @endauth

          {{-- USER DROPDOWN --}}
          @auth
            <div class="dropdown" style="margin-left:12px;">
              @php
                $email = Auth::user()->email ?? '';
                $username = $email ? explode('@', strtolower($email))[0] : (Auth::user()->name ?? 'user');
              @endphp

              <button class="dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">
                {{ $username }} ▼
              </button>
            </div>
          @endauth
        </nav>
      </header>
    @endif

    {{-- Flash messages (non-JS fallback) --}}
    <div class="container-messages" style="max-width:1200px;margin:12px auto;">
      @if(session('success'))
        <div style="margin-bottom:12px;padding:10px;border-radius:8px;background:#ecfdf5;color:#064e3b;">{{ session('success') }}</div>
      @endif
      @if(session('error'))
        <div style="margin-bottom:12px;padding:10px;border-radius:8px;background:#fff1f2;color:#9f1239;">{{ session('error') }}</div>
      @endif
      @if(session('info'))
        <div style="margin-bottom:12px;padding:10px;border-radius:8px;background:#eff6ff;color:#1e3a8a;">{{ session('info') }}</div>
      @endif
    </div>

    {{-- MAIN CONTENT --}}
    <main class="main-area" style="padding:18px;">
      <div class="page-card">
        @yield('content')
      </div>

      <div class="footer-small">&copy; {{ date('Y') }} ISO Library — Peroni Karya Sentra</div>
    </main>
  </div>

  {{-- Hidden logout form --}}
  <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">@csrf</form>

  {{-- allow pages to push scripts (backward-compatible) --}}
  @yield('scripts')
  @stack('scripts')

  {{-- SweetAlert2 --}}
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  {{-- Dropdown, SweetAlert flashes, and global approval helper --}}
  <script>
  (function () {
    // --------- Dropdown menu (appended to body) ----------
    document.addEventListener('DOMContentLoaded', function () {
      const dropdown = document.querySelector('.dropdown');
      if (dropdown) {
        const toggle = dropdown.querySelector('.dropdown-toggle');

        const menu = document.createElement('div');
        menu.className = 'dropdown-menu';
        menu.style.display = 'none';

        // Profile link
        @if(Route::has('profile.edit'))
          const profileBtn = document.createElement('button');
          profileBtn.type = 'button';
          profileBtn.textContent = 'Profile';
          profileBtn.addEventListener('click', function () { window.location = @json(route('profile.edit')); });
          menu.appendChild(profileBtn);
        @endif

        // Logout
        const logoutBtn = document.createElement('button');
        logoutBtn.type = 'button';
        logoutBtn.textContent = 'Logout';
        logoutBtn.addEventListener('click', function (e) {
          e.preventDefault();
          const logoutForm = document.getElementById('logout-form');
          if (logoutForm) logoutForm.submit();
        });
        menu.appendChild(logoutBtn);

        document.body.appendChild(menu);

        function positionMenu() {
          const rect = toggle.getBoundingClientRect();
          menu.style.left = Math.max(8, rect.right - menu.offsetWidth) + 'px';
          menu.style.top = (rect.bottom + 8) + 'px';

          const maxRight = window.innerWidth - 8;
          const menuRight = parseFloat(menu.style.left) + menu.offsetWidth;
          if (menuRight > maxRight) menu.style.left = Math.max(8, maxRight - menu.offsetWidth) + 'px';

          const menuBottom = rect.bottom + 8 + menu.offsetHeight;
          if (menuBottom > window.innerHeight - 8) {
            const above = rect.top - 8 - menu.offsetHeight;
            if (above > 8) menu.style.top = above + 'px';
          }
        }

        toggle.addEventListener('click', function (e) {
          e.stopPropagation();
          if (menu.style.display === 'block') { menu.style.display = 'none'; }
          else { positionMenu(); menu.style.display = 'block'; }
        });

        document.addEventListener('click', function (e) {
          if (!menu.contains(e.target) && !toggle.contains(e.target)) menu.style.display = 'none';
        });

        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') menu.style.display = 'none'; });

        window.addEventListener('resize', function () { if (menu.style.display === 'block') positionMenu(); }, { passive: true });
        window.addEventListener('scroll', function () { if (menu.style.display === 'block') positionMenu(); }, { passive: true });
      }

      // --------- SweetAlert flash messages ----------
      @if(session('success'))
        Swal.fire({ icon:'success', title:'Berhasil', text:@json(session('success')), toast:true, position:'top-end', showConfirmButton:false, timer:2500, timerProgressBar:true });
      @endif
      @if(session('error'))
        Swal.fire({ icon:'error', title:'Perhatian', text:@json(session('error')), confirmButtonText:'Tutup' });
      @endif
      @if(session('pending'))
        Swal.fire({
          icon:'warning', title:'Pending', text:@json(session('pending')), showCancelButton:true,
          confirmButtonText:'Lihat Antrian', cancelButtonText:'Tutup'
        }).then(result => { if (result.isConfirmed) window.location = @json(route('approval.index')); });
      @endif
    });

    // --------- Global approval helper ----------
    (function () {
      // small helper to enable approve/reject on a row when an "Open" link is clicked
      function enableRowForVersion(vid) {
        if (!vid) return;
        document.querySelectorAll('tr[data-version-id="'+vid+'"]').forEach(tr => {
          tr.classList.add('iso-opened-row');
          tr.querySelectorAll('.btn-approve, .btn-reject').forEach(btn => { btn.removeAttribute('disabled'); btn.removeAttribute('aria-disabled'); });
          tr.querySelectorAll('.select-version').forEach(cb => cb.disabled = false);
        });
      }

      function attachOpenHandlers() {
        document.querySelectorAll('.action-open').forEach(link => {
          if (link.__isoAttached) return;
          link.__isoAttached = true;

          link.addEventListener('click', function () {
            try {
              const tr = this.closest('tr');
              const vid = tr?.dataset?.versionId || this.dataset?.versionId;
              if (!vid) return;
              try { localStorage.setItem('iso_opened_version_' + vid, '1'); } catch(e){}
              enableRowForVersion(String(vid));
              try {
                if (window.opener && !window.opener.closed) {
                  window.opener.postMessage({ iso_action:'version_opened', version_id:String(vid) }, '*');
                }
              } catch(e){}
            } catch(e){ console.warn('iso:open error', e); }
            // allow navigation (open in new tab)
          }, { passive:true });

          // support middle-click
          link.addEventListener('auxclick', function (ev) { if (ev.button === 1) {
            const tr = this.closest('tr');
            const vid = tr?.dataset?.versionId || this.dataset?.versionId;
            if (vid) { try { localStorage.setItem('iso_opened_version_' + vid, '1'); } catch(e){}; enableRowForVersion(String(vid)); }
          }}, { passive:true });
        });
      }

      // Listen for postMessage from child tab
      window.addEventListener('message', function (ev) {
        try {
          const d = ev.data || {};
          if (d && d.iso_action === 'version_opened' && d.version_id) enableRowForVersion(String(d.version_id));
        } catch (e) {}
      });

      // Listen for storage events
      window.addEventListener('storage', function (ev) {
        if (!ev.key) return;
        if (ev.key.startsWith('iso_opened_version_') && ev.newValue) {
          const vid = ev.key.replace('iso_opened_version_', '');
          enableRowForVersion(vid);
        }
      });

      // Guard approve forms: require localStorage flag
      function attachApproveGuards() {
        document.querySelectorAll('form.action-form-approve').forEach(form => {
          if (form.__isoGuard) return;
          form.__isoGuard = true;
          form.addEventListener('submit', function (e) {
            try {
              const tr = this.closest('tr');
              const vid = tr?.dataset?.versionId;
              if (!vid) return;
              const opened = !!localStorage.getItem('iso_opened_version_' + vid);
              if (!opened) { e.preventDefault(); alert('Silakan buka dokumen (Open) terlebih dahulu sebelum menyetujui.'); return false; }
            } catch(err){ console.error(err); }
          });
        });
      }

      // Initialize
      function init() {
        attachOpenHandlers();
        attachApproveGuards();

        // apply persisted flags
        document.querySelectorAll('tr[data-version-id]').forEach(tr => {
          const v = tr.dataset.versionId;
          try { if (localStorage.getItem('iso_opened_version_' + v)) enableRowForVersion(String(v)); } catch(e){}
        });
      }

      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
      else init();

      // expose minimal API for debugging
      window.__isoApprovalHelper = { enableRowForVersion, attachOpenHandlers, attachApproveGuards };
    })();

  })();
  </script>

</body>
</html>
