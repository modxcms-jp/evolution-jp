<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if (!isset($modx->config['mail_check_timeperiod']) || empty($modx->config['mail_check_timeperiod']))
{
	$modx->config['mail_check_timeperiod'] = 0;
}
if ($manager_theme) $manager_theme .= '/';
$mxla = $modx_lang_attribute ? $modx_lang_attribute : 'en';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html <?php echo ($modx_textdir ? 'dir="rtl" lang="' : 'lang="').$mxla.'" xml:lang="'.$mxla.'"'; ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php echo $modx_manager_charset?>" />
	<title>nav</title>
	<link rel="stylesheet" type="text/css" href="media/style/<?php echo $manager_theme?>style.css" />
	<script src="media/script/jquery/jquery.min.js" type="text/javascript"></script>
	<script type="text/javascript">
	// TREE FUNCTIONS - FRAME
	// These functions affect the tree frame and any items that may be pointing to the tree.
	var currentFrameState = 'open';
	var defaultFrameWidth = '<?php echo !$modx_textdir ? '260,*' : '*,260'?>';
	var userDefinedFrameWidth = '<?php echo !$modx_textdir ? '260,*' : '*,260'?>';

	var workText;
	var buildText;
	var msgcheck = <?php echo $modx->hasPermission('messages') ? 1 : 0 ;?>;
	
	var $j = jQuery.noConflict();
	
	function keepMeAlive()
	{
		var tok = document.getElementById('sessTokenInput').value;
		var o = Math.random();
		var url = 'includes/session_keepalive.php';
		
		$j.getJSON(url, {'tok':tok,'o':o},
		function(resp)
		{
			if(resp.status != 'ok') window.location.href = 'index.php?a=8';
	    });
	}
	window.setInterval('keepMeAlive()', 1000 * 60);
	
	function updateMail(now)
	{
		try
		{
		// if 'now' is set, runs immediate ajax request (avoids problem on initial loading where periodical waits for time period before making first request)
			if (now && msgcheck!=0)
			{
				$j.ajax({type:'POST',url:'index.php',data:{'updateMsgCount':'true'},success:function(request){showResponse(request);}});
			}
			return false;
		} catch(oException) {
			// Delay first run until we're ready...
			if(msgcheck!=0) setTimeout('updateMail(true)',1000);
		}
	};

	function showResponse(request) {
		var counts = request.split(',');
		var elm = document.getElementById('msgCounter');
		if (elm) elm.innerHTML ='(' + counts[0] + ' / ' + counts[1] + ')';
		var elm = document.getElementById('newMail');
		if (elm) elm.style.display = counts[0] >0 ? 'inline' :  'none';
	}

	$j(function(){
		if(msgcheck!=0) updateMail(true); // First run update
		var mailinterval = <?php echo $modx->config['mail_check_timeperiod'];?>;
		if(mailinterval!='' && mailinterval!=0)
		{
			if(msgcheck!=0) setInterval('updateMail(true)',mailinterval * 1000);
		}
		
		if(top.__hideTree) {
			// display toc icon
			var elm = document.getElementById('tocText');
			if(elm) elm.innerHTML = "<a href='#' onclick='defaultTreeFrame();'><img src='<?php echo $_style['show_tree']?>' alt='<?php echo $_lang['show_tree']?>' width='16' height='16' /></a>";
		}
	});

	function hideTreeFrame() {
		userDefinedFrameWidth = parent.document.getElementsByTagName("FRAMESET").item(1).cols;
		currentFrameState = 'closed';
		try {
			var elm = document.getElementById('tocText');
			if(elm) elm.innerHTML = "<a href='#' onclick='defaultTreeFrame();'><img src='<?php echo $_style['show_tree']?>' alt='<?php echo $_lang['show_tree']?>' width='16' height='16' /></a>";
			parent.document.getElementsByTagName("FRAMESET").item(1).cols = '<?php echo (!$modx_textdir ? '0,*' : '*,0')?>';
			top.__hideTree = true;
		} catch(oException) {
			x=window.setTimeout('hideTreeFrame()', 100);
		}
	}

	function defaultTreeFrame() {
		userDefinedFrameWidth = defaultFrameWidth;
		currentFrameState = 'open';
		try {
			var elm = document.getElementById('tocText');
			if(elm) elm.innerHTML = "";
			parent.document.getElementsByTagName("FRAMESET").item(1).cols = defaultFrameWidth;
			top.__hideTree = false;
		} catch(oException) {
			z=window.setTimeout('defaultTreeFrame()', 100);
		}
	}

	// TREE FUNCTIONS - Expand/ Collapse
	// These functions affect the expanded/collapsed state of the tree and any items that may be pointing to it
	function expandTree() {
		try {
			parent.tree.d.openAll();  // dtree
		} catch(oException) {
			zz=window.setTimeout('expandTree()', 100);
		}
	}

	function collapseTree() {
		try {
			parent.tree.d.closeAll();  // dtree
		} catch(oException) {
			yy=window.setTimeout('collapseTree()', 100);
		}
	}

	// GENERAL FUNCTIONS - Refresh
	// These functions are used for refreshing the tree or menu
	function reloadtree() {
		var elm = document.getElementById('buildText');
		if (elm) {
			elm.innerHTML = "&nbsp;&nbsp;<img src='<?php echo $_style['icons_loading_doc_tree']?>' width='16' height='16' />&nbsp;<?php echo $_lang['loading_doc_tree']?>";
			elm.style.display = 'block';
		}
		top.tree.saveFolderState(); // save folder state
		setTimeout('top.tree.restoreTree()',100);
	}

	function reloadmenu() {
		var elm = document.getElementById('buildText');
		if (elm) {
			elm.innerHTML = "&nbsp;&nbsp;<img src='<?php echo $_style['icons_working']?>' width='16' height='16' />&nbsp;<?php echo $_lang['loading_menu']?>";
			elm.style.display = 'block';
		}
		parent.mainMenu.location.reload();
	}

	function startrefresh(rFrame){
		if(rFrame==1){
			x=window.setTimeout('reloadtree()',100);
		}
		if(rFrame==2) {
			x=window.setTimeout('reloadmenu()',100);
		}
		if(rFrame==9) {
			y=window.setTimeout('reloadtree()',100);
			x=window.setTimeout('reloadmenu()',500);
		}
		if(rFrame==10) {
			window.top.location.href = "../manager/";
		}
	}

	// GENERAL FUNCTIONS - Work
	// These functions are used for showing the user the system is working
	function work() {
		var elm = document.getElementById('workText');
		if (elm) elm.innerHTML = "&nbsp;<img src='<?php echo $_style['icons_working']?>' width='16' height='16' />&nbsp;<?php echo $_lang['working']?>";
		else w=window.setTimeout('work()', 50);
	}

	function stopWork() {
		var elm = document.getElementById('workText');
		if (elm) elm.innerHTML = "";
		else  ww=window.setTimeout('stopWork()', 50);
	}

	// GENERAL FUNCTIONS - Remove locks
	// This function removes locks on documents, templates, parsers, and snippets
	function removeLocks() {
		if(confirm("<?php echo $_lang['confirm_remove_locks']?>")==true) {
			top.main.document.location.href="index.php?a=67";
		}
	}

	function showWin() {
		window.open('../');
	}

	function stopIt() {
		top.mainMenu.stopWork();
	}

	function NavToggle(element) {
		// This gives the active tab its look
		var navid = document.getElementById('nav');
		var navs = navid.getElementsByTagName('li');
		var navsCount = navs.length;
		for(j = 0; j < navsCount; j++) {
			active = (navs[j].id == element.parentNode.id) ? "active" : "";
			navs[j].className = active;
		}

		// remove focus from top nav
		if(element) element.blur();
	}
	</script>
	<!--[if lt IE 7]>
	<style type="text/css">
	body { behavior: url(media/script/forIE/htcmime.php?file=csshover.htc) }
	img { behavior: url(media/script/forIE/htcmime.php?file=pngbehavior.htc); }
	</style>
	<![endif]-->
