<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
if(!$modx->hasPermission('edit_document'))   {$e->setError(3);$e->dumpError();}

// ok, two things to check.
// first, document cannot be moved to itself
// second, new parent must be a folder. If not, set it to folder.
if($_REQUEST['id']==$_REQUEST['new_parent']) {$e->setError(600); $e->dumpError();}
if($_REQUEST['id']=='')                      {$e->setError(601); $e->dumpError();}
if($_REQUEST['new_parent']=='')              {$e->setError(602); $e->dumpError();}

$tbl_site_content = $modx->getFullTableName('site_content');
$doc_id = $_REQUEST['id'];
$sql = "SELECT parent FROM {$tbl_site_content} WHERE id={$doc_id};";
$rs = mysql_query($sql);
if(!$rs)
{
	echo "An error occured while attempting to find the document's current parent.";
}
$row = mysql_fetch_assoc($rs);

$current_parent = $row['parent'];
$new_parent = intval($_REQUEST['new_parent']);

// check user has permission to move document to chosen location

if ($use_udperms == 1)
{
	if ($current_parent != $new_parent)
	{
		include_once MODX_MANAGER_PATH . 'processors/user_documents_permissions.class.php';
		$udperms = new udperms();
		$udperms->user = $modx->getLoginUserID();
		$udperms->document = $new_parent;
		$udperms->role = $_SESSION['mgrRole'];

		 if (!$udperms->checkPermissions())
		 {
			include_once('header.inc.php');
			?>
			<script type="text/javascript">parent.tree.ca = '';</script>
			<br /><br /><div class="sectionHeader"><?php echo $_lang['access_permissions']; ?></div>
			<div class="sectionBody">
			<p><?php echo $_lang['access_permission_parent_denied']; ?></p>
			</div>
			<?php
			include_once('footer.inc.php');
			exit;
		}
	}
}
$children= allChildren($doc_id);

if (!array_search($new_parent, $children))
{
	$sql = "UPDATE {$tbl_site_content} SET isfolder=1 WHERE id={$new_parent};";
	$rs = mysql_query($sql);
	if(!$rs)
	{
		echo "An error occured while attempting to change the new parent to a folder.";
	}

	// increase menu index
	if (is_null($modx->config['auto_menuindex']) || $modx->config['auto_menuindex'])
	{
		$sql = "SELECT max(menuindex) FROM {$tbl_site_content} WHERE parent='{$new_parent}'";
		$menuindex = $modx->db->getValue($sql)+1;
	}
	else $menuindex = 0;

	$user_id = $modx->getLoginUserID();
	$now     = time();
	$sql = "UPDATE {$tbl_site_content} SET parent={$new_parent}, editedby={$user_id}, editedon={$now}, menuindex={$menuindex} WHERE id={$doc_id};";
	$rs = mysql_query($sql);
	if(!$rs)
	{
		echo "An error occured while attempting to move the document to the new parent.";
	}

	// finished moving the document, now check to see if the old_parent should no longer be a folder.
	$sql = "SELECT count(id) FROM {$tbl_site_content} WHERE parent={$current_parent};";
	$rs = mysql_query($sql);
	if(!$rs)
	{
		echo "An error occured while attempting to find the old parents' children.";
	}
	$row = mysql_fetch_assoc($rs);
	$limit = $row['count(id)'];

	if(!$limit>0)
	{
		$sql = "UPDATE {$tbl_site_content} SET isfolder=0 WHERE id={$current_parent};";
		$rs = mysql_query($sql);
		if(!$rs)
		{
			echo 'An error occured while attempting to change the old parent to a regular document.';
		}
	}
}
else
{
	echo 'You cannot move a document to a child document!';
}
empty_cache($id,$new_parent);
exit;



function empty_cache($id,$new_parent)
{
	// empty cache & sync site
	include_once MODX_MANAGER_PATH . 'processors/cache_sync.class.processor.php';
	$sync = new synccache();
	$sync->setCachepath(MODX_BASE_PATH . 'assets/cache/');
	$sync->setReport(false);
	$sync->emptyCache(); // first empty the cache
	$header="Location: index.php?a=3&id={$new_parent}&tab=0&r=1";
	header($header);
}

function allChildren($docid)
{
	global $modx;
	$tbl_site_content = $modx->getFullTableName('site_content');
	$children= array();
	$sql = "SELECT id FROM {$tbl_site_content} WHERE parent = {$docid};";
	if(!$rs = $modx->db->query($sql))
	{
		echo "An error occured while attempting to find all of the document's children.";
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
