<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if (!$modx->hasPermission('view_document')) {
	$e->setError(3);
	$e->dumpError();
}
if (isset($_REQUEST['id']))
        $id = (int)$_REQUEST['id'];
else    $id = 0;

if (isset($_GET['pid']))    $_GET['pid'] = intval($_GET['pid']);

$modx->checkPublishStatus();

// Get access permissions
if($_SESSION['mgrDocgroups']) $docgrp = implode(',',$_SESSION['mgrDocgroups']);
$in_docgrp = !isset($docgrp) || empty($docgrp) ? '':" OR dg.document_group IN ({$docgrp})";
$access = "1='{$_SESSION['mgrRole']}' OR sc.privatemgr=0 {$in_docgrp}";

if($id!=0)
{
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
}
else $content = array();

/**
 * "View Children" tab setup
 */

// Get child document count
$from = array();
$from[] = '[+prefix+]site_content AS sc';
$from[] = 'LEFT JOIN [+prefix+]document_groups AS dg ON dg.document = sc.id';
$from[] = "LEFT JOIN [+prefix+]site_revision rev on rev.id = sc.id AND (rev.status='draft' OR rev.status='pending' OR rev.status='future') AND rev.element='resource' ";
$from = join(' ',$from);
$where = "sc.parent='{$id}' AND ({$access})";
$orderby ='sc.isfolder DESC, sc.published ASC, sc.publishedon DESC, if(sc.editedon=0,10000000000,sc.editedon) DESC, sc.id DESC';
$offset = (isset($_GET['page']) && preg_match('@^[0-9]+$@',$_GET['page']) && $_GET['page'] > 0) ? $_GET['page'] - 1 : 0;
define('MAX_DISPLAY_RECORDS_NUM',$modx->config['number_of_results']);
$limit = ($offset * MAX_DISPLAY_RECORDS_NUM) . ', ' . MAX_DISPLAY_RECORDS_NUM;
$rs = $modx->db->select('DISTINCT sc.*,rev.status',$from,$where,$orderby,$limit);
$numRecords = $modx->db->getRecordCount($rs);