</head>

<body id="topMenu" class="<?php echo $modx_textdir ? 'rtl':'ltr'?>">

<div id="tocText"<?php echo $modx_textdir ? ' class="tocTextRTL"' : '' ?>></div>
<div id="topbar">
<div id="topbar-container">
	<div id="statusbar">
		<span id="buildText"></span>
		<span id="workText"></span>
	</div>

	<div id="supplementalNav">
	<?php echo $modx->getLoginUserName(). ($modx->hasPermission('change_password') ? ': <a onclick="this.blur();" href="index.php?a=28" target="main">'.$_lang['change_password'].'</a>'."\n" : "\n") ?>
<?php if($modx->hasPermission('messages')) { ?>
	| <span id="newMail"><a href="index.php?a=10" title="<?php echo $_lang['you_got_mail']?>" target="main"> <img src="<?php echo $_style['icons_mail']?>" width="16" height="16" /></a></span>
	<a onclick="this.blur();" href="index.php?a=10" target="main"><?php echo $_lang['messages']?> <span id="msgCounter">( ? / ? )</span></a>
<?php }
if($modx->hasPermission('help')) { ?>
	| <a href="index.php?a=9" target="main"><?php echo $_lang['help']?></a>
<?php } ?>
	| <a href="index.php?a=8" target="_top"><?php echo $_lang['logout']?></a>
	| <span title="<?php echo $site_name ?> &ndash; <?php echo $modx_full_appname ?>"><?php echo $modx_version ?></span>&nbsp;
	<!-- close #supplementalNav --></div>
