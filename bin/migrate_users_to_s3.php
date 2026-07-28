#!/usr/bin/env php
<?php
/**
 * Migrate ALL user media into the canonical per-user S3 layout:
 *
 *   users/{user_id}/profile|brand|posts|reels|stories|products|chat|generated/...
 *
 * Sources supported:
 *   - local public_html/assetsNew/...
 *   - existing S3 URLs under assetsNew/...
 *   - absolute site URLs (adbook.co.in / advpost.in) pointing at assetsNew
 *   - bare filenames (profile/avatar guesses)
 *
 * Skips rows already under users/{id}/...
 *
 * Usage:
 *   php bin/migrate_users_to_s3.php --dry-run
 *   php bin/migrate_users_to_s3.php
 *   php bin/migrate_users_to_s3.php --limit=50
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

@ini_set('max_execution_time', '0');
@set_time_limit(0);
@ini_set('memory_limit', '1024M');

define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('BASEPATH', ROOTPATH . 'system' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public_html' . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'application' . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'production');

$dry_run = in_array('--dry-run', $argv, true);
$limit = 0;
$only_table = '';
foreach ($argv as $arg) {
    if (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = (int) $m[1];
    }
    if (preg_match('/^--table=([a-zA-Z0-9_]+)$/', $arg, $m)) {
        $only_table = $m[1];
    }
}
function should_run_table($name) {
    global $only_table;
    return $only_table === '' || $only_table === $name;
}

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

require APPPATH . 'config/aws.php';
$access = $config['access_key_id'] ?? '';
$secret = $config['secret_access_key'] ?? '';
$bucket = $config['bucket'] ?? 'advpost';
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
    fwrite(STDERR, "S3 credentials missing\n");
    exit(1);
}

/* ---------------- helpers ---------------- */

function safe_name($name)
{
    $name = basename((string) $name);
    $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
    return $name !== '' ? $name : ('file_' . uniqid());
}

function user_media_key($user_id, $category, $entity_id = null, $subfolder = null, $filename = null)
{
    $parts = ['users', (int) $user_id, $category];
    if (in_array($category, ['posts', 'reels', 'stories'], true) && $entity_id !== null && $entity_id !== '') {
        $parts[] = $entity_id;
    }
    if ($subfolder !== null && $subfolder !== '') {
        $parts[] = trim((string) $subfolder, '/');
    }
    if ($filename !== null && $filename !== '') {
        $parts[] = safe_name($filename);
    }
    return implode('/', array_filter($parts, static function ($p) {
        return $p !== '' && $p !== null;
    }));
}

function already_canonical($url)
{
    return (bool) preg_match('#(^|/)users/\d+/#i', (string) $url);
}

function extract_filename($value)
{
    $value = trim((string) $value, " \t\n\r\0\x0B\"'\[\]");
    // JSON array leftovers: take first http URL if present
    if (preg_match('#https?://[^\"\'\]\s]+#i', $value, $m)) {
        $value = $m[0];
    }
    $path = parse_url($value, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = $value;
    }
    return safe_name(basename(rawurldecode($path)));
}

function detect_mime_bytes($bytes, $filename)
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->buffer($bytes);
    if (is_string($mime) && $mime !== '' && $mime !== 'application/octet-stream') {
        return $mime;
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'mp4' => 'video/mp4',
        'mov' => 'video/quicktime', 'webm' => 'video/webm',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}

function http_get_bytes($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'AdvPost-Migrate/1.0',
        CURLOPT_PROXY => '',
        CURLOPT_NOPROXY => '*',
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    if ($body === false || $status < 200 || $status >= 300) {
        return [false, 'GET HTTP ' . $status . ' ' . $err];
    }
    return [true, $body];
}

