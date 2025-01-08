<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (
    (getv('a') == 16 && !hasPermission('edit_template'))
    ||
    (getv('a') == 19 && !hasPermission('new_template'))
) {
    alert()->setError(3);
    alert()->dumpError();
}

if (id()) {
    $username = is_locked(id());
    if ($username) {
        alert()->setError(
            5,
            sprintf(
                lang('lock_msg'),
                $username,
                lang('template')
            )
        );
        alert()->dumpError();
    }
}

?>
    <script type="text/javascript">
        function duplicaterecord() {
            if (confirm("<?= lang('confirm_duplicate_record') ?>")) {
                documentDirty = false;
                document.location.href = "index.php?id=<?= getv('id') ?>&a=96";
            }
        }

        function deletedocument() {
            if (confirm("<?= lang('confirm_delete_template') ?>")) {
                documentDirty = false;
                document.location.href = "index.php?id=" + document.mutate.id.value + "&a=21";
            }
        }
    </script>

    <form name="mutate" id="mutate" method="POST" action="index.php" enctype="multipart/form-data">
        <?php
        $tmp = ['id' => id()];
        $evtOut = evo()->invokeEvent('OnTempFormPrerender', $tmp);
        if (is_array($evtOut)) {
            echo implode('', $evtOut);
        }
        ?>
        <input type="hidden" name="a" value="20">
        <input type="hidden" name="id" value="<?= getv('id') ?>">
        <input type="hidden" name="mode" value="<?= (int)getv('a') ?>">

        <h1><?= lang('template_title') ?></h1>
        <div id="actions">
            <ul class="actionButtons">
                <?php if (evo()->hasPermission('save_template')) : ?>
                    <li id="Button1" class="mutate">
                        <a href="#"
                            onclick="jQuery('#templatesPane select').prop('disabled',false);documentDirty=false;jQuery('#Button1').hide();jQuery('input,textarea,select').addClass('readonly');jQuery('#mutate').submit();"><img
                                src="<?= style('icons_save') ?>"/> <?= lang('update') ?>
                        </a>
                        <span class="and"> + </span>
                        <select id="stay" name="stay">
                            <option id="stay1"
                                    value="1" <?= selected(getv('stay') == 1) ?>><?= lang('stay_new') ?></option>
                            <option id="stay2" value="2" <?= selected(getv('stay') == 2) ?>><?= lang('stay') ?></option>
                            <option id="stay3" value="" <?= selected(!getv('stay')) ?>><?= lang('close') ?></option>
                        </select>
                    </li>
                <?php endif; ?>
                <?php
                if (getv('a') == 16) {
                    if (evo()->hasPermission('new_template')) {
                        echo evo()->manager->ab([
                            'onclick' => 'duplicaterecord();',
                            'icon' => style('icons_resource_duplicate'),
                            'label' => lang('duplicate')
                        ]);
                    }
                    if (evo()->hasPermission('delete_template')) {
                        echo evo()->manager->ab([
                            'onclick' => 'deletedocument();',
                            'icon' => style('icons_delete_document'),
                            'label' => lang('delete')
                        ]);
                    }
                }
                echo evo()->manager->ab([
                    'onclick' => "document.location.href='index.php?a=76';",
                    'icon' => style('icons_cancel'),
                    'label' => lang('cancel')
                ]);
                ?>
            </ul>
        </div>

        <div class="sectionBody">
            <div class="tab-pane" id="templatesPane">
                <div class="tab-page" id="tabTemplate">
                    <h2 class="tab"><?= lang('template_edit_tab') ?></h2>
                    <div style="margin-bottom:10px;">
                        <b><?= lang('template_name') ?></b>
                        <input name="templatename" type="text" maxlength="100"
                            value="<?= evo()->hsc(template('templatename')) ?>"
                            class="inputBox"
                            style="width:200px;">
                        <?php
                        $rs = db()->select(
                            '*',
                            '[+prefix+]site_templates',
                            id() ? "parent!='" . id() . "'" : ''
                        );
                        $parent = [];
                        while ($row = db()->getRow($rs)) {
                            if (id() == $row['id']) {
                                continue;
                            }
                            $parent[] = array(
                                'id' => $row['id'],
                                'templatename' => evo()->hsc($row['templatename'])
                            );
                        }
                        $tpl = '<option value="[+id+]" [+selected+]>[+templatename+]([+id+])</option>';
                        $option = [];
                        foreach ($parent as $ph) {
                            $ph['selected'] = template('parent') == $ph['id'] ? 'selected' : '';
                            $option[] = evo()->parseText($tpl, $ph);
                        }
                        echo lang('template_parent');
                        ?>
                        <select name="parent">
                            <option value="0">None</option>
                            <?= implode("\n", $option) ?>
                        </select>
                    </div>
                    <?php
                    if (template('parent') != 0) {
                        $parent = getParentValues(template('parent'));
                    }
                    if (!empty($parent)) {
                        $head = $parent['head'] ?? '';
                        $foot = $parent['foot'] ?? '';
                    }
                    ?>
                    <div style="width:100%;position:relative">
                        <div
                            style="padding:3px 8px; overflow:hidden;zoom:1; background-color:#eeeeee; border:1px solid #c3c3c3; border-bottom:none;margin-top:5px;">
                            <span style="float:left;font-weight:bold;"><?= lang('template_code') ?></span>
                        </div>
                        <?php if (isset($head)) {
                            echo $head;
                        } ?>
                        <textarea dir="ltr" name="content" class="phptextarea"
                            style="width:100%; height: 370px;"><?= evo()->hsc(template('content')) ?></textarea>
                        <?php if (isset($foot)) {
                            echo $foot;
                        } ?>
                    </div>
                    <!-- HTML text editor end -->
                </div>

                <div class="tab-page" id="tabProp">
                    <h2 class="tab"><?= lang('settings_properties') ?></h2>
                    <table>
                        <tr>
                            <th><?= lang('existing_category') ?>:</th>
                            <td><select id="categoryid" name="categoryid" style="width:300px;">
                                    <option value="0"><?= lang('no_category') ?></option>
                                    <?php
                                    $ds = evo()->manager->getCategories();
                                    if ($ds) {
                                        foreach ($ds as $n => $v) {
                                            echo sprintf(
                                                '<option value="%s" %s>%s</option>',
                                                $v['id'],
                                                selected(template('category') == $v['id']),
                                                evo()->hsc($v['category'])
                                            );
                                        }
                                    }
                                    ?>
                                    <option value="-1">&gt;&gt; <?= lang('new_category') ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr id="newcategry" style="display:none;">
                            <th valign="top" style="padding-top:5px;">
                                <?= lang('new_category') ?>:
                            </th>
                            <td valign="top" style="padding-top:5px;">
                                <input name="newcategory"
                                    type="text"
                                    maxlength="45"
                                    value="<?= template('newcategory', '') ?>"
                                    class="inputBox" style="width:300px;">
                            </td>
                        </tr>
                        <tr>
                            <th><?= lang('template_desc') ?>:&nbsp;&nbsp;</th>
                            <td><textarea name="description"
                                    style="padding:0;height:4em;"><?= evo()->hsc(template('description')) ?></textarea>
                            </td>
                        </tr>
                        <?php if (evo()->hasPermission('save_template') == 1) { ?>
                            <tr>
                                <td colspan="2">
                                    <label>
                                        <input
                                            id="locked" name="locked"
                                            type="checkbox" class="inputBox"
                                            <?= checked(template('locked') == 1) ?>
                                        > <?= lang('lock_template') ?>
                                        <span class="comment"><?= lang('lock_template_msg') ?></span>
                                    </label>
                                </td>
                            </tr>
                        <?php } ?>
                    </table>
                </div>

                <?php
                if (getv('a') == '16') {
                    $rs = db()->select(
                        [
                            'name' => 'tv.name',
                            'id' => 'tv.id',
                            'tplid' => 'tpl.templateid',
                            'tpl.rank',
                            sprintf(
                                "if(isnull(cat.category),'%s',cat.category) as category",
                                lang('no_category')
                            ),
                            'desc' => 'tv.description'
                        ],
                        [
                            '[+prefix+]site_tmplvar_templates tpl',
                            'INNER JOIN [+prefix+]site_tmplvars tv ON tv.id=tpl.tmplvarid',
                            'LEFT JOIN [+prefix+]categories cat ON tv.category=cat.id',
                        ],
                        sprintf("tpl.templateid='%s'", id()),
                        'tpl.rank, tv.rank, tv.id'
                    );
                    $total = db()->count($rs);
                    ?>
                    <div class="tab-page" id="tabInfo">
                        <h2 class="tab"><?= lang('info') ?></h2>
                        <?= "<p>" . lang('template_tv_msg') . "</p>" ?>
                        <div class="sectionHeader">
                            <?= lang('template_assignedtv_tab') ?>
                        </div>
                        <div class="sectionBody">
                            <?php
                            if ($total) {
                                $tvList = '<ul>';
                                while ($row = db()->getRow($rs)) {
                                    $tvList .= sprintf(
                                        '<li><a href="index.php?id=%s&amp;a=301">%s</a>%s</li>',
                                        $row['id'],
                                        $row['name'],
                                        $row['desc'] ? sprintf(' (%s)', $row['desc']) : ''
                                    );
                                }
                                $tvList .= '</ul>';
                            } else {
                                $tvList = lang('template_no_tv');
                            }
                            echo $tvList;
                            ?>
                            <ul class="actionButtons" style="margin-top:15px;">
                                <?php
                                $query = getv('id') ? '&amp;tpl=' . (int)getv('id') : '';
                                ?>
                                <li>
                                    <a href="index.php?&amp;a=300<?= $query ?>">
                                        <img src="<?= style('icons_add') ?>"/>
                                        <?= lang('new_tmplvars') ?>
                                    </a>
                                </li>
                                <?php
                                if (evo()->hasPermission('save_template') && $total > 1) {
                                    echo '<li><a href="index.php?a=117&amp;id=' . getv('id') . '"><img src="' . style('sort') . '" />' . lang('template_tv_edit') . '</a></li>';
                                }
                                ?>
                            </ul>
                        </div>
                        <div class="sectionHeader"><?= lang('a16_use_resources') ?></div>
                        <div class="sectionBody">
                            <div id="use_resources"></div>
                        </div>
                    </div>
                    <?php
                }
                ?>
                <div class="tab-page" id="tabHelp">
                    <h2 class="tab">ヘルプ</h2>
                    <?= lang('template_msg') ?>
                </div>
                <?php
                // invoke OnTempFormRender event
                $tmp = array('id' => id());
                $evtOut = evo()->invokeEvent("OnTempFormRender", $tmp);
                if (is_array($evtOut)) {
                    echo implode("", $evtOut);
                }
                ?>
            </div>
        </div>
    </form>
    <script>
        var tpstatus = <?= (config('remember_last_tab') == 2 || getv('stay') == 2) ? 'true' : 'false' ?>;
        tpTemplates = new WebFXTabPane(document.getElementById("templatesPane"), tpstatus);
        var readonly = <?= (template('locked') == 1 || template('locked') === 'on') ? '1' : '0' ?>;
        if (readonly) {
            jQuery('#templatesPane textarea,input[type=text]').prop('readonly', true);
            jQuery('#templatesPane select').addClass('readonly');
            jQuery('#templatesPane select').prop('disabled', true);
            jQuery('#Button1').hide();
            jQuery('input#locked').click(function () {
                jQuery('#Button1').toggle();
            });
        }
        jQuery('input#locked').click(function () {
            jQuery('#templatesPane textarea,input[type=text]').prop('readonly', jQuery(this).prop('checked'));
            jQuery('#templatesPane select').prop('disabled', true);
            jQuery('#templatesPane select').toggleClass('readonly');
        });
        jQuery.get("index.php", {
                a: "1",
                ajaxa: "16",
                target: "use_resources",
                id: "<?= id() ?>"
            },
            function (data) {
                jQuery('div#use_resources').html(data);
            });
        jQuery('select#categoryid').change(function () {
            if (jQuery(this).val() == '-1') {
                jQuery('#newcategry').fadeIn();
            } else {
                jQuery('#newcategry').fadeOut();
                jQuery('input[name="newcategory"]').val('');
            }
        });
    </script>
