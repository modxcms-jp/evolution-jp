<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_chunk')) {
    alert()->setError(3);
    alert()->dumpError();
}

$input = $_POST;
extract($input);
unset($input);

if (preg_match('@^[1-9][0-9]*$@', postv('id'))) {
    $id = postv('id');
}

$snippet = $post;
$name = (trim($name) !== '') ? trim($name) : 'Untitled chunk';
$locked = $locked === 'on' ? 1 : 0;
$editor_type = $editor_type == 1 ? 1 : 0;
$published = $published == 1 ? 1 : 0;

// determine published status
$currentdate = time();

if (empty($pub_date)) {
    $pub_date = 0;
} else {
    $pub_date = evo()->toTimeStamp($pub_date);
    if (empty($pub_date)) {
        manager()->saveFormValues(78);
        evo()->webAlertAndQuit($_lang["mgrlog_dateinvalid"], "index.php?a=78&id=" . $id);
        exit;
    }
    if ($pub_date < $currentdate) {
        $published = 1;
    } elseif ($pub_date > $currentdate) {
        $published = 0;
    }
}
if (empty($unpub_date)) {
    $unpub_date = 0;
} else {
    $unpub_date = evo()->toTimeStamp($unpub_date);
    if (empty($unpub_date)) {
        manager()->saveFormValues(78);
        evo()->webAlertAndQuit($_lang['mgrlog_dateinvalid'], 'index.php?a=78&id=' . $id);
        exit;
    }
    if ($unpub_date < $currentdate) {
        $published = 0;
    }
}

//Kyle Jaebker - added category support
if (postv('newcategory')) {
    $catCheck = manager()->checkCategory(postv('newcategory'));
    if ($catCheck) {
        $category = $catCheck;
    } else {
        $category = manager()->newCategory(postv('newcategory'));
    }
} elseif (0 < postv('categoryid')) {
    $category = postv('categoryid');
} else {
    $category = 0;
}

switch (postv('mode')) {
    case '77':
        $tmp = array(
            'mode' => 'new',
            'id' => ''
        );
        evo()->invokeEvent('OnBeforeChunkFormSave', $tmp);

        $rs = db()->select(
            'COUNT(id)',
            '[+prefix+]site_htmlsnippets',
            where('name', $name)
        );
        $count = db()->getValue($rs);
        if ($count > 0) {
            manager()->saveFormValues(77);
            evo()->webAlertAndQuit(
                sprintf($_lang['duplicate_name_found_general'], $_lang['chunk'], $name),
                'index.php?a=77'
            );
            exit;
        }
        $field = compact(explode(
            ',',
            'name,description,published,pub_date,unpub_date,snippet,locked,editor_type,category'
        ));
        $newid = db()->insert(db()->escape($field), '[+prefix+]site_htmlsnippets');
        if (!$newid) {
            exit("Couldn't get last insert key!");
        }

        $tmp = array(
            'mode' => 'new',
            'id' => $newid
        );
        evo()->invokeEvent('OnChunkFormSave', $tmp);

        // empty cache
        evo()->clearCache(); // first empty the cache
        // finished emptying cache - redirect
        if (postv('stay') == '') {
            header('Location: index.php?a=76');
            return;
        }

        $a = (postv('stay') == '2') ? "78&id=" . $newid : '77';
        header("Location: index.php?a=" . $a . "&stay=" . postv('stay'));
        break;
    case '78':

        // invoke OnBeforeChunkFormSave event
        $tmp = array(
            "mode" => "upd",
            "id" => $id
        );
        evo()->invokeEvent("OnBeforeChunkFormSave", $tmp);

        if (check_exist_name($name) !== false) {
            manager()->saveFormValues(78);
            evo()->webAlertAndQuit(
                sprintf($_lang['duplicate_name_found_general'], $_lang['chunk'], $name),
                "index.php?a=78&id=" . $id
            );
            exit;
        }

        //do stuff to save the edited doc
        $was_name = db()->getValue(
            db()->select('name', '[+prefix+]site_htmlsnippets', where('id', $id))
        );
        $field = compact(explode(
            ',',
            'name,description,published,pub_date,unpub_date,snippet,locked,editor_type,category'
        ));
        $rs = db()->update(db()->escape($field), '[+prefix+]site_htmlsnippets', where('id', $id));
        if (!$rs) {
            echo "\$rs not set! Edited htmlsnippet not saved!";
        } else {
            $name = db()->escape(str_replace("'", "''", $name));
            $was_name = db()->escape(str_replace("'", "''", $was_name));
            if ($name !== $was_name) {
                db()->update(
                    sprintf("content=REPLACE(content,'{{%s}}','{{%s}}')", $was_name, $name),
                    '[+prefix+]site_content'
                );
                db()->update(
                    sprintf("content=REPLACE(content,'{{%s}}','{{%s}}')", $was_name, $name),
                    '[+prefix+]site_templates'
                );
                db()->update(
                    sprintf("snippet=REPLACE(snippet,'{{%s}}','{{%s}}')", $was_name, $name),
                    '[+prefix+]site_htmlsnippets'
                );
                db()->update(
                    sprintf("value=REPLACE(value,'{{%s}}','{{%s}}')", $was_name, $name),
                    '[+prefix+]site_tmplvar_contentvalues'
                );
                db()->update(
                    sprintf("content=REPLACE(content,'{{%s:','{{%s:')", $was_name, $name),
                    '[+prefix+]site_content'
                );
                db()->update(
                    sprintf("content=REPLACE(content,'{{%s:','{{%s:')", $was_name, $name),
                    '[+prefix+]site_templates'
                );
                db()->update(
                    sprintf("snippet=REPLACE(snippet,'{{%s:','{{%s:')", $was_name, $name),
                    '[+prefix+]site_htmlsnippets'
                );
                db()->update(
                    sprintf("value=REPLACE(value,'{{%s:','{{%s:')", $was_name, $name),
                    '[+prefix+]site_tmplvar_contentvalues'
                );
                db()->update(
                    sprintf("content=REPLACE(content,'{{%s?','{{%s?')", $was_name, $name),
                    '[+prefix+]site_content'
                );
                db()->update(
                    sprintf("content=REPLACE(content,'{{%s?','{{%s?')", $was_name, $name),
                    '[+prefix+]site_templates'
                );
                db()->update(
                    sprintf("snippet=REPLACE(snippet,'{{%s?','{{%s?')", $was_name, $name),
                    '[+prefix+]site_htmlsnippets'
                );
                db()->update(
                    sprintf("value=REPLACE(value,'{{%s?','{{%s?')", $was_name, $name),
                    '[+prefix+]site_tmplvar_contentvalues'
                );
            }

            // invoke OnChunkFormSave event
            $tmp = array(
                'mode' => 'upd',
                'id' => $id
            );
            evo()->invokeEvent('OnChunkFormSave', $tmp);

            // empty cache
            evo()->clearCache(); // first empty the cache
            // finished emptying cache - redirect
            if (postv('stay') == '') {
                header("Location: index.php?a=76");
                return;
            }

            $a = (postv('stay') == 2) ? "78&id=" . $id : "77";
            header("Location: index.php?a=" . $a . "&stay=" . postv('stay'));
        }
        break;
}

function check_exist_name($name)
{
    $where = "name='" . $name . "'";
    if (postv('mode') == 78) {
        $where .= " AND id!=" . postv('id');
    }
    $rs = db()->select('COUNT(id)', '[+prefix+]site_htmlsnippets', $where);
    if (!db()->getValue($rs)) {
        return false;
    }
    return true;
}
