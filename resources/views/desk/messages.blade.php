@extends('desk.layout')
@section('title', 'Messages')
@section('desk_class', 'desk--full')
@section('content')
<div class="top">
  <div><a href="{{ route('desk.home') }}" class="btn btn--ghost">← Desk</a></div>
  <form method="post" action="{{ route('logout') }}">@csrf <button class="btn btn--ghost" type="submit">Sign out</button></form>
</div>
<div class="card">
  <h1>Contact form messages</h1>
  <p class="sub">
    Every message sent from <code>/contactus/</code> is emailed to its topic's mailbox and kept here as a copy.
    To do {{ (int) ($counts['new'] ?? 0) }}, handled {{ (int) ($counts['done'] ?? 0) }}.
  </p>

  <form class="filters" method="get" action="{{ route('desk.messages') }}">
    <select name="topic">
      <option value="">All topics</option>
      @foreach ($topics as $key => $t)
        <option value="{{ $key }}" @selected($topic === $key)>{{ $t['label'] }}</option>
      @endforeach
    </select>
    <select name="status">
      <option value="" @selected($status === '')>All</option>
      <option value="new" @selected($status === 'new')>To do</option>
      <option value="done" @selected($status === 'done')>Handled</option>
    </select>
    <button class="btn" type="submit">Filter</button>
    @if ($topic !== '' || $status !== '')<a class="btn btn--ghost" href="{{ route('desk.messages') }}">Clear</a>@endif
  </form>

  <div class="scroll">
  <table class="grid">
    <thead>
      <tr><th>Received (UTC)</th><th>Topic</th><th>From</th><th>Subject</th><th>Email</th><th>Status</th></tr>
    </thead>
    <tbody>
    @forelse ($rows as $row)
      <tr class="{{ $row->status === 'done' ? 'off' : '' }}">
        <td>{{ substr((string) $row->created_at, 0, 16) }}</td>
        <td>{{ $topics[$row->topic]['label'] ?? $row->topic }}</td>
        <td>{{ $row->name }}<br><small>{{ $row->email }}{{ $row->country ? ' · '.$row->country : '' }}</small></td>
        <td><a href="{{ route('desk.messages.show', $row->id) }}">{{ $row->subject }}</a></td>
        <td>
          @if ($row->mailed_at)<span class="tag tag--good">sent</span>
          @elseif ($row->mail_error)<span class="tag tag--poor" title="{{ $row->mail_error }}">failed</span>
          @else<span class="tag">–</span>@endif
        </td>
        <td>{{ $row->status === 'done' ? 'handled' : 'to do' }}</td>
      </tr>
    @empty
      <tr><td colspan="6">No messages.</td></tr>
    @endforelse
    </tbody>
  </table>
  </div>

  @if ($rows->lastPage() > 1)
  <div class="pager">
    @if ($rows->previousPageUrl())<a href="{{ $rows->previousPageUrl() }}">← Newer</a>@endif
    <span>Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }} · {{ $rows->total() }} messages</span>
    @if ($rows->nextPageUrl())<a href="{{ $rows->nextPageUrl() }}">Older →</a>@endif
  </div>
  @endif
</div>
@endsection
