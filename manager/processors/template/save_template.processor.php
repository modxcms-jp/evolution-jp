<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_template')) {
    alert()->setError(3);
    alert()->dumpError();
}
if (isset($_POST['id']) && preg_match('@^[0-9]+$@', $_POST['id'])) {
    $id = $_POST['id'];
}

$templatename = db()->escape(trim($_POST['templatename']));
$description = db()->escape($_POST['description']);
$parent = db()->escape($_POST['parent']);

$locked = $_POST['locked'] == 'on' ? 1 : 0;

$tbl_site_templates = evo()->getFullTableName('site_templates');

//Kyle Jaebker - added category support
if (empty($_POST['newcategory']) && $_POST['categoryid'] > 0) {
    $categoryid = db()->escape($_POST['categoryid']);
} elseif (empty($_POST['newcategory']) && $_POST['categoryid'] <= 0) {
    $categoryid = 0;
} else {
    $catCheck = $modx->manager->checkCategory(db()->escape($_POST['newcategory']));
    if ($catCheck) {
        $categoryid = $catCheck;
    } else {
        $categoryid = $modx->manager->newCategory($_POST['newcategory']);
    }
}

if ($templatename == '') {
    $templatename = "Untitled template";
}

$field = array();
$field['templatename'] = $templatename;
$field['description'] = $description;
$field['content'] = db()->escape($_POST['content']);
$field['locked'] = $locked;
$field['category'] = $categoryid;
$field['parent'] = $parent;

switch ($_POST['mode']) {
    case '19':

        // invoke OnBeforeTempFormSave event
        $tmp = array(
            'mode' => 'new',
            'id' => ''
        );
        evo()->invokeEvent("OnBeforeTempFormSave", $tmp);

        // disallow duplicate names for new templates
        $rs = db()->select('COUNT(id)', $tbl_site_templates, "templatename = '{$templatename}'");
        $count = db()->getValue($rs);
        if ($count > 0) {
            $modx->event->alert(sprintf($_lang['duplicate_name_found_general'], $_lang['template'], $templatename));
            // prepare a few request/post variables for form redisplay...
            $_REQUEST['a'] = '19';
            $_POST['locked'] = isset($_POST['locked']) && $_POST['locked'] == 'on' ? 1 : 0;
            $_POST['category'] = $categoryid;
            $_GET['stay'] = $_POST['stay'];
            include(MODX_MANAGER_PATH . 'actions/header.inc.php');
            include(MODX_MANAGER_PATH . 'actions/element/mutate_templates.dynamic.php');
            include(MODX_MANAGER_PATH . 'actions/footer.inc.php');
            exit;
        }

        //do stuff to save the new doc
        $newid = db()->insert($field, $tbl_site_templates);
        if (!$newid) {
            echo "Couldn't get last insert key!";
            exit;
        }

        // invoke OnTempFormSave event
        $tmp = array(
            "mode" => "new",
            "id" => $newid
        );
        evo()->invokeEvent("OnTempFormSave", $tmp);

        // empty cache
        $modx->clearCache();
        // finished emptying cache - redirect
        if ($_POST['stay'] != '') {
            $a = ($_POST['stay'] == '2') ? "16&id=$newid" : "19";
            $header = "Location: index.php?a={$a}&stay={$_POST['stay']}";
        } else {
            $header = "Location: index.php?a=76";
        }
        header($header);
        break;
    case '16':

        // invoke OnBeforeTempFormSave event
        $tmp = array(
            'mode' => 'upd',
            'id' => $id
        );
        evo()->invokeEvent('OnBeforeTempFormSave', $tmp);

        // disallow duplicate names for new templates
        $rs = db()->select('COUNT(id)', $tbl_site_templates, "templatename = '{$templatename}' AND id != '{$id}'");
        $count = db()->getValue($rs);
        if ($count > 0) {
            $modx->event->alert(sprintf($_lang['duplicate_name_found_general'], $_lang['template'], $templatename));
            // prepare a few request/post variables for form redisplay...
            $_REQUEST['a'] = '16';
            $_POST['locked'] = isset($_POST['locked']) && $_POST['locked'] == 'on' ? 1 : 0;
            $_POST['category'] = $categoryid;
            $_GET['stay'] = $_POST['stay'];
            include(MODX_MANAGER_PATH . 'actions/header.inc.php');
            include(MODX_MANAGER_PATH . 'actions/element/mutate_templates.dynamic.php');
            include(MODX_MANAGER_PATH . 'actions/footer.inc.php');
            exit;
        }

        //do stuff to save the edited doc
        $rs = db()->update($field, $tbl_site_templates, "id='{$id}'");
        if (!$rs) {
            echo "\$rs not set! Edited template not saved!";
        } else {
            // invoke OnTempFormSave event
            $tmp = array(
                "mode" => "upd",
                "id" => $id
            );
            evo()->invokeEvent("OnTempFormSave", $tmp);

            // first empty the cache
            $modx->clearCache();
            // finished emptying cache - redirect
            if ($_POST['stay'] != '') {
                $a = ($_POST['stay'] == '2') ? "16&id=$id" : "19";
                $header = "Location: index.php?a={$a}&stay={$_POST['stay']}";
            } else {
                $header = "Location: index.php?a=76";
            }
            header($header);
        }
        break;
    default:
?>
        Erm... You supposed to be here now?
<?php
}
