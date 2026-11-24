# Instructions for AI coding agents

## What this is

- A **custom WordPress theme** for Zerobits (`theme/`): a bilingual (EN default at `/`, DE at `/de/`)
  one-pager about AI, automation, cloud and hosting, with a page per service (the Odoo partner
  section is part of the cloud page), a page per portfolio project, an about us page and a blog. Location: Koblenz.
- **Timber is mandatory.** Twig renders presentation (`templates/`, `views/`); PHP prepares data
  (`src/`). Do not put HTML in PHP and do not put business logic in Twig.
- **All site texts live in the repo** (`theme/content/`); only blog articles live in WordPress.
  See [`.claude/rules/content.md`](.claude/rules/content.md).
- Local development runs in Docker (`make up`, `make install`) on <http://localhost:8093>.
- **The repository is public.** Everything committed, and every branch name, commit message and
  pull request, is visible to anyone.

## Rules

1. **Every text change goes into both `content/en.json` and `content/de.json`**, using the same
   keys. `make lint` (`bin/check-content.php`) must pass. Never hard-code visible text in Twig or PHP.
   Admin-only strings use `__()` with the text domain `zb-theme`.
2. Lists, order, images, icons, links and IDs go in `content/site.json`. Texts for an item go under
   `{section}.items.{id}`. Two-colour headings are `{ "lead": …, "accent": … }`
   (`components/dual-title.twig`).
3. **URLs are built only through `Routing\Urls`**. Routes are defined in `Functions\Router::rules()`
   and `Routing\Request`.
4. **No page builders, no frontend frameworks, no third-party requests** (self-hosted fonts, no CDNs,
   no embeds without consent). The map loads after a click; the chat widget (`site.json` `chat`) is
   the only script from another host.
5. **Preserve the visual system**: tokens in `assets/css/tokens.css` (blue `#0b6ea5`, dark `#292f3a`,
   soft background `#f7faff`, Roboto for text, Poppins for headings). New values go into tokens first.
6. **Responsive**: no horizontal overflow from 320 to 1920 px. Long German compounds must wrap.
   Breakpoints: 24em, 40em, 48em, 64em.
7. **Accessibility**: semantic HTML, one `h1` per page, visible focus, alt texts in both languages,
   `prefers-reduced-motion`. The hero stays static.
8. **Escape in Twig** (`|e`, `|e('html_attr')`, `|esc_url`). Timber does not autoescape. Legal HTML
   and `post.content` are output as is.
9. Do not invent legal data, client names, figures or testimonials.
10. Do not commit secrets, credentials, server addresses or internal information about clients: the
    repository is public. Run `make lint` and `make test` after significant changes.
11. Every change goes through a Jira ticket, a branch and a pull request
    ([`.claude/rules/workflow.md`](.claude/rules/workflow.md)).

## Commands

| Task | Command |
|---|---|
| Start / install | `make up`, `make install` |
| Lint (incl. translations) / tests | `make lint`, `make test` |
| WP-CLI | `make wp ARGS="post list"` |
| Release ZIP | `make build` |

## More guidance

Detailed rules live in `.claude/rules/`:

- [`workflow.md`](.claude/rules/workflow.md) – Jira ticket → branch → PR flow and the `feat:`/`fix:` naming scheme
- [`architecture.md`](.claude/rules/architecture.md) – request flow, content model, feature classes, front end, visitor language
- [`content.md`](.claude/rules/content.md) – editing texts, lists, images, pages and blog articles
- [`deployment.md`](.claude/rules/deployment.md) – CI, releases, deploying and updating the theme
