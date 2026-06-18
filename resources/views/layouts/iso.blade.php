{{-- resources/views/layouts/iso.blade.php --}}
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>@yield('title', 'ISO Library')</title>

  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap">

  <style>
    /* ============================================================
       WAVE 2 — SIDEBAR SHELL
       All rules scoped to sidebar/topbar/layout structure only.
       Page content styles untouched.
    ============================================================ */

    /* Reset body for sidebar layout */
    body {
      margin: 0;
      padding: 0;
      display: block;
      background: #faf8ff;
      font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      color: #191b23;
    }

    /* Material Symbols baseline */
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 20;
      vertical-align: middle;
      font-size: 20px;
      line-height: 1;
      flex-shrink: 0;
    }

    /* ---- SIDEBAR ---- */
    .iso-sidebar {
      position: fixed;
      left: 0;
      top: 0;
      width: 260px;
      height: 100vh;
      background: #ffffff;
      border-right: 1px solid #c3c6d7;
      display: flex;
      flex-direction: column;
      z-index: 200;
      overflow-y: auto;
      overflow-x: hidden;
      transition: transform 0.28s ease;
    }

    /* Sidebar brand */
    .iso-sidebar-brand {
      padding: 20px 16px 16px;
      border-bottom: 1px solid #ededf9;
      flex-shrink: 0;
    }
    .iso-sidebar-brand-name {
      font-size: 15px;
      font-weight: 700;
      color: #004ac6;
      letter-spacing: -0.01em;
      margin: 0 0 2px;
    }
    .iso-sidebar-brand-sub {
      font-size: 11px;
      color: #434655;
      margin: 0;
    }

    /* Nav groups */
    .iso-nav-group {
      padding: 12px 0 4px;
    }
    .iso-nav-group-label {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #737686;
      padding: 0 16px 6px;
      display: block;
    }

    /* Nav links */
    .iso-nav-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 16px;
      font-size: 13px;
      font-weight: 500;
      color: #434655;
      text-decoration: none;
      border-left: 2px solid transparent;
      transition: background 0.15s, color 0.15s;
      white-space: nowrap;
    }
    .iso-nav-link:hover {
      background: #e7e7f3;
      color: #191b23;
      text-decoration: none;
    }
    .iso-nav-link.active {
      background: #d5e3fc;
      color: #004ac6;
      border-left-color: #004ac6;
      font-weight: 600;
    }
    .iso-nav-link.active .material-symbols-outlined {
      color: #004ac6;
    }

    /* Sidebar footer (user section) */
    .iso-sidebar-footer {
      margin-top: auto;
      border-top: 1px solid #c3c6d7;
      padding: 8px 0;
      flex-shrink: 0;
    }
    .iso-sidebar-user {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 16px;
      font-size: 13px;
      font-weight: 500;
      color: #434655;
    }
    .iso-sidebar-user .material-symbols-outlined {
      color: #737686;
    }
    .iso-logout-btn {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 16px;
      width: 100%;
      text-align: left;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 13px;
      font-weight: 500;
      color: #434655;
      transition: background 0.15s, color 0.15s;
    }
    .iso-logout-btn:hover {
      background: #e7e7f3;
      color: #ba1a1a;
    }
    .iso-logout-btn:hover .material-symbols-outlined {
      color: #ba1a1a;
    }

    /* ---- TOPBAR (content area only, not full-width) ---- */
    .iso-topbar {
      position: fixed;
      top: 0;
      left: 260px;
      right: 0;
      height: 56px;
      background: #ffffff;
      border-bottom: 1px solid #c3c6d7;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 24px;
      z-index: 100;
    }
    .iso-topbar-title {
      font-size: 15px;
      font-weight: 600;
      color: #191b23;
      margin: 0;
    }
    .iso-topbar-right {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 13px;
      color: #434655;
    }
    .iso-topbar-user {
      display: flex;
      align-items: center;
      gap: 6px;
      font-weight: 500;
    }

    /* ---- MAIN CONTENT AREA ---- */
    .iso-shell {
      margin-left: 260px;
      padding-top: 56px;
      min-height: 100vh;
    }
    .iso-main {
      padding: 24px;
    }

    /* Override old page-card centering for sidebar layout */
    .page-card {
      max-width: none;
      margin: 0;
      background: transparent;
      box-shadow: none;
      padding: 0;
    }

    /* Keep cards rendered within content working */
    .card {
      border-radius: 6px;
      box-shadow: 0 1px 4px rgba(25, 27, 35, 0.06);
      background: #fff;
    }
    .table thead th { background: #f3f3fe; border-bottom: 1px solid #c3c6d7; }
    .btn { border-radius: 6px; padding: .45rem .75rem; }
    .btn-sm { padding: .25rem .6rem; font-size: .85rem; }
    .table td, .table th { vertical-align: middle; }
    .approval-actions .btn { margin-right: 6px; }
    .login-card .card { border: none; }
    .login-card .form-control { border-radius: 6px; padding: .6rem .75rem; }
    .footer-small { margin-top: 24px; font-size: 12px; color: #737686; text-align: center; padding: 10px 0; }

    /* ---- MOBILE OVERLAY ---- */
    .iso-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(25, 27, 35, 0.45);
      z-index: 199;
    }
    .iso-overlay.active { display: block; }

    /* ---- HAMBURGER ---- */
    .iso-hamburger {
      display: none;
      position: fixed;
      top: 12px;
      left: 12px;
      z-index: 201;
      background: #004ac6;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: 6px 8px;
      cursor: pointer;
      line-height: 1;
    }

    /* ---- LOGIN PAGE (no sidebar) ---- */
    .iso-login-page body,
    body.iso-login-body {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }
    .iso-topbar.login-hidden,
    .iso-sidebar.login-hidden,
    .iso-overlay.login-hidden {
      display: none !important;
    }
    .iso-shell.login-page {
      margin-left: 0;
      padding-top: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }

    /* ---- RESPONSIVE ---- */
    @media (max-width: 768px) {
      .iso-hamburger { display: flex; align-items: center; }
      .iso-sidebar { transform: translateX(-260px); }
      .iso-sidebar.open { transform: translateX(0); }
      .iso-topbar { left: 0; padding-left: 56px; }
      .iso-shell { margin-left: 0; }
    }

    /* Scrollbar */
    .iso-sidebar::-webkit-scrollbar { width: 4px; }
    .iso-sidebar::-webkit-scrollbar-track { background: transparent; }
    .iso-sidebar::-webkit-scrollbar-thumb { background: #c3c6d7; border-radius: 4px; }
  </style>

</head>

<body>

@php
  $isLogin = Request::is('login') || Route::is('login');
  $pageTitle = View::yieldContent('title') ?: 'ISO Library';
  $email    = Auth::check() ? (Auth::user()->email ?? '') : '';
  $username = $email ? (explode('@', strtolower($email))[0] ?? (Auth::user()->name ?? 'user')) : '';
@endphp

{{-- ============================================================
     SIDEBAR — hidden on login page
============================================================ --}}
@if(!$isLogin)

  {{-- Mobile overlay --}}
  <div class="iso-overlay" id="iso-overlay"></div>

  {{-- Hamburger --}}
  <button class="iso-hamburger" id="iso-hamburger" aria-label="Toggle menu">
    <span class="material-symbols-outlined" style="font-size:22px;">menu</span>
  </button>

  {{-- Sidebar --}}
  <aside class="iso-sidebar" id="iso-sidebar" aria-label="Main navigation">

    {{-- Brand --}}
    <div class="iso-sidebar-brand">
      <p class="iso-sidebar-brand-name">Document Control</p>
      <p class="iso-sidebar-brand-sub">ISO 9001:2015 Management</p>
    </div>

    {{-- MAIN group --}}
    <div class="iso-nav-group">
      <span class="iso-nav-group-label">Main</span>

      <a href="{{ route('dashboard.index') }}"
         class="iso-nav-link {{ request()->routeIs('dashboard.*') ? 'active' : '' }}">
        <span class="material-symbols-outlined">dashboard</span>
        Dashboard
      </a>

      <a href="{{ route('documents.index') }}"
         class="iso-nav-link {{ request()->routeIs('documents.*') || request()->routeIs('versions.*') ? 'active' : '' }}">
        <span class="material-symbols-outlined">description</span>
        Documents
      </a>

      @auth
        @if(auth()->user()->hasAnyRole(['kabag','admin','mr','director']))
          <a href="{{ route('drafts.index') }}"
             class="iso-nav-link {{ request()->routeIs('drafts.*') ? 'active' : '' }}">
            <span class="material-symbols-outlined">edit_document</span>
            Draft Container
          </a>
        @endif
      @endauth

      @auth
        @if(auth()->user()->hasAnyRole(['mr','director']))
          <a href="{{ route('approval.index') }}"
             class="iso-nav-link {{ request()->routeIs('approval.*') ? 'active' : '' }}">
            <span class="material-symbols-outlined">task_alt</span>
            Approval Queue
          </a>
        @endif
      @endauth

      @auth
        @if(auth()->user()->hasAnyRole(['kabag','admin','mr','director']))
          <a href="{{ route('reviews.due') }}"
             class="iso-nav-link {{ request()->routeIs('reviews.*') ? 'active' : '' }}">
            <span class="material-symbols-outlined">fact_check</span>
            Review Program
          </a>
        @endif
      @endauth
    </div>

    {{-- ADMINISTRATION group --}}
    <div class="iso-nav-group">
      <span class="iso-nav-group-label">Administration</span>

      <a href="{{ route('categories.index') }}"
         class="iso-nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}">
        <span class="material-symbols-outlined">folder_open</span>
        Categories
      </a>

      <a href="{{ route('departments.index') }}"
         class="iso-nav-link {{ request()->routeIs('departments.*') ? 'active' : '' }}">
        <span class="material-symbols-outlined">corporate_fare</span>
        Departments
      </a>
    </div>

    {{-- COMPLIANCE group --}}
    <div class="iso-nav-group">
      <span class="iso-nav-group-label">Compliance</span>

      @auth
        @if(auth()->user()->hasAnyRole(['mr','director']))
          <a href="{{ route('audit.index') }}"
             class="iso-nav-link {{ request()->routeIs('audit.*') ? 'active' : '' }}">
            <span class="material-symbols-outlined">manage_search</span>
            Audit Trail
          </a>
        @endif
      @endauth

      <a href="{{ route('recycle.index') }}"
         class="iso-nav-link {{ request()->routeIs('recycle.*') || request()->routeIs('revision.*') ? 'active' : '' }}">
        <span class="material-symbols-outlined">delete_sweep</span>
        Recycle Bin
      </a>
    </div>

    {{-- USER section --}}
    @auth
      <div class="iso-sidebar-footer">
        <div class="iso-sidebar-user">
          <span class="material-symbols-outlined">account_circle</span>
          <span>{{ $username }}</span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="iso-logout-btn">
            <span class="material-symbols-outlined">logout</span>
            Logout
          </button>
        </form>
      </div>
    @endauth

  </aside>

  {{-- TOPBAR --}}
  <header class="iso-topbar">
    <h1 class="iso-topbar-title">@yield('title', 'ISO Library')</h1>
    <div class="iso-topbar-right">
      @auth
        <div class="iso-topbar-user">
          <span class="material-symbols-outlined" style="font-size:18px;color:#737686;">account_circle</span>
          <span>{{ $username }}</span>
        </div>
      @endauth
    </div>
  </header>

@endif

{{-- ============================================================
     MAIN CONTENT SHELL
============================================================ --}}
<div class="iso-shell {{ $isLogin ? 'login-page' : '' }}">
  <main class="{{ $isLogin ? '' : 'iso-main' }}">
    @yield('content')
    @if(!$isLogin)
      <div class="footer-small">&copy; {{ date('Y') }} ISO Library &mdash; Peroni Karya Sentra</div>
    @endif
  </main>
</div>

{{-- ============================================================
     SIDEBAR TOGGLE — Vanilla JS, mobile only
============================================================ --}}
@if(!$isLogin)
<script>
(function () {
  var sidebar  = document.getElementById('iso-sidebar');
  var overlay  = document.getElementById('iso-overlay');
  var hamburger = document.getElementById('iso-hamburger');
  if (!sidebar || !overlay || !hamburger) return;

  function openSidebar() {
    sidebar.classList.add('open');
    overlay.classList.add('active');
    hamburger.setAttribute('aria-expanded', 'true');
  }
  function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    hamburger.setAttribute('aria-expanded', 'false');
  }

  hamburger.addEventListener('click', function () {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  });
  overlay.addEventListener('click', closeSidebar);

  // Close on Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
  });
}());
</script>
@endif

