<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if (!$modx->hasPermission('view_document')) {
	$e->setError(3);
	$e->dumpError();
}

if (isset($_REQUEST['id']))
        $id = (int)$_REQUEST['id'];
else {
	$e->setError(1);
	$e->dumpError();
}

$isAllowed = $modx->manager->isAllowed($id);
if (!$isAllowed)
{
	$e->setError(3);
	$e->dumpError();
}

$modx->updatePublishStatus();

// Get access permissions
if($_SESSION['mgrDocgroups']) $docgrp = implode(',',$_SESSION['mgrDocgroups']);
$in_docgrp = !isset($docgrp) || empty($docgrp) ? '':" OR dg.document_group IN ({$docgrp})";
$access = "1='{$_SESSION['mgrRole']}' OR sc.privatemgr=0 {$in_docgrp}";

// Get the document content
$from = "[+prefix+]site_content AS sc LEFT JOIN [+prefix+]document_groups AS dg ON dg.document = sc.id";
$where = "sc.id ='{$id}' AND ({$access})";
$rs = $modx->db->select('DISTINCT sc.*',$from,$where);
$content = $modx->db->getRow($rs);
$total = $modx->db->getRecordCount($rs);
if ($total > 1)
{
	echo "<p>Internal System Error...</p>",
	     "<p>More results returned than expected. </p>",
	     "<p><strong>Aborting...</strong></p>";
	exit;
}
elseif ($total == 0)
{
	$e->setError(3);
	$e->dumpError();
}

/**
 * "General" tab setup
 */
// Get Creator's username
$rs = $modx->db->select('username', '[+prefix+]manager_users',"id='{$content['createdby']}'");
if ($row = $modx->db->getRow($rs))
	$createdbyname = $row['username'];

// Get Editor's username
$rs = $modx->db->select('username', '[+prefix+]manager_users', "id='{$content['editedby']}'");
if ($row = $modx->db->getRow($rs))
	$editedbyname = $row['username'];

// Get Template name
$rs = $modx->db->select('templatename', '[+prefix+]site_templates', "id='{$content['template']}'");
if ($row = $modx->db->getRow($rs))
	$templatename = $row['templatename'];

// Set the item name for logging
$_SESSION['itemname'] = $content['pagetitle'];

foreach($content as $k=>$v)
{
	$content[$k] = htmlspecialchars($v, ENT_QUOTES, $modx->config['modx_charset']);
}

?>
	<script type="text/javascript">
	jQuery(function(){
		tpDocInfo = new WebFXTabPane( document.getElementById( "docInfo" ), false );
    });
	function duplicatedocument(){
		if(confirm("<?php echo $_lang['confirm_resource_duplicate'];?>")==true) {
			document.location.href="index.php?id=<?php echo $id;?>&a=94";
		}
	}
	function deletedocument() {
		if(confirm("<?php echo $_lang['confirm_delete_resource'];?>")==true) {
			document.location.href="index.php?id=<?php echo $id;?>&a=6";
		}
	}
	function editdocument() {
		document.location.href="index.php?id=<?php echo $id;?>&a=27";
	}
	function movedocument() {
		document.location.href="index.php?id=<?php echo $id;?>&a=51";
	}
	</script>
<h1><?php echo $_lang['doc_data_title']?></h1>

<div id="actions">
  <ul class="actionButtons">
<?php if($modx->hasPermission('save_document')):?>
	<li id="Button1"><a href="javascript:void(0)" onclick="editdocument();"><img src="<?php echo $_style["icons_edit_document"] ?>" /> <?php echo $_lang['edit']?></a></li>
<?php endif; ?>
<?php if($modx->hasPermission('save_document')):?>
	<li id="Button2"><a href="#" onclick="movedocument();"><img src="<?php echo $_style["icons_move_document"] ?>" /> <?php echo $_lang['move']?></a></li>
<?php endif; ?>
<?php if($modx->hasPermission('new_document')&&$modx->hasPermission('save_document')):?>
	<li id="Button4"><a href="#" onclick="duplicatedocument();"><img src="<?php echo $_style["icons_resource_duplicate"] ?>" /> <?php echo $_lang['duplicate']?></a></li>
