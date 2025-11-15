{{-- resources/views/layouts/app.blade.php --}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
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
        min-width:130px;
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
              @if(auth()->user()->hasAnyRole(['mr','director']))
                  <a href="{{ route('audit.index') }}" class="{{ request()->routeIs('audit.*') ? 'active' : '' }}">Audit Log</a>
              @endif
          @endauth

          {{-- Drafts — kabag, admin, mr, director --}}
          @auth
              @if(auth()->user()->hasAnyRole(['kabag','admin','mr','director']))
                  <a href="{{ route('drafts.index') }}" class="{{ request()->routeIs('drafts.*') ? 'active' : '' }}">Drafts</a>
              @endif
          @endauth

          {{-- Approval Queue — ONLY MR & DIRECTOR --}}
          @auth
              @if(auth()->user()->hasAnyRole(['mr','director']))
                  <a href="{{ route('approval.index') }}" class="{{ request()->routeIs('approval.*') ? 'active' : '' }}">Approval Queue</a>
              @endif
          @endauth

          {{-- USER DROPDOWN --}}
          @auth
            <div class="dropdown" style="margin-left:12px;">
              @php
                $email = Auth::user()->email ?? '';
                $username = $email ? explode('@', strtolower($email))[0] : Auth::user()->name ?? 'user';
              @endphp

              <button class="dropdown-toggle" type="button" aria-haspopup="true" aria-expanded="false">
                {{ $username }} ▼
              </button>

              {{-- Inline fallback menu used to copy into body via JS --}}
              <div class="dropdown-menu" data-fallback="true" style="display:none;">
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

    {{-- Flash messages --}}
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

  {{-- DROPDOWN SCRIPT: move inline menu content to body and position fixed to avoid clipping --}}
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      const dropdown = document.querySelector('.dropdown');
      if (!dropdown) return;

      const toggle = dropdown.querySelector('.dropdown-toggle');
      const inlineMenu = dropdown.querySelector('.dropdown-menu[data-fallback="true"]');

      // create standalone menu appended to body
      const menu = document.createElement('div');
      menu.className = 'dropdown-menu';
      menu.style.display = 'none';
      menu.innerHTML = inlineMenu ? inlineMenu.innerHTML : '<form method="POST" action="{{ route("logout") }}">@csrf<button type="submit">Logout</button></form>';
      document.body.appendChild(menu);

      function positionMenu() {
        const rect = toggle.getBoundingClientRect();
        // try placing below and align right of toggle
        menu.style.left = Math.max(8, rect.right - menu.offsetWidth) + 'px';
        menu.style.top = (rect.bottom + 8) + 'px';

        // ensure inside viewport horizontally
        const maxRight = window.innerWidth - 8;
        const menuRight = parseFloat(menu.style.left) + menu.offsetWidth;
        if (menuRight > maxRight) {
          menu.style.left = Math.max(8, maxRight - menu.offsetWidth) + 'px';
        }

        // if not enough vertical space, open above
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

      // close on outside click
      document.addEventListener('click', function (e) {
        if (!menu.contains(e.target) && !toggle.contains(e.target)) {
          menu.style.display = 'none';
        }
      });

      // close on escape
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') menu.style.display = 'none';
      });

      // reposition on resize/scroll if open
      window.addEventListener('resize', function () {
        if (menu.style.display === 'block') positionMenu();
      }, { passive: true });

      window.addEventListener('scroll', function () {
        if (menu.style.display === 'block') {
          // reposition relative to viewport
          positionMenu();
        }
      }, { passive: true });
    });
  </script>
</body>
</html>
