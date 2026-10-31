@extends('desk.layout')
@section('title', 'Affiliate links')
@section('desk_class', 'desk--full')
@section('content')
<div class="top">
  <div><a href="{{ route('desk.home') }}" class="btn btn--ghost">← Desk</a></div>
  <form method="post" action="{{ route('logout') }}">@csrf <button class="btn btn--ghost" type="submit">Sign out</button></form>
</div>
<div class="card">
  <div class="top" style="margin-bottom:4px">
    <h1>Affiliate links</h1>
    <a class="btn btn--sm" href="{{ route('desk.links.create') }}">+ New link</a>
  </div>
  <p class="sub">
    The phrases of <code>/goto.php?link=…</code> (<code>jos_shortlink</code>, formerly com_shortlink in the Joomla backend).
    Each one sends the visitor to its destination in one redirect, and the click is logged in <code>jos_bonus_clicks</code>.
    {{ number_format($total) }} links, {{ number_format($inUse) }} used by a casino, a bonus, an article or a module, {{ number_format($never) }} never called.
  </p>

  @if (session('desk_ok'))<div class="ok">{{ session('desk_ok') }}</div>@endif
  @if (session('desk_error'))<div class="err">{{ session('desk_error') }}</div>@endif

  <form class="filters" method="get" action="{{ route('desk.links') }}">
    <input type="text" name="q" value="{{ $q }}" placeholder="Phrase, destination or description" autocomplete="off">
    <select name="show">
      <option value="all" @selected($show === 'all')>All links</option>
      <option value="unused" @selected($show === 'unused')>Not used by any page</option>
      <option value="idle" @selected($show === 'idle')>No call in 30 days</option>
      <option value="never" @selected($show === 'never')>Never called</option>
    </select>
    <select name="sort">
      <option value="newest" @selected($sort === 'newest')>Newest first</option>
      <option value="phrase" @selected($sort === 'phrase')>Phrase A–Z</option>
      <option value="hits" @selected($sort === 'hits')>Most hits</option>
      <option value="last_call" @selected($sort === 'last_call')>Last call</option>
    </select>
    <button class="btn" type="submit">Filter</button>
    @if ($q !== '' || $show !== 'all' || $sort !== 'newest')<a class="btn btn--ghost" href="{{ route('desk.links') }}">Clear</a>@endif
  </form>

  <div class="scroll">
  <table class="grid">
    <thead>
      <tr>
        <th>Phrase</th><th>Destination</th><th>Used by</th><th class="num">Clicks 30d</th><th class="num">Hits</th><th>Last call (UTC)</th><th>Created</th>
      </tr>
    </thead>
    <tbody>
    @forelse ($rows as $row)
      @php($u = $usage[\App\Fec\Desk\LinksDesk::key((string) $row->phrase)] ?? null)
      @php($last = \App\Fec\Desk\LinksDesk::when($row->last_call))
      <tr class="{{ $u ? '' : 'off' }}">
        <td><a href="{{ route('desk.links.edit', $row->id) }}"><strong>{{ $row->phrase }}</strong></a>
          @if ($row->description !== '')<br><small>{{ $row->description }}</small>@endif</td>
        <td class="dest" title="{{ $row->link }}"><strong>{{ \App\Fec\Desk\LinksDesk::host((string) $row->link) }}</strong><br><small>{{ \Illuminate\Support\Str::limit((string) $row->link, 70) }}</small></td>
        <td>
          @if ($u)
            @foreach (array_slice($u['casinos'], 0, 2) as $c){{ $c['title'] }}@if (! $loop->last), @endif @endforeach
            @if (count($u['casinos']) > 2) +{{ count($u['casinos']) - 2 }}@endif
            @if ($u['bonuses'] !== [])<br><small>{{ count($u['bonuses']) }} {{ count($u['bonuses']) === 1 ? 'bonus' : 'bonuses' }}</small>@endif
            @if ($u['articles'] !== [])<br><small>{{ count($u['articles']) }} {{ count($u['articles']) === 1 ? 'article' : 'articles' }}</small>@endif
            @if ($u['modules'] !== [])<br><small>{{ count($u['modules']) }} {{ count($u['modules']) === 1 ? 'module' : 'modules' }}</small>@endif
          @else
            <span class="tag">not used</span>
          @endif
        </td>
        <td class="num">{{ $clicks[\App\Fec\Desk\LinksDesk::key((string) $row->phrase)] ?? 0 }}</td>
        <td class="num">{{ number_format((int) $row->counter) }}</td>
        <td>{{ $last !== '' ? $last : 'never' }}</td>
        <td>{{ substr(\App\Fec\Desk\LinksDesk::when($row->create_date), 0, 10) }}</td>
      </tr>
    @empty
      <tr><td colspan="7">No links.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>

  @if ($rows->lastPage() > 1)
  <div class="pager">
    @if ($rows->previousPageUrl())<a href="{{ $rows->previousPageUrl() }}">← Previous</a>@endif
    <span>Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }} · {{ $rows->total() }} links</span>
    @if ($rows->nextPageUrl())<a href="{{ $rows->nextPageUrl() }}">Next →</a>@endif
  </div>
  @endif
</div>
@endsection
