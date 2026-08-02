<?php
/* lib/posts.php — post queries against the existing Craft tables.
 *
 * Tables used (trimmed Craft schema):
 *   entries          — id, postDate, status (sectionId/typeId hardcoded, no FKs)
 *   elements         — id, enabled, dateDeleted (soft delete)
 *   elements_sites   — elementId, title, slug, uri, content (JSON field values)
 *   relations        — fieldId, sourceId, targetId (relates posts to assets/categories)
 *   fields           — id, handle, uid (field UUIDs map to handles)
 *   categories       — id, groupId, parentId
 *   assets           — id, filename, folderId, path, alt
 *
 * Field content is stored in elements_sites.content as JSON keyed by field UID.
 * Relations are stored in the relations table keyed by field id.
 */

/** Field UIDs we care about (from the fields table). */
function field_uids(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $stmt = db()->query("SELECT handle, uid FROM fields");
    $cache = [];
    foreach ($stmt->fetchAll() as $row) {
        $cache[$row['handle']] = $row['uid'];
    }
    return $cache;
}

/** Get the field UID for a handle. */
function field_uid(string $handle): ?string {
    $uids = field_uids();
    return $uids[$handle] ?? null;
}

/** Get a single post by slug, with all its data. Returns null if not found. */
function get_post_by_slug(string $slug): ?array {
    $sql = "SELECT en.id, en.postDate, es.title, es.slug, es.content
            FROM entries en
            JOIN elements el ON el.id = en.id
            JOIN elements_sites es ON es.elementId = en.id
            WHERE en.sectionId = 1
              AND el.dateDeleted IS NULL
              AND el.enabled = 1
              AND en.status = 'live'
              AND es.slug = ?
            LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return hydrate_post($row);
}

/** Get paginated posts for the archive, with optional filters. */
function get_posts(array $filters = [], int $page = 1, int $per_page = 12): array {
    $where = ["en.sectionId = 1", "el.dateDeleted IS NULL", "el.enabled = 1", "en.status = 'live'"];
    $params = [];

    if (!empty($filters['category'])) {
        $catFieldId = field_id_for_handle('postCategories');
        $placeholders = implode(',', array_fill(0, count($filters['category']), '?'));
        $where[] = "EXISTS (
            SELECT 1 FROM relations r
            JOIN categories c ON c.id = r.targetId
            JOIN elements ce ON ce.id = c.id
            JOIN elements_sites ces ON ces.elementId = c.id
            WHERE r.sourceId = en.id AND r.fieldId = {$catFieldId}
              AND ce.dateDeleted IS NULL
              AND ces.slug IN ({$placeholders})
        )";
        $params = array_merge($params, $filters['category']);
    }

    if (!empty($filters['project_type'])) {
        $ptFieldId = field_id_for_handle('projectTypes');
        $placeholders = implode(',', array_fill(0, count($filters['project_type']), '?'));
        $where[] = "EXISTS (
            SELECT 1 FROM relations r
            JOIN categories c ON c.id = r.targetId
            JOIN elements ce ON ce.id = c.id
            JOIN elements_sites ces ON ces.elementId = c.id
            WHERE r.sourceId = en.id AND r.fieldId = {$ptFieldId}
              AND ce.dateDeleted IS NULL
              AND ces.slug IN ({$placeholders})
        )";
        $params = array_merge($params, $filters['project_type']);
    }

    if (!empty($filters['project_family'])) {
        $familyFieldId = field_id_for_handle('projectFamily');
        if ($familyFieldId) {
            $placeholders = implode(',', array_fill(0, count($filters['project_family']), '?'));
            $where[] = "EXISTS (
                SELECT 1 FROM relations r
                JOIN categories c ON c.id = r.targetId
                JOIN elements ce ON ce.id = c.id
                JOIN elements_sites ces ON ces.elementId = c.id
                WHERE r.sourceId = en.id AND r.fieldId = {$familyFieldId}
                  AND ce.dateDeleted IS NULL
                  AND ces.slug IN ({$placeholders})
            )";
            $params = array_merge($params, $filters['project_family']);
        }
    }

    if (!empty($filters['year'])) {
        $placeholders = implode(',', array_fill(0, count($filters['year']), '?'));
        $where[] = "YEAR(en.postDate) IN ({$placeholders})";
        $params = array_merge($params, $filters['year']);
    }

    $whereClause = implode(' AND ', $where);
    $offset = ($page - 1) * $per_page;

    // Count total
    $countSql = "SELECT COUNT(*) FROM entries en JOIN elements el ON el.id = en.id WHERE {$whereClause}";
    $stmt = db()->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    // Fetch page
    $sql = "SELECT en.id, en.postDate, es.title, es.slug, es.content
            FROM entries en
            JOIN elements el ON el.id = en.id
            JOIN elements_sites es ON es.elementId = en.id
            WHERE {$whereClause}
            ORDER BY en.postDate DESC
            LIMIT {$per_page} OFFSET {$offset}";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $posts = [];
    foreach ($stmt->fetchAll() as $row) {
        $posts[] = hydrate_post($row);
    }

    return ['posts' => $posts, 'total' => $total, 'page' => $page, 'per_page' => $per_page];
}

