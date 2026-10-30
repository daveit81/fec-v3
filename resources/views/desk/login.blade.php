@extends('desk.layout')
@section('title', 'Sign in')
@section('content')
<div class="card">
  <h1>FEC v3 desk</h1>
  <p class="sub">Editorial team: your email and password, then the code we email you.</p>
  @if ($errors->any())
    <div class="err">{{ $errors->first() }}</div>
  @endif
  <form method="post" action="{{ route('login.post') }}">
    @csrf
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <label class="remember"><input type="checkbox" name="remember" value="1"> Remember me on this device</label>
    <button class="btn" type="submit">Continue</button>
  </form>
</div>
@endsection