</div>
</div>

<form name="menuForm" action="l4mnu.php" class="clear">
    <input name="sessToken" type="hidden" id="sessTokenInput" value="<?php echo md5(session_id());?>" />
<div id="Navcontainer">
<div id="divNav">
	<ul id="nav">
<?php
// Concatenate menu items based on permissions

// Site Menu
$item['home']         = item($_lang['home'], 2);             // home
$item['preview']      = item($_lang['preview'], '../', 1, 'target="_blank"'); // preview
$item['refresh_site'] = item($_lang['refresh_site'], 26,$modx->hasPermission('empty_cache'));    // clear-cache
$item['search']       = item($_lang['search'], 71);          // search
$item['add_resource'] = item($_lang['add_resource'], 4,$modx->hasPermission('new_document')); // new-document
$item['add_weblink']  = item($_lang['add_weblink'], 72,$modx->hasPermission('new_document')); // new-weblink

// Resources Menu
if($modx->hasPermission('new_template') || $modx->hasPermission('edit_template')
|| $modx->hasPermission('new_snippet')  || $modx->hasPermission('edit_snippet')
|| $modx->hasPermission('new_chunk')    || $modx->hasPermission('edit_chunk')
|| $modx->hasPermission('new_plugin')   || $modx->hasPermission('edit_plugin'))
{
	$perm_element_management = 1;
}
else $perm_element_management = 0;
$perm_manage_metatags = ($modx->hasPermission('manage_metatags') && $modx->config['show_meta'] == 1) ? 1 : 0;

$item['element_management'] = item($_lang['element_management'], 76,$perm_element_management);// Elements
$item['manage_files']       = item($_lang['manage_files'], 31,$modx->hasPermission('file_manager'));// Manage-Files
$item['manage_metatags']    = item($_lang['manage_metatags'], 81, $perm_manage_metatags); // Manage-Metatags

// Modules Menu Items
$perm_module_management = ($modx->hasPermission('new_module') || $modx->hasPermission('edit_module')) ? 1 : 0;
$item['modules']['module_management'] = item($_lang['module_management'], 106,$perm_module_management);// manage-modules
if($modx->hasPermission('exec_module'))
{
	// Each module
	$tbl_site_modules       = $modx->getFullTableName('site_modules');
	$tbl_site_module_access = $modx->getFullTableName('site_module_access');
	$tbl_member_groups      = $modx->getFullTableName('member_groups');
	$uid = $modx->getLoginUserID();
	if ($_SESSION['mgrRole'] != 1)
	{
		// Display only those modules the user can execute
		$field = 'sm.id, sm.name, mg.member';
		$from = "{$tbl_site_modules}                 AS sm "
		       ."LEFT JOIN {$tbl_site_module_access} AS sma ON sma.module = sm.id "
		       ."LEFT JOIN {$tbl_member_groups}      AS mg  ON sma.usergroup = mg.user_group";
		$where   = "(mg.member IS NULL OR mg.member='{$uid}') AND sm.disabled != 1";
		$orderby = 'sm.editedon DESC';
		$rs = $modx->db->select($field, $from, $where, $orderby);
	}
	else
	{
		// Admins get the entire list
		$rs = $modx->db->select('id,name', $tbl_site_modules, 'disabled != 1', 'editedon DESC');
	}
	
	while ($content = $modx->db->getRow($rs))
	{
		$item['modules'][$content['name']] = item($content['name'], "index.php?a=112&amp;id={$content['id']}");
	}
	if(0<count($item['modules'])) $modulemenu = join("\n", $item['modules']);
	else                          $modulemenu = false;
}

// Security menu items (users)
$perm_role_management = ($modx->hasPermission('new_role') || $modx->hasPermission('edit_role') || $modx->hasPermission('delete_role')) ? 1 : 0;
$perm_mgruser = ($modx->hasPermission('access_permissions') && $modx->config['use_udperms'] == 1) ? 1 : 0;
$perm_webuser = ($modx->hasPermission('web_access_permissions') && $modx->config['use_udperms'] == 1) ? 1 : 0;

$item['user_management']     = item($_lang['user_management_title'], 75,$modx->hasPermission('edit_user'));// manager-users
$item['web_user_management'] = item($_lang['web_user_management_title'], 99,$modx->hasPermission('edit_web_user'));// web-users
$item['role_management']     = item($_lang['role_management_title'], 86, $perm_role_management);// roles
$item['manager_permissions'] = item($_lang['manager_permissions'], 40,$perm_mgruser);// manager-perms
$item['web_permissions']     = item($_lang['web_permissions'], 91,$perm_webuser);// web-user-perms

