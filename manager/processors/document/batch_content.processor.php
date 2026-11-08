<?php
// 一覧（a=120）でチェックしたリソースの一括の削除・公開・非公開（a=121）
// POST: batch[]（リソースの ID）、parent（一覧のリソースの ID。戻り先）。操作の種類 op（delete・publish・unpublish）は、
// 送り先の URL（一覧のボタンの formaction）のクエリで受ける。一覧の page・orderby・q も同じクエリで引き継ぐ
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

$op = request_choicevar('op', ['delete', 'publish', 'unpublish'], '');
if ($op === '') {
    alert()->setError(2);
    alert()->dumpError();
}
if ($op === 'delete') {
    $permitted = evo()->hasPermission('delete_document');
} else {
    $permitted = evo()->hasPermission('save_document') && evo()->hasPermission('publish_document');
}
if (!$permitted) {
    alert()->setError(3);
    alert()->dumpError();
}

// 一部だけを操作しないよう、1 つでも整数として読めなければ全体を止める
$raw = postv('batch');
if (!is_array($raw) || !$raw) {
    alert()->setError(2);
    alert()->dumpError();
}
$ids = [];
foreach ($raw as $v) {
    $v = to_intvar($v);
    if (!$v) {
        alert()->setError(2);
        alert()->dumpError();
    }
    if (!in_array($v, $ids, true)) {
        $ids[] = $v;
    }
}

include_once(MODX_CORE_PATH . 'controls/child_document_list_state.class.php');
include_once(__DIR__ . '/document_state.functions.php');

$returnUrl = ChildDocumentListState::returnUrl(post_uintvar('parent', 0));

// 先に全件を確かめる。1 件でも操作できなければ、何も変えずに文書の ID と理由を示して止める
$problems = [];
$children = [];
foreach ($ids as $docid) {
    $title = db()->getValue(db()->select('pagetitle', '[+prefix+]site_content', "id='" . $docid . "'"));
    if ($title === false || $title === null) {
        $problems[] = sprintf('ID:%d %s', $docid, lang('access_permission_denied'));
        continue;
    }
    if ($op === 'delete') {
        $check = docDeleteCheck($docid);
        $reason = $check['warning'];
        $children[$docid] = $check['children'];
    } else {
        $key = docPublishCheck($docid, $op === 'publish');
        $reason = $key !== '' ? lang($key) : '';
    }
    if ($reason !== '') {
        $problems[] = sprintf('ID:%d (%s) %s', $docid, $title, $reason);
    }
}
if ($problems) {
    evo()->webAlertAndQuit(implode("\n", $problems), $returnUrl);
}

// 確かめたあとは 1 件ずつ更新してイベントを発火し、キャッシュの消去は最後に 1 回だけ行う
$done = 0;
foreach ($ids as $docid) {
    if ($op === 'delete') {
        $interrupted = docDeleteExecute($docid, $children[$docid]);
        if ($interrupted !== '') {
            if ($done) {
                evo()->clearCache();
            }
            evo()->webAlertAndQuit(sprintf('ID:%d %s (%d / %d)', $docid, $interrupted, $done, count($ids)), $returnUrl);
        }
    } elseif ($op === 'publish') {
        docPublishExecute($docid);
    } else {
        docUnpublishExecute($docid);
    }
    $done++;
}

evo()->clearCache();
header('Location: ' . $returnUrl);
