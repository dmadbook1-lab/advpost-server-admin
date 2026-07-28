<?php
/**
 * Router for PHP built-in server so CodeIgniter routes work.
 * Start with:
 *   php -S localhost:8080 router.php
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false; // serve the requested resource as-is
}

require_once __DIR__ . '/index.php';
