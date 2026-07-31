# AGENT Notes

This repository is a Craft CMS site. The root agent file should describe the intended live setup, where behavior is implemented, and what the front end is supposed to do. Recovery history and one-off import scripts belong in a separate recovery document.

## Implementation Preference

Prefer modeling content and behavior in Craft CMS itself when that is a viable option.

- prefer Craft fields, entry types, sections, category/tag groups, globals, volumes, and project config over hard-coded structure in Twig
- treat Twig as the presentation layer first, not the primary source of content modeling
- only hard-code logic in Twig when the behavior is clearly presentation-specific or there is no sensible Craft-native model for it
- when choosing between a Craft config change and a Twig-only workaround, prefer the Craft change unless it would create disproportionate complexity or risk
- do not introduce new bespoke styles for one-off content blocks when the current default site styles already cover the need
- prefer the existing semantic element styles and shared CSS classes over new selectors, inline styles, or special-case page styling
- if a page just needs normal headings, captions, lists, or body copy, render the plain HTML and let the current stylesheet handle it

## CSS Selector Simplicity

Prefer generic element selectors for common semantic elements. Class-qualified selectors like `.panel h3`, `.body p`, or `.card h3` should be avoided unless there is a specific reason to scope the style.

- prefer `h3` over `.panel h3`, `.title h3`, `.table h3`, `.body h3`
- prefer `p` over `.body p`, `.panel-content p`, `.card-excerpt p`
- prefer `a` over `.list a`, `.subtitle a` when the link is already in a generic context
- prefer `ul`/`ol` over `.bullet-list`, `.number-list` — generic lists are styled by default with en-dash bullets and custom counters
- use shared CSS classes (e.g. `.subtitle`, `.caption`, `.body`, `.card-heading`, `.card-excerpt`) when a component needs distinct styling, rather than scoping element selectors under a parent class

The test at [CraftCms.Tests/unit/css-selector-simplicity.test.js](</E:/Coding Projects/craftcms/CraftCms.Tests/unit/css-selector-simplicity.test.js>) reports all class-qualified selectors for typical elements so the team can decide which to keep, replace with a generic selector, or replace with a shared class.

For the full CSS component reference (cards, panels, images, lists), see [docs/css-guidelines.md](</E:/Coding Projects/craftcms/docs/css-guidelines.md>).

## App-Specific Agent Files

Keep root guidance DRY and repository-wide.

## Repository Structure

Primary ownership in this repository:

- [config/project/](</E:/Coding Projects/craftcms/config/project/>) - Craft project config for sections, entry types, fields, globals, sites, and volumes
- [templates/](</E:/Coding Projects/craftcms/templates/>) - Twig templates for page rendering
- [web/](</E:/Coding Projects/craftcms/web/>) - public assets
- [scripts/](</E:/Coding Projects/craftcms/scripts/>) - maintenance, recovery, and import scripts; not part of the normal request path
  - `GenerateBuildInfo.php` - cross-platform build info generator. Reads git tags and conventional commit messages to derive a version. Supports `--format=twiginfo` (outputs a small Twig partial exposing build variables, consumed server-side by the footer), `--format=twig` (outputs changelog template), `--format=js` (outputs `window.BUILD_INFO` object), and `--format=csharp` (outputs C# `BuildInfo` class, default). Run via `composer build-info` or `composer build-changelog` from the repo root. Also runs automatically after `composer install` via `post-install-cmd`.
  - `GenerateBuildInfo.ps1` - original PowerShell version (Windows-only). Kept for reference; the PHP version is the canonical one used by composer scripts.
- [web/webhook.php](</E:/Coding Projects/craftcms/web/webhook.php>) - GitHub webhook listener for VPS auto-deploy. GitHub sends a push event, the VPS verifies the signature and runs `git pull` + `composer install`. Requires `GITHUB_WEBHOOK_SECRET` in `.env`. See deploy section below.
- [README.md](</E:/Coding Projects/craftcms/README.md>) - project bootstrap notes

## Template Layout

All standard pages extend the base layout (`templates/_layouts/base.twig`) for a shared shell with site header/footer, page subheader, and content blocks.

For the full page type catalog, template block reference, content model, front-end behavior contracts, file ownership, and browser-level verification checks, see [docs/site-structure.md](</E:/Coding Projects/craftcms/docs/site-structure.md>).

## Local CLI Runtime

This repo is run through DDEV in the local dev environment.

- use `ddev exec php ...` for Craft CLI commands when running them from the host shell
- do not assume a host `php.exe` is installed or on `PATH`
- prefer `ddev craft ...` when the command is supported directly by DDEV
- for one-off maintenance commands, use `ddev exec php craft ...` so the command runs inside the project container

## Target Craft Setup

The site should be driven by Craft content and project config, with templates consuming that content cleanly.

Key expectations:

- section and field structure should come from [config/project/](</E:/Coding Projects/craftcms/config/project/>), not ad hoc template assumptions
- templates should read stable field handles and relations that exist in the live field layout
- archive and detail pages should work from canonical Craft entries, not drafts or revisions
- asset volumes and upload locations should resolve to real public file paths under [web/uploads/](</E:/Coding Projects/craftcms/web/uploads/>)
- taxonomy and archive filtering should work from real Craft relations and entry dates

## Recovery and Script History

Old import helpers, recovery scripts, and drift-repair notes live in [docs/content-recovery.md](</E:/Coding Projects/craftcms/docs/content-recovery.md>).

Use that document only when content has been lost, relations have drifted, field layouts no longer match project config, or the database needs to be rebuilt from recovery data.

## Git Conventions

This project uses Conventional Commits to drive automatic versioning and changelog generation.

For the full commit message format, version bump rules, and tagging guidance, see the canonical [git-rules.md](../StructuredChaos/docs/git-rules.md) in the StructuredChaos umbrella repo. That file is shared across all Structured Chaos family repos.

### CraftCms-specific scopes

Common scopes used in this project:

- **css** — site stylesheet and styling
- **site** — site structure, templates, or layout
- **agents** — AGENTS.md or agent documentation
- **readme** — README documentation

This repo uses `ui` (not `style`) for the no-logic-change styling commit type. Scopes are not enforced — use whatever best describes the area of change.

## VPS Deploy via GitHub Webhook

The VPS auto-deploys when GitHub receives a push to `master`.

`web/webhook.php` is a PHP webhook listener that:

1. Verifies the GitHub HMAC-SHA256 signature using `GITHUB_WEBHOOK_SECRET` from `.env`
2. Checks that the push is to `refs/heads/master`
3. Runs `git fetch origin master` + `git reset --hard origin/master`

nginx serves the `web/` directory directly as the docroot via PHP-FPM. No
build step, no Composer install, no app process to reload.

### Manual deploy (fallback)

SSH into the VPS and run:

```bash
cd /var/www/boxofdragons
git pull origin master
```
