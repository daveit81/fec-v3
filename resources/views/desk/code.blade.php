@extends('desk.layout')
@section('title', 'Sign-in code')
@section('content')
<div class="card">
  <h1>Check your email</h1>
  <p class="sub">We have sent you a six-digit code. It works once, for ten minutes.</p>
  @if ($errors->any())
    <div class="err">{{ $errors->first() }}</div>
  @endif
  <form method="post" action="{{ route('desk.code.post') }}">
    @csrf
    <label for="code">Code</label>
    <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
    <button class="btn" type="submit">Sign in</button>
  </form>
  <form method="get" action="{{ route('login') }}"><button class="btn btn--ghost" type="submit">Start again</button></form>
</div>
@endsection
