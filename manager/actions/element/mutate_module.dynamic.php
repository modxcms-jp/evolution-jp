<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

switch (request_intvar('a')) {
    case 107:
        if (!evo()->hasPermission('new_module')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        break;
    case 108:
        if (!evo()->hasPermission('edit_module')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        break;
    default:
        alert()->setError(3);
        alert()->dumpError();
}

abort_if_invalid_id('id', request_intvar('a') === 108);

$id = request_intvar('id');

// Check to see the editor isn't locked
$rs = db()->select('internalKey, username', '[+prefix+]active_users', "action=108 AND id='{$id}'");
$total = db()->count($rs);
if ($total > 1) {
    for ($i = 0; $i < $total; $i++) {
        $lock = db()->getRow($rs);
        if ($lock['internalKey'] != evo()->getLoginUserID()) {
            $msg = sprintf(lang('lock_msg'), $lock['username'], 'module');
            alert()->setError(5, $msg);
            alert()->dumpError();
        }
    }
}
// end check for lock

if (get_intvar('id')) {
    $rs = db()->select('*', '[+prefix+]site_modules', "id='{$id}'");
    $total = db()->count($rs);
    if ($total > 1) {
        exit('<p>Multiple modules sharing same unique id. Not good.<p>');
    }
    if ($total < 1) {
        alert()->setError(3);
        alert()->dumpError();
    }
    $content = db()->getRow($rs);
    $_SESSION['itemname'] = content('name');
} else {
    $_SESSION['itemname'] = 'New Module';
    $content['wrap'] = '1';
}

// 名前の検証で差し戻されたときは、送信した入力を重ねる
$content = manager()->restoreElementFormValues(
    $content,
    'modulecode',
    ['disabled', 'wrap', 'locked', 'enable_resource', 'enable_sharedparams'],
    ['user_groups']
);
$modx->moduleObject = $content;

function content($key, $default = null)
{
    global $content;
    return $content[$key] ?? $default;
}
?>
<script src="<?= manager_asset('media/script/element-properties.js') ?>" type="text/javascript"></script>
<script src="<?= manager_asset('media/script/element-category.js') ?>" type="text/javascript"></script>
<script src="<?= manager_asset('media/script/element-lock.js') ?>" type="text/javascript"></script>
<script type="text/javascript">
    var docid = <?= request_intvar('id') ?>;
    Evo.ready(function() {
        EvoElementLock.init(document.forms['mutate'], {
            locked: <?= content('locked') == 1 ? 'true' : 'false' ?>,
            saveButton: '#Button1'
        });
        EvoElementCategory.init(document.forms['mutate']);
        const sharedInputs = document.querySelectorAll('input[name="enable_sharedparams"]');
        sharedInputs.forEach(function(el) {
            el.addEventListener('change', function() {
                const checked = Array.from(sharedInputs).some(function(input) {
                    return input.checked;
                });
                document.querySelectorAll('.sharedparams').forEach(function(section) {
                    section.style.display = checked ? '' : 'none';
                });
            });
        });
    });

    function loadDependencies() {
        if (documentDirty) {
            if (!confirm(<?= js_literal(stripcslashes(lang('confirm_load_depends'))) ?>)) {
                return;
            }
        }
        documentDirty = false;
        EvoShell.go("index.php?id=" + docid + "&a=113");
    }

    function duplicaterecord() {
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_duplicate_record'))) ?>)) {
            documentDirty = false;
            EvoCsrf.post("index.php?id=" + docid + "&a=111");
        }
    }

    function deletedocument() {
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_delete_module'))) ?>)) {
            documentDirty = false;
            EvoCsrf.post("index.php?id=" + document.mutate.id.value + "&a=110");
        }
    }

    function setTextWrap(ctrl, b) {
        if (!ctrl) return;
        ctrl.wrap = (b) ? "soft" : "off";
    }


    EvoElementProperties.configure({
        table: {
            style: 'margin-bottom:3px;margin-left:14px;background-color:#EEEEEE',
            cellpadding: '2',
            cellspacing: '1'
        },
        head: [<?= js_literal(lang('parameter')) ?>, <?= js_literal(lang('value')) ?>],
        stacked: false,
        cellStyle: 'background-color:#FFFFFF'
    });

    function showParameters(ctrl) {
        EvoElementProperties.show(ctrl);
    }

    function BrowseServer() {
        window.evoOpenFilePicker(<?= js_literal(config('base_url') . 'manager/media/browser/evo/browser.php?type=images') ?>);
    }

    function SetUrl(url, width, height, alt) {
        document.mutate.icon.value = url;
    }
