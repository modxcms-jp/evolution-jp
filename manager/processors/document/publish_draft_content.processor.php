<?php
// 129
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('save_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (preg_match('@^[1-9][0-9]*$@', postv('id', 0))) {
    $docid = postv('id');
} elseif (preg_match('@^[1-9][0-9]*$@', getv('id', 0))) {
    $docid = getv('id');
} else {
    alert()->setError(2);
    alert()->dumpError();
}

evo()->loadExtension('REVISION');

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
        ]
        , '[+prefix+]site_revision'
        , sprintf("elmid='%s'", $docid)
    );
    return 'set_standby';
}

function publishDraft($docid)
{
    evo()->loadExtension('DocAPI');
    $rs = db()->select(
        '*'
        , '[+prefix+]site_content'
        , sprintf("id='%s'", $docid)
    );
    $documentObject = db()->getRow($rs);
    $draft = evo()->revision->getDraft($docid);
    $draft['published'] = $documentObject['published'];
    evo()->doc->update($draft, $docid);
    db()->delete(
        '[+prefix+]site_revision'
        , sprintf(
            "( status='draft' OR status='standby' ) AND elmid='%s'"
            , $docid
        )
    );

    evo()->clearCache();
    $tmp = ['docid' => $docid, 'type' => 'draftManual'];
    evo()->invokeEvent('OnDocPublished', $tmp); // invoke OnDocPublished  event

    return 'publish_draft';
}
