<?php
// 129
if (!isset($modx) || !$modx->isLoggedin()) {
    exit;
}

if (!$modx->hasPermission('save_document')) {
    $e->setError(3);
    $e->dumpError();
}

if (preg_match('@^[1-9][0-9]*$@', postv('id', 0))) {
    $docid = post('id');
} elseif (preg_match('@^[1-9][0-9]*$@', getv('id', 0))) {
    $docid = getv('id');
} else {
    $e->setError(2);
    $e->dumpError();
}

$modx->loadExtension('REVISION');
$modx->loadExtension('DocAPI');

if (postv('publishoption') === 'reserve' && postv('pub_date')) {
    $pub_date = evo()->toTimeStamp(postv('pub_date'));
    if (serverv('REQUEST_TIME') < $pub_date) {
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


function setStandBy($docid, $pub_date) {
    db()->update(
        array(
            'pub_date' => $pub_date,
            'status' => 'standby'
        )
        , '[+prefix+]site_revision'
        , sprintf("elmid='%s'", $docid)
    );
    return 'set_standby';
}

function publishDraft($docid) {
    $rs = db()->select('*', '[+prefix+]site_content', "id='{$docid}'");
    $documentObject = db()->getRow($rs);
    $draft = evo()->revision->getDraft($docid);
    $draft['published'] = $documentObject['published'];
    evo()->doc->update($draft, $docid);
    db()->delete('[+prefix+]site_revision', "( status='draft' OR status='standby' ) AND elmid='{$docid}'");

    evo()->clearCache();
    $tmp = array('docid' => $docid, 'type' => 'draftManual');
    evo()->invokeEvent('OnDocPublished', $tmp); // invoke OnDocPublished  event

    return 'publish_draft';
}
