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
require_once MODX_CORE_PATH . 'settings_form.class.inc.php';

if (!save_settiongs()) {
    echo 'Failed to update setting value!';
    exit;
}

if (post_choicevar('reset_template', [1, 2], 0)) {
    reset_template();
}

cleanup_tv();
fix_pulishedon();
repairDocs();
evo()->clearCache();
setPermission();
header("Location: index.php?a=7&refresh=page");


function setPermission()
{
    $rb_base_dir = str_replace('[(base_path)]', MODX_BASE_PATH, formv('rb_base_dir'));

    if (!is_dir($rb_base_dir . 'images')) {
        mkd($rb_base_dir . 'images');
    }
    if (!is_dir($rb_base_dir . 'files')) {
        mkd($rb_base_dir . 'files');
    }
    if (!is_dir($rb_base_dir . 'media')) {
        mkd($rb_base_dir . 'media');
    }
    $export_dir = str_replace('[(base_path)]', MODX_BASE_PATH, exportDirOrDefault(formv('export_dir')));
    if (!is_dir($export_dir)) {
        mkd($export_dir);
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

// 空にされたらエクスポート先は既定値に戻す
function exportDirOrDefault($value)
{
    return trim((string)$value) === '' ? '[(base_path)]temp/export/' : $value;
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
    if (in_array($key, ['filemanager_path', 'rb_base_dir', 'export_dir'])) {
        $value = postv($key, $default);
        if (!is_string($value)) {
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
    if (post_strvar('friendly_urls') !== '1' || strpos(serverv('SERVER_SOFTWARE', ''), 'IIS') !== false) {
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

/**
 * フォームから送られてくるが、設定ではないキー（保存しない）
 */
function nonSettingKeys()
{
    return [
        'a',
        'csrf_token',
        'reload_site_unavailable',
        'reload_captcha_words',
        'reload_emailsubject',
        'reload_signupemail_message',
        'reload_websignupemail_message',
        'reload_system_email_webreminder_message',
        // 設定画面の補助項目（テンプレートの一括変更、言語ごとの初期文面）
        'reset_template',
        'old_template',
        'captcha_words_default',
        'emailsubject_default',
        'siteunavailable_message_default',
        'system_email_signup_default',
        'system_email_websignup_default',
        'system_email_webreminder_default',
    ];
}

// 文字列として加工してから保存する設定（配列は受け付けない）
function stringSettingKeys()
{
    return [
        'base_url', 'fixed_site_url', 'rb_base_url', 'export_dir', 'rb_base_dir', 'filemanager_path',
        'error_page', 'unauthorized_page', 'site_start', 'manager_language', 'new_file_permissions',
        'new_folder_permissions', 'smtppw', 'topmenu_site', 'topmenu_element', 'topmenu_security',
        'topmenu_user', 'topmenu_tools', 'topmenu_reports',
    ];
}

function save_settiongs()
{
    $default_config = include(MODX_CORE_PATH . 'default.config.php');
    $form_v = postv() + $default_config;
    $form_v['settings_version'] = getNewVersion();

    $savethese = [];
    $non_settings = nonSettingKeys();
    foreach ($form_v as $k => $v) {
        if (in_array($k, $non_settings, true)) {
            continue;
        }
        // 空のキーと 0 のキー（'0' は整数 0 として届く）は設定名にならないので保存しない
        if ($k === '' || $k === 0 || $k === '0') {
            continue;
        }
        // 文字列として扱う設定は、配列で送られたら保存しない（今の値のまま）
        if (is_array($v) && in_array($k, stringSettingKeys(), true)) {
            continue;
        }
        // 欄の形（upload_maxsize の MB など）を保存の形にする。読めない値は保存せず、今の値を残す
        $v = EvoSettingsForm::storedValue((string)$k, $v);
        if ($v === null) {
            continue;
        }
        switch ($k) {
            case 'base_url':
                if ($v !== '' && $v !== '/') {
                    $v = '/' . trim($v, '/') . '/';
                }
                break;
            case 'fixed_site_url':
                // スキーム・ホスト・ポートをそろえ、パスはサイトの設置場所に合わせる。形式が正しくなければ空（自動）にする
                $v = init::normalize_site_url($v, MODX_BASE_URL);
                break;
            case 'site_url':
                // 毎回自動で決まる値なので保存しない（固定するときは fixed_site_url）
                continue 2;
            case 'rb_base_url':
                $v = init::normalize_rb_base_url($v, config('site_url'), MODX_BASE_URL);
                break;
            case 'export_dir':
                $v = exportDirOrDefault($v);
                // no break
            case 'rb_base_dir':
            case 'filemanager_path':
                if ($v !== '[(base_path)]') {
                    $v = rtrim($v, '/') . '/';
                }
                if (strpos($v, MODX_BASE_PATH) === 0) {
                    $v = '[(base_path)]' . substr($v, strlen(MODX_BASE_PATH));
                }
                break;
            case 'error_page':
            case 'unauthorized_page':
                if (trim($v) !== '' && !is_numeric($v)) {
                    $v = post_strvar('site_start');
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

        $savethese[] = sprintf(
            "('%s', '%s')",
            db()->escape($k),
            db()->escape($v)
        );
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
    $mode = post_choicevar('reset_template', [1, 2], 0);
    // テンプレートの id。0（テンプレートなし）も正しい値。設定画面の「(blank)」は value="" で送られるので 0 とする。読めなければ null
    $defaultTemplate = postv('default_template') === '' ? 0 : post_uintvar('default_template');
    if ($defaultTemplate === null) {
        return;
    }
    if ($mode === 1) {
        db()->update(
            ['template' => $defaultTemplate],
            '[+prefix+]site_content',
            where('type', 'document')
        );
        return;
    }
    $oldTemplate = post_uintvar('old_template');
    if ($mode === 2 && $oldTemplate !== null) {
        db()->update(
            ['template' => $defaultTemplate],
            '[+prefix+]site_content',
            where('template', $oldTemplate)
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
