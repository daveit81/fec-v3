<?php
defined('_JEXEC') or die;

$app  = JFactory::getApplication();
$user = JFactory::getUser();

$tplParam = $app->input->getCmd('template', '');
$key      = $app->input->getCmd('k', '');

$doc = JFactory::getDocument();
$tpl = $this->template;

$showFooter = (int) $this->params->get('show_footer', 1);
$containerW = (int) $this->params->get('container_width', 1280);

// Assets
$ver = '1.0.5';
$doc->addStyleSheet(JURI::base(true) . "/css/fecv2.css");
$doc->addScript(JURI::base(true) . "/js/fecv2.js", 'text/javascript', true);

// Hub-longform scrollspy — global, safe on pages without .hub-longform
$doc->addScript(
    JURI::root(true) . '/js/scrollspy.js?v=1',
    'text/javascript',
    true   // defer
);


$option = $app->input->getCmd('option');
$view   = $app->input->getCmd('view');

// com_bonuses/details, com_bonuses/casino and com_tournaments/show used to
// load dedicated legacy stylesheets (fecv2.details.css, fecv2.casino.css,
// fecv2.tournaments.css) — all three removed in the May 2026 fecx migration
// completion. Every view is now fecx-styled via fec-edition.css, loaded
// below by the .fecx scope detection.
if ($option === 'com_fecsearch' || $option === 'com_search') {
    $doc->setMetaData('robots', 'noindex, nofollow, noarchive');
    $doc->setTitle('Search Results - FreeExtraChips.com');
}

    $fecHelpersDir = JPATH_SITE . '/components/com_bonuses/helpers';
    require_once $fecHelpersDir . '/fec_common_lib.php';

// Home detection by URL path, not Itemid — JReviews detail pages
// (e.g., /online-casinos/<slug>) fall back to home Itemid in Joomla's router.
$urlPath = trim(JUri::getInstance()->getPath(), '/');
$isHome  = ($urlPath === '');

// FEC V2.1 — every page served by THIS template is on the .fecx editorial
// design system. The two surfaces still on legacy design 1.0 (Casino Reviews
// on JReviews, Blog on com_content) are assigned to a DIFFERENT Joomla
// template style via menu-item assignment and never load this index.php —
// nothing to gate here. fec-edition.css loads globally below and the .fecx
// body class is always emitted.
//
// If a new section is later added on a different design, keep it on its own
// template style — do NOT reintroduce per-component gates in this file.
//
// The 6 granular gates (fec_is_fecx_bonus, _detail, _casino, _team, _author,
// _tournaments) remain in the lib because some helpers still key off the
// specific page kind (intro overrides, schema builders, deprecation strips).
// They are no longer wired to anything in THIS file.
$isFecx = true;

// Editorial redesign — fec-edition.css is .fecx-scoped (every selector is
// prefixed `.fecx ` or scoped under a `.fecx`-only class), so loading it
// globally stays inert on the few pages that don't actually carry the body
// class. With $isFecx pinned to true above, this is now an unconditional
// load — the if() wrapper was kept only when there were legacy-design pages
// on this template.
$doc->addStyleSheet(JURI::base(true) . "/css/fec-edition.css");

if ($isHome) {
    $fecHelpersDir = JPATH_SITE . '/components/com_bonuses/helpers';
    require_once $fecHelpersDir . '/fec_common_lib.php';
    require_once $fecHelpersDir . '/countriesarray.php';
    require_once $fecHelpersDir . '/bonustypesarray.php';
    require_once $fecHelpersDir . '/currenciesarray.php';

    // Homepage SEO overrides
    $homeTitle = 'Casino Bonuses Verified by Real Players | FreeExtraChips';
    $homeDesc  = 'Compare verified casino bonuses, free spins, and no deposit offers. We focus on wagering, max cashout, and country rules — with real player feedback via FXCheck™.';
    $homeUrl   = 'https://www.freeextrachips.com/';
    // OG image: 1200x630 brand-aligned, served from /images/og/
    $homeOgImage    = 'https://www.freeextrachips.com/images/og/og-home-1200x630.png';
    $homeOgImageAlt = 'FreeExtra · Chips — Casino bonuses verified by real players';

    $doc->setTitle($homeTitle);
    $doc->setMetaData('description', $homeDesc);

    // NOTE: OG and Twitter meta tags are emitted as raw HTML in the <head>
    // section below (after <jdoc:include type="head" />). Reason: Joomla 2.5's
    // setMetaData() third parameter is a BOOLEAN $http_equiv, not a string type
    // like in Joomla 3.x+. Passing 'property' makes it truthy → Joomla emits
    // `http-equiv="og:title"` (which Facebook/Twitter/LinkedIn don't parse) AND
    // appends "; charset=utf-8" to the content value (polluting it). We bypass
    // setMetaData() entirely for OG/Twitter on the home and output raw HTML.
}

