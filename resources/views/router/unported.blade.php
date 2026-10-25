@extends('layouts.fecx')

@section('title', 'Non ancora portata · FEC v3 — FreeExtraChips')
@section('robots', 'noindex, nofollow, noarchive')
@section('canonical', url($path))

@section('content')
@php($u = $decision->url)
<div class="wrap"><nav class="fec-breadcrumb" aria-label="Breadcrumb"><ol><li><a href="{{ rtrim(config('fec.site_url'), '/') }}/">Home</a></li><li><a href="{{ url('/') }}">v3</a></li><li aria-current="page">router</li></ol></nav></div>

<section class="tm-hero">
  <div class="wrap tm-hero-grid">
    <div>
      <div class="eyebrow">FEC v3 · fase 1 · router</div>
      @if ($decision->action === 'not_ported')
        <h1 class="tm-title">Pagina nota, <em>non ancora portata</em>.</h1>
        <p class="tm-intro">Il registro conosce questo path ({{ $u->source }}) e lo risolve in <code>{{ $u->routeKey() }}</code>, ma v3 non ha ancora un handler per quella vista: sul sito vivo risponde Joomla. Quando la sezione passa, qui comparirà la pagina con lo stesso markup.</p>
      @else
        <h1 class="tm-title">Path <em>sconosciuto</em>.</h1>
        <p class="tm-intro">{{ $u->kind === 'gone' ? 'sh404SEF ha registrato un 404 per questo path: anche il sito vivo risponde 404.' : 'Né le regole di redirect, né i pattern, né il registro URL conoscono questo path.' }}</p>
      @endif
    </div>
    <div class="tm-hero-stats">
      <div class="tm-hero-stat"><div class="num">{{ $decision->status }}</div><div class="lbl">{{ $decision->action }}</div></div>
      <div class="tm-hero-stat"><div class="num">{{ $u->kind }}</div><div class="lbl">{{ $u->source }}</div></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="sec-head"><div class="left"><h2 class="sec-title">Decisione del router</h2></div></div>
    <pre style="white-space:pre-wrap;word-break:break-word;background:#111;color:#ddd;padding:1rem;border-radius:6px;font-size:.85rem">{{ json_encode($decision->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
    <p class="tm-intro">Sonda JSON: <a href="{{ url('/__registry') }}?path={{ urlencode($path) }}">/__registry?path={{ $path }}</a> · da riga di comando: <code>php artisan fec:urls:resolve {{ $path }}</code></p>
  </div>
</section>
@endsection
