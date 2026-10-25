<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name') }} &middot; fase 0c</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; padding: 40px 28px; font: 16px/1.5 Georgia, "Times New Roman", serif; background: #faf8f3; color: #1d1d1b; }
        @media (prefers-color-scheme: dark) { body { background: #15150f; color: #ebe7dc; } }
        .wrap { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 28px; font-weight: 600; margin: 0 0 4px; letter-spacing: -0.01em; }
        .kicker { font: 600 12px/1 system-ui, sans-serif; text-transform: uppercase; letter-spacing: .12em; opacity: .6; margin-bottom: 18px; }
        .ok { color: #1e7f4f; } .ko { color: #b3261e; }
        table { border-collapse: collapse; width: 100%; margin: 18px 0 28px; font-variant-numeric: tabular-nums; }
        th, td { text-align: left; padding: 8px 0; border-bottom: 1px solid rgba(128,128,128,.25); }
        td:last-child { text-align: right; }
        code { font: 14px/1.4 ui-monospace, SFMono-Regular, Menlo, monospace; }
        p.note { font-size: 14px; opacity: .7; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="kicker">FreeExtraChips &middot; v3 &middot; fase 0c &middot; scheletro</div>
    <h1>{{ $report['ok'] ? 'Tutto collegato.' : 'Qualcosa non risponde.' }}</h1>
    <p>Laravel {{ $report['laravel'] }} su PHP {{ $report['php'] }} ({{ $report['sapi'] }}), ambiente <code>{{ $report['env'] }}</code>, {{ $report['time'] }}.</p>

    <table>
        <tr><th>Database del framework</th><td class="{{ $report['framework_db']['ok'] ? 'ok' : 'ko' }}">{{ $report['framework_db']['ok'] ? $report['framework_db']['result'] : $report['framework_db']['error'] }}</td></tr>
        @if ($report['fec_db']['ok'])
            <tr><th>Catalogo FEC</th><td class="ok">{{ $report['fec_db']['result']['database'] }} &middot; MariaDB {{ $report['fec_db']['result']['server'] }}</td></tr>
            @foreach ($report['fec_db']['result']['tables'] as $table => $rows)
                <tr><th><code>jos_{{ $table }}</code></th><td>{{ number_format($rows, 0, ',', '.') }}</td></tr>
            @endforeach
        @else
            <tr><th>Catalogo FEC</th><td class="ko">{{ $report['fec_db']['error'] }}</td></tr>
        @endif
    </table>

    <p class="note">Pagina di servizio dietro Cloudflare Access; la stessa fotografia in JSON &egrave; su <a href="{{ url('/health') }}">/health</a>. Parit&agrave;, non miglioramento: il layout <code>.fecx</code> arriva con la prima pagina vera.</p>
</div>
</body>
</html>
