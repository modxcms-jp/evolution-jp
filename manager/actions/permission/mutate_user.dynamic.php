<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
global $_style;

include_once __DIR__ . '/mutate_user/form_values.php';
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
    // 差し戻す値は文字列だけにする（配列は、配列で受ける項目だけ）
    $form_v = restorableFormValues($form_v, ['user_groups', 'allowed_days']);
    // restore post values
    $user = array_merge($user, $form_v);
    // 日付は解釈できればタイムスタンプに、できなければ入力の文字列のまま残す（入力欄は dateInputValue() で出す）
    foreach (['dob', 'blockeduntil', 'blockedafter'] as $dateField) {
        $user[$dateField] = manager()->restoredDateValue($user[$dateField] ?? '');
    }
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