if ($numRecords > 0)
{
	if (!$rs)
	{
		$e->setError(1); // sql error
		$e->dumpError();
		include(MODX_CORE_PATH . 'footer.inc.php');
		exit;
	}
	else
	{
		$resource = array();
		while($row = $modx->db->getRow($rs))
		{
			$resource[] = $row;
		}


		// context menu
		include_once(MODX_CORE_PATH . 'controls/contextmenu.php');
		$cm = new ContextMenu("cntxm", 180);
		// $cm->addSeparator();
		$cm->addItem($_lang["edit_resource"],       "js:menuAction(27)",$_style['icons_edit_document'],($modx->hasPermission('edit_document') ? 0:1));
		$cm->addItem($_lang["create_resource_here"],"js:menuAction(4)",$_style['icons_new_document'],($modx->hasPermission('new_document') ? 0:1));
		$cm->addItem($_lang["move_resource"],       "js:menuAction(51)",$_style['icons_move_document'],($modx->hasPermission('save_document') ? 0:1));
		$cm->addItem($_lang["resource_duplicate"],  "js:menuAction(94)",$_style['icons_resource_duplicate'],($modx->hasPermission('new_document') ? 0:1));
		$cm->addSeparator();
		$cm->addItem($_lang["publish_resource"],   "js:menuAction(61)",$_style['icons_publish_document'],($modx->hasPermission('publish_document') ? 0:1));
		$cm->addItem($_lang["unpublish_resource"], "js:menuAction(62)",$_style['icons_unpublish_resource'],($modx->hasPermission('publish_document') ? 0:1));
		$cm->addItem($_lang["delete_resource"],    "js:menuAction(6)",$_style['icons_delete'],($modx->hasPermission('delete_document') ? 0:1));
		$cm->addItem($_lang["undelete_resource"],  "js:menuAction(63)",$_style['icons_undelete_resource'],($modx->hasPermission('delete_document') ? 0:1));
		$cm->addSeparator();
		$cm->addItem($_lang["create_weblink_here"], "js:menuAction(72)",$_style['icons_weblink'],($modx->hasPermission('new_document') ? 0:1));
		$cm->addSeparator();
		$cm->addItem($_lang["resource_overview"], "js:menuAction(3)",$_style['icons_resource_overview'],($modx->hasPermission('view_document') ? 0:1));
		//$cm->addItem($_lang["preview_resource"], "js:menuAction(999)",$_style['icons_preview_resource'],0);
		echo $cm->render();
		
		echo get_jscript($id,$cm);
		
		$docs = array();
		
		foreach($resource as $k => $children)
		{
			$isAllowed = $modx->manager->isContainAllowed($children['id']);
			if(!$isAllowed) continue;
			foreach($children as $k=>$v)
			{
				$children[$k] = htmlspecialchars($v, ENT_QUOTES, $modx->config['modx_charset']);
			}
			if($children['published'] == 0 && ($_SERVER['REQUEST_TIME'] < $children['pub_date'] || $children['unpub_date'] < $_SERVER['REQUEST_TIME']))
			{
				$status = '<span class="unpublishedDoc">'.$_lang['page_data_unpublished'].'</span>';
			}
			else
			{
				$status = '<span class="publishedDoc">'.$_lang['page_data_published'].'</span>';
			}
			$description = $children['description'];
			$len_title = mb_strlen($children['pagetitle'], $modx->config['modx_charset']);
			$len_desc  = mb_strlen($description, $modx->config['modx_charset']);
			$len_total = $len_title + $len_desc;
			if($len_total < 50)
			{
				if(!empty($description)) $description = ' <span style="color:#777;">' . $description . '</span>';
			}
			else
			{
				$description = '<br /><div style="color:#777;">' . $description . '</div>';
			}
			
			$classes = array();
			$classes[] = 'withmenu';
			if($children['deleted']==='1')   $classes[] = 'deletedNode';
			if($children['published']==='0') $classes[] = 'unpublishedNode';
			$class = ' class="' . join(' ',$classes) . '"';
			
			$tpl = '<span [+class+] oncontextmenu="document.getElementById(\'icon[+id+]\').onclick(event);return false;">[+pagetitle+]</span>';
			$pagetitle = str_replace(array('[+class+]','[+pagetitle+]','[+id+]'),
			                         array($class,$children['pagetitle'],$children['id']),$tpl);
			
			if($children['isfolder'] == 0)
			{
				$link = "index.php?a=27&amp;id={$children['id']}";
				if($modx->config['site_start']==$children['id'])
					$iconpath = $_style['tree_page_home'];
				elseif($modx->config['error_page']==$children['id'])
					$iconpath = $_style['tree_page_404'];
				elseif($modx->config['site_unavailable_page']==$children['id'])
					$iconpath = $_style['tree_page_hourglass'];
				elseif($modx->config['unauthorized_page']==$children['id'])
					$iconpath = $_style['tree_page_info'];
				else
					$iconpath = $_style['tree_page_html'];
			}
			else
			{
				$link = "index.php?a=120&amp;id={$children['id']}";
				$iconpath = $_style['tree_folder'];
			}
			
			if( $children['type']==='reference')
			{
				$pagetitle = '<img src="' . $_style['tree_weblink'] . '" /> ' . $pagetitle;
			}
			$tpl = '';
			$tpl = '<img src="[+iconpath+]" id="icon[+id+]" onclick="return showContentMenu([+id+],event);" />';
			$icon = str_replace(array('[+iconpath+]','[+id+]'),array($iconpath,$children['id']),$tpl);
			$tpl = '<div style="float:left;">[+icon+]</div><a href="[+link+]" style="overflow:hidden;display:block;color:#333;">[+pagetitle+][+$description+]</a>';
			$title = str_replace(array('[+icon+]','[+link+]','[+pagetitle+]','[+$description+]'),
			                     array($icon,$link,$pagetitle,$description), $tpl);
			
			if($children['publishedon']!=='0')
			{
				$publishedon = '<span class="nowrap">' . $modx->toDateFormat($children['publishedon']) . '</span>';
			}
			elseif(!empty($children['pub_date']))
			{
				$publishedon = '<span class="nowrap disable">' . $modx->toDateFormat($children['pub_date']) . '</span>';
			}
			else $publishedon = '-';
			
			if($children['editedon']!=='0')
			{
				$editedon = '<span class="nowrap">' . $modx->toDateFormat($children['editedon']) . '</span>';
			}
			else $editedon = '-';
			
			$doc = array();
			$doc['checkbox']    = '<input type="checkbox" name="batch[]" value="' . $children['id'] . '" />';
			$doc['docid']       = $children['id'];
			$doc['title']       = $title;
			$doc['publishedon'] = $publishedon;
			$doc['editedon']    = $editedon;
			$doc['status']      = $status;
			$docs[] = $doc;
		}
		
		$modx->loadExtension('MakeTable');
		
		// CSS style for table
		$modx->table->setTableClass('grid');
		$modx->table->setRowHeaderClass('gridHeader');
		$modx->table->setRowRegularClass('gridItem');
		$modx->table->setRowAlternateClass('gridAltItem');
		
		$modx->table->setColumnWidths('2%, 2%, 68%, 10%, 10%, 8%');
		
		// Table header
		$header['checkbox']    = '<input type="checkbox" name="chkselall" onclick="selectAll()" />';
		$header['docid']       = $_lang['id'];
		$header['title']       = $_lang['resource_title'];
		$header['publishedon'] = $_lang['publish_date'];
		$header['editedon']    = $_lang['editedon'];
		$header['status']      = $_lang['page_data_status'];
		
		
		$pageNavBlock = $modx->table->createPagingNavigation($numRecords,"a=120&amp;id={$id}");
		$children_output = $pageNavBlock . $modx->table->create($docs,$header) . $pageNavBlock;
		$children_output .= '<div style="margin-top:10px;"><input type="submit" value="' . $_lang["document_data.static.php1"] . '" /></div>';
	}
}
else
{
	// No Child documents
	$children_output = "<p>".$_lang['resources_in_container_no']."</p>";
}

