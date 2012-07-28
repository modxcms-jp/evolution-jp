<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if(!$modx->hasPermission('delete_template')) {
	$e->setError(3);
	$e->dumpError();
}
	$id = isset($_GET['id'])? intval($_GET['id']):0;
	$forced = isset($_GET['force'])? $_GET['force']:0;
	
	$tbl_site_content               = $modx->getFullTableName('site_content');
	$tbl_site_tmplvar_contentvalues = $modx->getFullTableName('site_tmplvar_contentvalues');
	$tbl_site_tmplvars              = $modx->getFullTableName('site_tmplvars');
	$tbl_site_tmplvar_templates     = $modx->getFullTableName('site_tmplvar_templates');
	$tbl_site_tmplvar_access        = $modx->getFullTableName('site_tmplvar_access');

	// check for relations
	if(!$forced) {
		$field = 'sc.id, sc.pagetitle,sc.description';
		$from  = "{$tbl_site_content} sc INNER JOIN {$tbl_site_tmplvar_contentvalues} stcv ON stcv.contentid=sc.id";
		$where = "stcv.tmplvarid='{$id}'";
		$rs = $modx->db->select($field,$from,$where);
		$count = $modx->db->getRecordCount($rs);
		if($count>0)
		{
			include_once "header.inc.php";
		?>	
			<script type="text/javascript">
				function deletedocument() {
					document.location.href="index.php?id=<?php echo $id;?>&a=303&force=1";
				}
			</script>
			<h1><?php echo $_lang['tmplvars']; ?></h1>

	<div id="actions">
		<ul class="actionButtons">
			<li><a href="#" onclick="deletedocument();"><img src="<?php echo $_style["icons_delete"] ?>" /> <?php echo $_lang["delete"]; ?></a></td>
			<li><a href="index.php?a=301&id=<?php echo $id;?>"><img src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang["cancel"]; ?></a></li>
		</ul>
	</div>

			<div class="sectionHeader"><?php echo $_lang['tmplvars']; ?></div>
			<div class="sectionBody">
		<?php
			echo "<p>".$_lang['tmplvar_inuse']."</p>";
			echo "<ul>";
			while($row = $modx->db->getRow($rs))
			{
				echo '<li><span style="width: 200px"><a href="index.php?id='.$row['id'].'&a=27">'.$row['pagetitle'].'</a></span>'.($row['description']!='' ? ' - '.$row['description'] : '').'</li>';
			}
			echo "</ul>";
			echo '</div>';		
			include_once "footer.inc.php";
			exit;
		}	
	}

	// invoke OnBeforeTVFormDelete event
	$modx->invokeEvent("OnBeforeTVFormDelete",
							array(
								"id"	=> $id
							));
						
	// delete variable
	$rs = $modx->db->delete($tbl_site_tmplvars, "id='{$id}'");
	if(!$rs) {
		echo "Something went wrong while trying to delete the field...";
		exit;
	} else {		
		header("Location: index.php?a=76");
	}

	// delete variable's content values
	$modx->db->delete($tbl_site_tmplvar_contentvalues, "tmplvarid='{$id}'");
	
	// delete variable's template access
	$modx->db->delete($tbl_site_tmplvar_templates, "tmplvarid='{$id}'");
	
	// delete variable's access permissions
	$modx->db->delete($tbl_site_tmplvar_access, "tmplvarid='{$id}'");

	// invoke OnTVFormDelete event
	$modx->invokeEvent("OnTVFormDelete",
							array(
								"id"	=> $id
							));