<?php endif; ?>
<?php if($modx->hasPermission('delete_document') && $modx->hasPermission('save_document')):?>
	<li id="Button3"><a href="#" onclick="deletedocument();"><img src="<?php echo $_style["icons_delete_document"] ?>" /> <?php echo $_lang['delete']?></a></li>
<?php endif; ?>
	<li id="Button6"><a href="#" onclick="<?php echo ($modx->config['friendly_urls'] == '1') ? "window.open('".$modx->makeUrl($id)."','previeWin')" : "window.open('../index.php?id=$id','previeWin')"; ?>"><img src="<?php echo $_style["icons_preview_resource"]?>" /> <?php echo $_lang['view_resource']?></a></li>
    <li id="Button5"><a href="#" onclick="documentDirty=false;<?php
          	 if(isset($content['parent']) && $content['parent']!=='0')
          	 {
          		echo "document.location.href='index.php?a=120&id={$content['parent']}';";
          	 }
          	 elseif($_GET['pid'])
          	 {
          	 	$_GET['pid'] = intval($_GET['pid']);
          		echo "document.location.href='index.php?a=120&id={$_GET['pid']}';";
          	 }
          	 else
          	 {
          		echo "document.location.href='index.php?a=2';";
          	 }
          	?>"><img alt="icons_cancel" src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel']?></a></li>
  </ul>
</div>

<div class="sectionBody">
<div class="tab-pane" id="docInfo">

<style type="text/css">
h3 {font-size:1em;padding-bottom:0;margin-bottom:0;}
</style>
	<!-- General -->
	<div class="tab-page" id="tabDocInfo">
		<h2 class="tab"><?php echo $_lang['information']?></h2>
		<div class="sectionBody">
		<table>
			<tr><td width="200">ID: </td>
				<td><?php echo $content['id']?></td>
				<td>[*id*]</td>
			</tr>
			<tr><td width="200"><?php echo $_lang['page_data_template']?>: </td>
				<td><?php echo $templatename ?></td>
				<td>[*template*]</td>
			</tr>
			<tr><td><?php echo $_lang['resource_title']?>: </td>
				<td><?php echo $content['pagetitle']?></td>
				<td>[*pagetitle*]</td>
			</tr>
			<tr><td><?php echo $_lang['long_title']?>: </td>
				<td><?php echo $content['longtitle']!='' ? $content['longtitle'] : "(<i>".$_lang['not_set']."</i>)"?></td>
				<td>[*longtitle*]</td>
			</tr>
			<tr><td><?php echo $_lang['resource_description']?>: </td>
				<td><?php echo $content['description']!='' ? $content['description'] : "(<i>".$_lang['not_set']."</i>)"?></td>
				<td>[*description*]</td>
			</tr>
			<tr><td><?php echo $_lang['resource_summary']?>: </td>
				<td><?php echo $content['introtext']!='' ? $content['introtext'] : "(<i>".$_lang['not_set']."</i>)"?></td>
				<td>[*introtext*]</td>
			</tr>
			<tr><td><?php echo $_lang['type']?>: </td>
				<td><?php echo $content['type']=='reference' ? $_lang['weblink'] : $_lang['resource']?></td>
				<td>[*type*]</td>
			</tr>
			<tr><td><?php echo $_lang['resource_alias']?>: </td>
				<td><?php echo $content['alias']!='' ? $content['alias'] : "(<i>".$_lang['not_set']."</i>)"?></td>
				<td>[*alias*]</td>
			</tr>
			<tr><td width="200"><?php echo $_lang['page_data_created']?>: </td>
				<td><?php echo $modx->toDateFormat($content['createdon']+$server_offset_time)?> (<b><?php echo $createdbyname?></b>)</td>
				<td>[*createdon:date*]</td>
			</tr>
