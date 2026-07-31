# CSS Guidelines

This project keeps styling centralized in `web/css/site.css`.

## Rules

- Put shared styles in `web/css/site.css`.
- Avoid inline styles in PHP page files.
- Use inline styles only when the value is genuinely dynamic and cannot be expressed cleanly in shared CSS.
- Prefer semantic HTML elements first, then shared utility classes, then component classes.
- Do not create one-off style blocks in page files for layout, spacing, color, or typography.

## Typography

Use the shared semantic type scale:

- `h1` through `h6`
- `.body`
- `.subtitle`
- `.caption`
- `.button`

If a page needs custom typography, add a reusable class to `site.css` instead of duplicating font rules inside a page file.

## Colors

The site palette is defined in CSS variables at `:root`.

Use the semantic color tokens:

- `--body`
- `--primary`
- `--secondary`
- `--tertiary`
- plus their `-light` and `-dark` variants

Do not hardcode new color values in page files.

## Layout

The shared layout already includes:

- site header
- page subheader
- content width (`.shell`)
- panel styles
- sidebar container styles
- footer

Before adding a new wrapper or spacing rule, check whether an existing layout class can be reused.

Surface rules:

- `.shell` is the 80% width centered wrapper used on every page
- `.container` is the shared colored surface for the page subheader, general content pages, and sidebars
- `.container--sticky` makes a sidebar container stick below the site header
- `.container-section--headed` is a bordered group box with a grey header bar (`.container-section-header`) and white content body (`.container-section-body`); use it for labelled sub-sections inside a container or as a standalone group box on any page
- `.panel` is the shared white surface for content boxes (archive grid, panel inception, homepage)
- `.panel--padded` adds 24px padding for standalone panels inside a container
- `.panel--image-top` removes padding so an image bleeds to the panel edges
- `.panel-body` is the padded content wrapper inside an image-top panel

### Panel Inception

Panels can sit inside containers to create grouped content sections. This is the pattern used on single post pages and the style guide. Consecutive `.panel` elements inside a `.container` get a 28px gap automatically.

## Markdown And Rich Text

Markdown and long-form content should use the shared `.body` styles in `site.css`.

That file already handles:

- paragraphs
- headings
- lists
- blockquotes
- code blocks
- tables
- images

If a page needs a special content treatment, add a scoped class in `site.css` and keep the page markup clean.

## Panels And Chips

Panels should use the shared panel classes and chip classes rather than per-template styling.

Use the existing pattern:

- `.panel` — base (white bg, border, radius, flex column)
- `.panel--padded` — 24px padding modifier
- `.panel--image-top` — image-top modifier (padding: 0)
- `.panel-body` — padded content wrapper inside image-top panels
- `.panel-heading` — heading wrapper inside a panel body
- `.panel-excerpt` — clamped excerpt text (3-line truncation)
- `.panel-chips` — chip row inside a panel body
- `.panel-category-chips` — category chip row inside a panel heading

### Images

- `.thumb` — 4/3 aspect ratio cover image that fills its container
- `.gallery` — responsive auto-fill grid for image galleries
- `.gallery-image` — gallery image with border, radius, and background fill

### Lists

Generic `ul` and `ol` are styled by default — no classes needed. Use `.list` only when you need a markerless list (sidebar navigation, tag clouds).

If a page needs a white content box, add `panel` to the wrapper and keep any page-specific layout class separate.

If a new chip color is needed, add a named `color-pair-*` variant in `site.css` rather than using inline background colors.

## Page Files

When editing a page file:

1. Prefer semantic HTML.
2. Reuse existing classes.
3. Add shared styles to `site.css` when the pattern may repeat elsewhere.
4. Avoid local `<style>` blocks unless you are prototyping and immediately removing them.

