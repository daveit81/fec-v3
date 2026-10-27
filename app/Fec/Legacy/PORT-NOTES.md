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

## Known legacy behaviour reproduced on purpose (parity)

- Only the `authors` and `author` views load the lib themselves; the other views call `fec_*`
  inside `function_exists` guards and, as on the live site, render without breadcrumbs/JSON-LD
  when the lib is not loaded first. The runner does not pre-load the lib.
- `$bonustypeMap`/`$currencyMap` are never defined by the helper files (`$bonustype`/`$currency`
  are): the views' hardcoded fallback maps are what renders, as on the live site.
