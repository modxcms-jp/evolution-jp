<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_template')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (isset($_POST['id']) && preg_match('@^[0-9]+$@', $_POST['id'])) {
    $id = postv('id');
}
$name = db()->escape(trim(postv('name')));
$description = db()->escape(postv('description'));
$caption = db()->escape(postv('caption'));
$type = db()->escape(postv('type'));
$elements = db()->escape(postv('elements'));
$default_text = db()->escape(postv('default_text'));
$rank = isset($_POST['rank']) ? db()->escape(postv('rank')) : 0;
$display = db()->escape(postv('display'));
$display_params = db()->escape(postv('params'));
$locked = postv('locked') == 'on' ? 1 : 0;

//Kyle Jaebker - added category support
if (empty(postv('newcategory')) && postv('categoryid') > 0) {
    $category = db()->escape(postv('categoryid'));
} elseif (empty(postv('newcategory')) && postv('categoryid') <= 0) {
    $category = 0;
} else {
    $catCheck = manager()->checkCategory(db()->escape(postv('newcategory')));
    if ($catCheck) {
        $category = $catCheck;
    } else {
        $category = manager()->newCategory(postv('newcategory'));
    }
}

if ($name == '') {
    $name = 'Untitled variable';
}
if ($caption == '') {
    $caption = $name;
}
switch (postv('mode')) {
    case '300':
        // invoke OnBeforeTVFormSave event
        $tmp = [
            'mode' => 'new',
            'id' => ''
        ];
        evo()->invokeEvent('OnBeforeTVFormSave', $tmp);
        if (check_exist_name($name) !== false) {
            $msg = sprintf($_lang['duplicate_name_found_general'], $_lang['tv'], $name);
            manager()->saveFormValues(300);
            $modx->webAlertAndQuit($msg, 'index.php?a=300');
            exit;
        }
        if (check_reserved_names($name) !== false) {
            $msg = sprintf($_lang['reserved_name_warning'], $name);
            manager()->saveFormValues(300);
            $modx->webAlertAndQuit($msg, 'index.php?a=300');
            exit;
        }

        // Add new TV
        $field = compact(explode(
            ',',
            'name,description,caption,type,elements,default_text,display,display_params,rank,locked,category'
        ));
        $newid = db()->insert($field, '[+prefix+]site_tmplvars');
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
        if (postv('stay')) {
            switch (postv('stay')) {
                case '1':
                    $a = '300';
                    break;
                case '2':
                    $a = "301&id={$newid}";
                    break;
            }
            $url = "index.php?a={$a}&stay=" . postv('stay');
        } else {
            $url = "index.php?a=76";
        }
        header("Location: {$url}");
        break;
    case '301':
        // invoke OnBeforeTVFormSave event
        $tmp = [
            'mode' => 'upd',
            'id' => $id
        ];
        evo()->invokeEvent('OnBeforeTVFormSave', $tmp);
        if (check_exist_name($name) !== false) {
            $msg = sprintf($_lang['duplicate_name_found_general'], $_lang['tv'], $name);
            manager()->saveFormValues(301);
            $modx->webAlertAndQuit($msg, "index.php?id={$id}&a=301");
            exit;
        }
        if (check_reserved_names($name) !== false) {
            $msg = sprintf($_lang['reserved_name_warning'], $name);
            manager()->saveFormValues(301);
            $modx->webAlertAndQuit($msg, "index.php?id={$id}&a=301");
            exit;
        }
        // update TV
        $was_name = db()->getValue(db()->select('name', '[+prefix+]site_tmplvars', "id='{$id}'"));
        $field = compact(explode(
            ',',
            'name,description,caption,type,elements,default_text,display,display_params,rank,locked,category'
        ));
        $rs = db()->update($field, '[+prefix+]site_tmplvars', "id='{$id}'");

        if (!$rs) {
            echo "\$rs not set! Edited variable not saved!";
            exit;
        }

        // update all references to this TV
        $name = stripslashes($name);
        $name = str_replace("'", "''", $name);
        $was_name = str_replace("'", "''", $was_name);
        if ($name !== $was_name) {
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
        if (postv('stay')) {
            switch (postv('stay')) {
                case '1':
                    $a = '300';
                    break;
                case '2':
                    $a = "301&id={$id}";
                    break;
            }
            $url = "index.php?a={$a}&stay=" . postv('stay');
        } else {
            $url = 'index.php?a=76';
        }
        header("Location: {$url}");
        break;
    default:
        echo 'Erm... You supposed to be here now?';
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
    $templates = postv('template'); // get muli-templates based on S.BRENNAN mod
    if (!$templates) {
        return;
    }
    foreach ($templates as $iValue) {
        $setRank = $getRankArray[$iValue] ?? 0;
        $field = [
            'tmplvarid' => $id,
            'templateid' => $iValue,
            'rank' => $setRank
        ];
        db()->insert($field, '[+prefix+]site_tmplvar_templates');
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
            foreach ($docgroups as $value) {
                $documentGroupId = (int)stripslashes($value);
                if ($documentGroupId <= 0) {
                    continue;
                }

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

function check_exist_name($name)
{
    // disallow duplicate names for new tvs
    $where = "name='{$name}'";
    if (postv('mode') == 301) {
        $where = $where . " AND id!=" . postv('id');
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
    if (in_array($name, $reserved_names)) {
        $_POST['name'] = '';
        return true;
    } else {
        return false;
    }
}
