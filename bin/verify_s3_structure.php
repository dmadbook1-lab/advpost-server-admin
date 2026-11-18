#!/usr/bin/env php
<?php
/**
 * Verify AdvPost S3 per-user folder layout with real uploads.
 *
 *   php bin/verify_s3_structure.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

define('ROOTPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
define('BASEPATH', ROOTPATH . 'system' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public_html' . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'application' . DIRECTORY_SEPARATOR);

require_once ROOTPATH . 'private' . DIRECTORY_SEPARATOR . 'load_env.php';
advpost_load_env(ROOTPATH);
define('ENVIRONMENT', advpost_resolve_environment());

require APPPATH . 'config/aws.php';

$access = $config['access_key_id'] ?? '';
$secret = $config['secret_access_key'] ?? '';
$bucket = $config['bucket'] ?? 'advpost';
$region = $config['region'] ?? 'ap-south-1';
$base_url = rtrim($config['url'] ?? ('https://' . $bucket . '.s3.' . $region . '.amazonaws.com'), '/');

if ($access === '' || $secret === '' || $bucket === '') {
    fwrite(STDERR, "S3 credentials missing (.env AWS_* / application/config/aws.php)\n");
    exit(1);
}

function s3_put($access, $secret, $bucket, $region, $key, $body, $content_type)
{
    $payload_hash = hash('sha256', $body);
    $amz_date = gmdate('Ymd\THis\Z');
    $date_stamp = gmdate('Ymd');
    $host = $bucket . '.s3.' . $region . '.amazonaws.com';
    $canonical_uri = '/' . str_replace('%2F', '/', rawurlencode($key));

    $canonical_headers =
        "content-type:{$content_type}\n" .
        "host:{$host}\n" .
        "x-amz-content-sha256:{$payload_hash}\n" .
        "x-amz-date:{$amz_date}\n";
    $signed_headers = 'content-type;host;x-amz-content-sha256;x-amz-date';

    $canonical_request = implode("\n", [
        'PUT', $canonical_uri, '', $canonical_headers, $signed_headers, $payload_hash,
    ]);
    $algorithm = 'AWS4-HMAC-SHA256';
    $credential_scope = "{$date_stamp}/{$region}/s3/aws4_request";
    $string_to_sign = implode("\n", [
        $algorithm, $amz_date, $credential_scope, hash('sha256', $canonical_request),
    ]);

    $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret, true);
    $k_region = hash_hmac('sha256', $region, $k_date, true);
    $k_service = hash_hmac('sha256', 's3', $k_region, true);
    $signing_key = hash_hmac('sha256', 'aws4_request', $k_service, true);
    $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
    $authorization =
        "{$algorithm} Credential={$access}/{$credential_scope}, " .
        "SignedHeaders={$signed_headers}, Signature={$signature}";

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
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return [false, 0, 'cURL: ' . $error];
    }
    if ($status < 200 || $status >= 300) {
        return [false, $status, substr((string) $response, 0, 400)];
    }
    return [true, $status, ''];
}

function s3_head($access, $secret, $bucket, $region, $key)
{
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

    $canonical_request = implode("\n", [
        'HEAD', $canonical_uri, '', $canonical_headers, $signed_headers, $payload_hash,
    ]);
    $algorithm = 'AWS4-HMAC-SHA256';
    $credential_scope = "{$date_stamp}/{$region}/s3/aws4_request";
    $string_to_sign = implode("\n", [
        $algorithm, $amz_date, $credential_scope, hash('sha256', $canonical_request),
    ]);
    $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret, true);
    $k_region = hash_hmac('sha256', $region, $k_date, true);
    $k_service = hash_hmac('sha256', 's3', $k_region, true);
    $signing_key = hash_hmac('sha256', 'aws4_request', $k_service, true);
    $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
    $authorization =
        "{$algorithm} Credential={$access}/{$credential_scope}, " .
        "SignedHeaders={$signed_headers}, Signature={$signature}";

    $url = 'https://' . $host . $canonical_uri;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_CUSTOMREQUEST => 'HEAD',
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $authorization,
            'Host: ' . $host,
            'x-amz-content-sha256: ' . $payload_hash,
            'x-amz-date: ' . $amz_date,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $status;
}

// Mirror application/helpers/media_helper.php key builder
function adv_s3_safe_name($name)
{
    $name = basename((string) $name);
    $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
    return $name !== '' ? $name : ('file_' . uniqid());
}

function adv_user_media_key($user_id, $category, $entity_id = null, $subfolder = null, $filename = null)
{
    $parts = ['users', (int) $user_id, $category];
    if (in_array($category, ['posts', 'reels', 'stories'], true) && $entity_id !== null && $entity_id !== '') {
        $parts[] = $entity_id;
    }
    if ($subfolder !== null && $subfolder !== '') {
        $parts[] = trim((string) $subfolder, '/');
    }
    if ($filename !== null && $filename !== '') {
        $parts[] = adv_s3_safe_name($filename);
    }
    return implode('/', $parts);
}

$user_id = 999001; // verification-only user prefix
$stamp = date('YmdHis');
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$mp4 = 'ftypisomfake-video-bytes-for-structure-test';

$targets = [
    ['key' => adv_user_media_key($user_id, 'profile', null, null, "profile_{$stamp}.png"), 'body' => $png, 'type' => 'image/png', 'label' => 'profile'],
    ['key' => adv_user_media_key($user_id, 'brand', null, null, "logo_{$stamp}.png"), 'body' => $png, 'type' => 'image/png', 'label' => 'brand'],
    ['key' => adv_user_media_key($user_id, 'posts', 101, 'images', "img_{$stamp}.png"), 'body' => $png, 'type' => 'image/png', 'label' => 'posts/images'],
    ['key' => adv_user_media_key($user_id, 'posts', 101, 'thumbnails', "thumb_{$stamp}.png"), 'body' => $png, 'type' => 'image/png', 'label' => 'posts/thumbnails'],
    ['key' => adv_user_media_key($user_id, 'reels', 202, 'videos', "reel_{$stamp}.mp4"), 'body' => $mp4, 'type' => 'video/mp4', 'label' => 'reels/videos'],
    ['key' => adv_user_media_key($user_id, 'reels', 202, 'thumbnails', "reel_thumb_{$stamp}.png"), 'body' => $png, 'type' => 'image/png', 'label' => 'reels/thumbnails'],
    ['key' => adv_user_media_key($user_id, 'stories', 303, 'media', "story_{$stamp}.png"), 'body' => $png, 'type' => 'image/png', 'label' => 'stories/media'],
    ['key' => adv_user_media_key($user_id, 'stories', 303, 'thumbnails', "story_thumb_{$stamp}.png"), 'body' => $png, 'type' => 'image/png', 'label' => 'stories/thumbnails'],
    ['key' => adv_user_media_key($user_id, 'products', null, null, "product_{$stamp}.png"), 'body' => $png, 'type' => 'image/png', 'label' => 'products'],
    ['key' => adv_user_media_key($user_id, 'chat', null, 'images', "chat_{$stamp}.png"), 'body' => $png, 'type' => 'image/png', 'label' => 'chat/images'],
];

echo "Bucket: {$bucket}\nRegion: {$region}\nBase: {$base_url}\n";
echo "Test user prefix: users/{$user_id}/\n\n";

$ok = 0;
$fail = 0;
$uploaded = [];

foreach ($targets as $t) {
    [$success, $status, $err] = s3_put($access, $secret, $bucket, $region, $t['key'], $t['body'], $t['type']);
    if (!$success) {
        echo "FAIL PUT  [{$t['label']}] HTTP {$status}\n  key={$t['key']}\n  {$err}\n";
        $fail++;
        continue;
    }
    $head = s3_head($access, $secret, $bucket, $region, $t['key']);
    $public = $base_url . '/' . $t['key'];
    if ($head === 200) {
        echo "OK   [{$t['label']}]\n  {$public}\n";
        $ok++;
        $uploaded[] = $t['key'];
    } else {
        echo "WARN [{$t['label']}] PUT ok but HEAD={$head}\n  {$public}\n";
        $ok++;
        $uploaded[] = $t['key'];
    }
}

echo "\n---- summary ----\n";
echo "uploaded_ok={$ok} failed={$fail}\n";

if ($ok > 0) {
    echo "\nExpected tree under users/{$user_id}/:\n";
    echo "  profile/\n  brand/\n  posts/101/images/\n  posts/101/thumbnails/\n  reels/202/videos/\n  reels/202/thumbnails/\n  stories/303/media/\n  stories/303/thumbnails/\n  products/\n  chat/images/\n";
}

exit($fail > 0 ? 1 : 0);
