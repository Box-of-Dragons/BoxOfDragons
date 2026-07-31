<?php
/* web/index.php — front controller for Box of Dragons (stripped).
 *
 * Simple URL routing:
 *   /            → home page
 *   /posts       → posts archive (with query params for filters)
 *   /posts/{slug} → single post
 *
 * The .htaccess sends all non-file requests here.
 */

declare(strict_types=1);

// Bootstrap
$root = dirname(__DIR__);
require $root . '/lib/config.php';
require $root . '/lib/db.php';
require $root . '/lib/posts.php';
require $root . '/lib/render.php';

load_env($root . '/.env');

// Parse the path (strip query string, leading/trailing slashes)
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = '/' . trim($path, '/');
if ($path === '') $path = '/';

// Route
if ($path === '/' || $path === '/posts') {
    require $root . '/pages/posts.php';
    exit;
}

// /posts/{slug}
if (str_starts_with($path, '/posts/')) {
    $slug = substr($path, strlen('/posts/'));
    $slug = trim($slug, '/');
    if ($slug !== '') {
        $_GET['slug'] = $slug;
        require $root . '/pages/post.php';
        exit;
    }
}

// 404
http_response_code(404);
render_page(
    'Not Found',
    'The page you were looking for could not be found.',
    $path,
    '<main class="page-main"><div class="shell"><div class="container"><div class="panel panel--padded"><h2>404 — Not Found</h2><p>The page you were looking for could not be found.</p><p><a class="button" href="/">Back to home</a></p></div></div></div></main>',
    'Not Found'
);
