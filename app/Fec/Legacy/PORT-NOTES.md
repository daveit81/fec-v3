# Legacy port notes

`app/Fec/Legacy/site/` is the virtual docroot (`JPATH_SITE`) of the ported Joomla-era code:
copies of `legacy/` files that run under the compatibility layer (`app/Fec/Legacy/Joomla/`,
classes with Joomla 2.5 names over Laravel). Every difference from the snapshot is listed here;
anything not listed is byte-identical to `legacy/` and must stay so (re-snapshot, then re-copy).

## Lib slices (generated)

`components/com_bonuses/helpers/{fec_common_lib,fec_fxrate,fec_blog}.php` are written by
`php scripts/lib-slice.php` from `app/Fec/Legacy/lib-slices.json`: the transitive closure of the
functions each ported section needs, copied verbatim with the source file's constants. Never
edited by hand; add a seed and regenerate.

## Mechanical rewrites (every ported file)

`scripts/legacy-rewrite.inc.php` holds the rewrites the port applies to all legacy code: the
slicer applies them when it writes a slice, `php scripts/legacy-port.php <dir>` applies them to
copied files (run it after every copy; `--check` lists what is left, and `fec:legacy:check` fails
on it). They are token based and idempotent.

- `usort(` / `uasort(` / `asort(` / `arsort(` become `fec_php5_usort(` ... (`App\Fec\Legacy\Php5Sort`,
  loaded with the layer's functions). PHP 5.6 sorted with `Zend/zend_qsort.c`, a quicksort that is
  not stable (two elements the comparator cannot tell apart swap places, three come out reversed);
  PHP 8 sorts stably. The lib sorts candidates by a score and keeps a top N (`fec_render_similar_offers`,
  the editor's picks, the sister map) and walks `arsort()`ed click maps (trending): with tied scores
  the live site and v3 showed different rows (details pages: "similar offers" Uptown Aces/Dragonara
  on the live site, Uptown Pokies/Omni Slots on v3, same data and lib). `Php5Sort` is the PHP 5.6
  algorithm step by step, validated against the C source of php-5.6.40 on random inputs; the
  comparison itself (`<=>`, `strnatcasecmp`...) is the current engine's. `sort`/`rsort`/`ksort`/
  `krsort` are left alone: equal values are not told apart after `sort()`, keys are unique.
  Rewritten call sites: `fec_common_lib.php` (6), `fec_fxrate.php` (1), `fec_fxrate_render.php` (2),
  `filters.php`/`filtersnew.php` (8 each), com_authors models and views (13).
- `header(` becomes `fec_header(`: a bare `header()` goes to PHP's SAPI, which the runner cannot read
  in every context (the CLI keeps no header list, so `fec:parity` saw the `tc` and `ads` views of
  com_bonuses answer 200 where PHP had made them a 302 with a Location). `fec_header()` records the
  header on the Joomla application object, where the runner builds the response from; a status line
  or a response code sets the status; a Location header makes the response a 302 unless a 3xx status
  is set, as PHP does, the body kept (Joomla sent it too). 23 call sites: the JSON and download tasks
  of com_authors, `actions.php`, `mobileapp.php`, `views/tc`, `views/ads`; 7 more in the desk scripts
  of com_bonuses (`ajax/`).
- `exit;` / `exit(...)` / `die(...)` as a statement become `jexit(...)`: exit ends the PHP process,
  which under Joomla was the end of the request; here it would skip the response the runner builds
  (headers, session, the layout) and kill a console run. `jexit()` throws the exception the runner
  turns into the response of what was echoed so far; a string argument is printed first, as `exit`
  printed it (an integer was the process status, nothing of the response). The guards
  `defined('_JEXEC') or die(...)` are not statements and stay as written (they never fire). 4 call
  sites: the three desk scripts of com_bonuses (`ajax/clicks_live.php`, `ftd_save.php`,
  `ftd_import.php`), `modules/homeslidernew.php`.

## sh404SEF plugins (ported as classes, not copied)

`legacy/components/<option>/sef_ext/<option>.php` ran inside sh404SEF and used its globals
(`$dosef`, `$title`, `sef_404::sefGetLocation`), so it is ported as a class instead:
`App\Fec\Legacy\Sef\Sh404sef` is `sefGetLocation()` (titleToLocation with the live settings,
join, collapse, the registry row) and `Sef\BonusesPlugin` is `sef_ext/com_bonuses.php` branch by
branch (`$bonustype` copied from `helpers/bonustypesarray.php`, a file-scope file). `JRoute` calls
it for a query the registry does not hold and, with `FEC_LEGACY_SEF_REGISTER=true`, records the
pair as sh404SEF did (rank 0, duplicates with the next rank, a recorded 404 upgraded).
`php artisan fec:sef:check --option=com_bonuses` rebuilds every canonical row of the registry
with the port: the exhaustive test of the slugifier. Not ported: the shURL page ids
(`jos_sh404sef_pageids`) and the hit counter `cpt`; the desk views and tasks stay non-SEF, as the
plugin left them (`$dosef = false`).

## Hand edits

- `components/com_bonuses/helpers/bonustypesarray.php`: `getenv(GEOIP_COUNTRY_CODE)` used an
  undefined constant (fatal on PHP 8); now `getenv('GEOIP_COUNTRY_CODE')` with guarded lookups.
  The variables are file-scope leftovers nobody reads.
- `components/com_bonuses/helpers/currenciesarray.php`: the file re-defined `_JEXEC`, `DS`,
  `JPATH_BASE` and re-included the Joomla framework at file scope (notices under Joomla, fatal
  here); only the `$currency` array is kept.
- `components/com_authors/`: verbatim copy of the site component (`index.html` placeholders
  dropped), with one edit in three places: the JSON/download tasks flushed every output buffer
  (`while (ob_get_level()) ob_end_clean()`), which would also discard the runner's capture
  buffer; they now stop at `FEC_OB_BASE`, the level the runner defines before including the
  component (`controllers/dashboard.php` gateAndJson, `controllers/fxshieldadmin.php` exportmd and
  exportdossier). Headers they set with `header()` are copied into the response by the runner.
- `components/com_authors/models/payoutindex.php` renderCard: the edition card is written under
  `JPATH_ROOT . CARD_DIR` instead of `JPATH_SITE` (same path on Joomla, where the two are one
  directory; here `JPATH_SITE` is the code and `JPATH_ROOT` the real `public_html`).
- `components/com_authors/views/methodologyhub/view.html.php` and `models/methodology.php`
  `getHubStats()` (28 Oct 2026, a live bug, fixed on Joomla the same day with the same edit, so the
  copies and the live files agree): the hub listed every row of `jos_methodology_topics` as a
  methodology card, the page-kind rows too (the editorial pages' registry since 2.7.38:
  responsible-gambling, privacy-policy, affiliate-disclosure, fast-payouts, licence-watch,
  welcome-packages, about), linking them as `view=methodology&topic=<slug>`, which the methodology
  model refuses (`kind !== 'methodology'` → 404): sh404SEF registered `methodology/<slug>` for them,
  they were crawled, and the disclosure card doubled the external one. The view now keeps the
  methodology-kind topics only (cards, JSON-LD `hasPart`) and the stats count the same set. The six
  registered URLs are 301s to the pages (`fec.legacy.htaccess_redirects`, and the live `.htaccess`);
  the registry rows stay, the router answers the redirect first.

- `components/com_bonuses/`: verbatim copy of the site component (phase 3) without the static
  assets (`css/`, `js/`, `data/`: served from the live docroot), the standalone scripts (`chron/`,
  `scripts/`, `ajax_review_submit.php`, the public `ajax/bonus_feedback.php` and
  `ajax/bonus_reports.php`: they keep running on Joomla by direct path until phase 5), `admin/` and
  `sef_ext/` (the sh404SEF URL plugin, ported as a class of the layer
  instead). `helpers/fec_common_lib.php`, `fec_fxrate.php`, `fec_blog.php` are the generated
  slices; `bonustypesarray.php` and `currenciesarray.php` the hand-edited copies above. One edit:
  `renders/filtersheaders.php` had two `default:` clauses in the `switch($filter)`; PHP 5 ran the
  last one, PHP 7+ refuses two, so the first (empty) one is removed: same behaviour. Not copied:
  `helpers/authors.php`, a dead file (nothing includes it) that re-declares two functions the lib
  defines unguarded (`fec_get_bonus_author`, `fec_render_bonus_author_card`): loading both is fatal.
  Nor `views/authors/`: its file declares `BonusesViewTc` (a copy-paste), so `view=authors` was
  already broken on Joomla ("class BonusesViewAuthors not found") and no URL points to it.
  `helpers/pagination.php` (Zebra_Pagination) `get_pages()`: `get_page()` calls it before the
  record counts are set, dividing `'' / ''`; PHP 5.6 silently gave 0, PHP 8 throws a TypeError on
  string operands, so the operands are cast and a zero divisor returns 0, as before.
- `components/com_bonuses/` desk (step 6 of phase 3): the views `home`, `add`, `manage`, `clicks`
  and `controllers/actions.php` (the 14 tasks) run as desk views (`fec.legacy.desk_views`, with
  `com_bonuses.actions` for the tasks), gated as on the live site by `BonusesHelperBonus::authorized()`
  (Joomla user 42, the account's `joomla_user_id` here). Not copied: `views/edit/` (the old editor,
  `editb.php`, superseded by the `add` view): it includes the old MaxMind `geoip/geoip.inc` from the
  docroot (outside the snapshot) and no desk page links `view=edit`; it stays on Joomla. Edits: `actions.php`
  `jsonOut()` closed every output buffer before echoing the JSON (`while (ob_get_level())
  ob_end_clean()`), which would also discard the runner's capture buffer; it now stops at
  `FEC_OB_BASE` and empties that buffer instead (same effect: only the JSON is sent).
- `components/com_bonuses/ajax/{clicks_live,ftd_save,ftd_import}.php`: the desk's standalone
  endpoints (the click feed, the FTD entry and the StatsDrone CSV import), which the desk pages call
  by their path (`/components/com_bonuses/ajax/<name>.php`). On the live site each one bootstrapped
  Joomla itself (`define('_JEXEC')`, `JPATH_BASE` from `DOCUMENT_ROOT`, `includes/defines.php`,
  `includes/framework.php`); here that head is replaced by the `_JEXEC` guard, the runner boots the
  layer before including the script (`ComponentRunner::runScript()`, same path, `fec.legacy.desk_scripts`,
  logged-in redazione only, writing connection, the answer is what the script echoed with the headers
  and status it set), and `JLoader::register` points at `JPATH_COMPONENT` instead of
  `JPATH_BASE . 'components/com_bonuses'`. `ftd_save.php` also redirected PHP's error log to a file
  beside it (`ini_set('error_log', ...)`): dropped, errors go to Laravel's log. The scripts' `exit`
  and `header()` are rewritten as everywhere (above).

## com_content (phase 4): our port of the core component

`components/com_content/` is not a copy: Joomla's core component is not in the snapshot (it is
Joomla, not our code), so the files are written by hand from Joomla 2.5.28's
`components/com_content` (content.php, controller.php, helpers/route.php, models/article.php,
models/category.php, views/article/view.html.php, views/category/view.html.php), the original's
comments kept where they explain a choice, reduced to what the fecv2 template overrides receive:
the article row with its published state, dates and category (the "badcats" join), `params` as
the menu item and the article's `attribs` cascade them, `metadata`, the text as `show_intro`
decides (0 on the live site: the full text alone when there is one, the intro text otherwise),
the content plugins on it (`onContentPrepare` and the three display events), the document title
and metas (`_prepareDocument`), the category node with its params and access, the layout choice.
Left out, each one invisible in the output: the pathway (the template prints none), the hit
counter (`hit()` is kept, the view does not call it: the counter is shown nowhere and the public
pages do not write; a declared parity exception), the article list of the category view and the
plugins on each intro text (the override queries the lib itself and reads only `$this->category`;
`getItems()` returns an empty list), the feed format of the category view (RSS/Atom of the blog:
a decision of phase 4), print and pagebreak, edit, vote and form tasks, the multilingual lookups,
the edit permissions of a logged user. The component's options are the live row of
`#__extensions` (`fec.legacy.params.com_content`); the global metadata options of
`configuration.php` are `fec.legacy.meta.*` (`JSite::getParams()` is ported in `Application::getParams()`).
`JRoute` strips Joomla's `id:alias` slugs from com_content links before the registry lookup
(ContentHelperRoute builds them, sh404SEF stored numeric ids).

The overrides themselves are verbatim copies, `templates/fecv2/html/com_content/article/default.php`
and `category/default.php`, found by `JView` on the theme path first, as Joomla did. One edit in
the category override: two includes used `JPATH_ROOT` for the lib and the blog helper (one
directory on Joomla); here they use `JPATH_SITE`, where the code is (`JPATH_ROOT` is the real
docroot, which holds the unported lib). `article/home.php` is verbatim too: the home menu item may
ask for it, and Joomla ran the home's component even though the template never printed its body.
`article/default_withclock.php` (no article or menu item uses the layout) and the `com_users` and
`mod_menu` overrides are not copied.

## The home (phase 4, step 2)

"/" is the home menu item (`jos_menu` home = 1) for the router (`Router::home()`, flagged
`meta.home`), as sh404SEF answered an empty path, and runs like any com_content page: the
component with its plugins (the home article's head stays, `fechome` processes its shortcodes),
then `App\Fec\Legacy\HomeTemplate`, the `$isHome` branch of `templates/fecv2/index.php`: the
title and description it set, its Open Graph and Twitter tags after the head (`head_after`), the
sections in its order with its country and its fallback message (`sections()`, under
`LegacyErrors`), "is-home" on the body, "fec-main--home" on `<main>`, no message container; the
template's own stylesheets and scripts go on the document first (`templateAssets()`, lines 16-26
and 72 of `index.php`) and the layout prints none of its fixed ones, so the sections' assets
(`fec_fxrate.css`, `fxclock.css`, `fxclock.js`) follow them as on the live page. Then
`Sh404sefPage` with the home's metas row (sh404SEF's home code) and the canonical branches (the
bare home on menu item 26, given its slash by `feccanonicalfix`), then the system plugins. The
status page keeps "/" on the development hostname (APP_URL), where `/__home` shows the home.

