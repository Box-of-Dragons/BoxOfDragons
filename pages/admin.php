<?php
/* pages/admin.php - simple guarded admin for posts and taxonomy terms. */

admin_start_session();

function admin_post_value(array $post, string $key, string $default = ''): string {
    return (string)($post[$key] ?? $default);
}

function admin_selected(array $values, int $id): string {
    return in_array($id, array_map('intval', $values), true) ? ' selected' : '';
}

function admin_checked(bool $checked): string {
    return $checked ? ' checked' : '';
}

function admin_flash(): string {
    $message = $_GET['message'] ?? '';
    if (!is_string($message) || $message === '') return '';
    return '<div class="panel panel--padded"><p class="body">' . e($message) . '</p></div>';
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'login') {
            if (admin_try_login((string)($_POST['token'] ?? ''))) {
                admin_redirect('/admin', 'Signed in.');
            }
            admin_redirect('/admin', 'Invalid admin token.');
        }

        if (!admin_is_authenticated()) {
            admin_redirect('/admin', 'Sign in first.');
        }

        admin_require_csrf();

        if ($action === 'logout') {
            admin_logout();
            admin_redirect('/admin', 'Signed out.');
        }

        if ($action === 'save_post') {
            $id = admin_save_post($_POST);
            admin_redirect('/admin?view=post&id=' . $id, 'Post saved.');
        }

        if ($action === 'delete_post') {
            admin_delete_post((int)($_POST['id'] ?? 0));
            admin_redirect('/admin', 'Post removed.');
        }

        if ($action === 'save_category') {
            $id = admin_save_category($_POST);
            admin_redirect('/admin?view=category&id=' . $id, 'Taxonomy term saved.');
        }

        if ($action === 'delete_category') {
            admin_delete_category((int)($_POST['id'] ?? 0));
            admin_redirect('/admin', 'Taxonomy term removed.');
        }
    }
} catch (Throwable $e) {
    admin_redirect('/admin', $e->getMessage());
}

if (!admin_token_configured()) {
    $content = '<main class="page-main"><div class="shell"><div class="container"><div class="panel panel--padded">'
        . '<h2>Admin Disabled</h2>'
        . '<p>Set <code>ADMIN_TOKEN</code> in <code>.env</code> before using this page.</p>'
        . '</div></div></div></main>';
    render_page('Admin', 'Box of Dragons admin.', '/admin', $content, 'Admin');
    exit;
}

if (!admin_is_authenticated()) {
    ob_start();
    ?>
    <main class="page-main">
        <div class="shell">
            <div class="container">
                <?= admin_flash() ?>
                <section class="panel panel--padded">
                    <h2>Admin Sign In</h2>
                    <form method="post" action="/admin">
                        <input type="hidden" name="action" value="login">
                        <div class="form-row">
                            <label for="admin-token">Token</label>
                            <input id="admin-token" type="password" name="token" autocomplete="current-password" required>
                        </div>
                        <div class="container-actions">
                            <button class="button button-primary" type="submit">Sign in</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </main>
    <?php
    $content = ob_get_clean();
    render_page('Admin', 'Box of Dragons admin.', '/admin', $content, 'Admin');
    exit;
}

$view = (string)($_GET['view'] ?? 'dashboard');
$taxonomyConfigs = admin_taxonomy_configs();
$allTerms = [];
foreach ($taxonomyConfigs as $groupHandle => $config) {
    $allTerms[$groupHandle] = get_categories((int)$config['groupId']);
}

ob_start();
?>