function s3_get_bytes($access, $secret, $bucket, $region, $key)
{
    $key = ltrim(str_replace('\\', '/', (string) $key), '/');
    $payload_hash = hash('sha256', '');
    $amz_date = gmdate('Ymd\THis\Z');
    $date_stamp = gmdate('Ymd');
    $host = $bucket . '.s3.' . $region . '.amazonaws.com';
    $canonical_uri = '/' . str_replace('%2F', '/', rawurlencode($key));
    $canonical_headers =
        "host:{$host}\n" .
        "x-amz-content-sha256:{$payload_hash}\n" .
        "x-amz-date:{$amz_date}\n";
    $signed_headers = 'host;x-amz-content-sha256;x-amz-date';
    $canonical_request = "GET\n{$canonical_uri}\n\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
    $credential_scope = "{$date_stamp}/{$region}/s3/aws4_request";
    $string_to_sign = "AWS4-HMAC-SHA256\n{$amz_date}\n{$credential_scope}\n" . hash('sha256', $canonical_request);
    $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret, true);
    $k_region = hash_hmac('sha256', $region, $k_date, true);
    $k_service = hash_hmac('sha256', 's3', $k_region, true);
    $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
    $signature = hash_hmac('sha256', $string_to_sign, $k_signing);
    $authorization = "AWS4-HMAC-SHA256 Credential={$access}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";
    $ch = curl_init('https://' . $host . $canonical_uri);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $authorization,
            'Host: ' . $host,
            'x-amz-content-sha256: ' . $payload_hash,
            'x-amz-date: ' . $amz_date,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_PROXY => '',
        CURLOPT_NOPROXY => '*',
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    if ($body === false || $status < 200 || $status >= 300) {
        return [false, 'S3 GET HTTP ' . $status . ' ' . $err];
    }
    return [true, $body];
}

function extract_s3_key_from_url($value, $bucket)
{
    $value = trim((string) $value);
    if (preg_match('#https?://' . preg_quote($bucket, '#') . '\.s3[.\-][^/]+/(.+)$#i', $value, $m)) {
        return rawurldecode($m[1]);
    }
    if (preg_match('#https?://s3[.\-][^/]+/' . preg_quote($bucket, '#') . '/(.+)$#i', $value, $m)) {
        return rawurldecode($m[1]);
    }
    if (preg_match('#/(users/\d+/.+)$#i', $value, $m)) {
        return rawurldecode($m[1]);
    }
    if (strpos($value, 'users/') === 0) {
        return $value;
    }
    if (preg_match('#/(assetsNew/.+)$#i', $value, $m)) {
        return $m[1];
    }
    if (strpos($value, 'assetsNew/') === 0) {
        return $value;
    }
    return '';
}

function s3_head_exists($access, $secret, $bucket, $region, $key)
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

    $ch = curl_init('https://' . $host . $canonical_uri);
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
        CURLOPT_PROXY => '',
        CURLOPT_NOPROXY => '*',
    ]);
    curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return !$errno && $status >= 200 && $status < 300;
}

function s3_put_bytes($access, $secret, $bucket, $region, $key, $body, $content_type)
{
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

    $ch = curl_init('https://' . $host . $canonical_uri);
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
        CURLOPT_PROXY => '',
        CURLOPT_NOPROXY => '*',
    ]);
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($errno) {
        return [false, 'cURL: ' . $error];
    }
    if ($status < 200 || $status >= 300) {
        return [false, 'HTTP ' . $status . ': ' . substr((string) $response, 0, 300)];
    }
    return [true, null];
}

/**
 * Resolve media bytes from local disk, signed S3 GET, or public URL.
 * @return array{0:bool,1:string} [ok, bytes|error]
 */
