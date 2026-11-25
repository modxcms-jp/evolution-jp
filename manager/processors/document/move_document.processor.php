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
$rawIds = request_strvar('id');
if (trim($rawIds) === '') {
    alert()->setError(601);
    alert()->dumpError();
}
$doc_ids = [];
foreach (explode(',', $rawIds) as $v) {
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

// 移動先。ルート（0）も正しい値なので "0" は受け付け、整数として読めない値は移動先なしとして扱う
$new_parent = request_uintvar('new_parent');
if ($new_parent === null) {
    alert()->setError(602);
    alert()->dumpError();
}
// 自分自身・子孫・存在しないリソースへは移動しない（複数移動ではすべてのリソースについて確かめる）
$invalid_parent = manager()->invalidParentMessage($doc_ids, $new_parent);
if ($invalid_parent !== '') {
    alertAndQuit($invalid_parent, $doc_ids[0]);
}

// 移動するリソースそれぞれについても、操作できるかを確かめる
if (config('use_udperms') == 1) {
    foreach ($doc_ids as $v) {
        if (!evo()->checkPermissions($v)) {
            accessDenied('access_permission_denied');
        }
    }
}

// 許可するリソースの範囲の外の文書と、範囲の外への移動は止める
foreach ($doc_ids as $v) {
    if (!manager()->isInAllowedParents($v)) {
        accessDenied('access_permission_denied');
    }
}
if (!manager()->isInAllowedParents($new_parent)) {
    accessDenied('access_permission_parent_denied');
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
if (config('use_udperms') == 1 && $current_parent != $new_parent) {
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

// 先に全件のエイリアスの重なりを確かめる。1 件でも重なれば、何も変えずに止める
$duplicates = findDuplicateAliases($doc_ids, $new_parent);
if ($duplicates) {
    $messages = [];
    foreach ($duplicates as $d) {
        $messages[] = sprintf(lang('duplicate_alias_found'), $d['target_id'], $d['alias']);
    }
    $editUrl = EvoFormFlow::url(['a' => EvoFormFlow::screen('document', 'edit'), 'id' => $duplicates[0]['doc_id']]);
    evo()->webAlertAndQuit(
        implode("\n", $messages),
        "javascript:parent.tree.ca='open';window.location.href=" . js_literal($editUrl) . ';'
    );
    exit;
}

// 移動先を先にフォルダーにしておく（あとの更新が途中で止まっても、子が入った親が isfolder=0 のままにならない）
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

// 移動する文書の元の親（移動の後に、子が残らない親の isfolder を直す）
$old_parents = [];
$rs = db()->select(
    'DISTINCT parent',
    '[+prefix+]site_content',
    sprintf('id IN (%s)', implode(',', $doc_ids))
);
if (!$rs) {
    alertAndQuit(
        "An error occured while attempting to find the resources' current parents.",
        $doc_id
    );
    exit;
}
while ($row = db()->getRow($rs)) {
    $old_parents[] = (int) $row['parent'];
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
foreach ($doc_ids as $v) {
    update_parentid($v, $new_parent, $user_id, $menuindex);
    $menuindex++;
}

// finished moving the resource, now check to see if the old_parent should no longer be a folder.
foreach ($old_parents as $old_parent) {
    $rs = db()->select(
        'count(*) as count',
        '[+prefix+]site_content',
        "parent='" . $old_parent . "'"
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
            sprintf("id='%s'", $old_parent)
        );
        if (!$rs) {
            alertAndQuit(
                'An error occured while attempting to change the old parent to a regular resource.',
                $doc_id
            );
            exit;
        }
    }
}

evo()->clearCache();

if ($new_parent !== 0) {
    header(
        sprintf(
            'Location: index.php?a=120&id=%s&refresh=tree',
            $current_parent
        )
    );
    exit;
}

header("Location: index.php?a=2&refresh=tree");

exit;


function accessDenied($lang_key)
{
    include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
    ?>
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

// 移動先で同じエイリアスになる文書を返す（移動先に既にある文書との重なりと、移動する文書どうしの重なり）
function findDuplicateAliases($doc_ids, $new_parent)
{
    $moving_ids = implode(',', $doc_ids);
    if (evo()->config('allow_duplicate_alias')) {
        return [];
    }
    $duplicates = [];
    $moving = []; // 移動する文書で、先に出てきたもののエイリアス => id
    foreach ($doc_ids as $doc_id) {
        $rs = db()->select("IF(alias='', id, alias) AS alias", '[+prefix+]site_content', "id='{$doc_id}'");
        $alias = (string) db()->getValue($rs);
        $alias_sql = db()->escape($alias);
        $rs = db()->select('id', '[+prefix+]site_content',
            "parent='{$new_parent}' AND id NOT IN ({$moving_ids}) AND (alias='{$alias_sql}' OR (alias='' AND id='{$alias_sql}'))");
        if (0 < db()->count($rs)) {
            $duplicates[] = ['doc_id' => $doc_id, 'target_id' => db()->getValue($rs), 'alias' => $alias];
            continue;
        }
        $key = mb_strtolower($alias);
        if (isset($moving[$key])) {
            $duplicates[] = ['doc_id' => $doc_id, 'target_id' => $moving[$key], 'alias' => $alias];
            continue;
        }
        $moving[$key] = $doc_id;
    }
    return $duplicates;
}

function update_parentid($doc_id, $new_parent, $user_id, $menuindex)
{
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
