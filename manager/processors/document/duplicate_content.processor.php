<?php
if (!isset($modx) || !$modx->isLoggedin()) {
    exit;
}
if (!$modx->hasPermission('new_document') || !$modx->hasPermission('save_document')) {
    $e->setError(3);
    $e->dumpError();
}

// check the document doesn't have any children
$id = $_GET['id'];
$children = array();

// check permissions on the document
if (!$modx->checkPermissions($id, true)) {
    include(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
    <div class="sectionHeader"><?php echo $_lang['access_permissions']; ?></div>
    <div class="sectionBody">
    <p><?php echo $_lang['access_permission_denied']; ?></p>
    <?php
    include(MODX_MANAGER_PATH . 'actions/footer.inc.php');
    exit;
}

// Run the duplicator
$modx->loadExtension('DocAPI');
$newid = duplicateDocument($id);
$modx->clearCache();


// finish cloning - redirect
$pid = $modx->db->getValue($modx->db->select('parent', '[+prefix+]site_content', "id='{$newid}'"));
if ($pid == 0) {
    $header = "Location: index.php?r=1&a=3&id={$newid}";
} else {
    $header = "Location: index.php?r=1&a=120&id={$pid}";
}
header($header);


function duplicateDocument($docid, $parent = null, $_toplevel = 0, $reset_alias = true) {
    global $modx, $_lang;

    // invoke OnBeforeDocDuplicate event
    $tmp = array(
        'id' => $docid
    );
    $evtOut = $modx->invokeEvent('OnBeforeDocDuplicate', $tmp);

    // if( !in_array( 'false', array_values( $evtOut ) ) ){}
    // TODO: Determine necessary handling for duplicateDocument "return $newparent" if OnBeforeDocDuplicate were able to conditially control duplication
    // [DISABLED]: Proceed with duplicateDocument if OnBeforeDocDuplicate did not return false via: $event->output('false');

    $myChildren = array();
    $userID = $modx->getLoginUserID();

    // Grab the original document
    $rs = $modx->db->select('*', '[+prefix+]site_content', "id='{$docid}'");
    $content = $modx->db->getRow($rs);

    // Once we've grabbed the document object, start doing some modifications
    if ($_toplevel == 0 && $reset_alias === true) {
        $content['pagetitle'] = str_replace('[+title+]', $content['pagetitle'], $_lang['duplicate_title_string']);
        $content['alias'] = null;
    } elseif (($modx->config['friendly_urls'] == 0 || $modx->config['allow_duplicate_alias'] == 0) && $reset_alias === true) {
        $content['alias'] = null;
    }

    // change the parent accordingly
    if ($parent !== null) {
        $content['parent'] = $parent;
    }

    // Change the author
    $content['createdby'] = $userID;
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

    // Escape the proper strings
    $content['pagetitle'] = $modx->db->escape($content['pagetitle']);
    $content['longtitle'] = $modx->db->escape($content['longtitle']);
    $content['description'] = $modx->db->escape($content['description']);
    $content['introtext'] = $modx->db->escape($content['introtext']);
    $content['content'] = $modx->db->escape($content['content']);
    $content['menutitle'] = $modx->db->escape($content['menutitle']);

    // increase menu index
    if ($_toplevel == 0 && $modx->config['auto_menuindex'] === '1') {
        $pid = (int)$content['parent'];
        $content['menuindex'] = $modx->db->getValue($modx->db->select('max(menuindex)', '[+prefix+]site_content',
                "parent='{$pid}'")) + 1;
    }

    // Duplicate the Document
    $new_id = $modx->doc->getNewDocID();
    if (!empty($new_id)) {
        $content['id'] = $new_id;
    } else {
        unset($content['id']);
    }

    $new_id = $modx->db->insert($content, '[+prefix+]site_content');

    duplicateTVs($docid, $new_id);
    duplicateAccess($docid, $new_id);

    // invoke OnDocDuplicate event
    $tmp = array(
        'id' => $docid,
        'new_id' => $new_id
    );
    $evtOut = $modx->invokeEvent('OnDocDuplicate', $tmp);

    // Start duplicating all the child documents that aren't deleted.
    $rs = $modx->db->select('id', '[+prefix+]site_content', "parent='{$docid}' AND deleted=0", 'id ASC');
    if ($modx->db->getRecordCount($rs)) {
        $_toplevel++;
        while ($row = $modx->db->getRow($rs)) {
            duplicateDocument($row['id'], $new_id, $_toplevel, $reset_alias === false);
        }
    }

    // return the new doc id
    return $new_id;
}

// Duplicate Document TVs
function duplicateTVs($oldid, $newid) {
    global $modx;

    $tbltvc = $modx->getFullTableName('site_tmplvar_contentvalues');
    $modx->db->insert('contentid,tmplvarid,value', $tbltvc, "{$newid},tmplvarid,value", $tbltvc,
        "contentid='{$oldid}'");
}

// Duplicate Document Access Permissions
function duplicateAccess($oldid, $newid) {
    global $modx;

    $tbldg = $modx->getFullTableName('document_groups');
    $modx->db->insert('document,document_group', $tbldg, "{$newid},document_group", $tbldg, "document='{$oldid}'");
}
