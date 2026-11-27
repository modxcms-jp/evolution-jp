<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('empty_trash')) {
    alert()->setError(3);
    alert()->dumpError();
}

// 消す処理は DocAPI の purge()。ここは、リクエストの受け取り・権限の確かめ・キャッシュの消去・戻り先だけを持つ
evo()->loadExtension('DocAPI');

$stop = function ($code, $message = '') {
    alert()->setError($code, $message);
    alert()->dumpError();
    exit;
};

// id があればその 1 件とその子孫、無ければゴミ箱の全件。id が整数として読めないときは、全件の削除に進まないよう止める
$single = anyv('id') !== null && anyv('id') !== '';
if ($single) {
    $id = request_intvar('id');
    if (!$id) {
        $stop(2);
    }
    // 1 件の完全な削除（右クリックメニューの項目）は、メニューの定義と同じ権限を求める
    include_once(MODX_CORE_PATH . 'controls/document_menu.class.php');
    if (!DocumentMenu::permitted('64')) {
        $stop(3);
    }
    // 権限は、リソースの状態（有無・ゴミ箱にあるか・子孫）を知らせる前に確かめる。
    // 許可するリソースの範囲は対象だけを見る（子孫は対象の下にある）
    if (!evo()->checkPermissions($id) || !manager()->isInAllowedParents($id)) {
        $stop(3, lang('access_permission_denied'));
    }
    $check = evo()->doc->purgeCheck($id);
    if (!$check['ids']) {
        $stop(2);
    }
    foreach (array_slice($check['ids'], 1) as $childId) {
        if (!evo()->checkPermissions($childId)) {
            $stop(3, sprintf(lang('cannot_purge_child_no_permission'), $childId));
        }
    }
    if ($check['warning'] !== '') {
        $stop(3, $check['warning']);
    }
    $ids = $check['ids'];
    include_once(MODX_CORE_PATH . 'controls/child_document_list_state.class.php');
    $returnUrl = ChildDocumentListState::returnUrl($check['parent']);
} else {
    $ids = evo()->doc->trashedIds();
    // 1 件でも通らなければ、何も消さずに全体を止める
    foreach ($ids as $docid) {
        if (!evo()->checkPermissions($docid) || !manager()->isInAllowedParents($docid)) {
            $stop(3, lang('access_permission_denied'));
        }
    }
    $returnUrl = 'index.php?refresh=tree&a=7';
}

if (!evo()->doc->purge($ids)) {
    exit("Something went wrong while trying to remove deleted documents!");
}
evo()->clearCache();
header('Location: ' . $returnUrl);
