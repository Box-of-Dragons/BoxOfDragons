<?php
/* lib/render.php — shared page shell for Box of Dragons.
 *
 * Renders the global bar, site header, page subheader, and footer.
 * The global bar uses the shared global-bar.js from StructuredChaos.
 * The site header is rendered server-side (database-driven nav) — this
 * site does NOT use site-header.js, per the family UI docs.
 */

/** HTML-escape a string. */
function e(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/** Detect if running in local dev (DDEV or localhost). */
function is_local_dev(): bool {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return $host === 'localhost:8888'
        || str_ends_with($host, '.ddev.site')
        || str_starts_with($host, 'localhost:')
        || str_starts_with($host, '127.0.0.1:');
}

/** Get the base URL for shared assets (CSS/JS) from the root Structured Chaos site. */
function shared_assets_base(): string {
    return is_local_dev() ? 'http://localhost:4000' : 'https://misssponto.me.uk';
}

/** Render the global bar (shared JS from StructuredChaos). */
function render_global_bar(): string {
    $base = shared_assets_base();
    return '<div id="global-bar" data-active="box-of-dragons"></div>' . "\n"
        . '<script src="' . e($base) . '/js/global-bar.js" defer></script>' . "\n";
}

/** Render the site header with brand, nav, and project links. */
function render_site_header(string $currentPath = '/'): string {
    $siteName = getenv('SITE_NAME') ?: 'Box of Dragons';

    // Simple nav — can be moved to DB later
    $nav = [
        ['label' => 'Projects', 'url' => '/'],
        ['label' => 'Changelog', 'url' => '/changelog.html'],
    ];

    $navHtml = '';
    foreach ($nav as $item) {
        $url = $item['url'];
        // "/" is the projects archive; "/posts" is an alias for the same page
        if ($url === '/') {
            $isActive = $currentPath === '/' || $currentPath === '/posts';
        } else {
            $isActive = $currentPath === $url || str_starts_with($currentPath . '/', $url . '/');
        }
        $activeClass = $isActive ? ' active' : '';
        $navHtml .= '<div class="main-nav-item">'
            . '<a class="main-nav-link' . $activeClass . '" href="' . e($url) . '">' . e($item['label']) . '</a>'
            . '</div>';
    }

    return '<header class="site-header">'
        . '<div class="shell header-row">'
        . '<h1 class="brand">' . e($siteName) . '</h1>'
        . '<nav class="main-nav" aria-label="Main navigation">' . $navHtml . '</nav>'
        . '<div class="header-project-links" aria-label="Project links">'
        . '<a class="project-link project-link--github" href="https://github.com/Box-of-Dragons/BoxOfDragons" target="_blank" rel="noopener noreferrer" aria-label="Open the project on GitHub">'
        . github_icon_svg() . '<span>GitHub</span></a>'
        . '<a class="project-link project-link--gitlab" href="https://gitlab.com/structured-chaos/boxofdragons" target="_blank" rel="noopener noreferrer" aria-label="Open the project on GitLab">'
        . gitlab_icon_svg() . '<span>GitLab</span></a>'
        . '</div>'
        . '</div>'
        . '</header>';
}

/** Render the page subheader with a title. */
function render_page_subheader(string $title): string {
    return '<section class="page-subheader">'
        . '<div class="shell">'
        . '<div class="page-subheader-inner">'
        . '<h1>' . e($title) . '</h1>'
        . '</div>'
        . '</div>'
        . '</section>';
}

/** Render the site footer. */
function render_site_footer(): string {
    return '<footer class="site-footer">'
        . '<div class="shell footer-row">'
        . '<div>Box of Dragons</div>'
        . '</div>'
        . '</footer>';
}

/** GitHub icon SVG. */
function github_icon_svg(): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="M12 2C6.48 2 2 6.58 2 12.25c0 4.53 2.86 8.37 6.84 9.73.5.09.66-.22.66-.49v-1.73c-2.78.62-3.37-1.16-3.37-1.16-.46-1.2-1.12-1.52-1.12-1.52-.91-.63.07-.62.07-.62 1.01.07 1.54 1.07 1.54 1.07.9 1.58 2.35 1.12 2.92.86.09-.66.35-1.12.64-1.38-2.22-.26-4.56-1.14-4.56-5.07 0-1.12.39-2.04 1.03-2.76-.1-.26-.45-1.31.1-2.73 0 0 .84-.27 2.75 1.05a9.12 9.12 0 0 1 5 0C16.78 6.07 17.62 6.34 17.62 6.34c.55 1.42.2 2.47.1 2.73.64.72 1.03 1.64 1.03 2.76 0 3.94-2.35 4.81-4.58 5.06.36.32.69.95.69 1.92v2.84c0 .27.16.59.67.49A10.27 10.27 0 0 0 22 12.25C22 6.58 17.52 2 12 2z"/>'
        . '</svg>';
}

/** GitLab icon SVG. */
function gitlab_icon_svg(): string {
    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="M12 21.5 4.1 15.8c-.3-.2-.4-.6-.3-1l1.9-5.8h12.6l1.9 5.8c.1.4 0 .8-.3 1L12 21.5z"/>'
        . '<path d="m12 21.5 2.8-8.5H9.2L12 21.5z"/>'
        . '<path d="M12 21.5 4.1 15.8l7.9-2.8 0 8.5z"/>'
        . '<path d="M12 21.5 19.9 15.8l-7.9-2.8 0 8.5z"/>'
        . '<path d="m9.2 13 2.8-8.7L14.8 13H9.2z"/>'
        . '<path d="M4.4 14.8 6.2 9l3 4.2-4.8 1.6z"/>'
        . '<path d="M19.6 14.8 17.8 9l-3 4.2 4.8 1.6z"/>'
        . '</svg>';
}

/** Render the full page shell around content. */
function render_page(string $title, string $description, string $currentPath, string $content, string $subheaderTitle = ''): void {
    $siteName = getenv('SITE_NAME') ?: 'Box of Dragons';
    $fullTitle = $title ? $title . ' - ' . $siteName : $siteName;
    ?>
<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($fullTitle) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Open+Sans:wght@400;600;700&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(shared_assets_base()) ?>/css/shared.css">
    <link rel="stylesheet" href="/css/site.css">
</head>
<body>
<?= render_global_bar() ?>
<?= render_site_header($currentPath) ?>
<?php if ($subheaderTitle): ?>
<?= render_page_subheader($subheaderTitle) ?>
<?php endif; ?>
<?= $content ?>
<?= render_site_footer() ?>
<script>
(function () {
    document.addEventListener('click', function (e) {
        var img = e.target.closest('img.gallery-image[data-full]');
        if (!img) return;
        e.preventDefault();
        var overlay = document.createElement('div');
        overlay.className = 'lightbox';
        var full = document.createElement('img');
        full.src = img.dataset.full;
        full.alt = img.alt;
        overlay.appendChild(full);
        overlay.addEventListener('click', function () { overlay.remove(); });
        document.addEventListener('keydown', function esc (ev) {
            if (ev.key === 'Escape') { overlay.remove(); document.removeEventListener('keydown', esc); }
        });
        document.body.appendChild(overlay);
    });
})();
</script>
</body>
</html>
    <?php
}
