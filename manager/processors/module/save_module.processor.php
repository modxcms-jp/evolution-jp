<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('save_module')) {
	$e->setError(3);
	$e->dumpError();
}

$tbl_site_modules = $modx->getFullTableName('site_modules');

if(isset($_POST['id']) && preg_match('@^[0-9]+$@',$_POST['id'])) $id = $_POST['id'];
$name = $modx->db->escape(trim($_POST['name']));
$description = $modx->db->escape($_POST['description']);
$resourcefile = $modx->db->escape($_POST['resourcefile']);
$enable_resource = $_POST['enable_resource']=='on' ? 1 : 0 ;
if(($_POST['icon']!=='') && (preg_match('@^(' . $modx->config['rb_base_url'] . ')@', $_POST['icon'])==1))
    $_POST['icon'] = '../' . $_POST['icon'];
$icon = $modx->db->escape($_POST['icon']);
$disabled = $_POST['disabled']=='on' ? 1 : 0 ;
$wrap = $_POST['wrap']=='on' ? 1 : 0 ;
$locked = $_POST['locked']=='on' ? 1 : 0 ;
$modulecode = $modx->db->escape($_POST['post']);
$properties = $modx->db->escape($_POST['properties']);
$enable_sharedparams = $_POST['enable_sharedparams']=='on' ? 1 : 0 ;
$guid = $modx->db->escape($_POST['guid']);
$createdon = $editedon = time();

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

if($name=="") $name = "Untitled module";

switch ($_POST['mode']) {
    case '107':
		// invoke OnBeforeModFormSave event
    $tmp = array(
								'mode'	=> 'new',
								'id'	=> ''
    );
		$modx->invokeEvent("OnBeforeModFormSave",$tmp);
							
		// disallow duplicate names for new modules
		$rs = $modx->db->select('COUNT(id)',$tbl_site_modules,"name = '{$name}'");
		$count = $modx->db->getValue($rs);
		if($count > 0) {
			$modx->event->alert(sprintf($_lang['duplicate_name_found_module'], $name));

			// prepare a few variables prior to redisplaying form...
			$content = array();
			$_REQUEST['a'] = '107';
			$_GET['a'] = '107';
			$_GET['stay'] = $_POST['stay'];
			$content = array_merge($content, $_POST);
			$content['wrap'] = $wrap;
			$content['disabled'] = $disabled;
			$content['locked'] = $locked;
			$content['plugincode'] = $_POST['post'];
			$content['category'] = $_POST['categoryid'];
			$content['properties'] = $_POST['properties'];
			$content['modulecode'] = $_POST['post'];
			$content['enable_resource'] = $enable_resource;
			$content['enable_sharedparams'] = $enable_sharedparams;
			$content['usrgroups'] = $_POST['usrgroups'];
			

			include(MODX_MANAGER_PATH . 'actions/header.inc.php');
			include(MODX_MANAGER_PATH . 'actions/element/mutate_module.dynamic.php');
			include(MODX_MANAGER_PATH . 'actions/footer.inc.php');
			
			exit;
		}

		// save the new module
		
		$f = array();
		$f = compact('name','description','icon','enable_resource','resourcefile',
		             'disabled','wrap','locked','category','enable_sharedparams',
		             'guid','modulecode','properties','editedon','createdon');
		$newid = $modx->db->insert($f,$tbl_site_modules);
		if(!$newid){
			echo "\$newid not set! New module not saved!";
			exit;
		}
		else
		{
			// save user group access permissions
			saveUserGroupAccessPermissons();
			
			// invoke OnModFormSave event
      $tmp = array(
									'mode'	=> 'new',
									'id'	=> $newid
      );
			$modx->invokeEvent("OnModFormSave",$tmp);
			if($_POST['stay']!='')
			{
				$stay = $_POST['stay'];
				$a = ($stay=='2') ? "108&id={$newid}":'107';
				$header="Location: index.php?a={$a}&r=2&stay={$stay}";
			}
			else
			{
				$header="Location: index.php?a=106&r=2";
			}
			if($enable_sharedparams!==0) $modx->clearCache();
			header($header);
		}
        break;
    case '108':
		// invoke OnBeforeModFormSave event
      $tmp = array(
								'mode'	=> 'upd',
								'id'	=> $id
      );
		$modx->invokeEvent('OnBeforeModFormSave',$tmp);
							
		// save the edited module
		$f = array();
		$f = compact('name','description','icon','enable_resource','resourcefile',
		             'disabled','wrap','locked','category','enable_sharedparams',
		             'guid','modulecode','properties','editedon');
		$rs = $modx->db->update($f,$tbl_site_modules,"id='{$id}'");
		if(!$rs){
			echo "\$rs not set! Edited module not saved!".$modx->db->getLastError();
			exit;
		}
		else {
			// save user group access permissions
			saveUserGroupAccessPermissons();
				
			// invoke OnModFormSave event
      $tmp = array(
									'mode'	=> 'upd',
									'id'	=> $id
      );
			$modx->invokeEvent('OnModFormSave',$tmp);
			if($_POST['stay']!='') {
				$a = ($_POST['stay']=='2') ? "108&id=$id":"107";
				$header="Location: index.php?a=".$a."&r=2&stay=".$_POST['stay'];
			} else {
				$header='Location: index.php?a=106&r=2';
			}
			if($enable_sharedparams!==0) $modx->clearCache();
			header($header);
		}
        break;
    default:
    	// redirect to view modules
		header('Location: index.php?a=106&r=2');
}

// saves module user group access
function saveUserGroupAccessPermissons(){
	global $modx;
	global $id,$newid;
	
	$tbl_site_module_access = $modx->getFullTableName('site_module_access');
	
	if($newid) $id = $newid;
	$usrgroups = $_POST['usrgroups'];

	// check for permission update access
	if($modx->config['use_udperms']==1)
	{
		// delete old permissions on the module
		
		$rs = $modx->db->delete($tbl_site_module_access, "module='{$id}'");
		if(!$rs)
		{
			echo "An error occured while attempting to delete previous module user access permission entries.";
			exit;
		}
		elseif(is_array($usrgroups))
		{
			foreach ($usrgroups as $ugkey=>$value)
			{
				$f['module']    = $id;
				$f['usergroup'] = $modx->db->escape($value);
				$rs = $modx->db->insert($f,$tbl_site_module_access);
				if(!$rs)
				{
					echo "An error occured while attempting to save module user acess permissions.";
					exit;
				}
			}
		}
	}
}
