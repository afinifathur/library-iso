<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ISO Library</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>

<body>
  <div class="app-container">

    {{-- Top navbar --}}
    <header class="site-header">
      <a class="brand" href="{{ route('dashboard.index') }}">
        <div class="logo">ISO</div>
        <div class="brand-text">
          <h1>ISO Library</h1>
          <div class="sub">Dokumentasi Mutu &amp; SOP</div>
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

    <main class="main-area">
      <div class="page-card">
        @yield('content')
      </div>

      <div class="footer-small">&copy; {{ date('Y') }} ISO Library — built for offline/LAN use</div>
    </main>

  </div>
</body>
</html>
