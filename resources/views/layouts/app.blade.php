<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'ISO Library')</title>

  <link rel="stylesheet" href="{{ asset('css/style.css') }}">

  <style>
    /* General UI */
    .card { border-radius:12px; box-shadow:0 6px 18px rgba(20,40,70,0.05); }
    .table thead th { background:#f8fafc; border-bottom:1px solid #e6eef6; }
    .btn { border-radius:8px; padding:.45rem .75rem; }
    .btn-sm { padding:.25rem .6rem; font-size:.85rem; }
    .table td, .table th { vertical-align:middle; }

    .approval-actions .btn { margin-right:6px; }

    .login-card .card { border:none; }
    .login-card .form-control { border-radius:8px; padding:.6rem .75rem; }

    .btn-primary { background:#1e88ff; border-color:#1e88ff; color:#fff; }
    .btn-primary:hover { background:#166fe0; border-color:#166fe0; }

    /* Header */
    .site-header {
      display:flex;
      align-items:center;
      justify-content:space-between;
      padding:12px 18px;
      border-bottom:1px solid #eef6fb;
      background:#fff;
      position:relative;
      z-index:50;
    }

    .brand { display:flex; align-items:center; gap:12px; text-decoration:none; color:inherit; }
    .logo-img { width:46px; height:46px; border-radius:8px; object-fit:cover; }
    .brand-text .title { font-weight:700; font-size:16px; }
    .brand-text .sub { font-size:12px; color:#6b7280; }

    /* NAV LINKS */
    .main-nav { display:flex; gap:14px; align-items:center; }
    .main-nav a {
        padding:8px 12px;
        border-radius:8px;
        color:#0b5ed7;
        text-decoration:none;
        font-weight:500;
    }
    .main-nav a.active {
        background:#eef7ff;
        font-weight:600;
    }

    /* Dropdown (menu will be positioned fixed by JS) */
    .dropdown-toggle {
        background:transparent;
        border:1px solid transparent;
        padding:6px 10px;
        border-radius:8px;
        cursor:pointer;
        color:#0b5ed7;
        font-weight:500;
    }

    .dropdown-menu {
        display:none;
        background:#fff;
        border:1px solid #d1d5db;
        border-radius:6px;
        min-width:140px;
        box-shadow:0 6px 20px rgba(15,23,42,0.12);
        position:fixed;
        z-index:99999;
    }

    .dropdown-menu button {
        width:100%;
        text-align:left;
        padding:10px 14px;
        border:none;
        background:#fff;
        font-size:14px;
        cursor:pointer;
    }
    .dropdown-menu button:hover {
        background:#eef7ff;
        color:#0b5ed7;
    }

    .footer-small { margin-top:18px; font-size:13px; color:#6b7280; text-align:center; padding:10px 0; }

    .app-container { min-height:100vh; background:#f7fbff; }
    .main-area { padding:18px; }
    .page-card { max-width:1200px; margin:0 auto; }

    /* small responsive tweaks */
    @media (max-width:720px) {
      .main-nav { gap:8px; }
      .brand-text .title { font-size:15px; }
    }
  </style>
</head>

<body>
  <div class="app-container">

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

          {{-- Drafts — kabag, admin, mr, director --}}
          @auth
            @php
              $showDrafts = false;
              $u = auth()->user();
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

          {{-- Approval Queue — ONLY MR & DIRECTOR --}}
          @auth
            @php
              $showApproval = false;
              $u = auth()->user();
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

          {{-- Recycle — ONLY MR/DIRECTOR/ADMIN and when route exists --}}
          @auth
            @php
              $showRecycle = false;
              $u = auth()->user();
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
                {{-- small trash icon --}}
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                  <rect x="3" y="6" width="18" height="14" rx="2" fill="#e6f0ff"/>
                  <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2" stroke="#1e88ff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                  <path d="M8 10v6M12 10v6M16 10v6" stroke="#1e88ff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                  <rect x="7" y="4" width="10" height="2" rx="1" fill="#1e88ff"/>
                </svg>
                <span style="font-weight:500;color:#0b5ed7;"></span>
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

    {{-- Flash messages (non-JS fallback; JS SweetAlert also will show if available) --}}
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

    {{-- CONTENT --}}
    <main class="main-area">
      <div class="page-card">
        @yield('content')
      </div>

      <div class="footer-small">&copy; {{ date('Y') }} ISO Library — Peroni Karya Sentra</div>
    </main>
  </div>

  {{-- Hidden logout form (dipanggil oleh JS menu) --}}
  <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
    @csrf
  </form>

  {{-- allow pages to push scripts here --}}
  @yield('scripts')

  {{-- include SweetAlert2 CDN (hanya sekali di layout) --}}
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  {{-- Dropdown & Toast/Modal handlers --}}
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      /* ---------- DROPDOWN MENU (pindahkan ke body, tidak menyisipkan Blade ke string) ---------- */
      const dropdown = document.querySelector('.dropdown');
      if (dropdown) {
        const toggle = dropdown.querySelector('.dropdown-toggle');

        // create standalone menu appended to body
        const menu = document.createElement('div');
        menu.className = 'dropdown-menu';
        menu.style.display = 'none';

        // Build menu content safely
        // Profile link (if route exists)
        @if(Route::has('profile.edit'))
          const profileBtn = document.createElement('button');
          profileBtn.type = 'button';
          profileBtn.textContent = 'Profile';
          profileBtn.addEventListener('click', function () {
            window.location = @json(route('profile.edit'));
          });
          menu.appendChild(profileBtn);
        @endif

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
          // position menu under toggle, adjust if overflow
          menu.style.left = (Math.max(8, rect.right - menu.offsetWidth)) + 'px';
          menu.style.top = (rect.bottom + 8) + 'px';

          const maxRight = window.innerWidth - 8;
          const menuRight = parseFloat(menu.style.left) + menu.offsetWidth;
          if (menuRight > maxRight) {
            menu.style.left = Math.max(8, maxRight - menu.offsetWidth) + 'px';
          }

          const menuBottom = rect.bottom + 8 + menu.offsetHeight;
          if (menuBottom > window.innerHeight - 8) {
            const above = rect.top - 8 - menu.offsetHeight;
            if (above > 8) menu.style.top = above + 'px';
          }
        }

        toggle.addEventListener('click', function (e) {
          e.stopPropagation();
          if (menu.style.display === 'block') {
            menu.style.display = 'none';
          } else {
            positionMenu();
            menu.style.display = 'block';
          }
        });

        // close on outside click or escape
        document.addEventListener('click', function (e) {
          if (!menu.contains(e.target) && !toggle.contains(e.target)) {
            menu.style.display = 'none';
          }
        });

        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') menu.style.display = 'none';
        });

        window.addEventListener('resize', function () { if (menu.style.display === 'block') positionMenu(); }, { passive: true });
        window.addEventListener('scroll', function () { if (menu.style.display === 'block') positionMenu(); }, { passive: true });
      }

      /* ---------- SWEETALERT FLASH HANDLING ---------- */
      @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Berhasil',
            text: @json(session('success')),
            timer: 2500,
            timerProgressBar: true,
            toast: true,
            position: 'top-end',
            showConfirmButton: false
        });
      @endif

      @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Perhatian',
            text: @json(session('error')),
            confirmButtonText: 'Tutup'
        });
      @endif

      @if(session('pending'))
        Swal.fire({
          icon: 'warning',
          title: 'Pending',
          text: @json(session('pending')),
          showCancelButton: true,
          confirmButtonText: 'Lihat Antrian',
          cancelButtonText: 'Tutup'
        }).then(result => {
          if (result.isConfirmed) {
            window.location = @json(route('approval.index'));
          }
        });
      @endif

    });
  </script>

</body>
</html>
