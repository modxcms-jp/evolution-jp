<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

$user_id = evo()->getLoginUserID();
if (empty($user_id)) {
    return;
}

if (!empty($user_id)) {
    // Raymond: grab the user settings from the database.
    $rs = db()->select('setting_name, setting_value', '[+prefix+]user_settings', "user='{$user_id}'");
    while ($row = db()->getRow($rs)) {
        $settings[$row['setting_name']] = $row['setting_value'];
        if (isset($modx->config)) {
            $modx->config[$row['setting_name']] = $row['setting_value'];
        }
    }
    extract($settings, EXTR_OVERWRITE);
}
