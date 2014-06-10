<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
/**
 * mutate_settings.ajax.php
 * 
 */
require_once(MODX_CORE_PATH . 'protect.inc.php');

$action = preg_replace('/[^A-Za-z0-9_\-\.\/]/', '', $_POST['action']);
$lang   = preg_replace('/[^A-Za-z0-9_\s\+\-\.\/]/', '', $_POST['lang']);
$key    = preg_replace('/[^A-Za-z0-9_\-\.\/]/', '', $_POST['key']);
$value  = preg_replace('/[^A-Za-z0-9_\-\.\/]/', '', $_POST['value']);

$action = $modx->db->escape($action);
$lang = $modx->db->escape($lang);
$key = $modx->db->escape($key);
$value = $modx->db->escape($value);

$str = '';
$emptyCache = false;

if($action == 'get') {
    $langfile = MODX_CORE_PATH . "lang/{$lang}.inc.php";
    if(is_file($langfile)) {
        $str = getLangStringFromFile($langfile, $key);
    }
} elseif($action == 'setsetting') {
	if(!empty($key) && !empty($value)) {
        $sql = "REPLACE INTO ".$modx->getFullTableName("system_settings")." (setting_name, setting_value) VALUES('{$key}', '{$value}');";
		$str = "true";
		if(!@$rs = $modx->db->query($sql)) {
			$str = "false";
        } else {
            $emptyCache = true;
		}
	}
} elseif($action == 'updateplugin') {

    if($key == '_delete_' && !empty($lang)) {
        $str = "true";
        if(!@$rs = $modx->db->delete('[+prefix+}site_plugins', "name='{$lang}'")) {
            $str = 'false';
        } else {
            $emptyCache = true;
        }
    } elseif(!empty($key) && !empty($lang) && !empty($value)) {
        $str = "true";
        $rs = $modx->db->update(array($key=>$value),'[+prefix+]site_plugins',"name='{$lang}'");
        if(!$rs) {
            $str = "false";
        } else {
            $emptyCache = true;
        }
    }
}

if($emptyCache) {
    $modx->clearCache();
}

echo $str;

function getLangStringFromFile($file, $key) {
    include($file);
    return $_lang[$key];
}