<?php
// 128
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!hasPermission('save_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

$id = post_intvar('id');
if (!$id) {
    alert()->setError(2);
    alert()->dumpError();
    return;
}
if (!evo()->checkPermissions($id)) {
    alert()->setError(3);
    alert()->dumpError();
    return;
}
if (!manager()->isInAllowedParents($id)) {
    alert()->setError(3, lang('access_permission_denied'));
    alert()->dumpError();
    return;
}

manager()->saveFormValues(4);

evo()->loadExtension('REVISION');
evo()->loadExtension('DocAPI');

$fields = $modx->doc->fixPubStatus(
    $modx->doc->fixTvNest($_POST)
);

$parent = (int)array_get($fields, 'parent', 0);
$currentParent = (int)db()->getValue(
    db()->select('parent', '[+prefix+]site_content', where('id', '=', (int)$id))
);
if ($parent !== $currentParent) {
    $message = manager()->parentChangeDeniedMessage([$id], $parent);
    if ($message) {
        alert()->setError(3, $message);
        alert()->dumpError();
        return;
    }
}

if (postv('stay') === 'save_standby') {
    $rs = $modx->revision->save($id, $fields, 'standby');
} else {
    $rs = $modx->revision->save($id, $fields, 'draft');
}

if (postv('stay') === 'new') {
    header(
        sprintf('Location: index.php?a=131&id=%s&r=1', $id)
    );
    return;
}
if (postv('stay') === 'stay') {
    header(
        sprintf('Location: index.php?a=131&id=%s&stay=stay', $id)
    );
    return;
}
if (in_array(postv('stay'), ['save_draft', 'save_standby'])) {
    header(
        sprintf('Location: index.php?a=133&id=%s&r=1', $id)
    );
    return;
}

header(sprintf('Location: index.php?a=3&id=%s&r=1', $id));
