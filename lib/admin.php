<?php
/* lib/admin.php - guarded admin CRUD for the stripped Box of Dragons site. */

const BOD_ADMIN_SITE_ID = 1;
const BOD_POST_SECTION_ID = 1;
const BOD_POST_ENTRY_TYPE_ID = 1;
const BOD_CATEGORY_COLOR_UID = '1d4f9b53-6d35-4f3f-a70f-8e2f2e4f4d2b';

function admin_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('bod_admin');
    session_start();
}

function admin_token_configured(): bool {
    $token = getenv('ADMIN_TOKEN');
    return is_string($token) && $token !== '';
}

function admin_is_authenticated(): bool {
    admin_start_session();
    return !empty($_SESSION['bod_admin_authenticated']);
}

function admin_try_login(string $token): bool {
    if (!admin_token_configured()) return false;
    $expected = (string)getenv('ADMIN_TOKEN');
    if (!hash_equals($expected, $token)) return false;
    admin_start_session();
    $_SESSION['bod_admin_authenticated'] = true;
    $_SESSION['bod_admin_csrf'] = bin2hex(random_bytes(32));
    return true;
}

function admin_logout(): void {
    admin_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function admin_csrf_token(): string {
    admin_start_session();
    if (empty($_SESSION['bod_admin_csrf'])) {
        $_SESSION['bod_admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['bod_admin_csrf'];
}

function admin_require_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(admin_csrf_token(), $token)) {
        http_response_code(400);
        throw new RuntimeException('Invalid admin form token.');
    }
}

function admin_redirect(string $path, string $message = ''): void {
    if ($message !== '') {
        $separator = str_contains($path, '?') ? '&' : '?';
        $path .= $separator . 'message=' . rawurlencode($message);
    }
    header('Location: ' . $path);
    exit;
}

function admin_now(): string {
    return date('Y-m-d H:i:s');
}

function admin_uuid(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function admin_slugify(string $value): string {
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'untitled';
}

function admin_unique_slug(string $slug, string $elementType, ?int $ignoreElementId = null): string {
    $base = admin_slugify($slug);
    $candidate = $base;
    $i = 2;
    while (admin_slug_exists($candidate, $elementType, $ignoreElementId)) {
        $candidate = $base . '-' . $i;
        $i++;
    }
    return $candidate;
}

function admin_slug_exists(string $slug, string $elementType, ?int $ignoreElementId = null): bool {
    $sql = "SELECT COUNT(*)
            FROM elements_sites es
            JOIN elements el ON el.id = es.elementId
            WHERE es.slug = ? AND el.type = ? AND el.dateDeleted IS NULL";
    $params = [$slug, $elementType];
    if ($ignoreElementId) {
        $sql .= " AND es.elementId <> ?";
        $params[] = $ignoreElementId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() > 0;
}

function admin_taxonomy_configs(): array {
    $configs = [
        'postCategories' => ['label' => 'Categories', 'field' => 'postCategories'],
        'projectFamilies' => ['label' => 'Project Families', 'field' => 'projectFamily'],
        'projectTypes' => ['label' => 'Project Types', 'field' => 'projectTypes'],
        'designSources' => ['label' => 'Design Sources', 'field' => 'designSource'],
    ];

    $out = [];
    foreach ($configs as $groupHandle => $config) {
        $group = admin_category_group_by_handle($groupHandle);
        if (!$group) continue;
        $config['groupHandle'] = $groupHandle;
        $config['groupId'] = (int)$group['id'];
        $config['groupName'] = $group['name'];
        $config['structureId'] = (int)$group['structureId'];
        $out[$groupHandle] = $config;
    }
    return $out;
}

function admin_category_group_by_handle(string $handle): ?array {
    $stmt = db()->prepare("SELECT id, name, handle, structureId FROM categorygroups WHERE handle = ? AND dateDeleted IS NULL LIMIT 1");
    $stmt->execute([$handle]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function admin_posts_list(): array {
    $sql = "SELECT en.id, en.postDate, en.status, el.enabled, es.title, es.slug
            FROM entries en
            JOIN elements el ON el.id = en.id
            JOIN elements_sites es ON es.elementId = en.id AND es.siteId = ?
            WHERE en.sectionId = ? AND el.dateDeleted IS NULL
            ORDER BY en.postDate DESC, en.id DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute([BOD_ADMIN_SITE_ID, BOD_POST_SECTION_ID]);
    return $stmt->fetchAll();
}

function admin_get_post(int $id): ?array {
    $sql = "SELECT en.id, en.postDate, en.status, el.enabled, es.title, es.slug, es.content
            FROM entries en
            JOIN elements el ON el.id = en.id
            JOIN elements_sites es ON es.elementId = en.id AND es.siteId = ?
            WHERE en.id = ? AND en.sectionId = ? AND el.dateDeleted IS NULL
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([BOD_ADMIN_SITE_ID, $id, BOD_POST_SECTION_ID]);
    $row = $stmt->fetch();
    if (!$row) return null;

    $content = json_decode($row['content'] ?? '{}', true) ?: [];
    $row['body'] = admin_content_value($content, 'body', '');
    $row['resourceLinks'] = admin_content_value($content, 'resourceLinks', []);
    $row['relations'] = [];
    foreach (admin_taxonomy_configs() as $config) {
        $row['relations'][$config['field']] = array_column(get_post_categories($id, $config['field']), 'id');
    }
    return $row;
}

function admin_content_value(array $content, string $fieldHandle, mixed $fallback): mixed {
    $uid = field_uid($fieldHandle);
    if ($uid && array_key_exists($uid, $content)) return $content[$uid];
    if ($fieldHandle === 'body') {
        foreach ($content as $value) {
            if (is_string($value) && $value !== '') return $value;
        }
    }
    if ($fieldHandle === 'resourceLinks') {
        foreach ($content as $value) {
            if (is_array($value) && (empty($value) || isset($value[0]['col1'], $value[0]['col2']))) return $value;
        }
    }
    return $fallback;
}

function admin_save_post(array $data): int {
    $id = (int)($data['id'] ?? 0);
    $title = trim((string)($data['title'] ?? ''));
    if ($title === '') throw new InvalidArgumentException('Title is required.');

    $slug = trim((string)($data['slug'] ?? ''));
    $slug = admin_unique_slug($slug !== '' ? $slug : $title, 'craft\\elements\\Entry', $id ?: null);
    $status = in_array(($data['status'] ?? 'live'), ['live', 'pending', 'expired'], true) ? $data['status'] : 'live';
    $enabled = !empty($data['enabled']) ? 1 : 0;
    $postDate = trim((string)($data['postDate'] ?? ''));
    $postDate = $postDate !== '' ? date('Y-m-d H:i:s', strtotime($postDate)) : admin_now();
    $body = (string)($data['body'] ?? '');
    $resourceLinks = admin_normalize_resource_links((string)($data['resourceLinksText'] ?? ''));
    $content = admin_post_content($body, $resourceLinks);
    $now = admin_now();

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE elements SET enabled = ?, dateUpdated = ? WHERE id = ? AND type = 'craft\\\\elements\\\\Entry'");
            $stmt->execute([$enabled, $now, $id]);
            $stmt = $pdo->prepare("UPDATE entries SET postDate = ?, status = ?, dateUpdated = ? WHERE id = ? AND sectionId = ?");
            $stmt->execute([$postDate, $status, $now, $id, BOD_POST_SECTION_ID]);
            $stmt = $pdo->prepare("UPDATE elements_sites SET title = ?, slug = ?, uri = ?, content = ?, enabled = ?, dateUpdated = ? WHERE elementId = ? AND siteId = ?");
            $stmt->execute([$title, $slug, 'posts/' . $slug, $content, $enabled, $now, $id, BOD_ADMIN_SITE_ID]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO elements (canonicalId, draftId, revisionId, fieldLayoutId, type, enabled, archived, dateCreated, dateUpdated, dateLastMerged, dateDeleted, deletedWithOwner, uid) VALUES (NULL, NULL, NULL, NULL, 'craft\\\\elements\\\\Entry', ?, 0, ?, ?, NULL, NULL, NULL, ?)");
            $stmt->execute([$enabled, $now, $now, admin_uuid()]);
            $id = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO entries (id, sectionId, parentId, primaryOwnerId, fieldId, typeId, postDate, expiryDate, status, deletedWithEntryType, deletedWithSection, dateCreated, dateUpdated) VALUES (?, ?, NULL, NULL, NULL, ?, ?, NULL, ?, NULL, NULL, ?, ?)");
            $stmt->execute([$id, BOD_POST_SECTION_ID, BOD_POST_ENTRY_TYPE_ID, $postDate, $status, $now, $now]);
            $stmt = $pdo->prepare("INSERT INTO elements_sites (elementId, siteId, title, slug, uri, content, enabled, dateCreated, dateUpdated, uid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$id, BOD_ADMIN_SITE_ID, $title, $slug, 'posts/' . $slug, $content, $enabled, $now, $now, admin_uuid()]);
        }

        foreach (admin_taxonomy_configs() as $config) {
            admin_replace_relations($id, $config['field'], array_map('intval', $data[$config['field']] ?? []));
        }

        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function admin_post_content(string $body, array $resourceLinks): string {
    $content = [];
    $bodyUid = field_uid('body');
    $resourceLinksUid = field_uid('resourceLinks');
    if ($bodyUid) $content[$bodyUid] = $body;
    if ($resourceLinksUid) $content[$resourceLinksUid] = $resourceLinks;
    return json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function admin_normalize_resource_links(string $text): array {
    $links = [];
    foreach (preg_split('/\r?\n/', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = array_map('trim', explode('|', $line, 2));
        $links[] = [
            'col1' => $parts[0] ?? '',
            'col2' => $parts[1] ?? ($parts[0] ?? ''),
        ];
    }
    return $links;
}

function admin_resource_links_text(array $links): string {
    $lines = [];
    foreach ($links as $link) {
        $label = trim((string)($link['col1'] ?? $link['label'] ?? ''));
        $url = trim((string)($link['col2'] ?? $link['url'] ?? ''));
        if ($label === '' && $url === '') continue;
        $lines[] = $label . ' | ' . $url;
    }
    return implode("\n", $lines);
}

function admin_replace_relations(int $sourceId, string $fieldHandle, array $targetIds): void {
    $fieldId = field_id_for_handle($fieldHandle);
    if (!$fieldId) return;

    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM relations WHERE sourceId = ? AND fieldId = ?");
    $stmt->execute([$sourceId, $fieldId]);

    $targetIds = array_values(array_unique(array_filter($targetIds, fn($id) => $id > 0)));
    $stmt = $pdo->prepare("INSERT INTO relations (fieldId, sourceId, sourceSiteId, targetId, sortOrder, dateCreated, dateUpdated, uid) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $sort = 1;
    $now = admin_now();
    foreach ($targetIds as $targetId) {
        $stmt->execute([$fieldId, $sourceId, BOD_ADMIN_SITE_ID, $targetId, $sort, $now, $now, admin_uuid()]);
        $sort++;
    }
}

function admin_delete_post(int $id): void {
    $now = admin_now();
    $stmt = db()->prepare("UPDATE elements SET dateDeleted = ?, dateUpdated = ? WHERE id = ? AND type = 'craft\\\\elements\\\\Entry'");
    $stmt->execute([$now, $now, $id]);
}

function admin_save_category(array $data): int {
    $id = (int)($data['id'] ?? 0);
    $groupId = (int)($data['groupId'] ?? 0);
    $title = trim((string)($data['title'] ?? ''));
    if ($groupId <= 0) throw new InvalidArgumentException('Taxonomy is required.');
    if ($title === '') throw new InvalidArgumentException('Category title is required.');

    $group = admin_category_group_by_id($groupId);
    if (!$group) throw new InvalidArgumentException('Unknown taxonomy.');

    $parentId = (int)($data['parentId'] ?? 0);
    if ($parentId <= 0) $parentId = null;
    $slug = trim((string)($data['slug'] ?? ''));
    $slug = admin_unique_slug($slug !== '' ? $slug : $title, 'craft\\elements\\Category', $id ?: null);
    $colourPair = admin_valid_colour_pair((string)($data['colourPair'] ?? 'gold'));
    $content = admin_category_content($colourPair);
    $now = admin_now();

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE elements SET dateUpdated = ? WHERE id = ? AND type = 'craft\\\\elements\\\\Category'");
            $stmt->execute([$now, $id]);
            $stmt = $pdo->prepare("UPDATE categories SET parentId = ?, dateUpdated = ? WHERE id = ? AND groupId = ?");
            $stmt->execute([$parentId, $now, $id, $groupId]);
            $stmt = $pdo->prepare("UPDATE elements_sites SET title = ?, slug = ?, uri = ?, content = ?, dateUpdated = ? WHERE elementId = ? AND siteId = ?");
            $stmt->execute([$title, $slug, 'category/' . $slug, $content, $now, $id, BOD_ADMIN_SITE_ID]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO elements (canonicalId, draftId, revisionId, fieldLayoutId, type, enabled, archived, dateCreated, dateUpdated, dateLastMerged, dateDeleted, deletedWithOwner, uid) VALUES (NULL, NULL, NULL, NULL, 'craft\\\\elements\\\\Category', 1, 0, ?, ?, NULL, NULL, NULL, ?)");
            $stmt->execute([$now, $now, admin_uuid()]);
            $id = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare("INSERT INTO categories (id, groupId, parentId, deletedWithGroup, dateCreated, dateUpdated) VALUES (?, ?, ?, NULL, ?, ?)");
            $stmt->execute([$id, $groupId, $parentId, $now, $now]);
            $stmt = $pdo->prepare("INSERT INTO elements_sites (elementId, siteId, title, slug, uri, content, enabled, dateCreated, dateUpdated, uid) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?, ?)");
            $stmt->execute([$id, BOD_ADMIN_SITE_ID, $title, $slug, 'category/' . $slug, $content, $now, $now, admin_uuid()]);
            admin_insert_structure_element((int)$group['structureId'], $id, $parentId);
        }

        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function admin_get_category(int $id): ?array {
    $sql = "SELECT c.id, c.groupId, c.parentId, ces.title, ces.slug,
                   JSON_EXTRACT(ces.content, '$.\"" . BOD_CATEGORY_COLOR_UID . "\"') AS colourPair
            FROM categories c
            JOIN elements el ON el.id = c.id
            JOIN elements_sites ces ON ces.elementId = c.id AND ces.siteId = ?
            WHERE c.id = ? AND el.dateDeleted IS NULL
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([BOD_ADMIN_SITE_ID, $id]);
    $row = $stmt->fetch();
    return $row ? normalize_category($row) : null;
}

function admin_category_group_by_id(int $id): ?array {
    $stmt = db()->prepare("SELECT id, name, handle, structureId FROM categorygroups WHERE id = ? AND dateDeleted IS NULL LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function admin_category_content(string $colourPair): string {
    $content = [BOD_CATEGORY_COLOR_UID => $colourPair];
    $currentUid = field_uid('colourPair');
    if ($currentUid && $currentUid !== BOD_CATEGORY_COLOR_UID) {
        $content[$currentUid] = $colourPair;
    }
    return json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function admin_insert_structure_element(int $structureId, int $elementId, ?int $parentId): void {
    $pdo = db();
    $now = admin_now();
    if ($parentId) {
        $stmt = $pdo->prepare("SELECT root, rgt, level FROM structureelements WHERE structureId = ? AND elementId = ? LIMIT 1");
        $stmt->execute([$structureId, $parentId]);
        $parent = $stmt->fetch();
        if (!$parent) $parentId = null;
    }

    if ($parentId && !empty($parent)) {
        $root = (int)$parent['root'];
        $lft = (int)$parent['rgt'];
        $level = (int)$parent['level'] + 1;
        $pdo->prepare("UPDATE structureelements SET rgt = rgt + 2 WHERE structureId = ? AND root = ? AND rgt >= ?")->execute([$structureId, $root, $lft]);
        $pdo->prepare("UPDATE structureelements SET lft = lft + 2 WHERE structureId = ? AND root = ? AND lft > ?")->execute([$structureId, $root, $lft]);
    } else {
        $stmt = $pdo->prepare("SELECT id, root, rgt FROM structureelements WHERE structureId = ? AND elementId IS NULL ORDER BY id LIMIT 1");
        $stmt->execute([$structureId]);
        $rootRow = $stmt->fetch();
        if (!$rootRow) {
            $pdo->prepare("INSERT INTO structureelements (structureId, elementId, root, lft, rgt, level, dateCreated, dateUpdated, uid) VALUES (?, NULL, NULL, 1, 4, 0, ?, ?, ?)")->execute([$structureId, $now, $now, admin_uuid()]);
            $root = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE structureelements SET root = ? WHERE id = ?")->execute([$root, $root]);
            $lft = 2;
        } else {
            $root = (int)($rootRow['root'] ?: $rootRow['id']);
            $lft = (int)$rootRow['rgt'];
            $pdo->prepare("UPDATE structureelements SET rgt = rgt + 2 WHERE id = ?")->execute([(int)$rootRow['id']]);
        }
        $level = 1;
    }

    $stmt = $pdo->prepare("INSERT INTO structureelements (structureId, elementId, root, lft, rgt, level, dateCreated, dateUpdated, uid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$structureId, $elementId, $root, $lft, $lft + 1, $level, $now, $now, admin_uuid()]);
}

function admin_delete_category(int $id): void {
    $now = admin_now();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM relations WHERE targetId = ?")->execute([$id]);
        $pdo->prepare("UPDATE elements SET dateDeleted = ?, dateUpdated = ? WHERE id = ? AND type = 'craft\\\\elements\\\\Category'")->execute([$now, $now, $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function admin_valid_colour_pair(string $value): string {
    return in_array($value, admin_colour_pairs(), true) ? $value : 'gold';
}

function admin_colour_pairs(): array {
    return ['gold', 'sand', 'sage', 'olive', 'sky', 'stone', 'rose', 'clay', 'plum', 'ink', 'moss', 'lavender', 'peach', 'slate', 'rust', 'berry'];
}