function resolve_bytes($value, $guess_dirs = [], $access = '', $secret = '', $bucket = '', $region = 'ap-south-1')
{
    $value = trim((string) $value);
    if ($value === '' || preg_match('#^https?://example\.com#i', $value)) {
        return [false, 'empty_or_dummy'];
    }

    $s3Key = extract_s3_key_from_url($value, $bucket);
    $s3Err = '';
    if ($s3Key !== '' && $access !== '' && $secret !== '') {
        list($ok, $body) = s3_get_bytes($access, $secret, $bucket, $region, $s3Key);
        if ($ok) {
            return [true, $body];
        }
        $s3Err = $body;
    }

    // If URL/path references assetsNew, try local disk then live hosts
    $assetsRel = '';
    if ($s3Key !== '' && strpos($s3Key, 'assetsNew/') === 0) {
        $assetsRel = $s3Key;
    } elseif (preg_match('#(assetsNew/.+)$#i', $value, $m)) {
        $assetsRel = $m[1];
    }

    if ($assetsRel !== '') {
        $local = FCPATH . $assetsRel;
        if (is_file($local)) {
            $bytes = file_get_contents($local);
            return $bytes === false ? [false, 'read_fail'] : [true, $bytes];
        }
        foreach (['https://adbook.co.in/', 'https://www.advpost.in/', 'https://admin.advpost.in/'] as $host) {
            list($ok, $body) = http_get_bytes(rtrim($host, '/') . '/' . $assetsRel);
            if ($ok) {
                return [true, $body];
            }
        }
    }

    if (preg_match('#^https?://#i', $value)) {
        list($ok, $body) = http_get_bytes($value);
        if ($ok) {
            return [true, $body];
        }
        // continue to bare-filename guesses below if possible
    }

    $rel = ltrim($value, './');
    if (strpos($rel, 'assetsNew/') !== false) {
        $rel = substr($rel, strpos($rel, 'assetsNew/'));
        $local = FCPATH . $rel;
        if (is_file($local)) {
            $bytes = file_get_contents($local);
            return $bytes === false ? [false, 'read_fail'] : [true, $bytes];
        }
        if ($access !== '' && $secret !== '') {
            list($ok, $body) = s3_get_bytes($access, $secret, $bucket, $region, $rel);
            if ($ok) {
                return [true, $body];
            }
            $s3Err = $body;
        }
        foreach (['https://adbook.co.in/', 'https://www.advpost.in/', 'https://admin.advpost.in/'] as $host) {
            list($ok, $body) = http_get_bytes(rtrim($host, '/') . '/' . $rel);
            if ($ok) {
                return [true, $body];
            }
        }
        return [false, 'missing:' . $rel . ($s3Err ? ' / ' . $s3Err : '')];
    }

    $name = ltrim(basename(parse_url($value, PHP_URL_PATH) ?: $value), '/');
    if ($name === '' || $name === '/' || $name === '.') {
        return [false, 'unresolved:' . $value . ($s3Err ? ' / ' . $s3Err : '')];
    }
    foreach ($guess_dirs as $dir) {
        $local = FCPATH . rtrim($dir, '/') . '/' . $name;
        if (is_file($local)) {
            $bytes = file_get_contents($local);
            return $bytes === false ? [false, 'read_fail'] : [true, $bytes];
        }
    }
    foreach ($guess_dirs as $dir) {
        $rel = rtrim($dir, '/') . '/' . $name;
        if ($access !== '' && $secret !== '') {
            list($ok, $body) = s3_get_bytes($access, $secret, $bucket, $region, $rel);
            if ($ok) {
                return [true, $body];
            }
        }
        foreach (['https://adbook.co.in/', 'https://www.advpost.in/', 'https://admin.advpost.in/'] as $host) {
            list($ok, $body) = http_get_bytes(rtrim($host, '/') . '/' . $rel);
            if ($ok) {
                return [true, $body];
            }
        }
    }

    return [false, 'unresolved:' . $value . ($s3Err ? ' / ' . $s3Err : '')];
}

$stats = [
    'scanned' => 0,
    'uploaded' => 0,
    'updated_rows' => 0,
    'skipped_canonical' => 0,
    'repaired_canonical' => 0,
    'skipped_empty' => 0,
    'missing' => 0,
    'failed' => 0,
    'errors' => [],
];

