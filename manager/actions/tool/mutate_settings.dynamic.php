<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('settings')) {
    alert()->setError(3);
    alert()->dumpError();
}
$inc_path = MODX_MANAGER_PATH . 'actions/tool/mutate_settings/';
include_once($inc_path . 'functions.inc.php');
// check to see the edit settings page isn't locked
$rs = db()->select('internalKey, username', '[+prefix+]active_users', 'action=17');
if (1 < db()->count($rs)) {
    while ($row = db()->getRow($rs)) {
        if ($row['internalKey'] == evo()->getLoginUserID()) {
            continue;
        }
        alert()->setError(5, sprintf(lang('lock_settings_msg'), $row['username']));
        alert()->dumpError();
    }
}

if ($settings_version && $settings_version != $modx_version) {
    include_once(MODX_CORE_PATH . 'upgrades/upgrades.php');
}

// reload system settings from the database.
// this will prevent user-defined settings from being saved as system setting

$settings = settings();
extract($settings, EXTR_OVERWRITE);

// load language names
$lang_keys = [];
$dir = scandir(MODX_CORE_PATH . 'lang');
foreach ($dir as $filename) {
    if (substr($filename, -8) !== '.inc.php') {
        continue;
    }
    $languagename = str_replace('.inc.php', '', $filename);
    $lang_keys[$languagename] = true;
}
?>
<script type="text/javascript">
    var lang_chg = '<?= lang('confirm_setting_language_change') ?>';
</script>
<script type="text/javascript" src="actions/tool/mutate_settings/functions.js"></script>
<form name="settings" action="index.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="a" value="30"/>
    <h1><?= lang('settings_title') ?></h1>
    <div id="actions">
        <ul class="actionButtons">
            <li id="Button1" class="mutate">
                <a href="#" onclick="documentDirty=false; document.settings.submit();">
                    <img
                        src="<?= style('icons_save') ?>"
                    /> <?= lang('update') ?>
                </a>
            </li>
            <li id="Button5" class="mutate">
                <a href="#" onclick="document.location.href='index.php?a=2';">
                    <img
                        src="<?= style('icons_cancel') ?>"
                    /> <?= lang('cancel') ?>
                </a>
            </li>
        </ul>
    </div>
    <style type="text/css">
        table.settings {
            border-collapse: collapse;
            width: 100%;
        }

        table.settings tr {
            border-bottom: 1px dotted #ccc;
        }

        table.settings th {
            font-size: inherit;
            vertical-align: top;
            text-align: left;
        }

        table.settings th, table.settings td {
            padding: 5px;
        }

        table.settings td input[type=text] {
            width: 250px;
        }
    </style>
    <div>
        <input type="hidden" name="site_id" value="<?= $site_id ?>"/>
        <input type="hidden" name="settings_version" value="<?= $modx_version ?>"/>
        <!-- this field is used to check site settings have been entered/ updated after install or upgrade -->
        <?php
        if (!isset($settings_version) || $settings_version != $modx_version) {
            ?>
            <div class='sectionBody'><p><?= lang('settings_after_install') ?></p></div>
            <?php
        }
        ?>
        <div class="tab-pane" id="settingsPane">
            <?php
            include_once($inc_path . 'tab1_site_settings.inc.php');
            include_once($inc_path . 'tab1_doc_settings.inc.php');
            include_once($inc_path . 'tab2_cache_settings.inc.php');
            include_once($inc_path . 'tab2_furl_settings.inc.php');
            include_once($inc_path . 'tab3_user_settings.inc.php');
            include_once($inc_path . 'tab4_manager_settings.inc.php');
            include_once($inc_path . 'tab6_filemanager_settings.inc.php');
            ?>
        </div>
    </div>
</form>
<script>
    tpSettings = new WebFXTabPane(
        document.getElementById("settingsPane"),
        <?= evo()->config['remember_last_tab'] == 0 ? 'false' : 'true' ?>
    );
    jQuery('#udPermsOn').change(function () {
        jQuery('.udPerms').slideDown();
    });
    jQuery('#udPermsOff').change(function () {
        jQuery('.udPerms').slideUp();
    });
    jQuery('#editorRowOn').change(function () {
        jQuery('.editorRow').slideDown();
    });
    jQuery('#editorRowOff').change(function () {
        jQuery('.editorRow').slideUp();
    });
    jQuery('#rbRowOn').change(function () {
        jQuery('.rbRow').slideDown();
    });
    jQuery('#rbRowOff').change(function () {
        jQuery('.rbRow').slideUp();
    });
</script>
