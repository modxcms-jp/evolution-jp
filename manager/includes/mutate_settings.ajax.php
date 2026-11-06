<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}

if (postv('action') === null) {
    exit;
}
// key・value・lang は文字列だけを受ける（配列は拒否）
if (!is_string(postv('key', '')) || !is_string(postv('value', '')) || !is_string(postv('lang', ''))) {
    exit;
}
if (preg_match('@[^A-Za-z0-9_\-\./]@', postv('key', ''))) {
    exit;
}
if (preg_match('@[^A-Za-z0-9_\-\./]@', postv('value', ''))) {
    exit;
}
if (preg_match('@[^A-Za-z0-9_\s\+\-\./]@', postv('lang', ''))) {
    exit;
}

// 上で許可した文字だけなので引用符は含まないが、DB に渡す文字列の組み立ての前に念のためエスケープする
$post_lang = db()->escape(postv('lang', ''));
$post_key = db()->escape(postv('key', ''));
$post_value = db()->escape(postv('value', ''));

if (postv('action') === 'get') {
    echo getStringFromLangFile($post_key, $post_lang);
    return;
}

$output = '';
$tbl_system_settings = evo()->getFullTableName('system_settings');
switch (postv('action')) {
    case 'setsetting':
        if (strpos(postv('key', ''), '_hide_configcheck_') === 0 && postv('value', '') === '1') {
            $rs = @ db()->query("REPLACE INTO {$tbl_system_settings} (setting_name, setting_value) VALUES('{$post_key}', '{$post_value}')");
        }
        break;
    case 'updateplugin':
        if ($post_key == '_delete_') {
            if (!empty($post_lang) && evo()->hasPermission('delete_plugin')) {
                $rs = @ db()->delete('[+prefix+]site_plugins', "name='{$post_lang}'");
            }
        } elseif ($post_key === 'disabled' && $post_value === '1' && !empty($post_lang) && evo()->hasPermission('save_plugin')) {
            $rs = @ db()->update([$post_key => $post_value], '[+prefix+]site_plugins', "name='{$post_lang}'");
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


function getStringFromLangFile($key, $lang)
{
    if (!preg_match('/^[\w\-]+$/', $lang) || !is_file(MODX_CORE_PATH . "lang/{$lang}.inc.php")) {
        return '';
    }
    return evo()->getLexicon($lang)[$key] ?? '';
}
