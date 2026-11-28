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
// 権限のエラーや「新しい親を選んでください」より先に、自分自身・子孫のエラーを出すため、親が変わるかに関わらずここで確かめる
$invalid_parent = manager()->invalidParentMessage($doc_ids, $new_parent);
if ($invalid_parent !== '') {
    alertAndQuit($invalid_parent, $doc_ids);
}

// 移動するリソースそれぞれについても、操作できるかを確かめる
if (config('use_udperms') == 1) {
    foreach ($doc_ids as $v) {
        if (!evo()->checkPermissions($v)) {
            accessDenied('access_permission_denied');
        }
    }
}

// 許可するリソースの範囲の外の文書は止める（移動先の確認は、親が変わるときに下でまとめて行う）
foreach ($doc_ids as $v) {
    if (!manager()->isInAllowedParents($v)) {
        accessDenied('access_permission_denied');
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

// 親が変わるときは、移動先に置けるかを確かめる
// （自分自身・子孫・存在しない親、ルートに置けるか・文書グループ、許可するリソースの範囲。複数移動では全件）
if ($current_parent != $new_parent) {
    $denied = manager()->parentChangeDeniedMessage($doc_ids, $new_parent);
    if ($denied !== '') {
        alertAndQuit($denied, $doc_ids);
    }
}

if ($current_parent == $new_parent) {
    alertAndQuit(
        lang('move_resource_new_parent'),
        $doc_ids
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
        $doc_ids
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
        $doc_ids
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
            $doc_ids
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
                $doc_ids
            );
            exit;
        }
    }
}

evo()->clearCache();

manager()->notice(lang('notice_moved'));
if ($new_parent !== 0) {
    include_once MODX_CORE_PATH . 'controls/child_document_list_state.class.php';
    header('Location: ' . ChildDocumentListState::returnUrl((int) $current_parent));
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

// 断ったあとは、移動の画面に戻す。複数のときは対象を全件引き継ぐ（1 件は id、複数は batch[]。画面の受け取りと同じ）
function alertAndQuit($string, array $docIds)
{
    $docIds = array_map('intval', $docIds);
    if (count($docIds) === 1) {
        $query = 'id=' . $docIds[0];
    } else {
        $query = 'batch[]=' . implode('&batch[]=', $docIds);
    }
    evo()->webAlertAndQuit(
        $string,
        "javascript:parent.tree.ca='open';window.location.href='index.php?a=51&" . $query . "';"
    );
    exit;
}

// 移動先で同じエイリアスになる文書を返す。移動先にすでにある文書との重なりと、移動する文書どうしの重なりを、
// DB の照合順序で 1 度に見る。移動する文書どうしの組は、先に出てきた文書を相手として、あとの文書だけを返す
function findDuplicateAliases($doc_ids, $new_parent)
{
    if (evo()->config('allow_duplicate_alias')) {
        return [];
    }
    $moving_ids = implode(',', $doc_ids);
    $duplicates = [];
    foreach ($doc_ids as $i => $doc_id) {
        $alias = (string) db()->getValue(
            db()->select("IF(alias='', id, alias) AS alias", '[+prefix+]site_content', "id='{$doc_id}'")
        );
        $alias_sql = db()->escape($alias);
        $rs = db()->select(
            'id',
            '[+prefix+]site_content',
            "((parent='{$new_parent}' AND id NOT IN ({$moving_ids})) OR (id IN ({$moving_ids}) AND id<>'{$doc_id}'))"
            . " AND (alias='{$alias_sql}' OR (alias='' AND id='{$alias_sql}'))"
        );
        while ($row = db()->getRow($rs)) {
            $pos = array_search((int) $row['id'], $doc_ids, true);
            if ($pos !== false && $i < $pos) {
                continue; // 組の相手があとに出てくる文書は、その文書のほうで返す
            }
            $duplicates[] = ['doc_id' => $doc_id, 'target_id' => $row['id'], 'alias' => $alias];
            break;
        }
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
