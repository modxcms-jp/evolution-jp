<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_module')) {
    alert()->setError(3);
    alert()->dumpError();
}

$tbl_site_modules = evo()->getFullTableName('site_modules');

// 新規（107）と更新（108）以外は拒否する
$mode = post_choicevar('mode', [107, 108], 0);
$id = post_intvar('id');
if (!$mode || ($mode === 108 && !$id)) {
    alert()->setError(2);
    alert()->dumpError();
}

// 本文と設定は文字列だけを受ける。配列で送られたときは、空で上書きせず保存しない
$modulecode = postv('post', '');
$properties = postv('properties', '');
if (!is_string($modulecode) || !is_string($properties)) {
    alert()->setError(2);
    alert()->dumpError();
}

$name = manager()->requireElementName(post_strvar('name'), $_lang['module'], 107, 108, $id);
$enable_resource = postv('enable_resource') === 'on' ? 1 : 0;
$icon = post_strvar('icon');
if ($icon !== '' && preg_match('@^(' . config('rb_base_url') . ')@', $icon) == 1) {
    $icon = '../' . $icon;
}
$disabled = postv('disabled') === 'on' ? 1 : 0;
$wrap = postv('wrap') === 'on' ? 1 : 0;
$locked = postv('locked') === 'on' ? 1 : 0;
$enable_sharedparams = postv('enable_sharedparams') === 'on' ? 1 : 0;
$createdon = $editedon = time();

switch ($mode) {
    case 107:
        // invoke OnBeforeModFormSave event
        $eventParams = [
            'mode' => 'new',
            'id' => ''
        ];
        evo()->invokeEvent("OnBeforeModFormSave", $eventParams);

        // disallow duplicate names for new modules
        $rs = db()->select('COUNT(id)', $tbl_site_modules, where('name', db()->escape($name)));
        $count = db()->getValue($rs);
        if ($count > 0) {
            manager()->redirectToElementForm(
                sprintf($_lang['duplicate_name_found_module'], $name),
                107,
                108,
                0
            );
        }

        $category = manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));

        // save the new module

        $f = [
            'name' => $name,
            'description' => post_strvar('description'),
            'icon' => $icon,
            'enable_resource' => $enable_resource,
            'resourcefile' => post_strvar('resourcefile'),
            'disabled' => $disabled,
            'wrap' => $wrap,
            'locked' => $locked,
            'category' => $category,
            'enable_sharedparams' => $enable_sharedparams,
            'guid' => post_strvar('guid'),
            'modulecode' => $modulecode,
            'properties' => $properties,
            'editedon' => $editedon,
            'createdon' => $createdon
        ];
        $newid = db()->insert(db()->escape($f), $tbl_site_modules);
        if (!$newid) {
            echo '$newid not set! New module not saved!';
            exit;
        }

        saveUserGroupAccessPermissons();

        // invoke OnModFormSave event
        $eventParams = [
            'mode' => 'new',
            'id' => $newid
        ];
        evo()->invokeEvent("OnModFormSave", $eventParams);

        $stay = post_choicevar('stay', [1, 2], 0);
        if ($stay) {
            $a = ($stay === 2) ? "108&id={$newid}" : '107';
            $header = "Location: index.php?a={$a}&r=2&stay={$stay}";
        } else {
            $header = "Location: index.php?a=106&r=2";
        }
        if ($enable_sharedparams !== 0) {
            $modx->clearCache();
        }
        header($header);
        break;
    case 108:
        // invoke OnBeforeModFormSave event
        $eventParams = [
            'mode' => 'upd',
            'id' => $id
        ];
        evo()->invokeEvent('OnBeforeModFormSave', $eventParams);

        $category = manager()->resolveElementCategory(post_intvar('categoryid'), post_strvar('newcategory'));

        // save the edited module
        $f = [
            'name' => $name,
            'description' => post_strvar('description'),
            'icon' => $icon,
            'enable_resource' => $enable_resource,
            'resourcefile' => post_strvar('resourcefile'),
            'disabled' => $disabled,
            'wrap' => $wrap,
            'locked' => $locked,
            'category' => $category,
            'enable_sharedparams' => $enable_sharedparams,
            'guid' => post_strvar('guid'),
            'modulecode' => $modulecode,
            'properties' => $properties,
            'editedon' => $editedon
        ];

        $rs = db()->update(db()->escape($f), $tbl_site_modules, "id='" . $id . "'");
        if (!$rs) {
            echo '$rs not set! Edited module not saved!' . db()->getLastError();
            exit;
        }

// save user group access permissions
        saveUserGroupAccessPermissons();

        // invoke OnModFormSave event
        $eventParams = [
            'mode' => 'upd',
            'id' => $id
        ];
        evo()->invokeEvent('OnModFormSave', $eventParams);

        $stay = post_choicevar('stay', [1, 2], 0);
        if ($stay) {
            $a = ($stay === 2) ? "108&id={$id}" : "107";
            $header = "Location: index.php?a={$a}&r=2&stay={$stay}";
        } else {
            $header = 'Location: index.php?a=106&r=2';
        }
        if ($enable_sharedparams !== 0) {
            $modx->clearCache();
        }
        header($header);
        break;
}

// saves module user group access
function saveUserGroupAccessPermissons()
{
    global $id, $newid;

    // check for permission update access
    if (config('use_udperms') != 1) {
        return;
    }

    if ($newid) {
        $id = $newid;
    }

    // delete old permissions on the module
    $rs = db()->delete('[+prefix+]site_module_access', "module='" . $id . "'");
    if (!$rs) {
        echo "An error occured while attempting to delete previous module user access permission entries.";
        exit;
    }

    // 配列だけを受ける。配列でないときは何も登録しない
    $usrgroups = postv('user_groups');
    if (!is_array($usrgroups)) {
        return;
    }

    foreach (to_idsvar($usrgroups) as $value) {
        $f = [
            'module' => $id,
            'usergroup' => $value
        ];
        $rs = db()->insert(db()->escape($f), '[+prefix+]site_module_access');
        if (!$rs) {
            echo "An error occured while attempting to save module user acess permissions.";
            exit;
        }
    }
}
