#!/bin/bash
#
# legacy-snapshot.sh - copy the FEC legacy code (our Joomla components,
# helpers, template, plugins and entry points) from the live docroot into
# legacy/ of this repository, as text only, for the mechanical PHP 8 port.
#
# Run as freeextr (never as root: root-owned files in the repo break git am):
#   su -s /bin/bash freeextr -c '/home/freeextr/fecv3/scripts/legacy-snapshot.sh'
# With the cron entry points (phase 5), root dumps the crontab first and passes it:
#   crontab -l -u freeextr > /home/freeextr/.fec_crontab_dump; chown freeextr: /home/freeextr/.fec_crontab_dump
#   su -s /bin/bash freeextr -c 'FEC_CRONTAB=/home/freeextr/.fec_crontab_dump /home/freeextr/fecv3/scripts/legacy-snapshot.sh'
#   rm -f /home/freeextr/.fec_crontab_dump
# fec-admin/fec_cofm_cron.php comes only that way: its key is redacted by the value read from the crontab.
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
# Never copied: fec_review_config.php (Turnstile key), the parsers of com_jackpots and com_tournaments
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
  webhook
  api
)

# Phase 5: the crontab's entry points that still run on PHP 5.6 or through Joomla. Read from a
# dump of the crontab written by root (FEC_CRONTAB=file; `crontab -l -u freeextr`): a script
# under the docroot lands at its docroot path, one elsewhere in the home under legacy/_home/.
# The crontab itself is copied to legacy/_home/crontab.txt with keys, tokens and passwords
# replaced; the key= values of its URLs are redacted by value in every copied file too
# (fec-admin/fec_cofm_cron.php holds its own key: without a readable dump it is not copied).
CRONTAB="${FEC_CRONTAB:-}"
HOME_SRC="${FEC_HOME:-/home/freeextr}"
CRON_SCRIPTS='(bonuses_autoexpire\.php|send_weekly_digest\.php|fec_generate_llmstxt\.php|jackpots_update\.sh|fec_cofm_cron\.php)$'

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

cron_keys=()
cron_files=()
if [[ -n "$CRONTAB" && -r "$CRONTAB" ]]; then
  mkdir -p "$DST/_home"
  # keys, tokens, passwords, bot tokens and long literal tokens out of the copy of the crontab
  sed -E \
      -e "s/(([?&]|[[:space:]])(key|token|secret|pass[a-z]*|pwd)=)[^&[:space:]\"']+/\1<REDACTED>/Ig" \
      -e "s/(--password=)[^[:space:]]+/\1<REDACTED>/g" \
      -e "s/((mysql|mysqldump|mysqlcheck)[^|;]*[[:space:]])-p[^[:space:]]+/\1-p<REDACTED>/g" \
      -e "s/bot[0-9]+:[A-Za-z0-9_-]+/bot<REDACTED>/g" \
      -e "s/(Bearer[[:space:]]+)[A-Za-z0-9._~+\/=-]+/\1<REDACTED>/Ig" \
      -e "s/[A-Za-z0-9]{32,}/<REDACTED:long>/g" \
      "$CRONTAB" > "$DST/_home/crontab.txt"
  grep -vE '^[[:space:]]*#' "$CRONTAB" | grep -oE '[?&](key|token|secret)=[A-Za-z0-9_-]+' | cut -d= -f2 | sort -u > "$list" || true
  while IFS= read -r k; do [[ -n "$k" ]] && cron_keys+=("$k"); done < "$list"
  printf '%-48s %5d lines, %d URL keys\n' "crontab (dump)" "$(grep -cvE '^[[:space:]]*(#|$)' "$CRONTAB")" "${#cron_keys[@]}"
  # the entry points: absolute paths under the home and the site's own URLs
  grep -vE '^[[:space:]]*#' "$CRONTAB" \
    | grep -oE "(${HOME_SRC}/[A-Za-z0-9_./-]+|https?://[A-Za-z0-9.-]*freeextrachips\.com/[A-Za-z0-9_./-]+)" \
    | grep -E "$CRON_SCRIPTS" | sort -u > "$list" || true
  while IFS= read -r ref; do
    case "$ref" in
      http*) rel="${ref#*://}"; rel="${rel#*/}"; src="$SRC/$rel"; dst="$DST/$rel" ;;
      "$SRC"/*) rel="${ref#"$SRC"/}"; src="$ref"; dst="$DST/$rel" ;;
      "$HOME_SRC"/*) rel="${ref#"$HOME_SRC"/}"; src="$ref"; dst="$DST/_home/$rel" ;;
      *) continue ;;
    esac
    if [[ ! -f "$src" ]]; then printf '%-48s (in the crontab, not on disk)\n' "$ref"; continue; fi
    if [[ "$(basename "$src")" == fec_cofm_cron.php && ${#cron_keys[@]} -eq 0 ]]; then
      printf '%-48s (no key read from the crontab: not copied)\n' "$ref"; continue
    fi
    mkdir -p "$(dirname "$dst")"
    cp -p "$src" "$dst"
    cron_files+=("$dst")
    printf '%-48s copied\n' "${dst#"$DST"/}"
  done < "$list"
  total=$((total + ${#cron_files[@]}))
else
  printf '%-48s (no FEC_CRONTAB dump: cron entry points skipped)\n' "crontab"
fi

{
  echo "snapshot_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "source=$SRC"
  echo "files=$total"
  echo "fec_common_lib_md5=$(md5sum components/com_bonuses/helpers/fec_common_lib.php 2>/dev/null | cut -c1-32)"
} > "$DST/SNAPSHOT.txt"

# The live site's known secrets, read from its own files, are replaced by value in the copy
# (never printed): the database password, Joomla's secret and mail/FTP passwords from
# configuration.php, the com_fecapi bearer token. On 30 Oct 2026 the database password was
# found in 11 committed files (com_jackpots, chron/statusupd.php) that the keyword scan below
# had not flagged: hardcoded copies are caught by value, whatever the code around them.
redact_value() { # value label
  local value="$1" label="$2"
  [ "${#value}" -ge 8 ] || return 0
  { grep -rlF -- "$value" "$DST" 2>/dev/null || true; } | while IFS= read -r f; do
    V="$value" L="$label" perl -pi -e 'BEGIN { $v = $ENV{V}; $l = $ENV{L} } s/\Q$v\E/<REDACTED:$l>/g' "$f"
    echo "  redacted $label in ${f#"$DST"/}"
  done
}
echo
echo "Redacting the live site's known secrets in the copy:"
for key in password secret smtppass ftp_pass; do
  redact_value "$(sed -nE "s/.*public \\\$$key = '([^']*)'.*/\1/p" "$SRC/configuration.php" 2>/dev/null)" "$key"
