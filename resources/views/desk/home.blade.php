@extends('desk.layout')
@section('title', 'Home')
@section('desk_class', 'desk--wide')
@section('content')
<div class="top">
  <div><strong>{{ auth()->user()->name }}</strong> · {{ auth()->user()->role }} · utente legacy #{{ auth()->user()->joomla_user_id ?? '-' }}</div>
  <form method="post" action="{{ route('logout') }}">@csrf <button class="btn btn--ghost" type="submit">Esci</button></form>
</div>
<div class="card">
  <h1>Desk</h1>
  <p class="sub">Le maschere della redazione portate su v3. Sullo stesso codice del sito vivo, con i permessi di scrittura di <code>fec_rw</code>.</p>
  <ul class="links">
    @foreach ($desks as $desk)
      <li><a href="{{ $desk['url'] }}">{{ $desk['title'] }}</a><small>{{ $desk['note'] }}</small></li>
    @endforeach
  </ul>
</div>
@endsection
