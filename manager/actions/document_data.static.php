<?php
if (IN_MANAGER_MODE!='true') die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");

if (isset($_REQUEST['id']))
        $id = (int)$_REQUEST['id'];
else    $id = 0;

if (isset($_GET['opened'])) $_SESSION['openedArray'] = $_GET['opened'];

// Get table names (alphabetical)
$tbl_document_groups       = $modx->getFullTableName('document_groups');
$tbl_keyword_xref          = $modx->getFullTableName('keyword_xref');
$tbl_manager_users         = $modx->getFullTableName('manager_users');
$tbl_site_content          = $modx->getFullTableName('site_content');
$tbl_site_content_metatags = $modx->getFullTableName('site_content_metatags');
$tbl_site_keywords         = $modx->getFullTableName('site_keywords');
$tbl_site_metatags         = $modx->getFullTableName('site_metatags');
$tbl_site_templates        = $modx->getFullTableName('site_templates');

// Get access permissions
if($_SESSION['mgrDocgroups']) $docgrp = implode(",",$_SESSION['mgrDocgroups']);
$in_docgrp = !$docgrp ? '':" OR dg.document_group IN ({$docgrp})";
$access = "1='{$_SESSION['mgrRole']}' OR sc.privatemgr=0 {$in_docgrp}";


// Get the document content
$sql = 'SELECT DISTINCT sc.* '.
       "FROM {$tbl_site_content} AS sc ".
       "LEFT JOIN {$tbl_document_groups} AS dg ON dg.document = sc.id ".
       "WHERE sc.id ='{$id}' ".
       "AND ({$access})";
$rs = mysql_query($sql);
$limit = mysql_num_rows($rs);
if ($limit > 1) {
	echo "<p>Internal System Error...</p>",
	     "<p>More results returned than expected. </p>",
	     "<p><strong>Aborting...</strong></p>";
	exit;
} elseif ($limit == 0) {
	$e->setError(3);
	$e->dumpError();
}
$content = mysql_fetch_assoc($rs);

/**
 * "General" tab setup
 */
// Get Creator's username
$rs = mysql_query("SELECT username FROM {$tbl_manager_users} WHERE id='{$content['createdby']}'");
if ($row = mysql_fetch_assoc($rs))
	$createdbyname = $row['username'];

// Get Editor's username
$rs = mysql_query("SELECT username FROM {$tbl_manager_users} WHERE id='{$content['editedby']}'");
if ($row = mysql_fetch_assoc($rs))
	$editedbyname = $row['username'];

// Get Template name
$rs = mysql_query("SELECT templatename FROM {$tbl_site_templates} WHERE id='{$content['template']}'");
if ($row = mysql_fetch_assoc($rs))
	$templatename = $row['templatename'];

// Set the item name for logging
$_SESSION['itemname'] = $content['pagetitle'];

$keywords = array();
$metatags_selected = array();
if ($modx->config['show_meta'])
{
	// Get list of current keywords for this document
	$sql = "SELECT k.keyword FROM {$tbl_site_keywords} AS k, {$tbl_keyword_xref} AS x ".
	       "WHERE k.id = x.keyword_id AND x.content_id = '{$id}' ".
	       "ORDER BY k.keyword ASC";
	$rs = mysql_query($sql);
	while($row = mysql_fetch_assoc($rs))
	{
		$keywords[$i] = $row['keyword'];
	}
	
	// Get list of selected site META tags for this document
	$sql = "SELECT meta.id, meta.name, meta.tagvalue ".
	       "FROM {$tbl_site_metatags} AS meta ".
	       "LEFT JOIN {$tbl_site_content_metatags} AS sc ON sc.metatag_id = meta.id ".
	       "WHERE sc.content_id='{$content['id']}'";
	$rs = mysql_query($sql);
	while($row = mysql_fetch_assoc($rs))
	{
		$metatags_selected[] = $row['name'].': <i>'.$row['tagvalue'].'</i>';
	}
}

/**
 * "View Children" tab setup
 */
$maxpageSize = $modx->config['number_of_results'];
define('MAX_DISPLAY_RECORDS_NUM',$maxpageSize);

