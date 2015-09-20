<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if(!$modx->hasPermission('edit_role')) {
	$e->setError(3);
	$e->dumpError();
}
?>
<br />
<!-- User Roles -->

<h1><?php echo $_lang['role_management_title']; ?></h1>

<div id="actions">
  <ul class="actionButtons">
      <li id="Button5"><a href="#" onclick="documentDirty=false;document.location.href='index.php?a=2';"><img alt="icons_cancel" src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel']?></a></li>
  </ul>
</div>

<div class="section">
<div class="sectionBody">
<p><?php echo $_lang['role_management_msg']; ?></p>

<ul class="actionButtons">
	<li><a class="default" href="index.php?a=38"><img src="<?php echo $_style["icons_add"] ?>" /> <?php echo $_lang['new_role']; ?></a></li>
</ul>
<?php

$tbl_user_roles = $modx->getFullTableName('user_roles');
$rs = $modx->db->select('name, id, description',$tbl_user_roles,'','name');
$total = $modx->db->getRecordCount($rs);
if($total<1){
	echo "The request returned no roles!</div>";
	exit;
	include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
}
?>
<ul>
<style type="text/css">
li span {width: 200px;}
</style>
<?php
while($row = $modx->db->getRow($rs))
{
	if($row['id']==1)
	{
?>
	<li><span style="width: 200px"><i><?php echo "({$row['id']}) {$row['name']}"; ?></i></span> - <i><?php echo $_lang['administrator_role_message']; ?></i></li>
<?php
	}
	else
	{
?>
	<li><span style="width: 200px"><a href="index.php?id=<?php echo $row['id']; ?>&a=35"><?php echo "({$row['id']}) {$row['name']}"; ?></a></span> - <?php echo $row['description']; ?></li>
<?php
	}
}

?>
</ul>
</div>
</div>
