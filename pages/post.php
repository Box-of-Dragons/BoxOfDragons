<?php
/* pages/post.php — single post page.
 *
 * Expects $_GET['slug'] (set by the router).
 */

$slug = $_GET['slug'] ?? '';
$post = get_post_by_slug($slug);

if (!$post) {
    http_response_code(404);
    render_page(
        'Not Found',
        'The post you were looking for could not be found.',
        '/posts/' . $slug,
        '<main class="page-main"><div class="shell"><div class="container"><div class="panel panel--padded"><h2>404 — Post Not Found</h2><p>The post you were looking for could not be found.</p><p><a class="button" href="/posts">Back to Projects</a></p></div></div></div></main>',
        'Not Found'
    );
    exit;
}

// Combine featured image + gallery for the lightbox gallery
$allImages = [];
if ($post['featuredImage']) {
    $allImages[] = $post['featuredImage'];
}
foreach ($post['galleryImages'] as $img) {
    $allImages[] = $img;
}

// Filter out year-only categories from the displayed categories
$displayCategories = array_filter($post['categories'], fn($c) => !preg_match('/^\d{4}$/', $c['title']));

ob_start();
?>

<main class="page-main">
    <div class="shell page-layout">
        <div class="container">
            <div class="panel panel--image-top">
                <?php if ($post['featuredImage']): ?>
                    <img class="thumb" src="<?= e($post['featuredImage']['url']) ?>" alt="<?= e($post['featuredImage']['alt'] ?: $post['title']) ?>">
                <?php endif; ?>
                <div class="panel-body">
                    <div class="container-sections">
                        <div class="container-section">
                            <h1><?= e($post['title']) ?></h1>
                            <p class="caption"><?= e(format_date($post['postDate'])) ?></p>
                            <?php if (!empty($displayCategories)): ?>
                                <div class="subtitle">
                                    <?php foreach ($displayCategories as $i => $cat): ?>
                                        <a href="/posts?category[]=<?= e($cat['slug']) ?>"><?= e($cat['title']) ?></a><?= $i < count($displayCategories) - 1 ? ', ' : '' ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($post['projectTypes'])): ?>
                                <div class="panel-chips">
                                    <?php foreach ($post['projectTypes'] as $pt): ?>
                                        <a class="chip color-pair-<?= e($pt['colourPair']) ?>" href="/posts?projectType[]=<?= e($pt['slug']) ?>"><?= e($pt['title']) ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="container-section">
                            <?php if ($post['body']): ?>
                                <div class="body"><?= nl2br(e($post['body'])) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($allImages)): ?>
                                <div class="gallery" aria-label="Post image gallery">
                                    <?php foreach ($allImages as $img): ?>
                                        <img class="gallery-image" src="<?= e($img['url']) ?>" alt="<?= e($img['alt'] ?: $post['title']) ?>" data-full="<?= e($img['url']) ?>">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <a class="button" href="/posts">Back to Completed Projects</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <aside class="sidebar sidebar--sticky">
            <div class="container-sections">
                <?php if (!empty($displayCategories)): ?>
                <section class="container-section--headed">
                    <div class="container-section-header">Categories</div>
                    <div class="container-section-body">
                        <ul class="list">
                            <?php foreach ($displayCategories as $cat): ?>
                                <li><a href="/posts?category[]=<?= e($cat['slug']) ?>"><?= e($cat['title']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($post['projectTypes'])): ?>
                <section class="container-section--headed">
                    <div class="container-section-header">Project Types</div>
                    <div class="container-section-body">
                        <ul class="list">
                            <?php foreach ($post['projectTypes'] as $pt): ?>
                                <li><a href="/posts?projectType[]=<?= e($pt['slug']) ?>"><?= e($pt['title']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </section>
                <?php endif; ?>

                <?php if ($post['designSource']): ?>
                <section class="container-section--headed">
                    <div class="container-section-header">Design Source</div>
                    <div class="container-section-body">
                        <div class="body"><a href="/posts"><?= e($post['designSource']['title']) ?></a></div>
                    </div>
                </section>
                <?php endif; ?>

                <?php if (!empty($post['resourceLinks'])): ?>
                <section class="container-section--headed">
                    <div class="container-section-header">Links</div>
                    <div class="container-section-body">
                        <ul class="list">
                            <?php foreach ($post['resourceLinks'] as $link): ?>
                                <li>
                                    <h6 class="link-label"><?= e($link['label'] ?: 'Link') ?></h6>
                                    <a class="link-url" href="<?= e($link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($link['url']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</main>

<?php
$content = ob_get_clean();
render_page($post['title'], $post['body'] ?: 'A completed project.', '/posts/' . $post['slug'], $content, $post['title']);
