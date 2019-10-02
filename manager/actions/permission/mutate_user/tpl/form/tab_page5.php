<h2 class="tab"><?php echo lang('settings_ui') ?></h2>
<table class="settings">
    <tr>
        <th><?php echo lang('manager_theme')?></th>
        <td> <select name="manager_theme" size="1" class="inputBox" onchange="document.userform.theme_refresher.value = Date.parse(new Date())">
                <option value=""><?php echo lang('user_use_config'); ?></option>
                <?php
                $files = glob(MODX_MANAGER_PATH . 'media/style/*/style.php');
                foreach($files as $file)
                {
                    $file = str_replace('\\','/',$file);
                    if($file!="." && $file!=".." && substr($file,0,1) != '.')
                    {
                        $themename = substr(dirname($file),strrpos(dirname($file),'/')+1);
                        $selectedtext = $themename==$user['manager_theme'] ? "selected='selected'" : "" ;
                        echo "<option value='$themename' $selectedtext>".ucwords(str_replace("_", " ", $themename))."</option>";
                    }
                }
                ?>
            </select><input type="hidden" name="theme_refresher" value="">
            <div><?php echo lang('manager_theme_message');?></div></td>
    </tr>
    <tr>
        <th><?php echo lang('a17_manager_inline_style_title')?></th>
        <td>
            <textarea name="manager_inline_style" id="manager_inline_style" style="width:95%; height: 9em;"><?php echo $modx->config['manager_inline_style']; ?></textarea><br />
            &nbsp;&nbsp; <label><input type="checkbox" name="default_manager_inline_style" value="1" <?php echo isset($user['manager_inline_style']) ? '' : 'checked' ; ?>  /> <?php echo lang('user_use_config'); ?></label>
            <div><?php echo lang('a17_manager_inline_style_message');?></div>
        </td>
    </tr>
    <tr>
        <th><?php echo lang('mgr_login_start') ?></th>
        <td ><input type='text' maxlength='50' style="width: 100px;" name="manager_login_startup" value="<?php echo isset($_POST['manager_login_startup']) ? $_POST['manager_login_startup'] : $user['manager_login_startup']; ?>">
            <div><?php echo lang('mgr_login_start_message') ?></div>
        </td>
    </tr>
    <tr>
        <th><?php echo lang('language_title') ?></th>
        <td><select name="manager_language" size="1" class="inputBox">
                <option value=""><?php echo lang('user_use_config'); ?></option>
                <?php
                $activelang = (!empty($user['manager_language'])) ? $user['manager_language'] : '';
                $dir = dir(MODX_CORE_PATH . 'lang');
                while ($file = $dir->read())
                {
                    if (strpos($file, '.inc.php') !== false)
                    {
                        $endpos = strpos($file, ".");
                        $languagename = trim(substr($file, 0, $endpos));
                        $selectedtext = selected($activelang===$languagename);
                        ?>
                        <option value="<?php echo $languagename; ?>" <?php echo $selectedtext; ?>><?php echo ucwords(str_replace("_", " ", $languagename)); ?></option>
                        <?php
                    }
                }
                $dir->close();
                ?>
            </select>
            <div><?php echo lang('language_message'); ?></div>
        </td>
    </tr>
    <tr id='editorRow0' style="display: <?php echo $modx->config['use_editor']==1 ? $displayStyle : 'none' ; ?>">
        <th><?php echo lang('which_editor_title')?></th>
        <td>
            <select name="which_editor" class="inputBox">
                <option value=""><?php echo lang('user_use_config'); ?></option>
                <?php
                $edt = isset ($user["which_editor"]) ? $user["which_editor"] : '';
                // invoke OnRichTextEditorRegister event
                $evtOut = $modx->invokeEvent("OnRichTextEditorRegister");
                echo "<option value='none'" . selected($edt == 'none') . ">" . lang('none') . "</option>\n";
                if (is_array($evtOut))
                    foreach ($evtOut as $iValue) {
                        $editor = $iValue;
                        echo "<option value='$editor'" . selected($edt == $editor) . ">$editor</option>\n";
                    }
                ?>
            </select>
            <div><?php echo lang('which_editor_message')?></div>
        </td>
    </tr>
    <tr id='editorRow14' class="row3" style="display: <?php echo $modx->config['use_editor']==1 ? $displayStyle : 'none' ; ?>">
        <th><?php echo lang('editor_css_path_title')?></th>
        <td><input type='text' maxlength='255' style="width: 250px;" name="editor_css_path" value="<?php echo isset($user["editor_css_path"]) ? $user["editor_css_path"] : "" ; ?>" />
            <div><?php echo lang('editor_css_path_message')?></div>
        </td>
    </tr>
    <tr class='row1'>
        <td colspan="2" style="padding:0;">
            <?php
            // invoke OnInterfaceSettingsRender event
            $evtOut = $modx->invokeEvent("OnInterfaceSettingsRender");
            if (is_array($evtOut)) echo implode('', $evtOut);
            ?>
        </td>
    </tr>
</table>
