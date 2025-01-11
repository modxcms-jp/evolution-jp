<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_module')) {
    alert()->setError(3);
    alert()->dumpError();
}

$tbl_site_modules = evo()->getFullTableName('site_modules');

if (preg_match('@^[0-9]+$@', postv('id'))) {
    $id = postv('id');
}
$name = db()->escape(trim(postv('name')));
$description = db()->escape(postv('description'));
$resourcefile = db()->escape(postv('resourcefile'));
$enable_resource = postv('enable_resource') === 'on' ? 1 : 0;
if ((postv('icon') !== '') && (preg_match('@^(' . $modx->config['rb_base_url'] . ')@', postv('icon')) == 1)) {
    $_POST['icon'] = '../' . postv('icon');
}
$icon = db()->escape(postv('icon'));
$disabled = postv('disabled') === 'on' ? 1 : 0;
$wrap = postv('wrap') === 'on' ? 1 : 0;
$locked = postv('locked') === 'on' ? 1 : 0;
$modulecode = db()->escape(postv('post'));
$properties = db()->escape(postv('properties'));
$enable_sharedparams = postv('enable_sharedparams') === 'on' ? 1 : 0;
$guid = db()->escape(postv('guid'));
$createdon = $editedon = time();

//Kyle Jaebker - added category support
if (!postv('newcategory') && postv('categoryid') > 0) {
    $category = db()->escape(postv('categoryid'));
} elseif (!postv('newcategory') && postv('categoryid') <= 0) {
    $category = 0;
} else {
    $catCheck = manager()->checkCategory(db()->escape(postv('newcategory')));
    if ($catCheck) {
        $category = $catCheck;
    } else {
        $category = manager()->newCategory(postv('newcategory'));
    }
}

if ($name == "") {
    $name = "Untitled module";
}

switch (postv('mode')) {
    case '107':
        // invoke OnBeforeModFormSave event
        $tmp = array(
            'mode' => 'new',
            'id' => ''
        );
        evo()->invokeEvent("OnBeforeModFormSave", $tmp);

        // disallow duplicate names for new modules
        $rs = db()->select('COUNT(id)', $tbl_site_modules, "name = '{$name}'");
        $count = db()->getValue($rs);
        if ($count > 0) {
            $modx->event->alert(sprintf($_lang['duplicate_name_found_module'], $name));

            // prepare a few variables prior to redisplaying form...
            $content = [];
            $_REQUEST['a'] = '107';
            $_GET['a'] = '107';
            $_GET['stay'] = postv('stay');
            $content = array_merge($content, $_POST);
            $content['wrap'] = $wrap;
            $content['disabled'] = $disabled;
            $content['locked'] = $locked;
            $content['plugincode'] = postv('post');
            $content['category'] = postv('categoryid');
            $content['properties'] = postv('properties');
            $content['modulecode'] = postv('post');
            $content['enable_resource'] = $enable_resource;
            $content['enable_sharedparams'] = $enable_sharedparams;
            $content['usrgroups'] = postv('usrgroups');


            include(MODX_MANAGER_PATH . 'actions/header.inc.php');
            include(MODX_MANAGER_PATH . 'actions/element/mutate_module.dynamic.php');
            include(MODX_MANAGER_PATH . 'actions/footer.inc.php');

            exit;
        }

        // save the new module

        $f = compact('name', 'description', 'icon', 'enable_resource', 'resourcefile',
            'disabled', 'wrap', 'locked', 'category', 'enable_sharedparams',
            'guid', 'modulecode', 'properties', 'editedon', 'createdon');
        $newid = db()->insert($f, $tbl_site_modules);
        if (!$newid) {
            echo '$newid not set! New module not saved!';
            exit;
        }

// save user group access permissions
        saveUserGroupAccessPermissons();

        // invoke OnModFormSave event
        $tmp = array(
            'mode' => 'new',
            'id' => $newid
        );
        evo()->invokeEvent("OnModFormSave", $tmp);
        if (postv('stay') != '') {
            $stay = postv('stay');
            $a = ($stay == '2') ? "108&id={$newid}" : '107';
            $header = "Location: index.php?a=" . $a . "&r=2&stay=" . $stay;
        } else {
            $header = "Location: index.php?a=106&r=2";
        }
        if ($enable_sharedparams !== 0) {
            $modx->clearCache();
        }
        header($header);
        break;
    case '108':
        // invoke OnBeforeModFormSave event
        $tmp = array(
            'mode' => 'upd',
            'id' => $id
        );
        evo()->invokeEvent('OnBeforeModFormSave', $tmp);

        // save the edited module
        $f = compact('name', 'description', 'icon', 'enable_resource', 'resourcefile',
            'disabled', 'wrap', 'locked', 'category', 'enable_sharedparams',
            'guid', 'modulecode', 'properties', 'editedon');
        $rs = db()->update($f, $tbl_site_modules, "id='" . $id . "'");
        if (!$rs) {
            echo '$rs not set! Edited module not saved!' . db()->getLastError();
            exit;
        }

// save user group access permissions
        saveUserGroupAccessPermissons();

        // invoke OnModFormSave event
        $tmp = array(
            'mode' => 'upd',
            'id' => $id
        );
        evo()->invokeEvent('OnModFormSave', $tmp);
        if (postv('stay') != '') {
            $a = (postv('stay') == '2') ? "108&id=" . $id : "107";
            $header = "Location: index.php?a=" . $a . "&r=2&stay=" . postv('stay');
        } else {
            $header = 'Location: index.php?a=106&r=2';
        }
        if ($enable_sharedparams !== 0) {
            $modx->clearCache();
        }
        header($header);
        break;
    default:
        // redirect to view modules
        header('Location: index.php?a=106&r=2');
}

// saves module user group access
function saveUserGroupAccessPermissons()
{
    global $modx;
    global $id, $newid;

    $tbl_site_module_access = evo()->getFullTableName('site_module_access');

    if ($newid) {
        $id = $newid;
    }
    $usrgroups = postv('usrgroups');

    // check for permission update access
    if ($modx->config['use_udperms'] == 1) {
        // delete old permissions on the module

        $rs = db()->delete($tbl_site_module_access, "module='" . $id . "'");
        if (!$rs) {
            echo "An error occured while attempting to delete previous module user access permission entries.";
            exit;
        }

        if (is_array($usrgroups)) {
            foreach ($usrgroups as $ugkey => $value) {
                $f['module'] = $id;
                $f['usergroup'] = db()->escape($value);
                $rs = db()->insert($f, $tbl_site_module_access);
                if (!$rs) {
                    echo "An error occured while attempting to save module user acess permissions.";
                    exit;
                }
            }
        }
    }
}