// Tools Menu
$item['bk_manager']    = item($_lang['bk_manager'], 93,$modx->hasPermission('bk_manager'));// backup-mgr
$item['remove_locks']  = item($_lang['remove_locks'], 'javascript:removeLocks();', $modx->hasPermission('remove_locks'),'');// unlock-pages
$item['import_site']   = item($_lang['import_site'], 95,$modx->hasPermission('import_static'));// import-html
$item['export_site']   = item($_lang['export_site'], 83,$modx->hasPermission('export_static'));// export-static-site
$item['edit_settings'] = item($_lang['edit_settings'], 17,$modx->hasPermission('settings'));// configuration

// Reports Menu
$item['site_schedule']   = item($_lang['site_schedule'], 70,$modx->hasPermission('view_schedule'));// site-sched
$item['eventlog_viewer'] = item($_lang['eventlog_viewer'], 114,$modx->hasPermission('view_eventlog'));// eventlog
$item['view_logging']    = item($_lang['view_logging'], 13,$modx->hasPermission('logs'));// manager-audit-trail
$item['view_sysinfo']    = item($_lang['view_sysinfo'], 53);// system-info

$sitemenu     = buildMenu('site',$item);
$elementmenu  = buildMenu('element',$item);
//$modulemenu   = buildMenu('module',$item);//$item['modules']
$securitymenu = buildMenu('security',$item);
$toolsmenu    = buildMenu('tools',$item);
$reportsmenu  = buildMenu('reports',$item);

// Output Menus where there are items to show
if (!empty($sitemenu)) {
	echo '<li id="limenu1" class="active"><a href="#menu1" onclick="new NavToggle(this); return false;">'.$_lang['site'].'</a><ul class="subnav" id="menu1">'."\n".
	     "{$sitemenu}\n</ul></li>\n";
	     
}
if (!empty($elementmenu)) {
	echo '<li id="limenu2"><a href="#menu2" onclick="new NavToggle(this); return false;">'.$_lang['elements'].'</a><ul class="subnav" id="menu2">'."\n".
	     "{$elementmenu}\n</ul></li>\n";
}
if (!empty($modulemenu)) {
	echo '<li id="limenu3"><a href="#menu3" onclick="new NavToggle(this); return false;">'.$_lang['modules'].'</a><ul class="subnav" id="menu3">'."\n".
	     "{$modulemenu}\n</ul></li>\n";
}
if (!empty($securitymenu)) {
	echo '<li id="limenu4"><a href="#menu4" onclick="new NavToggle(this); return false;">'.$_lang['users'].'</a><ul class="subnav" id="menu4">'."\n".
	     "{$securitymenu}\n</ul></li>\n";
}
if (!empty($toolsmenu)) {
	echo '<li id="limenu5"><a href="#menu5" onclick="new NavToggle(this); return false;">'.$_lang['tools'].'</a><ul class="subnav" id="menu5">'."\n".
	     "{$toolsmenu}\n</ul></li>\n";
}
if (!empty($reportsmenu)) {
	echo '<li id="limenu6"><a href="#menu6" onclick="new NavToggle(this); return false;">'.$_lang['reports'].'</a><ul class="subnav" id="menu6">'."\n".
	     "{$reportsmenu}\n</ul></li>\n";
}
?>
	</ul>
</div>
</div>
</form>

<div id="menuSplitter"></div>
</body>
</html>

<?php
function item($name, $href, $display=1, $attrib='target="main"')
{
	global $modx;
	
	if($display==0) return false;
	
	if(is_int($href)) $href = "index.php?a={$href}";
	
	$tpl = '<li><a onclick="this.blur();" href="[+href+]" [+attrib+]>[+name+]</a></li>';
	$ph = compact('href','name','attrib');
	return $modx->parsePlaceholder($tpl, $ph);
}

function buildMenu($target,$item)
{
	$menu['site']     = 'home,preview,refresh_site,search,add_resource,add_weblink';
	$menu['element']  = 'element_management,manage_files,manage_metatags';
	$menu['module']   = 'modules';
	$menu['security'] = 'user_management,web_user_management,role_management,manager_permissions,web_permissions';
	$menu['tools']    = 'bk_manager,remove_locks unlock-pages,import_site,export_site,edit_settings';
	$menu['reports']  = 'site_schedule,eventlog_viewer,view_logging,view_sysinfo';
	
	if(empty($menu[$target])) return false;
	
	$a = explode(',',$menu[$target]);
	foreach($a as $v)
	{
		$v = trim($v);
		if(isset($item[$v])) $result[] = $item[$v];
		elseif(isset($item['modules'][$v]))
		{
			$result[] = $item['modules'][$v];
			unset($item['modules'][$v]);
		}
	}
	
	if(0<count($result)) return join("\n", $result);
	else                 return false;
}