?>
	<script type="text/javascript">
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
	<script type="text/javascript" src="media/script/tablesort.js"></script>
	<h1><?php echo $_lang['view_child_resources_in_container']?></h1>
	
	<div id="actions">
	  <ul class="actionButtons">
<?php
	$tpl = '<li id="%s"><a href="#" onclick="%s"><img src="%s" /> %s</a></li>';
	if($modx->hasPermission('save_document') && $id!=0 && $modx->manager->isAllowed($id))
		echo sprintf($tpl,'Button1', 'editdocument();', $_style["icons_edit_document"], $_lang['edit']);
	if($modx->hasPermission('save_document') && $id!=0 && $modx->manager->isAllowed($id))
		echo sprintf($tpl,'Button2', 'movedocument();', $_style["icons_move_document"], $_lang['move']);
	if($modx->hasPermission('new_document') && $id!=0 && $modx->manager->isAllowed($id))
		echo sprintf($tpl,'Button4', 'duplicatedocument();', $_style["icons_resource_duplicate"], $_lang['duplicate']);
	if($modx->hasPermission('delete_document') && $modx->hasPermission('save_document') && $id!=0 && $modx->manager->isAllowed($id))
		echo sprintf($tpl,'Button3', 'deletedocument();', $_style["icons_delete_document"], $_lang['delete']);
	
	$url = $modx->makeUrl($id);
	$prev = "window.open('{$url}','previeWin')";
	echo sprintf($tpl,'Button6', $prev, $_style["icons_preview_resource"], $_lang['view_resource']);
	$action = getReturnAction($content);
	$action = "documentDirty=false;document.location.href='{$action}'";
	echo sprintf($tpl,'Button5', $action, $_style["icons_cancel"], $_lang['cancel']);
?>
	  </ul>
	</div>

<div class="section">
<div class="sectionBody">
	<!-- View Children -->
<?php if ($modx->hasPermission('new_document')) { ?>
	
			<ul class="actionButtons">
				<li><a href="index.php?a=4&amp;pid=<?php echo $id?>"><img src="<?php echo $_style["icons_new_document"]; ?>" align="absmiddle" /> <?php echo $_lang['create_resource_here']?></a></li>
				<li><a href="index.php?a=72&amp;pid=<?php echo $id?>"><img src="<?php echo $_style["icons_new_weblink"]; ?>" align="absmiddle" /> <?php echo $_lang['create_weblink_here']?></a></li>
			</ul>
<?php }
	if ($numRecords > 0)
		$topicPath = getTopicPath($id);
		echo <<< EOT
<script type="text/javascript">
	function selectAll() {
		var f = document.forms['mutate'];
		var c = f.elements['batch[]'];
		for(i=0;i<c.length;i++){
			c[i].checked=f.chkselall.checked;
		}
	}
</script>
{$topicPath}
<form name="mutate" id="mutate" class="content" method="post" enctype="multipart/form-data" action="index.php">
<input type="hidden" name="a" value="51" />
{$children_output}
</form>
EOT;
?>
<style type="text/css">
h3 {font-size:1em;padding-bottom:0;margin-bottom:0;}
</style>

