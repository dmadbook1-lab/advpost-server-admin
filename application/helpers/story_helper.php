<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('story_image_url')) {

    function story_image_url($path)
    {
        if (empty($path)) {
            return "";
        }

        // ❌ only folder, no file
        if (str_ends_with($path, '/story_image/') || str_ends_with($path, '/story_image')) {
            return "";
        }

        // ✅ already full URL
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        // ✅ filename only
        return base_url('assetsNew/images/story_image/' . ltrim($path, '/'));
    }
}