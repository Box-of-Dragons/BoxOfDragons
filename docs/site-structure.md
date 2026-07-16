# Site Structure

This repository is the main Craft CMS site.

## Shared Page Shell

Every public Craft page should use the same shell:

1. `templates/_partials/site-header.twig`
2. `templates/_partials/page-subheader.twig`
3. page-specific content
4. `templates/_partials/site-footer.twig`

The only exception is the homepage, which uses a larger hero instead of the shared subheader.

If you create a new page template, it should follow that pattern unless there is a very clear reason not to.

### Base Layout

All standard pages (except the homepage) should extend the base layout template:

- Base layout: `templates/_layouts/base.twig`
- Provides shared HTML structure: DOCTYPE, head, body, site header/footer
- Template blocks available for customization:
  - `pageTitle` - page title (appends to site name)
  - `pageDescription` - meta description
  - `headExtra` - additional head content (CSS, etc.)
  - `pageSubheader` - optional page subheader via `_partials/page-subheader.twig`
  - `pageContent` - main page content
  - `scripts` - page-specific JavaScript

**Creating a new page:**

```twig
{% extends '_layouts/base.twig' %}

{% block pageTitle %}
    Your Page Title - {{ parent() }}
{% endblock %}

{% block pageDescription %}
    Your page description
{% endblock %}

{% block pageSubheader %}
    {% include '_partials/page-subheader.twig' with { pageName: 'Your Page' } %}
{% endblock %}

{% block pageContent %}
    Your content here
{% endblock %}
```

Pages that extend the base layout:
- `templates/index.twig` - homepage
- `templates/posts.twig` - posts archive
- `templates/_entries/post.twig` - single post
- `templates/category.twig` - category pages
- `templates/tag.twig` - tag pages
- `templates/style-guide.twig` - style guide

## Shared Layout Rules

- Use `web/css/site.css` for the shared layout and typography.
- Keep page width consistent by using the shared `.shell` and page layout classes.
- Avoid per-template spacing, width, and color rules unless the page is truly unique.
- Prefer semantic markup over template-specific presentation hooks.

## Current Page Types

### Homepage

- Template: `templates/index.twig`
- Purpose: portfolio landing page
- Uses:
  - shared header and footer
  - custom hero section
  - project card grid

### Posts Archive

- Template: `templates/posts.twig`
- Purpose: project archive and filter page
- Uses:
  - shared header and footer
  - shared page subheader
  - archive grid
  - shared sidebar filters

### Post Entry

- Template: `templates/_entries/post.twig`
- Purpose: full post/project detail page
- Uses:
  - shared header and footer
  - shared page subheader
  - main content column
  - shared sidebar partial

### Category and Tag Archive Pages

- Templates: `templates/category.twig`, `templates/tag.twig`
- Purpose: taxonomy archive views
- Uses:
  - shared header and footer
  - shared page subheader
  - shared archive layout

### Style Guide

- Template: `templates/style-guide.twig`
- Purpose: visual reference for generic element styles and shared CSS classes
- Uses:
  - shared header and footer
  - shared page subheader
  - two-column page layout (content + sticky sidebar)
  - panel inception pattern (cards inside a panel for each section)
  - sticky sidebar with table of contents linking to section anchors

### Markdown Page

- Template: `templates/_entries/markdown-page.twig`
- Purpose: reusable page for rendering remote Markdown content
- Uses:
  - shared header and footer
  - shared page subheader
  - the same content shell as a post detail page

### Changelog Page

- Template: `templates/_entries/changelog-page.twig`
- Purpose: reusable page for rendering a build-time changelog from git history
- Uses:
  - shared header and footer
  - shared page subheader
  - generated changelog fragment from `scripts/GenerateBuildInfo.php`

## Content Model

### Global Sets

- `siteHeader`
  - site name
  - top navigation
  - project links
- `home`
  - homepage hero content
  - homepage cards

### Sections

- `posts`
  - entry type: `post`
  - archive and detail content
- `projectsArchive`
  - archive metadata and sidebar text
- `markdownPage`
  - entry type: `markdownPage`
  - reusable remote Markdown pages
- `changeLogPage`
  - entry type: `changeLogPage`
  - generated changelog page driven by git history

### Posts

Posts are the main editorial/project content type.

- section handle: `posts`
- entry type handle: `post`
- section config: `config/project/sections/posts--7e05d885-8a22-4531-b57e-12f3e9d7f469.yaml`
- entry type config: `config/project/entryTypes/post--d7835db0-9bc7-4ee6-8ad8-acd09625daf0.yaml`

Expected `post` fields:

- `featuredImage`
- `body`
- `instructions`
- `resourceLinks`
- `postImages`
- `designSource`
- `projectTypes`
- `postCategories`
- `postTags`

The `instructions` field is a Matrix of `instructionSection` blocks. Each section has an optional heading, subheading, and text, and contains `instructionStep` blocks. Each step has an optional heading and a nested list of `instructionStepText` blocks (text line + optional image).

If these fields disappear from the live entry layout, the front end will behave as if the content is missing even when data still exists elsewhere.

### Posts Archive

The posts archive is a single filtered page.

- archive URL: `/posts`
- archive template: `templates/posts.twig`
- single post template: `templates/_entries/post.twig`

Archive chrome and editable copy come from the `projectsArchive` Single:

