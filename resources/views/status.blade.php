@extends('layouts.fecx')

@section('title', 'FEC v3 · fase 0c — FreeExtraChips')
@section('robots', 'noindex, nofollow, noarchive')
@section('canonical', url('/'))

@section('content')
<div class="wrap"><nav class="fec-breadcrumb" aria-label="Breadcrumb"><ol><li><a href="{{ rtrim(config('fec.site_url'), '/') }}/">Home</a></li><li aria-current="page">v3</li></ol></nav></div>

<section class="tm-hero">
  <div class="wrap tm-hero-grid">
    <div>
      <div class="eyebrow">FEC v3 · fase 0c · scheletro</div>
      <h1 class="tm-title">{!! $report['ok'] ? 'Tutto <em>collegato</em>.' : 'Qualcosa <em>non risponde</em>.' !!}</h1>
      <p class="tm-intro">
        Laravel {{ $report['laravel'] }} su PHP {{ $report['php'] }} ({{ $report['sapi'] }}), ambiente {{ $report['env'] }}.
        Il guscio che vedi è il porting del template fecv2: stessi marcatori, stessi fogli di stile serviti dal sito vivo.
        Parità, non miglioramento.
      </p>
    </div>
    <div class="tm-hero-stats">
      @if ($report['fec_db']['ok'])
        @php($t = $report['fec_db']['result']['tables'])
        <div class="tm-hero-stat"><div class="num">{{ number_format($t['bonuses'], 0, ',', '.') }}</div><div class="lbl">Bonus in catalogo</div></div>
        <div class="tm-hero-stat"><div class="num">{{ number_format($t['jreviews_content'], 0, ',', '.') }}</div><div class="lbl">Schede casinò</div></div>
        <div class="tm-hero-stat"><div class="num">{{ number_format($t['content'], 0, ',', '.') }}</div><div class="lbl">Articoli</div></div>
        <div class="tm-hero-stat"><div class="num">{{ number_format($t['sh404sef_urls'], 0, ',', '.') }}</div><div class="lbl">URL nel registro</div></div>
      @else
        <div class="tm-hero-stat"><div class="num">—</div><div class="lbl">Catalogo non raggiungibile</div></div>
        <div class="tm-hero-stat"><div class="num">—</div><div class="lbl">{{ \Illuminate\Support\Str::limit($report['fec_db']['error'], 60) }}</div></div>
      @endif
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="sec-head">
      <div class="left">
        <h2 class="sec-title">Collegamenti</h2>
        <p class="sec-sub">Database del framework e catalogo legacy letti in questo istante ({{ $report['time'] }}).</p>
      </div>
      <a class="sec-link" href="{{ url('/health') }}">La stessa fotografia in JSON →</a>
    </div>

    <div class="tm-hero-stats">
      <div class="tm-hero-stat">
        <div class="num">{{ $report['framework_db']['ok'] ? 'ok' : 'ko' }}</div>
        <div class="lbl">Framework DB · {{ $report['framework_db']['ok'] ? $report['framework_db']['result'] : 'errore' }}</div>
      </div>
      <div class="tm-hero-stat">
        <div class="num">{{ $report['fec_db']['ok'] ? 'ok' : 'ko' }}</div>
        <div class="lbl">Catalogo · {{ $report['fec_db']['ok'] ? $report['fec_db']['result']['database'].' · MariaDB '.$report['fec_db']['result']['server'] : 'errore' }}</div>
      </div>
      @if ($report['fec_db']['ok'])
        <div class="tm-hero-stat"><div class="num">{{ number_format($t['bonus_clicks'], 0, ',', '.') }}</div><div class="lbl">Click tracciati (jos_bonus_clicks)</div></div>
        <div class="tm-hero-stat"><div class="num">{{ number_format($t['redirection'], 0, ',', '.') }}</div><div class="lbl">Regole di redirect (jos_redirection)</div></div>
      @endif
    </div>
  </div>
</section>
@endsection
