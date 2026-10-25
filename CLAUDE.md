# FEC v3 — working notes for agents and humans

FreeExtraChips.com is leaving Joomla 2.5 / PHP 5.6 for this Laravel 13 + Filament 5 application,
one page at a time (strangler migration). The roadmap, decisions and runbooks live in the
"FreeExtraChips" Claude project (`FEC-v3-piano-migrazione.md`, `FEC-v3-migrazione-server-runbook.md`).

## Where it runs

- Server: KnownHost managed dedicated, AlmaLinux 9 + CloudLinux 9, cPanel, LiteSpeed. Account `freeextr`.
- App root `/home/freeextr/fecv3/`, docroot `/home/freeextr/fecv3/public` = `https://v3.freeextrachips.com`
  (Cloudflare proxied, behind Cloudflare Access; origin accepts only Cloudflare IPs).
- PHP 8.4 is the CloudLinux alt-php: CLI `/opt/alt/php84/usr/bin/php`, web via
  `AddHandler application/x-httpd-alt-php84 .php` in `public/.htaccess`. Inside CageFS every
  `/opt/cpanel/ea-php*/bin/php` and `/usr/local/bin/php` is the Selector's PHP 5.6: never use them.
- Composer: `/opt/alt/php84/usr/bin/php /home/freeextr/bin/composer`.
- Run everything as `freeextr` (no `su`/`rsync` inside the cage; root-owned files break the app).
- Deploy: this repository (`daveit81/fec-v3`) is the only channel. Claude pushes to `main`,
  the server pulls with a read-only deploy key: `cd /home/freeextr/fecv3 && git pull`.
  Never edit code on the server by hand.

## Databases

- Default connection (`DB_CONNECTION`, SQLite for now): framework tables only (sessions, cache, jobs, users).
- Connection `fec`: the live legacy catalogue `freeextr_fec25`, prefix `jos_`, **read-only user**
  during phase 0/1. Our own tables (`jos_bonuses`, `jos_fec_*`, `jos_atlas_*`, …) keep their names
  and ids; `jos_content`, `jos_jreviews_content`, `jos_sh404sef_urls` are read as they are.
- Secrets live only in `.env` on the server (never committed).

## Rules

- Parity, not improvement, until Joomla is switched off: same URLs, same markup (`.fecx`,
  `fec-edition.css`), same data. Improvements belong to phase 6.
- ASCII in PHP string literals; raw UTF-8 only in Blade/CSS files.
- No debug logs under the docroot; never log request payloads or user arrays.
- Every switch of a section is preceded by a parity script and followed by a rollback path
  (one line in the legacy `.htaccess`).

## Desk Lint

- `php artisan fec:desk-lint --probe` prints the value distributions the rules rely on; calibrate
  `FEC_BONUS_ACTIVE_WHERE`, `FEC_BONUS_WELCOME_TYPES`, `FEC_BONUS_CURRENCIES` from it.
- Rules live in `app/DeskLint/Rules`, one class each, explicit SQL with `$this->t('table')` for the
  prefix (the query builder prefixes aliases too, so never `table('bonuses as b')` with raw columns).
- JReviews checkbox flags (`jr_closed`, `jr_suspended`, `jr_blacklisted`) are set when non-empty
  (`*yes*`, `*susp*`, `*bl*`), never compared to "yes".
- Memory table `desk_lint_findings` (framework DB); `--dry-run` leaves it untouched.