if (!class_exists('makeTable')) include_once $modx->config['base_path'].'manager/includes/extenders/maketable.class.php';
$childsTable = new makeTable();

// Get child document count
$sql = "SELECT DISTINCT sc.id ".
       "FROM {$tbl_site_content} AS sc ".
       "LEFT JOIN {$tbl_document_groups} AS dg ON dg.document = sc.id ".
       "WHERE sc.parent='{$content['id']}' ".
       "AND ({$access})";
$rs = $modx->db->query($sql);
$numRecords = $modx->db->getRecordCount($rs);

// Get child documents (with paging)
$sql = 'SELECT DISTINCT sc.* '.
       "FROM {$tbl_site_content} AS sc ".
       "LEFT JOIN {$tbl_document_groups} AS dg ON dg.document = sc.id ".
       "WHERE sc.parent='{$content['id']}' ".
       "AND ({$access}) ".
       "ORDER BY sc.isfolder DESC, sc.createdon DESC".
       $childsTable->handlePaging(); // add limit clause

if ($numRecords > 0) {
	if (!$rs = $modx->db->query($sql)) {
		// sql error
		$e->setError(1);
		$e->dumpError();
		include($modx->config['base_path'].'manager/includes/footer.inc.php');
		exit;
	} else {
		$resource = array();
		while($row = $modx->fetchRow($rs))
		{
			$resource[] = $row;
		}

		// CSS style for table
		$tableClass = 'grid';
		$rowHeaderClass = 'gridHeader';
		$rowRegularClass = 'gridItem';
		$rowAlternateClass = 'gridAltItem';

		$childsTable->setTableClass($tableClass);
		$childsTable->setRowHeaderClass($rowHeaderClass);
		$childsTable->setRowRegularClass($rowRegularClass);
		$childsTable->setRowAlternateClass($rowAlternateClass);

		// Table header
		$listTableHeader = array(
			'docid' =>  $_lang['id'],
			'title' =>  $_lang['resource_title'],
			'status' => $_lang['page_data_status'],
			'action' => $_lang['mgrlog_action'],
		);
		$tbWidth = array('5%', '60%', '10%', '25%');
		$childsTable->setColumnWidths($tbWidth);

		$limitClause = $childsTable->handlePaging();

		$listDocs = array();
		foreach($resource as $k => $children)
		{
			if($children['published'] == 0)
			{
				$status = '<span class="unpublishedDoc">'.$_lang['page_data_unpublished'].'</span>';
			}
			else
			{
				$status = '<span class="publishedDoc">'.$_lang['page_data_published'].'</span>';
			}
			if($children['isfolder'] == 0)
			{
				$link = 'index.php?a=27&amp;id=' . $children['id'];
				$pagetitle = '<img src="' . $_style['tree_page'] . '" />' . $children['pagetitle'];
				$pagetitle = '<a href="' . $link . '" style="color:#333;">' . $pagetitle . '</a>';
			}
			else
			{
				$link = "index.php?a=3&amp;id={$children['id']}&amp;tab=0";
				$pagetitle = '<img src="' . $_style['icons_folder'] . '" />' . $children['pagetitle'];
				$pagetitle = '<a href="' . $link . '" style="color:#333;">' . $pagetitle . '</a>';
			}
			
			$listDocs[] = array(
				'docid'  => $children['id'],
				'title'  => $pagetitle,
				'status' => $status,
				'action' => get_action_links($children)
			);
		}
		$childsTable->createPagingNavigation($numRecords,'a=3&amp;id='.$content['id']);
		$children_output = $childsTable->create($listDocs,$listTableHeader,'index.php?a=3&amp;id='.$content['id']);
	}
} else {
	// No Child documents
	$children_output = "<p>".$_lang['resources_in_container_no']."</p>";
}

