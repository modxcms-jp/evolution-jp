<?php

/**
 * @var array $_lang
 */

if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

$content = manager()->openElementForm('chunk', lang('chunk'));
$id = (int) ($content['id'] ?? 0);
sessionv('*itemname', $content ? $content['name'] : 'New Chunk');

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
<script src="<?= manager_asset('media/script/element-category.js') ?>" type="text/javascript"></script>
<script src="<?= manager_asset('media/script/element-lock.js') ?>" type="text/javascript"></script>
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
            EvoCsrf.post("index.php?id=<?= $id ?>&a=97");
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
    <input type="hidden" name="id" value="<?= $id ?>" />
    <input type="hidden" name="mode" value="<?= hsc(EvoFormFlow::mode('chunk')) ?>" />
    <input type="hidden" name="changeMode" value="" />

    <h1><?= lang('htmlsnippet_title') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php if (evo()->hasPermission('save_chunk')): ?>
                <li id="save" class="primary mutate">
                    <a href="#">
                        <?= lang('update') ?>
                    </a>
                    <span class="and"> + </span>
                    <select id="stay" name="after_save">
                        <?= EvoFormFlow::afterSaveOptions(EvoFormFlow::requestedAfterSave(), evo()->hasPermission('new_chunk')) ?>
                    </select>
                </li>
            <?php endif; ?>
            <?php
            if (request_intvar('a') === 78) {
                if (evo()->hasPermission('new_chunk')) {
                    echo manager()->ab(
                        [
                            'onclick' => 'duplicaterecord();',
                            'label' => lang('duplicate')
                        ]
                    );
                }
                if (evo()->hasPermission('delete_chunk')) {
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
                    'onclick' => "EvoShell.go('index.php?a=76');",
                    'label' => lang('cancel')
                ]
            );
            ?>
        </ul>
    </div>

    <div class="sectionBody">
        <div class="tab-pane" id="chunkPane">
            <div class="tab-page" id="tabGeneral">
                <h2 class="tab"><?= lang('settings_general') ?></h2>
                <table>
                    <?= EvoFieldRow::render([
                        'label' => lang('htmlsnippet_name'),
                        'body' => '{{<input name="name" type="text" maxlength="100" value="' . hsc(entity('name')) . '" class="inputBox" style="width:300px;">}}',
                    ]) ?>
                </table>

                <div class="code-editor">
                    <div class="code-editor-header">
                        <span class="code-editor-title"><?= lang('chunk_code') ?></span>
                    </div>
                    <textarea
                        dir="ltr" class="phptextarea" name="post" id="post"
                        style="height:350px;width:100%"><?= hsc(entity('post') ?? entity('snippet')) ?></textarea>
                </div>

                <span class="warning"><?= lang('which_editor_title') ?></span>
                <select id="which_editor" name="which_editor"
                    onchange="gotosave=true;documentDirty=false;changeRTE();">
                    <option value="none" <?= $which_editor === 'none' ? ' selected="selected"' : '' ?>>
                        <?= lang('none') ?>
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
                <h2 class="tab"><?= lang('settings_properties') ?></h2>
                <table>
                    <?php
                    ob_start();
                    ?>
                    <input
                        name="published" onclick="resetpubdate();"
                        type="checkbox"
                        <?= (entity('published') == 1) ? ' checked="checked"' : '' ?>
                        class="inputBox" value="1" />
                    <?php
                    echo EvoFieldRow::render([
                        'label' => lang('chunk_opt_published'),
                        'body' => ob_get_clean(),
                    ]);

                    // 公開日・公開終了日と、日付を消すボタン
                    foreach (['pub_date' => 'page_data_publishdate', 'unpub_date' => 'page_data_unpublishdate'] as $name => $labelKey) {
                        ob_start();
                        ?>
                        <input id="<?= $name ?>" name="<?= $name ?>" type="text"
                            value="<?= entity($name) ?>" class="DatePicker" />
                        <a class="clickable-label"
                            onclick="document.mutate.<?= $name ?>.value=''; documentDirty=true; return true;">
                            <img src="<?= style("icons_cal_nodate") ?>"
                                alt="<?= lang('remove_date') ?>" /></a>
                        <?php
                        echo EvoFieldRow::render([
                            'label' => lang($labelKey),
                            'body' => ob_get_clean(),
                        ]);
                    }

                    if (evo()->hasPermission('save_chunk') == 1) {
                        ob_start();
                        ?>
                        <input
                                name="locked"
                                type="checkbox"
                                <?= entity('locked') == 1 || entity('locked') === 'on' ? ' checked="checked"' : '' ?>
                                class="inputBox" value="on" />
                        <?php
                        echo EvoFieldRow::render([
                            'label' => lang('lock_htmlsnippet'),
                            'body' => ob_get_clean(),
                            'help' => lang('lock_htmlsnippet_msg'),
                        ]);
                    }

                    ob_start();
                    ?>
                    <select name="categoryid" style="width:300px;">
                        <?= manager()->elementCategoryOptions(entity('categoryid', entity('category'))) ?>
                    </select>
                    <?php
                    echo EvoFieldRow::render([
                        'label' => lang('existing_category'),
                        'body' => ob_get_clean(),
                    ]);

                    // 新しいカテゴリの行は、element-category.js が表示を切り替える
                    ob_start();
                    ?>
                    <input
                        name="newcategory"
                        type="text" maxlength="45"
                        value="<?= hsc(entity('newcategory')) ?>"
                        class="inputBox" style="width:300px;">
                    <?php
                    echo EvoFieldRow::render([
                        'field' => 'newcategory',
                        'label' => lang('new_category'),
                        'body' => ob_get_clean(),
                        'attr' => ['id' => 'newcategry', 'style' => 'display:none;'],
                    ]);

                    ob_start();
                    ?>
                    <textarea
                        name="description"
                        style="height:4em;width:300px;"><?= hsc(entity('description')) ?></textarea>
                    <?php
                    echo EvoFieldRow::render([
                        'label' => lang('htmlsnippet_desc'),
                        'body' => ob_get_clean(),
                    ]);

                    ob_start();
                    ?>
                    <input
                        name="editor_type"
                        type="checkbox"
                        <?= entity('editor_type') == 1 ? ' checked="checked"' : '' ?>
                        class="inputBox" value="1" />
                    <?php
                    echo EvoFieldRow::render([
                        'label' => lang('resource_opt_richtext'),
                        'body' => ob_get_clean(),
                    ]);
                    ?>
                </table>
            </div>
            <div class="tab-page" id="tabHelp">
                <h2 class="tab">ヘルプ</h2>
                <?= lang('htmlsnippet_msg') ?>
            </div>
        </div>
        <script>
            var tpstatus = <?=
                        EvoFormFlow::rememberTab() ? 'true' : 'false'
                        ?>;
            chunkPane = new WebFXTabPane(document.getElementById('chunkPane'), tpstatus);
        </script>
    </div>
</form>
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
