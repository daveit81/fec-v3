{{--
    FEC v3 shell: a faithful port of templates/fecv2/index.php (the .fecx
    editorial design system). Same markup, same classes, same stylesheets
    served from the live docroot. Parity, not improvement.

    Sections:  title, meta_description, robots, canonical, body_class, content
    Stacks:    head (extra <link>/<meta>), scripts (end of body)
--}}
@php
    $geo = \App\Fec\Geo::fromRequest(request());
    $assets = rtrim(config('fec.asset_base'), '/');
    $siteUrl = rtrim(config('fec.site_url'), '/');
    $gtm = config('app.env') === 'production' ? config('fec.gtm_id') : null;
@endphp
<!doctype html>
<html lang="en-gb" dir="ltr">
<head>
@hasSection('canonical')
<link href="@yield('canonical')" rel="canonical" />
@endif

<meta name="viewport" content="width=device-width, initial-scale=1">
<script>
  window.FEC_COUNTRY = @json($geo->country);
  window.FEC_IS_EU   = {{ $geo->isEu ? 'true' : 'false' }};

  window.dataLayer = window.dataLayer || [];
  function gtag(){ dataLayer.push(arguments); }

  gtag('consent', 'default', {
    analytics_storage: window.FEC_IS_EU ? 'denied' : 'granted',
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied'
  });
</script>

@if ($gtm)
<!-- Google Tag Manager -->
<script>
  (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
  new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
  j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
  'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
  })(window,document,'script','dataLayer',@json($gtm));
</script>
@endif

    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
@hasSection('meta_description')
  <meta name="description" content="@yield('meta_description')" />
@endif
@hasSection('robots')
  <meta name="robots" content="@yield('robots')" />
@endif

  <title>@yield('title', 'FreeExtraChips')</title>
  <link href="{{ $assets }}/favicon.ico" rel="shortcut icon" type="image/vnd.microsoft.icon" />
  <link rel="stylesheet" href="{{ $assets }}/css/fecv2.css" type="text/css" />
  <link rel="stylesheet" href="{{ $assets }}/css/fec-edition.css" type="text/css" />
@stack('head')
  <script src="{{ $assets }}/js/fecv2.js" type="text/javascript" defer="defer"></script>
  <script src="{{ $assets }}/js/scrollspy.js?v=1" type="text/javascript" defer="defer"></script>

  <link rel="icon" type="image/png" sizes="32x32" href="{{ $assets }}/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="{{ $assets }}/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="{{ $assets }}/apple-touch-icon.png">
  <link rel="manifest" href="{{ $assets }}/site.webmanifest">
</head>
<body class="fecv2 fecx @yield('body_class')">

@if ($gtm)
<noscript>
  <iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}"
          height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
@endif

  <div class="fecv2-wrap" style="--fec-container: {{ config('fec.container_width') }}px;">

    <header class="fec-topbar" role="banner">
      <div class="fec-topbar__inner">

        <div class="fec-topbar__left">
          <a class="fec-brand" href="{{ $siteUrl }}/" aria-label="Home">
            <span class="fec-brand__mark" aria-hidden="true"></span>
            <span class="fec-brand__text">
              <span class="fec-brand__title">
                FreeExtra<span class="fec-brand__dot" aria-hidden="true">•</span>Chips
              </span>
            </span>
          </a>

          <div class="fec-topbar__slot">
            @yield('header_slot')
          </div>
        </div>

        <div class="fec-topbar__right">
          <button class="fec-burger" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="fecNav">
            <span class="fec-burger__lines" aria-hidden="true"></span>
          </button>

          <nav id="fecNav" class="fec-nav" role="navigation" aria-label="Primary">
            @include('partials.nav')
          </nav>

          <button class="fec-toolbtn fec-search__trigger"
                  type="button"
                  data-search-open
                  aria-haspopup="dialog"
                  aria-controls="fec-search"
                  aria-expanded="false">
            <span class="fec-ico" aria-hidden="true">
              <svg class="fec-ico__svg" viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" stroke="currentColor" stroke-width="2" />
                <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
            </span>
            <span class="sr-only">Search</span>
          </button>

        </div>
      </div>
    </header>

    <main class="fec-main @yield('main_class')" role="main">
@yield('content')
    </main>
  </div>

@include('partials.footer')
@include('partials.cookie')
@include('partials.search')

@stack('scripts')
</body>
</html>
