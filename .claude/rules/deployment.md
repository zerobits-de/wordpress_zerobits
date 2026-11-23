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
3. **build**: stamps a build version, builds `zb-theme.zip` and checks that it contains `vendor/`
   and both language files.
4. **deploy** (pushes to `main` only): installs the package on the production server.

Since all texts are in the theme, **a content change is a code change**: commit, pull request,
merge, and CI deploys it.

## Deployment (every merge to `main`)

Merging a pull request into `main` deploys, as soon as `validate`, `docker` and `build` have
passed. Nothing is deployed from pull requests, other branches or forks.

1. `build` stamps the package with `<base version>-<run number>.<commit>` (for example
   `1.0.0-42.d0a9fc7`; the base version is the one in `theme/style.css`). The version is part of
   every asset URL, so browsers load the new CSS and JS instead of their cached copies.
2. `deploy` sends the ZIP over SSH to the deploy user on the server. The SSH key is bound to
   [`bin/server-deploy.sh`](../../bin/server-deploy.sh) there and cannot run anything else.
3. The script unpacks the package as a new release, switches the site to it, reloads the web
   server and smoke-tests the live site. If a route fails, it switches back to the previous
   release and the job fails. The last five releases are kept.

**Secrets** (GitHub › Settings › Environments › `production`, limited to the branch `main`):
`DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS` (the server's SSH host key, as
printed by `ssh-keyscan`). This repository is public: the server address and the key exist only
there, never in a file.

**Rolling back**: revert the pull request; the merge of the revert deploys the previous state.
On the server, as the deploy user, `ln -sfn releases/<version> current.new && mv -T current.new
current` followed by a reload of the web server does the same within seconds.

### Server layout

In the home directory of the deploy user:

| Path | Content | Owner |
|---|---|---|
| `www/` | WordPress (document root); `wp-content/themes/zb-theme` is a symlink to `current` | web server user |
| `releases/<version>/` | one unpacked theme per deployment | deploy user |
| `current` | symlink to the active release | deploy user |

WordPress itself belongs to the web server user, so it keeps updating itself (core, translations);
the deploy user can only replace the theme. It may run exactly one command as root, the reload of
the web server (`sudoers`), which the script needs because PHP caches resolved paths.

The script is installed on the server as a root-owned copy, so a deployment cannot change it.
After changing `bin/server-deploy.sh`, install it again as root:

```bash
install -o root -g root -m 755 server-deploy.sh /usr/local/bin/zb-deploy-theme
```

The key of the deploy user is restricted in `~/.ssh/authorized_keys`:

```
restrict,command="/usr/local/bin/zb-deploy-theme" ssh-ed25519 AAAA… github-actions-deploy
```

## Releases

Optional, for a named version with a downloadable package: go to **Releases › Draft a new release**
in GitHub, enter a tag such as `1.0.1` (no `v` prefix), and **Publish**.
`.github/workflows/release.yml` then stamps the version into `style.css` and `Theme::VERSION`,
validates, builds and attaches `zb-theme.zip` to the release. A release does not deploy; to raise
the base version of the deployed builds, change it with `make set-version VERSION=1.0.1` in a
pull request.

## First installation of a server

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

Updates are deployed by CI (see above). By hand, a package can be sent the same way with a key
that is allowed to run the script: `ssh <deploy user>@<server> < build/zb-theme.zip`.
New language slugs or languages take effect without a manual permalink flush, because
`Router::add_rules()` flushes when the rules change. Never deploy the database or uploads from CI.