function migrate_value(
    $value,
    $dest_key,
    $guess_dirs,
    $dry_run,
    &$stats,
    $access,
    $secret,
    $bucket,
    $region,
    $base_url
) {
    $stats['scanned']++;
    $value = trim((string) $value);
    if ($value === '') {
        $stats['skipped_empty']++;
        return [$value, false];
    }

    $filename = extract_filename($value);
    // ensure dest key ends with filename if caller passed folder-ish key without file
    if (substr($dest_key, -1) === '/' || pathinfo($dest_key, PATHINFO_EXTENSION) === '') {
        $dest_key = rtrim($dest_key, '/') . '/' . $filename;
    }

    if (already_canonical($value)) {
        $existingKey = extract_s3_key_from_url($value, $bucket);
        if ($existingKey === '') {
            $existingKey = ltrim(parse_url($value, PHP_URL_PATH) ?: '', '/');
        }
        if ($existingKey !== '' && s3_head_exists($access, $secret, $bucket, $region, $existingKey)) {
            $stats['skipped_canonical']++;
            return [$value, false];
        }
        // Canonical URL in DB but object missing on S3 — try repair from local/guess dirs
        $stats['repaired_canonical']++;
        $dest_key = $existingKey !== '' ? $existingKey : $dest_key;
    }

    list($ok, $bytesOrErr) = resolve_bytes($value, $guess_dirs, $access, $secret, $bucket, $region);
    if (!$ok) {
        $stats['missing']++;
        if (count($stats['errors']) < 40) {
            $stats['errors'][] = $dest_key . ' <= ' . $bytesOrErr;
        }
        return [$value, false];
    }
    $bytes = $bytesOrErr;
    $mime = detect_mime_bytes($bytes, $filename);
    $newUrl = $base_url . '/' . ltrim($dest_key, '/');

    if ($dry_run) {
        $stats['uploaded']++;
        return [$newUrl, true];
    }

    $putOk = false;
    $err = null;
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        list($putOk, $err) = s3_put_bytes($access, $secret, $bucket, $region, $dest_key, $bytes, $mime);
        if ($putOk) {
            break;
        }
        if ($attempt < 3 && (stripos((string) $err, '503') !== false || stripos((string) $err, 'timeout') !== false || stripos((string) $err, 'Slow Down') !== false)) {
            sleep(2 * $attempt);
            continue;
        }
        break;
    }
    if (!$putOk) {
        $stats['failed']++;
        if (count($stats['errors']) < 40) {
            $stats['errors'][] = $dest_key . ' => ' . $err;
        }
        return [$value, false];
    }

    $stats['uploaded']++;
    if ($stats['uploaded'] % 10 === 0) {
        echo "  uploaded={$stats['uploaded']} updated_rows={$stats['updated_rows']} missing={$stats['missing']} repaired={$stats['repaired_canonical']}\n";
        @ob_flush(); flush();
    }
    return [$newUrl, true];
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

function apply_updates(mysqli $db, $table, $pk, $pkVal, array $updates, $dry_run, &$stats)
{
    if (empty($updates)) {
        return;
    }
    $stats['updated_rows']++;
    if ($dry_run) {
        return;
    }
    $sets = [];
    foreach ($updates as $k => $v) {
        $sets[] = "`{$k}`='" . $db->real_escape_string($v) . "'";
    }
    $id = $db->real_escape_string((string) $pkVal);
    if (!$db->query("UPDATE `{$table}` SET " . implode(',', $sets) . " WHERE `{$pk}`='{$id}'")) {
        $stats['failed']++;
        $stats['errors'][] = "{$table}#{$pkVal}: " . $db->error;
        $stats['updated_rows']--;
    }
}

echo ($dry_run ? "[DRY RUN] " : "[LIVE] ") . "Per-user migrate → s3://{$bucket}\n";
echo "Base URL: {$base_url}\n";
if ($limit > 0) {
    echo "Limit per table: {$limit}\n";
}
echo "\n";

$limitSql = $limit > 0 ? ' LIMIT ' . (int) $limit : '';

/* ========== USERS profile + brand ========== */
if (should_run_table('users') && table_exists($mysqli, 'users')) {
    echo "→ users (profile/brand)\n";
    $cols = ['id'];
    foreach (['profile_pic', 'logo_url'] as $c) {
        if (col_exists($mysqli, 'users', $c)) {
            $cols[] = $c;
        }
    }
    $q = $mysqli->query('SELECT `' . implode('`,`', $cols) . '` FROM `users`' . $limitSql);
    while ($q && ($row = $q->fetch_assoc())) {
        $uid = (int) $row['id'];
        $updates = [];
        if (!empty($row['profile_pic'])) {
            $key = user_media_key($uid, 'profile', null, null, extract_filename($row['profile_pic']));
            list($url, $did) = migrate_value(
                $row['profile_pic'],
                $key,
                ['assetsNew/images/profile_pic', 'assetsNew/images/avtar', 'assetsNew/images/avatar'],
                $dry_run,
                $stats,
                $access,
                $secret,
                $bucket,
                $region,
                $base_url
            );
            if ($did) {
                $updates['profile_pic'] = $url;
            }
        }
        if (!empty($row['logo_url'])) {
            $key = user_media_key($uid, 'brand', null, null, extract_filename($row['logo_url']));
            list($url, $did) = migrate_value(
                $row['logo_url'],
                $key,
                ['assetsNew/images/logo', 'assetsNew/images/profile_pic'],
                $dry_run,
                $stats,
                $access,
                $secret,
                $bucket,
                $region,
                $base_url
            );
            if ($did) {
                $updates['logo_url'] = $url;
            }
        }
        apply_updates($mysqli, 'users', 'id', $uid, $updates, $dry_run, $stats);
    }
}

