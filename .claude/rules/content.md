---
paths:
  - "theme/content/**"
  - "theme/assets/images/**"
---

# Editing content

All texts of the site are files in `theme/content/`. A change is a commit; CI checks it and a
release deploys it (see [deployment.md](deployment.md)). Only blog articles are edited in wp-admin.

| What | Where |
|---|---|
| Texts in English / German | `theme/content/en.json`, `theme/content/de.json` |
| Order of items, images, icons, links, contact data | `theme/content/site.json` |
| Imprint and privacy policy | `theme/content/legal/{en,de}/{imprint,privacy}.html` |
| Images | `theme/assets/images/` |
| Blog articles | WordPress (wp-admin › Posts) |

Run `make lint` after every change. It fails when a key exists in only one language, a text is
empty, an item of `site.json` has no texts, or an image or icon is missing.

## Texts

Both language files have exactly the same keys. Change a text in both files.

- Two-colour headings have two parts: `"title": { "lead": "How We Help You", "accent": "Grow" }`.
  The lead is dark, the accent blue.
- Placeholders such as `{n}`, `{total}`, `{title}` are filled in by the templates; keep them.
- Texts are plain text (no HTML). Only the legal pages are HTML files.

Top-level keys: `meta` (title and description of the homepage), `nav`, `hero`, `about`, `blog`,
`services`, `tools`, `portfolio`, `pricing`, `team`, `contact`, `odoo`, `footer`, `legal`,
`not_found`.

## Lists (services, features, technologies, projects, pricing)

The list itself is in `site.json`, the texts of each item under `{section}.items.{id}` in both
language files.

Add a project to the portfolio:

1. Put the images in `theme/assets/images/portfolio/`.
2. `site.json` › `portfolio`: add `{ "id": "my-project", "url": "https://…", "format": "landscape",
   "images": ["images/portfolio/my-project-1.webp", …], "tools": ["typescript", "vue"] }`
   (`format`: `portrait` for phone screenshots, `landscape` otherwise; `tools`: the technologies
   used, see below).
3. `en.json` and `de.json` › `portfolio.items.my-project`: `slug`, `name`, `meta_title`,
   `meta_description`, `title` (`lead`, `accent`), `text`, `image_alt`.

Every project is a row of the portfolio on the homepage and has a page of its own with the
technologies used as chips. The homepage names no specific tool or
platform: keep them out of `hero`, `about`, `services.text`, the `card` of a service and the
titles of its features.

Technologies: `site.json` › `tools` is the list of all of them (`id`, `name`, `url` of their
website), `tools.items.{id}.text` their description in both language files. A service or a project
shows the ones whose ids its `"tools": […]` lists, in that order: a service as cards with the
descriptions, a project on its page as chips (names only). To add a technology, add it to the
list and write its two descriptions; to remove one, also take its id out of every `"tools"`.

Services work the same way (`services` in `site.json`, `services.items.{id}` in the language
files). Every service is an entry of the scrolling section on the homepage (`title`, `card`, the
titles of its features, `image`) and has a page of its own (`text`, features, `meta_title`,
`meta_description`); `card_icon` is its icon on the cards "More services". Each feature has an `id`
and an `icon` in `site.json` and a `title` and `text` under `services.items.{id}.features.{feature
id}`. Icons are the SVG files in `theme/assets/images/icons/` (file name without `.svg`).

The page of a service scrolls like the services on the homepage: rows of a title, a text and the
titles of the features as a checklist, next to their illustration. The `text` and `icon` of a
feature, and of the Odoo section `work.text`, the texts of `work.items` and `why`, are kept in the
files but not shown at the moment. Below the rows, the technologies and the cards "More services"
show at most four in a row.

The page of a service can show more than its own text:

- `"blocks": [{ "id": "consulting", "image": …, "features": […] }]` adds further rows; their
  texts are under `services.items.{id}.blocks.{block id}` (`title`, `text`, `image_alt`, `features`).
- `"tools": ["temporal", "typescript"]` shows these technologies (ids of the list `tools`).
- `"odoo": true` adds the Odoo partner section as the last row (texts `odoo`: `title`, `intro`,
  the titles of `work.items`; the partner logo is its illustration). Exactly one service has it.

## Pages and their addresses

The address of a service is `/{services.slug}/{services.items.{id}.slug}/`, of a project
`/{portfolio.slug}/{portfolio.items.{id}.slug}/`, of the legal pages `legal.{key}.slug`, each per
language. Changing a slug changes the URL; no permalink flush is needed. The blog is always at
`/blog/` and `/de/blog/`. `/{services.slug}/`, `/{portfolio.slug}/` and `/{odoo.slug}/` have no
page: they redirect to the services and portfolio sections of the homepage and to the Odoo
section.

The navigation has five links to sections of the homepage (`nav.about`, `nav.portfolio` for the
services, `nav.references` for the projects, `nav.blog`, `nav.contact`).

## Contact data, social profiles, chat

`site.json` › `contact` (e-mail, phone, booking link, address, map links), and `social`. The chat
widget is configured in `chat` (`url`, `token`, `position` of a Chatwoot instance); remove `chat` to
switch it off. It is described in the privacy policy (`content/legal/*/privacy.html`). The note
beside the chat button, shown on reaching the team section, is the text `chat.hint`.

## Blog articles

Write the article in wp-admin and choose its **Language** in the sidebar (exactly one; without a
choice it counts as English). Set a featured image and an excerpt. The title is used on cards; if
the text starts with a heading 1, that heading is shown as the headline of the article page. The
article appears at `/blog/{slug}/` or `/de/blog/{slug}/`, in the blog of its language and in the
carousel on the homepage.

## Adding a language

Add the code to `languages` in `site.json`, copy `en.json` to `{code}.json` and translate it, and
add `content/legal/{code}/`. Routes, the language switch and hreflang follow automatically.
