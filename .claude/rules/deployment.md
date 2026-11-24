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
   languages and the 301 redirects (including the addresses
   of the former Elementor site).
   `seed/` is not in the repository, so the job runs without blog articles: the article pages,
   the carousel and the article redirects are not covered.
3. **build**: builds `zb-theme.zip` and checks that it contains `vendor/` and both language files.

Merging into `main` runs these checks again and does not deploy. Since all texts are in the theme,
**a content change is a code change**: commit, pull request, merge, release.

## Releases and deployment

A change goes live with a release. Go to **Releases › Draft a new release** in GitHub, enter a new
tag on `main` in the scheme `x.x.x`, such as `1.0.1` (three numbers, no `v` prefix, no suffix), and
**Publish**. The tag starts `.github/workflows/release.yml`:

1. `release` checks that the tag is a plain version and points to a commit on `main`, stamps the
   version into `style.css` and `Theme::VERSION` (the tag is the source of truth, there is no bump
   commit), validates, builds and attaches `zb-theme.zip` to the release. The version is part of
   every asset URL, so browsers load the new CSS and JS instead of their cached copies.
2. `deploy` sends that ZIP over SSH to the deploy user on the server. The SSH key is bound to
   [`bin/server-deploy.sh`](../../bin/server-deploy.sh) there and cannot run anything else.
3. The script unpacks the package as a new release, switches the site to it, reloads the web
   server and smoke-tests the live site. If a route fails, it switches back to the previous
   release and the job fails. The last five releases are kept.

Other tags (`v1.0.1`, `1.0.1-rc1`) do not start the workflow, and nothing is deployed from pull
requests, branches or forks. A version is released once: to ship a fix, publish the next version
instead of moving a tag.

**Secrets** (GitHub › Settings › Environments › `production`, limited to tags matching
`[0-9]*.[0-9]*.[0-9]*`): `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS` (the
server's SSH host key, as printed by `ssh-keyscan`). This repository is public: the server address
and the key exist only there, never in a file.

**Rolling back**: in **Actions › Release**, open the run of the previous version and choose
**Re-run all jobs**; it builds and deploys that version again. On the server, as the deploy user,
`ln -sfn releases/<version> current.new && mv -T current.new current` followed by a reload of the
web server does the same within seconds. Then fix the cause and publish a new version.

### Server layout

In the home directory of the deploy user:

| Path | Content | Owner |
|---|---|---|
| `www/` | WordPress (document root); `wp-content/themes/zb-theme` is a symlink to `current` | web server user |
| `releases/<version>/` | one unpacked theme per release | deploy user |
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

Updates are deployed by publishing a release (see above). By hand, a package can be sent the same
way with a key that is allowed to run the script:
`ssh <deploy user>@<server> < build/zb-theme.zip`.
New language slugs or languages take effect without a manual permalink flush, because
`Router::add_rules()` flushes when the rules change. Never deploy the database or uploads from CI.
