@extends('desk.layout')
@section('title', 'Messaggi')
@section('desk_class', 'desk--full')
@section('content')
<div class="top">
  <div><a href="{{ route('desk.home') }}" class="btn btn--ghost">← Desk</a></div>
  <form method="post" action="{{ route('logout') }}">@csrf <button class="btn btn--ghost" type="submit">Esci</button></form>
</div>
<div class="card">
  <h1>Messaggi dal form di contatto</h1>
  <p class="sub">
    Ogni messaggio di <code>/contactus/</code> arriva per mail alla casella del suo argomento e resta qui in copia.
    Da fare {{ (int) ($counts['new'] ?? 0) }}, gestiti {{ (int) ($counts['done'] ?? 0) }}.
  </p>

  <form class="filters" method="get" action="{{ route('desk.messages') }}">
    <select name="topic">
      <option value="">Ogni argomento</option>
      @foreach ($topics as $key => $t)
        <option value="{{ $key }}" @selected($topic === $key)>{{ $t['label'] }}</option>
      @endforeach
    </select>
    <select name="status">
      <option value="" @selected($status === '')>Tutti</option>
      <option value="new" @selected($status === 'new')>Da fare</option>
      <option value="done" @selected($status === 'done')>Gestiti</option>
    </select>
    <button class="btn" type="submit">Filtra</button>
    @if ($topic !== '' || $status !== '')<a class="btn btn--ghost" href="{{ route('desk.messages') }}">Azzera</a>@endif
  </form>

  <div class="scroll">
  <table class="grid">
    <thead>
      <tr><th>Quando (UTC)</th><th>Argomento</th><th>Da</th><th>Oggetto</th><th>Mail</th><th>Stato</th></tr>
    </thead>
    <tbody>
    @forelse ($rows as $row)
      <tr class="{{ $row->status === 'done' ? 'off' : '' }}">
        <td>{{ substr((string) $row->created_at, 0, 16) }}</td>
        <td>{{ $topics[$row->topic]['label'] ?? $row->topic }}</td>
        <td>{{ $row->name }}<br><small>{{ $row->email }}{{ $row->country ? ' · '.$row->country : '' }}</small></td>
        <td><a href="{{ route('desk.messages.show', $row->id) }}">{{ $row->subject }}</a></td>
        <td>
          @if ($row->mailed_at)<span class="tag tag--good">inviata</span>
          @elseif ($row->mail_error)<span class="tag tag--poor" title="{{ $row->mail_error }}">errore</span>
          @else<span class="tag">–</span>@endif
        </td>
        <td>{{ $row->status === 'done' ? 'gestito' : 'da fare' }}</td>
      </tr>
    @empty
      <tr><td colspan="6">Nessun messaggio.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>

  @if ($rows->lastPage() > 1)
  <div class="pager">
    @if ($rows->previousPageUrl())<a href="{{ $rows->previousPageUrl() }}">← Più recenti</a>@endif
    <span>Pagina {{ $rows->currentPage() }} di {{ $rows->lastPage() }} · {{ $rows->total() }} messaggi</span>
    @if ($rows->nextPageUrl())<a href="{{ $rows->nextPageUrl() }}">Meno recenti →</a>@endif
  </div>
  @endif
</div>
@endsection