// Return URL after logout. No public login link: the header user block is
// rendered only for logged-in (desk) users; guests see nothing (2026-10-04).
$return    = base64_encode(JUri::getInstance()->toString());
$logoutUrl = JRoute::_('index.php?option=com_users&task=user.logout&return=' . $return . '&' . JSession::getFormToken() . '=1', false);

$displayName = trim($user->name);
if ($displayName === '') $displayName = trim($user->username);
$initial = $displayName ? strtoupper(substr($displayName, 0, 1)) : 'U';

// Country detection (Cloudflare CF-IPCountry header).
// The "CF-IPCountry" HTTP request header is exposed by PHP as
// $_SERVER['HTTP_CF_IPCOUNTRY'] (HTTP_ prefix, dashes -> underscores,
// uppercased). getenv() does NOT read HTTP request headers — it reads process
// environment variables — so the old getenv('CF-IPCountry') was ALWAYS false
// and detection silently fell back to getenv('GEOIP_COUNTRY_CODE'), the
// mod_geoip / MaxMind env var, whose database is frequently stale and
// mis-locates IPs (e.g. an Italian IP reported as Belgium). Read the $_SERVER
// key directly; GEOIP stays only as a last-resort fallback.
$countryCode = '';
if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
    $countryCode = $_SERVER['HTTP_CF_IPCOUNTRY'];
} elseif (!empty($_SERVER['CF_IPCOUNTRY'])) {
    $countryCode = $_SERVER['CF_IPCOUNTRY'];
} elseif (getenv('GEOIP_COUNTRY_CODE')) {
    $countryCode = getenv('GEOIP_COUNTRY_CODE');
}
$countryCode = strtoupper(trim($countryCode));
// Cloudflare sends "XX" (unknown) and "T1" (Tor) as country codes — treat both
// as "no detection" so downstream code falls back cleanly to its 'us' default.
if (!preg_match('/^[A-Z]{2}$/', $countryCode)
    || $countryCode === 'XX'
    || $countryCode === 'T1') {
    $countryCode = '';
}

$eu = [
  'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT',
  'LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE',
  'GB','UK','NO','IS','LI',
];
$isEU = ($countryCode === '') ? true : in_array($countryCode, $eu, true);

?>
<!doctype html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>
  window.FEC_COUNTRY = <?php echo json_encode(strtoupper($countryCode)); ?>;
  window.FEC_IS_EU   = <?php echo $isEU ? 'true' : 'false'; ?>;

  window.dataLayer = window.dataLayer || [];
  function gtag(){ dataLayer.push(arguments); }

  gtag('consent', 'default', {
    analytics_storage: window.FEC_IS_EU ? 'denied' : 'granted',
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied'
  });
</script>

<!-- Google Tag Manager -->
<script>
  (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
  new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
  j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
  'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
  })(window,document,'script','dataLayer','GTM-P4J6ZS');
</script>

  <jdoc:include type="head" />

