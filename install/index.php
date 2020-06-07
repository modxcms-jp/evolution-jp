<?php
/**
 * MODX Installer
 */

error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 1);
header("Content-Type: text/html; charset=utf-8");

define('MODX_API_MODE', true);
define('MODX_BASE_PATH', str_replace('\\', '/', dirname(__DIR__)) . '/');
define('MODX_SETUP_PATH', MODX_BASE_PATH . 'install/');

include_once(MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;

require_once(MODX_BASE_PATH . 'manager/includes/version.inc.php');
$cmsName = 'MODX';
$cmsVersion = $modx_branch . ' ' . $modx_version;
$moduleRelease = $modx_release_date;

require_once(MODX_BASE_PATH . 'manager/includes/default.config.php');
require_once(MODX_SETUP_PATH . 'functions.php');

if (!install_sessionCheck()) {
    echo $modx->parseText(
        file_get_contents(MODX_SETUP_PATH . 'tpl/session_problem.tpl')
        , includeLang(lang_name())
    );
    exit;
}

sessionv('*prevAction', sessionv('currentAction', ''));
$action = anyv('action', 'mode');
sessionv('*currentAction', $action);

if ($action === 'mode') {
    sessionv('*is_upgradeable', isUpGradeable());
}

if (sessionv('database_server')) {
    db()->prop('*dbname', sessionv('dbase'));
    db()->prop('*table_prefix', sessionv('table_prefix', 'modx_'));
    db()->prop('*connection_method', sessionv('database_connection_method'));
    db()->prop('*charset', sessionv('database_charset', 'utf8'));
    db()->connect(
        sessionv('database_server')
        , sessionv('database_user')
        , sessionv('database_password')
    );
}

$_lang = includeLang(lang_name());

$errors = 0;

$ph = ph();
$ph = array_merge($ph, $_lang);
$ph['install_language'] = lang_name();

ob_start();
if (!@include(sprintf('%sactions/%s.php', MODX_SETUP_PATH, $action))) {
    exit (sprintf(
        'Invalid install action attempted. [action=%s]'
        , $action
    ));
}
$ph['content'] = ob_get_contents();
ob_end_clean();

echo evo()->parseText(
    file_get_contents(MODX_SETUP_PATH . 'tpl/template.tpl')
    , $ph
);
