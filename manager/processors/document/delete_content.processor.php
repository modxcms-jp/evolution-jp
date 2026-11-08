<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('delete_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

$id = get_intvar('id');

if (!$id) {
    alert()->setError(2);
    alert()->dumpError();
}

include_once(__DIR__ . '/document_state.functions.php');

// 文書・リンク・子孫（サイト開始ページ・エラーページ・サイト停止中のページ）を確かめる
$check = docDeleteCheck($id);
if ($check['warning'] !== '') {
    evo()->webAlertAndQuit($check['warning'], 'javascript:history.back();');
}

$warning = docDeleteExecute($id, $check['children']);
if ($warning !== '') {
    evo()->webAlertAndQuit($warning);
}

// empty cache
evo()->clearCache();
$pid = db()->getValue(db()->select('parent', '[+prefix+]site_content', "id='{$id}'"));
include_once(MODX_CORE_PATH . 'controls/child_document_list_state.class.php');
if ($pid !== '0') {
    $url = ChildDocumentListState::returnUrl((int) $pid);
} else {
    $url = 'index.php?a=2&r=1';
}
header("Location: {$url}");
