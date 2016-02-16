<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if (!$modx->hasPermission('save_chunk')) {
    $e->setError(3);
    $e->dumpError();
}

$input = $_POST;
extract($input);
unset($input);

if(isset($_POST['id']) && preg_match('@^[0-9]+$@',$_POST['id'])) $id = $_POST['id'];

$snippet = $modx->db->escape($post);
$name = (isset($name) && $name !== '') ? $modx->db->escape(trim($name)) : 'Untitled chunk';
$description = $modx->db->escape($description);
$locked      = $locked == 'on'     ? 1 : 0;
$editor_type = $editor_type == '1' ? 1 : 0;
$published   = $published == '1'   ? 1 : 0;

// determine published status
$currentdate = time();

if (empty($pub_date)) {
    $pub_date = 0;
} else {
    $pub_date = $modx->toTimeStamp($pub_date);
    if (empty($pub_date)) {
        $modx->manager->saveFormValues(78);
        $modx->webAlertAndQuit($_lang["mgrlog_dateinvalid"], "index.php?a=78&id={$id}");
        exit;
    }
    elseif ($pub_date < $currentdate) $published = 1;
    elseif ($pub_date > $currentdate) $published = 0;
}
if (empty($unpub_date))
    $unpub_date = 0;
else {
    $unpub_date = $modx->toTimeStamp($unpub_date);
    if (empty($unpub_date)) {
        $modx->manager->saveFormValues(78);
        $modx->webAlertAndQuit($_lang["mgrlog_dateinvalid"], "index.php?a=78&id={$id}");
        exit;
    }
    elseif ($unpub_date < $currentdate) $published = 0;
}

//Kyle Jaebker - added category support
if (empty($_POST['newcategory']) && $_POST['categoryid'] > 0) {
    $category = $modx->db->escape($_POST['categoryid']);
} elseif (empty($_POST['newcategory']) && $_POST['categoryid'] <= 0) {
    $category = 0;
} else {
    $catCheck = $modx->manager->checkCategory($modx->db->escape($_POST['newcategory']));
    if ($catCheck) $category = $catCheck;
    else           $category = $modx->manager->newCategory($_POST['newcategory']);
}

switch ($_POST['mode']) {
    case '77':

        // invoke OnBeforeChunkFormSave event
        $tmp = array(
              'mode' => 'new',
              'id' => ''
        );
        $modx->invokeEvent('OnBeforeChunkFormSave', $tmp);

        // disallow duplicate names for new chunks
        $rs = $modx->db->select('COUNT(id)', '[+prefix+]site_htmlsnippets', "name='{$name}'");
        $count = $modx->db->getValue($rs);
        if ($count > 0) {
            $msg = sprintf($_lang['duplicate_name_found_general'], $_lang['chunk'], $name);
            $modx->manager->saveFormValues(77);
            $modx->webAlertAndQuit($msg, 'index.php?a=77');
            exit;
        }
        //do stuff to save the new doc
        $field = compact(explode(',', 'name,description,published,pub_date,unpub_date,snippet,locked,editor_type,category'));
        $newid = $modx->db->insert($field, '[+prefix+]site_htmlsnippets');
        // get the id
        if(!$newid) exit("Couldn't get last insert key!");

        // invoke OnChunkFormSave event
        $tmp = array(
            'mode' => 'new',
            'id' => $newid
        );
        $modx->invokeEvent('OnChunkFormSave', $tmp);

        // empty cache
        $modx->clearCache(); // first empty the cache
        // finished emptying cache - redirect
        if ($_POST['stay'] != '') {
            $a = ($_POST['stay'] == '2') ? "78&id={$newid}" : '77';
            $header = "Location: index.php?a={$a}&stay={$_POST['stay']}";
        } else {
            $header = 'Location: index.php?a=76';
        }
        header($header);
        break;
    case '78':

        // invoke OnBeforeChunkFormSave event
        $tmp = array(
          "mode" => "upd",
          "id" => $id
        );
        $modx->invokeEvent("OnBeforeChunkFormSave", $tmp);

        if (check_exist_name($name) !== false) {
            $msg = sprintf($_lang['duplicate_name_found_general'], $_lang['chunk'], $name);
            $modx->manager->saveFormValues(78);
            $modx->webAlertAndQuit($msg, "index.php?a=78&id={$id}");
            exit;
        }

        //do stuff to save the edited doc
        $was_name = $modx->db->getValue($modx->db->select('name', '[+prefix+]site_htmlsnippets', "id='{$id}'"));
        $field = compact(explode(',', 'name,description,published,pub_date,unpub_date,snippet,locked,editor_type,category'));
        $rs = $modx->db->update($field, '[+prefix+]site_htmlsnippets', "id='{$id}'");
        if (!$rs) {
            echo "\$rs not set! Edited htmlsnippet not saved!";
        } else {
            $name = stripslashes($name);
            $name = str_replace("'", "''", $name);
            $was_name = str_replace("'", "''", $was_name);
            if ($name !== $was_name) {
                $modx->db->update("content=REPLACE(content,'{{{$was_name}}}','{{{$name}}}')", '[+prefix+]site_content');
                $modx->db->update("content=REPLACE(content,'{{{$was_name}}}','{{{$name}}}')", '[+prefix+]site_templates');
                $modx->db->update("snippet=REPLACE(snippet,'{{{$was_name}}}','{{{$name}}}')", '[+prefix+]site_htmlsnippets');
                $modx->db->update("value=REPLACE(value,    '{{{$was_name}}}','{{{$name}}}')", '[+prefix+]site_tmplvar_contentvalues');
                $modx->db->update("content=REPLACE(content,'{{{$was_name}:','{{{$name}:')", '[+prefix+]site_content');
                $modx->db->update("content=REPLACE(content,'{{{$was_name}:','{{{$name}:')", '[+prefix+]site_templates');
                $modx->db->update("snippet=REPLACE(snippet,'{{{$was_name}:','{{{$name}:')", '[+prefix+]site_htmlsnippets');
                $modx->db->update("value=REPLACE(value,    '{{{$was_name}:','{{{$name}:')", '[+prefix+]site_tmplvar_contentvalues');
            }

            // invoke OnChunkFormSave event
            $tmp = array(
                'mode' => 'upd',
                'id' => $id
            );
            $modx->invokeEvent('OnChunkFormSave', $tmp);

            // empty cache
            $modx->clearCache(); // first empty the cache
            // finished emptying cache - redirect
            if ($_POST['stay'] != '') {
                $a = ($_POST['stay'] == '2') ? "78&id={$id}" : "77";
                $header = "Location: index.php?a={$a}&stay={$_POST['stay']}";
            } else {
                $header = "Location: index.php?a=76";
            }
            header($header);
        }
        break;
    default:
}

function check_exist_name($name) { // disallow duplicate names for new chunks
    global $modx;
    $where = "name='{$name}'";
    if ($_POST['mode'] == 78) {
        $where = $where . " AND id!={$_POST['id']}";
    }
    $rs = $modx->db->select('COUNT(id)', '[+prefix+]site_htmlsnippets', $where);
    $count = $modx->db->getValue($rs);
    if ($count > 0)
        return true;
    else
        return false;
}
