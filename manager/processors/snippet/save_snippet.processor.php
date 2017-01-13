<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if(!$modx->hasPermission('save_snippet')) {
	$e->setError(3);
	$e->dumpError();
}
$id = (isset($_POST['id']) && preg_match('@^[0-9]+$@',$_POST['id'])) ? $_POST['id'] : 0;
$name = $modx->db->escape(trim($_POST['name']));
$description = $modx->db->escape($_POST['description']);
$locked = $_POST['locked']=='on' ? 1 : 0 ;
$snippet = $modx->db->escape(trim($_POST['post']));
$tbl_site_snippets = $modx->getFullTableName('site_snippets');

// strip out PHP tags from snippets
if ( strncmp($snippet, '<?', 2) == 0 ) {
    $snippet = substr($snippet, 2);
    if ( strncmp( $snippet, 'php', 3 ) == 0 ) $snippet = substr($snippet, 3);
    if ( substr($snippet, -2, 2) == '?>' ) $snippet = substr($snippet, 0, -2);
}
$properties = $modx->db->escape($_POST['properties']);
$moduleguid = $modx->db->escape($_POST['moduleguid']);
$sysevents = $_POST['sysevents'];

//Kyle Jaebker - added category support
if (empty($_POST['newcategory']) && 0 < $_POST['categoryid'])
{
    $categoryid = $modx->db->escape($_POST['categoryid']);
}
elseif(empty($_POST['newcategory']) && $_POST['categoryid'] <= 0)
{
    $categoryid = 0;
}
else
{
    $catCheck = $modx->manager->checkCategory($modx->db->escape($_POST['newcategory']));
    
    if ($catCheck) $categoryid = $catCheck;
    else           $categoryid = $modx->manager->newCategory($_POST['newcategory']);
}

if($name=='') $name = 'Untitled snippet';

// disallow duplicate names
$where = "name='{$name}'";
if($id) $where .= ' AND id!='.$id;
$rs = $modx->db->select('COUNT(id)', '[+prefix+]site_snippets', $where);
$count = $modx->db->getValue($rs);
if ($count > 0) {
    $msg = sprintf($_lang['duplicate_name_found_general'], $_lang['snippet'], $name);
    $modx->manager->saveFormValues(23);
    $modx->webAlertAndQuit($msg, 'index.php?a=23');
    exit;
}

switch ($_POST['mode'])
{
	case '23':
		// invoke OnBeforeSnipFormSave event
    $tmp = array(
									'mode'	=> 'new',
									'id'	=> ''
    );
		$modx->invokeEvent('OnBeforeSnipFormSave',$tmp);
		
		//do stuff to save the new doc
		$field=array();
		$field['name']        = $name;
		$field['description'] = $description;
		$field['snippet']     = $snippet;
		$field['moduleguid']  = $moduleguid;
		$field['locked']      = $locked;
		$field['properties']  = $properties;
		$field['category']    = $categoryid;
		$newid = $modx->db->insert($field,$tbl_site_snippets);
		if(!$newid)
		{
			echo '$newid not set! New snippet not saved!';
			exit;
		}
		
		// invoke OnSnipFormSave event
    $tmp = array(
									'mode'	=> 'new',
									'id'	=> $newid
    );
		$modx->invokeEvent('OnSnipFormSave',$tmp);
		// empty cache
		$modx->clearCache(); // first empty the cache
		// finished emptying cache - redirect
		if($_POST['stay']!='')
		{
			$a = ($_POST['stay']=='2') ? "22&id={$newid}":'23';
			$header="Location: index.php?a={$a}&stay={$_POST['stay']}";
		}
		else
		{
			$header='Location: index.php?a=76';
		}
		header($header);
		break;
		
	case '22':
		// invoke OnBeforeSnipFormSave event
    $tmp = array(
									'mode'	=> 'upd',
									'id'	=> $id
    );
		$modx->invokeEvent('OnBeforeSnipFormSave',$tmp);
								
		//do stuff to save the edited doc
		$field = array();
		$field['name']        = $name;
		$field['description'] = $description;
		$field['snippet']     = $snippet;
		$field['moduleguid']  = $moduleguid;
		$field['locked']      = $locked;
		$field['properties']  = $properties;
		$field['category']    = $categoryid;
		$rs = $modx->db->update($field,$tbl_site_snippets,"id='{$id}'");
		if(!$rs)
		{
			echo '$rs not set! Edited snippet not saved!';
			exit;
		}
		else
		{
			// invoke OnSnipFormSave event
      $tmp = array(
										'mode'	=> 'upd',
										'id'	=> $id
      );
			$modx->invokeEvent('OnSnipFormSave',$tmp);
			// empty cache
			$modx->clearCache(); // first empty the cache
			//if($_POST['runsnippet']) run_snippet($snippet);
			// finished emptying cache - redirect
			if($_POST['stay']!='')
			{
				$a = ($_POST['stay']=='2') ? "22&id={$id}":'23';
				$header="Location: index.php?a={$a}&stay={$_POST['stay']}";
			}
			else
			{
				$header='Location: index.php?a=76';
			}
			header($header);
		}
		break;
	default:
}
