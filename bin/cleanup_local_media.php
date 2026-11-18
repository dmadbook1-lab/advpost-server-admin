#!/usr/bin/env php
<?php
/**
 * Delete local assetsNew media files that are already:
 *   1) referenced in the DB as an S3 URL, and
 *   2) present on the S3 bucket (HEAD succeeds).
 *
 * Never touches admin UI assets (plugins, dist, css, library, ajax_js, style.css, top-level logo/).
 * Never deletes default.* placeholders.
 *
 *   php bin/cleanup_local_media.php --dry-run
 *   php bin/cleanup_local_media.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

@ini_set('max_execution_time', '0');
@set_time_limit(0);
@ini_set('memory_limit', '512M');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('BASEPATH', ROOTPATH . 'system' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public_html' . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'application' . DIRECTORY_SEPARATOR);

require_once ROOTPATH . 'private' . DIRECTORY_SEPARATOR . 'load_env.php';
advpost_load_env(ROOTPATH);
define('ENVIRONMENT', advpost_resolve_environment());

$dry_run = in_array('--dry-run', $argv, true);
$skip_s3_check = in_array('--skip-s3-check', $argv, true); // unsafe; kept for offline testing only

require APPPATH . 'config/database.php';
$dbcfg = $db['default'];

$host = $dbcfg['hostname'] === 'localhost' ? '127.0.0.1' : $dbcfg['hostname'];
$mysqli = @new mysqli($host, $dbcfg['username'], $dbcfg['password'], $dbcfg['database']);
if ($mysqli->connect_error) {
    fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

require APPPATH . 'config/aws.php';
$access = $config['access_key_id'] ?? '';
$secret = $config['secret_access_key'] ?? '';
$bucket = $config['bucket'] ?? '';
$region = $config['region'] ?? 'ap-south-1';
$base_url = rtrim($config['url'] ?? ('https://' . $bucket . '.s3.' . $region . '.amazonaws.com'), '/');

$res = $mysqli->query('SELECT aws_access_key_id, aws_secret_access_key, aws_bucket, aws_url FROM aws_credential ORDER BY id DESC LIMIT 1');
if ($res && ($row = $res->fetch_assoc())) {
    if (!empty($row['aws_access_key_id']) && !empty($row['aws_secret_access_key']) && !empty($row['aws_bucket'])) {
        $access = trim($row['aws_access_key_id']);
        $secret = trim($row['aws_secret_access_key']);
        $bucket = trim($row['aws_bucket']);
        if (!empty($row['aws_url'])) {
            $base_url = rtrim(trim($row['aws_url']), '/');
        }
    }
}

if ($access === '' || $secret === '' || $bucket === '') {
    fwrite(STDERR, "S3 credentials missing (aws.php / aws_credential)\n");
    exit(1);
}

/** Media dirs only — never admin UI trees */
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
    'post_image' => ['id', ['new_post']],
    'post_video' => ['id', ['post_video', 'post_video_hls', 'post_video_thumbnail']],
    'reels_video' => ['id', ['reel_video', 'reel_video_thumbnail']],
    'story' => ['story_id', ['url', 'video_thumbnail', 'hls_url', 'preview_video']],
    'story_highlight' => ['highlight_id', ['cover_pic']],
    'users' => ['id', ['profile_pic', 'logo_url']],
    'chats' => ['id', ['url', 'video_thumbnail']],
    'products' => ['id', ['images']],
    'image_creation_jobs' => ['id', ['product_image_url', 'logo_url', 'output_image_url', 'output_file_path']],
    'video_creation_jobs' => ['id', ['output_video_url', 'output_file_path', 'product_image_url']],
    'avtar' => ['id', ['image']],
    'music' => ['id', ['image']],
    'admins' => ['id', ['profile_pic']],
];

function is_protected_name($name)
{
    $base = strtolower(basename((string) $name));
    return $base === '' || preg_match('/^default(\.|$)/', $base) === 1;
}

