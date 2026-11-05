<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_chunk')) {
    alert()->setError(3);
    alert()->dumpError();
}

// 新規（77）と更新（78）以外は拒否する
$mode = post_choicevar('mode', [77, 78], 0);
$id = post_intvar('id');
if (!$mode || ($mode === 78 && !$id)) {
    alert()->setError(2);
    alert()->dumpError();
}

// 本文は文字列だけを受ける。配列で送られたときは、空で上書きせず保存しない
if (!is_string(postv('post', ''))) {
    alert()->setError(2);
    alert()->dumpError();
}

function entity($key)
{
    if ($key === 'name') {
        return manager()->normalizeElementName(post_strvar('name'));
    }
    if ($key === 'description') {
        return post_strvar('description');
    }
    if ($key === 'post') {
        return postv('post', '');
    }
    if ($key === 'locked') {
        return postv('locked') === 'on' ? 1 : 0;
    }
    if ($key === 'editor_type') {
        return post_intvar('editor_type') === 1 ? 1 : 0;
    }
    if ($key === 'published') {
        return post_intvar('published') === 1 ? 1 : 0;
    }

    $currentdate = time();

    if ($key === 'pub_date') {
        $pub_date = post_strvar('pub_date');
        if (empty($pub_date)) {
            return 0;
        }
        $pub_date = evo()->toTimeStamp($pub_date);
        if ($pub_date < $currentdate) {
            $_POST['published'] = 1;
        } elseif ($pub_date > $currentdate) {
            $_POST['published'] = 0;
        }
        return $pub_date;
    }

    // unpub_date
    if ($key === 'unpub_date') {
        $unpub_date = post_strvar('unpub_date');
        if (empty($unpub_date)) {
            return 0;
        }
        $unpub_date = evo()->toTimeStamp($unpub_date);
        if ($unpub_date < $currentdate) {
            $_POST['published'] = 0;
        }
        return $unpub_date;
    }

    // category
    return manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));
}

// 名前が空だと一覧から開けず、更新では {{旧名}} の参照まで空の名前に書き換わるため、保存しない
manager()->requireElementName(post_strvar('name'), $_lang['chunk'], 77, 78, $id);

switch ($mode) {
    case 77:
        $tmp = [
            'mode' => 'new',
            'id' => ''
        ];
        evo()->invokeEvent('OnBeforeChunkFormSave', $tmp);

        $rs = db()->select(
            'COUNT(id)',
            '[+prefix+]site_htmlsnippets',
            where('name', db()->escape(entity('name')))
        );
        $count = db()->getValue($rs);
        if ($count > 0) {
            manager()->redirectToElementForm(
                sprintf($_lang['duplicate_name_found_general'], $_lang['chunk'], entity('name')),
                77,
                78,
                0
            );
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
        $stay = post_choicevar('stay', [1, 2], 0);
        if (!$stay) {
            header('Location: index.php?a=76');
            return;
        }

        $a = ($stay === 2) ? "78&id=" . $newid : '77';
        header("Location: index.php?a=" . $a . "&stay=" . $stay);
        break;
    case 78:

        // invoke OnBeforeChunkFormSave event
        $tmp = [
            "mode" => "upd",
            "id" => $id
        ];
        evo()->invokeEvent("OnBeforeChunkFormSave", $tmp);

        if (check_exist_name(entity('name'), $id) !== false) {
            manager()->redirectToElementForm(
                sprintf($_lang['duplicate_name_found_general'], $_lang['chunk'], entity('name')),
                77,
                78,
                $id
            );
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
            $stay = post_choicevar('stay', [1, 2], 0);
            if (!$stay) {
                header("Location: index.php?a=76");
                return;
            }

            $a = ($stay === 2) ? "78&id=" . $id : "77";
            header("Location: index.php?a=" . $a . "&stay=" . $stay);
        }
        break;
}

// 更新で、自分以外に同じ名前のチャンクがあるか
function check_exist_name($chunkName, $id)
{
    $where = "name='" . db()->escape($chunkName) . "' AND id!=" . (int)$id;
    $rs = db()->select('COUNT(id)', '[+prefix+]site_htmlsnippets', $where);
    if (!db()->getValue($rs)) {
        return false;
    }
    return true;
}
