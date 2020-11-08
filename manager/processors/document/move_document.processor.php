<?php
if (!isset($modx) || !$modx->isLoggedin()) {
    exit;
}
if (!$modx->hasPermission('move_document')) {
    alert()->setError(3);
    alert()->dumpError();
}
if (!$modx->hasPermission('edit_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (anyv('id') == anyv('new_parent')) {
    alert()->setError(600);
    alert()->dumpError();
}
if (anyv('id') == '') {
    alert()->setError(601);
    alert()->dumpError();
}
if (anyv('new_parent') === null) {
    echo '<script type="text/javascript">parent.tree.ca = "open";</script>';
    alert()->setError(602);
    alert()->dumpError();
}

if (strpos(anyv('id'), ',')===false) {
    $doc_ids[] = anyv('id');
    $doc_id = anyv('id');
} else {
    $doc_ids = explode(',', anyv('id'));
    $doc_id = $doc_ids[0];
}

$rs = db()->select(
        'parent'
        , '[+prefix+]site_content'
        , sprintf("id='%s'", $doc_id)
);
if (!$rs) {
    exit("An error occured while attempting to find the resource's current parent.");
}
$current_parent = db()->getValue($rs);
$new_parent = (int)anyv('new_parent');

// check user has permission to move resource to chosen location
if (evo()->config['use_udperms'] == 1 && $current_parent != $new_parent) {
    if (!evo()->checkPermissions($new_parent)) {
        include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
        ?>
        <script type="text/javascript">parent.tree.ca = '';</script>
        <div class="sectionHeader"><?php echo lang('access_permissions'); ?></div>
        <div class="sectionBody">
            <p><?php echo lang('access_permission_parent_denied'); ?></p>
        </div>
        <?php
        include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
        exit;
    }
}

if ($current_parent == $new_parent) {
    alertAndQuit(
        lang('move_resource_new_parent')
        , $doc_id
    );
    exit;
}

$children = allChildren($doc_id);
if (in_array($new_parent, $children)) {
    alertAndQuit(
        lang('move_resource_cant_myself')
        , $doc_id
    );
    exit;
}

$rs = db()->update(
        'isfolder=1'
        , '[+prefix+]site_content'
        , "id='".$new_parent."'"
);
if (!$rs) {
    alertAndQuit(
        'An error occured while attempting to change the new parent to a folder.'
        , $doc_id
    );
    exit;
}

// increase menu index
if (evo()->config('auto_menuindex') === null || evo()->config('auto_menuindex')) {
    $menuindex = db()->getValue(
            db()->select(
                'max(menuindex)'
                , '[+prefix+]site_content'
                , "parent='".$new_parent."'"
            )
        ) + 1;
} else {
    $menuindex = 0;
}

$user_id = evo()->getLoginUserID();
if (is_array($doc_ids)) {
    foreach ($doc_ids as $v) {
        update_parentid($v, $new_parent, $user_id, $menuindex);
        $menuindex++;
    }
}

// finished moving the resource, now check to see if the old_parent should no longer be a folder.
$rs = db()->select(
        'count(*) as count'
        , '[+prefix+]site_content'
        , "parent='".$current_parent."'"
);
if (!$rs) {
    alertAndQuit(
        "An error occured while attempting to find the old parents' children."
        , $doc_id
    );
    exit;
}

$row = db()->getRow($rs);
if (! $row['count']) {
    $rs = db()->update(
        'isfolder=0'
        , '[+prefix+]site_content'
        , sprintf("id='%s'", $current_parent)
    );
    if (!$rs) {
        alertAndQuit(
            'An error occured while attempting to change the old parent to a regular resource.'
            , $doc_id
        );
        exit;
    }
}

evo()->clearCache();

if ($new_parent !== 0) {
    header(
        sprintf(
            'Location: index.php?a=120&id=%s&r=1'
            , $current_parent
        )
    );
    exit;
}

header("Location: index.php?a=2&r=1");

exit;


function alertAndQuit($string, $docid) {
    evo()->webAlertAndQuit(
        $string
        , sprintf(
            "javascript:parent.tree.ca='open';window.location.href='index.php?a=51&id=%s';"
            , $docid
        )
    );
    exit;
}

function allChildren($docid) {
    $children = array();
    $rs = db()->select('id', '[+prefix+]site_content', "parent='{$docid}'");
    if (!$rs) {
        exit("An error occured while attempting to find all of the resource's children.");
    }

    if ($numChildren = db()->getRecordCount($rs)) {
        while ($child = db()->getRow($rs)) {
            $children[] = $child['id'];
            $nextgen = allChildren($child['id']);
            foreach ($nextgen as $k => $v) {
                $children[$k] = $v;
            }
        }
    }
    return $children;
}

function update_parentid($doc_id, $new_parent, $user_id, $menuindex) {
    if (!evo()->config('allow_duplicate_alias')) {
        $rs = db()->select("IF(alias='', id, alias) AS alias", '[+prefix+]site_content', "id='{$doc_id}'");
        $alias = db()->getValue($rs);
        $rs = db()->select('id', '[+prefix+]site_content',
            "parent='{$new_parent}' AND (alias='{$alias}' OR (alias='' AND id='{$alias}'))");
        $find = db()->getRecordcount($rs);
        if (0 < $find) {
            $target_id = db()->getValue($rs);
            $url = "javascript:parent.tree.ca='open';window.location.href='index.php?a=27&id={$doc_id}';";
            evo()->webAlertAndQuit(sprintf(lang('duplicate_alias_found'), $target_id, $alias), $url);
            exit;
        }
    }
    $rs = db()->update(
        array(
            'parent' => $new_parent,
            'editedby' => $user_id,
            'menuindex' => $menuindex
        )
        , '[+prefix+]site_content'
        , sprintf("id='%s'", $doc_id)
    );
    if (!$rs) {
        exit("An error occured while attempting to move the resource to the new parent.");
    }
}
