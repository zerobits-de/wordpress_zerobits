#!/usr/bin/env bash
# Complete the WordPress installation with WP-CLI.
# Runs INSIDE the wordpress container:  make install
#
# Idempotent: safe to run repeatedly; existing articles and settings are kept.
#
# The site's texts come from the theme (theme/content/), not from WordPress.
# This script only sets WordPress up and imports the blog articles in
# seed/posts/ (the articles of the former site, in English and German).
set -euo pipefail

WP_PATH="${WP_PATH:-/var/www/html}"
ALLOW_ROOT=""
[ "$(id -u)" = "0" ] && ALLOW_ROOT="--allow-root"
WP="wp $ALLOW_ROOT --path=$WP_PATH"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SEED_DIR="${SEED_DIR:-$SCRIPT_DIR/../seed}"
URL="${WORDPRESS_URL:-http://localhost:${WORDPRESS_PORT:-8093}}"
TITLE="${WORDPRESS_SITE_TITLE:-Zerobits}"
SEED_POSTS="${SEED_POSTS:-1}"

echo "==> Waiting for the database"
for i in $(seq 1 30); do
  if $WP db check >/dev/null 2>&1; then break; fi
  sleep 2
done

if ! $WP core is-installed >/dev/null 2>&1; then
  echo "==> Installing WordPress at $URL"
  $WP core install \
    --url="$URL" \
    --title="$TITLE" \
    --admin_user="${WORDPRESS_ADMIN_USER:-admin}" \
    --admin_password="${WORDPRESS_ADMIN_PASSWORD:-admin}" \
    --admin_email="${WORDPRESS_ADMIN_EMAIL:-admin@example.com}" \
    --locale="${WORDPRESS_LOCALE:-de_DE}" \
    --skip-email
else
  URL="$($WP option get home)"
  echo "==> WordPress already installed at $URL"
fi

echo "==> Admin language"
LOCALE="${WORDPRESS_LOCALE:-de_DE}"
if [ "$LOCALE" != "en_US" ]; then
  $WP language core install "$LOCALE" --activate >/dev/null 2>&1 || true
fi

echo "==> Theme and settings"
$WP theme activate zb-theme
$WP option update blogname "$TITLE" >/dev/null
$WP option update blogdescription "Odoo, ERP, Automation & Software development" >/dev/null
$WP option update show_on_front posts >/dev/null
$WP rewrite structure '/%postname%/' >/dev/null
$WP option update timezone_string 'Europe/Berlin' >/dev/null
$WP option update default_comment_status closed >/dev/null
$WP option update default_ping_status closed >/dev/null
# Language terms (also created on theme activation) and fresh rewrite rules.
$WP eval 'ZB\Theme\Functions\BlogLanguage::ensure_terms();'
$WP option delete zb_rewrite_rules >/dev/null 2>&1 || true
$WP rewrite flush >/dev/null

echo "==> Removing WordPress sample content"
for item in post:hello-world page:sample-page page:privacy-policy; do
  for id in $($WP post list --post_type="${item%%:*}" --post_status=any --name="${item##*:}" --format=ids); do $WP post delete "$id" --force >/dev/null; done
done
$WP comment delete 1 --force >/dev/null 2>&1 || true

if [ "$SEED_POSTS" = "1" ] && [ -d "$SEED_DIR/posts" ]; then
  echo "==> Blog articles"
  AUTHOR_ID=$($WP user list --role=administrator --field=ID --orderby=ID --order=ASC | head -n1)
  # File name: {lang}--{slug}.html. Header lines: "<!-- title: ... -->", "<!-- excerpt: ... -->",
  # optional "<!-- image: file.jpg -->" (from seed/images/) and "<!-- date: YYYY-MM-DD HH:MM:SS -->".
  for file in "$SEED_DIR"/posts/*.html; do
    base="$(basename "$file" .html)"
    lang="${base%%--*}"
    slug="${base#*--}"
    existing="$($WP post list --post_type=post --post_status=any --name="$slug" --format=ids)"
    if [ -n "$existing" ]; then
      # An article of the former site that is already in the database: only give it its language.
      for id in $existing; do
        if [ -z "$($WP post term list "$id" zb_language --field=slug 2>/dev/null)" ]; then
          $WP post term set "$id" zb_language "$lang" --by=slug >/dev/null
          echo "    $slug exists, language set to $lang"
        else
          echo "    $slug exists"
        fi
      done
      continue
    fi
    title="$(sed -n 's/^<!-- title: \(.*\) -->$/\1/p' "$file")"
    excerpt="$(sed -n 's/^<!-- excerpt: \(.*\) -->$/\1/p' "$file")"
    image="$(sed -n 's/^<!-- image: \(.*\) -->$/\1/p' "$file")"
    date="$(sed -n 's/^<!-- date: \(.*\) -->$/\1/p' "$file")"
    content="$(grep -v '^<!-- \(title\|excerpt\|image\|date\): ' "$file")"
    date_args=()
    [ -n "$date" ] && date_args=(--post_date="$date")
    id=$($WP post create --user="$AUTHOR_ID" --post_type=post --post_status=publish --post_name="$slug" \
      --post_title="$title" --post_excerpt="$excerpt" --post_content="$content" "${date_args[@]}" --porcelain)
    $WP post term set "$id" zb_language "$lang" --by=slug >/dev/null
    if [ -n "$image" ]; then
      media=$($WP media import "$SEED_DIR/images/$image" --post_id="$id" --featured_image --porcelain)
      $WP post meta update "$media" _wp_attachment_image_alt "$title" >/dev/null
    fi
    echo "    $slug ($lang) created"
  done
fi

if [ "$(id -u)" = "0" ]; then
  chown -R www-data:www-data "$WP_PATH/wp-content/uploads" 2>/dev/null || true
fi

echo
echo "Done. Site: $URL   Admin: $URL/wp-admin/  (user: ${WORDPRESS_ADMIN_USER:-admin})"
