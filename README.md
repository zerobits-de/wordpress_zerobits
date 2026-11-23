# Zerobits – WordPress theme

The source of [zerobits.de](https://www.zerobits.de/): a custom WordPress theme built with
**Timber** and **Twig**, in English and German. Site texts are versioned in `theme/content/`;
only the blog articles live in WordPress. No page builder, no plugins.

> **This is a public repository.** Do not commit secrets, credentials or internal information.

## Quick start

Requirements: Docker (Compose v2), GNU Make.

```bash
cp .env.example .env
make up          # build + start WordPress and MySQL, composer install
make install     # install WordPress, activate the theme, import the blog articles
```

The site runs at <http://localhost:8093> (German at `/de/`), the admin at `/wp-admin/`
(`admin` / `admin`).

## Development

```bash
make lint     # composer validate, PHP syntax, PHPCS, Twig syntax, content/translation check
make test     # PHPStan, theme structure, docker compose config
make build    # build/zb-theme.zip
```
