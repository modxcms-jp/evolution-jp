<!-- Site Settings -->
<div class="tab-page" id="tabPageDoc">
    <h2 class="tab">リソース設定</h2>
    <table class="settings">
        <tr>
            <th><?= lang('defaulttemplate_title') ?></th>
            <td>
                <select name="default_template" class="inputBox"
                        onchange="wrap=document.getElementById('template_reset_options_wrapper');if(this.options[this.selectedIndex].value != '<?= config('default_template') ?>'){wrap.style.display='block';}else{wrap.style.display='none';}"
                        style="width:150px">
                    <option value="">(blank)</option>
                    <?php
                    $rs = db()->select(
                        't.templatename, t.id, c.category',
                        "[+prefix+]site_templates t LEFT JOIN [+prefix+]categories c ON t.category = c.id",
                        '',
                        'c.category, t.templatename ASC'
                    );
                    $options = [];
                    while ($row = db()->getRow($rs)) {
                        $options[$row['category'] == null ? lang('no_category') : $row['category']][] = array(
                            'id' => $row['id'],
                            'name' => $row['templatename'],
                            'selected' => (config('default_template') == $row['id'])
                        );
                    }
                    $echo = [];
                    foreach ($options as $category => $templates) {
                        $group = [];
                        foreach ($templates as $template) {
                            $group[] = sprintf(
                                '<option value="%s"%s>%s</option>',
                                $template['id'],
                                $template['selected'] ? ' selected' : '',
                                $template['name']
                            );
                        }
                        $echo[] = sprintf(
                            '<optgroup label="%s">%s</optgroup>',
                            $category,
                            implode("\n", $group)
                        );
                    }
                    echo implode("\n", $echo);
                    echo '</select><br />';
                    foreach ($options as $category => $templates) {
                        foreach ($templates as $template) {
                            if (!($template['selected'])) {
                                continue;
                            }
                            $savedTmpId = $template['id'];
                            $savedTmpName = $template['name'];
                            break;
                        }
                    }
                    ?>
                    <div id="template_reset_options_wrapper" style="display:none;">
                        <?= wrap_label(
                            lang('template_reset_all'),
                            form_radio('reset_template', '1')
                        );
                        ?><br/>
                        <?= wrap_label(
                            sprintf(
                                lang('template_reset_specific'),
                                $savedTmpName
                            ),
                            form_radio('reset_template', 2)
                        ); ?>
                    </div>
                    <input type="hidden" name="old_template" value="<?= $savedTmpId ?>"/>
                    <?= lang('defaulttemplate_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('defaulttemplate_logic_title') ?></th>
            <td>
                <?= wrap_label(
                    lang('defaulttemplate_logic_system_message'),
                    form_radio(
                        'auto_template_logic',
                        'system',
                        config('auto_template_logic') === 'system'
                    )
                ); ?><br/>
                <?= wrap_label(
                    lang('defaulttemplate_logic_parent_message'),
                    form_radio(
                        'auto_template_logic',
                        'parent',
                        config('auto_template_logic') === 'parent'
                    )
                ); ?><br/>
                <?= wrap_label(
                    lang('defaulttemplate_logic_sibling_message'),
                    form_radio(
                        'auto_template_logic',
                        'sibling',
                        config('auto_template_logic') === 'sibling'
                    )
                ); ?><br/>
                <?= lang('defaulttemplate_logic_general_message') ?>
            </td>
        </tr>
        <tr>
            <th>公開開始日時自動設定</th>
            <td>
                <?= wrap_label(
                    $_lang["enabled"],
                    form_radio(
                        'auto_pub_date',
                        1,
                        config('auto_pub_date')
                    )
                ); ?><br/>
                <?= wrap_label(
                    $_lang["disabled"],
                    form_radio(
                        'auto_pub_date',
                        0,
                        !config('auto_pub_date')
                    )
                ); ?>
                <br/>
                公開開始日時(pub_date)を設定しない場合、公開日時(publishedon)の値を取得し設定します。
            </td>
        </tr>
        <tr>
            <th><?= lang('defaultpublish_title') ?></th>
            <td>
                <?= wrap_label(
                    lang('yes'),
                    form_radio(
                        'publish_default',
                        1,
                        config('publish_default') == 1
                    )
                ); ?><br/>
                <?= wrap_label(
                    lang('no'),
                    form_radio(
                        'publish_default',
                        0,
                        config('publish_default') == 0
                    )
                ); ?><br/>
                <?= lang('defaultpublish_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('defaultmenuindex_title') ?></th>
            <td>
                <?= wrap_label(
                    lang('yes'),
                    form_radio(
                        'auto_menuindex',
                        1,
                        config('auto_menuindex') == 1
                    )
                ); ?><br/>
                <?= wrap_label(
                    lang('no'),
                    form_radio(
                        'auto_menuindex',
                        0,
                        config('auto_menuindex') == 0
                    )
                ); ?><br/>
                <?= lang('defaultmenuindex_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('docid_incrmnt_method_title') ?></th>
            <td>
                <?= wrap_label(
                    lang('docid_incrmnt_method_0'),
                    form_radio(
                        'docid_incrmnt_method',
                        0,
                        config('docid_incrmnt_method') == 0
                    )
                ); ?><br/>
                <?= wrap_label(
                    lang('docid_incrmnt_method_1'),
                    form_radio(
                        'docid_incrmnt_method',
                        1,
                        config('docid_incrmnt_method') == 1
                    )
                ); ?><br/>
                <?= wrap_label(
                    lang('docid_incrmnt_method_2'),
                    form_radio(
                        'docid_incrmnt_method',
                        2,
                        config('docid_incrmnt_method') == 2
                    )
                ); ?><br/>
                <?= lang('docid_incrmnt_method_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= $_lang["enable_draft_title"] ?></th>
            <td>
                <?= wrap_label($_lang["enabled"], form_radio('enable_draft', '1', $enable_draft == '1')) ?><br/>
                <?= wrap_label($_lang["disabled"], form_radio('enable_draft', '0', $enable_draft == '0')) ?>
                <br/>
                <?= $_lang["enable_draft_message"] ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('defaultcache_title') ?></th>
            <td>
                <?= wrap_label(
                    lang('yes'),
                    form_radio(
                        'cache_default',
                        1,
                        config('cache_default') == 1
                    )
                ); ?><br/>
                <?= wrap_label(
                    lang('no'),
                    form_radio(
                        'cache_default',
                        0,
                        config('cache_default') == 0
                    )
                ); ?><br/>
                <?= lang('defaultcache_message') ?>
            </td>
        </tr>
        <tr>
            <th><?= lang('defaultsearch_title') ?></th>
            <td>
                <?= wrap_label(
                    lang('yes'),
                    form_radio(
                        'search_default',
                        1,
                        config('search_default') == 1
                    )
                ); ?><br/>
                <?= wrap_label(
                    lang('no'),
                    form_radio(
                        'search_default',
                        0,
                        config('search_default') == 0
                    )
                ); ?><br/>
                <?= lang('defaultsearch_message') ?></td>
        </tr>
        <tr class="row1" style="border-bottom:none;">
            <td colspan="2" style="padding:0;">
                <?php
                // invoke OnSiteSettingsRender event
                $evtOut = evo()->invokeEvent('OnSiteSettingsRender');
                if (is_array($evtOut)) {
                    echo implode('', $evtOut);
                }
                ?>
            </td>
        </tr>
    </table>
</div>
