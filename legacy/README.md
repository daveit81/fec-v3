# legacy/ — read-only snapshot of the FEC code that still runs under Joomla

This directory is **not part of the application**. It is a text-only copy of our own
legacy code (components, helpers, template, plugins, entry points) taken from the live
docroot with `scripts/legacy-snapshot.sh`, kept here for one reason: the mechanical port
to PHP 8 (`app/Fec/Legacy/`, with Rector) needs a stable, versioned input, and every
re-snapshot shows in `git diff` exactly what moved on the live site.

- Never deployed, never autoloaded (outside `app/`, outside Composer's PSR-4 map).
- Never edited by hand: edits belong to the live site until a section is switched, then to
  the ported code. Re-run the snapshot instead.
- No secrets: `conf/` directories are excluded and the script prints a secret scan
  (file:line only) to review before committing. If a key ever lands here, rotate it.
- Joomla core and third-party extensions (JReviews, sh404SEF, AcyMailing, Community
  Builder) are deliberately absent: they are what the port removes, not what it ports.

`SNAPSHOT.txt` records when the copy was taken, how many files it holds and the md5 of
`fec_common_lib.php`, so the port can state which lib version it started from.

## Secrets (30 Oct 2026)

The first snapshots (26 and 28 Oct, commits 860e478 and 7c31f90) carried the database password
in 11 files (com_jackpots controllers, helpers and views, `com_bonuses/chron/statusupd.php`) and
the com_fecapi bearer token (`com_fecapi/helpers/auth.php`): the keyword scan did not flag
hardcoded connection arguments. Both are redacted here (`<REDACTED:label>`) and
`scripts/legacy-snapshot.sh` now replaces the live site's known secrets by value in every copy.
The database password was rotated on the live site the same day, so the copies left in the
history are dead; the token is rotated when its caller is known.