/* ========== POST IMAGES ========== */
if (should_run_table('post_image') && table_exists($mysqli, 'post_image')) {
    echo "→ post_image → users/{uid}/posts/{post_id}/images/\n";
    $q = $mysqli->query("SELECT id, post_id, user_id, new_post FROM post_image WHERE new_post IS NOT NULL AND new_post != ''" . $limitSql);
    while ($q && ($row = $q->fetch_assoc())) {
        $uid = (int) $row['user_id'];
        $postId = (int) $row['post_id'];
        $key = user_media_key($uid, 'posts', $postId, 'images', extract_filename($row['new_post']));
        list($url, $did) = migrate_value(
            $row['new_post'],
            $key,
            ['assetsNew/images/new_post'],
            $dry_run,
            $stats,
            $access,
            $secret,
            $bucket,
            $region,
            $base_url
        );
        if ($did) {
            apply_updates($mysqli, 'post_image', 'id', $row['id'], ['new_post' => $url], $dry_run, $stats);
        }
    }
}

/* ========== POST VIDEOS (route reels by posts.post_type) ========== */
if (should_run_table('post_video') && table_exists($mysqli, 'post_video')) {
    echo "→ post_video → posts|reels videos/thumbnails\n";
    $q = $mysqli->query(
        "SELECT pv.id, pv.post_id, pv.user_id, pv.post_video, pv.post_video_hls, pv.post_video_thumbnail, p.post_type
         FROM post_video pv
         LEFT JOIN posts p ON p.post_id = pv.post_id
         WHERE (pv.post_video IS NOT NULL AND pv.post_video != '')
            OR (pv.post_video_thumbnail IS NOT NULL AND pv.post_video_thumbnail != '')
            OR (pv.post_video_hls IS NOT NULL AND pv.post_video_hls != '')" . $limitSql
    );
    while ($q && ($row = $q->fetch_assoc())) {
        $uid = (int) $row['user_id'];
        $postId = (int) $row['post_id'];
        $type = strtolower((string) ($row['post_type'] ?? ''));
        $cat = ($type === 'reel' || $type === 'video') ? 'reels' : 'posts';
        $updates = [];

        if (!empty($row['post_video'])) {
            $key = user_media_key($uid, $cat, $postId, 'videos', extract_filename($row['post_video']));
            list($url, $did) = migrate_value(
                $row['post_video'],
                $key,
                ['assetsNew/videos/post_video', 'assetsNew/videos/reel_video', 'assetsNew/videos/reels'],
                $dry_run,
                $stats,
                $access,
                $secret,
                $bucket,
                $region,
                $base_url
            );
            if ($did) {
                $updates['post_video'] = $url;
            }
        }
        if (!empty($row['post_video_thumbnail'])) {
            $key = user_media_key($uid, $cat, $postId, 'thumbnails', extract_filename($row['post_video_thumbnail']));
            list($url, $did) = migrate_value(
                $row['post_video_thumbnail'],
                $key,
                ['assetsNew/videos/post_video_thumbnails', 'assetsNew/videos/reel_video_thumbnails'],
                $dry_run,
                $stats,
                $access,
                $secret,
                $bucket,
                $region,
                $base_url
            );
            if ($did) {
                $updates['post_video_thumbnail'] = $url;
            }
        }
        if (!empty($row['post_video_hls'])) {
            $key = user_media_key($uid, $cat, $postId, 'videos', extract_filename($row['post_video_hls']));
            list($url, $did) = migrate_value(
                $row['post_video_hls'],
                $key,
                ['assetsNew/videos/post_video'],
                $dry_run,
                $stats,
                $access,
                $secret,
                $bucket,
                $region,
                $base_url
            );
            if ($did) {
                $updates['post_video_hls'] = $url;
            }
        }
        apply_updates($mysqli, 'post_video', 'id', $row['id'], $updates, $dry_run, $stats);
    }
}

