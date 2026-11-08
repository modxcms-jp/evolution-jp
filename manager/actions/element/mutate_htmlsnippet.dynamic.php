<?php

/**
 * @var array $_lang
 * @var array $_style
 */

if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

switch (request_intvar('a')) {
    case 78:
        if (!evo()->hasPermission('edit_chunk')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        break;
    case 77:
        if (!evo()->hasPermission('new_chunk')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        break;
    default:
        alert()->setError(3);
        alert()->dumpError();
}

abort_if_invalid_id('id', request_intvar('a') === 78);

$id = request_intvar('id');

// Get table names (alphabetical)

// Check to see the snippet editor isn't locked
$rs = db()->select('internalKey, username', '[+prefix+]active_users', "action=78 AND id='{$id}'");
if (db()->count($rs) > 1) {
    while ($row = db()->getRow($rs)) {
        if ($row['internalKey'] != evo()->getLoginUserID()) {
            $msg = sprintf($_lang['lock_msg'], $row['username'], $_lang['chunk']);
            alert()->setError(5, $msg);
            alert()->dumpError();
        }
    }
}

$content = [];
if ($id) {
    $rs = db()->select('*', '[+prefix+]site_htmlsnippets', "id='{$id}'");
    $total = db()->count($rs);
    if ($total > 1) {
        exit('<p>Error: Multiple Chunk sharing same unique ID.</p>');
    }
    if (!$total) {
        exit("<p>Chunk doesn't exist.</p>");
    }
    $content = db()->getRow($rs);
    $_SESSION['itemname'] = $content['name'];
} else {
    $_SESSION['itemname'] = 'New Chunk';
}

// restore saved form
$formRestored = manager()->hasFormValues();
$content = manager()->restoreElementFormValues($content, 'snippet', ['published', 'editor_type', 'locked']);
if (!$formRestored && postv('changeMode')) {
    $content = array_merge($content, array_filter(postv(), 'is_string'));
}

function entity($key, $default = null)
{
    global $content;

    if (!isset($content['published'])) {
        $content['published'] = 1;
    }
    if ($key === 'pub_date') {
        return manager()->dateInputValue($content['pub_date'] ?? '');
    }

    if ($key === 'unpub_date') {
        return manager()->dateInputValue($content['unpub_date'] ?? '');
    }

    return $content[$key] ?? $default;
}

$which_editor = config('which_editor');
if (isset($content['which_editor'])) {
    $which_editor = $content['which_editor'];
} elseif (!entity('editor_type')) {
    $which_editor = 'none';
}

// Print RTE Javascript function
?>
<script src="media/script/element-category.js?<?= filemtime(MODX_MANAGER_PATH . 'media/script/element-category.js') ?>" type="text/javascript"></script>
<script src="media/script/element-lock.js?<?= filemtime(MODX_MANAGER_PATH . 'media/script/element-lock.js') ?>" type="text/javascript"></script>
<script>
    // Added for RTE selection
    function changeRTE() {
        var whichEditor = document.getElementById('which_editor');
        if (whichEditor) {
            for (var i = 0; i < whichEditor.length; i++) {
                if (whichEditor[i].selected) {
                    newEditor = whichEditor[i].value;
                    break;
                }
            }
        } else newEditor = '';

        documentDirty = false;
        document.mutate.a.value = <?= manager()->action ?>;
        document.mutate.which_editor.value = newEditor;
        document.mutate.changeMode.value = newEditor;
        document.mutate.submit();
    }

    function duplicaterecord() {
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_duplicate_record'))) ?>)) {
            documentDirty = false;
            EvoCsrf.post("index.php?id=<?= request_intvar('id') ?>&a=97");
        }
    }

    function deletedocument() {
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_delete_htmlsnippet'))) ?>)) {
            documentDirty = false;
            EvoCsrf.post("index.php?id=" + document.mutate.id.value + "&a=80");
        }
    }

    function resetpubdate() {
        if (document.mutate.pub_date.value != '' || document.mutate.unpub_date.value != '') {
            if (confirm(<?= js_literal(stripcslashes(lang('confirm_reset_pub_dates'))) ?>)) {
                document.mutate.pub_date.value = '';
                document.mutate.unpub_date.value = '';
            }
        }
        documentDirty = true;
    }
</script>

