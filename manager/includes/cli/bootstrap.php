<?php

if (php_sapi_name() !== 'cli') {
    exit('CLI only.');
}

if (!defined('EVO_CLI')) {
    define('EVO_CLI', true);
}
if (!defined('MODX_API_MODE')) {
    define('MODX_API_MODE', true);
}

if (!isset($_SERVER['REQUEST_TIME_FLOAT'])) {
    $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
}
if (!isset($_SERVER['REQUEST_TIME'])) {
    $_SERVER['REQUEST_TIME'] = time();
}
if (!isset($_SERVER['SCRIPT_NAME'])) {
    $_SERVER['SCRIPT_NAME'] = '/evo';
}
if (!isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = '/evo';
}
if (!isset($_SERVER['SERVER_NAME'])) {
    $_SERVER['SERVER_NAME'] = 'localhost';
}
if (!isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}
if (!isset($_SERVER['SERVER_PORT'])) {
    $_SERVER['SERVER_PORT'] = '80';
}
if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
}

$basePath = dirname(__DIR__, 3) . '/';
$definePath = $basePath . 'define-path.php';
if (!defined('MODX_BASE_PATH')) {
    if (!is_file($definePath)) {
        fwrite(STDERR, "define-path.php not found: {$definePath}\n");
        exit(1);
    }
    require_once $definePath;
}

if (!defined('MODX_BASE_URL')) {
    define('MODX_BASE_URL', '/');
}
if (!defined('MODX_SITE_URL')) {
    define('MODX_SITE_URL', 'http://localhost/');
}

if (is_file(MODX_BASE_PATH . 'autoload.php')) {
    include_once MODX_BASE_PATH . 'autoload.php';
}

if (!extension_loaded('mysqli')) {
    fwrite(STDERR, "PHP extension 'mysqli' is not available.\\n");
    exit(1);
}

include_once MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php';
$modx = new DocumentParser;
$modx->mstart = memory_get_usage();
$modx->error_reporting = 1;
$modx->getSettings();

define('EVO_CLI_PATH', __DIR__ . '/');
define('EVO_CLI_COMMANDS_PATH', EVO_CLI_PATH . 'commands/');
require_once EVO_CLI_PATH . 'cli-helpers.php';
