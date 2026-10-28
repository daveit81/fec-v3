{{--
    A ported Joomla component rendered inside the .fecx shell: the body the
    component echoed, and its document head pieces in the slots Joomla's
    head renderer would have filled (see layouts/fecx.blade.php).
--}}
@extends('layouts.fecx')

@section('title', $doc->title !== '' ? $doc->title : 'FreeExtraChips')

@if ($canonical !== null)
@section('canonical', $canonical)
@endif

@section('head_meta')
{!! $doc->renderMeta() !!}
@endsection

@section('head_links')
{!! $doc->renderLinks() !!}
@endsection

@section('head_styles')
{!! $doc->renderStyles() !!}
@endsection

@section('head_scripts')
{!! $doc->renderScripts() !!}
@endsection

@section('head_tail')
{!! $doc->renderTail() !!}
@endsection

@if (($headAfter ?? '') !== '')
@section('head_after')
{!! $headAfter !!}
@endsection
@endif

@section('body_class', $bodyClass)
@section('main_class', $mainClass ?? '')

@section('content')
{!! $body !!}
@endsection
