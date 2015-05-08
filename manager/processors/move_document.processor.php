<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('edit_document'))   {$e->setError(3);$e->dumpError();}

if($_REQUEST['id']==$_REQUEST['new_parent']) {$e->setError(600); $e->dumpError();}
if($_REQUEST['id']=='')                      {$e->setError(601); $e->dumpError();}
if($_REQUEST['new_parent']=='')              {echo '<script type="text/javascript">parent.tree.ca = "open";</script>';$e->setError(602); $e->dumpError();}

$tbl_site_content = $modx->getFullTableName('site_content');
$doc_id = $_REQUEST['id'];
if(strpos($doc_id,','))
{
	$doc_ids = explode(',',$doc_id);
	$doc_id = substr($doc_id,0,strpos($doc_id,','));
}
else $doc_ids[] = $doc_id;

$rs = $modx->db->select('parent',$tbl_site_content,"id='{$doc_id}'");
if(!$rs)
{
	echo "An error occured while attempting to find the resource's current parent.";
	exit;
}
$current_parent = $modx->db->getValue($rs);
$new_parent = intval($_REQUEST['new_parent']);

// check user has permission to move resource to chosen location
if ($modx->config['use_udperms'] == 1 && $current_parent != $new_parent)
{
	if (!$modx->checkPermissions($new_parent))
	{
		include_once('header.inc.php');
		?>
		<script type="text/javascript">parent.tree.ca = '';</script>
		<div class="sectionHeader"><?php echo $_lang['access_permissions']; ?></div>
		<div class="sectionBody">
		<p><?php echo $_lang['access_permission_parent_denied']; ?></p>
		</div>
		<?php
		include_once('footer.inc.php');
		exit;
	}
}
$children= allChildren($doc_id);
if($current_parent == $new_parent)
{
	$alert = $_lang["move_resource_new_parent"];
}
elseif (array_search($new_parent, $children)!==false)
{
	$alert = $_lang["move_resource_cant_myself"];
}
else
{
	$rs = $modx->db->update('isfolder=1',$tbl_site_content,"id='{$new_parent}'");
	if(!$rs)
		$alert = "An error occured while attempting to change the new parent to a folder.";

	// increase menu index
	if (is_null($modx->config['auto_menuindex']) || $modx->config['auto_menuindex'])
	{
		$menuindex = $modx->db->getValue($modx->db->select('max(menuindex)',$tbl_site_content,"parent='{$new_parent}'"))+1;
	}
	else $menuindex = 0;

	$user_id = $modx->getLoginUserID();
	if(is_array($doc_ids))
	{
		foreach($doc_ids as $v)
		{
			update_parentid($v,$new_parent,$user_id,$menuindex);
			$menuindex++;
		}
	}

	// finished moving the resource, now check to see if the old_parent should no longer be a folder.
	$rs = $modx->db->select('count(id)',$tbl_site_content,"parent='{$current_parent}'");
	if(!$rs)
		$alert = "An error occured while attempting to find the old parents' children.";
	
	$row = $modx->db->getRow($rs);
	$limit = $row['count(id)'];

	if(!$limit>0)
	{
		$rs = $modx->db->update('isfolder=0',$tbl_site_content,"id='{$current_parent}'");
		if(!$rs)
			$alert = 'An error occured while attempting to change the old parent to a regular resource.';
	}
}

if(!isset($alert))
{
	$modx->clearCache();
	if($new_parent!==0) $header="Location: index.php?a=120&id={$new_parent}&r=1";
	else                $header="Location: index.php?a=2&r=1";
	header($header);
}
else
{
	$url = "javascript:parent.tree.ca='open';window.location.href='index.php?a=51&id={$doc_id}';";
	$modx->webAlertAndQuit($alert, $url);
	exit;
}



function allChildren($docid)
{
	global $modx;
	$tbl_site_content = $modx->getFullTableName('site_content');
	$children= array();
	$rs = $modx->db->select('id',$tbl_site_content,"parent='{$docid}'");
	if(!$rs)
	{
		echo "An error occured while attempting to find all of the resource's children.";
		exit;
	}
	else
	{
		if ($numChildren= $modx->db->getRecordCount($rs))
		{
			while ($child= $modx->db->getRow($rs))
			{
				$children[]= $child['id'];
				$nextgen= array();
				$nextgen= allChildren($child['id']);
				$children= array_merge($children, $nextgen);
			}
		}
	}
	return $children;
}

function update_parentid($doc_id,$new_parent,$user_id,$menuindex)
{
	global $modx, $_lang;
	$tbl_site_content = $modx->getFullTableName('site_content');
	if (!$modx->config['allow_duplicate_alias'])
	{
		$rs = $modx->db->select("IF(alias='', id, alias) AS alias",$tbl_site_content, "id='{$doc_id}'");
		$alias = $modx->db->getValue($rs);
		$rs = $modx->db->select('id',$tbl_site_content, "parent='{$new_parent}' AND (alias='{$alias}' OR (alias='' AND id='{$alias}'))");
		$find = $modx->db->getRecordcount($rs);
		if(0<$find)
		{
			$target_id = $modx->db->getValue($rs);
			$url = "javascript:parent.tree.ca='open';window.location.href='index.php?a=27&id={$doc_id}';";
			$modx->webAlertAndQuit(sprintf($_lang["duplicate_alias_found"], $target_id, $alias), $url);
			exit;
		}
	}
	$field['parent']    = $new_parent;
	$field['editedby']  = $user_id;
	$field['menuindex'] = $menuindex;
	$rs = $modx->db->update($field,$tbl_site_content,"id='{$doc_id}'");
	if(!$rs)
	{
		echo "An error occured while attempting to move the resource to the new parent.";
		exit;
	}
}
