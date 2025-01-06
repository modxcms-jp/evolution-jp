<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_snippet')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = preg_match('@^[0-9]+$@', postv('id')) ? postv('id') : 0;
$name = db()->escape(trim(postv('name')));
$description = db()->escape(postv('description'));
$locked = postv('locked') == 'on' ? 1 : 0;
$snippet = db()->escape(trim(postv('post')));
$tbl_site_snippets = evo()->getFullTableName('site_snippets');

// strip out PHP tags from snippets
if (strncmp($snippet, '<?', 2) == 0) {
    $snippet = substr($snippet, 2);
    if (strncmp($snippet, 'php', 3) == 0) {
        $snippet = substr($snippet, 3);
    }
    if (substr($snippet, -2, 2) == '?>') {
        $snippet = substr($snippet, 0, -2);
    }
}
$properties = db()->escape(postv('properties'));
$moduleguid = db()->escape(postv('moduleguid'));
$sysevents = postv('sysevents');

//Kyle Jaebker - added category support
if (empty(postv('newcategory')) && 0 < postv('categoryid')) {
    $categoryid = db()->escape(postv('categoryid'));
} elseif (empty(postv('newcategory')) && postv('categoryid') <= 0) {
    $categoryid = 0;
} else {
    $catCheck = $modx->manager->checkCategory(db()->escape(postv('newcategory')));

    if ($catCheck) {
        $categoryid = $catCheck;
    } else {
        $categoryid = $modx->manager->newCategory(postv('newcategory'));
    }
}

if ($name == '') {
    $name = 'Untitled snippet';
}

// disallow duplicate names
$where = "name='{$name}'";
if ($id) {
    $where .= ' AND id!=' . $id;
}
$rs = db()->select('COUNT(id)', '[+prefix+]site_snippets', $where);
$count = db()->getValue($rs);
if ($count > 0) {
    $msg = sprintf($_lang['duplicate_name_found_general'], $_lang['snippet'], $name);
    $modx->manager->saveFormValues(23);
    $modx->webAlertAndQuit($msg, 'index.php?a=23');
    exit;
}

switch (postv('mode')) {
    case '23':
        // invoke OnBeforeSnipFormSave event
        $tmp = array(
            'mode' => 'new',
            'id' => ''
        );
        evo()->invokeEvent('OnBeforeSnipFormSave', $tmp);

        //do stuff to save the new doc
        $field = array();
        $field['name'] = $name;
        $field['description'] = $description;
        $field['snippet'] = $snippet;
        $field['moduleguid'] = $moduleguid;
        $field['locked'] = $locked;
        $field['properties'] = $properties;
        $field['category'] = $categoryid;
        $newid = db()->insert($field, $tbl_site_snippets);
        if (!$newid) {
            echo '$newid not set! New snippet not saved!';
            exit;
        }

        // invoke OnSnipFormSave event
        $tmp = array(
            'mode' => 'new',
            'id' => $newid
        );
        evo()->invokeEvent('OnSnipFormSave', $tmp);
        // empty cache
        $modx->clearCache(); // first empty the cache
        // finished emptying cache - redirect
        if (postv('stay') != '') {
            $a = (postv('stay') == '2') ? "22&id={$newid}" : '23';
            $header = "Location: index.php?a={$a}&stay=" . postv('stay');
        } else {
            $header = 'Location: index.php?a=76';
        }
        header($header);
        break;

    case '22':
        // invoke OnBeforeSnipFormSave event
        $tmp = array(
            'mode' => 'upd',
            'id' => $id
        );
        evo()->invokeEvent('OnBeforeSnipFormSave', $tmp);

        //do stuff to save the edited doc
        $field = array();
        $field['name'] = $name;
        $field['description'] = $description;
        $field['snippet'] = $snippet;
        $field['moduleguid'] = $moduleguid;
        $field['locked'] = $locked;
        $field['properties'] = $properties;
        $field['category'] = $categoryid;
        $rs = db()->update($field, $tbl_site_snippets, "id='{$id}'");
        if (!$rs) {
            echo '$rs not set! Edited snippet not saved!';
            exit;
        }

// invoke OnSnipFormSave event
        $tmp = array(
            'mode' => 'upd',
            'id' => $id
        );
        evo()->invokeEvent('OnSnipFormSave', $tmp);
        // empty cache
        $modx->clearCache(); // first empty the cache
        //if(postv('runsnippet')) run_snippet($snippet);
        // finished emptying cache - redirect
        if (postv('stay') != '') {
            $a = (postv('stay') == '2') ? "22&id={$id}" : '23';
            $header = "Location: index.php?a={$a}&stay=" . postv('stay');
        } else {
            $header = 'Location: index.php?a=76';
        }
        header($header);
        break;
    default:
}
