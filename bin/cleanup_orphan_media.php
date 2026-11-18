#!/usr/bin/env php
<?php
/**
 * Delete local assetsNew media files whose basename is not referenced
 * in any known media DB column (orphans left by deleted posts / failed uploads).
 *
 * Never touches admin UI assets (plugins, dist, css, library, etc.).
 * Never deletes default.* placeholders.
 *
 *   php bin/cleanup_orphan_media.php --dry-run
 *   php bin/cleanup_orphan_media.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

@ini_set('max_execution_time', '0');
@set_time_limit(0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('BASEPATH', ROOTPATH . 'system' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public_html' . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'application' . DIRECTORY_SEPARATOR);

require_once ROOTPATH . 'private' . DIRECTORY_SEPARATOR . 'load_env.php';
advpost_load_env(ROOTPATH);
define('ENVIRONMENT', advpost_resolve_environment());

$dry_run = in_array('--dry-run', $argv, true);

require APPPATH . 'config/database.php';
$dbcfg = $db['default'];
$host = $dbcfg['hostname'] === 'localhost' ? '127.0.0.1' : $dbcfg['hostname'];
$mysqli = @new mysqli($host, $dbcfg['username'], $dbcfg['password'], $dbcfg['database']);
if ($mysqli->connect_error) {
    fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

/** Media dirs only — never admin UI trees.
 *  Keep assetsNew/images/logo (site_setup branding) out of orphan cleanup dirs.
 */
$MEDIA_DIRS = [
    'assetsNew/images/profile_pic',
    'assetsNew/images/avtar',
    'assetsNew/images/avatar',
    'assetsNew/images/new_post',
    'assetsNew/images/story_image',
    'assetsNew/images/story_cover_pic',
    'assetsNew/images/chat',
    'assetsNew/images/chat_thumbnails',
    'assetsNew/images/message',
    'assetsNew/images/product',
    'assetsNew/images/post_video',
    'assetsNew/videos/post_video',
    'assetsNew/videos/post_video_thumbnails',
    'assetsNew/videos/reel_video',
    'assetsNew/videos/reel_video_thumbnails',
    'assetsNew/videos/reels',
    'assetsNew/videos/message',
];

$targets = [
    'post_image' => ['new_post'],
    'post_video' => ['post_video', 'post_video_hls', 'post_video_thumbnail'],
    'reels_video' => ['reel_video', 'reel_video_thumbnail'],
    'story' => ['url', 'video_thumbnail', 'hls_url', 'preview_video'],
    'story_highlight' => ['cover_pic'],
    'users' => ['profile_pic', 'logo_url'],
    'chats' => ['url', 'video_thumbnail'],
    'products' => ['images'],
    'image_creation_jobs' => ['product_image_url', 'logo_url', 'output_image_url', 'output_file_path'],
    'video_creation_jobs' => ['output_video_url', 'output_file_path', 'product_image_url'],
    'avtar' => ['image'],
    'music' => ['image'],
    'admins' => ['profile_pic'],
    'site_setup' => ['dark_logo', 'light_logo', 'fav_icon', 'banner_image', 'splash_image'],
];

function table_exists(mysqli $db, $table)
{
    $t = $db->real_escape_string($table);
    $r = $db->query("SHOW TABLES LIKE '{$t}'");
    return $r && $r->num_rows > 0;
}

function col_exists(mysqli $db, $table, $col)
{
    $t = $db->real_escape_string($table);
    $c = $db->real_escape_string($col);
    $r = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    return $r && $r->num_rows > 0;
}

function is_protected_name($name)
{
    $base = strtolower(basename((string) $name));
    return $base === '' || preg_match('/^default(\.|$)/', $base) === 1;
}

function basename_from_value($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    $path = parse_url($value, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = $value;
    }
    $base = basename($path);
    return ($base === '' || $base === '.' || $base === '/') ? '' : $base;
}

$referenced = [];
foreach ($targets as $table => $cols) {
    if (!table_exists($mysqli, $table)) {
        continue;
    }
    $existing = [];
    foreach ($cols as $col) {
        if (col_exists($mysqli, $table, $col)) {
            $existing[] = $col;
        }
    }
    if (empty($existing)) {
        continue;
    }

    $select = '`' . implode('`,`', $existing) . '`';
    $result = $mysqli->query("SELECT {$select} FROM `{$table}`");
    if (!$result) {
        continue;
    }
    while ($row = $result->fetch_assoc()) {
        foreach ($existing as $col) {
            $raw = isset($row[$col]) ? trim((string) $row[$col]) : '';
            if ($raw === '') {
                continue;
            }
            $parts = ($table === 'products' && $col === 'images')
                ? array_map('trim', explode(',', $raw))
                : [$raw];
            foreach ($parts as $part) {
                $b = basename_from_value($part);
                if ($b !== '') {
                    $referenced[$b] = true;
                }
            }
        }
    }
}

$mysqli->close();

$stats = [
    'referenced_basenames' => count($referenced),
    'scanned' => 0,
    'orphans' => 0,
    'deleted' => 0,
    'protected_skipped' => 0,
    'failed' => 0,
    'bytes' => 0,
    'errors' => [],
];

echo ($dry_run ? "[DRY RUN] " : "[LIVE] ") . "Cleanup orphan local media (not referenced in DB)\n";
echo "Referenced basenames in DB: " . count($referenced) . "\n\n";

$examples = [];

foreach ($MEDIA_DIRS as $dir) {
    $absDir = FCPATH . $dir;
    if (!is_dir($absDir)) {
        continue;
    }
    $dh = opendir($absDir);
    if (!$dh) {
        continue;
    }
    while (($file = readdir($dh)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $abs = $absDir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($abs)) {
            continue;
        }
        $stats['scanned']++;
        $rel = $dir . '/' . $file;

        if (is_protected_name($file)) {
            $stats['protected_skipped']++;
            continue;
        }
        if (isset($referenced[$file])) {
            continue;
        }

        $stats['orphans']++;
        $size = (int) @filesize($abs);
        $stats['bytes'] += $size;
        if (count($examples) < 40) {
            $examples[] = $rel . ' (' . round($size / 1024) . ' KB)';
        }

        if ($dry_run) {
            $stats['deleted']++;
            continue;
        }

        if (@unlink($abs)) {
            $stats['deleted']++;
        } else {
            $stats['failed']++;
            if (count($stats['errors']) < 30) {
                $stats['errors'][] = "unlink failed: {$rel}";
            }
        }
    }
    closedir($dh);
}

echo "Orphan examples:\n";
foreach ($examples as $ex) {
    echo "  {$ex}\n";
}
if ($stats['orphans'] > count($examples)) {
    echo "  ... and " . ($stats['orphans'] - count($examples)) . " more\n";
}

echo "\n" . json_encode([
    'status' => 'success',
    'dry_run' => $dry_run,
    'stats' => $stats,
    'approx_mb' => round($stats['bytes'] / 1048576, 1),
], JSON_PRETTY_PRINT) . "\n";
