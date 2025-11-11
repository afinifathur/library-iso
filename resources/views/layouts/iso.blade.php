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
  </style>

</head>

<body>
  <div class="app-container">

    {{-- ========================== --}}
    {{--   NAVBAR (disembunyikan di login) --}}
    {{-- ========================== --}}
    @if(!Request::is('login') && !Route::is('login'))
      <header class="site-header">
        <a class="brand" href="{{ route('dashboard.index') }}">
          <div class="logo">ISO</div>
          <div class="brand-text">
            <h1>ISO Library</h1>
            <div class="sub">Dokumentasi Mutu & SOP</div>
          </div>
        </a>

        <nav class="main-nav">
          <a href="{{ route('dashboard.index') }}" class="{{ request()->routeIs('dashboard*') ? 'active' : '' }}">Dashboard</a>
          <a href="{{ route('documents.index') }}" class="{{ request()->routeIs('documents*') ? 'active' : '' }}">Documents</a>
          <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories*') ? 'active' : '' }}">Categories</a>
          <a href="{{ route('departments.index') }}" class="{{ request()->routeIs('departments*') ? 'active' : '' }}">Departments</a>
          <a href="{{ route('approval.index') }}" class="{{ request()->routeIs('approval*') ? 'active' : '' }}">Approval Queue</a>
          <a href="{{ route('revision.index') }}" class="{{ request()->routeIs('revision*') ? 'active' : '' }}">Revision History</a>
          <a href="{{ route('audit.index') }}" class="{{ request()->routeIs('audit*') ? 'active' : '' }}">Audit Log</a>

          @auth
          <form method="POST" action="{{ route('logout') }}" style="display:inline;margin-left:12px;">
            @csrf
            <button type="submit" class="btn-muted">Logout</button>
          </form>
          @endauth
        </nav>
      </header>
    @endif
    {{-- end navbar --}}

    <main class="main-area">
      <div class="page-card">
        @yield('content')
      </div>

      <div class="footer-small">&copy; {{ date('Y') }} ISO Library — built for offline/LAN use</div>
    </main>

  </div>
</body>
</html>
