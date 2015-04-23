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

$modx->loadExtension('DocAPI');

$modx->checkPublishStatus();

if($id!=0)
{
	$rs = $modx->db->select('*','[+prefix+]site_content',"id='{$id}'");
	$content = $modx->db->getRow($rs);
	
	// Set the item name for logging
	$_SESSION['itemname'] = $content['pagetitle'];
	
	foreach($content as $k=>$v)
	{
		$content[$k] = htmlspecialchars($v, ENT_QUOTES, $modx->config['modx_charset']);
	}
}
else $content = array();
if(!isset($content['id'])) $content['id']=0;
/**
 * "View Children" tab setup
 */

// Get access permissions

if($_SESSION['mgrDocgroups']) $docgrp = implode(',',$_SESSION['mgrDocgroups']);
else $docgrp = '';
$in_docgrp = empty($docgrp) ? '':" OR dg.document_group IN ({$docgrp})";

$access = $modx->config['tree_show_protected'] ? '' : sprintf("AND (1='%s' OR sc.privatemgr=0 %s)",$_SESSION['mgrRole'] , $in_docgrp);

// Get child document count

$from = "[+prefix+]site_content AS sc LEFT JOIN [+prefix+]document_groups AS dg ON dg.document = sc.id";
$where = "sc.parent='{$content['id']}' {$access}";
$rs = $modx->db->select('DISTINCT sc.id',$from,$where);
$numRecords = $modx->db->getRecordCount($rs);

