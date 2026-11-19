<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission(EvoElementTypes::get('template')['permissions']['delete'])) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = get_intvar('id');
if (!$id) {
    alert()->setError(2);
    alert()->dumpError();
}

$rs = db()->select(
    'id, pagetitle',
    '[+prefix+]site_content',
    [where('template', '=', $id), and_where('deleted', '=', 0)]
);
if (db()->count($rs) > 0) {
    echo "This template is in use. Please set the documents using the template to another template. Documents using this template:<br />";
    while ($row = db()->getRow($rs)) {
        echo (int) $row['id'] . ' - ' . hsc($row['pagetitle']) . "<br />\n";
    }
    exit;
}

if ($id == config('default_template')) {
    echo "This template is set as the default template. Please choose a different default template in the MODx configuration before deleting this template.<br />";
    exit;
}

include __DIR__ . '/../element/delete_element.processor.php';
