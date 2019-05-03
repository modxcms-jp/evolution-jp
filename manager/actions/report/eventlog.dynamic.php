<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
if(!$modx->hasPermission('view_eventlog')) {
	$e->setError(3);
	$e->dumpError();
}

global $_PAGE;
$modx->manager->initPageViewState();

// get and save search string
if(isset($_REQUEST['op']) && $_REQUEST['op']=='reset') {
	$search = $query = '';
	$_PAGE['vs']['search']='';
}
else {
	$search = $query = isset($_REQUEST['search'])? $_REQUEST['search']:$_PAGE['vs']['search'];
	if(!is_numeric($search)) $search = $modx->db->escape($query);
	$_PAGE['vs']['search'] = $query;
}

// get & save listmode
$listmode = isset($_REQUEST['listmode']) ? $_REQUEST['listmode']:$_PAGE['vs']['lm'];
$_PAGE['vs']['lm'] = $listmode;

// context menu
include_once(MODX_CORE_PATH . 'controls/contextmenu.php');
$cm = new ContextMenu("cntxm", 150);
$cm->addItem($_lang['view_log'],"js:menuAction(1)",$_style['icons_save']);
$cm->addSeparator();
$cm->addItem($_lang['delete'], "js:menuAction(2)",$_style['icons_delete'],(!$modx->hasPermission('delete_eventlog') ? 1:0));
echo $cm->render();

?>
<script type="text/javascript">
  	function searchResource(){
		document.resource.op.value="srch";
		document.resource.submit();
	}

	function resetSearch(){
		document.resource.search.value = ''
		document.resource.op.value="reset";
		document.resource.submit();
	}

	function changeListMode(){
		var m = parseInt(document.resource.listmode.value) ? 1:0;
		if (m) document.resource.listmode.value=0;
		else document.resource.listmode.value=1;
		document.resource.submit();
	}

	var selectedItem;
	var contextm = <?php echo $cm->getClientScriptObject()?>;
	function showContentMenu(id,e){
		selectedItem=id;
		contextm.style.left = (e.pageX || (e.clientX + (document.documentElement.scrollLeft || document.body.scrollLeft)))+"px";
		contextm.style.top = (e.pageY || (e.clientY + (document.documentElement.scrollTop || document.body.scrollTop)))+"px";
		contextm.style.visibility = "visible";
		e.cancelBubble=true;
		return false;
	}

	function menuAction(a) {
		var id = selectedItem;
		switch(a) {
			case 1:		// view log details
				window.location.href='index.php?a=115&id='+id;
				break;
			case 2:		// clear log
				window.location.href='index.php?a=116&id='+id;
				break;
		}
	}

	document.addEvent('click', function(){
		contextm.style.visibility = "hidden";
	});
</script>
<form name="resource" method="post">
<input type="hidden" name="id" value="<?php echo $id?>" />
<input type="hidden" name="listmode" value="<?php echo $listmode?>" />
<input type="hidden" name="op" value="" />

<h1><?php echo $_lang['eventlog_viewer']?></h1>

<div id="actions">
  <ul class="actionButtons">
      <li id="Button5" class="mutate"><a href="#" onclick="documentDirty=false;document.location.href='index.php?a=2';"><img alt="icons_cancel" src="<?php echo $_style["icons_cancel"] ?>" /> <?php echo $_lang['cancel']?></a></li>
  </ul>
</div>

<div class="sectionBody">
	<!-- load modules -->
	<p><?php echo $_lang['eventlog_msg']?></p>
	<div class="actionButtons">
		<table border="0" style="width:100%">
			<tr>
			<td><a href="index.php?a=116&cls=1"><img src="<?php echo $_style["icons_delete_document"]?>"  align="absmiddle" /> <?php echo $_lang['clear_log']?></a></td>
			<td nowrap="nowrap">
				<table border="0" style="float:right">
				    <tr>
				        <td><?php echo $_lang['search']?> </td><td><input class="searchtext" name="search" type="text" size="15" value="<?php echo $query?>" /></td>
				        <td><a class="primary" href="#" title="<?php echo $_lang['search']?>" onclick="searchResource();return false;"><img src="<?php echo $_style['icons_save'];?>" /><?php echo $_lang['go']?></a></td>
				        <td><a href="#" title="<?php echo $_lang['reset']?>" onclick="resetSearch();return false;"><img src="<?php echo $_style['icons_refresh']; ?>" style="display:inline;" /></a></td>
				        <td><a href="#" title="<?php echo $_lang['list_mode']?>" onclick="changeListMode();return false;"><img src="<?php echo $_style['icons_table'];?>" style="display:inline;" /></a></td>
				    </tr>
				</table>
			</td>
			</tr>
		</table>
	</div>
	<div>
<?php
	$field  = "el.id, el.type, el.createdon, el.source, el.eventid,IFNULL(wu.username,mu.username) as 'username'";
	$from   = '[+prefix+]event_log el';
	$from  .= ' LEFT JOIN [+prefix+]manager_users mu ON mu.id=el.user AND el.usertype=0';
	$from  .= ' LEFT JOIN [+prefix+]web_users wu ON wu.id=el.user AND el.usertype=1';
	$where = '';
	if($search)
	{
		if(is_numeric($search))
		{
			$where = "(eventid='{$search}') OR ";
		}
		$where .= "(source LIKE '%{$search}%') OR (description LIKE '%{$search}%')";
	}
	$orderby = 'el.id DESC';
	$ds = $modx->db->select($field,$from,$where,$orderby);
	include_once(MODX_CORE_PATH . 'controls/datagrid.class.php');
	$grd = new DataGrid('',$ds,$number_of_results); // set page size to 0 t show all items
	$grd->noRecordMsg = $_lang['no_records_found'];
	$grd->cssClass="grid";
	$grd->columnHeaderClass="gridHeader";
	$grd->itemClass="gridItem";
	$grd->altItemClass="gridAltItem";
	$grd->fields="id,type,source,createdon,username";
	$grd->columns = $_lang['event_id'] . ', ' . $_lang['type']." ,".$_lang['source']." ,".$_lang['date']." ,".$_lang['sysinfo_userid'];
	$grd->colWidths="20,34,,150";
	$grd->columnHeaderStyle = 'text-align:center;';
	$grd->colAligns="right,center,,,center,center";
	$param = array($_lang['click_to_context'], $manager_theme, $_lang['click_to_view_details'], $modx->toDateFormat(null,'formatOnly').' %H:%M:%S');
	$grd->colTypes=vsprintf('||template:<a class="gridRowIcon" href="#" onclick="return showContentMenu([+id+],event);" title="%s"><img src="media/style/%s/images/icons/event[+type+].png" /></a>||template:<a href="index.php?a=115&id=[+id+]" title="%s">[+source+]</a>||date: %s', $param);
	if($listmode=='1') $grd->pageSize=0;
	if(isset($_REQUEST['op']) && $_REQUEST['op']=='reset') $grd->pageNumber = 1;
	// render grid
	echo $grd->render();
	?>
	</div>
</div>
</form>
