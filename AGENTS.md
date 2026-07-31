# AGENT Notes

This repository is a stripped-down PHP site that queries the existing Craft CMS database tables directly via PDO. The root agent file describes the intended live setup, where behavior is implemented, and what the front end does.

## Implementation Preference

Prefer plain PHP and PDO queries over introducing new frameworks or abstractions.

- keep the front controller (`web/index.php`) thin — route to page files in `pages/`, not inline rendering
- put database queries in `lib/` (config, db, posts, render) — not in page files or the front controller
- use the shared CSS from the root Structured Chaos site (`css/shared.css`) before site-specific styles (`web/css/site.css`)
- prefer semantic HTML elements and shared CSS classes over new selectors, inline styles, or special-case page styling
- if a page just needs normal headings, captions, lists, or body copy, render the plain HTML and let the stylesheet handle it

## CSS Selector Simplicity

Prefer generic element selectors for common semantic elements. Class-qualified selectors like `.panel h3`, `.body p`, or `.card h3` should be avoided unless there is a specific reason to scope the style.

- prefer `h3` over `.panel h3`, `.title h3`, `.table h3`, `.body h3`
- prefer `p` over `.body p`, `.panel-content p`, `.card-excerpt p`
- prefer `a` over `.list a`, `.subtitle a` when the link is already in a generic context
- prefer `ul`/`ol` over `.bullet-list`, `.number-list` — generic lists are styled by default with en-dash bullets and custom counters
- use shared CSS classes (e.g. `.subtitle`, `.caption`, `.body`, `.card-heading`, `.card-excerpt`) when a component needs distinct styling, rather than scoping element selectors under a parent class

For the full CSS component reference (cards, panels, images, lists), see the canonical [docs/ui.md](../StructuredChaos/docs/ui.md) in the StructuredChaos umbrella repo.

## Repository Structure

Primary ownership in this repository:

- [web/index.php](./web/index.php) - plain PHP front controller with URL routing
- [lib/](./lib/) - site libraries (not part of the normal request output, but loaded by pages)
  - `config.php` - environment loading and site configuration
  - `db.php` - PDO database connection factory
  - `posts.php` - post queries (archive listing, single post, filters, asset paths)
  - `render.php` - page shell rendering (shared header, footer, subheader)
- [pages/](./pages/) - page-level rendering (included by the front controller)
  - `posts.php` - posts archive (project list with sidebar filters)
  - `post.php` - single post detail page
- [web/](./web/) - public docroot (served by nginx)
  - `index.php` - front controller entry point
  - `.htaccess` - Apache URL rewrite rules (routes non-file requests to `index.php`)
  - `css/site.css` - BoD-specific CSS overrides (loads after `css/shared.css` from the root site)
  - `webhook.php` - GitHub webhook listener for VPS auto-deploy
  - `uploads/` - uploaded asset files (post images, thumbnails)
- [scripts/](./scripts/) - build tooling (not part of the normal request path)
  - `GenerateBuildInfo.php` - cross-platform build info generator. Reads git tags and conventional commit messages to derive a version. Supports `--format=js` (outputs `window.BUILD_INFO` object), `--format=html` (outputs full changelog page), `--format=twig` (outputs changelog fragment), `--format=twiginfo` (outputs small Twig partial), and `--format=csharp` (outputs C# `BuildInfo` class, default). Run via the webhook and GitHub Actions deploy steps.
  - `GenerateBuildInfo.ps1` - original PowerShell version (Windows-only). Kept for reference; the PHP version is the canonical one.
- [db/trimmed/](./db/trimmed/) - SQL files for the trimmed database migration (schema, data, drop-unused-tables)
- [README.md](./README.md) - project overview
- [.env.example](./.env.example) - template for `.env` (contains `DB_*` and `SITE_*` vars)

## Page Shell

All pages follow the shared family shell (see [docs/ui.md](../StructuredChaos/docs/ui.md)):

1. **Global bar** — rendered by `js/global-bar.js` from the root site
2. **Site header** — rendered by `js/site-header.js` from the root site
3. **Page subheader** — bordered container holding the page title (`h1`)
4. **Page content** — panels inside a `.container`
5. **Site footer** — per-site footer

The shell is rendered by `lib/render.php` which outputs the shared HTML head, global bar, site header, page subheader, and footer. Page files (`pages/*.php`) fill in the content section.

## Local CLI Runtime

This repo is run through DDEV in the local dev environment.

- use `ddev exec php ...` for PHP CLI commands when running them from the host shell
- do not assume a host `php.exe` is installed or on `PATH`
- the DDEV project name is `boxofdragons`

## Git Conventions

This project uses Conventional Commits to drive automatic versioning and changelog generation.

For the full commit message format, version bump rules, and tagging guidance, see the canonical [git-rules.md](../StructuredChaos/docs/git-rules.md) in the StructuredChaos umbrella repo. That file is shared across all Structured Chaos family repos.

### BoxOfDragons-specific scopes

Common scopes used in this project:

- **css** — site stylesheet and styling
- **site** — site structure, pages, or layout
- **db** — database queries, schema, or migrations
- **deploy** — webhook, GitHub Actions, or deploy pipeline
- **agents** — AGENTS.md or agent documentation
- **readme** — README documentation

This repo uses `ui` (not `style`) for the no-logic-change styling commit type. Scopes are not enforced — use whatever best describes the area of change.

## VPS Deploy via GitHub Webhook

The VPS auto-deploys when GitHub receives a push to `master`.

`web/webhook.php` is a PHP webhook listener that:

1. Verifies the GitHub HMAC-SHA256 signature using `GITHUB_WEBHOOK_SECRET` from `.env`
2. Checks that the push is to `refs/heads/master`
3. Runs `git fetch origin master` + `git reset --hard origin/master`
4. Runs `php scripts/GenerateBuildInfo.php --root=. --output=web/js/buildInfo.js --format=js`
5. Runs `php scripts/GenerateBuildInfo.php --root=. --output=web/changelog.html --format=html`

nginx serves the `web/` directory directly as the docroot via PHP-FPM. No
build step, no Composer install, no app process to reload.

### Manual deploy (fallback)

SSH into the VPS and run:

```bash
cd /home/boxofdragons/htdocs/www.boxofdragons.misssponto.me.uk
git fetch origin master
git reset --hard origin/master
php scripts/GenerateBuildInfo.php --root=. --output=web/js/buildInfo.js --format=js
php scripts/GenerateBuildInfo.php --root=. --output=web/changelog.html --format=html
```