{{-- ============================================================
     FORCE ENABLE APPROVE/REJECT BUTTONS — MVP PATCH
     DO NOT REMOVE OR MODIFY THIS SCRIPT BLOCK
============================================================ --}}
<script>
  (function forceEnableApproveButtonsForMVP() {
    /**
     * Purpose:
     * - Remove disabled attributes from buttons so Approve/Reject are clickable immediately.
     * - Mark approve forms to avoid JS guards that might intercept submission.
     *
     * Usage: paste this script into your layout (same file where approval helper runs).
     */
    function enableAllApproveButtons() {
      try {
        document.querySelectorAll('.btn-approve, .btn-reject').forEach(btn => {
          btn.removeAttribute('disabled');
          btn.removeAttribute('aria-disabled');
          // ensure pointer events are allowed
          btn.style.pointerEvents = '';
          btn.style.opacity = '';
        });

        // If there are guard flags set by other scripts (e.g. __isoGuard false),
        // set them to true so attachApproveGuards won't prevent submit.
        document.querySelectorAll('form.action-form-approve, form[action*="/approval/"][method="POST"]').forEach(f => {
          try { f.__isoGuard = true; } catch(e){}
        });

      } catch(e) {
        console.warn('forceEnableApproveButtonsForMVP error', e);
      }
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', enableAllApproveButtons);
    } else {
      enableAllApproveButtons();
    }

    // also observe for dynamically loaded rows (AJAX)
    try {
      const obs = new MutationObserver(muts => {
        enableAllApproveButtons();
      });
      obs.observe(document.body, { childList: true, subtree: true });
      window.__forceEnableApproveButtonsForMVP = { enableAllApproveButtons, _observer: obs };
    } catch(e) {
      window.__forceEnableApproveButtonsForMVP = { enableAllApproveButtons };
    }
  })();
</script>

</body>
</html>
