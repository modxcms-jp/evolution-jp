<?php
define('MODX_BASE_PATH', str_replace('\\', '/', __DIR__) . '/');
define('MODX_CACHE_PATH', MODX_BASE_PATH . 'temp/cache/');

if (!is_dir(rtrim(MODX_CACHE_PATH, '/'))) {
    mkdir(rtrim(MODX_CACHE_PATH, '/'));
}