done
redact_value "$(sed -nE "s/.*expected = '([^']*)'.*/\1/p" "$SRC/components/com_fecapi/helpers/auth.php" 2>/dev/null)" fecapi-token
for k in "${cron_keys[@]}"; do redact_value "$k" cron-key; done
# The cron entry points and the webhook are copied for the first time: any quoted literal assigned
# to a key/secret/token/password name is replaced as well, whatever its value.
for f in "${cron_files[@]}" $(find "$DST/webhook" -type f 2>/dev/null); do
  if perl -0ne 'exit((/(?:api[_-]?key|secret|token|passw(?:or)?d|pass|pwd)[A-Za-z0-9_]*[\x27"]?\s*(?:=>|=|:|,)\s*[\x27"](?!<REDACTED)[^\x27"\s]{8,}[\x27"]/i) ? 0 : 1)' "$f"; then
    perl -pi -e 's/((?:api[_-]?key|secret|token|passw(?:or)?d|pass|pwd)[A-Za-z0-9_]*[\x27"]?\s*(?:=>|=|:|,)\s*[\x27"])(?!<REDACTED)[^\x27"\s]{8,}([\x27"])/$1<REDACTED:literal>$2/gi' "$f"
    echo "  redacted literal(s) in ${f#"$DST"/}"
  fi
done

echo
echo "Total: $total files in $DST ($(du -sh "$DST" | cut -f1))"
echo
echo "Secret scan (file:line only, no values) - review before committing:"
# Two shapes: "$x = ..." / "'key' => ..." and define('..._KEY', '...').
{
  grep -rnoiE '(api[_-]?key|secret|passw(or)?d|token|bot_?id)\s*(=|=>|:)' "$DST" --include='*.php' --include='*.ini' --include='*.json' \
    | grep -viE 'csrf|nonce|token_?name|formtoken|JSession|checkToken|getToken|freshtoken|tokenise|placeholder'
  grep -rnoiE "define\s*\(\s*['\"][A-Z0-9_]*(KEY|SECRET|TOKEN|PASS(WORD)?|PWD)[A-Z0-9_]*['\"]\s*,\s*['\"][^'\"]{8,}" "$DST" --include='*.php'
  # literal connection arguments and long literal tokens (the 30 Oct 2026 misses)
  grep -rnoiE "(new mysqli|mysqli_connect|new PDO)\s*\(\s*['\"][^'\"]*['\"]\s*,\s*['\"][^'\"]+['\"]\s*,\s*['\"][^'\"<]{6,}" "$DST" --include='*.php'
  grep -rnoE "=\s*['\"][A-Za-z0-9]{32,}['\"]" "$DST" --include='*.php' | grep -viE 'md5|sha|hash|checksum|uuid'
} | cut -d: -f1,2 | sort | uniq | sed "s#^$DST/#  #" || true

# The copies made for the first time in this run (cron entry points, webhook, crontab): every line
# that still looks like a credential. Empty = safe to commit and push; otherwise read those lines.
# (Temporary files, not process substitution: CageFS has no /dev/fd.)
new_copies=("${cron_files[@]}")
[[ -f "$DST/_home/crontab.txt" ]] && new_copies+=("$DST/_home/crontab.txt")
find "$DST/webhook" -type f > "$list" 2>/dev/null || : > "$list"
while IFS= read -r f; do new_copies+=("$f"); done < "$list"
echo
echo "Scan of the new copies (cron entry points, webhook, crontab) - must be empty before pushing:"
hits=0
for f in "${new_copies[@]}"; do
  [[ -f "$f" ]] || continue
  grep -nE "(api[_-]?key|secret|passw(or)?d|token|pwd|auth)[A-Za-z0-9_]*['\"]?[[:space:]]*(=>|=|:|,)[[:space:]]*['\"][^'\"]{8,}|['\"][A-Za-z0-9_-]{24,}['\"]|\bre_[A-Za-z0-9]{16,}|bot[0-9]{6,}:|Bearer[[:space:]]+[A-Za-z0-9._~+/=-]{8,}|(new mysqli|mysqli_connect|new PDO)[[:space:]]*\(" "$f" \
    | grep -v 'REDACTED' | cut -d: -f1 > "$list" || true
  while IFS= read -r ln; do
    echo "  ${f#"$DST"/}:$ln"; hits=$((hits + 1))
  done < "$list"
done
if [[ $hits -eq 0 ]]; then echo "  (none)"; else echo "  $hits line(s) to read before pushing"; fi
