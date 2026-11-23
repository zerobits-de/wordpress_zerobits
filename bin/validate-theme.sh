#!/usr/bin/env sh
# Structural validation of the theme directory (or a built package directory).
# Usage: sh bin/validate-theme.sh <theme-dir> [--require-vendor]
set -eu

THEME="${1:?theme directory required}"
REQUIRE_VENDOR="${2:-}"
STATUS=0

fail() { echo "FAIL  $1"; STATUS=1; }
ok()   { echo "ok    $1"; }

for f in style.css functions.php index.php composer.json composer.lock theme.json \
         content/site.json content/en.json content/de.json \
         templates/base.twig templates/home.twig templates/service.twig templates/project.twig templates/blog.twig templates/single.twig templates/legal.twig \
         templates/page.twig templates/404.twig views/partials/header.twig views/partials/footer.twig \
         views/components/lang-switch.twig assets/css/tokens.css assets/css/fonts.css assets/js/main.js \
         assets/images/logo.svg assets/images/favicon.png; do
  if [ -f "$THEME/$f" ]; then ok "$f exists"; else fail "$f is missing"; fi
done

for header in "Theme Name" "Version" "Text Domain" "Requires PHP" "Requires at least"; do
  if grep -q "^$header:" "$THEME/style.css"; then ok "style.css has '$header'"; else fail "style.css lacks '$header'"; fi
done

if [ "$(grep '^Text Domain:' "$THEME/style.css" | awk '{print $3}')" = "zb-theme" ]; then
  ok "text domain is zb-theme"
else
  fail "text domain must be zb-theme"
fi

# Nothing that must never ship.
for bad in .env node_modules .git; do
  if [ -e "$THEME/$bad" ]; then fail "$bad must not be inside the theme"; fi
done

# No third-party requests from the front end (GDPR): no CDN URLs in templates, CSS or JS.
EXTERNAL=$(grep -rlE 'https?://(fonts\.googleapis|fonts\.gstatic|cdn\.|cdnjs|unpkg|ajax\.googleapis)' "$THEME/templates" "$THEME/views" "$THEME/assets/css" "$THEME/assets/js" 2>/dev/null || true)
if [ -n "$EXTERNAL" ]; then fail "third-party URLs found: $EXTERNAL"; else ok "no third-party assets"; fi

if [ "$REQUIRE_VENDOR" = "--require-vendor" ]; then
  if [ -f "$THEME/vendor/autoload.php" ]; then ok "vendor/autoload.php present"; else fail "vendor/autoload.php missing (production package must include Composer dependencies)"; fi
  if [ -d "$THEME/vendor/squizlabs" ] || [ -d "$THEME/vendor/phpstan" ]; then fail "dev dependencies found in production package"; else ok "no dev dependencies in package"; fi
fi

# PHP files must not contain HTML blocks (presentation belongs in Twig).
HTML_PHP=$(grep -lE '^\s*<(div|section|header|footer|ul|nav)\b' "$THEME"/*.php "$THEME"/src/*.php "$THEME"/src/*/*.php 2>/dev/null || true)
if [ -n "$HTML_PHP" ]; then fail "HTML markup found in PHP: $HTML_PHP"; else ok "no HTML blocks in PHP files"; fi

[ "$STATUS" -eq 0 ] && echo "Theme structure OK" || echo "Theme structure validation FAILED"
exit $STATUS