<form class="htmlsnippet" id="mutate" name="mutate" method="post" action="index.php" enctype="multipart/form-data">
    <?php

    // invoke OnChunkFormPrerender event
    $tmp = ['id' => $id];
    $evtOut = evo()->invokeEvent('OnChunkFormPrerender', $tmp);
    if (is_array($evtOut)) {
        echo implode('', $evtOut);
    }

    ?>
    <input type="hidden" name="a" value="79" />
    <input type="hidden" name="id" value="<?= request_intvar('id') ?>" />
    <input type="hidden" name="mode" value="<?= request_intvar('a') ?>" />
    <input type="hidden" name="changeMode" value="" />

    <h1><?= $_lang['htmlsnippet_title'] ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php if (evo()->hasPermission('save_chunk')): ?>
                <li id="save" class="primary mutate">
                    <a href="#">
                        <?= $_lang['update'] ?>
                    </a>
                    <span class="and"> + </span>
                    <select id="stay" name="stay">
                        <?php if (evo()->hasPermission('new_chunk')) { ?>
                            <option id="stay1"
                                value="1" <?= request_intvar('stay') === 1 ? ' selected=""' : '' ?>><?= $_lang['stay_new'] ?></option>
                        <?php } ?>
                        <option id="stay2"
                            value="2" <?= request_intvar('stay') === 2 ? ' selected="selected"' : '' ?>><?= $_lang['stay'] ?></option>
                        <option id="stay3"
                            value="" <?= !request_intvar('stay') ? ' selected=""' : '' ?>><?= $_lang['close'] ?></option>
                    </select>
                </li>
            <?php endif; ?>
            <?php
            if (request_intvar('a') === 78) {
                if (evo()->hasPermission('new_chunk')) {
                    echo manager()->ab(
                        [
                            'onclick' => 'duplicaterecord();',
                            'label' => $_lang['duplicate']
                        ]
                    );
                }
                if (evo()->hasPermission('delete_chunk')) {
                    echo manager()->ab(
                        [
                            'onclick' => 'deletedocument();',
                            'label' => $_lang['delete']
                        ]
                    );
                }
            }
            echo manager()->ab(
                [
                    'onclick' => "EvoShell.go('index.php?a=76');",
                    'label' => $_lang['cancel']
                ]
            );
            ?>
        </ul>
    </div>

    <div class="sectionBody">
        <div class="tab-pane" id="chunkPane">
            <div class="tab-page" id="tabGeneral">
                <h2 class="tab"><?= $_lang['settings_general'] ?></h2>
                <table>
                    <tr>
                        <th align="left"><?= $_lang['htmlsnippet_name'] ?></th>
                        <td align="left">
                            {{<input name="name" type="text" maxlength="100"
                                    value="<?= hsc(entity('name')) ?>"
                                    class="inputBox" style="width:300px;">}}
                        </td>
                    </tr>
                </table>

                <div class="code-editor">
                    <div class="code-editor-header">
                        <span class="code-editor-title"><?= $_lang['chunk_code'] ?></span>
                    </div>
                    <textarea
                        dir="ltr" class="phptextarea" name="post"
                        style="height:350px;width:100%"><?= hsc(entity('post') ?? entity('snippet')) ?></textarea>
                </div>

                <span class="warning"><?= $_lang['which_editor_title'] ?></span>
                <select id="which_editor" name="which_editor"
                    onchange="gotosave=true;documentDirty=false;changeRTE();">
                    <option value="none" <?= $which_editor === 'none' ? ' selected="selected"' : '' ?>>
                        <?= $_lang['none'] ?>
                    </option>
                    <?php
                    // invoke OnRichTextEditorRegister event
                    $evtOut = evo()->invokeEvent('OnRichTextEditorRegister');
                    if (is_array($evtOut)) {
                        foreach ($evtOut as $i => $editor) {
                            echo "\t" . '<option value="' . $editor . '"' . ($which_editor == $editor ? ' selected="selected"' : '') . '>' . $editor . "</option>\n";
                        }
                    }
                    ?>
                </select>
                <?php

                // invoke OnChunkFormRender event
                $tmp = ['id' => $id];
                $evtOut = evo()->invokeEvent('OnChunkFormRender', $tmp);
                if (is_array($evtOut)) {
                    echo implode('', $evtOut);
                }
                ?>

            </div>

            <div class="tab-page" id="tabInfo">
                <h2 class="tab"><?= $_lang['settings_properties'] ?></h2>
                <table>
                    <tr>
                        <th align="left"><?= $_lang['chunk_opt_published'] ?></th>
                        <td><input
                                name="published" onclick="resetpubdate();"
                                type="checkbox"
                                <?= (entity('published') == 1) ? ' checked="checked"' : '' ?>
                                class="inputBox" value="1" /></td>
                    </tr>
                    <tr>
                        <th align="left"><?= $_lang['page_data_publishdate'] ?></th>
                        <td>
                            <input id="pub_date" name="pub_date" type="text"
                                value="<?= entity('pub_date') ?>" class="DatePicker" />
                            <a onclick="document.mutate.pub_date.value=''; documentDirty=true; return true;"
                                style="cursor:pointer; cursor:hand;">
                                <img src="<?= $_style["icons_cal_nodate"] ?>"
                                    alt="<?= $_lang['remove_date'] ?>" /></a>
                        </td>
                    </tr>
                    <tr>
                        <th align="left"><?= $_lang['page_data_unpublishdate'] ?></th>
                        <td>
                            <input id="unpub_date" name="unpub_date" type="text"
                                value="<?= entity('unpub_date') ?>" class="DatePicker" />
                            <a onclick="document.mutate.unpub_date.value=''; documentDirty=true; return true;"
                                style="cursor:pointer; cursor:hand">
                                <img src="<?= $_style["icons_cal_nodate"] ?>"
                                    alt="<?= $_lang['remove_date'] ?>" /></a>
                        </td>
                    </tr>
                    <tr>
                        <th align="left"><?= $_lang['existing_category'] ?></th>
                        <td align="left"><span style="font-family:'Courier New', Courier, mono"></span>
                            <select name="categoryid" style="width:300px;">
                                <?= manager()->elementCategoryOptions(entity('categoryid', entity('category'))) ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="newcategry" style="display:none;">
                        <th align="left" valign="middle"><?= $_lang['new_category'] ?></th>
                        <td align="left" valign="top"><input
                                name="newcategory"
                                type="text" maxlength="45"
                                value="<?= hsc(entity('newcategory')) ?>"
                                class="inputBox" style="width:300px;">
                        </td>
                    </tr>
                    <tr>
                        <th align="left"><?= $_lang['htmlsnippet_desc'] ?></th>
                        <td align="left">
                            <textarea
                                name="description"
                                style="padding:0;height:4em;width:300px;"><?= hsc(entity('description')) ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th align="left" valign="middle"><?= $_lang['resource_opt_richtext'] ?></th>
                        <td align="left" valign="top"><input
                                name="editor_type"
                                type="checkbox"
                                <?= entity('editor_type') == 1 ? ' checked="checked"' : '' ?>
                                class="inputBox" value="1" /></td>
                    </tr>
                    <?php if (evo()->hasPermission('save_chunk') == 1) { ?>
                        <tr>
                            <td align="left" colspan="2">
                                <label><input
                                        name="locked"
                                        type="checkbox"
                                        <?= entity('locked') == 1 || entity('locked') === 'on' ? ' checked="checked"' : '' ?>
                                        class="inputBox" value="on" /> <?= $_lang['lock_htmlsnippet'] ?>
                                    <span class="comment"><?= $_lang['lock_htmlsnippet_msg'] ?></span></label>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
            <div class="tab-page" id="tabHelp">
                <h2 class="tab">ヘルプ</h2>
                <?= $_lang['htmlsnippet_msg'] ?>
            </div>
        </div>
        <script>
            var stay = <?=
                        ((evo()->config('remember_last_tab') == 2) || (get_intvar('stay') === 2)) ? 'true' : 'false'
                        ?>;
            chunkPane = new WebFXTabPane(document.getElementById('chunkPane'), stay);
        </script>
</form>
</div>
<script>
    // EvoShell は再表示時にこの script を同じ window で再実行するため、変数は関数の中に置く
    (function() {
        EvoElementLock.init(document.forms['mutate'], {
            locked: <?= entity('locked') == 1 ? 'true' : 'false' ?>,
            saveButton: '#save'
        });
        EvoElementCategory.init(document.forms['mutate']);
    })();
    // EvoShellは画面の再表示時にこのscriptを同じwindowで再実行するため、
    // 最上位でconst/letを宣言すると再宣言エラーで保存ハンドラが登録されなくなる
    (function() {
        const saveButton = document.querySelector('#save a');
        if (!saveButton) {
            return;
        }
        saveButton.addEventListener('click', function(event) {
            event.preventDefault();
            documentDirty = false;
            document.getElementById('mutate').requestSubmit();
        });
    })();
</script>
<?php
// invoke OnRichTextEditorInit event
if (config('use_editor') == 1) {
    $tmp = [
        'editor' => $which_editor,
        'elements' => ['post']
    ];
    $evtOut = evo()->invokeEvent('OnRichTextEditorInit', $tmp);
    if (is_array($evtOut)) {
        echo implode('', $evtOut);
    }
}
