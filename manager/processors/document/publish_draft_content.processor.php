<?php
// 129
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('save_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

$docid = request_intvar('id');
if (!$docid) {
    alert()->setError(2);
    alert()->dumpError();
}
if (!evo()->checkPermissions($docid)) {
    alert()->setError(3);
    alert()->dumpError();
}
if (!manager()->isInAllowedParents($docid)) {
    alert()->setError(3, lang('access_permission_denied'));
    alert()->dumpError();
}

evo()->loadExtension('REVISION');

$draft = evo()->revision->getDraft($docid);
if ($draft) {
    $currentParent = (int)db()->getValue(
        db()->select('parent', '[+prefix+]site_content', where('id', '=', (int)$docid))
    );
    $draftParent = array_get($draft, 'parent', 0);
    if (!preg_match('@^[0-9]+$@', (string)$draftParent)) {
        alert()->setError(3, lang('access_permission_parent_denied'));
        alert()->dumpError();
    }
    if ((int)$draftParent !== $currentParent) {
        $message = manager()->parentChangeDeniedMessage([$docid], $draftParent);
        if ($message) {
            alert()->setError(3, $message);
            alert()->dumpError();
        }
    }
}

// 空白だけの日付は空の日付（即時公開）として扱う
$reserveDate = trim(post_strvar('pub_date'));
if (postv('publishoption') === 'reserve' && $reserveDate !== '') {
    $pub_date = evo()->toTimeStamp($reserveDate);
    if ($pub_date === '') {
        // 解釈できない日付で即時に公開しない
        manager()->saveFormValues(133);
        evo()->webAlertAndQuit(
            lang('mgrlog_dateinvalid'),
            sprintf('index.php?a=133&id=%s', $docid)
        );
        exit;
    }
    if (request_time() < $pub_date) {
        setStandBy($docid, $pub_date);
        evo()->setCacheRefreshTime($pub_date);
    } else {
        publishDraft($docid);
    }
} else {
    publishDraft($docid);
}

evo()->clearCache();
$header = sprintf('Location: index.php?a=3&id=%s&r=1', $docid);
header($header);


function setStandBy($docid, $pub_date)
{
    db()->update(
        [
            'pub_date' => $pub_date,
            'status' => 'standby'
        ],
        '[+prefix+]site_revision',
        sprintf("elmid='%s'", $docid)
    );
    return 'set_standby';
}

function publishDraft($docid)
{
    evo()->loadExtension('DocAPI');
    $rs = db()->select(
        '*',
        '[+prefix+]site_content',
        sprintf("id='%s'", $docid)
    );
    $documentObject = db()->getRow($rs);
    $draft = evo()->revision->getDraft($docid);
    // 今の文書の公開の状態を起点に、リソースの保存と同じ規則（DocAPI::setPubStatus()）で
    // 下書きの公開日・公開終了日から published を決める。公開の権限が無いときは、published だけ今の値を引き継ぐ。
    // 下書きが無いときは日付を 0 で上書きしないよう、規則をかけない
    $hasDraft = !empty($draft);
    $draft['published'] = $documentObject['published'];
    if ($hasDraft) {
        $ruled = evo()->doc->setPubStatus($draft);
        if (evo()->hasPermission('publish_document')) {
            $draft = $ruled;
        }
        // トップページは、通常の保存と同じく非公開にも予約にもしない（文書も下書きも変えずに戻す）
        if ((int)$docid === (int)evo()->config('site_start')) {
            $error = evo()->doc->startDocPubError(
                $draft['published'],
                $ruled['pub_date'],
                $ruled['unpub_date']
            );
            if ($error !== '') {
                evo()->webAlertAndQuit($error, sprintf('index.php?a=131&id=%s', $docid));
                exit;
            }
        }
    }
    evo()->doc->update($draft, $docid);
    db()->delete(
        '[+prefix+]site_revision',
        sprintf(
            "( status='draft' OR status='standby' ) AND elmid='%s'",
            $docid
        )
    );

    evo()->clearCache();
    $tmp = ['docid' => $docid, 'type' => 'draftManual'];
    evo()->invokeEvent('OnDocPublished', $tmp); // invoke OnDocPublished  event

    return 'publish_draft';
}