</div>
</div>

<?php
function get_jscript($id,$cm)
{
	global $modx, $_lang, $modx_textdir;
	
	$contextm = $cm->getClientScriptObject();
	$textdir = $modx_textdir==='rtl' ? '-190' : '';
	$page = (isset($_GET['page'])) ? " + '&page={$_GET['page']}'" : '';
	
	$block = <<< EOT
<style type="text/css">
a span.withmenu {border:1px solid transparent;}
a span.withmenu:hover {border:1px solid #ccc;background-color:#fff;}
.nowrap {white-space:nowrap;}
.disable {color:#777;}
</style>
<script type="text/javascript">
	var selectedItem;
	var contextm = {$contextm};
	function showContentMenu(id,e){
		selectedItem=id;
		//offset menu if RTL is selected
		contextm.style.left = (e.pageX || (e.clientX + (document.documentElement.scrollLeft || document.body.scrollLeft))){$textdir}+10+"px";
		contextm.style.top = (e.pageY || (e.clientY + (document.documentElement.scrollTop || document.body.scrollTop)))-150 + 'px';
		contextm.style.visibility = "visible";
		e.cancelBubble=true;
		return false;
	}

	function menuAction(a) {
		var id = selectedItem;
		switch(a) {
			case 27:		// edit
				window.location.href='index.php?a=27&id='+id;
				break;
			case 4: 		// new Resource
				window.location.href='index.php?a=4&pid='+id;
				break;
			case 51:		// move
				window.location.href='index.php?a=51&id='+id{$page};
				break;
			case 94:		// duplicate
				if(confirm("{$_lang['confirm_resource_duplicate']}")==true)
				{
					window.location.href='index.php?a=94&id='+id{$page};
				}
				break;
			case 61:		// publish
				if(confirm("{$_lang['confirm_publish']}")==true)
				{
					window.location.href='index.php?a=61&id='+id{$page};
				}
				break;
			case 62:		// unpublish
				if (id != {$modx->config['site_start']})
				{
					if(confirm("{$_lang['confirm_unpublish']}")==true)
					{
						window.location.href="index.php?a=62&id=" + id{$page};
					}
				}
				else
				{
					alert('Document is linked to site_start variable and cannot be unpublished!');
				}
				break;
			case 6: 		// delete
				if(confirm("{$_lang['confirm_delete_resource']}")==true)
				{
					window.location.href='index.php?a=6&id='+id{$page};
				}
				break;
			case 63:		// undelete
				if(confirm("{$_lang['confirm_undelete']}")==true)
				{
					top.main.document.location.href="index.php?a=63&id=" + id{$page};
				}
				break;
			case 72: 		// new Weblink
				window.location.href='index.php?a=72&pid='+id;
				break;
			case 3:		// view
				window.location.href='index.php?a=120&id='+id;
				break;
		}
	}
	document.addEvent('click', function(){
		contextm.style.visibility = "hidden";
	});
</script>
EOT;
	return $block;
}

function getReturnAction($content)
{
	global $modx;
	
	if(isset($content['parent'])) $parent = $content['parent'];
	else $parent = 'root';
	
	if($parent!=='root')
	{
		$isAllowed = $modx->manager->isAllowed($parent);
		if(!$isAllowed) $parent = 0;
	}
	
	if($parent==='root') $a = 'a=2';
	elseif($parent==0)   $a = 'a=120';
	else                 $a = "a=120&id={$parent}";
		
	return 'index.php?' . $a;
}

function getTopicPath($id)
{
	global $modx;
	
	if($id==0) return;
	$parents[] = $modx->config['site_start'];
	$parents = array_merge($parents,array_reverse($modx->getParentIds($id)));
	
	$parents[] = $id;
	
	foreach($parents as $topic)
	{
		$doc = $modx->getDocumentObject('id',$topic);
		if($topic==$modx->config['site_start'])
			$topics[] = sprintf('<a href="index.php?a=120">%s</a>', 'Home');
		elseif($topic==$id)
			$topics[] = sprintf('%s', $doc['alias']);
		elseif($modx->manager->isAllowed($topic))
			$topics[] = sprintf('<a href="index.php?a=120&id=%s">%s</a>', $topic, $doc['alias']);
		else
			$topics[] = sprintf('%s', $doc['alias']);
	}
	return '<div style="margin-bottom:10px;">' . join(' / ', $topics) . '</div>';
}