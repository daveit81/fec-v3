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
  of com_authors, `actions.php`, `mobileapp.php`, `views/tc`, `views/ads`.

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

- `components/com_bonuses/`: verbatim copy of the site component (phase 3) without the static
  assets (`css/`, `js/`, `data/`: served from the live docroot), the standalone scripts (`ajax/`,
  `chron/`, `scripts/`, `ajax_review_submit.php`: they keep running on Joomla by direct path until
  phase 5), `admin/` and `sef_ext/` (the sh404SEF URL plugin, ported as a class of the layer
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

## The legacy .htaccess, reproduced where it reaches our paths

`public_html/.htaccess` redirects some paths under `bonuses/` itself (mod_alias `Redirect 301`,
`RedirectMatch`, `RewriteRule [R=301]`: the retired bonus types, `bonuses/free-spins` to its slash
form, `bonuses/ads/` to `bonuses/ctr/`, replaced bonuses, every country under `allowed-countries/`
but au and nz). With the v3 switch block at the top of that file those requests reach v3, so
`App\Fec\Url\HtaccessRedirects` answers them first, rule by rule with each directive's matching
(`fec.legacy.htaccess_redirects`, file order, targets as written). When a line is added to the live
file under a prefix v3 serves, it is added there too.

## Known legacy behaviour reproduced on purpose (parity)

- Only the `authors` and `author` views load the lib themselves; the other views call `fec_*`
  inside `function_exists` guards and, as on the live site, render without breadcrumbs/JSON-LD
  when the lib is not loaded first. The runner does not pre-load the lib.
- `$bonustypeMap`/`$currencyMap` are never defined by the helper files (`$bonustype`/`$currency`
  are): the views' hardcoded fallback maps are what renders, as on the live site.
