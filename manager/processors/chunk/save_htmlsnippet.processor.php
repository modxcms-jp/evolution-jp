<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_chunk')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (preg_match('@^[1-9][0-9]*$@', postv('id'))) {
    $id = postv('id');
}

function entity($key, $default = null)
{
    if ($key === 'name') {
        return postv($key, 'Untitled chunk');
    }
    if ($key === 'locked') {
        return postv('locked') === 'on' ? 1 : 0;
    }
    if ($key === 'editor_type') {
        return postv('editor_type') == 1 ? 1 : 0;
    }
    if ($key === 'published') {
        return postv('published') == 1 ? 1 : 0;
    }

    $currentdate = time();

    if ($key === 'pub_date') {
        if (empty(postv('pub_date'))) {
            return 0;
        }
        $pub_date = evo()->toTimeStamp(postv('pub_date'));
        if ($pub_date < $currentdate) {
            $_POST['published'] = 1;
        } elseif ($pub_date > $currentdate) {
            $_POST['published'] = 0;
        }
        return $pub_date;
    }

    // unpub_date
    if ($key === 'unpub_date') {
        if (empty(postv('unpub_date'))) {
            return 0;
        }
        $unpub_date = evo()->toTimeStamp(postv('unpub_date'));
        if ($unpub_date < $currentdate) {
            $_POST['published'] = 0;
        }
        return $unpub_date;
    }

    if ($key === 'category') {
        if (postv('newcategory')) {
            $catCheck = manager()->checkCategory(postv('newcategory'));
            if ($catCheck) {
                return $catCheck;
            } else {
                return manager()->newCategory(postv('newcategory'));
            }
        } elseif (0 < postv('categoryid')) {
            return postv('categoryid');
        } else {
            return 0;
        }
    }

    return postv($key, $default);
}

switch (postv('mode')) {
    case '77':
        $tmp = [
            'mode' => 'new',
            'id' => ''
        ];
        evo()->invokeEvent('OnBeforeChunkFormSave', $tmp);

        $rs = db()->select(
            'COUNT(id)',
            '[+prefix+]site_htmlsnippets',
            where('name', entity('name'))
        );
        $count = db()->getValue($rs);
        if ($count > 0) {
            manager()->saveFormValues(77);
            evo()->webAlertAndQuit(
                sprintf($_lang['duplicate_name_found_general'], $_lang['chunk'], entity('name')),
                'index.php?a=77'
            );
            exit;
        }
        $field = [
            'name' => entity('name'),
            'description' => entity('description'),
            'published' => entity('published'),
            'pub_date' => entity('pub_date'),
            'unpub_date' => entity('unpub_date'),
            'snippet' => entity('post'),
            'locked' => entity('locked'),
            'editor_type' => entity('editor_type'),
            'category' => entity('category')
        ];
        $newid = db()->insert(db()->escape($field), '[+prefix+]site_htmlsnippets');
        if (!$newid) {
            exit("Couldn't get last insert key!");
        }

        $tmp = [
            'mode' => 'new',
            'id' => $newid
        ];
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
        $tmp = [
            "mode" => "upd",
            "id" => $id
        ];
        evo()->invokeEvent("OnBeforeChunkFormSave", $tmp);

        if (check_exist_name(entity('name')) !== false) {
            manager()->saveFormValues(78);
            evo()->webAlertAndQuit(
                sprintf($_lang['duplicate_name_found_general'], $_lang['chunk'], entity('name')),
                "index.php?a=78&id=" . $id
            );
            exit;
        }

        //do stuff to save the edited doc
        $was_name = db()->getValue(
            db()->select('name', '[+prefix+]site_htmlsnippets', where('id', $id))
        );
        $field = [
            'name' => entity('name'),
            'description' => entity('description'),
            'published' => entity('published'),
            'pub_date' => entity('pub_date'),
            'unpub_date' => entity('unpub_date'),
            'snippet' => entity('post'),
            'locked' => entity('locked'),
            'editor_type' => entity('editor_type'),
            'category' => entity('category')
        ];
        $rs = db()->update(db()->escape($field), '[+prefix+]site_htmlsnippets', where('id', $id));
        if (!$rs) {
            echo "\$rs not set! Edited htmlsnippet not saved!";
        } else {
            $chunkName = db()->escape(str_replace("'", "''", entity('name')));
            $was_name = db()->escape(str_replace("'", "''", $was_name));
            if ($chunkName !== $was_name) {
                db()->update(
                    sprintf("content=REPLACE(content,'{{%s}}','{{%s}}')", $was_name, $chunkName),
                    '[+prefix+]site_content'
                );
                db()->update(
                    sprintf("content=REPLACE(content,'{{%s}}','{{%s}}')", $was_name, $chunkName),
                    '[+prefix+]site_templates'
                );
                db()->update(
                    sprintf("snippet=REPLACE(snippet,'{{%s}}','{{%s}}')", $was_name, $chunkName),
                    '[+prefix+]site_htmlsnippets'
                );
                db()->update(
                    sprintf("value=REPLACE(value,'{{%s}}','{{%s}}')", $was_name, $chunkName),
                    '[+prefix+]site_tmplvar_contentvalues'
                );
                db()->update(
                    sprintf("content=REPLACE(content,'{{%s:','{{%s:')", $was_name, $chunkName),
                    '[+prefix+]site_content'
                );
                db()->update(
                    sprintf("content=REPLACE(content,'{{%s:','{{%s:')", $was_name, $chunkName),
                    '[+prefix+]site_templates'
                );
                db()->update(
                    sprintf("snippet=REPLACE(snippet,'{{%s:','{{%s:')", $was_name, $chunkName),
                    '[+prefix+]site_htmlsnippets'
                );
                db()->update(
                    sprintf("value=REPLACE(value,'{{%s:','{{%s:')", $was_name, $chunkName),
                    '[+prefix+]site_tmplvar_contentvalues'
                );
                db()->update(
                    sprintf("content=REPLACE(content,'{{%s?','{{%s?')", $was_name, $chunkName),
                    '[+prefix+]site_content'
                );
                db()->update(
                    sprintf("content=REPLACE(content,'{{%s?','{{%s?')", $was_name, $chunkName),
                    '[+prefix+]site_templates'
                );
                db()->update(
                    sprintf("snippet=REPLACE(snippet,'{{%s?','{{%s?')", $was_name, $chunkName),
                    '[+prefix+]site_htmlsnippets'
                );
                db()->update(
                    sprintf("value=REPLACE(value,'{{%s?','{{%s?')", $was_name, $chunkName),
                    '[+prefix+]site_tmplvar_contentvalues'
                );
            }

            // invoke OnChunkFormSave event
            $tmp = [
                'mode' => 'upd',
                'id' => $id
            ];
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

function check_exist_name($chunkName)
{
    $where = "name='" . $chunkName . "'";
    if (postv('mode') == 78) {
        $where .= " AND id!=" . postv('id');
    }
    $rs = db()->select('COUNT(id)', '[+prefix+]site_htmlsnippets', $where);
    if (!db()->getValue($rs)) {
        return false;
    }
    return true;
}
