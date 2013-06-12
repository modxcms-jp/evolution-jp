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
<ul>
<style type="text/css">
li span {width: 200px;}
</style>
<?php

$rs = $modx->db->select('name, id, description','[+prefix+]user_roles','','name');
$total = $modx->db->getRecordCount($rs);
if($total<1){
	echo "The request returned no roles!</div>";
	exit;
	include_once "footer.inc.php";
}
$tpl       = '<li><span><a href="index.php?id=[+id+]&a=35">([+id+]) [+name+]</a></span> - [+description+]</li>';
$admin_tpl = '<li><span><i>([+id+]) [+name+]</i></span> - <i>[+administrator_role_message+]</i></li>';

while($ph = $modx->db->getRow($rs))
{
	if($ph['id']==='1')
	{
		$ph['administrator_role_message'] = $_lang['administrator_role_message'];
		echo $modx->parsePlaceholder($admin_tpl, $ph);
	}
	else echo $modx->parsePlaceholder($tpl, $ph);
}
?>
</ul>
</div>
</div>
