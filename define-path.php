<?php
define('MODX_BASE_PATH', str_replace('\\', '/', __DIR__) . '/');
define('MODX_CACHE_PATH', MODX_BASE_PATH . 'temp/cache/');
if (!is_writable(MODX_BASE_PATH . 'temp/')) {
    die('Error: ' . MODX_BASE_PATH . 'temp/ is not writable.');
}
if (!is_dir(MODX_CACHE_PATH)) {
    mkdir(MODX_CACHE_PATH);
}
