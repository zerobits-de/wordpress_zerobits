# Architecture

## Request flow

```
Browser
  |
Functions\Router           rewrite rules -> query vars zb_route, zb_lang, zb_page
  |                        pre_get_posts (blog filtered by language), 301 redirects
  |                        (canonical URLs and the addresses of the former site)
  |                        template_include -> always index.php
index.php -> Router::controller()
  |   Routing\Request::current(): {route, lang, page, paged}
Controller (src/Controllers)   Home | Service | Project | Blog | Post | Legal | Page | NotFound
  |   context(): Timber::context() + what the template needs
Timber::render(templates, context)
  |
Twig  templates/{home,service,project,blog,single,legal,page,404}.twig extend base.twig
      views/partials  header (navigation, language switch), footer
      views/sections  hero, about, blog-teaser, services, portfolio, pricing, team, contact,
                      service-rows, tools
      views/components button, chips, dual-title, service-card, image, lang-switch, logo, navigation,
                      lines (generated background), post-card, slider, social-links
```

## Content model

| Data | Source | Class |
|---|---|---|
| Texts per language | `content/{lang}.json` | `Content\Store::strings()`, exposed to Twig as `t` |
| IDs, images, icons, contact, languages | `content/site.json` | `Content\Store::site()` |
| Homepage sections | site.json + texts | `Content\SiteContent::home()` |
| Service pages | site.json `services`, `tools`, `odoo.logo` + texts `services`, `tools`, `odoo` | `Content\SiteContent::service()` (`services()`, `tools()`, `odoo()`) |
| Project pages | site.json `portfolio`, `tools` + texts `portfolio` | `Content\SiteContent::project()` (`portfolio()`) |
| Legal pages | `content/legal/{lang}/{key}.html` | `Content\Store::legal_html()` |
| Blog articles | WordPress posts + taxonomy `zb_language` | `Content\BlogContent`, `Functions\BlogLanguage` |

Why JSON instead of gettext `.po/.mo`: the texts are content, not UI strings. They are long, are
edited often and must stay structurally identical across languages. JSON needs no compile step,
and CI can check it completely (`bin/check-content.php`).

Why a taxonomy for the article language: it needs no editor plugin (the block editor shows it as a
checkbox panel), it shows up as a column in the post list, and it can be queried efficiently. Editors
can only assign the terms, not create them. `BlogLanguage::normalize()` keeps exactly one.

Articles of the former site start with their own `<h1>` and a copy of the featured image.
`BlogContent::card()` takes both out of the content (the heading becomes the article headline).

## Feature classes (`src/Functions/`)

| Class | Responsibility |
|---|---|
| `ThemeSupport` | theme supports, image sizes `zb-card` and `zb-wide`, editor styles |
| `Assets` | ordered CSS files, deferred `main.js`, font preload, favicon, chat settings |
| `BlogLanguage` | `zb_language` taxonomy, default term, one language per article |
| `Router` | rewrite rules (flushed automatically when they change), main query, redirects, controller choice, post permalinks |
| `Context` | `t`, `brand`, `navigation`, `languages` (switch), `legal_links`, `request` |
| `TwigExtensions` | `asset()`, `icon()`, `fill` filter for `{n}` placeholders |
| `Seo` | `<html lang>`, titles, description, canonical, hreflang, Open Graph, Organization JSON-LD |
| `Security` | removes generator and emoji script, disables XML-RPC, generic login errors |

## Front end

Plain CSS (`assets/css/`, tokens in `tokens.css`) and a single small `main.js`: navigation toggle,
header shadow, sliders and the article carousel (scroll-snap tracks that also work without
JavaScript), the map (loaded from Google only after a click), keeping the scroll
position when switching language, remembering the language chosen in the switch, and the chat widget. Fonts are self-hosted and there are no CDN
requests (`bin/validate-theme.sh` checks this). Breakpoints: 24em, 40em, 48em, 64em. Icons are SVG
files in `assets/images/icons/`, inlined with `icon('name')`.

## Language of the visitor

Every URL shows the language it names. The only exception is the homepage without a prefix (`/`):
`Router::redirect_to_visitor_language()` answers with a 302 to `/de/` when the visitor prefers a
language other than the default one. The preference is

1. the language last chosen in the language switch (cookie `zb_lang`, one year, set by `main.js`
   on a click and never otherwise), or
2. the best supported language of the browser's `Accept-Language` header
   (`I18n\Languages::preferred()`).

Requests without the header (crawlers), addresses with a query string and links from within the
site (same-host `Referer`) get the default language. The language switch carries `no-prefetch`:
WordPress' speculative loading would otherwise fetch `/` before the cookie is stored.
The response carries `Vary: Accept-Language, Cookie, Referer`; a page cache in front of WordPress has to
respect it or leave `/` uncached.