/* ========== REELS_VIDEO ========== */
if (should_run_table('reels_video') && table_exists($mysqli, 'reels_video')) {
    echo "→ reels_video\n";
    $q = $mysqli->query('SELECT * FROM reels_video' . $limitSql);
    while ($q && ($row = $q->fetch_assoc())) {
        $uid = (int) $row['user_id'];
        $reelId = (int) $row['reel_id'];
        $updates = [];
        if (!empty($row['reel_video'])) {
            $key = user_media_key($uid, 'reels', $reelId, 'videos', extract_filename($row['reel_video']));
            list($url, $did) = migrate_value(
                $row['reel_video'],
                $key,
                ['assetsNew/videos/reel_video', 'assetsNew/videos/reels'],
                $dry_run,
                $stats,
                $access,
                $secret,
                $bucket,
                $region,
                $base_url
            );
            if ($did) {
                $updates['reel_video'] = $url;
            }
        }
        if (!empty($row['reel_video_thumbnail'])) {
            $key = user_media_key($uid, 'reels', $reelId, 'thumbnails', extract_filename($row['reel_video_thumbnail']));
            list($url, $did) = migrate_value(
                $row['reel_video_thumbnail'],
                $key,
                ['assetsNew/videos/reel_video_thumbnails'],
                $dry_run,
                $stats,
                $access,
                $secret,
                $bucket,
                $region,
                $base_url
            );
            if ($did) {
                $updates['reel_video_thumbnail'] = $url;
            }
        }
        apply_updates($mysqli, 'reels_video', 'id', $row['id'], $updates, $dry_run, $stats);
    }
}

/* ========== STORIES ========== */
if (should_run_table('story') && table_exists($mysqli, 'story')) {
    echo "→ story → users/{uid}/stories/{story_id}/...\n";
    $q = $mysqli->query('SELECT story_id, user_id, url, video_thumbnail, hls_url, preview_video FROM story' . $limitSql);
    while ($q && ($row = $q->fetch_assoc())) {
        $uid = (int) $row['user_id'];
        $sid = (int) $row['story_id'];
        $updates = [];
        foreach ([
            'url' => 'media',
            'video_thumbnail' => 'thumbnails',
            'hls_url' => 'media',
            'preview_video' => 'media',
        ] as $col => $sub) {
            if (empty($row[$col]) || !col_exists($mysqli, 'story', $col)) {
                continue;
            }
            $key = user_media_key($uid, 'stories', $sid, $sub, extract_filename($row[$col]));
            list($url, $did) = migrate_value(
                $row[$col],
                $key,
                ['assetsNew/images/story_image', 'assetsNew/images/story_cover_pic', 'assetsNew/videos/message'],
                $dry_run,
                $stats,
                $access,
                $secret,
                $bucket,
                $region,
                $base_url
            );
            if ($did) {
                $updates[$col] = $url;
            }
        }
        apply_updates($mysqli, 'story', 'story_id', $sid, $updates, $dry_run, $stats);
    }
}

/* ========== STORY HIGHLIGHTS ========== */
if (should_run_table('story_highlight') && table_exists($mysqli, 'story_highlight') && col_exists($mysqli, 'story_highlight', 'cover_pic')) {
    echo "→ story_highlight\n";
    $q = $mysqli->query('SELECT highlight_id, user_id, story_id, cover_pic FROM story_highlight WHERE cover_pic IS NOT NULL AND cover_pic != \'\'' . $limitSql);
    while ($q && ($row = $q->fetch_assoc())) {
        $uid = (int) $row['user_id'];
        $sid = (int) ($row['story_id'] ?: $row['highlight_id']);
        $key = user_media_key($uid, 'stories', $sid, 'thumbnails', extract_filename($row['cover_pic']));
        list($url, $did) = migrate_value(
            $row['cover_pic'],
            $key,
            ['assetsNew/images/story_cover_pic', 'assetsNew/images/story_image'],
            $dry_run,
            $stats,
            $access,
            $secret,
            $bucket,
            $region,
            $base_url
        );
        if ($did) {
            apply_updates($mysqli, 'story_highlight', 'highlight_id', $row['highlight_id'], ['cover_pic' => $url], $dry_run, $stats);
        }
    }
}

