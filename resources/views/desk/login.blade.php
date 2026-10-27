@extends('desk.layout')
@section('title', 'Accesso')
@section('content')
<div class="card">
  <h1>Desk FEC v3</h1>
  <p class="sub">Redazione: email e password, poi il codice che arriva via email.</p>
  @if ($errors->any())
    <div class="err">{{ $errors->first() }}</div>
  @endif
  <form method="post" action="{{ route('login.post') }}">
    @csrf
    <label for="email">Email</label>
    <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <label class="remember"><input type="checkbox" name="remember" value="1"> Ricordami su questo dispositivo</label>
    <button class="btn" type="submit">Continua</button>
  </form>
</div>
@endsection
