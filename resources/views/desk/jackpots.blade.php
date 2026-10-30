@extends('desk.layout')
@section('title', 'Jackpot')
@section('desk_class', 'desk--full')
@section('content')
@php($back = request()->getRequestUri())
<div class="top">
  <div><a href="{{ route('desk.home') }}" class="btn btn--ghost">← Desk</a></div>
  <form method="post" action="{{ route('logout') }}">@csrf <button class="btn btn--ghost" type="submit">Esci</button></form>
</div>
<div class="card">
  <h1>Jackpot</h1>
  <p class="sub">
    I jackpot del tracker (<code>jos_jackpots</code>, aggiornati ogni 15 minuti da <code>fec:jackpots:update</code>).
    Qui si decide solo se un jackpot compare nella sezione jackpot delle recensioni: nome, valuta, software e importo
    vengono dal tracker e il cron li riscrive.
    Pubblicati {{ (int) ($counts[1] ?? 0) }}, nascosti {{ (int) ($counts[0] ?? 0) }}.
  </p>

  @if (session('desk_ok'))<div class="ok">{{ session('desk_ok') }}</div>@endif
  @if (session('desk_error'))<div class="err">{{ session('desk_error') }}</div>@endif

  <form class="filters" method="get" action="{{ route('desk.jackpots') }}">
    <input type="text" name="q" value="{{ $q }}" placeholder="Nome, jid o casinò" autocomplete="off">
    <select name="published">
      <option value="" @selected($published === null)>Tutti</option>
      <option value="1" @selected($published === 1)>Pubblicati</option>
      <option value="0" @selected($published === 0)>Nascosti</option>
    </select>
    <select name="soft">
      <option value="">Ogni software</option>
      @foreach ($softs as $value => $label)
        <option value="{{ $value }}" @selected($soft === (string) $value)>{{ $label }}</option>
      @endforeach
    </select>
    <button class="btn" type="submit">Filtra</button>
    @if ($q !== '' || $published !== null || $soft !== '')<a class="btn btn--ghost" href="{{ route('desk.jackpots') }}">Azzera</a>@endif
  </form>

  <div class="scroll">
  <table class="grid">
    <thead>
      <tr>
        <th>Jackpot</th><th>Jid</th><th>Casinò</th><th>Software</th><th>Importo</th><th>Aggiornato</th><th>Recensioni</th>
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
              <button class="btn btn--sm" type="submit" title="Togli dalla sezione jackpot delle recensioni">Pubblicato · nascondi</button>
            @else
              <input type="hidden" name="published" value="1">
              <button class="btn btn--sm btn--off" type="submit" title="Mostra nella sezione jackpot delle recensioni">Nascosto · pubblica</button>
            @endif
          </form>
        </td>
      </tr>
    @empty
      <tr><td colspan="7">Nessun jackpot.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>

  @if ($rows->lastPage() > 1)
  <div class="pager">
    @if ($rows->previousPageUrl())<a href="{{ $rows->previousPageUrl() }}">← Precedenti</a>@endif
    <span>Pagina {{ $rows->currentPage() }} di {{ $rows->lastPage() }} · {{ $rows->total() }} jackpot</span>
    @if ($rows->nextPageUrl())<a href="{{ $rows->nextPageUrl() }}">Successivi →</a>@endif
  </div>
  @endif
</div>
@endsection
