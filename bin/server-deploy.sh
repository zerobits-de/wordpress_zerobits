#!/usr/bin/env bash
# Install a theme package on the production server.
#
# Runs ON THE SERVER as the deploy user. It is the forced command of the SSH key
# used by CI (authorized_keys: restrict,command="…"), so that key can do nothing
# else. The package arrives on stdin:
#
#   ssh deploy-user@server < build/zb-theme.zip
#
# Layout in the deploy user's home directory:
#
#   releases/<version>/   one unpacked theme per deployment (the last $KEEP are kept)
#   current               symlink to the active release
#   www/                  WordPress; wp-content/themes/zb-theme is a symlink to ../current
#
# Steps: unpack, switch `current`, reload the web server (PHP caches resolved
# paths), smoke-test the live site, and switch back if the test fails.
#
# The installed copy on the server belongs to root, so a deployment cannot
# change it. After editing this file, install it again (see deployment.md).
set -euo pipefail

BASE="${ZB_DEPLOY_BASE:-$HOME}"
SITE_URL="${ZB_SITE_URL:-https://zerobits.de}"
KEEP="${ZB_KEEP_RELEASES:-5}"
RELEASES="$BASE/releases"
CURRENT="$BASE/current"

# Routes that have to answer after a deployment: "<path> <expected status>".
ROUTES="
/ 200
/de/ 200
/blog/ 200
/de/blog/ 200
/impress/ 200
/de/impressum/ 200
/privacy-policy/ 200
/de/datenschutz/ 200
/no-such-page-xyz/ 404
"

log() { printf '%s\n' "$*"; }
die() { printf 'FAIL  %s\n' "$*" >&2; exit 1; }

mkdir -p "$RELEASES"
WORK="$(mktemp -d "$BASE/.deploy.XXXXXX")"
trap 'rm -rf "$WORK"' EXIT

# One deployment at a time.
exec 9>"$BASE/.deploy.lock"
flock -n 9 || die "another deployment is running"

log "==> Receiving package"
cat > "$WORK/theme.zip"
[ -s "$WORK/theme.zip" ] || die "no package on stdin"
unzip -tqq "$WORK/theme.zip" || die "package is not a valid ZIP"
unzip -q "$WORK/theme.zip" -d "$WORK/unpacked"

SRC="$WORK/unpacked/zb-theme"
for f in style.css functions.php index.php vendor/autoload.php content/en.json content/de.json; do
  [ -f "$SRC/$f" ] || die "package has no zb-theme/$f"
done
[ -z "$(find "$SRC" -type l -print -quit)" ] || die "package contains symlinks"

VERSION="$(sed -n 's/^Version:[[:space:]]*//p' "$SRC/style.css" | tr -d '\r' | head -n1)"
printf '%s' "$VERSION" | grep -qE '^[0-9]+\.[0-9]+\.[0-9]+([-.][0-9A-Za-z.-]+)?$' \
  || die "not a valid version in style.css: '$VERSION'"

TARGET="$RELEASES/$VERSION"
PREVIOUS="$(readlink "$CURRENT" 2>/dev/null || true)"

log "==> Installing release $VERSION"
if [ -d "$TARGET" ]; then
  # The same build again (a re-run of the workflow): replace it, unless it is live.
  if [ "$PREVIOUS" = "$TARGET" ]; then
    log "    $VERSION is already active, checking it again"
  else
    rm -rf "$TARGET"
  fi
fi
if [ ! -d "$TARGET" ]; then
  chmod -R u=rwX,go=rX "$SRC"
  mv "$SRC" "$TARGET"
fi

activate() {
  ln -sfn "$1" "$CURRENT.new"
  mv -T "$CURRENT.new" "$CURRENT"
  sudo -n /usr/bin/systemctl reload apache2
  sleep 1
}

smoke_test() {
  local failed=0 path expect code body="$WORK/body"
  while read -r path expect; do
    [ -n "$path" ] || continue
    code="$(curl -s -o "$body" -m 20 -w '%{http_code}' "$SITE_URL$path" || true)"
    if [ "$code" != "$expect" ]; then
      log "FAIL  $path -> HTTP $code (expected $expect)"; failed=1
    elif grep -qiE 'fatal error|Warning:|kritischer Fehler|critical error' "$body"; then
      log "FAIL  $path contains PHP errors"; failed=1
    else
      log "ok    $path ($code)"
    fi
  done <<< "$ROUTES"
  # The page has to come from this release, not from a cached older one.
  if curl -s -m 20 "$SITE_URL/" | grep -qF "ver=$VERSION"; then
    log "ok    assets are served as version $VERSION"
  else
    log "FAIL  the homepage does not reference version $VERSION"; failed=1
  fi
  return "$failed"
}

log "==> Activating"
activate "$TARGET"

log "==> Smoke test $SITE_URL"
if ! smoke_test; then
  if [ -n "$PREVIOUS" ] && [ "$PREVIOUS" != "$TARGET" ] && [ -d "$PREVIOUS" ]; then
    activate "$PREVIOUS"
    die "smoke test failed, switched back to $(basename "$PREVIOUS")"
  fi
  die "smoke test failed and there is no previous release to switch back to"
fi

log "==> Removing old releases (keeping $KEEP)"
# Newest first; the active and the previous release always stay.
find "$RELEASES" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' | sort -rn | cut -d' ' -f2- \
  | tail -n "+$((KEEP + 1))" | while read -r old; do
    [ "$old" = "$TARGET" ] || [ "$old" = "$PREVIOUS" ] || { rm -rf "$old"; log "    removed $(basename "$old")"; }
  done

log "Done. $SITE_URL runs release $VERSION"
