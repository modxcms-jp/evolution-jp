<!-- Interface & editor settings -->
<div class="tab-page" id="tabPage5">
    <h2 class="tab"><?= $_lang["settings_ui"] ?></h2>
    <table class="settings">
        <tr>
            <th><?= $_lang["manager_theme"] ?></th>
            <td><select name="manager_theme" size="1" class="inputBox">
                    <?php
                    $files = glob(MODX_MANAGER_PATH . 'media/style/*/style.php');
                    foreach ($files as $file) {
                        $file = str_replace('\\', '/', $file);
                        if ($file != "." && $file != ".." && substr($file, 0, 1) != '.') {
                            $themename = substr(dirname($file), strrpos(dirname($file), '/') + 1);
                            if (strpos($themename, '_') === 0 || $themename === 'common') {
                                continue;
                            }
                            $selectedtext = $themename == $manager_theme ? "selected='selected'" : "";
                            echo "<option value='$themename' $selectedtext>" . ucwords(str_replace("_", " ",
                                    $themename)) . "</option>";
                        }
                    }
                    ?>
                </select><br/>
                <?= $_lang["manager_theme_message"] ?></td>
        </tr>

        <tr>
            <th><?= $_lang["a17_manager_inline_style_title"] ?></th>
            <td>
                <textarea
                    name="manager_inline_style" id="manager_inline_style"
                    style="width:95%; height: 9em;"
                ><?= config('manager_inline_style') ?></textarea><br/>
                <?= $_lang["a17_manager_inline_style_message"] ?>
            </td>
        </tr>

        <tr>
            <th><?= $_lang["language_title"] ?></th>
            <td>
                <select name="manager_language" size="1" class="inputBox">
                    <?= get_lang_options(null, config('manager_language')) ?>
                </select><br/>
                <?= $_lang["language_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["manager_docs_orderby_title"] ?></th>
            <td>
                <?=
                    Form::text(
                        'manager_docs_orderby',
                        config('manager_docs_orderby'), [
                            'style' => 'width:500px;'
                        ]
                    )
                ?>
                <p><?= $_lang["manager_docs_orderby_message"] ?></p>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["tree_pane_open_default_title"] ?></th>
            <td>
                <?= wrap_label($_lang["open"],
                    form_radio('tree_pane_open_default', 1, config('tree_pane_open_default') == 1)); ?><br/>
                <?= wrap_label($_lang["close"],
                    form_radio('tree_pane_open_default', 0, config('tree_pane_open_default') == 0)); ?><br/>
                <?= $_lang["tree_pane_open_default_message"] ?>
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
            <th><?= $_lang["topmenu_items_title"] ?></th>
            <td>
                <table>
                    <tr>
                        <td><?= $_lang['site'] . '</td><td>' . form_text('topmenu_site', '',
                                    $tmenu_style); ?></td>
                    </tr>
                    <tr>
                        <td><?= $_lang['elements'] . '</td><td>' . form_text('topmenu_element', '',
                                    $tmenu_style); ?></td>
                    </tr>
                    <tr>
                        <td><?= $_lang['users'] . '</td><td>' . form_text('topmenu_security', '',
                                    $tmenu_style); ?></td>
                    </tr>
                    <tr>
                        <td><?= $_lang['user'] . '</td><td>' . form_text('topmenu_user', '',
                                    $tmenu_style); ?></td>
                    </tr>
                    <tr>
                        <td><?= $_lang['tools'] . '</td><td>' . form_text('topmenu_tools', '',
                                    $tmenu_style); ?></td>
                    </tr>
                    <tr>
                        <td><?= $_lang['reports'] . '</td><td>' . form_text('topmenu_reports', '',
                                    $tmenu_style); ?></td>
                    </tr>
                </table>
                <div><?= $_lang["topmenu_items_message"] ?></div>
            </td>
        </tr>

        <tr>
            <th><?= $_lang["limit_by_container"] ?></th>
            <td>
                <?= form_text('limit_by_container', 4) ?><br/>
                <?= $_lang["limit_by_container_message"] ?></td>
        </tr>

        <tr>
            <th><?= $_lang["tree_page_click"] ?></th>
            <td>
                <?= wrap_label($_lang["edit_resource"],
                    form_radio('tree_page_click', '27', config('tree_page_click') == '27')); ?><br/>
                <?= wrap_label($_lang["doc_data_title"],
                    form_radio('tree_page_click', '3', config('tree_page_click') == '3')); ?><br/>
                <?= wrap_label($_lang["tree_page_click_option_auto"],
                    form_radio('tree_page_click', 'auto', config('tree_page_click') == 'auto')); ?><br/>
                <?= $_lang["tree_page_click_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["remember_last_tab"] ?></th>
            <td>
                <?= wrap_label("{$_lang['yes']} (Full)",
                    form_radio('remember_last_tab', '2', config('remember_last_tab') == '2')); ?><br/>
                <?= wrap_label("{$_lang['yes']} (Stay mode)",
                    form_radio('remember_last_tab', '1', config('remember_last_tab') == '1')); ?><br/>
                <?= wrap_label($_lang["no"], form_radio('remember_last_tab', '0', config('remember_last_tab') == '0')) ?>
                <br/>
                <?= $_lang["remember_last_tab_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["setting_resource_tree_sortby_default"] ?></th>
            <td>
                <select name="resource_tree_sortby_default" size="1" class="inputBox">
                    <?php
                    $output = [];
                    foreach (['isfolder','pagetitle','id','menuindex','createdon','editedon'] as $v) {
                        $output[] = str_replace(
                            ['[+value+]', '[+selected+]'],
                            [$v, ($v == config('resource_tree_sortby_default')) ? 'selected' : ''],
                            '<option value="[+value+]" [+selected+]>[*[+value+]*]</option>' . "\n"
                        );
                    }
                    echo implode("\n", $output)
                    ?>
                </select><br/>
                <?= $_lang["setting_resource_tree_sortby_default_desc"] ?>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["setting_resource_tree_node_name"] ?></th>
            <td>
                <select name="resource_tree_node_name" size="1" class="inputBox">
                    <?php
                    $tpl = '<option value="[+value+]" [+selected+]>[*[+value+]*]</option>' . "\n";
                    $option = ['pagetitle', 'menutitle', 'alias', 'createdon', 'editedon', 'publishedon'];
                    $output = [];
                    foreach ($option as $v) {
                        $selected = ($v == config('resource_tree_node_name')) ? 'selected' : '';
                        $s = ['[+value+]', '[+selected+]'];
                        $r = [$v, $selected];
                        $output[] = str_replace($s, $r, $tpl);
                    }
                    echo implode("\n", $output)
                    ?>
                </select><br/>
                <?= $_lang["setting_resource_tree_node_name_desc"] ?>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["manager_treepane_trim_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"], form_radio('manager_treepane_trim_title', '1', config('manager_treepane_trim_title') == '1')); ?><br/>
                <?= wrap_label($_lang["no"], form_radio('manager_treepane_trim_title', '0', config('manager_treepane_trim_title') == '0')); ?><br/>
                <?= $_lang["manager_treepane_trim_title_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["top_howmany_title"] ?></th>
            <td>
                <?= form_text('top_howmany', 3) ?><br/>
                <?= $_lang["top_howmany_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["datepicker_offset"] ?></th>
            <td>
                <?= form_text('datepicker_offset', 5) ?><br/>
                <?= $_lang["datepicker_offset_message"] ?></td>
        </tr>
        <tr>
            <th><?= $_lang["datetime_format"] ?></th>
            <td><select name="datetime_format" size="1" class="inputBox">
                    <?php
                    $datetime_format_list = ['dd-mm-YYYY', 'mm/dd/YYYY', 'YYYY/mm/dd'];
                    $str = '';
                    foreach ($datetime_format_list as $value) {
                        $selectedtext = (config('datetime_format') == $value) ? ' selected' : '';
                        $str .= '<option value="' . $value . '"' . $selectedtext . '>';
                        $str .= $value . "</option>\n";
                    }
                    echo $str;
                    ?>
                </select><br/>
                <?= $_lang["datetime_format_message"] ?></td>
        </tr>
        <tr>
            <th><?= $_lang["nologentries_title"] ?></th>
            <td>
                <?= form_text('number_of_logs', 3) ?><br/>
                <?= $_lang["nologentries_message"] ?></td>
        </tr>
        <tr>
            <th><?= $_lang["automatic_optimize_table_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"],
                    form_radio('automatic_optimize', '1', config('automatic_optimize') == '1')); ?><br/>
                <?= wrap_label($_lang["no"],
                    form_radio('automatic_optimize', '0', config('automatic_optimize') == '0')); ?><br/>
                <?= $_lang["automatic_optimize_table_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["mail_check_timeperiod_title"] ?></th>
            <td>
                <?= form_text('mail_check_timeperiod', 5) ?><br/>
                <?= $_lang["mail_check_timeperiod_message"] ?></td>
        </tr>
        <tr>
            <th><?= $_lang["nomessages_title"] ?></th>
            <td>
                <?= form_text('number_of_messages', 5) ?><br/>
                <?= $_lang["nomessages_message"] ?></td>
        </tr>
        <tr>
            <th><?= $_lang["pm2email_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"], form_radio('pm2email', '1', config('pm2email') == '1')) ?><br/>
                <?= wrap_label($_lang["no"], form_radio('pm2email', '0', config('pm2email') == '0')) ?><br/>
                <?= $_lang["pm2email_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["noresults_title"] ?></th>
            <td>
                <?= form_text('number_of_results', 5) ?><br/>
                <?= $_lang["noresults_message"] ?></td>
        </tr>

        <tr>
            <th><?= $_lang["use_editor_title"] ?></th>
            <td>
                <?= wrap_label($_lang["yes"],
                    form_radio('use_editor', '1', config('use_editor') == '1', 'id="editorRowOn"')); ?><br/>
                <?= wrap_label($_lang["no"],
                    form_radio('use_editor', '0', config('use_editor') == '0', 'id="editorRowOff"')); ?><br/>
                <?= $_lang["use_editor_message"] ?>
            </td>
        </tr>

        <tr class="editorRow" style="display: <?= config('use_editor') == 1 ? 'table-row' : 'none' ?>">
            <th><?= $_lang["which_editor_title"] ?></th>
            <td>
                <?php
                // invoke OnRichTextEditorRegister event
                $editors = evo()->invokeEvent("OnRichTextEditorRegister");
                if (is_array($editors)) {
                    $which_editor_sel = '<select name="which_editor">';
                    $which_editor_sel .= sprintf(
                        '<option value="none"%s>%s</option>\n',
                        config('which_editor') == 'none' ? ' selected' : '',
                        $_lang["none"]);
                    foreach ($editors as $editor) {
                        $editor_sel = config('which_editor') == $editor ? ' selected="selected"' : '';
                        $which_editor_sel .= sprintf(
                            '<option value="%s"%s>%s</option>\n', $editor, $editor_sel, $editor
                        );
                    }
                    $which_editor_sel .= '</select><br />';
                } else {
                    $which_editor_sel = '';
                }
                echo $which_editor_sel;
                ?>
                <?= $_lang["which_editor_message"] ?></td>
        </tr>
        <tr class="editorRow" style="display: <?= config('use_editor') == 1 ? 'table-row' : 'none' ?>">
            <th><?= $_lang["fe_editor_lang_title"] ?></th>
            <td><select name="fe_editor_lang" size="1" class="inputBox">
                    <?= get_lang_options(null, config('fe_editor_lang')) ?>
                </select><br/>
                <?= $_lang["fe_editor_lang_message"] ?></td>
        </tr>
        <tr class="editorRow" style="display: <?= config('use_editor') == 1 ? 'table-row' : 'none' ?>">
            <th><?= $_lang["editor_css_path_title"] ?></th>
            <td>
                <?= form_text('editor_css_path', '', 'style="width:400px;"'); ?><br/>
                <?= $_lang["editor_css_path_message"] ?></td>
        </tr>
        <tr class="row1" style="border-bottom:none;">
            <td colspan="2" style="padding:0;">
                <?php
                // invoke OnInterfaceSettingsRender event
                $evtOut = evo()->invokeEvent("OnInterfaceSettingsRender");
                if (is_array($evtOut)) {
                    echo implode("", $evtOut);
                }
                ?>
            </td>
        </tr>
    </table>
</div>
