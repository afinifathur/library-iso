@extends('layouts.iso')

@section('title','Login')

@section('content')

<style>
header, .navbar, nav, .site-header, .main-nav, .brand { display: none !important; }

html,body {
    height:100%;
    margin:0;
    padding:0;
    font-family: "Inter", Arial, Helvetica, sans-serif;
    background:#f5f7fb !important;
}

.login-wrapper {
    min-height: 100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding: 28px;
    box-sizing: border-box;
}

.login-card {
    width:420px;
    max-width:96%;
    background:#ffffff;
    border-radius:12px;
    box-shadow:0 12px 30px rgba(20,40,80,0.06);
    padding:28px;
    box-sizing:border-box;
}

/* LOGO + TITLE CENTER */
.login-top {
    display:flex;
    flex-direction:column;
    align-items:center;
    text-align:center;
    margin-bottom:16px;
}

.login-top img.logo {
    width:78px;
    height:78px;
    border-radius:10px;
    object-fit:cover;
    background:#fff;
    padding:6px;
    margin-bottom:10px;
    box-shadow:0 6px 14px rgba(20,40,80,0.06);
}

.login-title {
    font-size:22px;
    font-weight:700;
    margin:0;
    color:#0f172a;
}

.login-sub {
    margin-top:6px;
    color:#6b7280;
    font-size:0.92rem;
    line-height:1.35;
}

/* FORM */
.login-card .form-group { margin-bottom:12px; }
.login-card label {
    display:block;
    margin-bottom:6px;
    font-weight:600;
    color:#111827;
}

.login-card input[type="email"],
.login-card input[type="password"] {
    width:100%;
    padding:.62rem .8rem;
    border-radius:8px;
    border:1px solid #e6eef8;
    background:#fff;
    font-size:0.95rem;
    box-sizing:border-box;
}

.login-card input:focus {
    outline:none;
    box-shadow:0 6px 18px rgba(30,100,200,0.06);
    border-color:#93c5fd;
}

.pw-row { display:flex; gap:8px; }
.toggle-btn {
    min-width:42px;
    height:38px;
    border-radius:8px;
    border:1px solid #e6eef8;
    background:#0ea5e9;
    color:white;
    cursor:pointer;
    display:flex;
    align-items:center;
    justify-content:center;
}

.row-between {
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-top:6px;
}

.btn {
    display:inline-block;
    padding:.68rem .9rem;
    border-radius:10px;
    font-weight:600;
    text-align:center;
    cursor:pointer;
    border:none;
}

.btn-primary {
    background:#1e88ff;
    color:#fff;
    width:100%;
    box-shadow:0 8px 20px rgba(30,100,200,0.12);
}

.btn-secondary {
    background:transparent;
    border:1px solid #e6eef8;
    color:#0f172a;
    padding:.5rem .9rem;
    border-radius:8px;
}

.small-link { color:#2563eb; text-decoration:none; font-size:0.92rem; }

.login-footer {
    font-size:0.85rem;
    color:#94a3b8;
    text-align:center;
    margin-top:14px;
}

@media (max-width:480px) {
    .login-card { padding:18px; }
}
</style>

<div class="login-wrapper">
    <div class="login-card">

        {{-- LOGO + TITLE CENTER --}}
        <div class="login-top">
            <img src="{{ asset('images/logo.png') }}" alt="logo" class="logo">

            <div class="login-title">PT. Peroni Karya Sentra</div>

            <div class="login-sub">
                Document Control and Management System
            </div>
        </div>

        @if(session('error'))
            <div style="margin-bottom:10px;padding:10px;border-radius:8px;background:#fff3f2;color:#991b1b;border:1px solid #ffd6d6;">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf

            <div class="form-group">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}" autocomplete="username">
                @error('email') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="pw-row">
                    <input id="pwdInput" name="password" type="password" required autocomplete="current-password">
                    <button type="button" id="togglePwd" class="toggle-btn">👁</button>
                </div>
                @error('password') <div class="text-danger small">{{ $message }}</div> @enderror
            </div>

            <div class="row-between">
                <div>
                    <input id="remember" type="checkbox" name="remember">
                    <label for="remember" style="font-size:.94rem;">Remember me</label>
                </div>
                <a class="small-link" href="#">Lupa password?</a>
            </div>

            <div style="margin-top:14px;">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>

            <div style="display:flex;gap:8px;justify-content:center;margin-top:12px;">
                <a href="{{ url('/') }}" class="btn btn-secondary">Home</a>
            </div>

            <div class="login-footer">
                &copy; {{ date('Y') }} Document and Control — Peroni Karya Sentra
            </div>
        </form>

    </div>
</div>

<script>
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

@endsection
