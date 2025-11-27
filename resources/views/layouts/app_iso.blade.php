{{-- resources/views/layouts/simple.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Document and Control')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        :root{
            --bg: #f3f5f7;
            --card: #ffffff;
            --line: #e5e7eb;
            --brand: #1d4ed8;
            --muted: #6b7280;
        }

        html,body{ height:100%; margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; color:#111; background:var(--bg); }

        .container{ max-width:1200px; margin:0 auto; padding:0 20px; box-sizing:border-box; }

        /* Topbar */
        .topbar{ background:#fff; border-bottom:1px solid var(--line); position:sticky; top:0; z-index:200; }
        .nav{ display:flex; justify-content:space-between; align-items:center; height:72px; gap:12px; }

        .brand-wrap{ display:flex; align-items:center; gap:14px; text-decoration:none; color:inherit; }
        .brand-wrap img{ width:50px; height:50px; object-fit:contain; }
        .brand-title{ font-size:20px; font-weight:700; line-height:1; }
        .brand-sub{ font-size:12px; color:var(--muted); margin-top:2px; }

        /* Menu */
        .menu{ display:flex; gap:22px; align-items:center; }
        .menu a{ text-decoration:none; font-weight:600; color:#333; padding:8px 4px; border-radius:4px; }
        .menu a:hover{ color:var(--brand); }
        .menu a.active{ color:var(--brand); border-bottom:2px solid var(--brand); padding-bottom:6px; }

        /* User menu */
        .user-menu{ position:relative; }
        .user-btn{ background:#eef0f3; border:1px solid #d1d5db; padding:8px 14px; border-radius:6px; cursor:pointer; font-weight:600; text-transform:lowercase; }
        .dropdown-menu{ display:none; position:absolute; right:0; top:110%; background:#fff; border:1px solid #d1d5db; border-radius:6px; min-width:130px; z-index:1000; box-shadow:0 8px 24px rgba(15,23,42,0.08); }
        .dropdown-menu button{ display:block; width:100%; padding:10px 14px; border:none; background:none; text-align:left; cursor:pointer; font-size:14px; color:#333; }
        .dropdown-menu button:hover{ background:#f2f6ff; color:var(--brand); }

        /* Page card */
        .page{ background:var(--card); margin-top:20px; padding:20px; border-radius:14px; border:1px solid var(--line); box-sizing:border-box; }

        /* small responsive */
        @media (max-width:800px) {
            .menu{ gap:12px; }
            .brand-title{ font-size:18px; }
        }

        /* visual for opened rows (approval helper) */
        .iso-opened-row { background: #f7fff4 !important; }
    </style>
</head>
<body>

{{-- TOPBAR --}}
<div class="topbar">
    <div class="container nav">

        {{-- Brand --}}
        <a href="{{ url('/') }}" class="brand-wrap" aria-label="Document and Control">
            <img src="{{ asset('images/logo-peroni.png') }}" alt="Logo">
            <div>
                <div class="brand-title">Document and Control</div>
                <div class="brand-sub">Peroni Karya Sentra Management ISO Program</div>
            </div>
        </a>

        {{-- Menu --}}
        <nav class="menu" aria-label="Main menu">
            <a href="{{ route('dashboard.index') }}" class="{{ request()->routeIs('dashboard.*') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('documents.index') }}" class="{{ request()->routeIs('documents.*') ? 'active' : '' }}">Documents</a>
            <a href="{{ route('departments.index') }}" class="{{ request()->routeIs('departments.*') ? 'active' : '' }}">Departemen</a>

            @auth
                <a href="{{ route('drafts.index') }}" class="{{ request()->routeIs('drafts.*') ? 'active' : '' }}">Drafts</a>
            @endauth

            @auth
                @if(method_exists(auth()->user(), 'hasAnyRole') ? auth()->user()->hasAnyRole(['mr','director']) : optional(auth()->user()->roles()->pluck('name'))->contains(function($v){ return in_array($v, ['mr','director']); }))
                    <a href="{{ route('approval.index') }}" class="{{ request()->routeIs('approval.*') ? 'active' : '' }}">Approval Queue</a>
                @endif
            @endauth
        </nav>

        {{-- User --}}
        <div class="user-menu dropdown" aria-haspopup="true">
            @php
                $email = optional(Auth::user())->email ?? '';
                $username = $email ? explode('@', strtolower($email))[0] : (optional(Auth::user())->name ?? 'user');
            @endphp

            <button class="user-btn dropdown-toggle" type="button" aria-expanded="false">{{ $username }} ▼</button>

            <div class="dropdown-menu" role="menu" aria-hidden="true">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="dropdown-item">Logout</button>
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

{{-- OPTIONAL: pages can push additional scripts --}}
@yield('scripts')
@stack('scripts')

{{-- Minimal dropdown script + global approval helper (patch) --}}
<script>
document.addEventListener("DOMContentLoaded", function () {
    // Dropdown toggle
    (function(){
        const wrapper = document.querySelector('.user-menu.dropdown');
        if (!wrapper) return;
        const toggle = wrapper.querySelector('.dropdown-toggle');
        const menu = wrapper.querySelector('.dropdown-menu');

        function closeMenu(){ if (menu) { menu.style.display = 'none'; menu.setAttribute('aria-hidden', 'true'); toggle.setAttribute('aria-expanded','false'); } }
        function openMenu(){ if (menu) { menu.style.display = 'block'; menu.setAttribute('aria-hidden', 'false'); toggle.setAttribute('aria-expanded','true'); } }

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            if (!menu) return;
            if (menu.style.display === 'block') closeMenu(); else openMenu();
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.dropdown')) closeMenu();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeMenu();
        });
    })();

    // Global approval helper (patch) — ensure "Open" flags work site-wide
    (function(){
      try {
        const DEBUG = false;

        function enableRowForVersion(vid) {
          if (!vid) return;
          document.querySelectorAll('tr[data-version-id="'+vid+'"]').forEach(tr=>{
            tr.classList.add('iso-opened-row');
            tr.querySelectorAll('.btn-approve, .btn-reject').forEach(btn=>{
              btn.removeAttribute('disabled'); btn.removeAttribute('aria-disabled');
            });
            tr.querySelectorAll('.select-version').forEach(inp => inp.disabled = false);
          });
        }

        function attachOpenHandlers() {
          const links = Array.from(document.querySelectorAll('.action-open'));
          links.forEach(link => {
            if (link.__isoAttached) return;
            link.__isoAttached = true;

            link.addEventListener('click', function (ev) {
              try {
                const tr = this.closest('tr');
                const vid = tr?.dataset?.versionId || this.dataset?.versionId;
                if (!vid) return;
                try { localStorage.setItem('iso_opened_version_' + vid, '1'); } catch(e){}
                try { enableRowForVersion(String(vid)); } catch(e){}
                try {
                  if (window.opener && !window.opener.closed) {
                    window.opener.postMessage({ iso_action: 'version_opened', version_id: String(vid) }, '*');
                  }
                } catch(e){}
              } catch(e){ if (DEBUG) console.error('iso open handler', e); }
              // allow navigation (open in new tab)
            }, {passive:true});

            // support middle-click
            link.addEventListener('auxclick', function(ev){ if (ev.button === 1) {
              const tr = this.closest('tr');
              const vid = tr?.dataset?.versionId || this.dataset?.versionId;
              if (vid) { try { localStorage.setItem('iso_opened_version_' + vid, '1'); } catch(e){}; enableRowForVersion(String(vid)); }
            }}, {passive:true});
          });
        }

        // Handle messages from other tabs/windows
        window.addEventListener('message', function(ev){
          try {
            const d = ev.data || {};
            if (d && d.iso_action === 'version_opened' && d.version_id) {
              enableRowForVersion(String(d.version_id));
            }
          } catch(e){}
        });

        // Handle storage events (other tabs)
        window.addEventListener('storage', function(ev){
          if (!ev.key) return;
          if (ev.key.startsWith('iso_opened_version_') && ev.newValue) {
            const vid = ev.key.replace('iso_opened_version_', '');
            enableRowForVersion(String(vid));
          }
        });

        // Prevent approve form submit unless opened
        function attachApproveGuards() {
          document.querySelectorAll('form.action-form-approve, form[action*="/approval/"][method="POST"]').forEach(form=>{
            if (form.__isoGuard) return;
            form.__isoGuard = true;
            form.addEventListener('submit', function(e){
              try {
                const tr = this.closest('tr');
                const vid = tr?.dataset?.versionId || this.dataset?.versionId;
                if (!vid) return;
                const opened = !!localStorage.getItem('iso_opened_version_' + vid);
                if (!opened) {
                  e.preventDefault();
                  alert('Silakan buka dokumen (Open) terlebih dahulu sebelum menyetujui.');
                  return false;
                }
              } catch(err){ if (DEBUG) console.error(err); }
            });
          });
        }

        // Initialize
        function initAll() {
          attachOpenHandlers();
          attachApproveGuards();

          // apply existing flags
          document.querySelectorAll('tr[data-version-id]').forEach(tr=>{
            const v = tr.dataset.versionId;
            try { if (localStorage.getItem('iso_opened_version_' + v)) enableRowForVersion(String(v)); } catch(e){}
          });
        }

        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
        else initAll();

        // expose for debugging
        window.__isoApprovalHelper = { enableRowForVersion, attachOpenHandlers, attachApproveGuards };

      } catch(e){
        console.error('ISO approval helper failed:', e);
      }
    })();

});
</script>

</body>
</html>
