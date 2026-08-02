<?php
/* pages/posts.php — posts archive with category/project type/year filters.
 *
 * Query params:
 *   category[]   — category slugs (postCategories group)
 *   projectType[] — project type slugs (projectTypes group)
 *   year[]       — years
 */

// Gather filters
function param_array(string $key): array {
    $val = $_GET[$key] ?? [];
    if (!is_array($val)) $val = [$val];
    return array_filter(array_map('strval', $val), fn($s) => $s !== '');
}

/** Expand selected project type parents to include their child slugs. */
function expand_project_types(array $selected, array $projectTypes): array {
    $expanded = $selected;
    foreach ($projectTypes as $parent) {
        if (empty($parent['parentId']) && in_array($parent['slug'], $selected)) {
            foreach ($projectTypes as $child) {
                if (!empty($child['parentId']) && $child['parentId'] == $parent['id']) {
                    $expanded[] = $child['slug'];
                }
            }
        }
    }
    return array_values(array_unique($expanded));
}

$filters = [
    'category' => param_array('category'),
    'project_family' => param_array('projectFamily'),
    'project_type' => param_array('projectType'),
    'year' => param_array('year'),
];

// Sidebar data (needed for parent expansion)
$postCategories = get_categories_by_group_handle('postCategories');
$projectFamilies = get_categories_by_group_handle('projectFamilies');
$projectTypes = get_categories_by_group_handle('projectTypes');
$years = get_post_years();

$queryFilters = $filters;
$queryFilters['project_type'] = expand_project_types($filters['project_type'], $projectTypes);

$page = max(1, (int)($_GET['page'] ?? 1));
$result = get_posts($queryFilters, $page, 12);

$hasFilters = !empty($filters['category'])
    || !empty($filters['project_family'])
    || !empty($filters['project_type'])
    || !empty($filters['year']);

ob_start();
?>