function extract_s3_key($value, $base_url)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    // Full S3 / CloudFront-style URL
    if (preg_match('#^https?://#i', $value)) {
        if (stripos($value, 'amazonaws.com') === false && stripos($value, 's3.') === false
            && stripos($value, rtrim($base_url, '/')) === false) {
            return '';
        }
        $path = parse_url($value, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path === '/') {
            return '';
        }
        return ltrim(rawurldecode($path), '/');
    }

    // Relative canonical keys already migrated-style
    if (strpos($value, 'users/') === 0 || strpos($value, 'assetsNew/') === 0) {
        return ltrim($value, '/');
    }

    return '';
}

function s3_head($access, $secret, $bucket, $region, $key)
{
    $payload_hash = hash('sha256', '');
    $amz_date = gmdate('Ymd\THis\Z');
    $date_stamp = gmdate('Ymd');
    $host = $bucket . '.s3.' . $region . '.amazonaws.com';
    $canonical_uri = '/' . str_replace('%2F', '/', rawurlencode(ltrim($key, '/')));

    $canonical_headers =
        "host:{$host}\n" .
        "x-amz-content-sha256:{$payload_hash}\n" .
        "x-amz-date:{$amz_date}\n";
    $signed_headers = 'host;x-amz-content-sha256;x-amz-date';

    $canonical_request = "HEAD\n{$canonical_uri}\n\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
    $credential_scope = "{$date_stamp}/{$region}/s3/aws4_request";
    $string_to_sign = "AWS4-HMAC-SHA256\n{$amz_date}\n{$credential_scope}\n" . hash('sha256', $canonical_request);

    $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret, true);
    $k_region = hash_hmac('sha256', $region, $k_date, true);
    $k_service = hash_hmac('sha256', 's3', $k_region, true);
    $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
    $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

    $authorization = "AWS4-HMAC-SHA256 Credential={$access}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";
    $url = 'https://' . $host . $canonical_uri;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => 'HEAD',
        CURLOPT_NOBODY => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $authorization,
            'Host: ' . $host,
            'x-amz-content-sha256: ' . $payload_hash,
            'x-amz-date: ' . $amz_date,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (PHP_VERSION_ID < 80000) {
        curl_close($ch);
    }

    if ($errno) {
        return false;
    }
    return $status >= 200 && $status < 300;
}

function candidate_local_paths($key, array $media_dirs)
{
    $paths = [];
    $key = ltrim($key, '/');

    if (strpos($key, 'assetsNew/') === 0) {
        $paths[] = $key;
        return $paths;
    }

    // Per-user S3 keys: local source was usually assetsNew/.../basename
    $basename = basename($key);
    if ($basename === '' || $basename === '.' || $basename === '/') {
        return $paths;
    }
    foreach ($media_dirs as $dir) {
        $paths[] = rtrim($dir, '/') . '/' . $basename;
    }
    return $paths;
}

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

$stats = [
    'db_values_scanned' => 0,
    's3_urls_found' => 0,
    's3_verified' => 0,
    's3_missing' => 0,
    'local_deleted' => 0,
    'local_missing' => 0,
    'protected_skipped' => 0,
    'failed' => 0,
    'unique_locals' => 0,
    'errors' => [],
];

$to_delete = []; // local_rel => s3_key
$s3_cache = [];  // key => bool exists

echo ($dry_run ? "[DRY RUN] " : "[LIVE] ") . "Cleanup local media already on S3 + DB\n";
echo "Bucket: s3://{$bucket}  Base: {$base_url}\n";
echo "Only media dirs under assetsNew/images|videos — admin UI assets kept.\n\n";

