<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('new_document') || !evo()->hasPermission('save_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

// check permissions on the document
if (!$modx->checkPermissions(getv('id'), true)) {
    include(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
    <div class="sectionHeader"><?= lang('access_permissions') ?></div>
    <div class="sectionBody">
    <p><?= lang('access_permission_denied') ?></p>
    <?php
    include(MODX_MANAGER_PATH . 'actions/footer.inc.php');
    exit;
}

// Run the duplicator
evo()->loadExtension('DocAPI');
$newid = duplicateDocument(getv('id'));
$modx->clearCache();


// finish cloning - redirect
$pid = db()->getValue(
    db()->select(
        'parent',
        '[+prefix+]site_content',
        sprintf("id='%s'", $newid)
    )
);
if ($pid == 0) {
    header("Location: index.php?r=1&a=3&id=" . $newid);
    return;
}
header("Location: index.php?r=1&a=120&id=" . $pid);


function duplicateDocument($docid, $parent = null, $_toplevel = 0, $reset_alias = true)
{
    global $_lang;

    // invoke OnBeforeDocDuplicate event
    $tmp = [
        'id' => $docid
    ];
    evo()->invokeEvent('OnBeforeDocDuplicate', $tmp);

    // Grab the original document
    $rs = db()->select(
        '*',
        '[+prefix+]site_content',
        sprintf("id='%s'", $docid)
    );
    $content = db()->getRow($rs);

    // Once we've grabbed the document object, start doing some modifications
    if ($_toplevel == 0 && $reset_alias === true) {
        $content['pagetitle'] = str_replace(
            '[+title+]',
            $content['pagetitle'],
            $_lang['duplicate_title_string']
        );
        $content['alias'] = null;
    } elseif (
        (!evo()->config('friendly_urls') || !evo()->config('allow_duplicate_alias'))
        &&
        $reset_alias === true
    ) {
        $content['alias'] = null;
    }

    // change the parent accordingly
    if ($parent !== null) {
        $content['parent'] = $parent;
    }

    // Change the author
    $content['createdby'] = evo()->getLoginUserID();
    $content['createdon'] = time();
    // Remove other modification times
    $content['editedby'] = 0;
    $content['editedon'] = 0;
    $content['deleted'] = 0;
    $content['deletedby'] = 0;
    $content['deletedon'] = 0;

    // Set the published status to unpublished by default (see above ... commit #3388)
    $content['published'] = 0;
    $content['pub_date'] = 0;
    $content['unpub_date'] = 0;
    $content['publishedon'] = 0;

    // increase menu index
    if ($_toplevel == 0 && evo()->config('auto_menuindex') === '1') {
        $content['menuindex'] = db()->getValue(
                db()->select(
                    'max(menuindex)',
                    '[+prefix+]site_content',
                    "parent='" . (int)$content['parent'] . "'"
                )
            ) + 1;
    }

    // Duplicate the Document
    $new_id = evo()->doc->getNewDocID();
    if (empty($new_id)) {
        unset($content['id']);
    } else {
        $content['id'] = $new_id;
    }

    $new_id = db()->insert(
        db()->escape($content),
        '[+prefix+]site_content'
    );

    duplicateTVs($docid, $new_id);
    duplicateAccess($docid, $new_id);

    // invoke OnDocDuplicate event
    $tmp = [
        'id' => $docid,
        'new_id' => $new_id
    ];
    evo()->invokeEvent('OnDocDuplicate', $tmp);

    // Start duplicating all the child documents that aren't deleted.
    $rs = db()->select(
        'id',
        '[+prefix+]site_content',
        "parent='" . $docid . "' AND deleted=0",
        'id ASC'
    );
    if (db()->count($rs)) {
        $_toplevel++;
        while ($row = db()->getRow($rs)) {
            duplicateDocument(
                $row['id'],
                $new_id,
                $_toplevel,
                $reset_alias === false
            );
        }
    }
    // return the new doc id
    return $new_id;
}

// Duplicate Document TVs
function duplicateTVs($oldid, $newid)
{
    $tbltvc = evo()->getFullTableName('site_tmplvar_contentvalues');
    db()->insert(
        'contentid,tmplvarid,value',
        $tbltvc,
        $newid . ",tmplvarid,value",
        $tbltvc,
        "contentid='" . $oldid . "'"
    );
}

// Duplicate Document Access Permissions
function duplicateAccess($oldid, $newid)
{
    $tbldg = evo()->getFullTableName('document_groups');
    db()->insert(
        'document,document_group',
        $tbldg,
        $newid . ",document_group",
        $tbldg,
        "document='" . $oldid . "'"
    );
}
