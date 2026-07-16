# Git Rules

- This project uses [Conventional Commits](https://www.conventionalcommits.org/) to drive automatic versioning and changelog generation.
- Commit everything that is dirty unless specified otherwise.
- Use more than one commit if needed across multiple files.
- List commit messages for review before running git command:

```
git commit
```

- Do not commit before being asked to do so.

## Commit Message Format

```
<type>(<scope>): <description>

<optional body>
```

- The **type** is mandatory and determines the version bump.
- The **scope** is optional but encouraged for clarity (e.g. `css`,  `deploy`).
  - Avoid using just `cms` as this is too broad.
- The **description** should be lowercase, imperative, and concise.
- The **body** is optional but encouraged unless it causes duplication of the description.
  - Bullet points are preferred.

### Footers

Avoid adding non-functional footers such as `Generated with [Devin](https://devin.ai)` or `Co-Authored-By: Devin ...` to commit messages. These are not part of the project's conventional commit format and add noise to the changelog.

Functional footers are allowed only when they carry meaning for the project:

- `BREAKING CHANGE:` to signal a breaking change
- `Signed-off-by:` if the project requires DCO sign-off

## Commit Types and Version Impact

| Type                                  | Version bump | Changelog group                  |
|---------------------------------------|-------------|----------------------------------|
| `feat`                                | **minor** (e.g. 1.3.0 → 1.4.0) | Features                         |
| `fix`                                 | **patch** (e.g. 1.3.0 → 1.3.1) | Fixes                            |
| `docs`                                | none (revision only) | Documentation                    |
| `refactor`                            | none (revision only) | Refactors                        |
| `test`                                | none (revision only) | Tests                            |
| `chore`                               | none (revision only) | Maintenance                      |
| `ui`                                   | none (revision only) | User Interface (no logic change) |
| any + `BREAKING CHANGE` footer or `!` | **major** (e.g. 1.3.0 → 2.0.0) | Breaking changes                 |

Commits that don't match a known type (anything not `feat`, `fix`, or breaking) increment the **revision** — the fourth version number (e.g. 1.3.0.1, 1.3.0.2). The revision resets to 0 whenever a `feat`, `fix`, or breaking change is encountered.

## Breaking Changes

To signal a breaking change, either:

- Add `BREAKING CHANGE:` in the commit body footer, or
- Add `!` after the type/scope: `feat(api)!: redesign endpoint structure`

## Examples

```
feat(tag): add post tags filter to archive sidebar
fix(css): correct card image aspect ratio on mobile
docs(readme): update deployment instructions
refactor(ui): split bootstrap and UI wiring
test(fields): add post field layout coverage
chore(build): regenerate build info for v1.17.0
feat(api)!: remove deprecated v1 endpoints

BREAKING CHANGE: v1 endpoints are no longer available.
```

## Versioning Mechanics

Versioning is handled by `scripts/GenerateBuildInfo.php`, which runs automatically via `composer install` (`post-install-cmd`) and via `composer build-info`.

The generator:

1. Reads all git tags matching `vX.Y.Z` and uses the latest tag as the starting version.
2. Walks the commit log (oldest first) from the last tagged commit.
3. Bumps the version per the rules above for each commit.
4. Outputs the resolved version to `templates/_generated/build-info.twig` (Twig variables consumed server-side by the footer) and `templates/_generated/changelog.twig` (Twig changelog). The `--format=js` and `--format=csharp` outputs are still supported by the generator but no longer produced by `composer build-info`.

If no tags exist, the version starts at `v1.0.0`.

## Tagging

Tags are optional but should be created at release milestones:

```
git tag v1.4.0
git push origin v1.4.0
```

A tag pins the version at that point. All commits after the tag will increment from the tagged version.

## Scopes

Common scopes used in this project:

- **css** — site stylesheet and styling
- **site** — site structure, templates, or layout
- **agents** — AGENTS.md or agent documentation
- **readme** — README documentation

Scopes are not enforced — use whatever best describes the area of change.
