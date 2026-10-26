{{-- tmpl=component: Joomla's bare shell (templates/fecv2/component.php), used by printable views such as fxreport. --}}
<!doctype html>
<html lang="en-gb" dir="ltr">
<head>
  <meta http-equiv="content-type" content="text/html; charset=utf-8" />
{!! $doc->renderMeta() !!}
  <title>{{ $doc->title }}</title>
{!! $doc->renderLinks() !!}
{!! $doc->renderStyles() !!}
{!! $doc->renderScripts() !!}
{!! $doc->renderTail() !!}
</head>
<body class="contentpane">
{!! $body !!}
</body>
</html>
