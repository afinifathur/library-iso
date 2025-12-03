@extends('layouts.iso') {{-- atau 'layouts.app' kalau kamu masih nama itu --}}

@section('content')
<h2 style="margin-top:0">Login</h2>

@if($errors->any())
  <div style="padding:8px;background:#fee2e2;border:1px solid #fecaca;border-radius:6px;margin-bottom:10px;color:#7f1d1d;">
    {{ $errors->first() }}
  </div>
@endif

<form method="post" action="{{ route('login.attempt') }}">
  @csrf
  <div class="form-row">
    <label>Email</label>
    <input type="text" name="email" value="{{ old('email') }}" required>
  </div>

  <div class="form-row">
    <label>Password</label>
    <input type="password" name="password" required>
  </div>

  <div class="form-row">
    <label><input type="checkbox" name="remember"> Remember me</label>
  </div>

  <div style="margin-top:8px;">
    <button class="btn" type="submit">Login</button>
    <a class="btn-muted" href="{{ url('/') }}" style="margin-left:8px">Home</a>
  </div>
</form>
@endsection