/** Get the 3 most recent posts for the homepage hero/cards. */
function get_recent_posts(int $limit = 3): array {
    $sql = "SELECT en.id, en.postDate, es.title, es.slug, es.content
            FROM entries en
            JOIN elements el ON el.id = en.id
            JOIN elements_sites es ON es.elementId = en.id
            WHERE en.sectionId = 1
              AND el.dateDeleted IS NULL
              AND el.enabled = 1
              AND en.status = 'live'
            ORDER BY en.postDate DESC
            LIMIT {$limit}";
    $posts = [];
    foreach (db()->query($sql)->fetchAll() as $row) {
        $posts[] = hydrate_post($row);
    }
    return $posts;
}

/** Get all categories in a group, with slug, title, and colourPair. */
function get_categories(int $groupId): array {
    $sql = "SELECT c.id, c.parentId, ces.title, ces.slug,
                   JSON_EXTRACT(ces.content, '$.\"1d4f9b53-6d35-4f3f-a70f-8e2f2e4f4d2b\"') AS colourPair
            FROM categories c
            JOIN elements ce ON ce.id = c.id
            JOIN elements_sites ces ON ces.elementId = c.id
            WHERE c.groupId = ? AND ce.dateDeleted IS NULL
            ORDER BY ces.title";
    $stmt = db()->prepare($sql);
    $stmt->execute([$groupId]);
    return array_map('normalize_category', $stmt->fetchAll());
}

/** Get all categories in a group by handle. */
function get_categories_by_group_handle(string $handle): array {
    $groupId = category_group_id_for_handle($handle);
    return $groupId ? get_categories($groupId) : [];
}

/** Get all distinct years that have posts, descending. */
function get_post_years(): array {
    $sql = "SELECT DISTINCT YEAR(en.postDate) AS yr
            FROM entries en JOIN elements el ON el.id = en.id
            WHERE en.sectionId = 1 AND el.dateDeleted IS NULL AND en.status = 'live'
            ORDER BY yr DESC";
    return array_map(fn($r) => (int)$r['yr'], db()->query($sql)->fetchAll());
}

/** Get the categories related to a post (by field handle). */
function get_post_categories(int $postId, string $fieldHandle): array {
    $fieldId = field_id_for_handle($fieldHandle);
    if (!$fieldId) return [];
    $sql = "SELECT c.id, c.parentId, ces.title, ces.slug,
                   JSON_EXTRACT(ces.content, '$.\"1d4f9b53-6d35-4f3f-a70f-8e2f2e4f4d2b\"') AS colourPair
            FROM relations r
            JOIN categories c ON c.id = r.targetId
            JOIN elements ce ON ce.id = c.id
            JOIN elements_sites ces ON ces.elementId = c.id
            WHERE r.sourceId = ? AND r.fieldId = ? AND ce.dateDeleted IS NULL
            ORDER BY ces.title";
    $stmt = db()->prepare($sql);
    $stmt->execute([$postId, $fieldId]);
    return array_map('normalize_category', $stmt->fetchAll());
}

/** Normalize a category row — strip JSON quotes from colourPair, default to 'gold'. */
function normalize_category(array $row): array {
    $row['colourPair'] = isset($row['colourPair']) ? trim($row['colourPair'], '"') : 'gold';
    if ($row['colourPair'] === '' || $row['colourPair'] === 'null') {
        $row['colourPair'] = 'gold';
    }
    return $row;
}

