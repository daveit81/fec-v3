@extends('desk.layout')
@section('title', 'Message')
@section('desk_class', 'desk--wide')
@section('content')
@php($topic = $topics[$row->topic] ?? ['label' => $row->topic])
<div class="top">
  <div><a href="{{ route('desk.messages') }}" class="btn btn--ghost">← Messages</a></div>
  <form method="post" action="{{ route('logout') }}">@csrf <button class="btn btn--ghost" type="submit">Sign out</button></form>
</div>
<div class="card">
  @if (session('desk_ok'))<div class="ok">{{ session('desk_ok') }}</div>@endif
  <h1>{{ $row->subject }}</h1>
  <p class="sub">
    {{ $topic['label'] }} · {{ substr((string) $row->created_at, 0, 16) }} UTC · to <code>{{ $row->recipient }}</code>
    @if ($row->mailed_at) · email sent @elseif ($row->mail_error) · <strong>email not sent</strong>: {{ $row->mail_error }} @endif
  </p>
  <p><strong>{{ $row->name }}</strong> &lt;<a href="mailto:{{ $row->email }}?subject={{ rawurlencode('Re: '.$row->subject) }}">{{ $row->email }}</a>&gt;{{ $row->country ? ' · '.$row->country : '' }}</p>
  @if ($row->page_url)
    <p>Page: <a href="{{ $row->page_url }}" rel="noopener noreferrer nofollow" target="_blank">{{ $row->page_url }}</a></p>
  @endif
  <div style="white-space: pre-wrap; border-top: 1px solid #eef0f2; padding-top: 14px; margin-top: 10px;">{{ $row->message }}</div>

  <form method="post" action="{{ route('desk.messages.status', $row->id) }}" style="margin-top: 18px;">
    @csrf
    @if ($row->status === 'done')
      <input type="hidden" name="status" value="new">
      <button class="btn btn--off" type="submit">Handled · move back to to-do</button>
    @else
      <input type="hidden" name="status" value="done">
      <button class="btn" type="submit">Mark as handled</button>
    @endif
  </form>
</div>
@endsection