<?php if ($isHome): ?>
  <!-- Open Graph (home) — emitted as raw HTML because Joomla 2.5's
       setMetaData() can't produce property="og:..." reliably. -->
  <meta property="og:title" content="<?php echo htmlspecialchars($homeTitle, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($homeDesc, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:url" content="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="FreeExtraChips">
  <meta property="og:locale" content="en_US">
  <meta property="og:image" content="<?php echo htmlspecialchars($homeOgImage, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:image:secure_url" content="<?php echo htmlspecialchars($homeOgImage, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="<?php echo htmlspecialchars($homeOgImageAlt, ENT_QUOTES, 'UTF-8'); ?>">

  <!-- Twitter Card (home) — large image, matches OG image 1200x630. -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo htmlspecialchars($homeTitle, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="twitter:description" content="<?php echo htmlspecialchars($homeDesc, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="twitter:image" content="<?php echo htmlspecialchars($homeOgImage, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="twitter:image:alt" content="<?php echo htmlspecialchars($homeOgImageAlt, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>

  <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
  <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
  <link rel="manifest" href="/site.webmanifest">

<meta name="google-site-verification" content="AsFjo2DAOMAtcRiSNQMHr3IHZd0Z0-AKPe29ACju3tE" />
</head>
<body class="fecv2 fecx<?php echo $isHome ? ' is-home' : ''; ?>">

<noscript>
  <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-P4J6ZS"
          height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>

  <div class="fecv2-wrap" style="--fec-container: <?php echo (int)$containerW; ?>px;">

    <header class="fec-topbar" role="banner">
      <div class="fec-topbar__inner">

        <div class="fec-topbar__left">
          <a class="fec-brand" href="https://www.freeextrachips.com/" aria-label="Home">
            <span class="fec-brand__mark" aria-hidden="true"></span>
            <span class="fec-brand__text">
              <span class="fec-brand__title">
                FreeExtra<span class="fec-brand__dot" aria-hidden="true">•</span>Chips
              </span>
            </span>
          </a>

          <div class="fec-topbar__slot">
            <jdoc:include type="modules" name="fec_header" style="none" />
          </div>
        </div>

        <div class="fec-topbar__right">
          <button class="fec-burger" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="fecNav">
            <span class="fec-burger__lines" aria-hidden="true"></span>
          </button>

          <nav id="fecNav" class="fec-nav" role="navigation" aria-label="Primary">
            <jdoc:include type="modules" name="fec_nav" style="none" />
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

<?php if (!$user->guest): ?>

          <div class="fec-user is-logged" data-fec-dropdown>
            <button class="fec-user__btn" type="button" aria-haspopup="menu" aria-expanded="false">
              <span class="fec-avatar" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="fec-user__name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="fec-caret" aria-hidden="true"></span>
            </button>

            <div class="fec-dd" role="menu" aria-label="User menu">
              <div class="fec-dd__head">
                <div class="fec-dd__title"><?php echo htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fec-dd__sub"><?php echo htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8'); ?></div>
              </div>

              <div class="fec-dd__items">
                <a class="fec-dd__item" role="menuitem" href="<?php echo JRoute::_('index.php?option=com_users&view=profile', false); ?>">Profile</a>
                <a class="fec-dd__item" role="menuitem" href="<?php echo JRoute::_('index.php?option=com_users&view=profile&layout=edit', false); ?>">Account settings</a>
                <div class="fec-dd__sep" aria-hidden="true"></div>
                <a class="fec-dd__item fec-dd__item--danger" role="menuitem" href="<?php echo $logoutUrl; ?>">Logout</a>
              </div>
            </div>
          </div>

<?php endif; ?>

        </div>
      </div>
    </header>

    <main class="fec-main<?php echo $isHome ? ' fec-main--home' : ''; ?>" role="main">
<?php if ($isHome): ?>
<?php
    $db = JFactory::getDbo();

    // Country code lowercased for DB pattern matching (*xx* in bonuses tables)
    $cc = strtolower(trim($countryCode));
    if ($cc === '' || strlen($cc) !== 2) {
        $cc = 'us';
    }
    $cn = isset($countries_array[strtoupper($cc)]) ? (string)$countries_array[strtoupper($cc)] : '';

    try {
        // Organization + WebSite JSON-LD via helper in fec_common_lib.php.
        // Defaults render the same minimal graph we had inline (Org + WebSite).
        // To enrich with logo / sameAs / SearchAction, uncomment and fill in:
        echo fec_render_home_jsonld(array(
    'logo' => array(
        'url' => '/images/logo-new-extended.png',
        // width/height: aggiungi quando li hai (vedi sotto come trovarli).
        // Non bloccanti, ma Google li preferisce.
        'width'  => 512,
        'height' => 512,
    ),
    'same_as' => array(
        'https://x.com/FreeExtraChips',
        'https://www.youtube.com/@freeextrachips',
        (function_exists('fec_telegram_url') ? fec_telegram_url() : 'https://t.me/freeextrachips'),
        (function_exists('fec_discord_url') ? fec_discord_url() : 'https://discord.gg/BgCyVu7VvQ'),
    ),
    'search_url' => '/search?q={search_term_string}',
));

        // The hero is now two-column: the right side is the featured card
        // (the #1 editor's pick). The freshness counters live in its dateline.
        // This replaces BOTH the old freshness strip AND the standalone
        // Editor's Picks section (redesign: featured card / hero close-out).
        echo fec_render_home_hero_section($db, $cc, $cn, $bonustype, $currency);

        // Trending this week — real click data (lib v63+). Untyped page, so
        // the renderer's site-wide pass applies; shell=band forces the
        // bordered hub-picks-band look (root path is outside the
        // fec_is_fecx_bonus() allowlist, but this page IS .fecx and
        // fec-edition.css is global). Self-hides until 3 bonuses clear
        // 30 clicks / 7 days.
        if (function_exists('fec_render_trending_section')) {
            echo fec_render_trending_section($db, $cc, $cn, $bonustype, $currency, 3, array('shell' => 'band'));
        }

        echo fec_render_browse_by_type_section($db, $cc);
        // Country chip removed in FEC V2.1 — the Big Board sub-line already
        // states "Filtered to bonuses available in {country}".
        echo fec_render_latest_bonuses_section($db, $cc, $cn, $bonustype, $currency, 12);

        // FXRate (FEC Tools No. 05): "Welcome packages, priced." - the index
        // numbers and three tiles (latest priced, widest gap, the rate holds)
        // read from the operator terms, right after the big board, in the
        // bonus cluster. Standalone helper, same guarded pattern as FXClock:
        // a missing helper or missing tables leave the homepage untouched.
        // Operators that do not accept the visitor's country stay out of the
        // tiles ($cc), like the board; the numbers are catalogue-wide. The
        // country name ($cn, the one the board prints) lets the section say
        // so in its sub-line (render 1.4.3).
        $fxrHomeHelper = JPATH_SITE . '/components/com_bonuses/helpers/fec_fxrate_render.php';
        if (is_readable($fxrHomeHelper)) {
            require_once $fxrHomeHelper;
            if (function_exists('fec_fxrate_render_home_section')) {
                echo fec_fxrate_render_home_section($db, array('cc' => $cc, 'country' => $cn));
            }
        }

        echo fec_render_latest_casinos_section($db, 3);
        if (function_exists('fec_render_fxcheck_activity_section')) {
            echo fec_render_fxcheck_activity_section($db, 7);
        } else {
            echo '<!-- DEBUG: fec_render_fxcheck_activity_section() is NOT loaded in fec_common_lib.php -->';
        }

        // FXClock (FEC Tools No. 04): catalogue-wide live band, right after the
        // FXCheck activity as its player-side sibling. Standalone helper in
        // com_bonuses/helpers, same guarded pattern as the Comparator: a missing
        // helper leaves the homepage untouched. Renders an invitation until the
        // first clocks exist; pass array('hide_when_empty' => true) to hide it instead.
        $fxcHomeHelper = JPATH_SITE . '/components/com_bonuses/helpers/fxclock_widget.php';
        if (is_readable($fxcHomeHelper)) {
            require_once $fxcHomeHelper;
            if (function_exists('fxc_widget_render_home_band')) {
                fxc_widget_render_home_band(array('hide_when_empty' => false));
            }
        }
        echo fec_render_trust_pulse_strip($db);
        echo fec_render_blog_latest_section($db, 3);
        echo fec_render_home_faq_section();
        echo fec_render_email_capture_block('home_block');
    } catch (Exception $e) {
        error_log('Homepage render failed: ' . $e->getMessage());
        echo '<div class="fec-container" style="padding:40px 16px">';
        echo '<p>The homepage is temporarily unavailable. Please try again in a moment.</p>';
        echo '</div>';
    }
?>
<?php else: ?>
      <?php /* Hub (FEC V2.1) — the editorial layout is .wrap-based, like
               the homepage, which renders straight into <main> with no
               .fec-container. The hub does the same: the template
               container would only double-constrain a layout that
               already caps itself via .wrap. Sibling com_bonuses pages
               and every other page keep .fec-container until they too
               migrate (then widen this condition).

               FEC V2.1 — now that every page on this template is .fecx,
               .fec-container is no longer emitted at all by index.php
               (each view widens to .wrap). The wrap historically also
               capped width on legacy-design pages, but those are now
               served by a different Joomla template style. */ ?>
        <jdoc:include type="message" />
        <jdoc:include type="modules" name="fec_above" style="none" />
        <jdoc:include type="component" />
        <jdoc:include type="modules" name="fec_below" style="none" />
<?php endif; ?>
    </main>
  </div>

<footer class="fec-footer" role="contentinfo">
  <div class="fec-footer__inner fec-container">
      
<?php echo fec_render_email_capture_block('footer', array(
    'variant' => 'compact',
    'skip_if_other_rendered' => true,
)); ?>

    <div class="fec-footer__top">
      <a class="fec-footer__brand" href="/">
        <span class="fec-footer__mark" aria-hidden="true"></span>
        <span class="fec-footer__brandtxt">
          <span class="fec-footer__title">
            FreeExtra<span class="fec-footer__dot" aria-hidden="true">•</span>Chips™
          </span>
          <span class="fec-footer__sub">Verified bonus listings &amp; reviews</span>
        </span>
      </a>

      <div class="fec-footer__cols">

        <div class="fec-footer__col">
          <div class="fec-footer__h">Casinos</div>
          <a class="fec-footer__a" href="/online-casinos">Casino Reviews</a>
          <a class="fec-footer__a" href="/online-casinos?sort=newest">New Casinos</a>
          <a class="fec-footer__a" href="/online-casinos?filters=established_5y">Established Casinos</a>
                    <a class="fec-footer__a" href="/online-casinos/fast-payouts">Fast Payouts</a>
          <a class="fec-footer__a" href="/online-casinos?filters=crypto">Crypto Friendly</a>
          <a class="fec-footer__a" href="/tools/casino-comparator">Casino Comparator</a>
        </div>

        <div class="fec-footer__col">
          <div class="fec-footer__h">Bonuses</div>
          <a class="fec-footer__a" href="/bonuses/">All Bonuses</a>
          <a class="fec-footer__a" href="/bonuses/no-deposit-casinos">No Deposit</a>
          <a class="fec-footer__a" href="/bonuses/free-spins/">Free Spins</a>
          <a class="fec-footer__a" href="/bonuses/free-spins/no-deposit/">Free Spins No Deposit</a>
          <a class="fec-footer__a" href="/bonuses/high-roller-bonuses">High Roller</a>
          <a class="fec-footer__a" href="/tools/wagering-calculator">Wagering Calculator</a>
        </div>

        <div class="fec-footer__col">
          <div class="fec-footer__h">Transparency</div>
          <a class="fec-footer__a" href="/methodology">How we verify</a>
          <a class="fec-footer__a" href="/methodology/rtp-verification">RTP methodology</a>
          <a class="fec-footer__a" href="/methodology/fxcheck">FXCheck™ methodology</a>
          <a class="fec-footer__a" href="/methodology/editorial-standards">Editorial Standards</a>
        </div>

        <div class="fec-footer__col">
          <div class="fec-footer__h">About</div>
          <a class="fec-footer__a" href="/who-is-frank">Who is Frank?</a>
          <a class="fec-footer__a" href="/authors">Editorial Team</a>
          <a class="fec-footer__a" href="/contactus/">Contact</a>

          <div class="fec-footer__mini">
            <div class="fec-footer__badge">18+</div>
            <span class="fec-footer__muted">Please gamble responsibly.</span>
          </div>

          <a class="fec-footer__a" href="https://www.begambleaware.org/" target="_blank" rel="noopener">BeGambleAware.org</a>
        </div>

      </div>
    </div>

    <div class="fec-footer__social" aria-label="Follow FreeExtraChips">
      <a class="fec-footer__soc" href="https://x.com/FreeExtraChips" target="_blank" rel="noopener" aria-label="FreeExtraChips on X">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>X</span>
      </a>
      <a class="fec-footer__soc" href="<?php echo htmlspecialchars(function_exists('fec_telegram_url') ? fec_telegram_url('footer_social') : 'https://t.me/freeextrachips', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" aria-label="FreeExtraChips on Telegram">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M22 2 11 13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 2 15 22l-4-9-9-4 20-7Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>Telegram</span>
      </a>
      <a class="fec-footer__soc" href="<?php echo htmlspecialchars(function_exists('fec_discord_url') ? fec_discord_url('footer_social') : 'https://discord.gg/BgCyVu7VvQ', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" aria-label="FreeExtraChips on Discord">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6 12h4M8 10v4M15 13h.01M18 11h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M17.32 5H6.68a4 4 0 0 0-3.978 3.59c-.006.052-.01.101-.017.152C2.604 9.416 2 14.456 2 16a3 3 0 0 0 3 3c1 0 1.5-.5 2-1l1.414-1.414A2 2 0 0 1 9.828 16h4.344a2 2 0 0 1 1.414.586L17 18c.5.5 1 1 2 1a3 3 0 0 0 3-3c0-1.545-.604-6.584-.685-7.258-.007-.05-.011-.1-.017-.152A4 4 0 0 0 17.32 5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span>Discord</span>
      </a>
    </div>
    <style>
      .fec-footer__social{display:flex;flex-wrap:wrap;gap:10px;margin:18px 0 6px}
      .fec-footer__soc{display:inline-flex;align-items:center;gap:7px;padding:7px 13px;border:1px solid rgba(28,31,36,.18);border-radius:999px;font-size:13px;line-height:1;color:inherit;text-decoration:none}
      .fec-footer__soc svg{display:block}
      .fec-footer__soc:hover{border-color:currentColor}
    </style>

    <div class="fec-footer__bottom">
      <div class="fec-footer__muted">© <?php echo date('Y'); ?> FreeExtraChips. Content may change; always check terms at the casino.</div>
      <div class="fec-footer__muted">FXCheck™ is based on player reports (Yes/No).</div>
    </div>

  </div>
</footer>

<div class="fec-cookie" role="dialog" aria-modal="true" aria-labelledby="fec-cookie-title" hidden>
  <div class="fec-cookie__inner">
    <div class="fec-cookie__text">
      <strong id="fec-cookie-title">We use cookies</strong>
      <p>
        We use essential cookies to make the site work.
        With your consent, we may also use analytics cookies to improve FreeExtraChips.
      </p>
    </div>

    <div class="fec-cookie__actions">
      <button class="fec-btn fec-btn--primary" data-cookie="accept">Accept</button>
      <button class="fec-btn fec-btn--ghost" data-cookie="settings" aria-expanded="false" aria-controls="fec-cookie-settings">
        Settings
      </button>
      <button class="fec-link" data-cookie="reject">Reject non-essential</button>
    </div>

    <div class="fec-cookie__settings" id="fec-cookie-settings" hidden>
      <div class="fec-cookie__row">
        <div class="fec-cookie__rowtext">
          <div class="fec-cookie__rowtitle">Essential</div>
          <div class="fec-cookie__rowdesc">Always on. Needed for the site to work.</div>
        </div>
        <div class="fec-cookie__pill">On</div>
      </div>

      <div class="fec-cookie__row">
        <div class="fec-cookie__rowtext">
          <div class="fec-cookie__rowtitle">Analytics</div>
          <div class="fec-cookie__rowdesc">Helps us understand usage and improve pages.</div>
        </div>

        <label class="fec-switch">
          <input type="checkbox" id="fec-cookie-analytics" />
          <span class="fec-switch__ui" aria-hidden="true"></span>
          <span class="sr-only">Enable analytics cookies</span>
        </label>
      </div>

      <div class="fec-cookie__save">
        <button class="fec-btn fec-btn--primary" data-cookie="save">Save settings</button>
      </div>
    </div>
  </div>
</div>

<!-- Search overlay -->
<div class="fec-search" id="fec-search" role="dialog" aria-modal="true" aria-label="Global search" hidden>
  <div class="fec-search__backdrop" data-search-close></div>

  <div class="fec-search__panel" role="document">
    <div class="fec-search__top">
      <button class="fec-search__back" type="button" data-search-close aria-label="Close search">
        <span aria-hidden="true">←</span>
      </button>

      <div class="fec-search__field">
        <span class="fec-search__icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M11 19a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z" stroke="currentColor" stroke-width="2"></path>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"></path>
          </svg>
        </span>

        <input class="fec-search__input"
               type="search"
               inputmode="search"
               autocomplete="off"
               placeholder="Search bonuses, casinos, guides…"
               aria-label="Search"
               data-search-input />

        <button class="fec-search__clear" type="button" data-search-clear aria-label="Clear" hidden>
          <span aria-hidden="true">×</span>
        </button>
      </div>

      <button class="fec-search__esc" type="button" data-search-close>ESC</button>
    </div>

    <div class="fec-search__popular" data-search-popular>
      <div class="fec-search__sub">Popular searches</div>

      <div class="fec-search__chips" data-search-popular-chips>
        <button class="fec-chip" type="button" data-search-suggest="no deposit">No Deposit</button>
        <button class="fec-chip" type="button" data-search-suggest="free spins">Free Spins</button>
        <button class="fec-chip" type="button" data-search-suggest="no wagering">No Wagering</button>
        <button class="fec-chip" type="button" data-search-suggest="crypto">Crypto</button>
        <button class="fec-chip" type="button" data-search-suggest="exclusive">Exclusive</button>
        <button class="fec-chip" type="button" data-search-suggest="high roller">High Roller</button>
        <button class="fec-chip" type="button" data-search-suggest="new casinos">New Casinos</button>
      </div>
    </div>

    <div class="fec-search__recent" data-search-recent hidden>
      <div class="fec-search__sub">
        Recent searches
        <button class="fec-search__clearrec" type="button" data-search-recent-clear>Clear</button>
      </div>
      <div class="fec-search__chips" data-search-recent-chips></div>
    </div>

    <div class="fec-search__body" data-search-body>
      <div class="fec-search__state" data-search-loading hidden>
        <div class="fec-skel">
          <div class="fec-skel__line"></div>
          <div class="fec-skel__line"></div>
          <div class="fec-skel__line"></div>
        </div>
      </div>

      <div class="fec-search__state" data-search-empty hidden>
        <div class="fec-search__emptytitle">No results</div>
        <div class="fec-search__emptyhint">Try a casino name, “no wagering”, or a bonus code.</div>
      </div>

      <div class="fec-search__groups" data-search-groups hidden></div>
    </div>
  </div>
</div>

<?php echo fec_render_email_capture_popup(array(
    'heading'      => 'Hold on — your bonus weekly fix',
    'subhead'      => 'Every Tuesday, the bonuses that actually paid out.',
    'cta_label'    => 'Send me bonuses',
    'show_after_ms' => 30000,    // 30s fallback
    'show_at_scroll' => 60,       // più conservativo
)); ?>

</body>

<!-- CSRF token refresh: fetch a fresh token before any POST form submit.
     Mitigates Joomla's session-token expiry on long-idle pages. -->
<script>
(function() {
    var FORM_SELECTOR = 'form[method="post" i]';
    var forms = document.querySelectorAll(FORM_SELECTOR);

    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (form.dataset.tokenRefreshed === 'true') {
                return true;
            }
            e.preventDefault();

            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;

            fetch('/freshtoken.php', {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store'
            })
            .then(function(response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function(data) {
                if (!data.token) throw new Error('No token in response');

                var freshInput = document.createElement('input');
                freshInput.type = 'hidden';
                freshInput.name = data.token;
                freshInput.value = '1';
                form.appendChild(freshInput);

                form.dataset.tokenRefreshed = 'true';
                if (submitBtn) submitBtn.disabled = false;
                form.submit();
            })
            .catch(function(err) {
                console.error('Token refresh failed:', err);
                if (submitBtn) submitBtn.disabled = false;
                form.dataset.tokenRefreshed = 'true';
                form.submit();
            });
        });
    });
})();
</script>
</html>