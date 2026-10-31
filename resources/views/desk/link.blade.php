@extends('desk.layout')
@section('title', $row ? 'Link '.$row->phrase : 'New link')
@section('desk_class', 'desk--wide')
@section('content')
@php($locked = $row && $uses)
<div class="top">
  <div><a href="{{ route('desk.links') }}" class="btn btn--ghost">← Affiliate links</a></div>
  <form method="post" action="{{ route('logout') }}">@csrf <button class="btn btn--ghost" type="submit">Sign out</button></form>
</div>
<div class="card">
  <h1>{{ $row ? $row->phrase : 'New link' }}</h1>
  <p class="sub">
    @if ($row)
      Link #{{ $row->id }}, created {{ \App\Fec\Desk\LinksDesk::when($row->create_date) ?: 'on an unknown date' }} (UTC).
      A new destination works from the next click: nothing else to update.
    @else
      A phrase for <code>/goto.php?link=…</code>: put the address it gives you in the casino's link (or the bonus's).
    @endif
  </p>

  @if (session('desk_ok'))<div class="ok">{{ session('desk_ok') }}</div>@endif
  @if (session('desk_error'))<div class="err">{{ session('desk_error') }}</div>@endif
  @if ($errors->any())
    <div class="err">@foreach ($errors->all() as $e){{ $e }}@if (! $loop->last)<br>@endif @endforeach</div>
  @endif

  <form method="post" action="{{ $row ? route('desk.links.update', $row->id) : route('desk.links.store') }}">
    @csrf
    <label for="phrase">Phrase</label>
    <input type="text" id="phrase" name="phrase" maxlength="100" autocomplete="off"
           value="{{ old('phrase', $row->phrase ?? '') }}" @if ($locked) readonly @endif>
    @if ($locked)<small class="hint">In use: it cannot be renamed (the casinos and bonuses below would lose their link).</small>@endif

    <label for="link">Destination</label>
    <input type="text" id="link" name="link" maxlength="255" autocomplete="off" placeholder="https://..."
           value="{{ old('link', $row->link ?? '') }}">
    <small class="hint">The affiliate URL, as the program gives it. The landing-page subid is added at click time for the programs that have it enabled.</small>

    <label for="description">Description <span class="opt">(optional)</span></label>
    <input type="text" id="description" name="description" maxlength="255" autocomplete="off"
           value="{{ old('description', $row->description ?? '') }}">

    <button class="btn" type="submit">{{ $row ? 'Save' : 'Create link' }}</button>
  </form>

  @if ($row)
    <h2>Use it</h2>
    <label for="gourl">Address for the site</label>
    <input type="text" id="gourl" value="{{ $goUrl }}" readonly onclick="this.select()">
    <small class="hint">Sends to: <code>{{ $resolves }}</code></small>

    <h2>Clicks</h2>
    @if ($stats)
      <table class="grid">
        <tr><td>Last 30 days (people)</td><td class="num">{{ number_format($stats['clicks30']) }}</td></tr>
        <tr><td>Last 30 days (bots)</td><td class="num">{{ number_format($stats['bots30']) }}</td></tr>
        <tr><td>All time, <code>jos_bonus_clicks</code></td><td class="num">{{ number_format($stats['clicks']) }}</td></tr>
        <tr><td>Last click (UTC)</td><td class="num">{{ $stats['last'] !== '' ? $stats['last'] : '–' }}</td></tr>
        <tr><td>Hits, com_shortlink counter</td><td class="num">{{ number_format((int) $row->counter) }}</td></tr>
      </table>
    @else
      <p class="sub">Click counts unavailable.</p>
    @endif

    <h2>Used by</h2>
    @if ($uses)
      <ul class="uses">
        @foreach ($uses['casinos'] as $c)<li>Casino: {{ $c['title'] }} <small>(article {{ $c['id'] }}, jr_gourl)</small></li>@endforeach
        @foreach ($uses['bonuses'] as $b)<li>Bonus {{ $b['id'] }}: {{ $b['title'] }}</li>@endforeach
        @foreach ($uses['articles'] as $a)<li>Article {{ $a['id'] }}: {{ $a['title'] }}@if ($a['state'] !== 1) <small>(state {{ $a['state'] }})</small>@endif</li>@endforeach
        @foreach ($uses['modules'] as $m)<li>Module {{ $m['id'] }}: {{ $m['title'] }}@if ($m['state'] !== 1) <small>(unpublished)</small>@endif</li>@endforeach
      </ul>
    @else
      <p class="sub">No casino, bonus, article or module uses this phrase.</p>
      <form method="post" action="{{ route('desk.links.destroy', $row->id) }}"
            data-confirm="Delete the link {{ $row->phrase }}? A visitor with an old address will land on the home page."
            onsubmit="return confirm(this.dataset.confirm)">
        @csrf
        <button class="btn btn--danger" type="submit">Delete this link</button>
      </form>
    @endif
  @endif
</div>
@endsection
