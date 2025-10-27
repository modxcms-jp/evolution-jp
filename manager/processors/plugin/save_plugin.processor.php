<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('save_plugin')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (preg_match('@^[0-9]+$@', postv('id'))) {
    $id = postv('id');
}
$name = db()->escape(trim(postv('name')));
$description = db()->escape(postv('description'));
$locked = postv('locked') == 'on' ? '1' : '0';
$plugincode = db()->escape(postv('post'));
$properties = db()->escape(postv('properties'));
$disabled = postv('disabled') == "on" ? '1' : '0';
$moduleguid = db()->escape(postv('moduleguid', ''));
$sysevents = postv('sysevents');
$pluginErrorReporting = postv('error_reporting', 'inherit');
$validErrorLevels = ['inherit', '0', '1', '2', '99'];
if (!in_array($pluginErrorReporting, $validErrorLevels, true)) {
    $pluginErrorReporting = 'inherit';
}
if (empty($sysevents)) {
    $sysevents[] = 90;
} // Default OnWebPageInit

//Kyle Jaebker - added category support
if (!postv('newcategory') && postv('categoryid') > 0) {
    $category = db()->escape(postv('categoryid'));
} elseif (!postv('newcategory') && postv('categoryid') <= 0) {
    $category = '0';
} else {
    $catCheck = manager()->checkCategory(db()->escape(postv('newcategory')));
    if ($catCheck) {
        $category = $catCheck;
    } else {
        $category = manager()->newCategory(postv('newcategory'));
    }
}

if ($name == '') {
    $name = 'Untitled plugin';
}