<?php
function getParentValues($parent)
{
    $rs = db()->select('*', '[+prefix+]site_templates', "id='" . $parent . "'");
    $total = db()->count($rs);
    $p = (object)db()->getRow($rs);
    if ($total == 1) {
        if (strpos($p->content, '[*#content*]') !== false) {
            $p->content = str_replace('[*#content*]', '[*content*]', $p->content);
        }
    }
    if ($total != 1 || strpos($p->content, '[*content*]') === false) {
        return [];
    }

    $content = explode('[*content*]', $p->content, 2);
    $divstyle = "border:1px solid #C3C3C3;padding:1em;background-color:#f7f7f7;";
    $prestyle = "white-space: pre-wrap;display:block;width:auto; font-family: 'Courier New','Courier', monospace;";
    $head = sprintf(
        '<div style="%s border-bottom:none;"><pre style="%s">%s</pre></div>',
        $divstyle,
        $prestyle,
        evo()->hsc(trim($content[0]))
    );
    $foot = sprintf(
        '<div style="%s border-top:none;"><pre style="%s">%s</pre></div>',
        $divstyle,
        $prestyle,
        evo()->hsc(trim($content[1]))
    );
    return compact('head', 'foot');
}

function template($key, $default = null)
{
    static $tplObject = [];
    if (isset($tplObject[$key])) {
        return $tplObject[$key];
    }
    if (getv('id')) {
        $rs = db()->select('*', '[+prefix+]site_templates', "id='" . getv('id') . "'");
        $total = db()->count($rs);
        if ($total > 1) {
            echo "Oops, something went terribly wrong...<p>";
            echo "More results returned than expected. Which sucks. <p>Aborting.";
            exit;
        }
        if ($total < 1) {
            echo "Oops, something went terribly wrong...<p>";
            echo "No database record has been found for this template. <p>Aborting.";
            exit;
        }
        $tplObject = db()->getRow($rs);
        $_SESSION['itemname'] = $tplObject['templatename'];
    } else {
        $_SESSION['itemname'] = 'New template';
    }
    $tplObject = array_merge($tplObject, $_POST);
    return evo()->array_get($tplObject, $key, $default);
}

function id()
{
    if (preg_match('@^[0-9]+$@', getv('id'))) {
        return getv('id');
    }
    return '';
}

function is_locked($id)
{
    $rs = db()->select(
        'internalKey, username',
        '[+prefix+]active_users',
        sprintf("action=16 AND id='%s'", $id)
    );
    if (!db()->count($rs)) {
        return false;
    }

    while ($row = db()->getRow($rs)) {
        if ($row['internalKey'] == evo()->getLoginUserID()) {
            continue;
        }
        return $row['username'];
    }
    return false;
}

function checked($cond)
{
    if ($cond) {
        return 'checked';
    }
    return '';
}

function selected($cond)
{
    if ($cond) {
        return 'selected';
    }
    return '';
}