/** Get the featured image asset for a post. Returns [url, alt] or null. */
function get_post_featured_image(int $postId): ?array {
    $fieldId = field_id_for_handle('featuredImage');
    if (!$fieldId) return null;
    $sql = "SELECT a.id, a.filename, a.alt, a.path
            FROM relations r
            JOIN assets a ON a.id = r.targetId
            WHERE r.sourceId = ? AND r.fieldId = ?
            ORDER BY r.sortOrder LIMIT 1";
    $stmt = db()->prepare($sql);
    $stmt->execute([$postId, $fieldId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'url' => asset_url($row),
        'alt' => $row['alt'] ?: '',
    ];
}

/** Get gallery images for a post (postImages field). */
function get_post_gallery_images(int $postId): array {
    $fieldId = field_id_for_handle('postImages');
    if (!$fieldId) return [];
    $sql = "SELECT a.id, a.filename, a.alt, a.path
            FROM relations r
            JOIN assets a ON a.id = r.targetId
            WHERE r.sourceId = ? AND r.fieldId = ?
            ORDER BY r.sortOrder";
    $stmt = db()->prepare($sql);
    $stmt->execute([$postId, $fieldId]);
    $images = [];
    foreach ($stmt->fetchAll() as $row) {
        $images[] = [
            'url' => asset_url($row),
            'alt' => $row['alt'] ?: '',
        ];
    }
    return $images;
}

/** Build the public URL for an asset row. */
function asset_url(array $assetRow): string {
    // UPLOADS_BASE_URL is the public path (e.g. /uploads/posts).
    // assets.path gives the per-asset subfolder (e.g. "2/").
    $base = rtrim(UPLOADS_BASE_URL, '/');
    $folderPath = trim($assetRow['path'] ?? '', '/');
    $filename = $assetRow['filename'];
    $parts = array_filter([$folderPath, $filename]);
    return $base . '/' . implode('/', $parts);
}

/** Get the field id for a handle. */
function field_id_for_handle(string $handle): ?int {
    static $cache = [];
    if (isset($cache[$handle])) return $cache[$handle];
    $stmt = db()->prepare("SELECT id FROM fields WHERE handle = ? LIMIT 1");
    $stmt->execute([$handle]);
    $id = $stmt->fetchColumn();
    $cache[$handle] = $id ? (int)$id : null;
    return $cache[$handle];
}

/** Get the category group id for a handle. */
function category_group_id_for_handle(string $handle): ?int {
    static $cache = [];
    if (isset($cache[$handle])) return $cache[$handle];

    try {
        $tableExists = db()->query("SHOW TABLES LIKE 'categorygroups'")->fetchColumn();
        if ($tableExists) {
            $stmt = db()->prepare("SELECT id FROM categorygroups WHERE handle = ? LIMIT 1");
            $stmt->execute([$handle]);
            $id = $stmt->fetchColumn();
            if ($id) {
                $cache[$handle] = (int)$id;
                return $cache[$handle];
            }
        }
    } catch (PDOException $e) {
        // Fall back to the stable group ids in the trimmed public database.
    }

    $knownGroups = [
        'postCategories' => 1,
        'projectTypes' => 2,
        'designSources' => 3,
        'projectFamilies' => 4,
    ];
    $cache[$handle] = $knownGroups[$handle] ?? null;
    return $cache[$handle];
}

/** Convert a raw DB row (with JSON content) into a hydrated post array. */
function hydrate_post(array $row): array {
    $content = json_decode($row['content'] ?? '{}', true) ?: [];
    $uids = field_uids();

    // Extract body text (PlainText field stored directly in content JSON by UID).
    // The body field UID may be stale (field was recreated at some point), so
    // if the current UID doesn't match, fall back to scanning for string values.
    $bodyUid = $uids['body'] ?? null;
    $body = '';
    if ($bodyUid && isset($content[$bodyUid]) && is_string($content[$bodyUid])) {
        $body = $content[$bodyUid];
    } elseif ($body === '') {
        // Fallback: scan content for string values (the body is the only
        // PlainText field stored directly as a string in the content JSON).
        // Skip known relation/table field UIDs (their values are arrays).
        foreach ($content as $uid => $value) {
            if (is_string($value) && $value !== '') {
                $body = $value;
                break;
            }
        }
    }

    // Extract resource links (Table field — array of {col1: label, col2: url}).
    // Same stale-UID fallback as body: if the current field UID doesn't match,
    // scan for array values with col1/col2 structure.
    $resourceLinksUid = $uids['resourceLinks'] ?? null;
    $resourceLinks = [];
    $resourceData = null;
    if ($resourceLinksUid && isset($content[$resourceLinksUid]) && is_array($content[$resourceLinksUid])) {
        $resourceData = $content[$resourceLinksUid];
    } else {
        foreach ($content as $uid => $value) {
            if (is_array($value) && !empty($value) && isset($value[0]['col1']) && isset($value[0]['col2'])) {
                $resourceData = $value;
                break;
            }
        }
    }
    if ($resourceData) {
        foreach ($resourceData as $row2) {
            if (!empty($row2['col2'])) {
                $resourceLinks[] = [
                    'label' => $row2['col1'] ?? '',
                    'url' => $row2['col2'],
                ];
            }
        }
    }

    return [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'slug' => $row['slug'],
        'postDate' => $row['postDate'],
        'body' => $body,
        'resourceLinks' => $resourceLinks,
        'featuredImage' => get_post_featured_image((int)$row['id']),
        'galleryImages' => get_post_gallery_images((int)$row['id']),
        'categories' => get_post_categories((int)$row['id'], 'postCategories'),
        'projectTypes' => get_post_categories((int)$row['id'], 'projectTypes'),
        'projectFamily' => get_post_categories((int)$row['id'], 'projectFamily'),
        'designSource' => (function() use ($row) {
            $cats = get_post_categories((int)$row['id'], 'designSource');
            return $cats[0] ?? null;
        })(),
    ];
}

/** Format a date string as "F j, Y". */
function format_date(?string $date): string {
    if (!$date) return '';
    $ts = strtotime($date);
    return date('F j, Y', $ts);
}