switch (postv('mode')) {
    case '101':

        // invoke OnBeforePluginFormSave event
        $tmp = array(
            'mode' => 'new',
            'id' => ''
        );
        evo()->invokeEvent('OnBeforePluginFormSave', $tmp);

        // disallow duplicate names for active plugins
        if ($disabled == '0') {
            $rs = db()->select('COUNT(id)', '[+prefix+]site_plugins', "name='{$name}' AND disabled='0'");
            $count = db()->getValue($rs);
            if ($count > 0) {
                manager()->saveFormValues(101);
                $modx->event->alert(sprintf($_lang['duplicate_name_found_general'], $_lang['plugin'], $name));

                // prepare a few variables prior to redisplaying form...
                $content = [];
                $_REQUEST['a'] = '101';
                $_GET['a'] = '101';
                $_GET['stay'] = postv('stay');
                $content = array_merge($content, $_POST);
                $content['locked'] = $locked;
                $content['plugincode'] = postv('post');
                $content['category'] = postv('categoryid');
                $content['disabled'] = $disabled;
                $content['properties'] = $properties;
                $content['moduleguid'] = $moduleguid;
                $content['sysevents'] = $sysevents;

                include(MODX_MANAGER_PATH . 'actions/header.inc.php');
                include(MODX_MANAGER_PATH . 'actions/element/mutate_plugin.dynamic.php');
                include(MODX_MANAGER_PATH . 'actions/footer.inc.php');

                exit;
            }
        }

        //do stuff to save the new plugin
        $f = compact('name', 'description', 'plugincode', 'disabled', 'moduleguid', 'locked', 'properties', 'category');
        $f['error_reporting'] = $pluginErrorReporting;
        $newid = db()->insert($f, '[+prefix+]site_plugins');
        if (!$newid) {
            echo "Couldn't get last insert key!";
            exit;
        }

        // save event listeners
        saveEventListeners($newid, $sysevents, postv('mode'));

        // invoke OnPluginFormSave event
        $tmp = array(
            'mode' => 'new',
            'id' => $newid
        );
        evo()->invokeEvent('OnPluginFormSave', $tmp);

        // empty cache
        $modx->clearCache(); // first empty the cache
        // finished emptying cache - redirect
        if (postv('stay') != '') {
            $a = (postv('stay') == '2') ? "102&id={$newid}" : "101";
            $header = "Location: index.php?a={$a}&stay=" . postv('stay');
        } else {
            $header = "Location: index.php?a=76";
        }
        header($header);
        break;
    case '102':

        // invoke OnBeforePluginFormSave event
        $tmp = array(
            'mode' => 'upd',
            'id' => $id
        );
        evo()->invokeEvent("OnBeforePluginFormSave", $tmp);

        // disallow duplicate names for active plugins
        if ($disabled == '0') {
            $rs = db()->select('COUNT(*)', '[+prefix+]site_plugins',
                "name='{$name}' AND id!='{$id}' AND disabled='0'");
            if (db()->getValue($rs) > 0) {
                manager()->saveFormValues();
                $modx->event->alert(sprintf($_lang['duplicate_name_found_general'], $_lang['plugin'], $name));

                // prepare a few variables prior to redisplaying form...
                $content = [];
                $_REQUEST['a'] = '102';
                $_GET['a'] = '102';
                $_GET['stay'] = postv('stay');
                $content = array_merge($content, $_POST);
                $content['locked'] = $locked;
                $content['plugincode'] = postv('post');
                $content['category'] = postv('categoryid');
                $content['disabled'] = $disabled;
                $content['properties'] = $properties;
                $content['moduleguid'] = $moduleguid;
                $content['sysevents'] = $sysevents;

                include(MODX_MANAGER_PATH . 'actions/header.inc.php');
                include(MODX_MANAGER_PATH . 'actions/element/mutate_plugin.dynamic.php');
                include(MODX_MANAGER_PATH . 'actions/footer.inc.php');

                exit;
            }
        }
        //do stuff to save the edited plugin
        $f = compact('name', 'description', 'plugincode', 'disabled', 'moduleguid', 'locked', 'properties', 'category');
        $f['error_reporting'] = $pluginErrorReporting;
        $rs = db()->update($f, '[+prefix+]site_plugins', "id='{$id}'");
        if (!$rs) {
            echo "\$rs not set! Edited plugin not saved!";
        } else {
            // save event listeners
            saveEventListeners($id, $sysevents, postv('mode'));

            // invoke OnPluginFormSave event
            $tmp = array(
                'mode' => 'upd',
                'id' => $id
            );
            evo()->invokeEvent('OnPluginFormSave', $tmp);

            // empty cache
            $modx->clearCache(); // first empty the cache
            // finished emptying cache - redirect
            if (postv('stay') != '') {
                $a = (postv('stay') == '2') ? "102&id={$id}" : "101";
                $header = "Location: index.php?a={$a}&stay=" . postv('stay');
            } else {
                $header = 'Location: index.php?a=76';
            }
            header($header);
        }
        break;
    default:
        ?>
        Erm... You supposed to be here now?
    <?php
}


# Save Plugin Event Listeners
function saveEventListeners($id, $sysevents, $mode)
{
    global $modx;
    // save selected system events
    $tblSitePluginEvents = evo()->getFullTableName('site_plugin_events');
    $sql = "INSERT INTO {$tblSitePluginEvents} (pluginid,evtid,priority) VALUES ";
    for ($i = 0, $iMax = count($sysevents); $i < $iMax; $i++) {
        $event = $sysevents[$i];
        if (!preg_match('/^[0-9]+\z/', $event)) {
            continue;
        } //ignore invalid data

        if ($mode == '101') {
            $prioritySql = "select max(priority) as priority from {$tblSitePluginEvents} where evtid={$event}";
        } else {
            $prioritySql = "select priority from {$tblSitePluginEvents} where evtid={$event} and pluginid={$id}";
        }
        $rs = db()->query($prioritySql);
        $prevPriority = db()->getRow($rs);
        if ($mode == '101') {
            $priority = isset($prevPriority['priority']) ? $prevPriority['priority'] + 1 : 1;
        } else {
            $priority = isset($prevPriority['priority']) ? $prevPriority['priority'] : 1;
        }
        if ($i > 0) {
            $sql .= ",";
        }
        $sql .= "(" . $id . "," . $event . "," . $priority . ")";
    }
    db()->query("DELETE FROM {$tblSitePluginEvents} WHERE pluginid={$id}");
    if (count($sysevents) > 0) {
        db()->query($sql);
    }
}
