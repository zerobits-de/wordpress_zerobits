---
paths:
  - ".github/**"
  - "bin/**"
  - "docker/**"
  - "docker-compose.yml"
  - "Makefile"
---

# Building and deploying

## CI (every push and pull request)

`.github/workflows/ci.yml` runs these jobs:

1. **validate**: content and translation check (`bin/check-content.php`), composer validate,
   PHP syntax, PHPCS (WordPress), PHPStan level 6, Twig syntax, theme structure.
2. **docker**: starts the stack, runs `bin/wp-install.sh` and smoke-tests every route in both
   languages, the language separation of the blogs and the 301 redirects (including the addresses
   of the former Elementor site).
   `seed/` is not in the repository, so the job imports the two fixture articles in
   `.github/fixtures/posts/` instead; their slugs are the ones the smoke test asks for.
3. **build**: builds `zb-theme.zip` and checks that it contains `vendor/` and both language files.

Since all texts are in the theme, **a content change is a code change**: commit, CI, release.

## Releases

Go to **Releases › Draft a new release** in GitHub, enter a tag such as `1.0.1` (no `v` prefix), and
**Publish**. `.github/workflows/release.yml` then stamps the version into `style.css` and
`Theme::VERSION`, validates, builds and attaches `zb-theme.zip` to the release.

## First deployment

The server needs WordPress, WP-CLI, PHP 8.2+ and SSH access. Use a **fresh WordPress installation**
(new files, new database) rather than the former one: the theme needs none of its plugins
(Elementor, Royal Addons, Smart Slider, TranslatePress, cookie banner), and a clean start leaves
nothing of the old installation behind.

```bash
make build
scp build/zb-theme.zip bin/wp-install.sh user@server:/tmp/
scp -r seed user@server:/tmp/zb-seed
# on the server, in the WordPress root
sudo -u www-data wp theme install /tmp/zb-theme.zip --force --activate
sudo -u www-data env WP_PATH="$PWD" SEED_DIR=/tmp/zb-seed WORDPRESS_SITE_TITLE="Zerobits" bash /tmp/wp-install.sh
```

The script imports the eight articles in `seed/posts/` (the articles of the former site, EN + DE)
with their original dates and featured images. Use `SEED_POSTS=0` to skip them. The script is
idempotent: existing slugs are left alone, apart from receiving their language if they have none.
It sets permalinks to `/%postname%/`, creates the language terms and flushes the rewrite rules.

If the theme is activated on the **existing database** instead: run the same script (it tags the
existing articles with their language), deactivate and delete the plugins listed above, and delete
the Elementor pages that are no longer used. Leftover pages cannot shadow the new URLs, because the
theme's rewrite rules come first.

## Updates

Install the new release ZIP (`wp theme install <zip> --force`). New language slugs or languages
take effect without a manual permalink flush, because `Router::add_rules()` flushes when the
rules change. Never deploy the database or uploads from CI.