if ($numRecords > 0)
{
	$from = array();
	$from[] = '[+prefix+]site_content AS sc';
	$from[] = 'LEFT JOIN [+prefix+]document_groups AS dg ON dg.document = sc.id';
	$from[] = "LEFT JOIN [+prefix+]site_revision rev on rev.elmid = sc.id AND (rev.status='draft' OR rev.status='pending' OR rev.status='standby') AND rev.element='resource' ";
	$from = join(' ',$from);
	$where = "sc.parent='{$id}' {$access} GROUP BY sc.id";
	$orderby ='sc.isfolder DESC, sc.published ASC, sc.publishedon DESC, if(sc.editedon=0,10000000000,sc.editedon) DESC, sc.id DESC';
	if(isset($_GET['page']) && preg_match('@^[1-9][0-9]*$@',$_GET['page']))
		$offset =  $_GET['page'] - 1;
	else $offset = 0;
	$limit = ($offset * $modx->config['number_of_results']) . ', ' . $modx->config['number_of_results'];
	$field = sprintf('DISTINCT sc.*, MAX(IF(1=%s OR sc.privatemgr=0 %s, 1, 0)) AS has_access, rev.status', $_SESSION['mgrRole'], $in_docgrp);
	$rs = $modx->db->select($field,$from,$where,$orderby,$limit);
	$resource = array();
	while($row = $modx->db->getRow($rs))
	{
		$resource[] = $row;
	}

	// context menu
	include_once(MODX_CORE_PATH . 'controls/contextmenu.php');
	$cm = new ContextMenu('cntxm', 180);
	$contextMenu = getContextMenu($cm);
	echo $contextMenu;
	
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
		if($children['deleted']==='1')    $classes[] = 'deletedNode';
		if($children['has_access']==='0') $classes[] = 'protectedNode';
		if($children['published']==='0')  $classes[] = 'unpublishedNode';
		$class = ' class="' . join(' ',$classes) . '"';
		
		$tpl = '<span [+class+] oncontextmenu="document.getElementById(\'icon[+id+]\').onclick(event);return false;">[+pagetitle+]</span>';
		$pagetitle = str_replace(array('[+class+]','[+pagetitle+]','[+id+]'),
		                         array($class,$children['pagetitle'],$children['id']),$tpl);
		
		if($children['isfolder'] == 0)
		{
			$link = "index.php?a=27&amp;id={$children['id']}";
			if($children['privatemgr']==1)
				$iconpath = $_style['tree_page_html_secure'];
			elseif($modx->config['site_start']==$children['id'])
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
			if($children['privatemgr']==1) $iconpath = $_style['tree_folderopen_secure'];
			else                           $iconpath = $_style['tree_folder'];
				
		}
		
		if( $children['type']==='reference')
			$pagetitle = sprintf('<img src="%s" /> %s', $_style['tree_weblink'], $pagetitle);
		
		$tpl = '';
		$tpl = '<img src="[+iconpath+]" id="icon[+id+]" onclick="return showContentMenu([+id+],event);" />';
		$icon = str_replace(array('[+iconpath+]','[+id+]'),array($iconpath,$children['id']),$tpl);
		switch($children['status'])
		{
			case 'draft':
    			$statusIcon = sprintf('&nbsp;<img src="%s">&nbsp;',$_style['tree_draft']);
    			break;
			case 'standby':
				$statusIcon = sprintf('&nbsp;<img src="%s">&nbsp;',$_style['icons_date']);
    			break;
		    default:
		    	$statusIcon = '';
		}
		$tpl = '<div style="float:left;">[+icon+][+statusIcon+]</div><a href="[+link+]" style="overflow:hidden;display:block;color:#333;">[+pagetitle+][+description+]</a>';
		$title = str_replace(array('[+icon+]','[+link+]','[+pagetitle+]','[+description+]','[+statusIcon+]'),
		                     array($icon,$link,$pagetitle,$description,$statusIcon), $tpl);
		
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
	$modx->table->setRowDefaultClass('gridItem');
	$modx->table->setRowAlternateClass('gridAltItem');
	$modx->table->setColumnWidths('2%, 2%, 68%, 10%, 10%, 8%');
	
	// Table header
	$header['checkbox']    = '<input type="checkbox" name="chkselall" onclick="selectAll()" />';
	$header['docid']       = $_lang['id'];
	$header['title']       = $_lang['resource_title'];
	$header['publishedon'] = $_lang['publish_date'];
	$header['editedon']    = $_lang['editedon'];
	$header['status']      = $_lang['page_data_status'];
	$qs = 'a=120';
	if($id) $qs .= "&id={$id}";
	$pageNavBlock = $modx->table->renderPagingNavigation($numRecords,$qs);
	$children_output = $pageNavBlock . $modx->table->renderTable($docs,$header) . $pageNavBlock;
	$children_output .= '<div style="margin-top:10px;"><input type="submit" value="' . $_lang["document_data.static.php1"] . '" /></div>';
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
	if($modx->doc->canCopyDoc() && $id!=0 && $modx->manager->isAllowed($id))
		echo sprintf($tpl,'Button4', 'duplicatedocument();', $_style["icons_resource_duplicate"], $_lang['duplicate']);
	if($modx->hasPermission('delete_document') && $modx->hasPermission('save_document') && $id!=0 && $modx->manager->isAllowed($id))
		echo sprintf($tpl,'Button3', 'deletedocument();', $_style["icons_delete_document"], $_lang['delete']);
	
	$url = $modx->makeUrl($id);
	$prev = "window.open('{$url}','previeWin')";
	echo sprintf($tpl,'Button6', $prev, $_style["icons_preview_resource"], $id==0 ? $_lang["view_site"] : $_lang['view_resource']);
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
				window.location.href='index.php?a=3&id='+id;
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
		$rs = $modx->db->select("IF(alias='', id, alias) AS alias", '[+prefix+]site_content', "id='{$topic}'");
		$doc = $modx->db->getRow($rs);
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

function getContextMenu($cm)
{
	global $modx, $_lang, $_style;
	extract($_lang, EXTR_PREFIX_ALL, 'lang');
	extract($_style);
	
	// $cm->addSeparator();
	if($modx->hasPermission('edit_document'))
		$cm->addItem($lang_edit_resource,       "js:menuAction(27)",$icons_edit_document);
	if($modx->hasPermission('new_document'))
		$cm->addItem($lang_create_resource_here,"js:menuAction(4)",$icons_new_document);
	if($modx->hasPermission('save_document')&&$modx->hasPermission('publish_document'))
		$cm->addItem($lang_move_resource,       "js:menuAction(51)",$icons_move_document);
	if($modx->hasPermission('new_document'))
		$cm->addItem($lang_resource_duplicate,  "js:menuAction(94)",$icons_resource_duplicate);
	if(0<$cm->i)
	{
		$cm->addSeparator();
		$cm->i = 0;
	}
	if($modx->hasPermission('publish_document'))
		$cm->addItem($lang_publish_resource,   "js:menuAction(61)",$icons_publish_document);
	if($modx->hasPermission('publish_document'))
		$cm->addItem($lang_unpublish_resource, "js:menuAction(62)",$icons_unpublish_resource);
	if($modx->hasPermission('delete_document'))
		$cm->addItem($lang_delete_resource,    "js:menuAction(6)",$icons_delete);
	if($modx->hasPermission('delete_document'))
		$cm->addItem($lang_undelete_resource,  "js:menuAction(63)",$icons_undelete_resource);
	if(0<$cm->i)
	{
		$cm->addSeparator();
		$cm->i = 0;
	}
	if($modx->hasPermission('new_document'))
		$cm->addItem($lang_create_weblink_here, "js:menuAction(72)",$icons_weblink);
	if(0<$cm->i)
	{
		$cm->addSeparator();
		$cm->i = 0;
	}
	if($modx->hasPermission('view_document'))
		$cm->addItem($lang_resource_overview, "js:menuAction(3)",$icons_resource_overview);
	//$cm->addItem($_lang["preview_resource"], "js:menuAction(999)",$_style['icons_preview_resource'],0);
	return $cm->render();
}