<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

global $default_config, $settings_version;

db()->importSql(MODX_CORE_PATH . 'upgrades/upd_db_structure.sql', false);
$default_config = include_once(MODX_CORE_PATH . 'default.config.php');

run_update($settings_version);
evo()->clearCache();

function run_update($pre_version) {
    global $modx_version;

    $pre_version = str_replace(
        array('j', 'rc', '-r')
        , array('', 'RC', '-')
        , strtolower($pre_version)
    );

    if (version_compare($pre_version, '1.0.5') < 0) {
        update_tbl_system_settings();
        $msg = 'Update 1.0.5 to ' . $modx_version;
        evo()->logEvent(0, 1, $msg, $msg);
    }

    if (version_compare($pre_version, '1.0.6') < 0) {
        update_config_custom_contenttype();
        update_config_default_template_method();
        $msg = 'Update 1.0.6 to ' . $modx_version;
        evo()->logEvent(0, 1, $msg, $msg);
    }

    if (version_compare($pre_version, '1.0.7') < 0) {
        disableLegacyPlugins();
        $msg = 'Update 1.0.7 to ' . $modx_version;
        evo()->logEvent(0, 1, $msg, $msg);
    }

    if (0 < version_compare($pre_version, '1.0.4') && version_compare($pre_version, '1.0.7') < 0) {
        delete_actionphp();
        $msg = 'Delete action.php is success';
        evo()->logEvent(0, 1, $msg, $msg);
    }

    update_tbl_user_roles();
    disableOldCarbonTheme();
    disableOldFckEditor();
    updateTopMenu();
}

function disableOldCarbonTheme() {
    global $default_config;

    $old_manager_theme = evo()->config['manager_theme'];
    $old_manager_dir = MODX_MANAGER_PATH . sprintf('media/style/%s/', $old_manager_theme);

    if (
        is_dir($old_manager_dir . 'manager')
        || is_file($old_manager_dir . 'sysalert_style.php')
        || !is_file($old_manager_dir . 'style.php')
        || ($old_manager_theme === 'MODxCarbon' && !is_dir(MODX_MANAGER_PATH . 'media/style/MODxCarbon/images/icons/32x'))
    ) {
        evo()->regOption('manager_theme', $default_config['manager_theme']);
        $msg = "古い仕様の管理画面テーマを無効にしました";
        evo()->logEvent(0, 1, $msg, $msg);
    }
}

function disableOldFckEditor() {
    global $default_config;

    $tpl_path = MODX_BASE_PATH . 'assets/plugins/fckeditor/plugin.fckeditor.tpl';
    if (!is_file($tpl_path)) {
        return;
    }
    $file = file_get_contents($tpl_path);
    if (strpos($file, 'FCKeditor v2.1.1') === false) {
        return;
    }
    evo()->regOption('which_editor', $default_config['which_editor']);
    $msg = "FCKeditorプラグインを無効にしました";
    evo()->logEvent(0, 1, $msg, $msg);
}

function disableLegacyPlugins() {
    db()->update("`disabled`='1'", '[+prefix+]site_plugins', "`name`='Bindings機能の有効無効'");
    db()->update("`disabled`='1'", '[+prefix+]site_plugins', "`name`='Bottom Button Bar'");
}

function update_config_custom_contenttype() {
    $search = array(
        '',
        'text/css,text/html,text/javascript,text/plain,text/xml',
        'application/rss+xml,application/pdf,application/msword,application/excel,text/html,text/css,text/xml,text/javascript,text/plain');
    foreach ($search as $v) {
        if ($v !== evo()->config['custom_contenttype']) {
            continue;
        }
        evo()->regOption(
            'custom_contenttype'
            , 'application/rss+xml,application/pdf,application/vnd.ms-word,application/vnd.ms-excel,text/html,text/css,text/xml,text/javascript,text/plain,application/json'
        );
    }
}

function update_config_default_template_method() {
    global $auto_template_logic;

    $rs  = db()->select(
        'properties,disabled'
        , '[+prefix+]site_plugins'
        , "`name`='Inherit Parent Template' AND disabled=0"
    );
    $row = db()->getRow($rs);
    if ($row) {
        db()->update(
            "`disabled`='1'"
            , '[+prefix+]site_plugins'
            , "`name` IN ('Inherit Parent Template')"
        );
    }
    if (!$row || !isset(evo()->config['auto_template_logic'])) {
        $auto_template_logic = 'sibling';
        return;
    }

    if ($row['disabled'] == 1) {
        $auto_template_logic = 'sibling';
        return;
    }

    $properties = evo()->parseProperties($row['properties']);
    if (!isset($properties['inheritTemplate'])) {
        return;
    }

    if ($properties['inheritTemplate'] !== 'From First Sibling') {
        return;
    }
    $auto_template_logic = 'sibling';
}

function update_tbl_user_roles() {
    db()->update(
        array(
            'view_unpublished' => '1',
            'publish_document' => '1',
            'move_document'    => '1',
            'edit_chunk'       => '1',
            'new_chunk'        => '1',
            'save_chunk'       => '1',
            'delete_chunk'     => '1',
            'import_static'    => '1',
            'export_static'    => '1',
            'empty_trash'      => '1',
            'remove_locks'     => '1',
            'view_schedule'    => '1',
        )
        , '[+prefix+]user_roles'
        , "`save_role`='1'"
    );
}

function update_tbl_system_settings() {
    global $use_udperms;
    if (evo()->config('validate_referer') === '00') {
        evo()->regOption('validate_referer', '0');
    }
    if (evo()->config('upload_maxsize') === '1048576') {
        evo()->regOption('upload_maxsize', '');
    }
    if (evo()->config('emailsender') === 'you@example.com') {
        evo()->regOption('emailsender', sessionv('mgrEmail'));
    }

    $rs = db()->select('*', '[+prefix+]document_groups');
    $use_udperms  = (db()->count($rs) == 0) ? '0' : '1';
    evo()->config['use_udperms'] = evo()->regOption(
        'use_udperms'
        , $use_udperms
    );
}

function delete_actionphp() {
    $path = MODX_BASE_PATH . 'action.php';
    if (!is_file($path)) {
        return;
    }
    if (strpos(file_get_contents($path), 'if(strpos($path,MODX_MANAGER_PATH)!==0)') !== false) {
        return;
    }

    @unlink(evo()->config['base_path'] . 'action.php');
    $msg = '脆弱性を持つaction.phpを削除しました';
    evo()->logEvent(0, 1, $msg, $msg);
}

function updateTopMenu() {
    if (!evo()->config('topmenu_site')) {
        return;
    }

    if (evo()->config('topmenu_site') !== 'home,preview,refresh_site,search,add_resource,add_weblink') {
        return;
    }

    evo()->regOption(
        'topmenu_site'
        , 'home,preview,refresh_site,search,resource_list,add_resource,add_weblink'
    );
}