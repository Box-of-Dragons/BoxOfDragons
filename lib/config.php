<?php
/* lib/config.php — site-wide configuration.
 *
 * Put per-install settings here (paths, URLs, defaults) so they don't get
 * buried in the query code. Constants are simpler than passing config arrays
 * around the templates.
 */

/** Base public URL for image uploads. Used in asset_url() for posts/gallery. */
define('UPLOADS_BASE_URL', '/uploads/posts');

/** Base filesystem path for image uploads (used if any server-side file checks are needed). */
define('UPLOADS_BASE_DIR', __DIR__ . '/../web/uploads/posts');
