<?php
/**
 * GenerateBuildInfo.php — Cross-platform build info generator.
 *
 * Mirrors the logic of GenerateBuildInfo.ps1:
 *   - Reads git tags and commit history
 *   - Derives a version from conventional commit messages (feat:, fix:, BREAKING CHANGE)
 *   - Non-feat/fix commits increment the revision (4th number)
 *   - Outputs a JS file (window.BUILD_INFO) or C# class
 *
 * Usage:
 *   php scripts/GenerateBuildInfo.php --root=. --output=web/js/buildInfo.js --format=js
 *   php scripts/GenerateBuildInfo.php --root=. --output=BuildInfo.cs --format=csharp
 *
 * Parameters:
 *   --root     Repository root path (required)
 *   --output   Output file path (required)
 *   --format   Output format: "js", "twig", "twiginfo", "html", or "csharp" (default: csharp)
 */

$options = getopt('', ['root:', 'output:', 'format:']);

$root = $options['root'] ?? null;
$outputPath = $options['output'] ?? null;
$format = $options['format'] ?? 'csharp';

if (!$root || !$outputPath) {
    fwrite(STDERR, "Usage: php scripts/GenerateBuildInfo.php --root=. --output=web/js/buildInfo.js --format=js\n");
    exit(1);
}

$root = realpath($root);
if (!$root || !is_dir($root)) {
    fwrite(STDERR, "Repository root not found: $root\n");
    exit(1);
}

function findGit(): string {
    // Check if git is already in PATH
    $testOutput = [];
    $testExit = 0;
    exec('git --version 2>&1', $testOutput, $testExit);
    if ($testExit === 0) {
        return 'git';
    }
    // Try common locations
    $candidates = ['/usr/local/bin/git', '/usr/bin/git', '/bin/git', '/usr/local/git/bin/git', '/opt/git/bin/git'];
    foreach ($candidates as $path) {
        if (file_exists($path) && is_executable($path)) {
            return $path;
        }
    }
    throw new RuntimeException('git binary not found in PATH or common locations');
}

function git(string $root, string ...$args): array {
    static $gitBin = null;
    if ($gitBin === null) {
        $gitBin = findGit();
    }
    $escapedRoot = escapeshellarg($root);
    $escapedArgs = array_map('escapeshellarg', $args);
    $cmd = "$gitBin -C $escapedRoot " . implode(' ', $escapedArgs) . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("git " . implode(' ', $args) . " failed (exit $exitCode): " . implode("\n", $output));
    }
    return $output;
}

function parseVersion(string $tagName): array {
    $raw = ltrim(trim($tagName), 'vV');
    $parts = explode('.', $raw);
    if (count($parts) < 3) {
        throw new RuntimeException("Tag '$tagName' is not a valid version");
    }
    return array_map('intval', $parts);
}

function formatVersion(array $version, int $revision = 0): string {
    if ($revision > 0) {
        return sprintf('v%d.%d.%d.%d', $version[0], $version[1], $version[2], $revision);
    }
    return sprintf('v%d.%d.%d', $version[0], $version[1], $version[2]);
}

function tryParseTaggedVersion(string $tagName): ?array {
    $raw = ltrim(trim($tagName), 'vV');
    $parts = explode('.', $raw);
    if (count($parts) < 3) {
        return null;
    }
    foreach ($parts as $p) {
        if (!ctype_digit($p)) {
            return null;
        }
    }
    $version = array_map('intval', $parts);
    if (count($version) === 3) {
        $version[] = 0;
    }
    return $version;
}

function getCommitType(string $subject): string {
    if (preg_match('/BREAKING CHANGE|!:/', $subject)) {
        return 'major';
    }
    if (preg_match('/^feat(\([^)]+\))?:/', $subject)) {
        return 'minor';
    }
    if (preg_match('/^fix(\([^)]+\))?:/', $subject)) {
        return 'patch';
    }
    return 'none';
}

