<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_template')) {
    alert()->setError(3);
    alert()->dumpError();
}

// 新規（300）と更新（301）以外は拒否する
$mode = post_choicevar('mode', [300, 301], 0);
$id = post_intvar('id');
if (!$mode || ($mode === 301 && !$id)) {
    alert()->setError(2);
    alert()->dumpError();
}

// 既定値と選択肢は文字列だけを受ける。配列で送られたときは、空で上書きせず保存しない
$elements = postv('elements', '');
$default_text = postv('default_text', '');
if (!is_string($elements) || !is_string($default_text)) {
    alert()->setError(2);
    alert()->dumpError();
}

$rawName = manager()->requireElementName(post_strvar('name'), $_lang['tv'], 300, 301, $id);
$description = post_strvar('description');
$caption = post_strvar('caption', $rawName);
$type = post_strvar('type');
$rank = post_strvar('rank', 0);
$display = post_strvar('display');
$display_params = post_strvar('params');
$locked = postv('locked') === 'on' ? 1 : 0;

switch ($mode) {
    case 300:
        // invoke OnBeforeTVFormSave event
        $tmp = [
            'mode' => 'new',
            'id' => ''
        ];
        evo()->invokeEvent('OnBeforeTVFormSave', $tmp);
        if (check_exist_name($rawName, 0) !== false) {
            manager()->redirectToElementForm(
                sprintf($_lang['duplicate_name_found_general'], $_lang['tv'], $rawName),
                300,
                301,
                0
            );
        }
        if (check_reserved_names($rawName) !== false) {
            manager()->redirectToElementForm(
                sprintf($_lang['reserved_name_warning'], $rawName),
                300,
                301,
                0
            );
        }

        $category = manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));

        // Add new TV
        $field = [
            'name' => $rawName,
            'description' => $description,
            'caption' => $caption,
            'type' => $type,
            'elements' => $elements,
            'default_text' => $default_text,
            'display' => $display,
            'display_params' => $display_params,
            'rank' => $rank,
            'locked' => $locked,
            'category' => $category
        ];
        $newid = db()->insert(db()->escape($field), '[+prefix+]site_tmplvars');
        if (!$newid) {
            echo "Couldn't get last insert key!";
            exit;
        }

        // save access permissions
        saveTemplateAccess();
        saveDocumentAccessPermissons();

        // invoke OnTVFormSave event
        $tmp = [
            'mode' => 'new',
            'id' => $newid
        ];
        evo()->invokeEvent('OnTVFormSave', $tmp);

        // empty cache
        $modx->clearCache(); // first empty the cache
        // finished emptying cache - redirect
        $stay = post_choicevar('stay', [1, 2], 0);
        if ($stay) {
            $a = ($stay === 2) ? "301&id={$newid}" : '300';
            $url = "index.php?a={$a}&stay={$stay}";
        } else {
            $url = "index.php?a=76";
        }
        header("Location: {$url}");
        break;
    case 301:
        // invoke OnBeforeTVFormSave event
        $tmp = [
            'mode' => 'upd',
            'id' => $id
        ];
        evo()->invokeEvent('OnBeforeTVFormSave', $tmp);
        if (check_exist_name($rawName, $id) !== false) {
            manager()->redirectToElementForm(
                sprintf($_lang['duplicate_name_found_general'], $_lang['tv'], $rawName),
                300,
                301,
                $id
            );
        }
        if (check_reserved_names($rawName) !== false) {
            manager()->redirectToElementForm(
                sprintf($_lang['reserved_name_warning'], $rawName),
                300,
                301,
                $id
            );
        }

        $category = manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));

        // update TV
        $was_name = db()->getValue(db()->select('name', '[+prefix+]site_tmplvars', "id='{$id}'"));
        $field = [
            'name' => $rawName,
            'description' => $description,
            'caption' => $caption,
            'type' => $type,
            'elements' => $elements,
            'default_text' => $default_text,
            'display' => $display,
            'display_params' => $display_params,
            'rank' => $rank,
            'locked' => $locked,
            'category' => $category
        ];
        $rs = db()->update(db()->escape($field), '[+prefix+]site_tmplvars', "id='{$id}'");

        if (!$rs) {
            echo "\$rs not set! Edited variable not saved!";
            exit;
        }

        // update all references to this TV
        $name = db()->escape($rawName);
        $was_name_raw = $was_name;
        $was_name = db()->escape($was_name);
        if ($rawName !== $was_name_raw) {
            db()->update("content=REPLACE(content,'[*{$was_name}*]','[*{$name}*]')", '[+prefix+]site_content');
            db()->update(
                "content=REPLACE(content,'[*{$was_name}*]','[*{$name}*]')",
                '[+prefix+]site_templates'
            );
            db()->update(
                "snippet=REPLACE(snippet,'[*{$was_name}*]','[*{$name}*]')",
                '[+prefix+]site_htmlsnippets'
            );
            db()->update(
                "value=REPLACE(value,    '[*{$was_name}*]','[*{$name}*]')",
                '[+prefix+]site_tmplvar_contentvalues'
            );
            db()->update("content=REPLACE(content,'[*{$was_name}:','[*{$name}:')", '[+prefix+]site_content');
            db()->update("content=REPLACE(content,'[*{$was_name}:','[*{$name}:')", '[+prefix+]site_templates');
            db()->update(
                "snippet=REPLACE(snippet,'[*{$was_name}:','[*{$name}:')",
                '[+prefix+]site_htmlsnippets'
            );
            db()->update(
                "value=REPLACE(value,    '[*{$was_name}:','[*{$name}:')",
                '[+prefix+]site_tmplvar_contentvalues'
            );
        }
        // save access permissions
        saveTemplateAccess();
        saveDocumentAccessPermissons();
        // invoke OnTVFormSave event
        $tmp = [
            'mode' => 'upd',
            'id' => $id
        ];
        evo()->invokeEvent('OnTVFormSave', $tmp);
        // empty cache
        $modx->clearCache(); // first empty the cache
        // finished emptying cache - redirect
        $stay = post_choicevar('stay', [1, 2], 0);
        if ($stay) {
            $a = ($stay === 2) ? "301&id={$id}" : '300';
            $url = "index.php?a={$a}&stay={$stay}";
        } else {
            $url = 'index.php?a=76';
        }
        header("Location: {$url}");
        break;
}

