<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

// get the settings from the database.
$settings = [];
if ($modx && count($modx->config) > 0) {
    $settings = $modx->config;
} else {
    $rs = db()->select('setting_name, setting_value', '[+prefix+]system_settings');
    while ($row = db()->getRow($rs)) {
        $settings[$row['setting_name']] = $row['setting_value'];
    }
}

extract($settings, EXTR_OVERWRITE);

// setup default site id - new installation should generate a unique id for the site.
if (!isset($site_id)) {
    $site_id = "MzGeQ2faT4Dw06+U49x3";
}
