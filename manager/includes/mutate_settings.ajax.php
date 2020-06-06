<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

if (!isset($_POST['action'])) {
    exit;
}
if (isset($_POST['key']) && preg_match('@[^A-Za-z0-9_\-\./]@', $_POST['key'])) {
    exit;
}
if (isset($_POST['value']) && preg_match('@[^A-Za-z0-9_\-\./]@', $_POST['value'])) {
    exit;
}
if (isset($_POST['lang']) && preg_match('@[^A-Za-z0-9_\s\+\-\./]@', $_POST['lang'])) {
    exit;
}

$post = $modx->db->escape($_POST);
$post_lang = $post['lang'];
$post_key = $post['key'];
$post_value = $post['value'];

if ($_POST['action'] === 'get') {
    echo getStringFromLangFile($post_key, $post_lang);
    return;
}

$output = '';
$tbl_system_settings = $modx->getFullTableName('system_settings');
switch ($_POST['action']) {
    case 'setsetting':
        if (!empty($post_key) && !empty($post_value)) {
            $rs = @ $modx->db->query("REPLACE INTO {$tbl_system_settings} (setting_name, setting_value) VALUES('{$post_key}', '{$post_value}')");
        }
        break;
    case 'updateplugin':
        if ($post_key == '_delete_' && !empty($post_lang)) {
            $rs = @ $modx->db->delete('[+prefix+}site_plugins', "name='{$post_lang}'");
        } elseif (!empty($post_key) && !empty($post_lang) && !empty($post_value)) {
            $rs = @ $modx->db->update(array($post_key => $post_value), '[+prefix+]site_plugins', "name='{$post_lang}'");
        }
        break;
}

if (isset($rs) && !empty($rs)) {
    $output = 'true';
} else {
    $output = 'false';
}

if ($output === 'true') {
    $modx->clearCache();
}

echo $output;


function getStringFromLangFile($key, $lang) {
    $langfile_path = MODX_CORE_PATH . "lang/{$lang}.inc.php";
    if (strpos($langfile_path, '..') !== false || !is_file($langfile_path)) {
        return;
    }

    include($langfile_path);
    if (isset($_lang[$key])) {
        return $_lang[$key];
    } else {
        return '';
    }
}