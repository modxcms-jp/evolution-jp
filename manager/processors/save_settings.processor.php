<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('settings')) {
    alert()->setError(3);
    alert()->dumpError();
}

$warnings = warnings();
if ($warnings) {
    manager()->saveFormValues(17);
    evo()->webAlertAndQuit(implode("\n", $warnings), 'index.php?a=17');
    exit;
}
if (!save_settiongs()) {
    echo 'Failed to update setting value!';
    exit;
}

if (formv('reset_template')) {
    reset_template();
}

cleanup_tv();
evo()->clearCache();
setPermission();
header("Location: index.php?a=7&r=9");


function setPermission()
{
    if (!is_dir(formv('rb_base_dir') . 'images')) {
        mkd(formv('rb_base_dir') . 'images');
    }
    if (!is_dir(formv('rb_base_dir') . 'files')) {
        mkd(formv('rb_base_dir') . 'files');
    }
    if (!is_dir(formv('rb_base_dir') . 'media')) {
        mkd(formv('rb_base_dir') . 'media');
    }
    if (!is_dir(formv('rb_base_dir') . 'flash')) {
        mkd(formv('rb_base_dir') . 'flash');
    }
    if (!is_dir(MODX_BASE_PATH . 'temp/export')) {
        mkd(MODX_BASE_PATH . 'temp/export');
    }
    if (!is_dir(MODX_BASE_PATH . 'temp/backup')) {
        mkd(MODX_BASE_PATH . 'temp/backup');
    }

    if (is_writable(MODX_CORE_PATH . 'config.inc.php')) {
        @chmod(MODX_CORE_PATH . 'config.inc.php', 0444);
    }
}

function mkd($path)
{
    $rs = @mkdir($path, 0777, true);
    if ($rs) {
        $rs = @chmod($path, 0777);
    }
    return $rs;
}

function setModifiedConfig($form_v, $defaut_v)
{
    if ($form_v === $defaut_v) {
        return $defaut_v;
    }
    return '* ' . $form_v;
}

function formv($key, $default = null)
{
    if (in_array($key, array('filemanager_path', 'rb_base_dir'))) {
        return str_replace('[(base_path)]', MODX_BASE_PATH, postv($key));
    }
    return postv($key, $default);
}

function warnings()
{
    $warnings = array();
    if (!is_dir(formv('filemanager_path'))) {
        $warnings[] = lang('configcheck_filemanager_path');
    }
    if (!is_dir(formv('rb_base_dir'))) {
        $warnings[] = lang('configcheck_rb_base_dir');
    }
    if (formv('friendly_urls') != 1 || strpos(serverv('SERVER_SOFTWARE'), 'IIS') !== false) {
        return $warnings;
    }

    $htaccess = MODX_BASE_PATH . '.htaccess';
    $sample_htaccess = MODX_BASE_PATH . 'sample.htaccess';
    $dir = '/' . trim(evo()->config['base_url'], '/');
    if (is_file($htaccess)) {
        $_ = file_get_contents($htaccess);
        if (strpos($_, 'RewriteEngine') === false) {
            $warnings[] = lang('settings_friendlyurls_alert2');
            return $warnings;
        }
        if (is_writable($htaccess)) {
            $rs = file_put_contents(
                $htaccess,
                preg_replace('@RewriteBase.+@', "RewriteBase " . $dir, $_)
            );
            if (!$rs) {
                $warnings[] = lang('settings_friendlyurls_alert2');
            }
        }
        return $warnings;
    }
    if (is_file($sample_htaccess)) {
        if (!rename($sample_htaccess, $htaccess)) {
            $warnings[] = lang('settings_friendlyurls_alert');
        } elseif (evo()->config['base_url'] !== '/') {
            $_ = preg_replace(
                '@RewriteBase.+@', "RewriteBase " . $dir,
                file_get_contents($htaccess)
            );
            if (!@file_put_contents($htaccess, $_)) {
                $warnings[] = lang('settings_friendlyurls_alert2');
            }
        }
    }
    return $warnings;
}

function save_settiongs()
{
    $default_config = include(MODX_CORE_PATH . 'default.config.php');
    $form_v = $_POST;
    $savethese = array();
    foreach ($form_v as $k => $v) {
        switch ($k) {
            case 'base_url':
                if ($v !== '' && $v !== '/') {
                    $v = '/' . trim($v, '/') . '/';
                }
                break;
            case 'site_url':
            case 'rb_base_dir':
            case 'rb_base_url':
            case 'filemanager_path':
                $v = formv($k);
                if ($v !== '') {
                    $v = rtrim($v, '/') . '/';
                }
                break;
            case 'error_page':
            case 'unauthorized_page':
                if (trim($v) !== '' && !is_numeric($v)) {
                    $v = formv('site_start');
                }
                break;

            case 'lst_custom_contenttype':
            case 'txt_custom_contenttype':
                // Skip these
                continue 2;
                break;
            case 'manager_language':
                if (!is_file(MODX_CORE_PATH . "lang/{$v}.inc.php")) {
                    $v = 'english';
                }
                break;
            case 'new_file_permissions':
            case 'new_folder_permissions':
                if (strlen($v) == 3) {
                    $v = '0' . $v;
                }
                break;
            case 'smtppw':
                if ($v !== '********************') {
                    $v = trim($v);
                    $v = base64_encode($v) . substr(str_shuffle('abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'),
                            0, 7);
                    $v = str_replace('=', '%', $v);
                } else {
                    $k = '';
                }
                break;
            case 'a':
            case 'reload_site_unavailable':
            case 'reload_captcha_words':
            case 'reload_emailsubject':
            case 'reload_signupemail_message':
            case 'reload_websignupemail_message':
            case 'reload_system_email_webreminder_message':
                $k = '';
                break;
            case 'topmenu_site':
            case 'topmenu_element':
            case 'topmenu_security':
            case 'topmenu_user':
            case 'topmenu_tools':
            case 'topmenu_reports':
                $v = setModifiedConfig($v, $default_config[$k]);
                break;
            default:
                break;
        }
        $v = is_array($v) ? implode(',', $v) : $v;

        if (!empty($k)) {
            $savethese[] = sprintf(
                "('%s', '%s')",
                db()->escape($k),
                db()->escape($v)
            );
        }
    }
    // Run a single query to save all the values
    return db()->query(
        sprintf(
            "REPLACE INTO %s (setting_name, setting_value) VALUES %s",
            evo()->getFullTableName('system_settings'),
            implode(', ', $savethese)
        )
    );
}

function reset_template()
{
    if (formv('reset_template') == 1) {
        db()->update(
            array('template' => formv('default_template')),
            '[+prefix+]site_content',
            where('type', 'document')
        );
        return;
    }
    if (formv('reset_template') == 2) {
        db()->update(
            array('template' => formv('default_template')),
            '[+prefix+]site_content',
            where('template', formv('old_template'))
        );
    }
}

function cleanup_tv()
{
    $rs = db()->select(
        'DISTINCT contentid',
        array(
            '[+prefix+]site_tmplvar_contentvalues tvc',
            'LEFT JOIN [+prefix+]site_content doc ON doc.id=tvc.contentid'
        ),
        'doc.id IS NULL'
    );
    if (!db()->count($rs)) {
        return;
    }
    $docs = array();
    while ($row = db()->getRow($rs)) {
        $docs[] = $row['contentid'];
    }
    db()->delete(
        '[+prefix+]site_tmplvar_contentvalues',
        where_in('contentid', $docs)
    );
}