## Plugins (phase 4)

`plugins/<type>/<name>/<name>.php` are verbatim copies of our plugins, run by the layer's
`JPlugin`/`JPluginHelper`/`JDispatcher` (`fec.legacy.plugins` lists them, their params come from
their rows of `#__extensions`): `content/feccounts` (`{FEC:...}` counts in article text),
`content/fechome` (the shortcodes of the home article: inert, the home is rendered by the
template), `system/fecclustermeta` (the title and description of the cluster pages and of the
blog listing, stashed on the application by the lib and written into the finished page after
sh404SEF: the runner's `afterRender()` is Joomla's `onAfterRender`). The 30 third party content
plugins enabled on the live site are not ported: `fec:parity` on the reviews says which of them
change a page, and those are reproduced one by one (none known yet). `system/feccanonicalfix`
(verbatim, ordering 86) gives its slash to the bare-host canonical that sh404SEF writes on menu
item 26 (the home and any article on that item outside the reviews). `fectemplateswitch`, `geoip`
and `cfcookie` are not ported (the template switch is for com_easyblog, the country comes from
Cloudflare's header, the cookie is Joomla's login).

`App\Fec\Legacy\Sh404sefPage` is sh404SEF's page rewrite on the com_content pages
(`components/com_sh404sef/shPageRewrite.full.php`, `shDoTitleTags`, with its meta plugin
`meta_ext/com_content.php`, 4.3.0.1671): the live copy was edited by hand for FEC (menu item 26,
categories 8 and 139 in its branches) and that copy is what is ported, branch by branch, with the
settings of the live `config.sef.php` in `fec.legacy.sh404sef`. The title is the meta plugin's
(an article's title; a category's last two categories, `Clusters | Online Casinos`) or the
"Title and metas" row's, never on catid 8 (the reviews' override sets its own; a row still wins
there) or 139; the canonical follows the five branches (an article's canonical rewritten to the
relative `JRoute` of its own non-SEF URL, `/online-casinos/king-billy-casino`; a page with none
gets its own URL at the top, the bare home on menu item 26 outside catid 8; more than one are all
removed first). "Menu item 26" is the item sh404SEF's router made active: the Itemid of the
page's own non-SEF URL, none without one (the clusters are registered without an Itemid; the
layer's menu falls back to the home, as mod_menu does for its active class, but the branches
must not). Measured on 28 Oct 2026 on the review, the clusters index, a cluster, a blog post
and the directory (`tests/Unit/Sh404sefPageTest.php`). The site-wide `canonical` system plugin is
enabled in `#__extensions` but none of its raw-URI links reaches those pages: not reproduced.
The other components keep `Sh404sefMetas` (the row only) and their own canonical, which is what
the same branches give on their pages (no meta plugin, one canonical of their own).
The row and the meta plugin's canonical start from sh404SEF's current non-SEF URL: the
registry's query with the request's own variables on top (`parseRule` merged them), rebuilt by
JURI, `&lang=en` added, the tracking variables of sh404SEF's configuration stripped (utm_*,
gclid, fb_xd_*, hitcount; not fbclid), sorted by `shSortUrl`. Measured on 29 Oct 2026:
`/online-casinos?sort=newest`, `?start=50` and `?filters=crypto` carry the plugin's title
"Online Casinos" and no description on the live site, the row's only on the plain URL. On the
home with a query string the router passed the request's variables alone, so sh404SEF did not
see the home there (no home row, the plugin's title of the home article): reproduced, parity
green on `/?fbclid=` and `/?utm_source=` (29 Oct 2026).
sh404SEF wrote its canonical into the finished page after the core `sef` system plugin (ordering
76 and 79): what it wrote was never made absolute. The "Title and metas" rows 344/345 give the
casinouk review the relative canonical `reviews/online-casino/casinouk.html`, served so. v3
decides the canonical before the layout, so the layout prints a canonical sh404SEF inserted or
rewrote under a placeholder `sef` skips (`fecsh404sef:N`, a URL scheme to its regex) and the
layer's system plugin `sh404sef` (`site/plugins/system/sh404sef`, ours, not a copy) puts it back
in sh404SEF's place, before `feccanonicalfix` (`Sh404sefOutput`). A canonical of the page's own
that sh404SEF left alone still goes through `sef`, as on the live site.

`plugins/system/sef/sef.php` is our port of Joomla's core SEF system plugin (2.5.28, the
original's regexes): on the finished page every `href="index.php?..."` written literally (the
lib's FXShield report link) is routed through `JRoute`, and relative `src`/`href`/`poster`,
`onclick`, background images and object parameters get the site base in front. It runs first
among the system plugins, as on the live site (ordering 76, before sh404SEF and fecclustermeta).

`components/com_bonuses/fec_review_config.php` is not the live file (excluded from the snapshot:
it holds the Turnstile secret) but a small one of ours that defines the same constants the lib
reads at include time from the environment (`fec.legacy.review`: the public site key the review
form prints, the form's limits); without the site key `fec_render_review_form()` renders nothing,
as on the live site. The form posts to `ajax_review_submit.php` on Joomla until phase 5.

`JRoute` for a com_authors query the registry does not hold and the component's router gives no
segment for (the FXShield report link of the casino pages, `view=fxreport`) answers what sh404SEF's
default plugin answered: the component's name as the path and the leftover variables sorted
(`/authors?casino_id=910&tmpl=component&view=fxreport`), never stored.

`helpers/fxclock_widget.php` (verbatim since phase 3) loads the FXClock library from
`/home/freeextr/fxclock/bootstrap.php`, outside the docroot and outside this repository, as it
did on Joomla (`dirname(JPATH_SITE)` resolves elsewhere here, the absolute fallback is what
runs): the review pages depend on that library until phase 5 decides where FXClock lives.

## The legacy .htaccess, reproduced where it reaches our paths

The v3 switch block sits at the top of `public_html/.htaccess` and rewrites the section's paths to
`/v3/index.php`; the second pass reads the `.htaccess` of that directory (Laravel's), not the rest of
the root one, so **every rule below the block is skipped for those paths** (verified on 27 Oct 2026
with the test block: `/slots/tag/...`, `/authors?GLOBALS[x]=1`, `/authors?q=base64_encode(x)` all
answered by PHP 8.4). `App\Fec\Url\HtaccessRedirects` answers them first, rule by rule with each
directive's matching (`fec.legacy.htaccess_redirects`, rewrite rules before mod_alias, as the two
modules run): the 410 of line 112 (`<section>/tag/...`), Joomla's exploit filters (403 on
`base64_encode(`, `<script>`, `GLOBALS[`, `_REQUEST[` in the raw query string), the AppEngine
user-agent 403, and the redirects under `bonuses/` (retired bonus types, `bonuses/ads/` to
`bonuses/ctr/`, replaced bonuses, every country under `allowed-countries/` but au and nz, the
exclusive filter page to the hub). Only the rules that are effective on the live site count: a
`RewriteRule` below Joomla's catch-all (`.* index.php [L]`, around line 340) never fires there
(line 462, `bonuses/free-spins` → slash form: the live 301 is the sh404SEF alias); mod_alias
`Redirect`/`RedirectMatch` lines fire wherever they are. Not reproduced: the LiteSpeed cache rules (v3 pages are
no-cache). **When a rule is added to the live file and can match a path v3 serves, it is added
there too.**

## sh404SEF's other tables

- `jos_sh404sef_aliases` (721 rows, 27 under our paths): `App\Fec\Url\Sh404sefAliases`, checked by the
  router after the `.htaccess` rules and before the URL registry, with sh404SEF's behaviour read off
  the live site (a path target is the Location as written, relative, the request's query string
  appended; an `index.php` target is the SEF URL stored for that exact newurl of any rank, absolute,
  the home when none is stored; an alias of the requested page itself serves the page). Read again
  in sh404SEF's code on 29 Oct 2026 (`_checkAliases`, `shCheckRedirect`): with a query string the
  lookup also tries the path with and without the trailing slash (the alias sorting last wins), the
  query goes into the non-SEF target and the target is routed: com_content (sh404SEF's own plugin)
  keeps the variables it does not encode, sorted and decoded, com_bonuses' `sefGetLocation` drops
  them; no redirect when the result is the requested URL as sent. The directory has the alias
  `online-casinos/`: `/online-casinos?b=2&a=1` 301s to `?a=1&b=2`, `?sort=newest` is served.
- `jos_sh404sef_metas` (810 rows, 49 for com_bonuses): `App\Fec\Legacy\Sh404sefMetas`, applied by
  the runner after the component on sh404SEF's current non-SEF URL (the page's own plus the
  request's query variables, see above; title, description, keywords, robots, canonical when
  non-empty; Open Graph fields left to the lib).
- `JRoute` looks a link up as sh404SEF's `shSefRelToAbs()` did before anything else: `lang=en`
  added when missing, sorted by `shSortUrl()`, then the stored row of that exact newurl whatever its
  rank, the oldest first (`getSefUrlFromDatabase()`: no ORDER BY). Five casino pages were first
  built while their article had no title and got "bonuses/" (rank > 0); the live site links them
  so (the breadcrumb of `/bonuses/cashy-casino/25percent-cashback-bonus-id9671`, 29 Oct 2026),
  and so does v3. sh404SEF's URL cache, consulted before the table, is not reproduced.
- Query strings that `JRoute` appends (the variables sh404SEF did not store) are decoded, as
  J2.5's `JURI::buildQuery()` wrote them (`urldecode(http_build_query())`): the directory's chips
  link `?filters=accepts_country,crypto`, not `%2C`; spaces become `%20` (JRoute::_).
- `jos_redirection` has the schema of sh404SEF 1.x's URL table (cpt, rank, dateadd): it is that
  old table, not a list of the desk's redirects; the router still consults it last, as the Joomla
  redirect plugin did on a 404 (to be re-read in phase 4).

- `jexit()`/`JApplication::close()` and `JApplication::redirect()` raise `ExitException` and
  `RedirectException`, both `Error`s: on the live site they ended the request, and the 213
  `catch (Exception $e)` of the legacy code never saw them. As `RuntimeException`s they were
  caught: the blog listing's try/catch swallowed the guard's 301 of `/blog/feed/rss` (the live site
  renders the feed URLs as the HTML listing, whose guard sends them to `/blog`) and printed
  "Blog temporarily unavailable" with a 200 (29 Oct 2026).

## Known legacy behaviour reproduced on purpose (parity)

- sh404SEF wrote a page title into the HTML as a preg replacement string: on a review (catid 8)
  the "Title and metas" row's title goes in unprotected, so "$1000 Free Play" shows as "00 Free
  Play" (`/online-casinos/eurofortune`: "$10" is an empty backreference); on the other pages its
  own escaping keeps the dollars. `Sh404sefPage::replaceTitle()` runs the same `preg_replace`.
- `JRegistry` read a params or metadata string that is not wrapped in braces with its INI reader
  (Joomla 2.5's JSON format handed it over): old articles still carry Joomla 1.5 metadata
  (`robots=index, follow`, jos_content 88) and the live page prints that robots. `Registry` does
  the same (`Registry::iniToArray()` is JRegistryFormatINI without sections).
- Only the `authors` and `author` views load the lib themselves; the other views call `fec_*`
  inside `function_exists` guards and, as on the live site, render without breadcrumbs/JSON-LD
  when the lib is not loaded first. The runner does not pre-load the lib.
- `$bonustypeMap`/`$currencyMap` are never defined by the helper files (`$bonustype`/`$currency`
  are): the views' hardcoded fallback maps are what renders, as on the live site.
