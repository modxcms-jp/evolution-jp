<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

switch ((int)$_REQUEST['a']) {
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

$id = preg_match('@^[1-9][0-9]*$@', $_REQUEST['id']) ? $_REQUEST['id'] : 0;

// Check to see the editor isn't locked
$rs = db()->select('internalKey, username', '[+prefix+]active_users', "action=108 AND id='{$id}'");
$total = db()->count($rs);
if ($total > 1) {
    for ($i = 0; $i < $total; $i++) {
        $lock = db()->getRow($rs);
        if ($lock['internalKey'] != evo()->getLoginUserID()) {
            $msg = sprintf($_lang['lock_msg'], $lock['username'], 'module');
            alert()->setError(5, $msg);
            alert()->dumpError();
        }
    }
}
// end check for lock

// make sure the id's a number
if (!is_numeric($id)) {
    exit('Passed ID is NaN!');
}

if (isset($_GET['id']) && preg_match('@^[1-9][0-9]*$@', $_GET['id'])) {
    $rs = db()->select('*', '[+prefix+]site_modules', "id='{$id}'");
    $total = db()->count($rs);
    if ($total > 1) {
        exit('<p>Multiple modules sharing same unique id. Not good.<p>');
    }
    if ($total < 1) {
        exit('<p>No record found for id: ' . $id . '.</p>');
    }
    $content = db()->getRow($rs);
    $_SESSION['itemname'] = $content['name'];
} else {
    $_SESSION['itemname'] = 'New Module';
    $content['wrap'] = '1';
}
$modx->moduleObject = $content;
?>
<script type="text/javascript">
    var docid = <?= anyv('id') ?>;
    jQuery(function() {
        jQuery('select[name="categoryid"]').change(function() {
            if (jQuery(this).val() == '-1') {
                jQuery('#newcategry').fadeIn();
            } else {
                jQuery('#newcategry').fadeOut();
                jQuery('input[name="newcategory"]').val('');
            }
        });
        jQuery('input[name="enable_sharedparams"]').change(function() {
            var checked = jQuery('input[name="enable_sharedparams"]').is(':checked');
            if (checked) {
                jQuery('.sharedparams').fadeIn();
            } else {
                jQuery('.sharedparams').fadeOut();
            }
        });
    });

    function loadDependencies() {
        if (documentDirty) {
            if (!confirm("<?= $_lang['confirm_load_depends'] ?>")) {
                return;
            }
        }
        documentDirty = false;
        window.location.href = "index.php?id=" + docid + "&a=113";
    }

    function duplicaterecord() {
        if (confirm("<?= $_lang['confirm_duplicate_record'] ?>")) {
            documentDirty = false;
            document.location.href = "index.php?id=" + docid + "&a=111";
        }
    }

    function deletedocument() {
        if (confirm("<?= $_lang['confirm_delete_module'] ?>")) {
            documentDirty = false;
            document.location.href = "index.php?id=" + document.mutate.id.value + "&a=110";
        }
    }

    function setTextWrap(ctrl, b) {
        if (!ctrl) return;
        ctrl.wrap = (b) ? "soft" : "off";
    }

    // Current Params
    var currentParams = {};

    function showParameters(ctrl) {
        var c, p, df, cp;
        var ar, desc, value, key, dt;

        currentParams = {}; // reset;

        if (ctrl) {
            f = ctrl.form;
        } else {
            f = document.forms['mutate'];
            if (!f) return;
        }

        // setup parameters
        tr = document.getElementById('displayparamrow');
        dp = (f.properties.value) ? f.properties.value.split("&") : "";
        if (!dp) tr.style.display = 'none';
        else {
            t = '<table style="margin-bottom:3px;margin-left:14px;background-color:#EEEEEE" cellpadding="2" cellspacing="1"><thead><tr><td><?= $_lang['parameter'] ?></td><td><?= $_lang['value'] ?></td></tr></thead>';
            for (p = 0; p < dp.length; p++) {
                dp[p] = (dp[p] + '').replace(/^\s|\s$/, ""); // trim
                ar = dp[p].split("=");
                key = ar[0]; // param
                ar = (ar[1] + '').split(";");
                desc = ar[0]; // description
                dt = ar[1]; // data type
                value = decode((ar[2]) ? ar[2] : '');

                // store values for later retrieval
                if (key && dt === 'list') currentParams[key] = [desc, dt, value, ar[3]];
                else if (key) currentParams[key] = [desc, dt, value];

                if (dt) {
                    switch (dt) {
                        case 'int':
                            c = '<input type="text" name="prop_' + key + '" value="' + value + '" size="30" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)" />';
                            break;
                        case 'menu':
                            value = ar[3];
                            c = '<select name="prop_' + key + '" style="width:168px" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)">';
                            ls = (ar[2] + '').split(",");
                            if (currentParams[key] == ar[2]) currentParams[key] = ls[0]; // use first list item as default
                            for (i = 0; i < ls.length; i++) {
                                c += '<option value="' + ls[i] + '"' + ((ls[i] == value) ? ' selected="selected"' : '') + '>' + ls[i] + '</option>';
                            }
                            c += '</select>';
                            break;
                        case 'list':
                            value = ar[3];
                            ls = (ar[2] + '').split(",");
                            if (currentParams[key] == ar[2]) currentParams[key] = ls[0]; // use first list item as default
                            c = '<select name="prop_' + key + '" size="' + ls.length + '" style="width:168px" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)">';
                            for (i = 0; i < ls.length; i++) {
                                c += '<option value="' + ls[i] + '"' + ((ls[i] == value) ? ' selected="selected"' : '') + '>' + ls[i] + '</option>';
                            }
                            c += '</select>';
                            break;
                        case 'list-multi':
                            value = (ar[3] + '').replace(/^\s|\s$/, "");
                            arrValue = value.split(",")
                            ls = (ar[2] + '').split(",");
                            if (currentParams[key] == ar[2]) currentParams[key] = ls[0]; // use first list item as default
                            c = '<select name="prop_' + key + '" size="' + ls.length + '" multiple="multiple" style="width:168px" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)">';
                            for (i = 0; i < ls.length; i++) {
                                if (arrValue.length) {
                                    for (j = 0; j < arrValue.length; j++) {
                                        if (ls[i] == arrValue[j]) {
                                            c += '<option value="' + ls[i] + '" selected="selected">' + ls[i] + '</option>';
                                        } else {
                                            c += '<option value="' + ls[i] + '">' + ls[i] + '</option>';
                                        }
                                    }
                                } else {
                                    c += '<option value="' + ls[i] + '">' + ls[i] + '</option>';
                                }
                            }
                            c += '</select>';
                            break;
                        case 'textarea':
                            c = '<textarea class="phptextarea" name="prop_' + key + '" cols="50" rows="4" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)">' + value + '</textarea>';
                            break;
                        default: // string
                            c = '<input type="text" name="prop_' + key + '" value="' + value + '" size="30" onchange="setParameter(\'' + key + '\',\'' + dt + '\',this)" />';
                            break;

                    }
                    t += '<tr><td bgcolor="#FFFFFF">' + desc + '</td><td bgcolor="#FFFFFF">' + c + '</td></tr>';
                }

            }
            t += '</table>';
            td = document.getElementById('displayparams');
            td.innerHTML = t;
            tr.style.display = '';
        }
        implodeParameters();
    }

    function setParameter(key, dt, ctrl) {
        var v;
        if (!ctrl) return null;
        switch (dt) {
            case 'int':
                ctrl.value = parseInt(ctrl.value);
                if (isNaN(ctrl.value)) ctrl.value = 0;
                v = ctrl.value;
                break;
            case 'menu':
                v = ctrl.options[ctrl.selectedIndex].value;
                currentParams[key][3] = v;
                implodeParameters();
                return;
            case 'list':
                v = ctrl.options[ctrl.selectedIndex].value;
                currentParams[key][3] = v;
                implodeParameters();
                return;
            case 'list-multi':
                var arrValues = [];
                for (var i = 0; i < ctrl.options.length; i++) {
                    if (ctrl.options[i].selected) {
                        arrValues.push(ctrl.options[i].value);
                    }
                }
                currentParams[key][3] = arrValues.toString();
                implodeParameters();
                return;
            default:
                v = ctrl.value + '';
                break;
        }
        currentParams[key][2] = v;
        implodeParameters();
    }

    // implode parameters
    function implodeParameters() {
        var v, p, s = '';
        for (p in currentParams) {
            if (currentParams[p]) {
                v = currentParams[p].join(";");
                if (s && v) s += ' ';
                if (v) s += '&' + p + '=' + encode(v);
            }
        }
        document.forms['mutate'].properties.value = s;
    }

    function encode(s) {
        s = s + '';
        s = s.replace(/=/g, '%3D'); // =
        s = s.replace(/&/g, '%26'); // &
        return s;
    }

    function decode(s) {
        s = s + '';
        s = s.replace(/%3D/g, '='); // =
        s = s.replace(/%26/g, '&'); // &
        return s;
    }

    // Resource browser
    function OpenServerBrowser(url, width, height) {
        let iLeft = (screen.width - width) / 2;
        let iTop = (screen.height - height) / 2;

        let sOptions = "toolbar=no,status=no,resizable=yes,dependent=yes";
        sOptions += ",width=" + width;
        sOptions += ",height=" + height;
        sOptions += ",left=" + iLeft;
        sOptions += ",top=" + iTop;

        var oWindow = window.open(url, "FCKBrowseWindow", sOptions);
    }

    function BrowseServer() {
        let w = screen.width * 0.7;
        let h = screen.height * 0.7;
        OpenServerBrowser("<?= $base_url ?>manager/media/browser/mcpuk/browser.php?Type=images", w, h);
    }

    function SetUrl(url, width, height, alt) {
        document.mutate.icon.value = url;
    }
</script>
<form name="mutate" id="mutate" class="module" method="post" action="index.php?a=109" enctype="multipart/form-data">
    <?php
    // invoke OnModFormPrerender event
    $tmp = array('id' => $id);
    $evtOut = evo()->invokeEvent('OnModFormPrerender', $tmp);
    if (is_array($evtOut)) {
        echo implode('', $evtOut);
    }
    ?>
    <input type="hidden" name="id" value="<?= $content['id'] ?>">
    <input type="hidden" name="mode" value="<?= $_GET['a'] ?>">

    <h1><?= $_lang['module_title'] ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php if (evo()->hasPermission('save_module')) : ?>
                <li id="Button1" class="mutate">
                    <a href="#" onclick="documentDirty=false;jQuery('#mutate').submit();jQuery('#Button1').hide();jQuery('input,textarea,select').addClass('readonly');">
                        <img src="<?= $_style["icons_save"] ?>" /> <?= $_lang['update'] ?>
                    </a>
                    <span class="and"> + </span>
                    <select id="stay" name="stay">
                        <?php if (evo()->hasPermission('new_module')) { ?>
                            <option id="stay1" value="1" <?= $_REQUEST['stay'] == '1' ? ' selected=""' : '' ?>><?= $_lang['stay_new'] ?></option>
                        <?php } ?>
                        <option id="stay2" value="2" <?= $_REQUEST['stay'] == '2' ? ' selected="selected"' : '' ?>><?= $_lang['stay'] ?></option>
                        <option id="stay3" value="" <?= $_REQUEST['stay'] == '' ? ' selected=""' : '' ?>><?= $_lang['close'] ?></option>
                    </select>
                </li>
            <?php endif; ?>
            <?php
            if ($_REQUEST['a'] == '108') {
                if (evo()->hasPermission('delete_module')) {
                    echo $modx->manager->ab(
                        array(
                            'onclick' => 'deletedocument();',
                            'icon' => $_style['icons_delete_document'],
                            'label' => $_lang['delete']
                        )
                    );
                }
            }
            echo $modx->manager->ab(
                array(
                    'onclick' => "document.location.href='index.php?a=106';",
                    'icon' => $_style['icons_cancel'],
                    'label' => $_lang['cancel']
                )
            );
            ?>
        </ul>
    </div>
    <!-- end #actions -->

    <div class="sectionBody">
        <p><img class="icon" src="<?= $_style['icons_modules'] ?>" alt="." style="vertical-align:middle;text-align:left;" /> <?= $_lang['module_msg'] ?></p>

        <div class="tab-pane" id="modulePane">
            <!-- General -->
            <div class="tab-page" id="tabModule">
                <h2 class="tab"><?= $_lang['settings_general'] ?></h2>
                <table>
                    <tr>
                        <td align="left"><?= $_lang['module_name'] ?>:</td>
                        <td align="left"><input name="name" type="text" maxlength="100" value="<?= htmlspecialchars($content['name']) ?>" class="inputBox"></td>
                    </tr>
                    <tr>
                        <td align="left" valign="top" colspan="2"><input name="disabled" type="checkbox" <?= $content['disabled'] == 1 ? 'checked="checked"' : '' ?> value="on" class="inputBox" />
                            <span style="cursor:pointer" onclick="document.mutate.disabled.click();"><?= $content['disabled'] == 1 ? '<span class="warning">' . $_lang['module_disabled'] . '</span>' : $_lang['module_disabled'] ?></span>
                        </td>
                    </tr>
                </table>

                <!-- PHP text editor start -->
                <div style="position:relative">
                    <div style="padding:3px 8px; overflow:hidden;zoom:1; background-color:#eeeeee; border:1px solid #c3c3c3; border-bottom:none;margin-top:5px;">
                        <span style="float:left;font-weight:bold;"><?= $_lang['module_code'] ?></span>
                        <span style="float:right; color:#707070"><?= $_lang['wrap_lines'] ?><input name="wrap" type="checkbox" <?= $content['wrap'] == 1 ? ' checked="checked"' : '' ?> class="inputBox" onclick="setTextWrap(document.mutate.post,this.checked)" /></span>
                    </div>
                    <?php
                    if ($content['locked'] === '1') {
                        $readonly = 'readonly';
                    } else {
                        $readonly = '';
                    }
                    ?>
                    <textarea dir="ltr" <?= $readonly ?> class="phptextarea" name="post" style="width:100%; height:370px;" wrap="<?= $content['wrap'] == 1 ? 'soft' : 'off' ?>"><?= htmlspecialchars($content['modulecode']) ?></textarea>
                </div>
                <!-- PHP text editor end -->
            </div>

            <!-- Configuration -->
            <div class="tab-page" id="tabConfig">
                <h2 class="tab"><?= $_lang['settings_config'] ?></h2>
                <table>
                    <tr>
                        <td align="left"><?= $_lang['existing_category'] ?>:</td>
                        <td align="left">
                            <select name="categoryid">
                                <option value="0"><?= $_lang["no_category"] ?></option>
                                <?php
                                $ds = $modx->manager->getCategories();
                                if ($ds) {
                                    foreach ($ds as $n => $v) {
                                        echo "\t\t\t" . '<option value="' . $v['id'] . '"' . ($content['category'] == $v['id'] ? ' selected="selected"' : '') . '>' . htmlspecialchars($v['category']) . "</option>\n";
                                    }
                                }
                                ?>
                                <option value="-1">&gt;&gt; <?= $_lang["new_category"] ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr id="newcategry" style="display:none;">
                        <td align="left" valign="top" style="padding-top:5px;"><?= $_lang['new_category'] ?>
                            :
                        </td>
                        <td align="left" valign="top" style="padding-top:5px;"><input name="newcategory" type="text" maxlength="45" value="" class="inputBox"></td>
                    </tr>
                    <tr>
                        <td align="left"><?= $_lang['module_desc'] ?>:</td>
                        <td align="left"><textarea name="description" style="padding:0;width:300px;height:4em;"><?= $content['description'] ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td align="left"><?= $_lang['icon'] ?> <span class="comment">(32x32)</span>:</td>
                        <td align="left"><input type="text" maxlength="255" style="width: 235px;" name="icon" value="<?= $content['icon'] ?>" /> <input type="button" value="<?= $_lang['insert'] ?>" onclick="BrowseServer();" />
                        </td>
                    </tr>
                    <tr style="display:none;">
                        <td align="left"><input name="enable_resource" title="<?= $_lang['enable_resource'] ?>" type="checkbox" <?= $content['enable_resource'] == 1 ? ' checked="checked"' : '' ?> class="inputBox" /> <span style="cursor:pointer" onclick="document.mutate.enable_resource.click();" title="<?= $_lang['enable_resource'] ?>"><?= $_lang["element"] ?></span>:
                        </td>
                        <td align="left"><input name="resourcefile" type="text" maxlength="255" value="<?= $content['resourcefile'] ?>" class="inputBox" />
                        </td>
                    </tr>
                    <?php if (evo()->hasPermission('save_module') == 1) { ?>
                        <tr>
                            <td align="left" valign="top" colspan="2"><input name="locked" type="checkbox" <?= $content['locked'] == 1 ? ' checked="checked"' : '' ?> class="inputBox" />
                                <span style="cursor:pointer" onclick="document.mutate.locked.click();"><?= $_lang['lock_module'] ?></span>
                                <span class="comment"><?= $_lang['lock_module_msg'] ?></span>
                            </td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <td align="left" valign="top"><?= $_lang['module_config'] ?>:</td>
                        <td align="left" valign="top"><textarea
                                name="properties"
                                style="display:block;"
                                maxlength="65535"
                                class="inputBox phptextarea"
                                onchange="showParameters(this);"
                            ><?= $content['properties'] ?></textarea>
                            <input type="button" value="<?= $_lang['update_params'] ?>" style="width:16px; margin-left:2px;" title="<?= $_lang['update_params'] ?>" />
                        </td>
                    </tr>
                    <tr id="displayparamrow">
                        <td valign="top" align="left">&nbsp;</td>
                        <td align="left" id="displayparams">&nbsp;</td>
                    </tr>
                </table>
            </div>

            <?php if ($_REQUEST['a'] == '107') { ?>
                <input name="guid" type="hidden" value="<?= createGUID() ?>" />
            <?php } elseif ($_REQUEST['a'] == '108') { ?>
                <!-- Dependencies -->
                <div class="tab-page" id="tabDepend">
                    <h2 class="tab"><?= $_lang['settings_dependencies'] ?></h2>
                    <div class="sectionBody">
                        <?php
                        $display = ($content['enable_sharedparams'] != 1) ? 'style="display:none;"' : '';
                        ?>
                        <table>
                            <tr>
                                <td align="left" valign="top" colspan="2">
                                    <input
                                        name="enable_sharedparams"
                                        type="checkbox"
                                        <?= $content['enable_sharedparams'] == 1 ? ' checked="checked"' : '' ?>
                                        class="inputBox"
                                    />
                                    <span style="cursor:pointer" onclick="document.mutate.enable_sharedparams.click();">
                                        <?= $_lang['enable_sharedparams'] ?>:
                                    </span>
                                </td>
                            </tr>
                            <tr class="sharedparams" <?= $display ?>>
                                <td align="left" valign="top"><?= $_lang['guid'] ?>:</td>
                                <td align="left" valign="top">
                                    <input
                                        name="guid" type="text" maxlength="32"
                                        value="<?=
                                        ($content['guid'] != '') ? $content['guid'] : createGUID()
                                        ?>"
                                        class="inputBox"
                                    /><br />
                                    <span class="comment"><?= $_lang['enable_sharedparams_msg'] ?></span><br />
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="sectionBody sharedparams" <?= $display ?>>
                        <p><?= $_lang['module_viewdepend_msg'] ?></p>
                        <p class="actionButtons" style="float:none;overflow:hidden;zoom:1">
                            <a href="#" onclick="loadDependencies();return false;">
                                <img src="<?= $_style["icons_edit_document"] ?>" align="absmiddle" />
                                <?= $_lang['manage_depends'] ?>
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
                            include_once(MODX_CORE_PATH . 'controls/datagrid.class.php');
                            $grd = new DataGrid('', $ds, 0); // set page size to 0 t show all items
                            $grd->noRecordMsg = $_lang['no_records_found'];
                            $grd->cssClass = 'grid';
                            $grd->columnHeaderClass = 'gridHeader';
                            $grd->itemClass = 'gridItem';
                            $grd->altItemClass = 'gridAltItem';
                            $grd->columns = $_lang['element_name'] . " ," . $_lang['type'];
                            $grd->fields = "name,type";
                            echo $grd->render();
                        } ?>
                    </div>
                </div>
            <?php } ?>
            <?php
            if ($modx->config['use_udperms'] == 1) {
            ?>
                <!-- Access permissions -->
                <div class="tab-page" id="tabAccess">
                    <h2 class="tab"><?= $_lang['group_access_permissions'] ?></h2>
                    <?php
                    // fetch user access permissions for the module
                    $groupsarray = array();
                    $rs = db()->select('*', '[+prefix+]site_module_access', "module='{$id}'");
                    $total = db()->count($rs);
                    for ($i = 0; $i < $total; $i++) {
                        $currentgroup = db()->getRow($rs);
                        $groupsarray[$i] = $currentgroup['usergroup'];
                    }

                    if (evo()->hasPermission('access_permissions')) {
                    ?>
                        <!-- User Group Access Permissions -->
                        <script type="text/javascript">
                            function makePublic(b) {
                                var notPublic = false;
                                var f = document.forms['mutate'];
                                var chkpub = f['chkallgroups'];
                                var chks = f['usrgroups[]'];
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
                        <p><?= $_lang['module_group_access_msg'] ?></p>
                    <?php
                    }
                    $chk = '';
                    $rs = db()->select('name, id', '[+prefix+]membergroup_names');
                    $total = db()->count($rs);
                    for ($i = 0; $i < $total; $i++) {
                        $row = db()->getRow($rs);
                        $groupsarray = is_numeric($id) && $id > 0 ? $groupsarray : array();
                        $checked = in_array($row['id'], $groupsarray);
                        if (evo()->hasPermission('access_permissions')) {
                            if ($checked) {
                                $notPublic = true;
                            }
                            $chks .= '<label><input type="checkbox" name="usrgroups[]" value="' . $row['id'] . '"' . ($checked ? ' checked="checked"' : '') . ' onclick="makePublic(false)" />' . $row['name'] . "</label><br />\n";
                        } elseif ($checked) {
                            $chks = '<input type="hidden" name="usrgroups[]"  value="' . $row['id'] . '" />' . "\n" . $chks;
                        }
                    }
                    if (evo()->hasPermission('access_permissions')) {
                        $chks = '<label><input type="checkbox" name="chkallgroups"' . (!$notPublic ? ' checked="checked"' : '') . ' onclick="makePublic(true)" /><span class="warning">' . $_lang['all_usr_groups'] . '</span></label><br />' . "\n" . $chks;
                    }
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
    $tmp = array('id' => $id);
    $evtOut = evo()->invokeEvent('OnModFormRender', $tmp);
    if (is_array($evtOut)) {
        echo implode('', $evtOut);
    }
    ?>
</form>
<script type="text/javascript">
    var tpstatus = <?= (($modx->config['remember_last_tab'] == 2) || ($_GET['stay'] == 2)) ? 'true' : 'false' ?>;
    tpModule = new WebFXTabPane(document.getElementById("modulePane"), tpstatus);
    setTimeout('showParameters();', 10);
</script>

<?php
// create globally unique identifiers (guid)
function createGUID()
{
    mt_srand((float)microtime() * 1000000);
    return md5(
        uniqid(getmypid() . mt_rand() . (float)microtime() * 1000000, 1)
    );
}
