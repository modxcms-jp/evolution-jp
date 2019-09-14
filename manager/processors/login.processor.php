<?php
if(!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
    header('HTTP/1.0 404 Not Found');exit;
}

include_once(__DIR__ . '/login.processor.functions.php');

$self = 'manager/processors/login.processor.php';
$base_path = str_replace($self,'',str_replace('\\','/',__FILE__));
define('IN_MANAGER_MODE', 'true');
define('MODX_API_MODE',true);
include_once($base_path.'manager/includes/document.parser.class.inc.php');
$modx = new DocumentParser;
$modx->config['login_by'] = 'username,email';

if(strpos(urldecode($modx->server_var('REQUEST_URI')),"'")!==false) {
    jsAlert('This is illegal login.');
    return;
}

include_once(MODX_CORE_PATH . 'helpers.php');

if (!$modx->session_var('SystemAlertMsgQueque')) {
    $_SESSION['SystemAlertMsgQueque'] = array();
}

$modx->SystemAlertMsgQueque = &$_SESSION['SystemAlertMsgQueque'];
$_SESSION['safeMode'] = input('safeMode');

// invoke OnBeforeManagerLogin event
OnBeforeManagerLogin();

if(!checkBlockedUser()) {
    return;
}
if(!checkAllowedIp()) {
    return;
}
if(!checkAllowedDays()) {
    return;
}

$modx->loadExtension('ManagerAPI');

// invoke OnManagerAuthentication event
$rt = OnManagerAuthentication();
if (!$rt) {
    if(!loginByForm()) {
        jsAlert(alert()->errors[901]);
        failedLogin();
        return;
    }
    if(!checkCaptcha()) {
        return;
    }
}

managerLogin();
OnManagerLogin();

// check if we should redirect user to a web page
if(user_conf('manager_login_startup',0)) {
    $header = 'Location: '.$modx->makeUrl(user_conf('manager_login_startup',0));
    if($modx->input_post('ajax')) {
        exit($header);
    }
    header($header);
    return;
}

if($modx->session_var('save_uri')) {
    $uri = $modx->session_var('save_uri');
    unset($_SESSION['save_uri']);
} else {
    $uri = MODX_MANAGER_URL;
}
$header = 'Location: ' . $uri;
if($modx->input_post('ajax')==1) {
    exit($header);
}
header($header);
