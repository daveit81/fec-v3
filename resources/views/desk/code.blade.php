@extends('desk.layout')
@section('title', 'Codice')
@section('content')
<div class="card">
  <h1>Controlla la posta</h1>
  <p class="sub">Ti abbiamo inviato un codice di sei cifre: vale dieci minuti, una sola volta.</p>
  @if ($errors->any())
    <div class="err">{{ $errors->first() }}</div>
  @endif
  <form method="post" action="{{ route('desk.code.post') }}">
    @csrf
    <label for="code">Codice</label>
    <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
    <button class="btn" type="submit">Entra</button>
  </form>
  <form method="get" action="{{ route('login') }}"><button class="btn btn--ghost" type="submit">Ricomincia</button></form>
</div>
@endsection
