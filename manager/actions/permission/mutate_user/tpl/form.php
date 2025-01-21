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
</style>
<form action="index.php?a=32" method="post" name="userform" enctype="multipart/form-data">
    <input type="hidden" name="mode" value="<?= evo()->input_get('a') ?>">
    <input type="hidden" name="userid" value="<?= evo()->input_get('id') ?>">
    <input type="hidden" name="blockedmode" value="<?= blockedmode($user) ?>"/>
    <h1><?= lang('user_title') ?></h1>
    <div id="actions">
        <ul class="actionButtons">
            <?php
            echo aButtonSave();
            echo aButtonDelete($userid);
            echo aButtonCancel();
            ?>
        </ul>
    </div>
    <!-- Tab Start -->
    <div class="sectionBody">
        <div class="tab-pane" id="userPane">
            <div class="tab-page" id="tabGeneral">
                <?php include __DIR__ . '/form/tab_general.php'; ?>
            </div>
            <div class="tab-page" id="tabProfile">
                <?php include __DIR__ . '/form/tab_profile.php'; ?>
            </div>
            <div class="tab-page" id="tabSettings">
                <?php include __DIR__ . '/form/tab_settings.php'; ?>
            </div>
            <!-- Interface & editor settings -->
            <div class="tab-page" id="tabPage5">
                <?php include __DIR__ . '/form/tab_page5.php'; ?>
            </div>
            <!-- Miscellaneous settings -->
            <div class="tab-page" id="tabPage7">
                <?php include __DIR__ . '/form/tab_page7.php'; ?>
            </div>
            <?php
            if ($modx->config['use_udperms'] == 1) {
                echo '<div class="tab-page" id="tabAccess">';
                include __DIR__ . '/form/tab_access.php';
                echo '</div>';
            }
            // invoke OnUserFormRender event
            $tmp = array(
                'id' => $userid,
                'usersettings' => $user
            );
            $evtOut = evo()->invokeEvent('OnUserFormRender', $tmp);
            if (is_array($evtOut)) {
                echo implode('', $evtOut);
            }
            ?>
        </div>
    </div>
    <input type="submit" name="save" style="display:none">
</form>
