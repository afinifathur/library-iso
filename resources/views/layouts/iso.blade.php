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

    /* header/brand tweaks */
    .site-header { display:flex; align-items:center; justify-content:space-between; padding:12px 18px; border-bottom:1px solid #eef6fb; background: #fff; }
    .brand { display:flex; align-items:center; gap:12px; text-decoration:none; color:inherit; }
    .brand .logo-img { width:46px; height:46px; border-radius:8px; object-fit:cover; }
    .brand-text { line-height:1; }
    .brand-text .title { font-weight:700; font-size:16px; }
    .brand-text .sub { font-size:12px; color:#6b7280; }
    .main-nav { display:flex; gap:10px; align-items:center; }
    .main-nav a { padding:8px 10px; border-radius:8px; color:#0b5ed7; text-decoration:none; }
    .main-nav a.active { background:#eef7ff; font-weight:600; }
    .btn-muted { background:transparent; border:1px solid transparent; padding:6px 8px; border-radius:8px; color:#0b5ed7; text-decoration:none; }
    .footer-small { margin-top:18px; font-size:13px; color:#6b7280; text-align:center; padding:10px 0; }
    .app-container { min-height:100vh; background:#f7fbff; }
    .main-area { padding:18px; }
    .page-card { max-width:1200px; margin:0 auto; }
  </style>

</head>

<body>
  <div class="app-container">

    {{-- ========================== --}}
    {{--   NAVBAR (disembunyikan di login) --}}
    {{-- ========================== --}}
    @if(!Request::is('login') && !Route::is('login'))
      <header class="site-header">
        {{-- updated brand/logo --}}
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

      <div class="footer-small">&copy; {{ date('Y') }} ISO Library — Peroni Karya Sentra</div>
    </main>

  </div>
</body>
</html>
