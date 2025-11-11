@extends('layouts.iso')

@section('title','Login')

{{-- Self-contained CSS agar override semua style lama --}}
@push('head')
<style>
/* reset small differences & force our look */
html,body { height:100%; margin:0; padding:0; font-family: "Inter", "Helvetica Neue", Arial, sans-serif; background:#f5f7fb; color:#111827; }

/* hide layout navbar only on login (if layout didn't already hide it) */
header, .navbar, nav { display: none !important; }

/* main wrapper */
.login-wrapper {
  min-height: 100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding: 28px;
  box-sizing: border-box;
}

/* card */
.login-card {
  width: 420px;
  max-width: 96%;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 12px 30px rgba(20,40,80,0.06);
  overflow: hidden;
  padding: 28px 26px;
}

/* logo & header */
.login-card .top {
  display:flex;
  align-items:center;
  gap:14px;
  margin-bottom:12px;
}
.login-card .top img.logo {
  width:64px;
  height:64px;
  border-radius:10px;
  object-fit:contain;
  background:#fff;
  padding:6px;
  box-shadow: 0 4px 12px rgba(40,60,90,0.06);
}
.login-card h1 {
  font-size:18px;
  margin:0;
  font-weight:700;
  line-height:1.05;
}
.login-card .subtitle {
  margin: 4px 0 10px 0;
  color:#6b7280;
  font-size:0.92rem;
}

/* form */
.form-group { margin-bottom:12px; }
.form-label { display:block; margin-bottom:6px; font-weight:600; font-size:0.95rem; color:#111827; }
.input, .form-control {
  width:100%;
  box-sizing:border-box;
  padding:.65rem .9rem;
  border-radius:8px;
  border:1px solid #e6eef6;
  outline:none;
  font-size:0.95rem;
  transition: box-shadow .12s, border-color .12s;
}
.input:focus, .form-control:focus { box-shadow: 0 6px 18px rgba(30,100,200,0.06); border-color:#93c5fd; }

/* password inline toggle */
.input-group { display:flex; align-items:center; gap:8px; }
.input-group .toggle-btn {
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:40px;
  height:38px;
  border-radius:8px;
  border:1px solid #e6eef6;
  background:#1e88ff;
  color:#fff;
  cursor:pointer;
  padding:0 8px;
  font-weight:600;
}
.input-group .toggle-btn.secondary {
  background:#ffffff;
  color:#1e293b;
  border:1px solid #e6eef6;
}

/* small row */
.row-between { display:flex; align-items:center; justify-content:space-between; gap:10px; }

/* buttons */
.btn {
  display:inline-block;
  padding:.62rem .9rem;
  border-radius:10px;
  border:none;
  cursor:pointer;
  font-weight:600;
  text-align:center;
}
.btn-primary {
  background:#0ea5e9; /* bright cyan-blue */
  color:#fff;
  box-shadow: 0 6px 18px rgba(14,165,233,0.12);
}
.btn-outline {
  background: transparent;
  color:#0f172a;
  border:1px solid #e6eef6;
}

/* links and footer */
.small-link { color:#2563eb; font-size:.92rem; text-decoration:none; }
.footer-note { font-size:.82rem; color:#94a3b8; text-align:center; margin-top:14px; }

/* responsive */
@media (max-width:460px) {
  .login-card { padding:18px; width:92%; }
  .login-card .top img.logo { width:56px; height:56px; }
}
</style>
@endpush

@section('content')
<div class="login-wrapper" role="main" aria-labelledby="login-title">
  <div class="login-card" aria-live="polite">
    <div class="top">
      <img src="{{ asset('images/logo.png') }}" alt="logo" class="logo" />
      <div>
        <h1 id="login-title">PT. Peroni Karya Sentra</h1>
        <div class="subtitle">Document & Control — Peroni Karya Sentra<br><small style="color:#94a3b8">Management System</small></div>
      </div>
    </div>

    @if(session('error'))
      <div style="margin-bottom:10px;padding:10px;border-radius:8px;background:#fff3f2;color:#991b1b;border:1px solid #ffcccc;">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('login.attempt') }}" novalidate>
      @csrf

      <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input id="email" name="email" type="email" required class="input" placeholder="Masukkan email Anda" value="{{ old('email') }}" />
        @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-group">
          <input id="pwdInput" name="password" type="password" required class="input" placeholder="Masukkan password" />
          <button type="button" id="togglePwd" class="toggle-btn" aria-label="toggle password">👁</button>
        </div>
        @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
      </div>

      <div class="form-group row-between" style="margin-top:6px;">
        <div style="display:flex;align-items:center;gap:8px;">
          <input id="remember" type="checkbox" name="remember" style="width:16px;height:16px;" />
          <label for="remember" style="font-size:.95rem;color:#111827;">Remember me</label>
        </div>
        <a class="small-link" href="#">Lupa password?</a>
      </div>

      <div style="margin-top:14px;">
        <button type="submit" class="btn btn-primary" style="width:100%;">Login</button>
      </div>

      <div style="display:flex; gap:8px; justify-content:center; margin-top:12px;">
        <a href="{{ url('/') }}" class="btn btn-outline" style="padding:.5rem .8rem;">Home</a>
      </div>

      <div class="footer-note">&copy; {{ date('Y') }} Document and Control — Peroni Karya Sentra</div>
    </form>

  </div>
</div>

@push('scripts')
<script>
  // password toggle
  (function(){
    const t = document.getElementById('togglePwd');
    const input = document.getElementById('pwdInput');
    if (!t || !input) return;
    t.addEventListener('click', function(){
      if (input.type === 'password') { input.type = 'text'; t.textContent = '🙈'; }
      else { input.type = 'password'; t.textContent = '👁'; }
    });
  })();
</script>
@endpush
@endsection
