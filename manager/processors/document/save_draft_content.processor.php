<?php
// 128
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!hasPermission('save_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (!preg_match('@^[1-9][0-9]*$@', postv('id'))) {
    alert()->setError(2);
    alert()->dumpError();
    return;
}

manager()->saveFormValues(4);

evo()->loadExtension('REVISION');
evo()->loadExtension('DocAPI');

$fields = $modx->doc->fixPubStatus(
    $modx->doc->fixTvNest($_POST)
);

if (postv('stay') === 'save_standby') {
    $rs = $modx->revision->save(postv('id'), $fields, 'standby');
} else {
    $rs = $modx->revision->save(postv('id'), $fields, 'draft');
}

if (postv('stay') === 'new') {
    header(
        sprintf('Location: index.php?a=131&id=%s&r=1', postv('id'))
    );
    return;
}
if (postv('stay') === 'stay') {
    header(
        sprintf('Location: index.php?a=131&id=%s&stay=stay', postv('id'))
    );
    return;
}
if (in_array(postv('stay'), ['save_draft', 'save_standby'])) {
    header(
        sprintf('Location: index.php?a=133&id=%s&r=1', postv('id'))
    );
    return;
}

header(sprintf('Location: index.php?a=3&id=%s&r=1', postv('id')));
