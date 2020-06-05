<!-- Interface & editor settings -->
<div class="tab-page" id="tabPage5">
    <h2 class="tab"><?php echo $_lang["settings_ui"] ?></h2>
    <table class="settings">
        <tr>
            <th><?php echo $_lang["manager_theme"] ?></th>
            <td><select name="manager_theme" size="1" class="inputBox">
                    <?php
                    $files = glob(MODX_MANAGER_PATH . 'media/style/*/style.php');
                    foreach ($files as $file) {
                        $file = str_replace('\\', '/', $file);
                        if ($file != "." && $file != ".." && substr($file, 0, 1) != '.') {
                            $themename = substr(dirname($file), strrpos(dirname($file), '/') + 1);
                            $selectedtext = $themename == $manager_theme ? "selected='selected'" : "";
                            echo "<option value='$themename' $selectedtext>" . ucwords(str_replace("_", " ",
                                    $themename)) . "</option>";
                        }
                    }
                    ?>
                </select><br/>
                <?php echo $_lang["manager_theme_message"] ?></td>
        </tr>

        <tr>
            <th><?php echo $_lang["a17_manager_inline_style_title"] ?></th>
            <td>
                <textarea name="manager_inline_style" id="manager_inline_style"
                          style="width:95%; height: 9em;"><?php echo $manager_inline_style; ?></textarea><br/>
                <?php echo $_lang["a17_manager_inline_style_message"] ?>
            </td>
        </tr>

        <tr>
            <th><?php echo $_lang["language_title"] ?></th>
            <td>
                <select name="manager_language" size="1" class="inputBox">
                    <?php echo get_lang_options(null, $manager_language); ?>
                </select><br/>
                <?php echo $_lang["language_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?php echo $_lang["enable_draft_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["enabled"], form_radio('enable_draft', '1', $enable_draft == '1')); ?><br/>
                <?php echo wrap_label($_lang["disabled"], form_radio('enable_draft', '0', $enable_draft == '0')); ?>
                <br/>
                <?php echo $_lang["enable_draft_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?php echo $_lang["tree_pane_open_default_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["open"],
                    form_radio('tree_pane_open_default', 1, $tree_pane_open_default == 1)); ?><br/>
                <?php echo wrap_label($_lang["close"],
                    form_radio('tree_pane_open_default', 0, $tree_pane_open_default == 0)); ?><br/>
                <?php echo $_lang["tree_pane_open_default_message"] ?>
            </td>
        </tr>
        <?php
        $tmenu_style = 'style="width:350px;"';
        checkConfig('topmenu_site');
        checkConfig('topmenu_element');
        checkConfig('topmenu_security');
        checkConfig('topmenu_user');
        checkConfig('topmenu_tools');
        ?>
        <tr>
            <th><?php echo $_lang["topmenu_items_title"] ?></th>
            <td>
                <table>
                    <tr>
                        <td><?php echo $_lang['site'] . '</td><td>' . form_text('topmenu_site', '',
                                    $tmenu_style); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo $_lang['elements'] . '</td><td>' . form_text('topmenu_element', '',
                                    $tmenu_style); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo $_lang['users'] . '</td><td>' . form_text('topmenu_security', '',
                                    $tmenu_style); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo $_lang['user'] . '</td><td>' . form_text('topmenu_user', '',
                                    $tmenu_style); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo $_lang['tools'] . '</td><td>' . form_text('topmenu_tools', '',
                                    $tmenu_style); ?></td>
                    </tr>
                    <tr>
                        <td><?php echo $_lang['reports'] . '</td><td>' . form_text('topmenu_reports', '',
                                    $tmenu_style); ?></td>
                    </tr>
                </table>
                <div><?php echo $_lang["topmenu_items_message"]; ?></div>
            </td>
        </tr>

        <tr>
            <th><?php echo $_lang["limit_by_container"] ?></th>
            <td>
                <?php echo form_text('limit_by_container', 4); ?><br/>
                <?php echo $_lang["limit_by_container_message"] ?></td>
        </tr>

        <tr>
            <th><?php echo $_lang["tree_page_click"] ?></th>
            <td>
                <?php echo wrap_label($_lang["edit_resource"],
                    form_radio('tree_page_click', '27', $tree_page_click == '27')); ?><br/>
                <?php echo wrap_label($_lang["doc_data_title"],
                    form_radio('tree_page_click', '3', $tree_page_click == '3')); ?><br/>
                <?php echo wrap_label($_lang["tree_page_click_option_auto"],
                    form_radio('tree_page_click', 'auto', $tree_page_click == 'auto')); ?><br/>
                <?php echo $_lang["tree_page_click_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?php echo $_lang["remember_last_tab"] ?></th>
            <td>
                <?php echo wrap_label("{$_lang['yes']} (Full)",
                    form_radio('remember_last_tab', '2', $remember_last_tab == '2')); ?><br/>
                <?php echo wrap_label("{$_lang['yes']} (Stay mode)",
                    form_radio('remember_last_tab', '1', $remember_last_tab == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"], form_radio('remember_last_tab', '0', $remember_last_tab == '0')); ?>
                <br/>
                <?php echo $_lang["remember_last_tab_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?php echo $_lang["setting_resource_tree_node_name"] ?></th>
            <td>
                <select name="resource_tree_node_name" size="1" class="inputBox">
                    <?php
                    $tpl = '<option value="[+value+]" [+selected+]>[*[+value+]*]</option>' . "\n";
                    $option = array('pagetitle', 'menutitle', 'alias', 'createdon', 'editedon', 'publishedon');
                    $output = array();
                    foreach ($option as $v) {
                        $selected = ($v == $resource_tree_node_name) ? 'selected' : '';
                        $s = array('[+value+]', '[+selected+]');
                        $r = array($v, $selected);
                        $output[] = str_replace($s, $r, $tpl);
                    }
                    echo join("\n", $output)
                    ?>
                </select><br/>
                <?php echo $_lang["setting_resource_tree_node_name_desc"] ?>
            </td>
        </tr>

        <tr>
            <th><?php echo $_lang["top_howmany_title"] ?></th>
            <td>
                <?php echo form_text('top_howmany', 3); ?><br/>
                <?php echo $_lang["top_howmany_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?php echo $_lang["datepicker_offset"] ?></th>
            <td>
                <?php echo form_text('datepicker_offset', 5); ?><br/>
                <?php echo $_lang["datepicker_offset_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["datetime_format"] ?></th>
            <td><select name="datetime_format" size="1" class="inputBox">
                    <?php
                    $datetime_format_list = array('dd-mm-YYYY', 'mm/dd/YYYY', 'YYYY/mm/dd');
                    $str = '';
                    foreach ($datetime_format_list as $value) {
                        $selectedtext = ($datetime_format == $value) ? ' selected' : '';
                        $str .= '<option value="' . $value . '"' . $selectedtext . '>';
                        $str .= $value . "</option>\n";
                    }
                    echo $str;
                    ?>
                </select><br/>
                <?php echo $_lang["datetime_format_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["nologentries_title"] ?></th>
            <td>
                <?php echo form_text('number_of_logs', 3); ?><br/>
                <?php echo $_lang["nologentries_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["automatic_optimize_table_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('automatic_optimize', '1', $automatic_optimize == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"],
                    form_radio('automatic_optimize', '0', $automatic_optimize == '0')); ?><br/>
                <?php echo $_lang["automatic_optimize_table_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?php echo $_lang["mail_check_timeperiod_title"] ?></th>
            <td>
                <?php echo form_text('mail_check_timeperiod', 5); ?><br/>
                <?php echo $_lang["mail_check_timeperiod_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["nomessages_title"] ?></th>
            <td>
                <?php echo form_text('number_of_messages', 5); ?><br/>
                <?php echo $_lang["nomessages_message"] ?></td>
        </tr>
        <tr>
            <th><?php echo $_lang["pm2email_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"], form_radio('pm2email', '1', $pm2email == '1')); ?><br/>
                <?php echo wrap_label($_lang["no"], form_radio('pm2email', '0', $pm2email == '0')); ?><br/>
                <?php echo $_lang["pm2email_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?php echo $_lang["noresults_title"] ?></th>
            <td>
                <?php echo form_text('number_of_results', 5); ?><br/>
                <?php echo $_lang["noresults_message"] ?></td>
        </tr>

        <tr>
            <th><?php echo $_lang["use_editor_title"] ?></th>
            <td>
                <?php echo wrap_label($_lang["yes"],
                    form_radio('use_editor', '1', $use_editor == '1', 'id="editorRowOn"')); ?><br/>
                <?php echo wrap_label($_lang["no"],
                    form_radio('use_editor', '0', $use_editor == '0', 'id="editorRowOff"')); ?><br/>
                <?php echo $_lang["use_editor_message"] ?>
            </td>
        </tr>

        <tr class="editorRow" style="display: <?php echo $use_editor == 1 ? $displayStyle : 'none'; ?>">
            <th><?php echo $_lang["which_editor_title"] ?></th>
            <td>
                <?php
                // invoke OnRichTextEditorRegister event
                $editors = $modx->invokeEvent("OnRichTextEditorRegister");
                if (is_array($editors)) {
                    $which_editor_sel = '<select name="which_editor">';
                    $which_editor_sel .= '<option value="none"' . ($which_editor == 'none' ? ' selected="selected"' : '') . '>' . $_lang["none"] . "</option>\n";
                    foreach ($editors as $editor) {
                        $editor_sel = $which_editor == $editor ? ' selected="selected"' : '';
                        $which_editor_sel .= '<option value="' . $editor . '"' . $editor_sel . '>' . $editor . "</option>\n";
                    }
                    $which_editor_sel .= '</select><br />';
                } else {
                    $which_editor_sel = '';
                }
                echo $which_editor_sel;
                ?>
                <?php echo $_lang["which_editor_message"] ?></td>
        </tr>
        <tr class="editorRow" style="display: <?php echo $use_editor == 1 ? $displayStyle : 'none'; ?>">
            <th><?php echo $_lang["fe_editor_lang_title"] ?></th>
            <td><select name="fe_editor_lang" size="1" class="inputBox">
                    <?php echo get_lang_options(null, $fe_editor_lang); ?>
                </select><br/>
                <?php echo $_lang["fe_editor_lang_message"] ?></td>
        </tr>
        <tr class="editorRow" style="display: <?php echo $use_editor == 1 ? $displayStyle : 'none'; ?>">
            <th><?php echo $_lang["editor_css_path_title"] ?></th>
            <td>
                <?php echo form_text('editor_css_path', '', 'style="width:400px;"'); ?><br/>
                <?php echo $_lang["editor_css_path_message"] ?></td>
        </tr>
        <tr class="row1" style="border-bottom:none;">
            <td colspan="2" style="padding:0;">
                <?php
                // invoke OnInterfaceSettingsRender event
                $evtOut = $modx->invokeEvent("OnInterfaceSettingsRender");
                if (is_array($evtOut)) {
                    echo implode("", $evtOut);
                }
                ?>
            </td>
        </tr>
    </table>
</div>
