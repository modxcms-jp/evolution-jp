<h2 class="tab"><?= lang('settings_ui') ?></h2>
<table class="settings">
    <tr>
        <th><?= lang('manager_theme') ?></th>
        <td><select name="manager_theme" size="1" class="inputBox"
                onchange="document.userform.theme_refresher.value = Date.parse(new Date())">
                <option value=""><?= lang('user_use_config') ?></option>
                <?php
                $files = glob(MODX_MANAGER_PATH . 'media/style/*/style.php');
                foreach ($files as $file) {
                    $file = str_replace('\\', '/', $file);
                    if ($file != "." && $file != ".." && substr($file, 0, 1) != '.') {
                        $themename = substr(dirname($file), strrpos(dirname($file), '/') + 1);
                        if (strpos($themename, '_') === 0 || $themename === 'common') {
                            continue;
                        }
                        $selectedtext = $themename == user('manager_theme') ? "selected='selected'" : "";
                        echo "<option value='$themename' $selectedtext>" . ucwords(str_replace(
                            "_",
                            " ",
                            $themename
                        )) . "</option>";
                    }
                }
                ?>
            </select><input type="hidden" name="theme_refresher" value="">
            <div><?= lang('manager_theme_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?= lang('a17_manager_inline_style_title') ?></th>
        <td>
            <textarea
                name="manager_inline_style" id="manager_inline_style"
                style="width:95%; height: 9em;"><?= $modx->config['manager_inline_style'] ?></textarea><br />
            &nbsp;&nbsp;
            <label><input
                    type="checkbox" name="default_manager_inline_style"
                    value="1" <?= user('manager_inline_style') ? '' : 'checked' ?> /> <?= lang('user_use_config') ?>
            </label>
            <div><?= lang('a17_manager_inline_style_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?= lang('mgr_login_start') ?></th>
        <td>
            <input
                name="manager_login_startup"
                value="<?= user('manager_login_startup') ?: '' ?>"
                type="text"
                maxlength="50"
                style="width: 100px;">
            <div><?= lang('mgr_login_start_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?= lang('language_title') ?></th>
        <td><select name="manager_language" size="1" class="inputBox">
                <option value=""><?= lang('user_use_config') ?></option>
                <?php
                $activelang = user('manager_language') ?: '';
                $dir = dir(MODX_CORE_PATH . 'lang');
                while ($file = $dir->read()) {
                    if (strpos($file, '.inc.php') === false) {
                        continue;
                    }
                    $languagename = trim(
                        substr(
                            $file,
                            0,
                            strpos($file, '.')
                        )
                    );
                ?>
                    <option
                        value="<?= $languagename ?>"
                        <?= selected($activelang === $languagename) ?>><?= ucwords(str_replace('_', ' ', $languagename)) ?></option>
                <?php
                }
                $dir->close();
                ?>
            </select>
            <div><?= lang('language_message') ?></div>
        </td>
    </tr>
    <tr id='editorRow0' style="display: <?= $modx->config['use_editor'] == 1 ? 'table-row' : 'none' ?>">
        <th><?= lang('which_editor_title') ?></th>
        <td>
            <select name="which_editor" class="inputBox">
                <option value=""><?= lang('user_use_config') ?></option>
                <?php
                $edt = user("which_editor");
                // invoke OnRichTextEditorRegister event
                $evtOut = evo()->invokeEvent("OnRichTextEditorRegister");
                echo "<option value='none'" . selected($edt == 'none') . ">" . lang('none') . "</option>\n";
                if (is_array($evtOut)) {
                    foreach ($evtOut as $editor) {
                        echo "<option value='$editor'" . selected($edt == $editor) . ">$editor</option>\n";
                    }
                }
                ?>
            </select>
            <div><?= lang('which_editor_message') ?></div>
        </td>
    </tr>
    <tr id='editorRow14' class="row3"
        style="display: <?= $modx->config['use_editor'] == 1 ? 'table-row' : 'none' ?>">
        <th><?= lang('editor_css_path_title') ?></th>
        <td><input type='text' maxlength='255' style="width: 250px;" name="editor_css_path"
                value="<?= user("editor_css_path") ?>" />
            <div><?= lang('editor_css_path_message') ?></div>
        </td>
    </tr>
    <tr class='row1'>
        <td colspan="2" style="padding:0;">
            <?php
            // invoke OnInterfaceSettingsRender event
            $evtOut = evo()->invokeEvent("OnInterfaceSettingsRender");
            if (is_array($evtOut)) {
                echo implode('', $evtOut);
            }
            ?>
        </td>
    </tr>
</table>
