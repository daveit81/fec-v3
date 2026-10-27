{{--
    Joomla 2.5's message renderer (JDocumentRendererMessage): the queue the
    component enqueued, grouped by type, inside #system-message-container.
    Empty on every public page; the desks get "Topic saved." after a redirect.
--}}@php
    $groups = [];
    foreach ($messages ?? [] as $m) {
        $groups[strtolower((string) ($m['type'] ?? 'message'))][] = (string) ($m['message'] ?? '');
    }
    $labels = ['message' => 'Message', 'error' => 'Error', 'notice' => 'Notice', 'warning' => 'Warning'];
@endphp
        <div id="system-message-container">
@if ($groups !== [])
<dl id="system-message">
@foreach ($groups as $type => $list)
<dt class="{{ $type }}">{{ $labels[$type] ?? ucfirst($type) }}</dt>
<dd class="{{ $type }} message">
	<ul>
@foreach ($list as $text)
		<li>{!! $text !!}</li>
@endforeach
	</ul>
</dd>
@endforeach
</dl>
@endif
        </div>
