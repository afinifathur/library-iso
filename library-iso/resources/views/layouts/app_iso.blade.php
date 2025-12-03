<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document and Control</title>

    <style>
        :root {
            --bg: #f3f5f7;
            --card: #ffffff;
            --line: #e5e7eb;
            --brand: #1d4ed8;
            --muted: #6b7280;
        }

        body {
            margin: 0;
            background: var(--bg);
            font-family: system-ui, -apple-system, Segoe UI, Roboto;
            color: #111;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* NAVBAR */
        .topbar {
            background: #ffffff;
            border-bottom: 1px solid var(--line);
            position: sticky;
            top: 0;
            z-index: 200;
        }

        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 72px;
        }

        /* BRAND */
        .brand-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-wrap img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        .brand-title {
            font-size: 20px;
            font-weight: 700;
        }

        .brand-sub {
            font-size: 12px;
            color: var(--muted);
            margin-top: 1px;
        }

        /* MENU */
        .menu {
            display: flex;
            gap: 22px;
        }

        .menu a {
            text-decoration: none;
            font-weight: 600;
            color: #333;
            padding: 8px 4px;
            border-radius: 4px;
        }

        .menu a:hover {
            color: var(--brand);
        }

        .menu a.active {
            color: var(--brand);
            border-bottom: 2px solid var(--brand);
            padding-bottom: 6px;
        }

        /* USER BADGE */
        .user-menu {
            position: relative;
        }

        .user-btn {
            background: #eef0f3;
            border: 1px solid #d1d5db;
            padding: 8px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-transform: lowercase;
        }

        .user-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 110%;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            min-width: 130px;
        }

        .user-dropdown a {
            display: block;
            padding: 10px 14px;
            text-decoration: none;
            color: #333;
        }

        .user-dropdown a:hover {
            background: #f2f6ff;
            color: var(--brand);
        }

        .user-menu:hover .user-dropdown {
            display: block;
        }

        /* PAGE WRAPPER */
        .page {
            background: #ffffff;
            margin-top: 20px;
            padding: 20px;
            border-radius: 14px;
            border: 1px solid var(--line);
        }
    </style>
</head>

<body>

    {{-- NAVBAR --}}
    <div class="topbar">
        <div class="container nav">

            {{-- Brand --}}
            <div class="brand-wrap">
                <img src="{{ asset('images/logo-peroni.png') }}" alt="Logo">
                <div>
                    <div class="brand-title">Document and Control</div>
                    <div class="brand-sub">Peroni Karya Sentra Management ISO Program</div>
                </div>
            </div>

            {{-- Menu --}}
            <nav class="menu">
                <a href="{{ route('dashboard.index') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>

                <a href="{{ route('documents.index') }}" class="{{ request()->routeIs('documents.*') ? 'active' : '' }}">Documents</a>

                <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'active' : '' }}">Kategori</a>

                <a href="{{ route('departments.index') }}" class="{{ request()->routeIs('departments.*') ? 'active' : '' }}">Departemen</a>

                <a href="{{ route('approval.index') }}" class="{{ request()->routeIs('approval.*') ? 'active' : '' }}">Approval Queue</a>

                <a href="{{ route('revision.index') }}" class="{{ request()->routeIs('revision.*') ? 'active' : '' }}">Revision History</a>

                <a href="{{ route('audit.index') }}" class="{{ request()->routeIs('audit.*') ? 'active' : '' }}">Audit Log</a>
            </nav>

            {{-- User --}}
            <div class="user-menu">

                @php
                    $email = strtolower(Auth::user()->email);
                    $username = explode('@', $email)[0];
                @endphp

                <button class="user-btn">{{ $username }}</button>

                <div class="user-dropdown">
                    <a href="#"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        Logout
                    </a>
                    <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none;">
                        @csrf
                    </form>
                </div>

            </div>

        </div>
    </div>

    {{-- PAGE CONTENT --}}
    <div class="container">
        <div class="page">
            @yield('content')
        </div>
    </div>

</body>
</html>
