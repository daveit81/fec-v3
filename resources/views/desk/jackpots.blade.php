@extends('desk.layout')
@section('title', 'Jackpots')
@section('desk_class', 'desk--full')
@section('content')
@php($back = request()->getRequestUri())
<div class="top">
  <div><a href="{{ route('desk.home') }}" class="btn btn--ghost">← Desk</a></div>
  <form method="post" action="{{ route('logout') }}">@csrf <button class="btn btn--ghost" type="submit">Sign out</button></form>
</div>
<div class="card">
  <h1>Jackpots</h1>
  <p class="sub">
    The tracker's jackpots (<code>jos_jackpots</code>, updated every 15 minutes by <code>fec:jackpots:update</code>).
    Here you only decide whether a jackpot appears in the jackpot section of the casino reviews: name, currency,
    software and amount come from the tracker, and the cron rewrites them.
    Published {{ (int) ($counts[1] ?? 0) }}, hidden {{ (int) ($counts[0] ?? 0) }}.
  </p>

  @if (session('desk_ok'))<div class="ok">{{ session('desk_ok') }}</div>@endif
  @if (session('desk_error'))<div class="err">{{ session('desk_error') }}</div>@endif

  <form class="filters" method="get" action="{{ route('desk.jackpots') }}">
    <input type="text" name="q" value="{{ $q }}" placeholder="Name, jid or casino" autocomplete="off">
    <select name="published">
      <option value="" @selected($published === null)>All</option>
      <option value="1" @selected($published === 1)>Published</option>
      <option value="0" @selected($published === 0)>Hidden</option>
    </select>
    <select name="soft">
      <option value="">All software</option>
      @foreach ($softs as $value => $label)
        <option value="{{ $value }}" @selected($soft === (string) $value)>{{ $label }}</option>
      @endforeach
    </select>
    <button class="btn" type="submit">Filter</button>
    @if ($q !== '' || $published !== null || $soft !== '')<a class="btn btn--ghost" href="{{ route('desk.jackpots') }}">Clear</a>@endif
  </form>

  <div class="scroll">
  <table class="grid">
    <thead>
      <tr>
        <th>Jackpot</th><th>Jid</th><th>Casino</th><th>Software</th><th>Amount</th><th>Updated</th><th>Reviews</th>
      </tr>
    </thead>
    <tbody>
    @forelse ($rows as $row)
      @php($fresh = \App\Fec\Desk\JackpotsDesk::freshness($row->last_update, $now))
      <tr class="{{ (int) $row->published === 1 ? '' : 'off' }}">
        <td>{{ $row->name }}</td>
        <td><code>{{ $row->jid }}</code></td>
        <td>{{ (int) $row->cid === 0 ? 'All' : ($row->title ?? '#'.$row->cid) }}</td>
        <td>{{ $softs[$row->soft] ?? $row->soft }}</td>
        <td class="num">{{ $row->amount === null ? '–' : number_format((float) $row->amount, 2, ',', '.') }} {{ strtoupper((string) $row->currency) }}</td>
        <td><span class="tag tag--{{ strtolower($fresh) }}" title="{{ $row->last_update }}">{{ $fresh }}</span></td>
        <td>
          <form method="post" action="{{ route('desk.jackpots.publish') }}">
            @csrf
            <input type="hidden" name="jid" value="{{ $row->jid }}">
            <input type="hidden" name="back" value="{{ $back }}">
            @if ((int) $row->published === 1)
              <input type="hidden" name="published" value="0">
              <button class="btn btn--sm" type="submit" title="Remove from the jackpot section of the reviews">Published · hide</button>
            @else
              <input type="hidden" name="published" value="1">
              <button class="btn btn--sm btn--off" type="submit" title="Show in the jackpot section of the reviews">Hidden · publish</button>
            @endif
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="7">No jackpots.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>

  @if ($rows->lastPage() > 1)
  <div class="pager">
    @if ($rows->previousPageUrl())<a href="{{ $rows->previousPageUrl() }}">← Previous</a>@endif
    <span>Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }} · {{ $rows->total() }} jackpots</span>
    @if ($rows->nextPageUrl())<a href="{{ $rows->nextPageUrl() }}">Next →</a>@endif
  </div>
  @endif
</div>
@endsection
