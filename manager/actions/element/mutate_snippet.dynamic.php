<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

switch ((int)anyv('a')) {
    case 22:
        if (!evo()->hasPermission('edit_snippet')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        break;
    case 23:
        if (!evo()->hasPermission('new_snippet')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        break;
    default:
        alert()->setError(3);
        alert()->dumpError();
}

abort_if_invalid_id('id', anyv('a') == 22);

$id = (int)anyv('id', 0);

// check to see the snippet editor isn't locked
$rs = db()->select('internalKey, username', '[+prefix+]active_users', "action=22 AND id='{$id}'");
$limit = db()->count($rs);
if ($limit > 1) {
    for ($i = 0; $i < $limit; $i++) {
        $lock = db()->getRow($rs);
        if ($lock['internalKey'] != evo()->getLoginUserID()) {
            $msg = sprintf($_lang['lock_msg'], $lock['username'], $_lang['snippet']);
            alert()->setError(5, $msg);
            alert()->dumpError();
        }
    }
}
// end check for lock

$content = [];
if (getv('id') && preg_match('@^[0-9]+$@', getv('id'))) {
    $rs = db()->select('*', '[+prefix+]site_snippets', "id='{$id}'");
    $limit = db()->count($rs);
    if ($limit > 1) {
        echo "Oops, Multiple snippets sharing same unique id. Not good.<p>";
        exit;
    }
    if ($limit < 1) {
        header("Location: /index.php?id=" . $site_start);
    }
    $content = db()->getRow($rs);
    $_SESSION['itemname'] = $content['name'];
} else {
    $_SESSION['itemname'] = "New snippet";
}
// restore saved form
$content = manager()->restoreElementFormValues($content, 'snippet');

function entity($key, $default = null)
{
    global $content;
    return $content[$key] ?? $default;
}

?>
<script src="media/script/element-properties.js?<?= filemtime(MODX_MANAGER_PATH . 'media/script/element-properties.js') ?>" type="text/javascript"></script>
<script src="media/script/element-category.js?<?= filemtime(MODX_MANAGER_PATH . 'media/script/element-category.js') ?>" type="text/javascript"></script>
<script src="media/script/element-lock.js?<?= filemtime(MODX_MANAGER_PATH . 'media/script/element-lock.js') ?>" type="text/javascript"></script>
<script type="text/javascript">
    function duplicaterecord() {
        if (confirm("<?= $_lang['confirm_duplicate_record'] ?>")) {
            documentDirty = false;
            EvoCsrf.post("index.php?id=<?= request_intvar('id') ?>&a=98");
        }
    }

    function deletedocument() {
        if (confirm("<?= $_lang['confirm_delete_snippet'] ?>")) {
            documentDirty = false;
            EvoCsrf.post("index.php?id=" + document.mutate.id.value + "&a=25");
        }
    }

    function setTextWrap(ctrl, b) {
        if (!ctrl) return;
        ctrl.wrap = (b) ? "soft" : "off";
    }

    EvoElementProperties.configure({
        table: {width: '300', style: 'margin-bottom:3px;'},
        stacked: true
    });

    function showParameters(ctrl) {
        EvoElementProperties.show(ctrl);
    }
</script>

<form name="mutate" id="mutate" method="post" action="index.php?a=24" enctype="multipart/form-data">
    <?php
    // invoke OnSnipFormPrerender event
    $tmp = ["id" => $id];
    $evtOut = evo()->invokeEvent("OnSnipFormPrerender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
    }
    ?>
    <input type="hidden" name="id" value="<?= (int) entity('id') ?>">
    <input type="hidden" name="mode" value="<?= hsc(getv('a')) ?>">

    <div id="actions">
        <ul class="actionButtons">
            <?php if (evo()->hasPermission('save_snippet')): ?>
                <li id="Button1" class="mutate">
                    <a href="#"
                        onclick="documentDirty=false;var f=document.getElementById('mutate');f.noValidate=true;f.requestSubmit();document.getElementById('Button1').style.display='none';document.querySelectorAll('input,textarea,select').forEach(function(el){el.classList.add('readonly');});">
                        <?= $_lang['update'] ?>
                    </a>
                    <span class="and"> + </span>
                    <select id="stay" name="stay">
                        <option id="stay1"
                            value="1" <?= anyv('stay') == 1 ? ' selected=""' : '' ?>><?= $_lang['stay_new'] ?></option>
                        <option id="stay2"
                            value="2" <?= anyv('stay') == 2 ? ' selected="selected"' : '' ?>><?= $_lang['stay'] ?></option>
                        <option id="stay3"
                            value="" <?= anyv('stay') == '' ? ' selected=""' : '' ?>><?= $_lang['close'] ?></option>
                    </select>
                </li>
            <?php endif; ?>
            <?php
            if (getv('a') == 22) {
                if (evo()->hasPermission('new_snippet')) {
                    echo manager()->ab([
                        'onclick' => 'duplicaterecord();',
                        'label' => $_lang['duplicate']
                    ]);
                }
                if (evo()->hasPermission('delete_snippet')) {
                    echo manager()->ab([
                        'onclick' => 'deletedocument();',
                        'label' => $_lang['delete']
                    ]);
                }
            }
            echo manager()->ab([
                'onclick' => "document.location.href='index.php?a=76';",
                'label' => $_lang['cancel']
            ]);
            ?>
        </ul>
    </div>

    <h1><?= $_lang['snippet_title'] ?></h1>

    <div class="sectionBody">
        <div class="tab-pane" id="snipetPane">
            <!-- General -->
            <div class="tab-page" id="tabSnippet">
                <h2 class="tab"><?= $_lang['settings_general'] ?></h2>
                <table>
                    <tr>
                        <th align="left"><?= $_lang['snippet_name'] ?></th>
                        <td align="left">[[<input name="name" type="text" maxlength="100"
                                value="<?= hsc(entity('name')) ?>"
                                class="inputBox" style="width:300px;">]]
                        </td>
                    </tr>
                </table>
                <!-- PHP text editor start -->
                <div class="code-editor">
                    <div class="code-editor-header">
                        <span class="code-editor-title"><?= $_lang['snippet_code'] ?></span>
                        <span class="code-editor-options"><?= $_lang['wrap_lines'] ?>
                            <input name="wrap" type="checkbox" checked="checked" class="inputBox"
                                onclick="setTextWrap(document.mutate.post,this.checked)" /></span>
                    </div>
                    <?php
                    if (isset($content['snippet'])) {
                        $code = trim(hsc(entity('snippet')));
                    } elseif (isset($content['post'])) {
                        $code = trim(hsc(entity('post')));
                    } else {
                        $code = '';
                    }
                    ?>
                    <textarea class="phptextarea" dir="ltr" name="post" style="width:100%; height:370px;"
                        wrap="soft"><?= $code ?></textarea>
                </div>
                <!-- PHP text editor end -->
            </div>

            <!-- Properties -->
            <div class="tab-page" id="tabProps">
                <h2 class="tab"><?= $_lang['settings_properties'] ?></h2>
                <table>
                    <tr>
                        <th align="left"><?= $_lang['existing_category'] ?>:</th>
                        <td align="left">
                            <select name="categoryid" style="width:300px;">
                                <?= manager()->elementCategoryOptions(entity('category')) ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="newcategry" style="display:none;">
                        <th align="left" valign="top" style="padding-top:10px;">
                            <?= $_lang['new_category'] ?>:
                        </th>
                        <td align="left" valign="top" style="padding-top:10px;">
                            <input name="newcategory" type="text"
                                maxlength="45" value="<?= hsc(entity('newcategory', '')) ?>"
                                class="inputBox"
                                style="width:300px;">
                        </td>
                    </tr>
                    <tr>
                        <th align="left" style="padding-top:10px">
                            <?= $_lang['snippet_desc'] ?>:
                        </th>
                        <td align="left" style="padding-top:10px">
                            <textarea name="description"
                                style="padding:0;height:4em;"><?= hsc(entity('description')) ?></textarea>
                        </td>
                    </tr>
                    <?php $snippetErrorReporting = entity('error_reporting', 'inherit'); ?>
                    <tr>
                        <th align="left" style="padding-top:10px;">
                            <?= $_lang['a17_error_reporting_title'] ?>:
                        </th>
                        <td align="left" style="padding-top:10px;">
                            <label>
                                <input type="radio" name="error_reporting" value="inherit"<?= $snippetErrorReporting === 'inherit' ? ' checked="checked"' : '' ?>>
                                <?= $_lang['element_error_reporting_inherit'] ?>
                            </label><br/>
                            <label>
                                <input type="radio" name="error_reporting" value="0"<?= $snippetErrorReporting === '0' ? ' checked="checked"' : '' ?>>
                                <?= $_lang['a17_error_reporting_opt0'] ?>
                            </label><br/>
                            <label>
                                <input type="radio" name="error_reporting" value="1"<?= $snippetErrorReporting === '1' ? ' checked="checked"' : '' ?>>
                                <?= $_lang['a17_error_reporting_opt1'] ?>
                            </label><br/>
                            <label>
                                <input type="radio" name="error_reporting" value="2"<?= $snippetErrorReporting === '2' ? ' checked="checked"' : '' ?>>
                                <?= $_lang['a17_error_reporting_opt2'] ?>
                            </label><br/>
                            <label>
                                <input type="radio" name="error_reporting" value="99"<?= $snippetErrorReporting === '99' ? ' checked="checked"' : '' ?>>
                                <?= $_lang['a17_error_reporting_opt99'] ?>
                            </label><br/>
                            <?= $_lang['a17_error_reporting_msg'] ?>
                        </td>
                    </tr>
                    <?php if (evo()->hasPermission('save_snippet') == 1) { ?>
                        <tr>
                            <td style="padding-top:10px" align="left" valign="top" colspan="2">
                                <label>
                                    <input style="padding:0;margin:0;" name="locked"
                                        type="checkbox" <?= entity('locked') == 1 ? "checked='checked'" : '' ?>
                                        class="inputBox">
                                    <b><?= $_lang['lock_snippet'] ?></b>
                                    <span class="comment"><?= $_lang['lock_snippet_msg'] ?></span>
                                </label>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php
                    $from = "[+prefix+]site_modules AS sm " .
                        "INNER JOIN [+prefix+]site_module_depobj AS smd ON smd.module=sm.id AND smd.type=40 " .
                        "INNER JOIN [+prefix+]site_snippets AS ss ON ss.id=smd.resource ";
                    $ds = db()->select(
                        'sm.id,sm.name,sm.guid',
                        $from,
                        "smd.resource='{$id}' AND sm.enable_sharedparams='1'",
                        'sm.name'
                    );
                    $guid_total = db()->count($ds);
                    if ($guid_total > 0) {
                        $options = '';
                        while ($row = db()->getRow($ds)) {
                            $options .= '<option value="' . hsc($row['guid']) . '"' . (entity('moduleguid') == $row['guid'] ? " selected='selected'" : "") . ">" . hsc($row['name']) . "</option>";
                        }
                    }
                    ?>
                    <?php if ($guid_total > 0) {
                    ?>
                        <tr>
                            <th align="left" style="padding-top:10px;"><?= $_lang['import_params'] ?>:</th>
                            <td align="left" valign="top" style="padding-top:10px;">
                                <select name="moduleguid" style="width:300px;">
                                    <?= $options ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                            <td align="left" valign="top"><span
                                    class="comment"><?= $_lang['import_params_msg'] ?></td>
                        </tr>
                    <?php } ?>
                    <tr>
                        <th align="left" valign="top"><?= $_lang['snippet_properties'] ?>:</th>
                        <td align="left" valign="top">
                            <textarea name="properties" maxlength="65535"
                                class="inputBox phptextarea"
                                onChange="showParameters(this);"><?= hsc(entity('properties')) ?></textarea>
                        </td>
                    </tr>
                    <tr id="displayparamrow">
                        <td valign="top" align="left">&nbsp;</td>
                        <td align="left" id="displayparams">&nbsp;</td>
                    </tr>
                </table>
            </div>
            <div class="tab-page" id="tabHelp">
                <h2 class="tab">ヘルプ</h2>
                <?= $_lang['snippet_msg'] ?>
            </div>
        </div>
    </div>
    <?php
    // invoke OnSnipFormRender event
    $tmp = ["id" => $id];
    $evtOut = evo()->invokeEvent("OnSnipFormRender", $tmp);
    if (is_array($evtOut)) {
        echo implode("", $evtOut);
    }
    ?>
</form>

<script type="text/javascript">
    setTimeout('showParameters();', 10);
    var tpstatus = <?= (($modx->config['remember_last_tab'] == 2) || (getv('stay') == 2)) ? 'true' : 'false' ?>;
    tpSnippet = new WebFXTabPane(document.getElementById("snipetPane"), tpstatus);
    // EvoShell は再表示時にこの script を同じ window で再実行するため、変数は関数の中に置く
    (function() {
        EvoElementLock.init(document.forms['mutate'], {
            locked: <?= entity('locked') == 1 ? 'true' : 'false' ?>,
            saveButton: '#Button1'
        });
        EvoElementCategory.init(document.forms['mutate']);
    })();
</script>