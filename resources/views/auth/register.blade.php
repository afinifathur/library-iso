@extends('layouts.iso')

@section('content')
<h2 style="margin-top:0">Register (optional)</h2>

@if($errors->any())
  <div style="padding:8px;background:#fee2e2;border:1px solid #fecaca;border-radius:6px;margin-bottom:10px;color:#7f1d1d;">
    {!! implode('<br>', $errors->all()) !!}
  </div>
@endif

<form method="post" action="{{ route('register.attempt') }}">
  @csrf
  <div class="form-row"><label>Name</label><input type="text" name="name" value="{{ old('name') }}" required></div>
  <div class="form-row"><label>Email</label><input type="email" name="email" value="{{ old('email') }}" required></div>
  <div class="form-row"><label>Password</label><input type="password" name="password" required></div>
  <div class="form-row"><label>Confirm Password</label><input type="password" name="password_confirmation" required></div>
  <div style="margin-top:8px;">
    <button class="btn" type="submit">Register</button>
    <a class="btn-muted" href="{{ route('login') }}" style="margin-left:8px">Login</a>
  </div>
</form>
@endsection
