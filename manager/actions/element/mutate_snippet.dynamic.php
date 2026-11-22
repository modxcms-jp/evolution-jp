<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

$content = manager()->openElementForm('snippet', lang('snippet'));
$id = (int) ($content['id'] ?? 0);
sessionv('*itemname', $content ? $content['name'] : 'New snippet');

// restore saved form
$content = manager()->restoreElementFormValues($content, 'snippet');

function entity($key, $default = null)
{
    global $content;
    return $content[$key] ?? $default;
}

?>
<script src="<?= manager_asset('media/script/element-properties.js') ?>" type="text/javascript"></script>
<script src="<?= manager_asset('media/script/element-category.js') ?>" type="text/javascript"></script>
<script src="<?= manager_asset('media/script/element-lock.js') ?>" type="text/javascript"></script>
<script type="text/javascript">
    function duplicaterecord() {
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_duplicate_record'))) ?>)) {
            documentDirty = false;
            EvoCsrf.post("index.php?id=<?= $id ?>&a=98");
        }
    }

    function deletedocument() {
        if (confirm(<?= js_literal(stripcslashes(lang('confirm_delete_snippet'))) ?>)) {
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
    <input type="hidden" name="mode" value="<?= hsc(EvoFormFlow::mode('snippet')) ?>">

    <h1><?= lang('snippet_title') ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php if (evo()->hasPermission('save_snippet')): ?>
                <li id="Button1" class="mutate">
                    <a href="#"
                        onclick="documentDirty=false;var f=document.getElementById('mutate');f.noValidate=true;f.requestSubmit();">
                        <?= lang('update') ?>
                    </a>
                    <span class="and"> + </span>
                    <select id="stay" name="after_save">
                        <?= EvoFormFlow::afterSaveOptions(EvoFormFlow::requestedAfterSave(), true) ?>
                    </select>
                </li>
            <?php endif; ?>
            <?php
            if (request_intvar('a') === 22) {
                if (evo()->hasPermission('new_snippet')) {
                    echo manager()->ab([
                        'onclick' => 'duplicaterecord();',
                        'label' => lang('duplicate')
                    ]);
                }
                if (evo()->hasPermission('delete_snippet')) {
                    echo manager()->ab([
                        'onclick' => 'deletedocument();',
                        'label' => lang('delete')
                    ]);
                }
            }
            echo manager()->ab([
                'onclick' => "EvoShell.go('index.php?a=76');",
                'label' => lang('cancel')
            ]);
            ?>
        </ul>
    </div>

    <div class="sectionBody">
        <div class="tab-pane" id="snipetPane">
            <!-- General -->
            <div class="tab-page" id="tabSnippet">
                <h2 class="tab"><?= lang('settings_general') ?></h2>
                <table>
                    <?= EvoFieldRow::render([
                        'label' => lang('snippet_name'),
                        'body' => '[[<input name="name" type="text" maxlength="100" value="' . hsc(entity('name')) . '" class="inputBox" style="width:300px;">]]',
                    ]) ?>
                </table>
                <!-- PHP text editor start -->
                <div class="code-editor">
                    <div class="code-editor-header">
                        <span class="code-editor-title"><?= lang('snippet_code') ?></span>
                        <span class="code-editor-options"><?= lang('wrap_lines') ?>
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
                <h2 class="tab"><?= lang('settings_properties') ?></h2>
                <table>
                    <?php
                    ob_start();
                    ?>
                    <select name="categoryid" style="width:300px;">
                        <?= manager()->elementCategoryOptions(entity('category')) ?>
                    </select>
                    <?php
                    echo EvoFieldRow::render([
                        'label' => lang('existing_category'),
                        'body' => ob_get_clean(),
                    ]);

                    // 新しいカテゴリの行は、element-category.js が表示を切り替える
                    ob_start();
                    ?>
                    <input name="newcategory" type="text"
                        maxlength="45" value="<?= hsc(entity('newcategory', '')) ?>"
                        class="inputBox"
                        style="width:300px;">
                    <?php
                    echo EvoFieldRow::render([
                        'field' => 'newcategory',
                        'label' => lang('new_category'),
                        'body' => ob_get_clean(),
                        'attr' => ['id' => 'newcategry', 'style' => 'display:none;'],
                    ]);

                    ob_start();
                    ?>
                    <textarea name="description"
                        style="height:4em;"><?= hsc(entity('description')) ?></textarea>
                    <?php
                    echo EvoFieldRow::render([
                        'label' => lang('snippet_desc'),
                        'body' => ob_get_clean(),
                    ]);

                    $snippetErrorReporting = entity('error_reporting', 'inherit');
                    echo EvoFieldRow::render([
                        'label' => lang('a17_error_reporting_title'),
                        'body' => EvoErrorReporting::radios((string)$snippetErrorReporting),
                        'help' => lang('a17_error_reporting_msg'),
                    ]);

                    // 見出しの無い行は、見出しを空にして値を入力欄の列にそろえる
                    if (evo()->hasPermission('save_snippet') == 1) {
                        ob_start();
                        ?>
                        <label>
                            <input style="padding:0;margin:0;" name="locked"
                                type="checkbox" <?= entity('locked') == 1 ? "checked='checked'" : '' ?>
                                class="inputBox">
                            <b><?= lang('lock_snippet') ?></b>
                            <span class="comment"><?= lang('lock_snippet_msg') ?></span>
                        </label>
                        <?php
                        echo EvoFieldRow::render([
                            'label' => '',
                            'body' => ob_get_clean(),
                        ]);
                    }

                    $from = "[+prefix+]site_modules AS sm " .
                        "INNER JOIN [+prefix+]site_module_depobj AS smd ON smd.module=sm.id AND smd.type=40 " .
                        "INNER JOIN [+prefix+]site_snippets AS ss ON ss.id=smd.resource ";
                    $ds = db()->select(
                        'sm.id,sm.name,sm.guid',
                        $from,
                        "smd.resource='{$id}' AND sm.enable_sharedparams='1'",
                        'sm.name'
                    );
                    if (db()->count($ds) > 0) {
                        $options = '';
                        while ($row = db()->getRow($ds)) {
                            $options .= '<option value="' . hsc($row['guid']) . '"' . (entity('moduleguid') == $row['guid'] ? " selected='selected'" : "") . ">" . hsc($row['name']) . "</option>";
                        }
                        echo EvoFieldRow::render([
                            'label' => lang('import_params'),
                            'body' => '<select name="moduleguid" style="width:300px;">' . $options . '</select>',
                            'help' => lang('import_params_msg'),
                        ]);
                    }

                    ob_start();
                    ?>
                    <textarea name="properties" maxlength="65535"
                        class="inputBox phptextarea"
                        onChange="showParameters(this);"><?= hsc(entity('properties')) ?></textarea>
                    <?php
                    echo EvoFieldRow::render([
                        'label' => lang('snippet_properties'),
                        'body' => ob_get_clean(),
                    ]);

                    // パラメーターの表は element-properties.js が #displayparams の中身を置き換え、行ごと表示を切り替える
                    echo EvoFieldRow::render([
                        'label' => '',
                        'body' => '<div id="displayparams">&nbsp;</div>',
                        'attr' => ['id' => 'displayparamrow'],
                    ]);
                    ?>
                </table>
            </div>
            <div class="tab-page" id="tabHelp">
                <h2 class="tab">ヘルプ</h2>
                <?= lang('snippet_msg') ?>
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
    var tpstatus = <?= EvoFormFlow::rememberTab() ? 'true' : 'false' ?>;
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