<?php				if ($editedbyname != '') { ?>
			<tr><td><?php echo $_lang['page_data_edited']?>: </td>
				<td><?php echo $modx->toDateFormat($content['editedon']+$server_offset_time)?> (<b><?php echo $editedbyname?></b>)</td>
				<td>[*editedon:date*]</td>
			</tr>
<?php				} ?>
			<tr><td width="200"><?php echo $_lang['page_data_status']?>: </td>
				<td><?php echo $content['published']==0 ? '<span class="unpublishedDoc">'.$_lang['page_data_unpublished'].'</span>' : '<span class="publisheddoc">'.$_lang['page_data_published'].'</span>'?></td>
				<td>[*published*]</td>
			</tr>
			<tr><td><?php echo $_lang['page_data_publishdate']?>: </td>
				<td><?php echo $content['pub_date']==0 ? "(<i>".$_lang['not_set']."</i>)" : $modx->toDateFormat($content['pub_date'])?></td>
				<td>[*pub_date:date*]</td>
			</tr>
			<tr><td><?php echo $_lang['page_data_unpublishdate']?>: </td>
				<td><?php echo $content['unpub_date']==0 ? "(<i>".$_lang['not_set']."</i>)" : $modx->toDateFormat($content['unpub_date'])?></td>
				<td>[*unpub_date:date*]</td>
			</tr>
			<tr><td><?php echo $_lang['page_data_cacheable']?>: </td>
				<td><?php echo $content['cacheable']==0 ? $_lang['no'] : $_lang['yes']?></td>
				<td>[*cacheable*]</td>
			</tr>
			<tr><td><?php echo $_lang['page_data_searchable']?>: </td>
				<td><?php echo $content['searchable']==0 ? $_lang['no'] : $_lang['yes']?></td>
				<td>[*searchable*]</td>
			</tr>
			<tr><td><?php echo $_lang['resource_opt_menu_index']?>: </td>
				<td><?php echo $content['menuindex']?></td>
				<td>[*menuindex*]</td>
			</tr>
			<tr><td><?php echo $_lang['resource_opt_show_menu']?>: </td>
				<td><?php echo $content['hidemenu']==1 ? $_lang['no'] : $_lang['yes']?></td>
				<td>[*hidemenu*]</td>
			</tr>
			<tr><td><?php echo $_lang['page_data_web_access']?>: </td>
				<td><?php echo $content['privateweb']==0 ? $_lang['public'] : '<b style="color: #821517">'.$_lang['private'].'</b> <img src="media/style/' . $modx->config['manager_theme'] .'/images/icons/secured.gif" align="absmiddle" />'?></td>
				<td>[*privateweb*]</td>
			</tr>
			<tr><td><?php echo $_lang['page_data_mgr_access']?>: </td>
				<td><?php echo $content['privatemgr']==0 ? $_lang['public'] : '<b style="color: #821517">'.$_lang['private'].'</b> <img src="media/style/' . $modx->config['manager_theme'] .'/images/icons/secured.gif" align="absmiddle" />'?></td>
				<td>[*privatemgr*]</td>
			</tr>
			<tr><td width="200"><?php echo $_lang['page_data_template']?>: </td>
				<td><?php echo $templatename ?></td>
				<td>[*template*]</td>
			</tr>
			<tr><td><?php echo $_lang['page_data_editor']?>: </td>
				<td><?php echo $content['richtext']==0 ? $_lang['no'] : $_lang['yes']?></td>
				<td>[*richtext*]</td>
			</tr>
			<tr><td><?php echo $_lang['page_data_folder']?>: </td>
				<td><?php echo $content['isfolder']==0 ? $_lang['no'] : $_lang['yes']?></td>
				<td>[*isfolder*]</td>
			</tr>
		</table>
		</div><!-- end sectionBody -->
	</div><!-- end tab-page -->
<?php
		$cache_path = "{$modx->config['base_path']}assets/cache/docid_{$id}.pageCache.php";
		$cache = @file_get_contents($cache_path);
		if($cache) :
			$cache = htmlspecialchars($cache, ENT_QUOTES, $modx->config['modx_charset']);
			$cache = $_lang['page_data_cached'].'<p><textarea style="width: 100%; height: 400px;">'.$cache."</textarea>\n";
?>
	<!-- Page Source -->
	<div class="tab-page" id="tabSource">
		<h2 class="tab"><?php echo $_lang['page_data_source']?></h2>
		<?php echo $cache;?>
	</div><!-- end tab-page -->
<?php endif;?>
</div><!-- end documentPane -->
</div><!-- end sectionBody -->