- section handle: `projectsArchive`
- section config: `config/project/sections/projectsArchive--6a47cd07-ec9e-460f-999f-081f64ceeb62.yaml`
- entry type config: `config/project/entryTypes/projectsArchive--fd9b9dfd-058f-4810-a86c-3f74aa2d8f4c.yaml`

Editable archive fields:

- `projectsArchiveHeading`
- `projectsArchiveMetaDescription`
- `projectsArchiveSidebarIntro`

### Taxonomy Groups

- `postCategories`
- `postTags`
- `designSources`
- `projectTypes`

Project classification is handled through Craft taxonomy relations, not separate archive templates.

- `projectTypes` drives the project-type filter and card chip
- `postCategories` drives category filters and post/category links
- `postTags` drives tag filters and post/tag links
- `postDate` drives the year filter

The intended UX is one filtered `/posts` page, not separate category or tag destination pages, even though `templates/category.twig` and `templates/tag.twig` still exist.

## Field Usage Notes

- Keep field names descriptive and stable.
- If a page needs a new field, add it in project config and document the purpose here.
- Prefer reusing an existing field or taxonomy group before adding a new one.
- Do not hide content structure inside template-local variables when it belongs in Craft config.

## Front-End Behavior

### Archive Filtering

Supported filter forms:

- `/posts?projectType[]=<slug>`
- `/posts?category[]=<slug>`
- `/posts?tag[]=<slug>`
- `/posts?year[]=<YYYY>`

Behavior contract:

- clicking category, tag, project type, or year filters should stay on `/posts`
- the archive page chrome should stay fixed while the result set changes
- filter groups combine with `AND` logic across groups
- selections within a single group combine with `OR` logic
- years are derived from `postDate`, not from category terms
- visible category lists should not show year-like terms such as `2023`

### Archive Cards

Expected card behavior in `templates/posts.twig`:

- show a `Design Source` chip when a `designSource` relation exists
- show a `Project Type` chip when a `projectTypes` relation exists
- prefer `entry.body` for excerpt text
- fall back to a resource-link label or a generic prompt when body content is empty
- render a thumbnail from `featuredImage` when available
- fall back to the first asset in the post image folder when the relation is missing at runtime

### Single Post Pages

Expected detail-page behavior in `templates/_entries/post.twig`:

- show the main featured image when available
- render the `body` content
- render the `instructions` matrix as a numbered list below the body when steps are present
- render `postImages` as a gallery below the body and instructions
- show project type, categories, tags, and year in the sidebar/details area
- emit archive links using array-style query parameters so they seed the same `/posts` filtering UI

## File Ownership

Use these files as the first place to look when behavior changes:

- `templates/posts.twig` - archive layout, filter UI, archive card rendering, year extraction
- `templates/_entries/post.twig` - single post rendering, post meta, taxonomy links, gallery output
- `config/project/entryTypes/post--d7835db0-9bc7-4ee6-8ad8-acd09625daf0.yaml` - source-of-truth field layout for posts
- `config/project/fields/featuredImage--29579835-63db-4481-b347-52f1852e0eb9.yaml` - featured image field config
- `config/project/fields/instructions--53cdc25e-21c1-4998-a258-79e3ec30f36c.yaml` - instructions matrix field config
- `config/project/entryTypes/instructionStep--e658df5a-6730-4006-8252-088fed617ae2.yaml` - instruction step block layout
- `config/project/fields/postImages--895989d9-cefc-4582-bbad-6736c0c471b8.yaml` - gallery field config
- `config/project/fields/projectTypes--c8ae7352-3ab5-47e7-b586-a001fbe07430.yaml` - project type taxonomy field
- `config/project/fields/postCategories--52d25c97-f091-4cfb-84f6-e2529d60f743.yaml` - category taxonomy field
- `config/project/fields/postTags--5cfa4a6e-5fa2-43f3-a646-d35ea81d5d63.yaml` - tag taxonomy field
- `config/project/volumes/postImages--56b64bf1-40b8-4808-a076-f845aced2527.yaml` - post image volume pathing

## Browser-Level Verification

High-signal checks for the live site:

- `/posts` should show the sidebar sections `Project Type`, `Categories`, `Tags`, and `Year`
- `/posts` should render populated cards with non-empty titles
- `/posts` should render image thumbnails even if `featuredImage` resolution is partially broken, via the per-post folder fallback
- `/posts?category[]=crochet&year[]=2023` should stay on `/posts` and combine filters
- `/posts?projectType[]=<slug>` should stay on `/posts` and filter by project type
- `/posts?category[]=accessories&category[]=crochet&year[]=2023` should stay on `/posts` and return the union of those categories inside the selected year
- `/posts?year[]=2023` should stay on `/posts` and filter by `postDate`
- `/posts?category[]=<slug>` should stay on `/posts` and filter by taxonomy relation
- `/posts?tag[]=<slug>` should stay on `/posts` and filter by taxonomy relation

If the page renders no `<img>` tags but asset files exist on disk, or taxonomy appears empty despite known content, treat that as content/config drift first rather than a template-only problem.

## Adding A New Page

When adding a page:

1. Create the Craft section or entry type first.
2. Add the fields needed in project config.
3. Add or update the Twig template.
4. Make it use the shared header, subheader, and footer.
5. Add styling to `web/css/site.css` instead of inlining it in the template.
6. Document the new page in this file.