</script>
<form name="mutate" id="mutate" class="module" method="post" action="index.php?a=109" enctype="multipart/form-data">
    <?php
    // invoke OnModFormPrerender event
    $tmp = ['id' => $id];
    $evtOut = evo()->invokeEvent('OnModFormPrerender', $tmp);
    if (is_array($evtOut)) {
        echo implode('', $evtOut);
    }
    ?>
    <input type="hidden" name="id" value="<?= (int) content('id') ?>">
    <input type="hidden" name="mode" value="<?= hsc(EvoFormFlow::mode('module')) ?>">

    <h1><?= lang('module_title') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php if (evo()->hasPermission('save_module')) : ?>
                <li id="Button1" class="mutate">
                    <a href="#"
                        onclick="documentDirty=false;var f=document.getElementById('mutate');f.noValidate=true;f.requestSubmit();">
                        <?= lang('update') ?>
                    </a>
                    <span class="and"> + </span>
                    <select id="stay" name="after_save">
                        <?= EvoFormFlow::afterSaveOptions(EvoFormFlow::requestedAfterSave(), evo()->hasPermission('new_module')) ?>
                    </select>
                </li>
            <?php endif; ?>
            <?php
            if (request_intvar('a') === 108) {
                if (evo()->hasPermission('delete_module')) {
                    echo manager()->ab(
                        [
                            'onclick' => 'deletedocument();',
                            'label' => lang('delete')
                        ]
                    );
                }
            }
            echo manager()->ab(
                [
                    'onclick' => "EvoShell.go('index.php?a=106');",
                    'label' => lang('cancel')
                ]
            );
            ?>
        </ul>
    </div>
    <!-- end #actions -->

    <div class="sectionBody">
        <p><img class="icon" src="<?= style('icons_modules') ?>" alt="."
                style="vertical-align:middle;text-align:left;" /> <?= lang('module_msg') ?></p>

        <div class="tab-pane" id="modulePane">
            <!-- General -->
            <div class="tab-page" id="tabModule">
                <h2 class="tab"><?= lang('settings_general') ?></h2>
                <table>
                    <tr>
                        <td align="left"><?= lang('module_name') ?>:</td>
                        <td align="left"><input name="name" type="text" maxlength="100"
                                value="<?= hsc(content('name')) ?>" class="inputBox">
                        </td>
                    </tr>
                    <tr>
                        <td align="left" valign="top" colspan="2"><input name="disabled"
                                type="checkbox" <?= content('disabled') == 1 ? 'checked="checked"' : '' ?>
                                value="on" class="inputBox" />
                            <span class="clickable-label"
                                onclick="document.mutate.disabled.click();"><?= content('disabled') == 1 ? '<span class="mutate-field-title">' . lang('module_disabled') . '</span>' : lang('module_disabled') ?></span>
                        </td>
                    </tr>
                </table>

                <!-- PHP text editor start -->
                <div class="code-editor" style="position:relative">
                    <div class="code-editor-header">
                        <span class="code-editor-title"><?= lang('module_code') ?></span>
                        <span class="code-editor-options"><?= lang('wrap_lines') ?><input name="wrap"
                                type="checkbox" <?= content('wrap') == 1 ? ' checked="checked"' : '' ?> class="inputBox"
                                onclick="setTextWrap(document.mutate.post,this.checked)" /></span>
                    </div>
                    <textarea dir="ltr" class="phptextarea" name="post"
                        style="width:100%; height:370px;"
                        wrap="<?= content('wrap') == 1 ? 'soft' : 'off' ?>"><?= hsc(content('modulecode')) ?></textarea>
                </div>
                <!-- PHP text editor end -->
            </div>

            <!-- Configuration -->
            <div class="tab-page" id="tabConfig">
                <h2 class="tab"><?= lang('settings_config') ?></h2>
                <table>
                    <tr>
                        <td align="left"><?= lang('existing_category') ?>:</td>
                        <td align="left">
                            <select name="categoryid">
                                <?= manager()->elementCategoryOptions(content('category')) ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="newcategry" style="display:none;">
                        <td align="left" valign="top" style="padding-top:5px;"><?= lang('new_category') ?>
                            :
                        </td>
                        <td align="left" valign="top" style="padding-top:5px;"><input name="newcategory" type="text"
                                maxlength="45" value="<?= hsc(content('newcategory', '')) ?>"
                                class="inputBox"></td>
                    </tr>
                    <tr>
                        <td align="left"><?= lang('module_desc') ?>:</td>
                        <td align="left"><textarea name="description"
                                style="padding:0;width:300px;height:4em;"><?= hsc(content('description')) ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td align="left"><?= lang('icon') ?> <span class="comment">(32x32)</span>:</td>
                        <td align="left"><input type="text" maxlength="255" style="width: 235px;" name="icon"
                                value="<?= hsc(content('icon')) ?>" /> <input type="button"
                                value="<?= lang('insert') ?>"
                                onclick="BrowseServer();" />
                        </td>
                    </tr>
                    <tr style="display:none;">
                        <td align="left"><input name="enable_resource" title="<?= lang('enable_resource') ?>"
                                type="checkbox" <?= content('enable_resource') == 1 ? ' checked="checked"' : '' ?>
                                class="inputBox" /> <span style="cursor:pointer"
                                onclick="document.mutate.enable_resource.click();"
                                title="<?= lang('enable_resource') ?>"><?= lang("element") ?></span>:
                        </td>
                        <td align="left"><input name="resourcefile" type="text" maxlength="255"
                                value="<?= hsc(content('resourcefile')) ?>" class="inputBox" />
                        </td>
                    </tr>
                    <?php if (evo()->hasPermission('save_module') == 1) { ?>
                        <tr>
                            <td align="left" valign="top" colspan="2"><input name="locked"
                                    type="checkbox" <?= content('locked') == 1 ? ' checked="checked"' : '' ?>
                                    class="inputBox" />
                                <span style="cursor:pointer"
                                    onclick="document.mutate.locked.click();"><?= lang('lock_module') ?></span>
                                <span class="comment"><?= lang('lock_module_msg') ?></span>
                            </td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td align="left" valign="top"><?= lang('module_config') ?>:</td>
                        <td align="left" valign="top"><textarea
                                name="properties"
                                style="display:block;"
                                maxlength="65535"
                                class="inputBox phptextarea"
                                onchange="showParameters(this);"><?= hsc(content('properties')) ?></textarea>
                            <input type="button" value="<?= lang('update_params') ?>"
                                style="width:16px; margin-left:2px;" title="<?= lang('update_params') ?>" />
                        </td>
                    </tr>
                    <tr id="displayparamrow">
                        <td valign="top" align="left">&nbsp;</td>
                        <td align="left" id="displayparams">&nbsp;</td>
                    </tr>
                </table>
            </div>

            <?php if (request_intvar('a') === 107) { ?>
                <input name="guid" type="hidden" value="<?= createGUID() ?>" />
            <?php } elseif (request_intvar('a') === 108) { ?>
                <!-- Dependencies -->
                <div class="tab-page" id="tabDepend">
                    <h2 class="tab"><?= lang('settings_dependencies') ?></h2>
                    <div class="sectionBody">
                        <?php
                        $display = (content('enable_sharedparams') != 1) ? 'style="display:none;"' : '';
                        ?>
                        <table>
                            <tr>
                                <td align="left" valign="top" colspan="2">
                                    <input
                                        name="enable_sharedparams"
                                        type="checkbox"
                                        <?= content('enable_sharedparams') == 1 ? ' checked="checked"' : '' ?>
                                        class="inputBox" />
                                    <span style="cursor:pointer"
                                        onclick="document.mutate.enable_sharedparams.click();">
                                        <?= lang('enable_sharedparams') ?>:
                                    </span>
                                </td>
                            </tr>
                            <tr class="sharedparams" <?= $display ?>>
                                <td align="left" valign="top"><?= lang('guid') ?>:</td>
                                <td align="left" valign="top">
                                    <input
                                        name="guid" type="text" maxlength="32"
                                        value="<?=
                                                hsc(content('guid') ?: createGUID())
                                                ?>"
                                        class="inputBox" /><br />
                                    <span class="comment"><?= lang('enable_sharedparams_msg') ?></span><br />
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="sectionBody sharedparams" <?= $display ?>>
                        <p><?= lang('module_viewdepend_msg') ?></p>
                        <p class="actionButtons" style="float:none;overflow:hidden;zoom:1">
                            <a href="#" onclick="loadDependencies();return false;">
                                <?= lang('manage_depends') ?>
                            </a>
                        </p>
                        <?php
                        $field = 'smd.id, COALESCE(ss.name,st.templatename,sv.name,sc.name,sp.name,sd.pagetitle) AS `name`, ' .
                            'CASE smd.type' .
                            " WHEN 10 THEN 'Chunk'" .
                            " WHEN 20 THEN 'Document'" .
                            " WHEN 30 THEN 'Plugin'" .
                            " WHEN 40 THEN 'Snippet'" .
                            " WHEN 50 THEN 'Template'" .
                            " WHEN 60 THEN 'TV'" .
                            'END AS `type`';
                        $from = '[+prefix+]site_module_depobj AS smd ' .
                            'LEFT JOIN [+prefix+]site_htmlsnippets AS sc ON sc.id = smd.resource AND smd.type = 10 ' .
                            'LEFT JOIN [+prefix+]site_content AS sd ON sd.id = smd.resource AND smd.type = 20 ' .
                            'LEFT JOIN [+prefix+]site_plugins AS sp ON sp.id = smd.resource AND smd.type = 30 ' .
                            'LEFT JOIN [+prefix+]site_snippets AS ss ON ss.id = smd.resource AND smd.type = 40 ' .
                            'LEFT JOIN [+prefix+]site_templates AS st ON st.id = smd.resource AND smd.type = 50 ' .
                            'LEFT JOIN [+prefix+]site_tmplvars AS sv ON sv.id = smd.resource AND smd.type = 60 ';
                        $ds = db()->select($field, $from, "smd.module='{$id}' ORDER BY smd.type,name");
                        if (!$ds) {
                            echo "An error occured while loading module dependencies.";
                        } else {
                            include_once(MODX_CORE_PATH . 'controls/data_table.class.php');
                            $table = new DataTable(
                                [
                                    ['key' => 'name', 'label' => lang('element_name')],
                                    ['key' => 'type', 'label' => lang('type')],
                                ],
                                [
                                    'id' => 'module-depends',
                                    'tableClass' => 'grid',
                                    'emptyMessage' => lang('no_records_found'),
                                ]
                            );
                            echo $table->render(db()->getRows($ds) ?: []);
                        } ?>
                    </div>
                </div>
            <?php } ?>
            <?php
            if (config('use_udperms') == 1 && evo()->hasPermission('access_permissions')) {
            ?>
                <!-- Access permissions -->
                <div class="tab-page" id="tabAccess">
                    <h2 class="tab"><?= lang('group_access_permissions') ?></h2>
                    <?php
                    // fetch user access permissions for the module
                    $groupsarray = [];
                    if (isset($content['user_groups'])) {
                        // 差し戻された入力があれば、DB よりそちらを優先する
                        $groupsarray = array_map('intval', (array)$content['user_groups']);
                    } elseif (is_numeric($id) && $id > 0) {
                        $rs = db()->select('*', '[+prefix+]site_module_access', sprintf("module='%d'", (int)$id));
                        $total = db()->count($rs);
                        for ($i = 0; $i < $total; $i++) {
                            $currentgroup = db()->getRow($rs);
                            $groupsarray[$i] = $currentgroup['usergroup'];
                        }
                    }

                    ?>
                        <!-- User Group Access Permissions -->
                        <script type="text/javascript">
                            function makePublic(b) {
                                var notPublic = false;
                                var f = document.forms['mutate'];
                                var chkpub = f['chkallgroups'];
                                var chks = f['user_groups[]'];
                                if (!chks && chkpub) {
                                    chkpub.checked = true;
                                    return false;
                                } else if (!b && chkpub) {
                                    if (!chks.length) notPublic = chks.checked;
                                    else
                                        for (i = 0; i < chks.length; i++)
                                            if (chks[i].checked) notPublic = true;
                                    chkpub.checked = !notPublic;
                                } else {
                                    if (!chks.length) chks.checked = (b) ? false : chks.checked;
                                    else
                                        for (i = 0; i < chks.length; i++)
                                            if (b) chks[i].checked = false;
                                    chkpub.checked = true;
                                }
                            }
                        </script>
                        <p><?= lang('module_group_access_msg') ?></p>
                    <?php
                    $chks = '';
                    $notPublic = false;
                    $rs = db()->select('name, id', '[+prefix+]membergroup_names');
                    $total = db()->count($rs);
                    for ($i = 0; $i < $total; $i++) {
                        $row = db()->getRow($rs);
                        $checked = in_array($row['id'], $groupsarray);
                        if ($checked) {
                            $notPublic = true;
                        }
                        $chks .= '<label><input type="checkbox" name="user_groups[]" value="' . $row['id'] . '"' . ($checked ? ' checked="checked"' : '') . ' onclick="makePublic(false)" />' . hsc($row['name']) . "</label><br />\n";
                    }
                    $chks = '<label><input type="checkbox" name="chkallgroups"' . (!$notPublic ? ' checked="checked"' : '') . ' onclick="makePublic(true)" /><span class="mutate-field-title">' . lang('all_usr_groups') . '</span></label><br />' . "\n" . $chks;
                    echo $chks;
                    ?>
                </div>
            <?php
            }
            ?>
        </div>
    </div>
    <?php
    // invoke OnModFormRender event
    $tmp = ['id' => $id];
    $evtOut = evo()->invokeEvent('OnModFormRender', $tmp);
    if (is_array($evtOut)) {
        echo implode('', $evtOut);
    }
    ?>
</form>
<script type="text/javascript">
    var tpstatus = <?= EvoFormFlow::rememberTab() ? 'true' : 'false' ?>;
    tpModule = new WebFXTabPane(document.getElementById("modulePane"), tpstatus);
    setTimeout('showParameters();', 10);
</script>

<?php
// create globally unique identifiers (guid)
function createGUID()
{
    return md5(
        uniqid(getmypid() . mt_rand() . (float)microtime() * 1000000, 1)
    );
}
