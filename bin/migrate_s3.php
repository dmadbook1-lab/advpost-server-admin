#!/usr/bin/env php
<?php
/**
 * Standalone CLI: migrate local media files to S3 and update DB URLs.
 *
 *   php bin/migrate_s3.php --dry-run
 *   php bin/migrate_s3.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

@ini_set('max_execution_time', '0');
@set_time_limit(0);
@ini_set('memory_limit', '512M');

define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('BASEPATH', ROOTPATH . 'system' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public_html' . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'application' . DIRECTORY_SEPARATOR);
define('STORAGEPATH', ROOTPATH . 'storage' . DIRECTORY_SEPARATOR);
define('VENDORPATH', ROOTPATH . 'vendor' . DIRECTORY_SEPARATOR);
define('CREDENTIALSPATH', ROOTPATH . 'private' . DIRECTORY_SEPARATOR . 'credentials' . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'production');

$dry_run = in_array('--dry-run', $argv, true);

// Load DB config
require APPPATH . 'config/database.php';
$dbcfg = $db['default'];

$mysqli = new mysqli(
    $dbcfg['hostname'] === 'localhost' ? '127.0.0.1' : $dbcfg['hostname'],
    $dbcfg['username'],
    $dbcfg['password'],
    $dbcfg['database']
);
if ($mysqli->connect_error) {
    fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . "\n");
    exit(1);
}
$mysqli->set_charset('utf8mb4');

// Load AWS config
require APPPATH . 'config/aws.php';
$access = $config['access_key_id'] ?? '';
$secret = $config['secret_access_key'] ?? '';
$bucket = $config['bucket'] ?? '';
$region = $config['region'] ?? 'ap-south-1';
$base_url = rtrim($config['url'] ?? ('https://' . $bucket . '.s3.' . $region . '.amazonaws.com'), '/');

// Prefer DB credentials if present
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

function s3_public_url($base_url, $key)
{
    return $base_url . '/' . ltrim($key, '/');
}

function s3_upload($access, $secret, $bucket, $region, $local, $key, $content_type)
{
    $body = file_get_contents($local);
    if ($body === false) {
        return [false, 'Cannot read file'];
    }
    $payload_hash = hash('sha256', $body);
    $amz_date = gmdate('Ymd\THis\Z');
    $date_stamp = gmdate('Ymd');
    $host = $bucket . '.s3.' . $region . '.amazonaws.com';
    $canonical_uri = '/' . str_replace('%2F', '/', rawurlencode(ltrim($key, '/')));

    $canonical_headers =
        "content-type:{$content_type}\n" .
        "host:{$host}\n" .
        "x-amz-content-sha256:{$payload_hash}\n" .
        "x-amz-date:{$amz_date}\n";
    $signed_headers = 'content-type;host;x-amz-content-sha256;x-amz-date';

    $canonical_request = "PUT\n{$canonical_uri}\n\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
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
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $authorization,
            'Content-Type: ' . $content_type,
            'Content-Length: ' . strlen($body),
            'Host: ' . $host,
            'x-amz-content-sha256: ' . $payload_hash,
            'x-amz-date: ' . $amz_date,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 300,
    ]);
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return [false, 'cURL: ' . $error];
    }
    if ($status < 200 || $status >= 300) {
        return [false, 'HTTP ' . $status . ': ' . substr((string) $response, 0, 300)];
    }
    return [true, null];
}

function detect_mime($path)
{
    if (function_exists('mime_content_type')) {
        $m = @mime_content_type($path);
        if (is_string($m) && $m !== '') {
            return $m;
        }
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'mp4' => 'video/mp4',
        'mov' => 'video/quicktime', 'webm' => 'video/webm',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

function rel_path($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (preg_match('#https?://[^/]+/(assetsNew/.+)$#i', $value, $m)) {
        return $m[1];
    }
    $rel = ltrim($value, './');
    if (strpos($rel, 'assetsNew/') !== false) {
        return substr($rel, strpos($rel, 'assetsNew/'));
    }
    return '';
}

$stats = [
    'scanned' => 0,
    'uploaded' => 0,
    'updated' => 0,
    'skipped' => 0,
    'missing' => 0,
    'failed' => 0,
    'errors' => [],
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
    'avtar' => ['id', ['image']],
    'music' => ['id', ['image']],
    'admins' => ['id', ['profile_pic']],
];

function migrate_one($value, $dry_run, &$stats, $access, $secret, $bucket, $region, $base_url)
{
    $stats['scanned']++;
    $value = trim((string) $value);
    if ($value === '') {
        return [$value, false];
    }

    if (preg_match('#^https?://#i', $value)) {
        if (stripos($value, 'amazonaws.com') !== false || stripos($value, 's3.') !== false) {
            $stats['skipped']++;
            return [$value, false];
        }
        if (preg_match('#/(assetsNew/.+)$#i', $value, $m)) {
            $value = $m[1];
        } else {
            $stats['skipped']++;
            return [$value, false];
        }
    }

    $rel = rel_path($value);
    if ($rel === '' || strpos($rel, 'assetsNew/') !== 0) {
        $candidates = [
            'assetsNew/images/profile_pic/' . ltrim($value, '/'),
            'assetsNew/images/avtar/' . ltrim($value, '/'),
            'assetsNew/images/new_post/' . ltrim($value, '/'),
        ];
        $found = '';
        foreach ($candidates as $c) {
            if (is_file(FCPATH . $c)) {
                $found = $c;
                break;
            }
        }
        if ($found === '') {
            $stats['missing']++;
            return [$value, false];
        }
        $rel = $found;
    }

    $local = FCPATH . $rel;
    if (!is_file($local)) {
        $stats['missing']++;
        return [$value, false];
    }

    $s3Url = s3_public_url($base_url, $rel);
    if ($dry_run) {
        $stats['uploaded']++;
        return [$s3Url, true];
    }

    list($ok, $err) = s3_upload($access, $secret, $bucket, $region, $local, $rel, detect_mime($local));
    if (!$ok) {
        $stats['failed']++;
        $stats['errors'][] = $rel . ' => ' . $err;
        return [$value, false];
    }

    $stats['uploaded']++;
    return [$s3Url, true];
}

echo ($dry_run ? "[DRY RUN] " : "[LIVE] ") . "Migrating to s3://{$bucket}\n";

foreach ($targets as $table => $meta) {
    list($pk, $cols) = $meta;

    // Skip missing tables/columns
    $check = $mysqli->query("SHOW TABLES LIKE '{$mysqli->real_escape_string($table)}'");
    if (!$check || $check->num_rows === 0) {
        continue;
    }

    $existingCols = [];
    foreach ($cols as $col) {
        $c = $mysqli->query("SHOW COLUMNS FROM `{$table}` LIKE '{$mysqli->real_escape_string($col)}'");
        if ($c && $c->num_rows > 0) {
            $existingCols[] = $col;
        }
    }
    if (empty($existingCols)) {
        continue;
    }

    $select = '`' . $pk . '`,`' . implode('`,`', $existingCols) . '`';
    $result = $mysqli->query("SELECT {$select} FROM `{$table}`");
    if (!$result) {
        $stats['errors'][] = $table . ': ' . $mysqli->error;
        continue;
    }

    echo "Table {$table}...\n";
    while ($row = $result->fetch_assoc()) {
        $updates = [];
        foreach ($existingCols as $col) {
            $value = isset($row[$col]) ? trim((string) $row[$col]) : '';
            if ($value === '') {
                continue;
            }

            if ($table === 'products' && $col === 'images' && strpos($value, ',') !== false) {
                $parts = array_map('trim', explode(',', $value));
                $newParts = [];
                $changed = false;
                foreach ($parts as $part) {
                    list($newUrl, $did) = migrate_one($part, $dry_run, $stats, $access, $secret, $bucket, $region, $base_url);
                    $newParts[] = $newUrl;
                    if ($did) {
                        $changed = true;
                    }
                }
                if ($changed) {
                    $updates[$col] = implode(',', $newParts);
                }
                continue;
            }

            list($newUrl, $did) = migrate_one($value, $dry_run, $stats, $access, $secret, $bucket, $region, $base_url);
            if ($did) {
                $updates[$col] = $newUrl;
            }
        }

        if (!empty($updates)) {
            if (!$dry_run) {
                $sets = [];
                foreach ($updates as $k => $v) {
                    $sets[] = "`{$k}`='" . $mysqli->real_escape_string($v) . "'";
                }
                $id = $mysqli->real_escape_string($row[$pk]);
                $sql = "UPDATE `{$table}` SET " . implode(',', $sets) . " WHERE `{$pk}`='{$id}'";
                if (!$mysqli->query($sql)) {
                    $stats['failed']++;
                    $stats['errors'][] = $table . '#' . $row[$pk] . ': ' . $mysqli->error;
                    continue;
                }
            }
            $stats['updated']++;
            if ($stats['uploaded'] % 25 === 0) {
                echo "  uploaded={$stats['uploaded']} updated={$stats['updated']}\n";
            }
        }
    }
}

$mysqli->close();

echo json_encode([
    'status' => 'success',
    'dry_run' => $dry_run,
    'bucket' => $bucket,
    'base_url' => $base_url,
    'stats' => $stats,
], JSON_PRETTY_PRINT) . "\n";