function saveTemplateAccess()
{
    global $id, $newid;

    if ($newid) {
        $id = $newid;
    }

    $getRankArray = [];

    $getRank = db()->select('templateid,`rank`', '[+prefix+]site_tmplvar_templates', "tmplvarid={$id}");

    while ($row = db()->getRow($getRank)) {
        $getRankArray[$row['templateid']] = $row['rank'];
    }
    db()->delete('[+prefix+]site_tmplvar_templates', "tmplvarid={$id}");

    // update template selections
    // 配列だけを受ける。配列でないときは何も割り当てない
    $templates = postv('template'); // get muli-templates based on S.BRENNAN mod
    if (!is_array($templates)) {
        return;
    }
    foreach (to_idsvar($templates) as $iValue) {
        $setRank = $getRankArray[$iValue] ?? 0;
        $field = [
            'tmplvarid' => $id,
            'templateid' => $iValue,
            'rank' => $setRank
        ];
        db()->insert(db()->escape($field), '[+prefix+]site_tmplvar_templates');
    }
}

function saveDocumentAccessPermissons()
{
    global $modx, $id, $newid;

    if ($newid) {
        $id = $newid;
    }
    $docgroups = postv('docgroups');

    // check for permission update access
    if ($modx->config['use_udperms'] == 1) {
        // delete old permissions on the tv
        $rs = db()->delete('[+prefix+]site_tmplvar_access', "tmplvarid='{$id}'");
        if (!$rs) {
            echo 'An error occurred while attempting to delete previous template variable access permission entries.';
            exit;
        }
        if (is_array($docgroups)) {
            foreach (to_idsvar($docgroups) as $documentGroupId) {
                $field = [
                    'tmplvarid' => $id,
                    'documentgroup' => $documentGroupId
                ];

                $rs = db()->insert(db()->escape($field), '[+prefix+]site_tmplvar_access');
                if (!$rs) {
                    echo "An error occurred while attempting to save template variable access permissions.";
                    exit;
                }
            }
        }
    }
}

// 同じ名前の TV があるか。$id は更新のときの自分の id（新規は 0）で、あれば自分は除く
function check_exist_name($name, $id)
{
    // disallow duplicate names for new tvs
    $where = "name='" . db()->escape($name) . "'";
    if ($id) {
        $where = $where . " AND id!=" . (int)$id;
    }
    $rs = db()->select('COUNT(id)', '[+prefix+]site_tmplvars', $where);
    $count = db()->getValue($rs);
    if ($count > 0) {
        return true;
    } else {
        return false;
    }
}

function check_reserved_names($name)
{
    // disallow reserved names
    $reserved_names = explode(
        ',',
        'id,type,contentType,pagetitle,longtitle,description,alias,link_attributes,published,pub_date,unpub_date,parent,isfolder,introtext,content,richtext,template,menuindex,searchable,cacheable,createdby,createdon,editedby,editedon,deleted,deletedon,deletedby,publishedon,publishedby,menutitle,donthit,haskeywords,hasmetatags,privateweb,privatemgr,content_dispo,hidemenu'
    );
    return in_array($name, $reserved_names);
}
