<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
global $_style;

include_once __DIR__ . '/mutate_user/blocked.php';
include_once __DIR__ . '/mutate_user/functions.php';

$action = request_intvar('a');

if (!hasUserPermission($action)) {
    alert()->setError(3);
    alert()->dumpError();
    return;
}

$userid = request_intvar('id');
if ($action === 11) {
    $userid = 0; // 新規は id を使わない
}

if ($userid && !activeUserCheck($userid)) {
    alert()->dumpError();
    return;
}

global $user;
if ($action === 12) {
    $user = getUser($userid);
    if (!$user) {
        exit('No user returned while getting username!<p>');
    }
    $_SESSION['itemname'] = $user['username'];
} else {
    $user = [];
    $_SESSION['itemname'] = 'New user';
}

// restore saved form
$formRestored = false;
if (manager()->hasFormValues()) {
    $form_v = manager()->loadFormValues();
    // 差し戻す値は文字列だけにする（配列は、配列で受ける項目の、文字列だけの配列に限る）
    $form_v = array_filter($form_v, static function ($v, $k) {
        return is_string($v)
            || (is_array($v) && in_array($k, ['user_groups', 'allowed_days'], true) && $v === array_filter($v, 'is_string'));
    }, ARRAY_FILTER_USE_BOTH);
    // restore post values
    $user = array_merge($user, $form_v);
    $user['dob'] = !empty($user['dob']) ? ConvertDate($user['dob']) : '';
    $user['blockeduntil'] = ConvertDate($user['blockeduntil'] ?? '');
    $user['blockedafter'] = ConvertDate($user['blockedafter'] ?? '');
    $user['username'] = $form_v['newusername'] ?? '';
    if (is_array($form_v['allowed_days']??null)) {
        $user['allowed_days'] = implode(',', $form_v['allowed_days']);
    } else {
        $user['allowed_days'] = '';
    }
}
global $usersettings;
$usersettings = $user;

// include the country list language file
$_country_lang = [];
include_once(MODX_CORE_PATH . 'lang/country/english_country.inc.php');
$countries_path = MODX_CORE_PATH . sprintf(
        'lang/country/%s_country.inc.php',
        evo()->config('manager_language')
    );
if (evo()->config('manager_language') !== 'english' && is_file($countries_path)) {
    include_once $countries_path;
}

// invoke OnUserFormPrerender event
$tmp = ['id' => $userid];
$evtOut = evo()->invokeEvent('OnUserFormPrerender', $tmp);
if (is_array($evtOut)) {
    echo implode('', $evtOut);
}
include_once __DIR__ . '/mutate_user/tpl/javascript.php';
include_once __DIR__ . '/mutate_user/tpl/form.php';
include_once __DIR__ . '/mutate_user/tpl/changed_settings.php';