function getChangelogGroup(string $subject): string {
    if (preg_match('/BREAKING CHANGE|!:/', $subject)) {
        return 'breaking';
    }
    if (preg_match('/^feat(\([^)]+\))?:/', $subject)) {
        return 'feature';
    }
    if (preg_match('/^fix(\([^)]+\))?:/', $subject)) {
        return 'fix';
    }
    if (preg_match('/^docs(\([^)]+\))?:/', $subject)) {
        return 'docs';
    }
    if (preg_match('/^refactor(\([^)]+\))?:/', $subject)) {
        return 'refactor';
    }
    if (preg_match('/^test(\([^)]+\))?:/', $subject)) {
        return 'test';
    }
    if (preg_match('/^chore(\([^)]+\))?:/', $subject)) {
        return 'chore';
    }
    return 'other';
}

function humanizeCommitSubject(string $subject): string {
    $summary = preg_replace('/^(?:[a-z]+(?:\([^)]+\))?:\s*|BREAKING CHANGE:?\s*)/i', '', $subject);
    $summary = trim((string)$summary);
    if ($summary === '') {
        return $subject;
    }
    return strtoupper($summary[0]) . substr($summary, 1);
}

function cleanCommitDescription(string $subject, string $body): string|array|null {
    $body = trim($body);
    if ($body === '') {
        return null;
    }

    $lines = preg_split('/\R+/', $body) ?: [];

    // Detect bullet-style body lines
    $hasBullets = false;
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || preg_match('/^(Signed-off-by:|Co-authored-by:|Reviewed-by:|Acked-by:)/i', $trimmed)) {
            continue;
        }
        if (preg_match('/^[-*]\s/', $trimmed)) {
            $hasBullets = true;
            break;
        }
    }

    if ($hasBullets) {
        $bullets = [];
        $currentBullet = null;

        $processCurrentBullet = function () use (&$bullets, &$currentBullet, $subject) {
            if ($currentBullet === null) {
                return;
            }
            $trimmed = trim(preg_replace('/\s+/', ' ', $currentBullet) ?? '');
            if ($trimmed === '') {
                $currentBullet = null;
                return;
            }
            if (preg_match('/^(?:[a-z]+(?:\([^)]+\))?:\s*|BREAKING CHANGE:?\s*)/i', $trimmed, $match)) {
                $summary = trim(substr($trimmed, strlen($match[0])));
                if ($summary !== '') {
                    $trimmed = $summary;
                }
            }
            $summaryPrefix = preg_replace('/^(?:[a-z]+(?:\([^)]+\))?:\s*|BREAKING CHANGE:?\s*)/i', '', $subject);
            $summaryPrefix = trim((string)$summaryPrefix);
            if ($summaryPrefix !== '' && strncasecmp($trimmed, $summaryPrefix, strlen($summaryPrefix)) === 0) {
                $trimmed = trim(substr($trimmed, strlen($summaryPrefix)));
            }
            if ($trimmed !== '') {
                $bullets[] = $trimmed;
            }
            $currentBullet = null;
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || preg_match('/^(Signed-off-by:|Co-authored-by:|Reviewed-by:|Acked-by:)/i', $trimmed)) {
                $processCurrentBullet();
                continue;
            }
            if (preg_match('/^[-*]\s+/', $line)) {
                // New bullet
                $processCurrentBullet();
                $currentBullet = preg_replace('/^[-*]\s+/', '', $trimmed);
            } elseif ($currentBullet !== null && preg_match('/^\s+/', $line)) {
                // Continuation of the current bullet
                $currentBullet .= ' ' . $trimmed;
            } else {
                // Standalone line with no preceding bullet
                $processCurrentBullet();
                $currentBullet = $trimmed;
            }
        }
        $processCurrentBullet();

        return $bullets ?: null;
    }

    $paragraphs = [];
    $current = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            if ($current) {
                $paragraphs[] = implode(' ', $current);
                $current = [];
            }
            continue;
        }
        if (preg_match('/^(Signed-off-by:|Co-authored-by:|Reviewed-by:|Acked-by:)/i', $trimmed)) {
            continue;
        }
        $trimmed = preg_replace('/^-\s*/', '', $trimmed);
        $trimmed = preg_replace('/^\*\s*/', '', $trimmed);
        $current[] = $trimmed;
    }

    if ($current) {
        $paragraphs[] = implode(' ', $current);
    }

    foreach ($paragraphs as $paragraph) {
        $paragraph = trim(preg_replace('/\s+/', ' ', $paragraph) ?? '');
        if ($paragraph !== '') {
            if (preg_match('/^(?:[a-z]+(?:\([^)]+\))?:\s*|BREAKING CHANGE:?\s*)/i', $paragraph, $match)) {
                $summary = trim(substr($paragraph, strlen($match[0])));
                if ($summary !== '') {
                    $paragraph = $summary;
                }
            }
            $summaryPrefix = preg_replace('/^(?:[a-z]+(?:\([^)]+\))?:\s*|BREAKING CHANGE:?\s*)/i', '', $subject);
            $summaryPrefix = trim((string)$summaryPrefix);
            if ($summaryPrefix !== '' && strncasecmp($paragraph, $summaryPrefix, strlen($summaryPrefix)) === 0) {
                $paragraph = trim(substr($paragraph, strlen($summaryPrefix)));
            }
            if ($paragraph === '') {
                continue;
            }
            return $paragraph;
        }
    }

    return null;
}