/* ========== CHATS ========== */
if (should_run_table('chats') && table_exists($mysqli, 'chats')) {
    echo "→ chats\n";
    $q = $mysqli->query('SELECT id, from_user, url, video_thumbnail, type FROM chats WHERE (url IS NOT NULL AND url != \'\') OR (video_thumbnail IS NOT NULL AND video_thumbnail != \'\')' . $limitSql);
    while ($q && ($row = $q->fetch_assoc())) {
        $uid = (int) $row['from_user'];
        $updates = [];
        if (!empty($row['url'])) {
            $sub = (stripos((string) $row['type'], 'video') !== false) ? 'videos' : 'images';
            $key = user_media_key($uid, 'chat', null, $sub, extract_filename($row['url']));
            list($url, $did) = migrate_value(
                $row['url'],
                $key,
                ['assetsNew/images/chat', 'assetsNew/images/message', 'assetsNew/videos/message'],
                $dry_run,
                $stats,
                $access,
                $secret,
                $bucket,
                $region,
                $base_url
            );
            if ($did) {
                $updates['url'] = $url;
            }
        }
        if (!empty($row['video_thumbnail'])) {
            $key = user_media_key($uid, 'chat', null, 'images', extract_filename($row['video_thumbnail']));
            list($url, $did) = migrate_value(
                $row['video_thumbnail'],
                $key,
                ['assetsNew/images/chat_thumbnails'],
                $dry_run,
                $stats,
                $access,
                $secret,
                $bucket,
                $region,
                $base_url
            );
            if ($did) {
                $updates['video_thumbnail'] = $url;
            }
        }
        apply_updates($mysqli, 'chats', 'id', $row['id'], $updates, $dry_run, $stats);
    }
}

/* ========== PRODUCTS ========== */
if (should_run_table('products') && table_exists($mysqli, 'products') && col_exists($mysqli, 'products', 'images')) {
    echo "→ products\n";
    $q = $mysqli->query('SELECT id, user_id, images FROM products WHERE images IS NOT NULL AND images != \'\'' . $limitSql);
    while ($q && ($row = $q->fetch_assoc())) {
        $uid = (int) $row['user_id'];
        $parts = array_map('trim', explode(',', $row['images']));
        $newParts = [];
        $changed = false;
        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }
            $key = user_media_key($uid, 'products', null, null, ($i + 1) . '_' . extract_filename($part));
            list($url, $did) = migrate_value(
                $part,
                $key,
                ['assetsNew/images/product', 'assetsNew/images/new_post'],
                $dry_run,
                $stats,
                $access,
                $secret,
                $bucket,
                $region,
                $base_url
            );
            $newParts[] = $url;
            if ($did) {
                $changed = true;
            }
        }
        if ($changed) {
            apply_updates($mysqli, 'products', 'id', $row['id'], ['images' => implode(',', $newParts)], $dry_run, $stats);
        }
    }
}

/* ========== AI JOBS ========== */
if (should_run_table('image_creation_jobs') && table_exists($mysqli, 'image_creation_jobs')) {
    echo "→ image_creation_jobs\n";
    $jobCols = [];
    foreach (['product_image_url', 'logo_url', 'output_image_url', 'output_file_path'] as $c) {
        if (col_exists($mysqli, 'image_creation_jobs', $c)) {
            $jobCols[] = $c;
        }
    }
    if ($jobCols) {
        $q = $mysqli->query('SELECT id, user_id, `' . implode('`,`', $jobCols) . '` FROM image_creation_jobs' . $limitSql);
        while ($q && ($row = $q->fetch_assoc())) {
            $uid = (int) $row['user_id'];
            $updates = [];
            foreach ($jobCols as $col) {
                if (empty($row[$col])) {
                    continue;
                }
                $key = user_media_key($uid, 'generated', null, 'images', extract_filename($row[$col]));
                list($url, $did) = migrate_value(
                    $row[$col],
                    $key,
                    ['assetsNew/images/new_post', 'assetsNew/images/product'],
                    $dry_run,
                    $stats,
                    $access,
                    $secret,
                    $bucket,
                    $region,
                    $base_url
                );
                if ($did) {
                    $updates[$col] = $url;
                }
            }
            apply_updates($mysqli, 'image_creation_jobs', 'id', $row['id'], $updates, $dry_run, $stats);
        }
    }
}

