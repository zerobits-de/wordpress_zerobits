# Workflow: Jira ticket → branch → PR

Tickets live in Jira (site `zerobits.atlassian.net`, project `MT`). Every
change goes through a ticket, a branch and a pull request into `main`. Since
all texts are in the theme, this includes content changes.

## Public repository

This repository is public, Jira is not. Branch names, commit messages and PRs
are visible to anyone:

- No secrets, credentials, server addresses or internal information about
  clients in code, commits or PRs.
- A PR body has to be understandable without the ticket. Reference the ticket
  by its key and keep internal details in Jira.

## Naming scheme

Tickets, commits and PRs use the Conventional Commits prefixes:

| Prefix      | Use for                                                       |
|-------------|---------------------------------------------------------------|
| `feat:`     | new functionality, new sections or pages                      |
| `fix:`      | bug fixes                                                     |
| `content:`  | texts, translations, images, legal pages (`theme/content/`)   |
| `style:`    | visual changes without new functionality (CSS, tokens)        |
| `refactor:` | code changes without behaviour change                         |
| `perf:`     | performance improvements                                      |
| `test:`     | adding or changing checks only                                |
| `docs:`     | documentation, including README, AGENTS.md and `.claude/`     |
| `chore:`    | dependencies, tooling, config, maintenance                    |
| `ci:`       | GitHub Actions workflows                                      |

Format: `<prefix> <short imperative description>`, lowercase, no trailing period,
e.g. `feat: add a pricing section to the service pages`.

- **Ticket summary**: `<prefix> <description>`. Issue type `Bug` for `fix:`,
  `Task` or `Story` otherwise. `MT` holds tickets of other projects as well, so
  name the repository (`wordpress_zerobits`) in the description.
- **Branch**: `MT-<number>-<kebab-case-slug>`, branched from an up-to-date
  `origin/main`.
- **Commits**: `<prefix> <description>`, no ticket key (the branch carries it).
  One concern per commit.
- **PR title**: the ticket summary followed by the key, e.g.
  `feat: add a pricing section to the service pages (MT-12)`.
- **PR body**: the ticket key, a short summary of the change, and how it was
  verified (`make lint`, `make test`, pages checked in the browser).

## Steps

1. **Ticket** – create it in `MT` with the naming scheme above, assign it and
   move it to *In Progress*.
2. **Branch** – `git fetch origin` and branch from `origin/main`.
3. **Implement** – run `make lint` and `make test` before committing (both need
   the stack, `make up`). For visible changes, check the page in both languages
   (<http://localhost:8093> and `/de/`).
4. **Review** – do not push or open the PR until the user has reviewed the
   local commits and said so.
5. **PR** – push the branch and open the PR against `main`; move the ticket
   to *In Review*. CI (`.github/workflows/ci.yml`) has to pass.
6. **Close** – after the merge, comment on the ticket (what changed, commit or
   PR link, how the acceptance criteria are met) and move it to *Done*.

Merging does not deploy. A change goes live with a release (see `deployment.md`).