function escapeHtml(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function escapeTwigText(string $value): string {
    return str_replace(
        ['{{', '}}', '{%', '%}', '{#', '#}'],
        ['', '', '', '', '', ''],
        $value
    );
}

// Commit count
$commitCountOutput = git($root, 'rev-list', '--count', 'HEAD');
$commitCount = (int)trim($commitCountOutput[0] ?? '0');

// Tags with object hashes
$tagOutput = git($root, 'tag', '--format=%(objectname)|%(refname:short)');
$taggedVersions = [];
foreach ($tagOutput as $line) {
    if (trim($line) === '') continue;
    $parts = explode('|', $line, 2);
    if (count($parts) !== 2) continue;
    $version = tryParseTaggedVersion($parts[1]);
    if ($version !== null) {
        $taggedVersions[$parts[0]] = $version;
    }
}

// Commit log (oldest first)
$logOutput = git($root, 'log', '--pretty=format:%H%x1f%ad%x1f%s%x1f%B%x1e', '--date=short', '--reverse', '--', '.');
$resolvedVersion = [1, 0, 0, 0];
$revision = 0;
$changeGroups = [
    'breaking' => [],
    'feature' => [],
    'fix' => [],
    'docs' => [],
    'refactor' => [],
    'test' => [],
    'chore' => [],
    'other' => [],
];

foreach (preg_split('/\x1e/', implode("\n", $logOutput)) ?: [] as $record) {
    if (trim($record) === '') {
        continue;
    }
    $parts = explode("\x1f", $record, 4);
    if (count($parts) !== 4) {
        continue;
    }

    [$sha, $date, $subject, $body] = $parts;

    if (isset($taggedVersions[$sha])) {
        $resolvedVersion = $taggedVersions[$sha];
        if (count($resolvedVersion) < 4) {
            $resolvedVersion[] = 0;
        }
        $revision = 0;
        continue;
    }

    $commitType = getCommitType($subject);
    switch ($commitType) {
        case 'major':
            $resolvedVersion = [$resolvedVersion[0] + 1, 0, 0, 0];
            $revision = 0;
            break;
        case 'minor':
            $resolvedVersion = [$resolvedVersion[0], $resolvedVersion[1] + 1, 0, 0];
            $revision = 0;
            break;
        case 'patch':
            $resolvedVersion = [$resolvedVersion[0], $resolvedVersion[1], $resolvedVersion[2] + 1, 0];
            $revision = 0;
            break;
        default:
            $revision++;
            break;
    }

    $group = getChangelogGroup($subject);
    $changeGroups[$group][] = [
        'version' => formatVersion($resolvedVersion, $revision),
        'majorVersion' => $resolvedVersion[0],
        'sha' => substr($sha, 0, 7),
        'date' => $date,
        'subject' => humanizeCommitSubject($subject),
        'description' => cleanCommitDescription($subject, $body),
    ];
}

$displayVersion = formatVersion($resolvedVersion, $revision);

// Latest tag for production version
$latestTag = null;
try {
    $tagList = git($root, 'tag', '--list', 'v[0-9]*.[0-9]*.[0-9]*', '--sort=-v:refname');
    $latestTag = trim($tagList[0] ?? '');
} catch (Throwable $e) {
    $latestTag = '';
}

$productionVersion = $latestTag !== '' ? formatVersion(parseVersion($latestTag)) : $displayVersion;

// Short SHA
$shaOutput = git($root, 'rev-parse', '--short', 'HEAD');
$shortSha = trim($shaOutput[0] ?? '');

// Generate output
if ($format === 'js') {
    $content = <<<JS
window.BUILD_INFO = {
  version: "$displayVersion",
  productionVersion: "$productionVersion",
  commit: "$shortSha",
  commitCount: "$commitCount"
};
JS;
} elseif ($format === 'twiginfo') {
    // Small Twig partial that renders the build version string server-side.
    // Consumed via {{ include('_generated/build-info.twig') }} so the footer
    // version always matches the server-rendered changelog and never depends
    // on a browser-cached JS file. Twig include() does not share variables
    // back to the caller, so the partial renders the final string directly.
    $content = "{# Auto-generated by scripts/GenerateBuildInfo.php. Do not edit. #}\n"
        . escapeTwigText($displayVersion) . ' (' . escapeTwigText($shortSha) . ")\n";
} elseif ($format === 'twig') {
    $groupLabels = [
        'breaking' => 'Breaking changes',
        'feature' => 'Features',
        'fix' => 'Fixes',
        'docs' => 'Documentation',
        'refactor' => 'Refactors',
        'test' => 'Tests',
        'chore' => 'Maintenance',
        'other' => 'Other changes',
    ];
    $groupChips = [
        'breaking' => 'rose',
        'feature' => 'gold',
        'fix' => 'sage',
        'docs' => 'sky',
        'refactor' => 'stone',
        'test' => 'plum',
        'chore' => 'olive',
        'other' => 'ink',
    ];

    // Determine which major versions exist in the change groups
    $majorVersions = [];
    foreach ($changeGroups as $items) {
        foreach ($items as $item) {
            $majorVersions[$item['majorVersion']] = true;
        }
    }
    ksort($majorVersions);
    $majorVersions = array_keys($majorVersions);
    if (empty($majorVersions)) {
        $majorVersions = [1];
    }
    $currentMajor = end($majorVersions);

    // Render tabs newest major first so the current version is the first tab.
    $majorVersions = array_reverse($majorVersions);

    $content = '';
    $content .= "<div class=\"changelog-tabs\" data-changelog-tabs>\n";
    $content .= "  <div class=\"changelog-tablist\" role=\"tablist\">\n";
    foreach ($majorVersions as $mv) {
        $tabId = 'changelog-v' . $mv;
        $panelId = 'changelog-panel-v' . $mv;
        $isActive = ($mv === $currentMajor) ? ' active' : '';
        $content .= '    <button class="changelog-tab' . $isActive . '" role="tab" data-tab="' . escapeHtml($tabId) . '" data-panel="' . escapeHtml($panelId) . '" aria-controls="' . escapeHtml($panelId) . '" aria-selected="' . ($mv === $currentMajor ? 'true' : 'false') . '">v' . escapeHtml((string)$mv) . "</button>\n";
    }
    $content .= "  </div>\n";

    foreach ($majorVersions as $mv) {
        $panelId = 'changelog-panel-v' . $mv;
        $isActive = ($mv === $currentMajor) ? ' active' : '';
        $content .= '  <div class="changelog-tabpanel' . $isActive . '" id="' . escapeHtml($panelId) . '" role="tabpanel">' . "\n";
        $content .= "    <div class=\"container-sections\">\n";
        $content .= "      <section class=\"panel panel--padded\">\n";
        $content .= "        <h2>Build Snapshot</h2>\n";
        $content .= "        <div class=\"container-actions\">\n";
        $content .= '          <span class="chip color-pair-ink">Version ' . escapeHtml($displayVersion) . "</span>\n";
        $content .= '          <span class="chip color-pair-stone">' . escapeHtml((string)$commitCount) . " commits</span>\n";
        $content .= "        </div>\n";
        $content .= "        <p class=\"body\">Generated from conventional commits and git tags during the site build.</p>\n";
        $content .= "      </section>\n";

        // Build Change Types nav for this major version
        $content .= "      <section class=\"panel panel--padded\">\n";
        $content .= "        <h2>Change Types</h2>\n";
        $content .= "        <nav class=\"container-actions\" aria-label=\"Change log sections\">\n";
        foreach ($groupLabels as $groupKey => $groupLabel) {
            $items = array_filter($changeGroups[$groupKey] ?? [], fn($i) => $i['majorVersion'] === $mv);
            if (!$items) {
                continue;
            }
            $sectionId = $groupKey . '-changes-v' . $mv;
            $chipColor = $groupChips[$groupKey] ?? 'ink';
            $content .= '          <a class="chip color-pair-' . escapeHtml($chipColor) . '" href="#' . escapeHtml($sectionId) . '">' . escapeHtml($groupLabel) . "</a>\n";
        }
        $content .= "        </nav>\n";
        $content .= "      </section>\n";

        foreach ($groupLabels as $groupKey => $groupLabel) {
            $items = array_filter($changeGroups[$groupKey] ?? [], fn($i) => $i['majorVersion'] === $mv);
            if (!$items) {
                continue;
            }
            $sectionId = $groupKey . '-changes-v' . $mv;

            $content .= '      <section class="panel panel--padded" id="' . escapeHtml($sectionId) . "\">\n";
            $content .= '        <h3>' . escapeHtml($groupLabel) . "</h3>\n";
            $content .= "        <ul class=\"list\">\n";
            foreach (array_reverse($items) as $item) {
                $chipColor = $groupChips[$groupKey] ?? 'ink';
                $content .= "          <li>\n";
                $content .= "            <div class=\"container-content\">\n";
                $content .= "              <div class=\"container-actions\">\n";
                $content .= '              <span class="chip color-pair-' . escapeHtml($chipColor) . '">' . escapeHtml($groupLabel) . "</span>\n";
                $content .= '                <span class="caption">' . escapeHtml($item['version']) . ' - ' . escapeHtml($item['sha']) . ' - ' . escapeHtml($item['date']) . "</span>\n";
                $content .= "              </div>\n";
                $content .= '              <h4>' . escapeHtml(escapeTwigText($item['subject'])) . "</h4>\n";
                if (!empty($item['description'])) {
                    $content .= "              <ul>\n";
                    if (is_array($item['description'])) {
                        foreach ($item['description'] as $bullet) {
                            $content .= '                <li>' . escapeHtml(escapeTwigText($bullet)) . "</li>\n";
                        }
                    } else {
                        $content .= '                <li>' . escapeHtml(escapeTwigText($item['description'])) . "</li>\n";
                    }
                    $content .= "              </ul>\n";
                }
                $content .= "            </div>\n";
                $content .= "          </li>\n";
            }
            $content .= "        </ul>\n";
            $content .= "      </section>\n";
        }

        $content .= "    </div>\n";
        $content .= "  </div>\n";
    }

    $content .= "</div>\n";
} elseif ($format === 'html') {
    $groupLabels = [
        'breaking' => 'Breaking changes',
        'feature' => 'Features',
        'fix' => 'Fixes',
        'docs' => 'Documentation',
        'refactor' => 'Refactors',
        'test' => 'Tests',
        'chore' => 'Maintenance',
        'other' => 'Other changes',
    ];
    $groupChips = [
        'breaking' => 'red',
        'feature' => 'gold',
        'fix' => 'sage',
        'docs' => 'sky',
        'refactor' => 'stone',
        'test' => 'plum',
        'chore' => 'olive',
        'other' => 'ink',
    ];

    $htmlBody = '';
    $htmlBody .= "<section class=\"panel panel--padded\">\n";
    $htmlBody .= "  <h2>Build Snapshot</h2>\n";
    $htmlBody .= "  <div class=\"container-actions\">\n";
    $htmlBody .= '    <span class="chip color-pair-ink">Version ' . escapeHtml($displayVersion) . "</span>\n";
    $htmlBody .= '    <span class="chip color-pair-stone">' . escapeHtml((string)$commitCount) . " commits</span>\n";
    $htmlBody .= "  </div>\n";
    $htmlBody .= "  <p class=\"body\">Generated from conventional commits and git tags during the site build.</p>\n";
    $htmlBody .= "</section>\n";

    // Collect unique versions and sort descending (newest first)
    $versionMap = [];
    foreach ($changeGroups as $items) {
        foreach ($items as $item) {
            $versionMap[$item['version']] = true;
        }
    }
    $sortedVersions = array_keys($versionMap);
    usort($sortedVersions, function (string $a, string $b): int {
        $aParts = array_map('intval', explode('.', ltrim($a, 'vV')));
        $bParts = array_map('intval', explode('.', ltrim($b, 'vV')));
        for ($i = 0; $i < max(count($aParts), count($bParts)); $i++) {
            $av = $aParts[$i] ?? 0;
            $bv = $bParts[$i] ?? 0;
            if ($av !== $bv) return $bv <=> $av;
        }
        return 0;
    });

    // Sidebar: Change Types (chips) + Versions (list)
    $htmlSidebar = '';
    $htmlSidebar .= "<div class=\"changelog-types\">\n";
    $htmlSidebar .= "  <section class=\"container-section--headed\">\n";
    $htmlSidebar .= "    <div class=\"container-section-header\">Change Types</div>\n";
    $htmlSidebar .= "    <div class=\"container-section-body\">\n";
    $htmlSidebar .= "      <nav class=\"container-actions\" aria-label=\"Changelog sections\">\n";
    foreach ($groupLabels as $groupKey => $groupLabel) {
        if (empty($changeGroups[$groupKey])) continue;
        $chipColor = $groupChips[$groupKey] ?? 'ink';
        $htmlSidebar .= '        <a class="chip color-pair-' . escapeHtml($chipColor) . '" href="#' . escapeHtml($groupKey) . '-changes">' . escapeHtml($groupLabel) . "</a>\n";
    }
    $htmlSidebar .= "      </nav>\n";
    $htmlSidebar .= "    </div>\n";
    $htmlSidebar .= "  </section>\n";
    $htmlSidebar .= "</div>\n";

    $htmlSidebar .= "<div class=\"changelog-versions\">\n";
    $htmlSidebar .= "  <section class=\"container-section--headed\">\n";
    $htmlSidebar .= "    <div class=\"container-section-header\">Versions</div>\n";
    $htmlSidebar .= "    <div class=\"container-section-body\">\n";
    $htmlSidebar .= "      <ul class=\"list\">\n";
    foreach ($sortedVersions as $version) {
        $htmlSidebar .= '        <li><a href="#' . escapeHtml($version) . '"><span class="caption">' . escapeHtml($version) . "</span></a></li>\n";
    }
    $htmlSidebar .= "      </ul>\n";
    $htmlSidebar .= "    </div>\n";
    $htmlSidebar .= "  </section>\n";
    $htmlSidebar .= "</div>\n";

    $anchoredVersions = [];
    foreach ($groupLabels as $groupKey => $groupLabel) {
        if (empty($changeGroups[$groupKey])) continue;
        $sectionId = $groupKey . '-changes';
        $chipColor = $groupChips[$groupKey] ?? 'ink';
        $htmlBody .= '<section class="panel panel--padded" id="' . escapeHtml($sectionId) . '">' . "\n";
        $htmlBody .= '  <h3>' . escapeHtml($groupLabel) . "</h3>\n";
        $htmlBody .= "  <ul class=\"list\">\n";
        foreach (array_reverse($changeGroups[$groupKey]) as $item) {
            $liId = !isset($anchoredVersions[$item['version']]) ? ' id="' . escapeHtml($item['version']) . '"' : '';
            if ($liId !== '') $anchoredVersions[$item['version']] = true;
            $htmlBody .= '    <li' . $liId . ">\n";
            $htmlBody .= "      <div class=\"container-actions\">\n";
            $htmlBody .= '        <span class="chip color-pair-' . escapeHtml($chipColor) . '">' . escapeHtml($groupLabel) . "</span>\n";
            $htmlBody .= '        <span class="caption">' . escapeHtml($item['version']) . ' - ' . escapeHtml($item['sha']) . ' - ' . escapeHtml($item['date']) . "</span>\n";
            $htmlBody .= "      </div>\n";
            $htmlBody .= '      <h4>' . escapeHtml(escapeTwigText($item['subject'])) . "</h4>\n";
            if (!empty($item['description'])) {
                $htmlBody .= "      <ul>\n";
                if (is_array($item['description'])) {
                    foreach ($item['description'] as $bullet) {
                        $htmlBody .= '        <li>' . escapeHtml(escapeTwigText($bullet)) . "</li>\n";
                    }
                } else {
                    $htmlBody .= '        <li>' . escapeHtml(escapeTwigText($item['description'])) . "</li>\n";
                }
                $htmlBody .= "      </ul>\n";
            }
            $htmlBody .= "    </li>\n";
        }
        $htmlBody .= "  </ul>\n";
        $htmlBody .= "</section>\n";
    }

    $content = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Changelog - Box of Dragons</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@700&family=Open+Sans:wght@400;600;700&family=Playfair+Display:wght@400;600&display=swap" rel="stylesheet">
    <script>(function(){var h=location.hostname;var l=h==='localhost'||h==='127.0.0.1'||h.indexOf('.ddev.site')!==-1;var b=(l?'http://localhost:4000':'https://misssponto.me.uk');document.write('<link rel="stylesheet" href="'+b+'/css/shared.css">');})();</script>
    <link rel="stylesheet" href="/css/site.css">
</head>
<body>
    <div id="global-bar" data-active="box-of-dragons"></div>
    <script src="https://misssponto.me.uk/js/global-bar.js" defer></script>
    <header class="site-header">
        <div class="shell header-row">
            <h1 class="brand">Box of Dragons</h1>
            <nav class="main-nav" aria-label="Main navigation">
                <div class="main-nav-item"><a class="main-nav-link" href="/">Projects</a></div>
                <div class="main-nav-item"><a class="main-nav-link active" href="/changelog.html">Changelog</a></div>
            </nav>
            <div class="header-project-links" aria-label="Project links">
                <a class="project-link" href="https://github.com/Box-of-Dragons/CraftCms" target="_blank" rel="noopener noreferrer" aria-label="Open the project on GitHub">GitHub</a>
                <a class="project-link" href="https://gitlab.com/structured-chaos/craftcms" target="_blank" rel="noopener noreferrer" aria-label="Open the project on GitLab">GitLab</a>
            </div>
        </div>
    </header>
    <section class="page-subheader">
        <div class="shell">
            <div class="page-subheader-inner">
                <h1>Changelog</h1>
            </div>
        </div>
    </section>
    <main class="shell page-layout">
        <div class="content-main">
$htmlBody
        </div>
        <aside class="sidebar sidebar--sticky">
            <div class="container-sections">
                <section class="container-section--headed">
                    <div class="container-section-header">About this log</div>
                    <div class="container-section-body">
                        <div class="body">This page is generated from conventional commits and git tags during the build step. The latest build snapshot, production version, commit count, and recent commits grouped into simple readable sections.</div>
                    </div>
                </section>
$htmlSidebar
            </div>
        </aside>
    </main>
    <footer class="site-footer">
        <div class="shell footer-row">
            <div>Box of Dragons</div>
        </div>
    </footer>
</body>
</html>
HTML;

} else {
    $namespace = 'Mudblazer.Build';
    $content = <<<CS
namespace $namespace;

public static class BuildInfo
{
    public const string Version = "$displayVersion";
    public const string ProductionVersion = "$productionVersion";
    public const string Commit = "$shortSha";
    public const string CommitCount = "$commitCount";
}
CS;
}

// Write output
$outputDir = dirname($outputPath);
if ($outputDir !== '' && !is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

file_put_contents($outputPath, $content);
fwrite(STDOUT, "Generated $outputPath ($format) — version $displayVersion, commit $shortSha\n");

