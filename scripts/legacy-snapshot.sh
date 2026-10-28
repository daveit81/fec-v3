#!/bin/bash
#
# legacy-snapshot.sh - copy the FEC legacy code (our Joomla components,
# helpers, template, plugins and entry points) from the live docroot into
# legacy/ of this repository, as text only, for the mechanical PHP 8 port.
#
# Run as freeextr (never as root: root-owned files in the repo break git am):
#   su -s /bin/bash freeextr -c '/home/freeextr/fecv3/scripts/legacy-snapshot.sh'
#
# Idempotent: legacy/ is emptied and rebuilt, so `git status` after a run
# shows exactly what changed on the live site since the last snapshot.
# Nothing here is ever deployed or autoloaded; see legacy/README.md.
#
# Excluded on purpose: conf/ directories (keys), fec_review_config.php
# (Turnstile keys, lives next to the component), cache/ logs/ tmp/, backups
# (*_bk*, *_before_*, *.bak, *.orig), binaries (images, archives, fonts).
# Run the secret scan printed at the end before committing.

set -euo pipefail

SRC="${FEC_DOCROOT:-/home/freeextr/public_html}"
DST="${FEC_V3_ROOT:-/home/freeextr/fecv3}/legacy"

# Our code only: no Joomla core, no third-party extensions (JReviews, sh404SEF,
# AcyMailing, Community Builder). Missing roots are reported, not fatal.
# Never copied: fec_review_config.php (Turnstile key), fec-admin/fec_cofm_cron.php
# (the cron key is in the file), the parsers of com_jackpots and com_tournaments
# (every parser file defines the database password), the simplehtmldom library
# of com_tournaments, the data/ of com_jackpots (9.000 JSON files of jackpot
# values, 223 MB: data written by the parsers, not code).
ROOTS=(
  components/com_bonuses
  components/com_authors
  components/com_atlas
  components/com_partnerdesk
  components/com_fecapi
  components/com_fecsearch
  components/com_shortlink
  administrator/components/com_bonuses
  administrator/components/com_authors
  administrator/components/com_atlas
  administrator/components/com_partnerdesk
  administrator/components/com_fecapi
  administrator/components/com_shortlink
  components/com_tournaments
  components/com_jackpots
  administrator/components/com_tournaments
  administrator/components/com_jackpots
  templates/fecv2
  plugins/system/fecclustermeta
  plugins/system/cfcookie
  plugins/system/feccanonicalfix
  plugins/system/fectemplateswitch
  plugins/system/geoip
  plugins/content/fechome
  plugins/content/feccounts
  renders
  fec-admin
  goto.php
  freshtoken.php
  r.php
  subscribe.php
  get_bonuses.php
  ajax_review_submit.php
  og_image.php
  fec_dashboard.php
  fec_dash_api.php
  fec_brandmap_build.php
  fec_cmp_build.php
  fec_listing_v2.php
)

if [[ "$(id -un)" != "freeextr" ]]; then
  echo "Run as freeextr, not $(id -un)" >&2
  exit 1
fi
[[ -d "$SRC" ]] || { echo "Docroot not found: $SRC" >&2; exit 1; }

# Rebuild everything but README.md (tracked, written by hand).
find "$DST" -mindepth 1 ! -name 'README.md' -delete 2>/dev/null || true
mkdir -p "$DST"
cd "$SRC"

# No process substitution (< <(find ...)): CageFS has no /dev/fd, so the file
# list goes through a temporary file instead.
list="$(mktemp "${TMPDIR:-/tmp}/legacy-snapshot.XXXXXX")"
trap 'rm -f "$list"' EXIT

total=0
for root in "${ROOTS[@]}"; do
  if [[ ! -e "$root" ]]; then
    printf '%-48s (not on the live site, skipped)\n' "$root"
    continue
  fi
  find "$root" -type f \
      \( -name '*.php' -o -name '*.xml' -o -name '*.ini' -o -name '*.sql' -o -name '*.css' -o -name '*.js' \
         -o -name '*.html' -o -name '*.htm' -o -name '*.json' -o -name '*.txt' -o -name '*.md' -o -name '.htaccess' \) \
      ! -path '*/conf/*' ! -path '*/cache/*' ! -path '*/logs/*' ! -path '*/tmp/*' \
      ! -name '*_bk*' ! -name '*_before_*' ! -name '*.bak' ! -name '*.orig' ! -name '*.log' \
      ! -name 'fec_review_config.php' ! -name 'fec_cofm_cron.php' \
      ! -path '*/simplehtmldom/*' ! -path '*/com_jackpots/parser/*' ! -path '*/com_tournaments/parser/*' \
      ! -path '*/com_jackpots/data/*' \
      -print0 > "$list"
  n=0
  while IFS= read -r -d '' f; do
    cp --parents -p "$f" "$DST/"
    n=$((n + 1))
  done < "$list"
  printf '%-48s %5d files\n' "$root" "$n"
  total=$((total + n))
done

{
  echo "snapshot_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "source=$SRC"
  echo "files=$total"
  echo "fec_common_lib_md5=$(md5sum components/com_bonuses/helpers/fec_common_lib.php 2>/dev/null | cut -c1-32)"
} > "$DST/SNAPSHOT.txt"

echo
echo "Total: $total files in $DST ($(du -sh "$DST" | cut -f1))"
echo
echo "Secret scan (file:line only, no values) - review before committing:"
# Two shapes: "$x = ..." / "'key' => ..." and define('..._KEY', '...').
{
  grep -rnoiE '(api[_-]?key|secret|passw(or)?d|token|bot_?id)\s*(=|=>|:)' "$DST" --include='*.php' --include='*.ini' --include='*.json' \
    | grep -viE 'csrf|nonce|token_?name|formtoken|JSession|checkToken|getToken|freshtoken|tokenise|placeholder'
  grep -rnoiE "define\s*\(\s*['\"][A-Z0-9_]*(KEY|SECRET|TOKEN|PASS(WORD)?|PWD)[A-Z0-9_]*['\"]\s*,\s*['\"][^'\"]{8,}" "$DST" --include='*.php'
} | cut -d: -f1,2 | sort | uniq | sed "s#^$DST/#  #" || true