foreach ($targets as $table => $meta) {
    list($pk, $cols) = $meta;
    if (!table_exists($mysqli, $table)) {
        continue;
    }

    $existingCols = [];
    foreach ($cols as $col) {
        if (col_exists($mysqli, $table, $col)) {
            $existingCols[] = $col;
        }
    }
    if (empty($existingCols)) {
        continue;
    }

    echo "Table {$table}...\n";
    $select = '`' . $pk . '`,`' . implode('`,`', $existingCols) . '`';
    $result = $mysqli->query("SELECT {$select} FROM `{$table}`");
    if (!$result) {
        $stats['errors'][] = $table . ': ' . $mysqli->error;
        continue;
    }

    while ($row = $result->fetch_assoc()) {
        foreach ($existingCols as $col) {
            $raw = isset($row[$col]) ? trim((string) $row[$col]) : '';
            if ($raw === '') {
                continue;
            }

            $parts = ($table === 'products' && $col === 'images' && strpos($raw, ',') !== false)
                ? array_map('trim', explode(',', $raw))
                : [$raw];

            foreach ($parts as $value) {
                $stats['db_values_scanned']++;
                $key = extract_s3_key($value, $base_url);
                if ($key === '') {
                    continue;
                }
                // Only treat as migrated if DB stores an absolute S3 URL, OR relative users/assetsNew key
                // that is already the S3 object key style.
                $is_abs_s3 = (bool) preg_match('#^https?://#i', $value)
                    && (stripos($value, 'amazonaws.com') !== false
                        || stripos($value, 's3.') !== false
                        || stripos($value, rtrim($base_url, '/')) !== false);
                $is_rel_key = (strpos($key, 'users/') === 0 || strpos($key, 'assetsNew/') === 0)
                    && !preg_match('#^https?://#i', $value);

                if (!$is_abs_s3 && !$is_rel_key) {
                    continue;
                }

                $stats['s3_urls_found']++;

                if (!array_key_exists($key, $s3_cache)) {
                    if ($skip_s3_check) {
                        $s3_cache[$key] = true;
                    } else {
                        $s3_cache[$key] = s3_head($access, $secret, $bucket, $region, $key);
                    }
                }

                if (!$s3_cache[$key]) {
                    $stats['s3_missing']++;
                    continue;
                }
                $stats['s3_verified']++;

                foreach (candidate_local_paths($key, $MEDIA_DIRS) as $localRel) {
                    if (is_protected_name($localRel)) {
                        $stats['protected_skipped']++;
                        continue;
                    }
                    // Safety: must stay inside allowed media dirs
                    $allowed = false;
                    foreach ($MEDIA_DIRS as $dir) {
                        if (strpos($localRel, rtrim($dir, '/') . '/') === 0 || $localRel === rtrim($dir, '/')) {
                            $allowed = true;
                            break;
                        }
                    }
                    if (!$allowed) {
                        continue;
                    }

                    $abs = FCPATH . $localRel;
                    if (!is_file($abs)) {
                        continue;
                    }
                    $to_delete[$localRel] = $key;
                }
            }
        }
    }
}

$stats['unique_locals'] = count($to_delete);
echo "\nCandidates to delete: " . count($to_delete) . "\n";

foreach ($to_delete as $localRel => $s3Key) {
    $abs = FCPATH . $localRel;
    if (!is_file($abs)) {
        $stats['local_missing']++;
        continue;
    }

    if ($dry_run) {
        $stats['local_deleted']++;
        if ($stats['local_deleted'] <= 30) {
            echo "  would delete: {$localRel}  (S3: {$s3Key})\n";
        }
        continue;
    }

    if (@unlink($abs)) {
        $stats['local_deleted']++;
        if ($stats['local_deleted'] % 25 === 0) {
            echo "  deleted={$stats['local_deleted']}\n";
        }
    } else {
        $stats['failed']++;
        if (count($stats['errors']) < 40) {
            $stats['errors'][] = "unlink failed: {$localRel}";
        }
    }
}

$mysqli->close();

if ($dry_run && $stats['local_deleted'] > 30) {
    echo "  ... and " . ($stats['local_deleted'] - 30) . " more\n";
}

echo "\n" . json_encode([
    'status' => 'success',
    'dry_run' => $dry_run,
    'bucket' => $bucket,
    'stats' => $stats,
], JSON_PRETTY_PRINT) . "\n";
