<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('delete_template')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = (int)getv('id');
$tbl_site_content = evo()->getFullTableName('site_content');
$tbl_site_templates = evo()->getFullTableName('site_templates');
$tbl_site_tmplvar_templates = evo()->getFullTableName('site_tmplvar_templates');

// delete the template, but first check it doesn't have any documents using it
$rs = db()->select('id, pagetitle', $tbl_site_content, "template='{$id}' and deleted=0");
$limit = db()->count($rs);
if ($limit > 0) {
    echo "This template is in use. Please set the documents using the template to another template. Documents using this template:<br />";
    for ($i = 0; $i < $limit; $i++) {
        $row = db()->getRow($rs);
        echo $row['id'] . " - " . $row['pagetitle'] . "<br />\n";
    }
    exit;
}

if ($id == $default_template) {
    echo "This template is set as the default template. Please choose a different default template in the MODx configuration before deleting this template.<br />";
    exit;
}

// invoke OnBeforeTempFormDelete event
$tmp = array('id' => $id);
evo()->invokeEvent('OnBeforeTempFormDelete', $tmp);

//ok, delete the document.
$rs = db()->delete($tbl_site_templates, "id='{$id}'");
if (!$rs) {
    echo "Something went wrong while trying to delete the template...";
    exit;
}

$rs = db()->delete($tbl_site_tmplvar_templates, "templateid='{$id}'");

// invoke OnTempFormDelete event
$tmp = array('id' => $id);
evo()->invokeEvent('OnTempFormDelete', $tmp);

// empty cache
$modx->clearCache();

header('Location: index.php?a=76');