?>
<script type="text/javascript">
function duplicatedocument(){
	if(confirm("<?php echo $_lang['confirm_resource_duplicate']?>")==true) {
		document.location.href="index.php?id=<?php echo $_REQUEST['id']?>&a=94";
	}
}
function deletedocument() {
	if(confirm("<?php echo $_lang['confirm_delete_resource']?>")==true) {
		document.location.href="index.php?id=<?php echo $_REQUEST['id']?>&a=6";
	}
}
function editdocument() {
	document.location.href="index.php?id=<?php echo $_REQUEST['id']?>&a=27";
}
function movedocument() {
	document.location.href="index.php?id=<?php echo $_REQUEST['id']?>&a=51";
}
</script>
<script type="text/javascript" src="media/script/tabpane.js"></script>
<script type="text/javascript" src="media/script/tablesort.js"></script>

	<h1><?php echo $_lang['doc_data_title']?></h1>
	
	<div id="actions">	
	  <ul class="actionButtons">
		  <li id="Button1">
			<a href="#" onclick="editdocument();"><img src="<?php echo $_style["icons_edit_document"] ?>" /> <?php echo $_lang['edit']?></a>
		  </li>
		  <li id="Button2">
			<a href="#" onclick="movedocument();"><img src="<?php echo $_style["icons_move_document"] ?>" /> <?php echo $_lang['move']?></a>
		  </li>
		  <li id="Button4">
		    <a href="#" onclick="duplicatedocument();"><img src="<?php echo $_style["icons_resource_duplicate"] ?>" /> <?php echo $_lang['duplicate']?></a>
		  </li>
		  <li id="Button3">
		    <a href="#" onclick="deletedocument();"><img src="<?php echo $_style["icons_delete_document"] ?>" /> <?php echo $_lang['delete']?></a>
		  </li>
		  <li id="Button6">
			<a href="#" onclick="<?php echo ($modx->config['friendly_urls'] == '1') ? "window.open('".$modx->makeUrl($id)."','previeWin')" : "window.open('../index.php?id=$id','previeWin')"; ?>"><img src="<?php echo $_style["icons_preview_resource"]?>" /> <?php echo $_lang['preview']?></a>
		  </li>
	  </ul>
	</div>

<div class="sectionHeader"><?php echo $_lang['page_data_title']?></div>
<div class="sectionBody">

<div class="tab-pane" id="childPane">
	<script type="text/javascript">
	docSettings = new WebFXTabPane( document.getElementById( "childPane" ), <?php echo $modx->config['remember_last_tab'] == 0 ? 'false' : 'true'; ?> );
	</script>

	<!-- View Children -->
	<div class="tab-page" id="tabChildren">
		<h2 class="tab"><?php echo $_lang['view_child_resources_in_container']?></h2>
		<script type="text/javascript">docSettings.addTabPage( document.getElementById( "tabChildren" ) );</script>
<?php if ($modx->hasPermission('new_document')) { ?>
	
			<ul class="actionButtons">
				<li><a href="index.php?a=4&amp;pid=<?php echo $content['id']?>"><img src="<?php echo $_style["icons_new_document"]; ?>" align="absmiddle" /> <?php echo $_lang['create_resource_here']?></a></li>
				<li><a href="index.php?a=72&amp;pid=<?php echo $content['id']?>"><img src="<?php echo $_style["icons_new_weblink"]; ?>" align="absmiddle" /> <?php echo $_lang['create_weblink_here']?></a></li>
			</ul>
<?php }
	if ($numRecords > 0)
		echo '<h4><span class="publishedDoc">'.$numRecords.'</span> '.$_lang['resources_in_container'].' (<strong>'.$content['pagetitle'].'</strong>)</h4>'."\n";
	echo $children_output."\n";
