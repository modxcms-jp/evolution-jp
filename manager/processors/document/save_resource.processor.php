<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('save_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

global $form_v;
include_once(MODX_BASE_PATH . 'manager/actions/document/mutate_content/functions.php');
evo()->loadExtension('DocAPI');
$form_v = evo()->doc->fixTvNest($_POST);
$form_v = evo()->doc->initValue($form_v);
$form_v = evo()->doc->setValue($form_v);

// preprocess POST values
$id = $form_v['id'];
if (!preg_match('@^[0-9]*$@', $id) || (mode() === 'edit' && !$id)) {
    alert()->setError(2);
    alert()->dumpError();
}

$document_groups = getDocGroups();

checkDocPermission($id, $document_groups);

evo()->manager->saveFormValues();

if (mode() === 'new') {
    // invoke OnBeforeDocFormSave event
    $param = array(
        'mode' => 'new',
        'doc_vars' => getInputValues(evo()->doc->getNewDocID(), 'new'),
        'tv_vars' => $form_v['template'] ? get_tmplvars() : array()
    );
    evo()->invokeEvent('OnBeforeDocFormSave', $param);

    $newid = db()->insert(
        db()->escape($param['doc_vars'])
        , '[+prefix+]site_content'
    );
    if (!$newid) {
        $msg = 'An error occured while attempting to save the new document: ' . db()->getLastError();
        evo()->webAlertAndQuit($msg, 'index.php?a=' . getv('a'));
    }

    if (!empty($param['tv_vars'])) {
        insert_tmplvars($newid, $param['tv_vars']);
    }

    setDocPermissionsNew($document_groups, $newid);
    updateParentStatus($form_v['parent']);

    if (evo()->config('use_udperms')) {
        evo()->manager->setWebDocsAsPrivate($newid);
        evo()->manager->setMgrDocsAsPrivate($newid);
    }

    if ($form_v['syncsite']) {
        evo()->clearCache();
    }

    // invoke OnDocFormSave event
    evo()->event->vars = array('mode' => 'new', 'id' => $newid);
    evo()->invokeEvent('OnDocFormSave', evo()->event->vars);

    goNextAction($newid, $form_v['parent'], $form_v['stay'], $form_v['type']);
    return;
}

if (mode() === 'edit') {
    if ($id == evo()->config('site_start')) {
        checkStartDoc($id, $form_v['published'], $form_v['pub_date'], $form_v['unpub_date']);
    }
    if ($id == $form_v['parent']) {
        evo()->webAlertAndQuit(
            "Document can not be it's own parent!"
            , sprintf('index.php?a=27&id=%s', $id)
        );
    }

    $form_v['isfolder'] = checkFolderStatus($id);

    $db_v = getExistsValues($id);
    // set publishedon and publishedby
    $form_v['published'] = checkPublished($db_v);
    $form_v['pub_date'] = checkPub_date($db_v);
    $form_v['unpub_date'] = checkUnpub_date($db_v);
    $form_v['publishedon'] = checkPublishedon($db_v['publishedon']);
    $form_v['publishedby'] = checkPublishedby($db_v);
    $len = strlen(evo()->conf_var('friendly_url_suffix'));
    if (substr($form_v['alias'], -$len) === evo()->conf_var('friendly_url_suffix')) {
        $form_v['alias'] = substr($form_v['alias'], 0, -$len);
    }
    // invoke OnBeforeDocFormSave event
    $values = getInputValues($id, 'edit');
    $param = array(
        'mode' => 'upd',
        'id' => $id,
        'doc_vars' => $values,
        'tv_vars' => $form_v['template'] ? get_tmplvars($id) : array()
    );
    evo()->invokeEvent('OnBeforeDocFormSave', $param);

    $rs = db()->update(
        db()->escape($param['doc_vars'])
        , '[+prefix+]site_content'
        , sprintf("id='%s'", $id)
    );
    if (!$rs) {
        evo()->webAlertAndQuit(
            sprintf(
                "An error occured while attempting to save the edited document. The generated SQL is: <i> %s </i>."
                , $sql
            ), sprintf('index.php?a=27&id=%s', $id)
        );
    }

    if ($param['tv_vars']) {
        update_tmplvars($id, $param['tv_vars']);
    }

    setDocPermissionsEdit($document_groups, $id);
    updateParentStatus($form_v['parent']);

    // finished moving the document, now check to see if the old_parent should no longer be a folder
    if ($db_v['parent'] != 0) {
        folder2doc($db_v['parent']);
    }

    if (evo()->config('use_udperms') == 1) {
        evo()->manager->setWebDocsAsPrivate($id);
        evo()->manager->setMgrDocsAsPrivate($id);
    }

    if ($form_v['syncsite']) {
        if ($form_v['published'] != $db_v['published'] || $form_v['alias'] != $db_v['alias']) {
            evo()->clearCache(array('target' => 'sitecache'));
        } elseif ($form_v['parent'] != $db_v['parent']) {
            evo()->clearCache(array('target' => 'sitecache'));
        } else {
            evo()->clearCache(array('target' => 'pagecache'));
        }
    }

    // invoke OnDocFormSave event
    evo()->event->vars = array('mode' => 'upd', 'id' => $id);
    evo()->invokeEvent('OnDocFormSave', evo()->event->vars);
    goNextAction($id, $form_v['parent'], $form_v['stay'], $form_v['type']);
    return;
}

header('Location: index.php?a=7');


function get_tmplvars($id = 0) {
    global $form_v;

    $template = $form_v['template'];

    if (empty($template)) {
        return array();
    }

    // get document groups for current user
    if (sessionv('mgrDocgroups')) {
        $docgrp = implode(',', sessionv('mgrDocgroups'));
    }

    $rs = db()->select(
        'DISTINCT tv.*'
        , array(
            '[+prefix+]site_tmplvars AS tv',
            'INNER JOIN [+prefix+]site_tmplvar_templates AS tvtpl ON tvtpl.tmplvarid = tv.id',
            'LEFT JOIN [+prefix+]site_tmplvar_access tva ON tva.tmplvarid=tv.id'
        )
        , sprintf(
            "tvtpl.templateid='%s' AND (1='%s' OR ISNULL(tva.documentgroup) %s)"
            , $template
            , sessionv('mgrRole')
            , $docgrp ? sprintf('OR tva.documentgroup IN (%s)', $docgrp) : ''
        )
        , 'tv.rank'
    );

    $tmplvars = array();
    while ($row = db()->getRow($rs)) {
        $tvid = 'tv' . $row['id'];

        if (!isset($form_v[$tvid])) {
            $multi_type = array('checkbox', 'listbox-multiple', 'custom_tv');
            if (!in_array($row['type'], $multi_type)) {
                continue;
            }
        }

        if ($row['type'] === 'url') {
            if ($form_v[$tvid . '_prefix'] === 'DocID') {
                $value = $form_v[$tvid];
                if (preg_match('/\A[0-9]+\z/', $value)) {
                    $value = '[~' . $value . '~]';
                }
            } elseif ($form_v[$tvid . '_prefix'] !== '--') {
                $value = $form_v[$tvid];
                $value = $form_v[$tvid . '_prefix'] . $value;
            } else {
                $value = $form_v[$tvid];
            }
        } elseif ($row['type'] === 'file') {
            $value = $form_v[$tvid];
        } else {
            if (is_array($form_v[$tvid])) {
                // handles checkboxes & multiple selects elements
                $value = join('||', $form_v[$tvid]);
            } elseif (isset($form_v[$tvid])) {
                $value = $form_v[$tvid];
            } else {
                $value = '';
            }
        }
        // save value if it was modified
        if (substr($row['default_text'], 0, 6) === '@@EVAL') {
            $eval_str = trim(substr($row['default_text'], 7));
            $row['default_text'] = eval($eval_str);
        }
        if (strlen($value) > 0 && $value != $row['default_text']) {
            $tmplvars[$row['id']] = $value;
        } else {
            $tmplvars[$row['id']] = false;
        }
    }
    return $tmplvars;
}

function get_alias($id, $alias, $parent, $pagetitle) {
    if ($alias) {
        $alias = evo()->stripAlias($alias);
    }
    // friendly url alias checks
    if (!evo()->config('friendly_urls')) {
        return $alias;
    }

    if (!$parent) {
        $parent = '0';
    }
    if ($alias && !evo()->config('allow_duplicate_alias')) {
        // check for duplicate alias name if not allowed
        return _check_duplicate_alias($id, $alias, $parent);
    }

    if (!$alias && evo()->config('automatic_alias')) { // auto assign alias
        $i = evo()->config('automatic_alias');
        if ($i == 1) {
            return evo()->manager->get_alias_from_title($id, $pagetitle);
        }
        if ($i == 2) {
            return evo()->manager->get_alias_num_in_folder($id, $parent);
        }
    }
    return $alias;
}

function _check_duplicate_alias($id, $alias, $parent) {
    // only check for duplicates on the same level if alias_path is on
    if (evo()->config('use_alias_path')) {
        $docid = db()->getValue(
            'id'
            , '[+prefix+]site_content'
            , sprintf(
                "id!='%s' AND alias='%s' AND parent=%s LIMIT 1"
                , $id
                , $alias
                , $parent
            )
        );
        if ($docid < 1) {
            $docid = db()->getValue(
                'id'
                , '[+prefix+]site_content'
                , sprintf(
                    "id='%s' AND alias='' AND parent='%s'"
                    , $alias
                    , $parent)
            );
        }
    } else {
        $rs = db()->select(
            'id'
            , '[+prefix+]site_content'
            , sprintf(
                "id!='%s' AND alias='%s' LIMIT 1"
                , $id
                , $alias
            )
        );
        $docid = db()->getValue($rs);
        if ($docid < 1) {
            $docid = db()->getValue(
                db()->select(
                    'id'
                    , '[+prefix+]site_content'
                    , sprintf("id='%s' AND alias=''", $alias)
                )
            );
        }
    }

    if ($docid) {
        evo()->manager->saveFormValues(postv('mode'));

        $url = sprintf('index.php?a=%s', postv('mode'));
        if (mode() === 'edit') {
            $url .= sprintf('&id=%s', $id);
        } elseif (anyv('pid')) {
            $url .= sprintf('&pid=%s', anyv('pid'));
        }

        if (anyv('stay')) {
            $url .= '&stay=' . anyv('stay');
        }

        evo()->webAlertAndQuit(
            sprintf(lang('duplicate_alias_found')
                , $docid
                , $alias
            )
            , $url
        );
    }
    return $alias;
}

function checkDocPermission($id, $document_groups = array()) {
    global $form_v;
    // ensure that user has not made this document inaccessible to themselves
    if (sessionv('mgrRole') != 1 && is_array($document_groups) && $document_groups) {
        $document_group_list = implode(',', array_filter($document_groups, 'is_numeric'));
        if ($document_group_list) {
            $count = db()->getValue(
                db()->select(
                    'COUNT(mg.id)'
                    , '[+prefix+]membergroup_access mga, [+prefix+]member_groups mg'
                    , sprintf(
                    "mga.membergroup = mg.user_group AND mga.documentgroup IN(%s) AND mg.member='%s'"
                    , $document_group_list
                    , sessionv('mgrInternalKey')
                ))
            );
            if (!$count) {
                if (mode() === 'new') {
                    $url = 'index.php?a=4';
                } else {
                    $url = 'index.php?a=27&id=' . $id;
                }

                evo()->manager->saveFormValues();
                evo()->webAlertAndQuit(sprintf(lang('resource_permissions_error')), $url);
            }
        }
    }

    // get the document, but only if it already exists
    if (mode() === 'edit') {
        $rs = db()->select('parent', '[+prefix+]site_content', "id='{$id}'");
        $total = db()->getRecordCount($rs);
        if ($total > 1) {
            alert()->setError(6);
            alert()->dumpError();
        } elseif ($total < 1) {
            alert()->setError(7);
            alert()->dumpError();
        }
        if (evo()->config('use_udperms') != 1) {
            return;
        }
        $existingDocument = db()->getRow($rs);

        // check to see if the user is allowed to save the document in the place he wants to save it in
        if ($existingDocument['parent'] == $form_v['parent']) {
            return;
        }

        if (!evo()->checkPermissions($form_v['parent'])) {
            if (mode() === 'new') {
                $url = 'index.php?a=4';
            } else {
                $url = "index.php?a=27&id={$id}";
            }
            evo()->manager->saveFormValues();
            evo()->webAlertAndQuit(sprintf(lang('access_permission_parent_denied'), $id, $form_v['alias']), $url);
        }
    } elseif (!isAllowroot()) {
        alert()->setError(3);
        alert()->dumpError();
    } elseif (!evo()->hasPermission('new_document')) {
        alert()->setError(3);
        alert()->dumpError();
    }
}

function isAllowroot() {
    if (postv('parent') != 0) {
        return 1;
    }
    if (evo()->hasPermission('save_role')) {
        return 1;
    }
    if (evo()->config('udperms_allowroot')) {
        return 1;
    } else {
        return 0;
    }
}

function getInputValues($id = 0, $mode = 'new') {
    global $form_v;

    $db_v_names = explode(',',
        'content,pagetitle,longtitle,type,description,alias,link_attributes,isfolder,richtext,published,pub_date,unpub_date,parent,template,menuindex,searchable,cacheable,editedby,editedon,publishedon,publishedby,contentType,content_dispo,donthit,menutitle,hidemenu,introtext,createdby,createdon');
    if ($id) {
        $fields['id'] = $id;
    }
    foreach ($db_v_names as $key) {
        if (!isset($form_v[$key])) {
            $form_v[$key] = '';
        }
        $fields[$key] = $form_v[$key];
    }
    if($fields['type'] === 'reference' && !preg_match('{^[1-9][0-9]+$}', $fields['content'])) {
        $fetch_id = evo()->getIdFromUrl($fields['content']);
        if ($fetch_id) {
            $fields['content'] = $fetch_id;
        }
    }
    
    $fields['editedby'] = evo()->getLoginUserID();
    if ($mode === 'new') {
        $fields['createdon'] = request_time();
        $fields['createdby'] = evo()->getLoginUserID();
        $fields['publishedon'] = checkPublishedon(0);
    } elseif ($mode === 'edit') {
        unset($fields['createdby']);
        unset($fields['createdon']);
    }
    return $fields;
}

function checkStartDoc($id, $published, $pub_date, $unpub_date) {
    if ($published == 0) {
        evo()->webAlertAndQuit(
            'Document is linked to site_start variable and cannot be unpublished!'
            , sprintf('index.php?a=27&id=%s', $id)
        );
        exit;
    }
    if ($pub_date > request_time() || $unpub_date) {
        evo()->webAlertAndQuit(
            'Document is linked to site_start variable and cannot have publish or unpublish dates set!'
            , sprintf('index.php?a=27&id=%s', $id)
        );
    }
}

function checkFolderStatus($id) {
    global $form_v;

    $isfolder = $form_v['isfolder'];
    // check to see document is a folder
    $rs = db()->select('COUNT(id) AS count', '[+prefix+]site_content', "parent='{$id}'");
    if ($rs) {
        $row = db()->getRow($rs);
        if ($row['count'] > 0) {
            $isfolder = '1';
        }
    } else {
        evo()->webAlertAndQuit("An error occured while attempting to find the document's children.");
    }
    return $isfolder;
}

// keep original publish state, if change is not permitted
function getPublishPermission($field_name, $db_v) {
    global $form_v;
    if (!evo()->hasPermission('publish_document')) {
        return $db_v[$field_name];
    }
    return $form_v[$field_name];
}

function checkPublished($db_v) {
    return getPublishPermission('published', $db_v);
}

function checkPub_date($db_v) {
    return getPublishPermission('pub_date', $db_v);
}

function checkUnpub_date($db_v) {
    return getPublishPermission('unpub_date', $db_v);
}

function checkPublishedon($timestamp) {
    global $form_v;

    if (!evo()->hasPermission('publish_document')) {
        return $timestamp;
    }

    if ($form_v['published'] && $form_v['pub_date'] && $form_v['pub_date'] <= request_time()) {
        return $form_v['pub_date'];
    }

    if (0 < $timestamp && $form_v['published']) {
        return $timestamp;
    }

    if (!$form_v['published']) {
        return 0;
    }

    return request_time();
}

function checkPublishedby($db_v) {
    global $form_v;

    if (!evo()->hasPermission('publish_document')) {
        return $db_v['publishedon'];
    }

    // if it was changed from unpublished to published
    if ($form_v['published'] && $form_v['pub_date'] <= request_time()) {
        return $db_v['publishedby'];
    }

    if (0 < $db_v['publishedon'] && $form_v['published']) {
        return $db_v['publishedby'];
    }

    if (!$form_v['published']) {
        return 0;
    }

    return evo()->getLoginUserID();
}

function getExistsValues($id) {
    $row = db()->getRow(
        db()->select('*', '[+prefix+]site_content', sprintf("id='%s'", $id))
    );
    if (!$row) {
        evo()->webAlertAndQuit(
            "An error occured while attempting to find the document's current parent."
            , sprintf('index.php?a=27&id=%s', $id)
        );
    }
    return $row;
}

function insert_tmplvars($docid, $tmplvars) {
    if (!$tmplvars) {
        return;
    }
    $tvChanges = array();
    $tv['contentid'] = $docid;
    foreach ($tmplvars as $tmplvarid => $value) {
        if ($value !== false) {
            $tv['tmplvarid'] = $tmplvarid;
            $tv['value'] = $value;
            $tvChanges[] = $tv;
        }
    }
    if ($tvChanges) {
        foreach ($tvChanges as $tv) {
            $tv = db()->escape($tv);
            db()->insert($tv, '[+prefix+]site_tmplvar_contentvalues');
        }
    }
}

function update_tmplvars($docid, $tmplvars) {
    if (!$tmplvars) {
        return;
    }
    $tvChanges = array();
    $tvAdded = array();
    $tvDeletions = array();
    $rs = db()->select(
        'id, tmplvarid'
        , '[+prefix+]site_tmplvar_contentvalues'
        , sprintf("contentid='%s'", $docid)
    );
    $tvIds = array();
    while ($row = db()->getRow($rs)) {
        $tvIds[$row['tmplvarid']] = $row['id'];
    }
    $tv['contentid'] = $docid;
    foreach ($tmplvars as $tmplvarid => $value) {
        if ($value === false) {
            if (isset($tvIds[$tmplvarid])) {
                $tvDeletions[] = $tvIds[$tmplvarid];
            }
        } else {
            $tv['tmplvarid'] = $tmplvarid;
            $tv['value'] = $value;
            if (isset($tvIds[$tmplvarid])) {
                $tvChanges[] = $tv;
            } else {
                $tvAdded[] = $tv;
            }
        }
    }

    if ($tvDeletions) {
        $where = 'id IN(' . join(',', $tvDeletions) . ')';
        db()->delete('[+prefix+]site_tmplvar_contentvalues', $where);
    }
    if ($tvAdded) {
        foreach ($tvAdded as $tv) {
            $tv = db()->escape($tv);
            db()->insert($tv, '[+prefix+]site_tmplvar_contentvalues');
        }
    }

    if ($tvChanges) {
        foreach ($tvChanges as $tv) {
            $tv = db()->escape($tv);
            $tvid = $tv['tmplvarid'];
            db()->update(
                $tv
                , '[+prefix+]site_tmplvar_contentvalues'
                , sprintf(
                    "tmplvarid='%s' AND contentid='%s'"
                    , $tvid
                    , $docid
                )
            );
        }
    }
}

// document access permissions
function setDocPermissionsNew($document_groups, $newid) {
    global $form_v;
    $parent = $form_v['parent'];
    $tbl_document_groups = evo()->getFullTableName('document_groups');

    $docgrp_save_attempt = false;
    if (evo()->config('use_udperms') == 1 && is_array($document_groups)) {
        $new_groups = array();
        // first, split the pair (this is a new document, so ignore the second value
        foreach ($document_groups as $value_pair) {
            $group = (int)substr($value_pair, 0, strpos($value_pair, ','));
            $new_groups[] = sprintf('(%s,%s)', $group, $newid);
        }
        $saved = true;
        if ($new_groups) {
            $rs = db()->query(
                sprintf(
                    'INSERT INTO %s (document_group, document) VALUES %s'
                    , $tbl_document_groups
                    , implode(',', $new_groups)
                )
            );
            if (!$rs) {
                $saved = false;
            }
            $docgrp_save_attempt = true;
        }
    } else {
        // inherit document access permissions
        if (evo()->config('use_udperms') == 1 && isPublic() && $parent) {
            $sql = sprintf(
                "INSERT INTO %s (document_group, document) SELECT document_group, %s FROM %s WHERE document='%s'"
                , $tbl_document_groups
                , $newid
                , $tbl_document_groups
                , $parent
            );
            $saved = db()->query($sql);
            $docgrp_save_attempt = true;
        }
    }
    if ($docgrp_save_attempt && !$saved) {
        $msg = 'An error occured while attempting to add the document to a document_group.';
        evo()->webAlertAndQuit($msg);
    }
}

function isPublic() {
    return !evo()->hasPermission('access_permissions') && !evo()->hasPermission('web_access_permissions');
}

// update parent folder status
function updateParentStatus($parent) {
    if (!$parent) {
        return;
    }

    $rs = db()->update(
        'isfolder=1'
        , '[+prefix+]site_content'
        , sprintf("id='%s'", $parent)
    );
    if (!$rs) {
        evo()->webAlertAndQuit(
            "An error occured while attempting to change the document's parent to a folder."
        );
    }
}

// redirect/stay options
function goNextAction($id, $parent, $next, $type) {
    if ($next === 'new') {
        if ($type === 'document') {
            header(
                sprintf('Location: index.php?a=4&pid=%s&r=1&stay=new', $parent)
            );
            return;
        }
        header(
            sprintf('Location: index.php?a=72&pid=%s&r=1&stay=new', $parent)
        );
        return;
    }
    if ($next === 'stay') {
        header(
            sprintf('Location: index.php?a=27&id=%s&r=1&stay=stay', $id)
        );
        return;
    }
    if ($parent) {
        header(
            sprintf('Location: index.php?a=120&id=%s&r=1', $parent)
        );
        return;
    }
    header("Location: index.php?a=3&id=" . $id . "&r=1");
}

function setDocPermissionsEdit($document_groups, $id) {
    if (evo()->config('use_udperms') != 1 || !is_array($document_groups)) {
        return;
    }

    // grab the current set of permissions on this document the user can access
    $rs = db()->select(
        'groups.id, groups.document_group'
        , array(
            '[+prefix+]document_groups AS `groups`',
            'LEFT JOIN [+prefix+]documentgroup_names AS dgn ON dgn.id=`groups`.document_group'
        )
        , sprintf(
            "((1=%s AND dgn.private_memgroup) OR (1=%s AND dgn.private_webgroup)) AND groups.document='%s'"
            , (int)evo()->hasPermission('access_permissions')
            , (int)evo()->hasPermission('web_access_permissions')
            , $id
        )
    );
    $exists_groups = array();
    while ($row = db()->getRow($rs)) {
        $exists_groups[$row['document_group']] = $row['id'];
    }
    // update the permissions in the database
    $new_groups = array();
    // process the new input
    foreach ($document_groups as $value_pair) {
        list($group, $link_id) = explode(',', $value_pair);
        $new_groups[$group] = $link_id;
    }
    $insertions = array();
    foreach ($new_groups as $group_id => $link_id) {
        $group_id = (int)$group_id;
        if (isset($exists_groups[$group_id])) {
            unset($exists_groups[$group_id]);
            continue;
        }
        if ($link_id === 'new') {
            $insertions[] = sprintf('(%s,%s)', $group_id, $id);
        }
    }
    $saved = true;
    if ($insertions) {
        $sql_insert = sprintf(
            'INSERT INTO %s (document_group, document) VALUES %s'
            , evo()->getFullTableName('document_groups')
            , implode(',', $insertions)
        );
        $rs = db()->query($sql_insert);
        if (!$rs) {
            $saved = false;
        }
    }
    if ($exists_groups) {
        $rs = db()->delete(
            '[+prefix+]document_groups'
            , sprintf('id IN (%s)', implode(',', $exists_groups))
        );
        if (!$rs) {
            $saved = false;
        }
    }
    // necessary to remove all permissions as document is public
    if (postv('chkalldocs') === 'on') {
        $rs = db()->delete(
            '[+prefix+]document_groups'
            , sprintf("document='%s'", $id)
        );
        if (!$rs) {
            $saved = false;
        }
    }
    if (!$saved) {
        evo()->webAlertAndQuit('An error occured while saving document groups.');
    }
}

function folder2doc($parent) {
    $rs = db()->select(
        'COUNT(id) as total'
        , '[+prefix+]site_content'
        , "parent=" . $parent
    );
    if (!$rs) {
        echo "An error occured while attempting to find the old parents' children.";
    }
    $row = db()->getRow($rs);
    if (!$row['total']) {
        $rs = db()->update(
            'isfolder = 0'
            , '[+prefix+]site_content'
            , sprintf("id='%s'", $parent)
        );
        if (!$rs) {
            echo 'An error occured while attempting to change the old parent to a regular document.';
        }
    }
}

function getDocGroups() {
    if (postv('chkalldocs') === 'on') {
        return array();
    }
    return postv('docgroups', array());
}

function mode() {
    if (postv('mode') == 27) {
        return 'edit';
    }
    return 'new';
}