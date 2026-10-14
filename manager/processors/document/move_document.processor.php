<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('move_document')) {
    alert()->setError(3);
    alert()->dumpError();
}
if (!evo()->hasPermission('edit_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

// 移動するリソース（カンマ区切りで複数可）。一部だけを移動しないよう、1 つでも整数として読めなければ全体を止める
if (!is_string(anyv('id')) || trim(anyv('id')) === '') {
    alert()->setError(601);
    alert()->dumpError();
}
$doc_ids = [];
foreach (explode(',', anyv('id')) as $v) {
    $v = to_intvar(trim($v));
    if (!$v) {
        alert()->setError(2);
        alert()->dumpError();
    }
    if (!in_array($v, $doc_ids, true)) {
        $doc_ids[] = $v;
    }
}
$doc_id = $doc_ids[0];

// 移動先。ルート（0）も正しい値なので "0" は受け付け、それ以外の整数として読めない値は移動先なしとして扱う
$new_parent = anyv('new_parent') === '0' ? 0 : request_intvar('new_parent');
if (!$new_parent && anyv('new_parent') !== '0') {
    alert()->setError(602);
    alert()->dumpError();
}
// 自分自身・子孫・存在しないリソースへは移動しない（複数移動ではすべてのリソースについて確かめる）
$invalid_parent = manager()->invalidParentMessage($doc_ids, $new_parent);
if ($invalid_parent !== '') {
    alertAndQuit($invalid_parent, $doc_ids[0]);
}

// 移動するリソースそれぞれについても、操作できるかを確かめる
if (evo()->config['use_udperms'] == 1) {
    foreach ($doc_ids as $v) {
        if (!evo()->checkPermissions($v)) {
            accessDenied('access_permission_denied');
        }
    }
}

$rs = db()->select(
    'parent',
    '[+prefix+]site_content',
    sprintf("id='%s'", $doc_id)
);
if (!$rs) {
    exit("An error occured while attempting to find the resource's current parent.");
}
$current_parent = (int) db()->getValue($rs);

// check user has permission to move resource to chosen location
if (evo()->config['use_udperms'] == 1 && $current_parent != $new_parent) {
    if (!evo()->checkPermissions($new_parent)) {
        accessDenied('access_permission_parent_denied');
    }
}

if ($current_parent == $new_parent) {
    alertAndQuit(
        lang('move_resource_new_parent'),
        $doc_id
    );
    exit;
}

$rs = db()->update(
    'isfolder=1',
    '[+prefix+]site_content',
    "id='" . $new_parent . "'"
);
if (!$rs) {
    alertAndQuit(
        'An error occured while attempting to change the new parent to a folder.',
        $doc_id
    );
    exit;
}

// increase menu index
if (evo()->config('auto_menuindex') === null || evo()->config('auto_menuindex')) {
    $menuindex = db()->getValue(
            db()->select(
                'max(menuindex)',
                '[+prefix+]site_content',
                "parent='" . $new_parent . "'"
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
    'count(*) as count',
    '[+prefix+]site_content',
    "parent='" . $current_parent . "'"
);
if (!$rs) {
    alertAndQuit(
        "An error occured while attempting to find the old parents' children.",
        $doc_id
    );
    exit;
}

$row = db()->getRow($rs);
if (!$row['count']) {
    $rs = db()->update(
        'isfolder=0',
        '[+prefix+]site_content',
        sprintf("id='%s'", $current_parent)
    );
    if (!$rs) {
        alertAndQuit(
            'An error occured while attempting to change the old parent to a regular resource.',
            $doc_id
        );
        exit;
    }
}

evo()->clearCache();

if ($new_parent !== 0) {
    header(
        sprintf(
            'Location: index.php?a=120&id=%s&r=1',
            $current_parent
        )
    );
    exit;
}

header("Location: index.php?a=2&r=1");

exit;


function accessDenied($lang_key)
{
    include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
    <script type="text/javascript">parent.tree.ca = '';</script>
    <div class="sectionHeader"><?= lang('access_permissions') ?></div>
    <div class="sectionBody">
        <p><?= lang($lang_key) ?></p>
    </div>
    <?php
    include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
    exit;
}

function alertAndQuit($string, $docid)
{
    evo()->webAlertAndQuit(
        $string,
        sprintf(
            "javascript:parent.tree.ca='open';window.location.href='index.php?a=51&id=%s';",
            $docid
        )
    );
    exit;
}

function update_parentid($doc_id, $new_parent, $user_id, $menuindex)
{
    if (!evo()->config('allow_duplicate_alias')) {
        $rs = db()->select("IF(alias='', id, alias) AS alias", '[+prefix+]site_content', "id='{$doc_id}'");
        $alias = db()->getValue($rs);
        $alias_sql = db()->escape((string) $alias);
        $rs = db()->select('id', '[+prefix+]site_content',
            "parent='{$new_parent}' AND (alias='{$alias_sql}' OR (alias='' AND id='{$alias_sql}'))");
        $find = db()->count($rs);
        if (0 < $find) {
            $target_id = db()->getValue($rs);
            $url = "javascript:parent.tree.ca='open';window.location.href='index.php?a=27&id={$doc_id}';";
            evo()->webAlertAndQuit(sprintf(lang('duplicate_alias_found'), $target_id, $alias), $url);
            exit;
        }
    }
    $rs = db()->update(
        [
            'parent' => $new_parent,
            'editedby' => $user_id,
            'menuindex' => $menuindex
        ],
        '[+prefix+]site_content',
        sprintf("id='%s'", $doc_id)
    );
    if (!$rs) {
        exit("An error occured while attempting to move the resource to the new parent.");
    }
}