?>
	</div><!-- end tab-page -->

	<!-- General -->
	<div class="tab-page" id="tabdocInfo">
		<h2 class="tab"><?php echo $_lang['information']?></h2>
		<script type="text/javascript">docSettings.addTabPage( document.getElementById( "tabdocInfo" ) );</script>
		<div class="sectionBody">

		<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr><td colspan="2"><b><?php echo $_lang['page_data_general']?></b></td></tr>
			<tr><td width="200" valign="top"><?php echo $_lang['resource_title']?>: </td>
				<td><b><?php echo $content['pagetitle']?></b></td></tr>
			<tr><td width="200" valign="top"><?php echo $_lang['long_title']?>: </td>
				<td><small><?php echo $content['longtitle']!='' ? $content['longtitle'] : "(<i>".$_lang['not_set']."</i>)"?></small></td></tr>
			<tr><td valign="top"><?php echo $_lang['resource_description']?>: </td>
				<td><?php echo $content['description']!='' ? $content['description'] : "(<i>".$_lang['not_set']."</i>)"?></td></tr>
			<tr><td valign="top"><?php echo $_lang['resource_summary']?>: </td>
				<td><?php echo $content['introtext']!='' ? $content['introtext'] : "(<i>".$_lang['not_set']."</i>)"?></td></tr>
			<tr><td valign="top"><?php echo $_lang['type']?>: </td>
				<td><?php echo $content['type']=='reference' ? $_lang['weblink'] : $_lang['resource']?></td></tr>
			<tr><td valign="top"><?php echo $_lang['resource_alias']?>: </td>
				<td><?php echo $content['alias']!='' ? urldecode($content['alias']) : "(<i>".$_lang['not_set']."</i>)"?></td></tr>
			<?php if ($modx->config['show_meta']) {?>
			<tr><td valign="top"><?php echo $_lang['keywords']?>: </td>
				<td><?php // Keywords
				if(count($keywords) != 0)
					echo join($keywords, ", ");
				else    echo "(<i>".$_lang['not_set']."</i>)";
				?></td></tr>
			<tr><td valign="top"><?php echo $_lang['metatags']?>: </td>
				<td><?php // META Tags
				if(count($metatags_selected) != 0)
					echo join($metatags_selected, "<br /> ");
				else    echo "(<i>".$_lang['not_set']."</i>)";
				?></td></tr>
			<?php } ?>
		<tr><td colspan="2">&nbsp;</td></tr>
			<tr><td colspan="2"><b><?php echo $_lang['page_data_changes']?></b></td></tr>
			<tr><td><?php echo $_lang['page_data_created']?>: </td>
				<td><?php echo $modx->toDateFormat($content['createdon']+$server_offset_time)?> (<b><?php echo $createdbyname?></b>)</td></tr>
