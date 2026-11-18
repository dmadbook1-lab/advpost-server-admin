<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * AdvPost media helpers
 *
 * Canonical S3 layout (bucket: advpost):
 *
 *   users/{user_id}/
 *     profile/                          profile pictures
 *     brand/                            business logos
 *     posts/{post_id}/
 *       images/
 *       videos/
 *       thumbnails/
 *     reels/{post_id}/
 *       videos/
 *       thumbnails/
 *     stories/{story_id}/
 *       media/
 *       thumbnails/
 *     products/
 *     chat/images|videos/
 *     generated/images|videos/
 *
 * Legacy local files still live under public_html/assetsNew/...
 */

if (!function_exists('adv_media_rel_path')) {
    function adv_media_rel_path($path)
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#https?://[^/]+/(assetsNew/.+)$#i', $path, $m)) {
            return $m[1];
        }
        if (preg_match('#https?://[^/]+/(users/.+)$#i', $path, $m)) {
            return $m[1];
        }
        $rel = ltrim($path, './');
        if (strpos($rel, 'assetsNew/') !== false) {
            $rel = substr($rel, strpos($rel, 'assetsNew/'));
        }
        return $rel;
    }
}

if (!function_exists('adv_media_exists')) {
    function adv_media_exists($path)
    {
        $rel = adv_media_rel_path($path);
        return $rel !== '' && defined('FCPATH') && is_file(FCPATH . $rel);
    }
}

/**
 * Resolve a media URL for API / admin responses.
 * - Absolute http(s) URLs (including S3) are returned as-is
 * - users/... keys resolve via S3 public URL
 * - Local assetsNew paths resolve to local file, then S3, then live fallback
 */
if (!function_exists('adv_media_url')) {
    function adv_media_url($path, $fallback_host = 'https://www.advpost.in/')
    {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $rel = adv_media_rel_path($path);
        if ($rel === '') {
            $rel = ltrim($path, '/');
        }

        // Local file still on disk
        if ($rel !== '' && defined('FCPATH') && is_file(FCPATH . $rel)) {
            return base_url($rel);
        }

        $CI =& get_instance();
        if (isset($CI->load)) {
            $CI->load->library('aws_s3');
            if ($CI->aws_s3->is_ready()) {
                // New per-user keys OR legacy assetsNew keys stored relatively
                if (strpos($rel, 'users/') === 0 || strpos($rel, 'assetsNew/') === 0) {
                    return $CI->aws_s3->public_url($rel);
                }
            }
        }

        return rtrim($fallback_host, '/') . '/' . ltrim($rel, '/');
    }
}

if (!function_exists('adv_s3_safe_name')) {
    function adv_s3_safe_name($name)
    {
        $name = basename((string) $name);
        $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
        return $name !== '' ? $name : ('file_' . uniqid());
    }
}

if (!function_exists('adv_s3_key')) {
    /**
     * Build a consistent S3 object key from path parts.
     */
    function adv_s3_key($parts)
    {
        $clean = [];
        foreach ((array) $parts as $part) {
            $part = trim((string) $part, '/');
            if ($part !== '') {
                $clean[] = $part;
            }
        }
        return implode('/', $clean);
    }
}

if (!function_exists('adv_normalize_post_type')) {
    /**
     * Map app values (photo/reel) to DB values (image/reel).
     */
    function adv_normalize_post_type($post_type)
    {
        $post_type = strtolower(trim((string) $post_type));
        if ($post_type === '' || $post_type === 'photo' || $post_type === 'general' || $post_type === 'image') {
            return 'image';
        }
        if ($post_type === 'reel' || $post_type === 'video') {
            return 'reel';
        }
        return $post_type;
    }
}

