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
fix_pulishedon();
repairDocs();
evo()->clearCache();
setPermission();
header("Location: index.php?a=7&r=10");


function setPermission()
{
    $rb_base_dir = str_replace('[(base_path)]', MODX_BASE_PATH, formv('rb_base_dir'));

    if (is_string($rb_base_dir) && $rb_base_dir !== '') {
        if (!is_dir($rb_base_dir . 'images')) {
            mkd($rb_base_dir . 'images');
        }
        if (!is_dir($rb_base_dir . 'files')) {
            mkd($rb_base_dir . 'files');
        }
        if (!is_dir($rb_base_dir . 'media')) {
            mkd($rb_base_dir . 'media');
        }
    }
    if (!is_dir(MODX_BASE_PATH . 'temp/export')) {
        mkd(MODX_BASE_PATH . 'temp/export');
    }
    if (!is_dir(MODX_BASE_PATH . 'temp/backup')) {
        mkd(MODX_BASE_PATH . 'temp/backup');
    }

    if (is_writable(MODX_CORE_PATH . 'config.inc.php')) {
        if (!chmod(MODX_CORE_PATH . 'config.inc.php', 0444)) {
            echo 'Failed to change permissions for config.inc.php';
        }
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
    return ltrim($form_v, '* ');
}

function formv($key, $default = null)
{
    if (in_array($key, ['filemanager_path', 'rb_base_dir'])) {
        $value = postv($key, $default);
        if ($value === null) {
            $value = '';
        }
        return str_replace(MODX_BASE_PATH, '[(base_path)]', $value);
    }
    return postv($key, $default);
}

function warnings()
{
    $warnings = [];
    if (!is_dir(str_replace('[(base_path)]', MODX_BASE_PATH, formv('filemanager_path')))) {
        $warnings[] = lang('configcheck_filemanager_path');
    }
    if (!is_dir(str_replace('[(base_path)]', MODX_BASE_PATH, formv('rb_base_dir')))) {
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

function getNewVersion() {
    include MODX_CORE_PATH . 'version.inc.php';
    return $modx_version;
}

function save_settiongs()
{
    $default_config = include(MODX_CORE_PATH . 'default.config.php');
    $form_v = $_POST + $default_config;
    $form_v['settings_version'] = getNewVersion();

    $savethese = [];
    foreach ($form_v as $k => $v) {
        if (in_array($k, ['filemanager_path', 'rb_base_dir'], true)) {
            continue;
        }
        switch ($k) {
            case 'base_url':
                if ($v !== '' && $v !== '/') {
                    $v = '/' . trim($v, '/') . '/';
                }
                break;
            case 'site_url':
            case 'rb_base_url':
                $v = rtrim($v, '/') . '/';
                break;
            case 'rb_base_dir':
            case 'filemanager_path':
                if ($v !== '[(base_path)]') {
                    $v = rtrim($v, '/') . '/';
                }
                if (strpos($v, MODX_BASE_PATH) === 0) {
                    $v = '[(base_path)]' . substr($v, strlen('[(base_path)]'));
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
                if ($v === '********************') {
                    continue 2;
                }
                $v = trim($v);
                $v = base64_encode($v) . substr(str_shuffle('abcdefghjkmnpqrstuvxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'),
                        0, 7);
                $v = str_replace('=', '%', $v);
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
            ['template' => formv('default_template')],
            '[+prefix+]site_content',
            where('type', 'document')
        );
        return;
    }
    if (formv('reset_template') == 2) {
        db()->update(
            ['template' => formv('default_template')],
            '[+prefix+]site_content',
            where('template', formv('old_template'))
        );
    }
}

function cleanup_tv()
{
    $rs = db()->select(
        'DISTINCT contentid',
        [
            '[+prefix+]site_tmplvar_contentvalues tvc',
            'LEFT JOIN [+prefix+]site_content doc ON doc.id=tvc.contentid'
        ],
        'doc.id IS NULL'
    );
    if (!db()->count($rs)) {
        return;
    }
    $docs = [];
    while ($row = db()->getRow($rs)) {
        $docs[] = $row['contentid'];
    }
    db()->delete(
        '[+prefix+]site_tmplvar_contentvalues',
        where_in('contentid', $docs)
    );
}

function fix_pulishedon() {
    db()->update(
        'publishedon=editedon',
        '[+prefix+]site_content',
        [
            "published=1 AND deleted=0 AND publishedon=0",
            "AND pub_date<".request_time(),
            sprintf(
                "AND (unpub_date=0 OR unpub_date>'%s')",
                request_time()
            )
        ]
    );
}

function repairDocs() {
    db()->update(
        'editedon=createdon',
        '[+prefix+]site_content',
        'editedon=0'
    );
}