<?php				if ($editedbyname != '') { ?>
			<tr><td><?php echo $_lang['page_data_edited']?>: </td>
				<td><?php echo $modx->toDateFormat($content['editedon']+$server_offset_time)?> (<b><?php echo $editedbyname?></b>)</td></tr>
<?php				} ?>
		<tr><td colspan="2">&nbsp;</td></tr>
			<tr><td colspan="2"><b><?php echo $_lang['page_data_status']?></b></td></tr>
			<tr><td><?php echo $_lang['page_data_status']?>: </td>
				<td><?php echo $content['published']==0 ? '<span class="unpublishedDoc">'.$_lang['page_data_unpublished'].'</span>' : '<span class="publisheddoc">'.$_lang['page_data_published'].'</span>'?></td></tr>
			<tr><td><?php echo $_lang['page_data_publishdate']?>: </td>
				<td><?php echo $content['pub_date']==0 ? "(<i>".$_lang['not_set']."</i>)" : $modx->toDateFormat($content['pub_date'])?></td></tr>
			<tr><td><?php echo $_lang['page_data_unpublishdate']?>: </td>
				<td><?php echo $content['unpub_date']==0 ? "(<i>".$_lang['not_set']."</i>)" : $modx->toDateFormat($content['unpub_date'])?></td></tr>
			<tr><td><?php echo $_lang['page_data_cacheable']?>: </td>
				<td><?php echo $content['cacheable']==0 ? $_lang['no'] : $_lang['yes']?></td></tr>
			<tr><td><?php echo $_lang['page_data_searchable']?>: </td>
				<td><?php echo $content['searchable']==0 ? $_lang['no'] : $_lang['yes']?></td></tr>
			<tr><td><?php echo $_lang['resource_opt_menu_index']?>: </td>
				<td><?php echo $content['menuindex']?></td></tr>
			<tr><td><?php echo $_lang['resource_opt_show_menu']?>: </td>
				<td><?php echo $content['hidemenu']==1 ? $_lang['no'] : $_lang['yes']?></td></tr>
			<tr><td><?php echo $_lang['page_data_web_access']?>: </td>
				<td><?php echo $content['privateweb']==0 ? $_lang['public'] : '<b style="color: #821517">'.$_lang['private'].'</b> <img src="' . $style_path .'icons/secured.gif" align="absmiddle" width="16" height="16" />'?></td></tr>
			<tr><td><?php echo $_lang['page_data_mgr_access']?>: </td>
				<td><?php echo $content['privatemgr']==0 ? $_lang['public'] : '<b style="color: #821517">'.$_lang['private'].'</b> <img src="' . $style_path . 'icons/secured.gif" align="absmiddle" width="16" height="16" />'?></td></tr>
		<tr><td colspan="2">&nbsp;</td>	</tr>
			<tr><td colspan="2"><b><?php echo $_lang['page_data_markup']?></b></td></tr>
			<tr><td><?php echo $_lang['page_data_template']?>: </td>
				<td><?php echo $templatename ?></td></tr>
			<tr><td><?php echo $_lang['page_data_editor']?>: </td>
				<td><?php echo $content['richtext']==0 ? $_lang['no'] : $_lang['yes']?></td></tr>
			<tr><td><?php echo $_lang['page_data_folder']?>: </td>
				<td><?php echo $content['isfolder']==0 ? $_lang['no'] : $_lang['yes']?></td></tr>
		</table>
		</div><!-- end sectionBody -->
	</div><!-- end tab-page -->

	<!-- Page Source -->
	<div class="tab-page" id="tabSource">
		<h2 class="tab"><?php echo $_lang['page_data_source']?></h2>
		<script type="text/javascript">docSettings.addTabPage( document.getElementById( "tabSource" ) );</script>
		<?php
		$buffer = "";
		$filename = $modx->config['base_path']."assets/cache/docid_".$id.".pageCache.php";
		$handle = @fopen($filename, "r");
		if(!$handle) {
			$buffer = $_lang['page_data_notcached'];
		} else {
			while (!feof($handle)) {
				$buffer .= fgets($handle, 4096);
			}
			fclose($handle);
			$buffer = $_lang['page_data_cached'].'<p><textarea style="width: 100%; height: 400px;">'.htmlspecialchars($buffer)."</textarea>\n";
		}
		echo $buffer;
?>
	</div><!-- end tab-page -->
</div><!-- end documentPane -->
</div><!-- end sectionBody -->

<?php if ($show_preview==1) { ?>
<div class="sectionHeader"><?php echo $_lang['preview']?></div>
<div class="sectionBody" id="lyr2">
	<iframe src="../index.php?id=<?php echo $id?>&z=manprev" frameborder="0" border="0" id="previewIframe"></iframe>
</div>
<?php }

function get_action_links($children)
{
	global $modx,$_style,$_lang;
	
	$action_links_tpl = '<a href="index.php?a=3&amp;id=[+docid+]"><img src="[+icons_preview_resource+]" />[+lang_view_details+]</a>[+action_links+]';
	$ph = array();
	if($modx->hasPermission('edit_document'))
	{
		$ph['action_links']  = '&nbsp;<a href="index.php?a=27&amp;id=[+docid+]"><img src="[+icons_save+]" />[+lang_edit+]</a>';
		$ph['action_links'] .= '&nbsp;<a href="index.php?a=51&amp;id=[+docid+]"><img src="[+icons_move_document+]" />[+lang_move+]</a>';
	}
	else $ph['action_links'] = '';
	$ph['docid']                  = $children['id'];
	$ph['icons_preview_resource'] = $_style["icons_preview_resource"];
	$ph['lang_view_details']      = $_lang['click_to_view_details'];
	$ph['icons_save']             = $_style["icons_save"];
	$ph['lang_edit']              = $_lang['edit'];
	$ph['icons_move_document']    = $_style["icons_move_document"];
	$ph['lang_move']              = $_lang['move'];
	$action_links = $action_links_tpl;
	foreach($ph as $k=>$v)
	{
		$k = '[+' . $k . '+]';
		$action_links = str_replace($k,$v,$action_links);
	}
	return $action_links;
}
