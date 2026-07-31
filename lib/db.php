<?php
/* lib/db.php — PDO database connection for Box of Dragons.
 *
 * Reads DB_* vars from .env (or environment). Returns a shared PDO instance.
 * The database is the existing bod-db / DDEV db with the migrated Craft
 * content — we query the Craft tables directly (entries, elements_sites,
 * relations, categories, assets, etc.) until the content is migrated to
 * a clean schema.
 */

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('DB_NAME') ?: 'db';
    $user = getenv('DB_USER') ?: 'db';
    $pass = getenv('DB_PASSWORD') ?: 'db';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

/* Load .env into getenv() if not already loaded. Simple parser — no quoting
 * beyond stripping surrounding quotes. Called once at bootstrap. */
function load_env(string $path): void {
    if (!is_file($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        // Strip surrounding quotes
        if (strlen($value) >= 2 && ($value[0] === '"' && $value[-1] === '"')) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}
