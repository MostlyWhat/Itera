<?php

/*
 * Router for the PHP built-in web server on Wasmer Edge.
 *
 * The server sends each request to this file. The file returns false for
 * existing public files, so that the server sends them as static assets.
 * The file sends all other requests to the Laravel front controller.
 *
 * The package file system can be read-only. If the storage directory is not
 * writable, this file moves Laravel storage to a temporary directory.
 */

$publicPath = __DIR__.'/public';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if ($uri !== '/' && ! str_contains($uri, '..') && is_file($publicPath.$uri)) {
    return false;
}

if (! isset($_SERVER['LARAVEL_STORAGE_PATH']) && ! is_writable(__DIR__.'/storage/framework')) {
    $storagePath = sys_get_temp_dir().'/itera-storage';

    foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
        if (! is_dir($storagePath.'/'.$directory)) {
            @mkdir($storagePath.'/'.$directory, 0755, true);
        }
    }

    $_SERVER['LARAVEL_STORAGE_PATH'] = $storagePath;
}

$_SERVER['SCRIPT_FILENAME'] = $publicPath.'/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require $publicPath.'/index.php';