<main class="page-main">
    <div class="shell page-layout">
        <div class="content-main">
            <?php if (empty($result['posts'])): ?>
                <div class="container">
                    <div class="panel panel--padded">
                        <h2>No projects found</h2>
                        <p>Try adjusting your filters.</p>
                        <?php if ($hasFilters): ?>
                            <p><a class="button" href="/posts">Clear filters</a></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid" aria-label="Completed project list">
                    <?php foreach ($result['posts'] as $post): ?>
                        <?php $img = $post['featuredImage']; ?>
                        <article class="panel panel--image-top">
                            <a href="/posts/<?= e($post['slug']) ?>" aria-label="View <?= e($post['title']) ?>">
                                <?php if ($img): ?>
                                    <img class="thumb" src="<?= e($img['url']) ?>" alt="<?= e($img['alt'] ?: $post['title']) ?>">
                                <?php endif; ?>
                            </a>
                            <div class="panel-body">
                                <?php if (!empty($post['projectFamily']) || !empty($post['projectTypes']) || !empty($post['designSource'])): ?>
                                    <div class="panel-chips">
                                        <?php foreach ($post['projectFamily'] as $family): ?>
                                            <a class="chip color-pair-<?= e($family['colourPair']) ?>" href="/posts?projectFamily[]=<?= e($family['slug']) ?>"><?= e($family['title']) ?></a>
                                        <?php endforeach; ?>
                                        <?php if (!empty($post['projectTypes'])): ?>
                                            <?php
                                                $ptChip = $post['projectTypes'][0];
                                                foreach ($post['projectTypes'] as $pt) {
                                                    if (!empty($pt['parentId']) && in_array($pt['parentId'], array_column($post['projectTypes'], 'id'))) {
                                                        $ptChip = $pt;
                                                    }
                                                }
                                            ?>
                                            <div class="chip color-pair-<?= e($ptChip['colourPair']) ?>"><?= e($ptChip['title']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($post['designSource'])): ?>
                                            <div class="chip color-pair-<?= e($post['designSource']['colourPair']) ?>"><?= e($post['designSource']['title']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="panel-heading">
                                    <h3><a href="/posts/<?= e($post['slug']) ?>"><?= e($post['title']) ?></a></h3>
                                    <p class="caption"><?= e(format_date($post['postDate'])) ?></p>
                                    <?php
                                        $cardCats = array_filter($post['categories'] ?? [], fn($c) => !preg_match('/^\d{4}$/', $c['title']));
                                    ?>
                                    <?php if (!empty($cardCats)): ?>
                                        <div class="panel-category-chips">
                                            <?php foreach ($cardCats as $cat): ?>
                                                <a class="chip color-pair-<?= e($cat['colourPair']) ?>" href="/posts?category[]=<?= e($cat['slug']) ?>"><?= e($cat['title']) ?></a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php
                $totalPages = (int)ceil($result['total'] / $result['per_page']);
                if ($totalPages > 1):
                ?>
                <nav class="pagination" aria-label="Pagination">
                    <?php if ($page > 1): ?>
                        <a class="button" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page - 1]))) ?>">Previous</a>
                    <?php endif; ?>
                    <span class="pagination-info">Page <?= $page ?> of <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?>
                        <a class="button" href="?<?= e(http_build_query(array_merge($_GET, ['page' => $page + 1]))) ?>">Next</a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <aside class="sidebar sidebar--sticky">
            <form method="get" action="/posts">
                <div class="container-sections">
                    <?php if (!empty($postCategories)): ?>
                    <section class="container-section--headed">
                        <div class="container-section-header">Categories</div>
                        <div class="container-section-body">
                            <ul class="list">
                                <?php foreach ($postCategories as $cat): ?>
                                    <?php $catChecked = in_array($cat['slug'], $filters['category']); ?>
                                    <li>
                                        <label>
                                            <input type="checkbox" name="category[]" value="<?= e($cat['slug']) ?>" <?= $catChecked ? 'checked' : '' ?> onchange="this.form.submit()">
                                            <?= e($cat['title']) ?>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if (!empty($projectFamilies)): ?>
                    <section class="container-section--headed">
                        <div class="container-section-header">Project Family</div>
                        <div class="container-section-body">
                            <ul class="list">
                                <?php foreach ($projectFamilies as $family): ?>
                                    <?php $familyChecked = in_array($family['slug'], $filters['project_family']); ?>
                                    <li>
                                        <label>
                                            <input type="checkbox" name="projectFamily[]" value="<?= e($family['slug']) ?>" <?= $familyChecked ? 'checked' : '' ?> onchange="this.form.submit()">
                                            <?= e($family['title']) ?>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if (!empty($projectTypes)): ?>
                    <section class="container-section--headed">
                        <div class="container-section-header">Project Types</div>
                        <div class="container-section-body">
                            <ul class="list">
                                <?php foreach ($projectTypes as $pt): ?>
                                    <?php if (empty($pt['parentId'])): ?>
                                        <?php $parentChecked = in_array($pt['slug'], $filters['project_type']); ?>
                                        <li>
                                            <label>
                                                <input type="checkbox" name="projectType[]" value="<?= e($pt['slug']) ?>" <?= $parentChecked ? 'checked' : '' ?> onchange="this.form.submit()">
                                                <?= e($pt['title']) ?>
                                            </label>
                                        </li>
                                        <?php foreach ($projectTypes as $child): ?>
                                            <?php if (!empty($child['parentId']) && $child['parentId'] == $pt['id']): ?>
                                                <?php $childChecked = in_array($child['slug'], $filters['project_type']); ?>
                                                <li class="sub-category">
                                                    <label>
                                                        <input type="checkbox" name="projectType[]" value="<?= e($child['slug']) ?>" <?= $childChecked ? 'checked' : '' ?> onchange="this.form.submit()">
                                                        <?= e($child['title']) ?>
                                                    </label>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if (!empty($years)): ?>
                    <section class="container-section--headed">
                        <div class="container-section-header">Years</div>
                        <div class="container-section-body">
                            <ul class="list">
                                <?php foreach ($years as $yr): ?>
                                    <?php $yrChecked = in_array((string)$yr, $filters['year']); ?>
                                    <li>
                                        <label>
                                            <input type="checkbox" name="year[]" value="<?= e($yr) ?>" <?= $yrChecked ? 'checked' : '' ?> onchange="this.form.submit()">
                                            <?= e($yr) ?>
                                        </label>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </section>
                    <?php endif; ?>

                    <?php if ($hasFilters): ?>
                    <div class="container-section--headed">
                        <div class="container-section-body">
                            <a href="/posts" class="button">Clear all filters</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </aside>
    </div>
</main>

<?php
$content = ob_get_clean();
render_page('Projects', 'Completed projects archive.', $path, $content, 'Projects');
