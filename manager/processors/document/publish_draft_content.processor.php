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

if (postv('publishoption') === 'reserve' && postv('pub_date')) {
    $pub_date = evo()->toTimeStamp(postv('pub_date'));
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
    $draft['published'] = $documentObject['published'];
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