if (!function_exists('adv_user_media_key')) {
    /**
     * Canonical per-user S3 key builder.
     *
     * Categories:
     *   profile | brand | posts | reels | stories | products | chat | generated
     *
     * Examples:
     *   adv_user_media_key(12, 'profile', null, 'images', 'pic.jpg')
     *   → users/12/profile/pic.jpg
     *
     *   adv_user_media_key(12, 'posts', 99, 'images', 'a.jpg')
     *   → users/12/posts/99/images/a.jpg
     *
     *   adv_user_media_key(12, 'reels', 99, 'videos', 'a.mp4')
     *   → users/12/reels/99/videos/a.mp4
     *
     *   adv_user_media_key(12, 'stories', 55, 'media', 'a.jpg')
     *   → users/12/stories/55/media/a.jpg
     */
    function adv_user_media_key($user_id, $category, $entity_id = null, $subfolder = null, $filename = null)
    {
        $user_id = (int) $user_id;
        $category = strtolower(trim((string) $category));
        $parts = ['users', $user_id, $category];

        // entity folder for posts / reels / stories
        if (in_array($category, ['posts', 'reels', 'stories'], true) && $entity_id !== null && $entity_id !== '') {
            $parts[] = $entity_id;
        }

        if ($subfolder !== null && $subfolder !== '') {
            $parts[] = trim((string) $subfolder, '/');
        }

        if ($filename !== null && $filename !== '') {
            $parts[] = adv_s3_safe_name($filename);
        }

        return adv_s3_key($parts);
    }
}

if (!function_exists('adv_s3_upload_path')) {
    /**
     * Upload a local filesystem path to S3.
     * @return string|false Public S3 URL
     */
    function adv_s3_upload_path($local_path, $key, $content_type = null)
    {
        $CI =& get_instance();
        $CI->load->library('aws_s3');
        $url = $CI->aws_s3->upload_file($local_path, $key, $content_type);
        if ($url === false) {
            log_message('error', 'S3 upload failed: ' . $CI->aws_s3->get_last_error());
        }
        return $url;
    }
}

if (!function_exists('adv_s3_upload_bytes')) {
    function adv_s3_upload_bytes($bytes, $key, $content_type = 'application/octet-stream')
    {
        $CI =& get_instance();
        $CI->load->library('aws_s3');
        $url = $CI->aws_s3->upload_bytes($bytes, $key, $content_type);
        if ($url === false) {
            log_message('error', 'S3 upload failed: ' . $CI->aws_s3->get_last_error());
        }
        return $url;
    }
}

if (!function_exists('adv_s3_upload_file_field')) {
    /**
     * Upload a $_FILES[...] entry to S3.
     * @return string|false
     */
    function adv_s3_upload_file_field(array $file, $key, $content_type = null)
    {
        $CI =& get_instance();
        $CI->load->library('aws_s3');
        $url = $CI->aws_s3->upload_uploaded_file($file, $key, $content_type);
        if ($url === false) {
            log_message('error', 'S3 upload failed: ' . $CI->aws_s3->get_last_error());
        }
        return $url;
    }
}

if (!function_exists('adv_s3_last_error')) {
    function adv_s3_last_error()
    {
        $CI =& get_instance();
        $CI->load->library('aws_s3');
        return $CI->aws_s3->get_last_error();
    }
}

if (!function_exists('adv_profile_pic_url')) {
    /**
     * Resolve a user profile picture for API responses.
     * Accepts full S3 URL, relative assetsNew/users path, or bare filename.
     */
    function adv_profile_pic_url($profile_pic, $default = null)
    {
        $default = $default ?: base_url('assetsNew/images/profile_pic/default.png');
        $profile_pic = trim((string) $profile_pic);
        if ($profile_pic === '') {
            return $default;
        }
        if (preg_match('#^https?://#i', $profile_pic)) {
            return $profile_pic;
        }
        if (strpos($profile_pic, 'assetsNew/') !== false || strpos($profile_pic, 'users/') === 0) {
            return adv_media_url($profile_pic);
        }
        // Bare filename from older records
        return adv_media_url('assetsNew/images/profile_pic/' . ltrim($profile_pic, '/'));
    }
}

if (!function_exists('adv_story_media_url')) {
    /**
     * Resolve story media URL (S3, assetsNew, or bare legacy filename).
     */
    function adv_story_media_url($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (strpos($url, 'assetsNew/') !== false || strpos($url, 'users/') === 0) {
            return adv_media_url($url);
        }
        return adv_media_url('assetsNew/images/story_image/' . ltrim($url, '/'));
    }
}
