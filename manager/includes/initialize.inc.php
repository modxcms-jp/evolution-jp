<?php
include_once(__DIR__ . '/helpers.php');
include_once(__DIR__ . '/initialize.functions.inc.php');

init::fix_favicon_req();
init::check_phpvar();
init::fix_request_time();
init::fix_document_root();
init::fix_script_name();
init::fix_server_addr();
init::fix_ssl();

// automatically assign base_path and base_url
if (!defined('MODX_BASE_PATH')) {
    include dirname(__DIR__, 2) . '/define-path.php';
}
$dotenvFile = MODX_BASE_PATH . '.env';
if (is_file($dotenvFile)) {
    require_once __DIR__ . '/dotenv-loader.php';
    $dotenv = new Dotenv($dotenvFile);
    $dotenv->load();
}
if (!defined('MODX_BASE_URL')) {
    define('MODX_BASE_URL', init::get_base_url(MODX_BASE_PATH));
}
if (!defined('MODX_SITE_URL')) {
    define('MODX_SITE_URL', init::get_site_url(MODX_BASE_URL));
}
if (!defined('MODX_CORE_PATH')) {
    define('MODX_CORE_PATH', str_replace('\\', '/', __DIR__) . '/');
}

if (!defined('MODX_MANAGER_PATH')) {
    define('MODX_MANAGER_PATH', MODX_BASE_PATH . 'manager/');
}
if (!defined('MODX_MANAGER_URL')) {
    define('MODX_MANAGER_URL', MODX_SITE_URL . 'manager/');
}

require_once(MODX_CORE_PATH . 'version.inc.php');

if (defined('IN_MANAGER_MODE')) {
    init::init_mgr();
    include_once(__DIR__ . '/csrf_token.php');
}

if (!defined('E_DEPRECATED')) {
    define('E_DEPRECATED', 8192);
}
if (!defined('E_USER_DEPRECATED')) {
    define('E_USER_DEPRECATED', 16384);
}

error_reporting(E_ALL & ~E_NOTICE);

if (!defined('MODX_API_MODE')) {
    set_parser_mode();
}
if (session_id() === '' && php_sapi_name() !== 'cli') {
    startCMSSession();
}
