<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
global $_style;

include_once MODX_CORE_PATH . 'helpers.php';
include_once __DIR__ . '/mutate_user/functions.php';

if(!hasUserPermission(request_intvar('a'))) {
    alert()->setError(3);
    alert()->dumpError();
    return;
}

$userid = $modx->input_any('id', 0);

if ($userid && !activeUserCheck($userid)) {
    alert()->dumpError();
    return;
}

if ($_REQUEST['a'] == 12) {
    $user = getUser($userid);
    if(!$user) {
        exit('No user returned while getting username!<p>');
    }
    $_SESSION['itemname'] = $user['username'];
} else {
    $user = array ();
    $_SESSION['itemname'] = "New user";
}

// restore saved form
$formRestored = false;
if ($modx->manager->hasFormValues()) {
    $form_v = $modx->manager->loadFormValues();
    // restore post values
    $user = array_merge($user, $form_v);
    $user['dob'] = ConvertDate($user['dob']);
    $user['username'] = $user['newusername'];
    if (is_array($form_v['allowed_days'])) {
        $user['allowed_days'] = implode(',', $form_v['allowed_days']);
    } else {
        $user['allowed_days'] = '';
    }
}

// include the country list language file
$_country_lang = array();
include_once(MODX_CORE_PATH . 'lang/country/english_country.inc.php');
$countries_path = MODX_CORE_PATH . sprintf('lang/country/%s_country.inc.php', $modx->config['manager_language']);
if($modx->config['manager_language'] !== 'english' && is_file($countries_path)){
    include_once $countries_path;
}

$displayStyle = ($_SESSION['browser'] ==='modern') ? 'table-row' : 'block';
// invoke OnUserFormPrerender event
$tmp = array ('id' => $userid);
$evtOut = $modx->invokeEvent("OnUserFormPrerender", $tmp);
if (is_array($evtOut)) {
    echo implode("", $evtOut);
}
include_once __DIR__ . '/mutate_user/tpl/javascript.php';
include_once __DIR__ . '/mutate_user/tpl/form.php';
