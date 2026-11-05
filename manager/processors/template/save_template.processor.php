<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_template')) {
    alert()->setError(3);
    alert()->dumpError();
}
// 新規（19）と更新（16）以外は拒否する
$mode = post_choicevar('mode', [16, 19], 0);
$id = post_intvar('id');
if (!$mode || ($mode === 16 && !$id)) {
    alert()->setError(2);
    alert()->dumpError();
}

// 本文は文字列だけを受ける。配列で送られたときは、空で上書きせず保存しない
$content = postv('content', '');
if (!is_string($content)) {
    alert()->setError(2);
    alert()->dumpError();
}

$rawName = manager()->requireElementName(post_strvar('templatename'), $_lang['template'], 19, 16, $id);

$tbl_site_templates = evo()->getFullTableName('site_templates');

$field = [];
$field['templatename'] = $rawName;
$field['description'] = post_strvar('description');
$field['content'] = $content;
$field['locked'] = postv('locked') === 'on' ? 1 : 0;
$field['parent'] = post_intvar('parent');

switch ($mode) {
    case 19:

        // invoke OnBeforeTempFormSave event
        $tmp = [
            'mode' => 'new',
            'id' => ''
        ];
        evo()->invokeEvent("OnBeforeTempFormSave", $tmp);

        // disallow duplicate names for new templates
        $rs = db()->select('COUNT(id)', $tbl_site_templates, "templatename = '" . db()->escape($rawName) . "'");
        $count = db()->getValue($rs);
        if ($count > 0) {
            manager()->redirectToElementForm(
                sprintf($_lang['duplicate_name_found_general'], $_lang['template'], $rawName),
                19,
                16,
                0
            );
        }

        $field['category'] = manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));

        //do stuff to save the new doc
        $newid = db()->insert(db()->escape($field), $tbl_site_templates);
        if (!$newid) {
            echo "Couldn't get last insert key!";
            exit;
        }

        // invoke OnTempFormSave event
        $tmp = [
            "mode" => "new",
            "id" => $newid
        ];
        evo()->invokeEvent("OnTempFormSave", $tmp);

        // empty cache
        $modx->clearCache();
        // finished emptying cache - redirect
        $stay = post_choicevar('stay', [1, 2], 0);
        if ($stay) {
            $a = ($stay === 2) ? "16&id=$newid" : "19";
            $header = "Location: index.php?a={$a}&stay={$stay}";
        } else {
            $header = "Location: index.php?a=76";
        }
        header($header);
        break;
    case 16:

        // invoke OnBeforeTempFormSave event
        $tmp = [
            'mode' => 'upd',
            'id' => $id
        ];
        evo()->invokeEvent('OnBeforeTempFormSave', $tmp);

        // disallow duplicate names for new templates
        $rs = db()->select('COUNT(id)', $tbl_site_templates, "templatename = '" . db()->escape($rawName) . "' AND id != '{$id}'");
        $count = db()->getValue($rs);
        if ($count > 0) {
            manager()->redirectToElementForm(
                sprintf($_lang['duplicate_name_found_general'], $_lang['template'], $rawName),
                19,
                16,
                $id
            );
        }

        $field['category'] = manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));

        //do stuff to save the edited doc
        $rs = db()->update(db()->escape($field), $tbl_site_templates, "id='{$id}'");
        if (!$rs) {
            echo "\$rs not set! Edited template not saved!";
        } else {
            // invoke OnTempFormSave event
            $tmp = [
                "mode" => "upd",
                "id" => $id
            ];
            evo()->invokeEvent("OnTempFormSave", $tmp);

            // first empty the cache
            $modx->clearCache();
            // finished emptying cache - redirect
            $stay = post_choicevar('stay', [1, 2], 0);
            if ($stay) {
                $a = ($stay === 2) ? "16&id=$id" : "19";
                $header = "Location: index.php?a={$a}&stay={$stay}";
            } else {
                $header = "Location: index.php?a=76";
            }
            header($header);
        }
        break;
}
