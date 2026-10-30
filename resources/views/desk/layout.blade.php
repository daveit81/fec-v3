{{-- Desk pages of v3 (login, home): a small standalone shell, not the public .fecx one. --}}
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Desk') · FEC v3</title>
<style>
  :root { color-scheme: light; }
  body { margin: 0; font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f4f5f7; color: #1b1f24; }
  .desk { max-width: 420px; margin: 8vh auto; padding: 0 16px; }
  .desk--wide { max-width: 760px; }
  .desk--full { max-width: 1240px; margin-top: 4vh; }
  table.grid { width: 100%; border-collapse: collapse; font-size: 14px; }
  table.grid th, table.grid td { text-align: left; padding: 7px 8px; border-top: 1px solid #eef0f2; vertical-align: middle; }
  table.grid th { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #5c6670; border-top: 0; }
  table.grid td.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
  table.grid tr.off td { color: #8a939c; }
  table.grid form { margin: 0; }
  .filters { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0 0 16px; }
  .filters input[type=text] { width: 260px; }
  .filters select, .filters input[type=text] { padding: 8px 10px; border: 1px solid #c9ced4; border-radius: 6px; font: inherit; background: #fff; }
  .filters .btn { margin-top: 0; padding: 8px 14px; }
  .btn--sm { margin-top: 0; padding: 4px 10px; font-size: 13px; }
  .btn--off { background: #8a939c; }
  .tag { display: inline-block; padding: 1px 7px; border-radius: 10px; font-size: 12px; background: #eef0f2; color: #3c444c; }
  .tag--good { background: #e8f5f1; color: #14523f; }
  .tag--fair { background: #fff4dc; color: #7a5300; }
  .tag--poor { background: #fdecec; color: #8a1f1f; }
  .pager { display: flex; gap: 12px; align-items: center; margin-top: 16px; font-size: 14px; }
  .pager a { color: #1d6f5f; font-weight: 600; text-decoration: none; }
  .scroll { overflow-x: auto; }
  .card { background: #fff; border: 1px solid #e3e6ea; border-radius: 10px; padding: 24px 28px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  h1 { font-size: 20px; margin: 0 0 4px; }
  .sub { color: #5c6670; margin: 0 0 20px; font-size: 14px; }
  label { display: block; font-weight: 600; margin: 14px 0 6px; }
  input[type=email], input[type=password], input[type=text] { width: 100%; box-sizing: border-box; padding: 10px 12px; border: 1px solid #c9ced4; border-radius: 6px; font: inherit; }
  input:focus { outline: 2px solid #1d6f5f; border-color: #1d6f5f; }
  .btn { display: inline-block; margin-top: 18px; padding: 10px 18px; border: 0; border-radius: 6px; background: #1d6f5f; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
  .btn--ghost { background: transparent; color: #1d6f5f; padding: 6px 0; }
  .err { background: #fdecec; border: 1px solid #f3b9b9; color: #8a1f1f; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px; font-size: 14px; }
  .ok { background: #e8f5f1; border: 1px solid #b7e0d3; color: #14523f; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px; font-size: 14px; }
  .remember { display: flex; gap: 8px; align-items: center; margin-top: 12px; font-weight: 400; }
  .links { list-style: none; padding: 0; margin: 0; }
  .links li { border-top: 1px solid #eef0f2; padding: 12px 0; }
  .links a { color: #1d6f5f; font-weight: 600; text-decoration: none; }
  .links small { display: block; color: #5c6670; }
  .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .top form { margin: 0; }
  code { background: #f0f2f4; padding: 1px 5px; border-radius: 4px; }
</style>
</head>
<body>
<div class="desk @yield('desk_class')">
@yield('content')
</div>
</body>
</html>