<main class="page-main">
    <div class="shell page-layout page-layout--wide">
        <div class="content-main">
            <?= admin_flash() ?>

            <?php if ($view === 'post'): ?>
                <?php
                    $postId = (int)($_GET['id'] ?? 0);
                    $post = $postId ? admin_get_post($postId) : [
                        'id' => 0,
                        'title' => '',
                        'slug' => '',
                        'postDate' => date('Y-m-d H:i:s'),
                        'status' => 'live',
                        'enabled' => 1,
                        'body' => '',
                        'resourceLinks' => [],
                        'relations' => [],
                    ];
                    if (!$post) {
                        echo '<div class="panel panel--padded"><h2>Post not found</h2><p><a class="button" href="/admin">Back to admin</a></p></div>';
                    } else {
                ?>
                <section class="panel panel--padded">
                    <h2><?= $post['id'] ? 'Edit Post' : 'New Post' ?></h2>
                    <form method="post" action="/admin">
                        <input type="hidden" name="action" value="save_post">
                        <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= e((string)$post['id']) ?>">

                        <div class="form-row">
                            <label for="title">Title</label>
                            <input id="title" type="text" name="title" value="<?= e(admin_post_value($post, 'title')) ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="slug">Slug</label>
                            <input id="slug" type="text" name="slug" value="<?= e(admin_post_value($post, 'slug')) ?>" placeholder="auto-generated if blank">
                        </div>
                        <div class="form-row">
                            <label for="postDate">Post date</label>
                            <input id="postDate" type="text" name="postDate" value="<?= e(admin_post_value($post, 'postDate')) ?>">
                        </div>
                        <div class="form-row">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <?php foreach (['live', 'pending', 'expired'] as $status): ?>
                                    <option value="<?= e($status) ?>" <?= admin_post_value($post, 'status', 'live') === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="checkbox-row">
                            <label><input type="checkbox" name="enabled" value="1" <?= admin_checked(!empty($post['enabled'])) ?>> Enabled</label>
                        </div>

                        <h3>Body</h3>
                        <textarea name="body" rows="10"><?= e(admin_post_value($post, 'body')) ?></textarea>

                        <h3>Resource Links</h3>
                        <p class="caption">One per line: <code>Label | https://example.com</code></p>
                        <textarea name="resourceLinksText" rows="5"><?= e(admin_resource_links_text($post['resourceLinks'] ?? [])) ?></textarea>

                        <?php foreach ($taxonomyConfigs as $config): ?>
                            <?php $selected = $post['relations'][$config['field']] ?? []; ?>
                            <h3><?= e($config['label']) ?></h3>
                            <select name="<?= e($config['field']) ?>[]" multiple size="<?= max(3, min(8, count($allTerms[$config['groupHandle']] ?? []))) ?>">
                                <?php foreach ($allTerms[$config['groupHandle']] ?? [] as $term): ?>
                                    <option value="<?= e((string)$term['id']) ?>"<?= admin_selected($selected, (int)$term['id']) ?>>
                                        <?= e($term['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endforeach; ?>

                        <div class="container-actions">
                            <button class="button button-primary" type="submit">Save post</button>
                            <a class="button" href="/admin">Back</a>
                            <?php if (!empty($post['slug'])): ?>
                                <a class="button" href="/posts/<?= e($post['slug']) ?>">View public page</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
                <?php } ?>

            <?php elseif ($view === 'category'): ?>
                <?php
                    $categoryId = (int)($_GET['id'] ?? 0);
                    $defaultGroup = (int)($_GET['groupId'] ?? 0);
                    $category = $categoryId ? admin_get_category($categoryId) : [
                        'id' => 0,
                        'groupId' => $defaultGroup,
                        'parentId' => null,
                        'title' => '',
                        'slug' => '',
                        'colourPair' => 'gold',
                    ];
                    if (!$category) {
                        echo '<div class="panel panel--padded"><h2>Taxonomy term not found</h2><p><a class="button" href="/admin">Back to admin</a></p></div>';
                    } else {
                ?>
                <section class="panel panel--padded">
                    <h2><?= $category['id'] ? 'Edit Taxonomy Term' : 'New Taxonomy Term' ?></h2>
                    <form method="post" action="/admin">
                        <input type="hidden" name="action" value="save_category">
                        <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                        <input type="hidden" name="id" value="<?= e((string)$category['id']) ?>">

                        <div class="form-row">
                            <label for="groupId">Taxonomy</label>
                            <select id="groupId" name="groupId" required>
                                <?php foreach ($taxonomyConfigs as $config): ?>
                                    <option value="<?= e((string)$config['groupId']) ?>" <?= (int)$category['groupId'] === (int)$config['groupId'] ? 'selected' : '' ?>><?= e($config['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <label for="cat-title">Title</label>
                            <input id="cat-title" type="text" name="title" value="<?= e(admin_post_value($category, 'title')) ?>" required>
                        </div>
                        <div class="form-row">
                            <label for="cat-slug">Slug</label>
                            <input id="cat-slug" type="text" name="slug" value="<?= e(admin_post_value($category, 'slug')) ?>" placeholder="auto-generated if blank">
                        </div>
                        <div class="form-row">
                            <label for="parentId">Parent</label>
                            <select id="parentId" name="parentId">
                                <option value="">None</option>
                                <?php
                                    $groupHandle = '';
                                    foreach ($taxonomyConfigs as $handle => $config) {
                                        if ((int)$config['groupId'] === (int)$category['groupId']) $groupHandle = $handle;
                                    }
                                    foreach ($allTerms[$groupHandle] ?? [] as $term):
                                        if ((int)$term['id'] === (int)$category['id']) continue;
                                ?>
                                    <option value="<?= e((string)$term['id']) ?>" <?= (int)($category['parentId'] ?? 0) === (int)$term['id'] ? 'selected' : '' ?>><?= e($term['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <label for="colourPair">Colour</label>
                            <select id="colourPair" name="colourPair">
                                <?php foreach (admin_colour_pairs() as $colour): ?>
                                    <option value="<?= e($colour) ?>" <?= ($category['colourPair'] ?? 'gold') === $colour ? 'selected' : '' ?>><?= e($colour) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="container-actions">
                            <button class="button button-primary" type="submit">Save term</button>
                            <a class="button" href="/admin">Back</a>
                        </div>
                    </form>
                </section>
                <?php } ?>

            <?php else: ?>
                <section class="panel panel--padded">
                    <div class="container-actions">
                        <h2>Posts</h2>
                        <a class="button button-primary" href="/admin?view=post">New post</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (admin_posts_list() as $post): ?>
                                <tr>
                                    <td><?= e($post['title']) ?><br><span class="caption"><?= e($post['slug']) ?></span></td>
                                    <td><?= e($post['enabled'] ? $post['status'] : 'disabled') ?></td>
                                    <td><?= e(format_date($post['postDate'])) ?></td>
                                    <td>
                                        <div class="container-actions">
                                            <a class="button button-sm" href="/admin?view=post&id=<?= e((string)$post['id']) ?>">Edit</a>
                                            <a class="button button-sm" href="/posts/<?= e($post['slug']) ?>">View</a>
                                            <form method="post" action="/admin" onsubmit="return confirm('Remove this post?');">
                                                <input type="hidden" name="action" value="delete_post">
                                                <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                                                <input type="hidden" name="id" value="<?= e((string)$post['id']) ?>">
                                                <button class="button button-sm" type="submit">Remove</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            <?php endif; ?>
        </div>

        <aside class="sidebar sidebar--sticky">
            <div class="container-sections">
                <section class="container-section--headed">
                    <div class="container-section-header">Admin</div>
                    <div class="container-section-body">
                        <div class="container-actions">
                            <a class="button" href="/admin">Dashboard</a>
                            <a class="button" href="/posts">Public posts</a>
                        </div>
                        <form method="post" action="/admin">
                            <input type="hidden" name="action" value="logout">
                            <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                            <button class="button button-secondary" type="submit">Sign out</button>
                        </form>
                    </div>
                </section>

                <?php foreach ($taxonomyConfigs as $groupHandle => $config): ?>
                    <section class="container-section--headed">
                        <div class="container-section-header"><?= e($config['label']) ?></div>
                        <div class="container-section-body">
                            <div class="container-actions">
                                <a class="button button-sm" href="/admin?view=category&groupId=<?= e((string)$config['groupId']) ?>">New</a>
                            </div>
                            <ul class="list">
                                <?php foreach ($allTerms[$groupHandle] ?? [] as $term): ?>
                                    <li>
                                        <a href="/admin?view=category&id=<?= e((string)$term['id']) ?>"><?= e($term['title']) ?></a>
                                        <form method="post" action="/admin" onsubmit="return confirm('Remove this taxonomy term?');">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="csrf" value="<?= e(admin_csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= e((string)$term['id']) ?>">
                                            <button class="button button-sm" type="submit">Remove</button>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </aside>
    </div>
</main>

<?php
$content = ob_get_clean();
render_page('Admin', 'Box of Dragons admin.', '/admin', $content, 'Admin');
