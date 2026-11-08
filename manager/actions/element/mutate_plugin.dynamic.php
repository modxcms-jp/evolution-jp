<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

switch (request_intvar('a')) {
    case 102:
        if (!evo()->hasPermission('edit_plugin')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        break;
    case 101:
        if (!evo()->hasPermission('new_plugin')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        break;
    default:
        alert()->setError(3);
        alert()->dumpError();
}

abort_if_invalid_id('id', request_intvar('a') === 102);

// check to see the plugin editor isn't locked
$rs = db()->select('*', '[+prefix+]active_users', sprintf("action='102' AND id='%d'", get_intvar('id')));
$row = db()->getRow($rs);
if (1 < db()->count($rs) && $row['internalKey'] != evo()->getLoginUserID()) {
    $msg = sprintf($_lang['lock_msg'], $row['username'], $_lang['plugin']);
    alert()->setError(5, $msg);
    alert()->dumpError();
}
// end check for lock

if (get_intvar('id')) {
    $rs = db()->select('*', '[+prefix+]site_plugins', sprintf("id='%d'", get_intvar('id')));
    $total = db()->count($rs);
    $content = db()->getRow($rs);

    if (1 < $total):
        echo "Multiple plugins sharing same unique id. Not good.<p>";
        exit;
    elseif ($total < 1):
        header("Location: " . MODX_SITE_URL);
    endif;

    $_SESSION['itemname'] = entity('name');
} else {
    $content = [];
    $_SESSION['itemname'] = 'New Plugin';
}

// 名前の検証で差し戻されたときは、送信した入力を重ねる
$content = manager()->restoreElementFormValues($content, 'plugincode', ['disabled', 'locked'], ['sysevents']);

function entity($key, $default = null)
{
    global $content;

    return $content[$key] ?? $default;
}
?>
<script src="media/script/element-properties.js?<?= filemtime(MODX_MANAGER_PATH . 'media/script/element-properties.js') ?>" type="text/javascript"></script>
<script src="media/script/element-category.js?<?= filemtime(MODX_MANAGER_PATH . 'media/script/element-category.js') ?>" type="text/javascript"></script>
<script src="media/script/element-lock.js?<?= filemtime(MODX_MANAGER_PATH . 'media/script/element-lock.js') ?>" type="text/javascript"></script>
<script language="JavaScript">
    Evo.ready(function() {
        EvoElementLock.init(document.forms['mutate'], {
            locked: <?= entity('locked') == 1 ? 'true' : 'false' ?>,
            saveButton: '#Button1'
        });
        setTimeout('showParameters()', 10);
        EvoElementCategory.init(document.forms['mutate']);
    });

    function duplicaterecord() {
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_duplicate_record'))) ?>)) {
            documentDirty = false;
            EvoCsrf.post("index.php?id=<?= request_intvar('id') ?>&a=105");
        }
    }

    function deletedocument() {
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_delete_plugin'))) ?>)) {
            documentDirty = false;
            EvoCsrf.post("index.php?id=" + document.mutate.id.value + "&a=104");
        }
    }

    function setTextWrap(ctrl, b) {
        if (!ctrl) return;
        ctrl.wrap = (b) ? "soft" : "off";
    }

    EvoElementProperties.configure({
        table: {style: 'width:300px;margin-top:20px;margin-bottom:20px;'},
        stacked: true,
        rowStyle: 'padding-bottom:10px;'
    });

    function showParameters(ctrl) {
        EvoElementProperties.show(ctrl);
    }

    Evo.ready(function() {

        // Try and populate config fields from the text that is pasted in the PHP box
        const phpTextarea = document.getElementById('phptextarea');
        if (!phpTextarea) return;
        phpTextarea.addEventListener('blur', function() {

            // Get the value of the php text field
            var src = phpTextarea.value;

            // Is  something in there?
            if (src == '') {
                // Untick all sys events
                document.querySelectorAll('input[name="sysevents[]"]').forEach(function(el) {
                    el.checked = false;
                });
                ['pluginName', 'pluginDescription', 'propertiesBox'].forEach(function(id) {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                const categorySelect = document.getElementById('categoryid');
                if (categorySelect) categorySelect.value = '0';
                EvoElementCategory.sync(document.forms['mutate']);
                showParameters(document.getElementById('propertiesBox'));
                return;
            }

            var find_block = /\/\*\*([\s\S]*)\*\//;
            var theBlock = find_block.exec(src);
            if (theBlock == null) {
                return;
            } else {
                src = theBlock[1];
            }

            var theParams = {};

            // Go through each line and transform into something useful
            var lines = src.split(/\n/);
            lines.forEach(function(theLine) {

                //theLine = theLine.search(/\s\*\s([A-Za-z0-9])/);
                var docblock_regexp = /\s\*\s(.+)/;
                theLine = docblock_regexp.exec(theLine);

                if (theLine != null) { // Does it have words on it?
                    if (!theParams.name) { // The first non-null line we come across should be the name
                        theParams.name = theLine[1];
                    } else if (!theParams.description) { // The second non-null line we come across should be the description
                        theParams.description = theLine[1];
                    } else {

                        var line_regexp = /@([A-Za-z_]+)\s+(.*)/;
                        var theLineDetail = line_regexp.exec(theLine[1]);

                        if (theLineDetail) {
                            switch (theLineDetail[1]) {
                                case 'internal':
                                    var theInternalLineDetail = line_regexp.exec(theLineDetail[2]);
                                    theParams[theInternalLineDetail[1]] = theInternalLineDetail[2];
                                    break;
                                default:
                                    theParams[theLineDetail[1]] = theLineDetail[2];
                                    break;
                            }
                        }
                    }
                }

            });

            // Populate the events
            if (theParams.events) {
                var events = theParams.events.split(',');

                // Untick all sys events
                document.querySelectorAll('input[name="sysevents[]"]').forEach(function(el) {
                    el.checked = false;
                });

                // The checkbox id is the event name
                events.forEach(function(i) {
                    const el = document.getElementById(i.trim());
                    if (el) el.checked = true;
                });
            }


            // Populate the name
            const nameEl = document.getElementById('pluginName');
            if (theParams.name && nameEl) nameEl.value = theParams.name;

            // Populate the description
            var version = (theParams.version) ? '<strong>' + theParams.version + '</strong> ' : '';
            const descEl = document.getElementById('pluginDescription');
            if (theParams.description && descEl) descEl.value = version + theParams.description;

            // If old param values are set, keep a record of them
            var oldParams = EvoElementProperties.params;

            // Set new default params
            if (theParams.properties) {
                const propertiesBox = document.getElementById('propertiesBox');
                propertiesBox.value = theParams.properties;
                showParameters(propertiesBox);
            }


            // Populate the properties from any old existing values
            if (oldParams) {

                // Go through each old param, and set its value if it exists
                Object.keys(oldParams).forEach(function(oldName) {
                    var oldParam = oldParams[oldName];
                    var theField = document.getElementById('prop_' + oldName);

                    if (!theField) return;

                    switch (oldParam[1]) {
                        case 'list':
                        case 'menu':
                            var oldValue = oldParam[3];
                            theField.value = oldValue;
                            EvoElementProperties.set(oldName, oldParam[1], theField);
                            break;

                        case 'list-multi':
                            // Not supporting list-multi yet, as it is broken anyway
                            break;

                        default:
                            var oldValue = oldParam[2];
                            theField.value = oldValue;
                            EvoElementProperties.set(oldName, oldParam[1], theField);
                            break;
                    }

                });
            }

            // Select the correct dropdown value
            // @modx_category が無いときは、選択にも新しいカテゴリの欄にも触れない
            var modx_category = (theParams.modx_category || '').trim();
            if (modx_category) {
                var modx_category_found = false;
                document.querySelectorAll('#categoryid option').forEach(function(opt) {
                    opt.selected = false;
                    if (opt.value !== '-1' && opt.text.trim() == modx_category) {
                        opt.selected = true;
                        modx_category_found = true;
                    }
                });
                if (modx_category_found) {
                    EvoElementCategory.sync(document.forms['mutate']);
                } else {
                    EvoElementCategory.useNew(document.forms['mutate'], modx_category);
                }
            }

        });
    });
</script>

<form name="mutate" id="mutate" method="post" action="index.php?a=103" enctype="multipart/form-data">
    <?php
    // invoke OnPluginFormPrerender event
    $tmp = ["id" => get_intvar('id')];
    $evtOut = evo()->invokeEvent("OnPluginFormPrerender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
    }
    ?>
    <input type="hidden" name="id" value="<?= (int) entity('id') ?>">
    <input type="hidden" name="mode" value="<?= request_intvar('a') ?>">

    <h1><?= $_lang['plugin_title'] ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php if (evo()->hasPermission('save_plugin')): ?>
                <li id="Button1" class="mutate">
                    <a href="#"
                        onclick="documentDirty=false;var f=document.getElementById('mutate');f.noValidate=true;f.requestSubmit();">
                        <?= $_lang['update'] ?>
                    </a>
                    <span class="and"> + </span>
                    <select id="stay" name="stay">
                        <option id="stay1"
                            value="1" <?= selected(request_intvar('stay') === 1) ?>><?= $_lang['stay_new'] ?></option>
                        <option id="stay2"
                            value="2" <?= selected(request_intvar('stay') === 2) ?>><?= $_lang['stay'] ?></option>
                        <option id="stay3"
                            value="" <?= selected(!request_intvar('stay')) ?>><?= $_lang['close'] ?></option>
                    </select>
                </li>
            <?php endif; ?>
            <?php
            if (request_intvar('a') === 102) {
                $params = [
                    'onclick' => 'duplicaterecord();',
                    'label' => $_lang['duplicate']
                ];
                if (evo()->hasPermission('new_plugin')) {
                    echo manager()->ab($params);
                }
                $params = [
                    'onclick' => 'deletedocument();',
                    'label' => $_lang['delete']
                ];
                if (evo()->hasPermission('delete_plugin')) {
                    echo manager()->ab($params);
                }
            }
            $params = [
                'onclick' => "EvoShell.go('index.php?a=76');",
                'label' => $_lang['cancel']
            ];
            echo manager()->ab($params);
            ?>
        </ul>
    </div>
    <div class="sectionBody">
        <div class="tab-pane" id="pluginPane">

            <!-- General -->
            <div class="tab-page" id="tabPlugin">
                <h2 class="tab"><?= $_lang["settings_general"] ?></h2>
                <table border="0" cellspacing="0" cellpadding="0">
                    <tr>
                        <th align="left"><?= $_lang['plugin_name'] ?></th>
                        <td align="left"><input id="pluginName" name="name" type="text" maxlength="100"
                                value="<?= hsc(entity('name')) ?>"
                                class="inputBox" style="width:300px;"></td>
                    </tr>
                    <tr>
                        <td align="left" valign="top" colspan="2"><label><input name="disabled"
                                    type="checkbox" <?= entity('disabled') == 1 ? "checked='checked'" : "" ?>
                                    value="on"
                                    class="inputBox"> <?= entity('disabled') == 1 ? "<span class='warning'>" . $_lang['plugin_disabled'] . "</span></label>" : $_lang['plugin_disabled'] ?>
                        </td>
                    </tr>
                </table>
                <!-- PHP text editor start -->
                <div class="code-editor" style="width:100%;position:relative">
                    <div class="code-editor-header">
                        <span class="code-editor-title"><?= $_lang['plugin_code'] ?></span>
                        <span class="code-editor-options"><?= $_lang['wrap_lines'] ?>
                            <input
                                name="wrap"
                                type="checkbox"
                                checked="checked"
                                class="inputBox"
                                onclick="setTextWrap(document.mutate.post,this.checked)" />
                        </span>
                    </div>
                    <textarea
                        dir="ltr" name="post" style="width:100%; height:370px;" wrap="soft"
                        class="phptextarea"
                        id="phptextarea"><?= hsc(entity('plugincode')) ?></textarea>
                </div>
                <!-- PHP text editor end -->
            </div>

            <!-- Configuration/Properties -->
            <div class="tab-page" id="tabProps">
                <h2 class="tab"><?= $_lang["settings_config"] ?></h2>
                <?php
                $field = 'sm.id,sm.name,sm.guid';
                $from = '[+prefix+]site_modules sm ' .
                    'INNER JOIN [+prefix+]site_module_depobj smd ON smd.module=sm.id AND smd.type=30 ' .
                    'INNER JOIN [+prefix+]site_plugins sp ON sp.id=smd.resource';
                $where = sprintf("smd.resource='%d' AND sm.enable_sharedparams='1'", get_intvar('id'));
                $ds = db()->select($field, $from, $where, 'sm.name');
                $guid_total = db()->count($ds);
                if ($guid_total > 0) {
                    $options = '';
                    while ($row = db()->getRow($ds)) {
                        $options .= '<option value="' . hsc($row['guid']) . '"' . selected(entity('moduleguid') == $row["guid"]) . ">" . hsc($row["name"]) . "</option>";
                    }
                }
                ?>
                <table>
                    <?php
                    if ($guid_total > 0) {
                    ?>
                        <tr>
                            <th align="left"><?= $_lang['import_params'] ?>:&nbsp;&nbsp;</th>
                            <td align="left">
                                <select name="moduleguid" style="width:300px;">
                                    <option>&nbsp;</option>
                                    <?= $options ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td align="left" valign="top"><span style="width:300px;"><span
                                        class="comment"><?= $_lang['import_params_msg'] ?></span></span><br />
                            </td>
                        </tr>
                    <?php
                    }
                    ?>
                    <tr>
                        <th align="left" valign="top"><?= $_lang['plugin_config'] ?>:</th>
                        <td align="left" valign="top">
                            <textarea class="phptextarea inputBox" name="properties" id="propertiesBox"
                                onblur='showParameters(this);'
                                onChange="showParameters(this);"><?= hsc(entity('properties')) ?></textarea><br />
                            <input
                                type="button" value="<?= $_lang['update_params'] ?>"
                                onclick="showParameters(this);" />
                        </td>
                    </tr>
                    <tr id="displayparamrow">
                        <td valign="top" align="left">&nbsp;</td>
                        <td align="left" id="displayparams">&nbsp;</td>
                    </tr>
                </table>
            </div>

            <!-- System Events -->
            <div class="tab-page" id="tabEvents">
                <h2 class="tab"><?= $_lang["settings_events"] ?></h2>
                <p><?= $_lang['plugin_event_msg'] ?></p>
                <table>
                    <?php

                    // get selected events
                    if (isset($content['sysevents'])) {
                        // 差し戻された入力があれば、DB よりそちらを優先する
                        $evts = array_map('intval', (array)$content['sysevents']);
                    } elseif (get_intvar('id')) {
                        $evts = [];
                        $rs = db()->select('*', '[+prefix+]site_plugin_events', sprintf("pluginid='%d'", get_intvar('id')));
                        while ($row = db()->getRow($rs)) {
                            $evts[] = $row['evtid'];
                        }
                    } else {
                        $evts = [];
                    }

                    // display system events
                    $evtnames = [];
                    $services = [
                        "Parser Service Events",
                        "Manager Access Events",
                        "Web Access Service Events",
                        "Cache Service Events",
                        "Template Service Events",
                        "User Defined Events"
                    ];
                    $rs = db()->select('*', '[+prefix+]system_eventnames', '', 'service DESC, groupname, name');
                    if (db()->count($rs) == 0) {
                        echo '<tr><td>&nbsp;</td></tr>';
                    } else {
                        $g = 0;
                        $srvID = '';
                        $grpName = '';
                        while ($row = db()->getRow($rs)) {
                            // display records
                            if ($srvID != $row['service']) {
                                $g++;
                                $srvID = $row['service'];
                                if (count($evtnames) > 0) {
                                    echoEventRows($evtnames);
                                }
                                echo '<tr><td colspan="2"><div class="split" style="margin:10px 0;"></div></td></tr>';
                                echo '<tr><td colspan="2"><b>' . "[{$g}] " . $services[$srvID - 1] . '</b></td></tr>';
                            }
                            // display group name
                            if ($grpName != $row['groupname']) {
                                $g++;
                                if (count($evtnames) > 0) {
                                    echoEventRows($evtnames);
                                }
                                echo '<tr><td colspan="2"><div class="split" style="margin:10px 0;"></div></td></tr>';
                                echo '<tr><td colspan="2"><b>' . "[{$g}] " . hsc($row['groupname']) . '</b></td></tr>';
                                $grpName = $row['groupname'];
                            }
                            $evtid = $row['id'];
                            $evtnames[] = '<input name="sysevents[]" type="checkbox"' . checked(in_array(
                                $row['id'],
                                $evts
                            )) . ' class="inputBox" value="' . $row['id'] . '" id="' . hsc($row['name']) . '"/><label for="' . hsc($row['name']) . '"' . bold(in_array(
                                $row['id'],
                                $evts
                            )) . '>' . "[{$evtid}] " . hsc($row['name']) . '</label>' . "\n";
                            if (count($evtnames) == 2) {
                                echoEventRows($evtnames);
                            }
                        }
                    }
                    if (count($evtnames) > 0) {
                        echoEventRows($evtnames);
                    }

                    function echoEventRows(&$evtnames)
                    {
                        echo "<tr><td>" . implode("</td><td>", $evtnames) . "</td></tr>";
                        $evtnames = [];
                    }

                    ?>
                </table>
            </div>
            <div class="tab-page" id="tabInfo">
                <h2 class="tab"><?= $_lang['settings_properties'] ?></h2>
                <table>
                    <tr>
                        <th align="left"><?= $_lang['existing_category'] ?>:&nbsp;&nbsp;</th>
                        <td align="left">
                            <select name="categoryid" id="categoryid" style="width:300px;">
                                <?= manager()->elementCategoryOptions(entity('category')) ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="newcategry" style="display:none;">
                        <th align="left" valign="top" style="padding-top:5px;"><?= $_lang['new_category'] ?>
                            :
                        </th>
                        <td align="left" valign="top" style="padding-top:5px;">
                            <input name="newcategory"
                                id="newcategory" type="text"
                                maxlength="45" value="<?= hsc(entity('newcategory', '')) ?>"
                                class="inputBox"
                                style="width:300px;">
                        </td>
                    </tr>
                    <tr>
                        <th align="left"><?= $_lang['plugin_desc'] ?>:&nbsp;&nbsp;</th>
                        <td align="left">
                            <textarea id="pluginDescription" name="description"
                                style="padding:0;height:4em;"><?= hsc(entity('description')) ?></textarea>
                        </td>
                    </tr>
                    <?php $pluginErrorReporting = entity('error_reporting', 'inherit'); ?>
                    <tr>
                        <th align="left" style="padding-top:10px;">
                            <?= $_lang['a17_error_reporting_title'] ?>:
                        </th>
                        <td align="left" style="padding-top:10px;">
                            <label>
                                <input type="radio" name="error_reporting" value="inherit"<?= $pluginErrorReporting === 'inherit' ? ' checked="checked"' : '' ?>>
                                <?= $_lang['element_error_reporting_inherit'] ?>
                            </label><br/>
                            <label>
                                <input type="radio" name="error_reporting" value="0"<?= $pluginErrorReporting === '0' ? ' checked="checked"' : '' ?>>
                                <?= $_lang['a17_error_reporting_opt0'] ?>
                            </label><br/>
                            <label>
                                <input type="radio" name="error_reporting" value="1"<?= $pluginErrorReporting === '1' ? ' checked="checked"' : '' ?>>
                                <?= $_lang['a17_error_reporting_opt1'] ?>
                            </label><br/>
                            <label>
                                <input type="radio" name="error_reporting" value="2"<?= $pluginErrorReporting === '2' ? ' checked="checked"' : '' ?>>
                                <?= $_lang['a17_error_reporting_opt2'] ?>
                            </label><br/>
                            <label>
                                <input type="radio" name="error_reporting" value="99"<?= $pluginErrorReporting === '99' ? ' checked="checked"' : '' ?>>
                                <?= $_lang['a17_error_reporting_opt99'] ?>
                            </label><br/>
                            <?= $_lang['a17_error_reporting_msg'] ?>
                        </td>
                    </tr>
                    <?php if (evo()->hasPermission('save_plugin') == 1) { ?>
                        <tr>
                            <td align="left" valign="top" colspan="2">
                                <label><input name="locked"
                                        type="checkbox" <?= entity('locked') == 1 ? "checked='checked'" : "" ?>
                                        value="on" class="inputBox">
                                    <b><?= $_lang['lock_plugin'] ?></b> <span
                                        class="comment"><?= $_lang['lock_plugin_msg'] ?></span></label>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
            <div class="tab-page" id="tabHelp">
                <h2 class="tab">ヘルプ</h2>
                <?= $_lang['plugin_msg'] ?>
            </div>
        </div>
    </div>
    <script>
        var tpstatus = <?= (($modx->config['remember_last_tab'] == 2) || (get_intvar('stay') === 2)) ? 'true' : 'false'; ?>;
        tp = new WebFXTabPane(document.getElementById("pluginPane"), tpstatus);
    </script>
    <?php
    // invoke OnPluginFormRender event
    $tmp = ["id" => get_intvar('id')];
    $evtOut = evo()->invokeEvent("OnPluginFormRender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
    }
    ?>
</form>
<?php

function bold($cond = false)
{
    if ($cond !== false) {
        return ' style="background-color:#777;color:#fff;"';
    } else {
        return;
    }
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