if (should_run_table('video_creation_jobs') && table_exists($mysqli, 'video_creation_jobs')) {
    echo "→ video_creation_jobs\n";
    $jobCols = [];
    foreach (['starting_image_url', 'output_video_url', 'output_file_path'] as $c) {
        if (col_exists($mysqli, 'video_creation_jobs', $c)) {
            $jobCols[] = $c;
        }
    }
    if ($jobCols) {
        $q = $mysqli->query('SELECT id, user_id, `' . implode('`,`', $jobCols) . '` FROM video_creation_jobs' . $limitSql);
        while ($q && ($row = $q->fetch_assoc())) {
            $uid = (int) $row['user_id'];
            $updates = [];
            foreach ($jobCols as $col) {
                if (empty($row[$col])) {
                    continue;
                }
                $sub = (strpos($col, 'video') !== false) ? 'videos' : 'images';
                $key = user_media_key($uid, 'generated', null, $sub, extract_filename($row[$col]));
                list($url, $did) = migrate_value(
                    $row[$col],
                    $key,
                    ['assetsNew/videos/post_video', 'assetsNew/images/new_post'],
                    $dry_run,
                    $stats,
                    $access,
                    $secret,
                    $bucket,
                    $region,
                    $base_url
                );
                if ($did) {
                    $updates[$col] = $url;
                }
            }
            apply_updates($mysqli, 'video_creation_jobs', 'id', $row['id'], $updates, $dry_run, $stats);
        }
    }
}

/* ========== SHARED AVATARS (not per-user) ========== */
if (should_run_table('avtar') && table_exists($mysqli, 'avtar') && col_exists($mysqli, 'avtar', 'image')) {
    echo "→ avtar (shared/avatars)\n";
    $q = $mysqli->query('SELECT id, image FROM avtar WHERE image IS NOT NULL AND image != \'\'' . $limitSql);
    while ($q && ($row = $q->fetch_assoc())) {
        $value = trim($row['image']);
        if ($value === '' || already_canonical($value) || preg_match('#(^|/)shared/avatars/#', $value)) {
            if (already_canonical($value) || preg_match('#(^|/)shared/avatars/#', $value)) {
                $stats['skipped_canonical']++;
            }
            continue;
        }
        $stats['scanned']++;
        $fname = extract_filename($value);
        $dest = 'shared/avatars/' . $fname;
        list($ok, $bytesOrErr) = resolve_bytes($value, ['assetsNew/images/avtar', 'assetsNew/images/avatar'], $access, $secret, $bucket, $region);
        if (!$ok) {
            $stats['missing']++;
            continue;
        }
        $newUrl = $base_url . '/' . $dest;
        if ($dry_run) {
            $stats['uploaded']++;
            apply_updates($mysqli, 'avtar', 'id', $row['id'], ['image' => $newUrl], $dry_run, $stats);
            continue;
        }
        list($putOk, $err) = s3_put_bytes($access, $secret, $bucket, $region, $dest, $bytesOrErr, detect_mime_bytes($bytesOrErr, $fname));
        if (!$putOk) {
            $stats['failed']++;
            $stats['errors'][] = $dest . ' => ' . $err;
            continue;
        }
        $stats['uploaded']++;
        apply_updates($mysqli, 'avtar', 'id', $row['id'], ['image' => $newUrl], $dry_run, $stats);
    }
}

$mysqli->close();

echo "\n";
echo json_encode([
    'status' => 'success',
    'dry_run' => $dry_run,
    'bucket' => $bucket,
    'base_url' => $base_url,
    'stats' => $stats,
], JSON_PRETTY_PRINT) . "\n";

exit(($stats['failed'] > 0 && !$dry_run) ? 1 : 0);
