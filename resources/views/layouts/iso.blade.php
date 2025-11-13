<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ISO Library</title>

  <link rel="stylesheet" href="{{ asset('css/style.css') }}">

  <style>
    /* General UI */
    .card { border-radius:12px; box-shadow:0 6px 18px rgba(20,40,70,0.05); }
    .table thead th { background:#f8fafc; border-bottom:1px solid #e6eef6; }
    .btn { border-radius:8px; padding:.45rem .75rem; }
    .btn-sm { padding:.25rem .6rem; font-size:.85rem; }
    .table td, .table th { vertical-align:middle; }

    /* approval page */
    .approval-actions .btn { margin-right:6px; }
    #rejectNotes { min-height:120px; }

    /* login page */
    .login-card .card { border:none; }
    .login-card .form-control { border-radius:8px; padding:.6rem .75rem; }
    .btn-primary { background:#1e88ff; border-color:#1e88ff; }
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
      z-index: 20; /* keep header above normal content */
    }

    .brand { display:flex; align-items:center; gap:12px; text-decoration:none; color:inherit; }
    .logo-img { width:46px; height:46px; border-radius:8px; object-fit:cover; }
    .brand-text { line-height:1; }
    .brand-text .title { font-weight:700; font-size:16px; }
    .brand-text .sub { font-size:12px; color:#6b7280; }

    .main-nav { display:flex; gap:10px; align-items:center; }
    .main-nav a { padding:8px 10px; border-radius:8px; color:#0b5ed7; text-decoration:none; }
    .main-nav a.active { background:#eef7ff; font-weight:600; }

    .btn-muted {
      background:transparent;
      border:1px solid transparent;
      padding:6px 8px;
      border-radius:8px;
      color:#0b5ed7;
      text-decoration:none;
    }

    .footer-small { margin-top:18px; font-size:13px; color:#6b7280; text-align:center; padding:10px 0; }
    .app-container { min-height:100vh; background:#f7fbff; }
    .main-area { padding:18px; }
    .page-card { max-width:1200px; margin:0 auto; }

    /* Dropdown (visual styles only) */
    .dropdown {
        position: relative; /* still useful for spacing but menu will be moved into body */
    }
    .dropdown-toggle {
        background:transparent;
        border:1px solid transparent;
        padding:6px 10px;
        border-radius:8px;
        cursor:pointer;
        color:#0b5ed7;
    }

    /* menu will be appended to body and positioned via JS */
    .dropdown-menu {
        display:none; /* initial hidden; JS will toggle */
        background:#fff;
        border:1px solid #d1d5db;
        border-radius:6px;
        min-width:130px;
        box-shadow:0 6px 20px rgba(15,23,42,0.12);
        z-index: 999999; /* high fallback */
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

    /* small responsive tweak: ensure menu width not exceed viewport */
    @media (max-width:420px) {
      .dropdown-menu { min-width: 120px; }
    }
  </style>

</head>

<body>
  <div class="app-container">

    {{-- NAVBAR (hide on login) --}}
    @if(!Request::is('login') && !Route::is('login'))
      <header class="site-header">

        {{-- Brand --}}
        <a href="{{ url('/') }}" class="brand" aria-label="Document Control — Management System">
          <img src="{{ asset('images/logo.png') }}" alt="Document Control" class="logo-img">
          <div class="brand-text">
            <div class="title">Document Control</div>
            <div class="sub">Management System</div>
          </div>
        </a>

        <nav class="main-nav" role="navigation" aria-label="Main navigation">
          <a href="{{ route('dashboard.index') }}" class="{{ request()->routeIs('dashboard*') ? 'active' : '' }}">Dashboard</a>
          <a href="{{ route('documents.index') }}" class="{{ request()->routeIs('documents*') ? 'active' : '' }}">Documents</a>
          <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories*') ? 'active' : '' }}">Categories</a>
          <a href="{{ route('departments.index') }}" class="{{ request()->routeIs('departments*') ? 'active' : '' }}">Departments</a>
          <a href="{{ route('approval.index') }}" class="{{ request()->routeIs('approval*') ? 'active' : '' }}">Approval Queue</a>
          <a href="{{ route('revision.index') }}" class="{{ request()->routeIs('revision*') ? 'active' : '' }}">Revision History</a>
          <a href="{{ route('audit.index') }}" class="{{ request()->routeIs('audit*') ? 'active' : '' }}">Audit Log</a>

          @auth
          {{-- USER DROPDOWN --}}
          <div class="dropdown" style="margin-left:12px;">
              @php
                  $email = Auth::user()->email ?? '';
                  $username = $email ? explode('@', strtolower($email))[0] : 'user';
              @endphp

              <button class="dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">
                  {{ $username }} ▼
              </button>

              {{-- keep an inline fallback menu for non-JS (visually hidden) --}}
              <div class="dropdown-menu" aria-hidden="true" data-fallback="true" style="display:none;">
                  <form method="POST" action="{{ route('logout') }}">
                      @csrf
                      <button type="submit">Logout</button>
                  </form>
              </div>
          </div>
          @endauth
        </nav>

      </header>
    @endif
    {{-- END NAVBAR --}}

    <main class="main-area">
      <div class="page-card">
        @yield('content')
      </div>

      <div class="footer-small">&copy; {{ date('Y') }} ISO Library — Peroni Karya Sentra</div>
    </main>

  </div>

  {{-- Dropdown Script: move menu to body & position fixed to avoid being clipped by other stacking contexts --}}
  <script>
    (function () {
      document.addEventListener("DOMContentLoaded", function () {
        const dropdown = document.querySelector('.dropdown');
        if (!dropdown) return;

        const toggle = dropdown.querySelector('.dropdown-toggle');
        const inlineMenu = dropdown.querySelector('.dropdown-menu[data-fallback="true"]');

        // create a standalone menu element that will be appended to body
        const menu = document.createElement('div');
        menu.className = 'dropdown-menu';
        // copy contents from inline fallback menu if present
        if (inlineMenu) {
          menu.innerHTML = inlineMenu.innerHTML;
        } else {
          // fallback: minimal logout form (shouldn't happen)
          menu.innerHTML = '<form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">Logout</button></form>';
        }

        // set ARIA
        menu.setAttribute('role', 'menu');
        menu.setAttribute('aria-hidden', 'true');

        // append to body
        document.body.appendChild(menu);

        // helper to position menu near the toggle
        function positionMenu() {
          const rect = toggle.getBoundingClientRect();
          const menuRect = menu.getBoundingClientRect();
          // default place below the toggle, aligned to the right edge of the toggle
          const gap = 8; // little spacing
          let top = rect.bottom + gap;
          let left = rect.right - menuRect.width;

          // ensure within viewport horizontally
          if (left < 8) left = 8;
          if (left + menuRect.width > window.innerWidth - 8) {
            left = Math.max(8, window.innerWidth - 8 - menuRect.width);
          }

          // ensure vertically within viewport (if not enough space, place above)
          if (top + menuRect.height > window.innerHeight - 8) {
            const possibleTop = rect.top - gap - menuRect.height;
            if (possibleTop > 8) top = possibleTop;
          }

          menu.style.position = 'fixed';
          menu.style.left = (left) + 'px';
          menu.style.top = (top) + 'px';
          menu.style.zIndex = '999999';
        }

        function openMenu() {
          positionMenu();
          menu.style.display = 'block';
          menu.setAttribute('aria-hidden', 'false');
          toggle.setAttribute('aria-expanded', 'true');
        }

        function closeMenu() {
          menu.style.display = 'none';
          menu.setAttribute('aria-hidden', 'true');
          toggle.setAttribute('aria-expanded', 'false');
        }

        // toggle click handler
        toggle.addEventListener('click', function (e) {
          e.stopPropagation();
          if (menu.style.display === 'block') {
            closeMenu();
          } else {
            openMenu();
          }
        });

        // close on outside click
        document.addEventListener('click', function (e) {
          if (!e.target.closest('.dropdown') && !menu.contains(e.target)) {
            closeMenu();
          }
        });

        // close on resize/scroll to avoid mispositioned menu; also reposition on resize
        window.addEventListener('scroll', closeMenu, { passive: true });
        window.addEventListener('resize', function () {
          if (menu.style.display === 'block') positionMenu();
        });

        // close on Escape
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape') closeMenu();
        });

        // ensure menu width is measured (temporary show to compute size if needed)
        // (not necessary usually because copy to body sets size; but keep safe)
        menu.style.display = 'none';
      });
    })();
  </script>

</body>
</html>
