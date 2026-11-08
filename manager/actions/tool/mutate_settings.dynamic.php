<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('settings')) {
    alert()->setError(3);
    alert()->dumpError();
}
require_once MODX_CORE_PATH . 'settings_form.class.inc.php';
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

// reload system settings from the database.
// this will prevent user-defined settings from being saved as system setting
$stored = EvoSettingsForm::storedValues();
evo()->config = $stored;
$values = EvoSettingsForm::systemValues($stored);
?>
<script type="text/javascript">
    var lang_chg = <?= js_literal(stripcslashes(lang('confirm_setting_language_change'))) ?>;
</script>
<script type="text/javascript" src="actions/tool/mutate_settings/functions.js"></script>
<form name="settings" action="index.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="a" value="30"/>
    <h1><?= lang('settings_title') ?></h1>
    <div id="actions">
        <ul class="actionButtons">
            <li id="Button1" class="mutate">
                <a href="#" onclick="documentDirty=false; document.settings.submit();">
                    <?= lang('update') ?>
                </a>
            </li>
            <li id="Button5" class="mutate">
                <a href="#" onclick="EvoShell.go('index.php?a=2');">
                    <?= lang('cancel') ?>
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
        <input type="hidden" name="site_id" value="<?= hsc($values['site_id'] ?? '') ?>"/>
        <input type="hidden" name="settings_version" value="<?= hsc(globalv('modx_version')) ?>"/>
        <!-- this field is used to check site settings have been entered/ updated after install or upgrade -->
        <?php
        if (in_array((string)($values['settings_version'] ?? ''), ['', '0'], true)) {
            ?>
            <div class='sectionBody'><p><?= lang('settings_after_install') ?></p></div>
            <?php
        }
        ?>
        <div class="tab-pane" id="settingsPane">
            <?php
            echo (new EvoSettingsForm(EvoSettingsForm::definition(), $values, $stored))->render();
            $evtOut = evo()->invokeEvent('OnSystemSettingsRender');
            if (is_array($evtOut)) {
                echo implode('', $evtOut);
            }
            ?>
        </div>
    </div>
</form>
<script>
    tpSettings = new WebFXTabPane(
        document.getElementById("settingsPane"),
        <?= evo()->config['remember_last_tab'] == 0 ? 'false' : 'true' ?>
    );
</script>
