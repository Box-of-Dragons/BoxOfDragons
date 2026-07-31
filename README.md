# Box of Dragons

A personal portfolio site covering the things I actually make — crocheted garments and accessories, sewn pieces, parametric 3D models, and the occasional software tool that exists because I needed it to.

The site is a stripped-down PHP application that queries the existing Craft CMS database tables directly via PDO. Building the site *is* part of the portfolio.

## What's in the archive

### Crochet & fibre

Garments, accessories, and household items worked up from published patterns and my own drafts. Entries cover yarn choices, construction notes, and any modifications made along the way. The archive is filterable by project type, category, tag, and year — handy when I want to look back at everything I made in a particular season or with a particular technique.

### Sewing & textiles

Sewn pieces from fashion fabric and quilting cotton alike. Some follow commercial patterns closely; others are hacked together from multiple sources or drafted from scratch. Process notes live on each post so the decisions are recorded somewhere other than my head.

### Parametric modelling

3D models built in Fusion 360, mostly functional objects designed to solve a specific problem. Parametric modelling means the dimensions are driven by variables rather than fixed geometry, so a bracket or an organiser can be resized cleanly without rebuilding from scratch. Project entries usually include the design rationale and any iteration the model went through before it was printed or fabricated.

### Tools & software

Software I have built because I needed it. Development on these tools is documented alongside the other project work rather than in a separate engineering blog.

## How the site is built

The front end is plain PHP with PDO queries against the existing MySQL database (originally created by Craft CMS). A single front controller (`web/index.php`) routes requests to page files in `pages/`, which use library functions in `lib/` for database access and rendering. Archive filtering is handled server-side via PDO query parameters.

Most of the development has been done with AI coding tools in the loop. The agent notes that shape how those sessions run are in `AGENTS.md`. The site is hosted on a VPS and deploys automatically when commits land on the master branch via a GitHub webhook.

## Repository layout

- `web/index.php` — front controller (URL routing)
- `lib/` — config, database, post queries, and page shell rendering
- `pages/` — page-level rendering (posts archive, single post)
- `web/css/site.css` — BoD-specific CSS overrides
- `web/uploads/` — uploaded asset files (post images, thumbnails)
- `scripts/` — build info generator
- `db/trimmed/` — SQL files for the trimmed database migration
- `AGENTS.md` — working rules and conventions for agent-assisted development sessions
- `README.md` — project overview
