<?php
if(IN_MANAGER_MODE!="true") die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODx Content Manager instead of accessing this file directly.");
if (!array_key_exists('mail_check_timeperiod', $modx->config) || !is_numeric($modx->config['mail_check_timeperiod'])) {
	$modx->config['mail_check_timeperiod'] = 5;
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
	<script src="media/script/mootools/mootools.js" type="text/javascript"></script>
	<script src="media/script/mootools/moodx.js" type="text/javascript"></script>
    <script type="text/javascript" src="media/script/session.js"></script>
	<script type="text/javascript">
	// TREE FUNCTIONS - FRAME
	// These functions affect the tree frame and any items that may be pointing to the tree.
	var currentFrameState = 'open';
	var defaultFrameWidth = '<?php echo !$modx_textdir ? '260,*' : '*,260'?>';
	var userDefinedFrameWidth = '<?php echo !$modx_textdir ? '260,*' : '*,260'?>';

	var workText;
	var buildText;

	// Create the AJAX mail update object before requesting it
	var updateMailerAjx = new Ajax('index.php',
	{
		method:'post',
		postBody:'updateMsgCount=true',
		onComplete:showResponse
	});
	function updateMail(now)
	{
		try
		{
		// if 'now' is set, runs immediate ajax request (avoids problem on initial loading where periodical waits for time period before making first request)
			if (now)
				updateMailerAjx.request();
			return false;
		} catch(oException) {
			// Delay first run until we're ready...
			xx=updateMail.delay(1000,'',true);
		}
	};

	function showResponse(request) {
		var counts = request.split(',');
		var elm = $('msgCounter');
		if (elm) elm.innerHTML ='(' + counts[0] + ' / ' + counts[1] + ')';
		var elm = $('newMail');
		if (elm) elm.style.display = counts[0] >0 ? 'inline' :  'none';
	}

	window.addEvent('load', function() {
		updateMail(true); // First run update
		updateMail.periodical(<?php echo $modx->config['mail_check_timeperiod'] * 1000 ?>, '', true); // Periodical Updater
		if(top.__hideTree) {
			// display toc icon
			var elm = $('tocText');
			if(elm) elm.innerHTML = "<a href='#' onclick='defaultTreeFrame();'><img src='<?php echo $_style['show_tree']?>' alt='<?php echo $_lang['show_tree']?>' width='16' height='16' /></a>";
		}
	});

	function hideTreeFrame() {
		userDefinedFrameWidth = parent.document.getElementsByTagName("FRAMESET").item(1).cols;
		currentFrameState = 'closed';
		try {
			var elm = $('tocText');
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
			var elm = $('tocText');
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
		var elm = $('buildText');
		if (elm) {
			elm.innerHTML = "&nbsp;&nbsp;<img src='<?php echo $_style['icons_loading_doc_tree']?>' width='16' height='16' />&nbsp;<?php echo $_lang['loading_doc_tree']?>";
			elm.style.display = 'block';
		}
		top.tree.saveFolderState(); // save folder state
		setTimeout('top.tree.restoreTree()',100);
	}

	function reloadmenu() {
		var elm = $('buildText');
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
		var elm = $('workText');
		if (elm) elm.innerHTML = "&nbsp;<img src='<?php echo $_style['icons_working']?>' width='16' height='16' />&nbsp;<?php echo $_lang['working']?>";
		else w=window.setTimeout('work()', 50);
	}

	function stopWork() {
		var elm = $('workText');
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

function item($name, $href, $attrib='target="main"')
{
	global $modx;
	
	$tpl = '<li><a onclick="this.blur();" href="[+href+]" [+attrib+]>[+name+]</a></li>';
	$ph = compact('href','name','attrib');
	return $modx->parsePlaceholder($tpl, $ph);
}
// Site Menu
$sitemenu = array();

$sitemenu[] = item($_lang['home'], 'index.php?a=2');             // home
$sitemenu[] = item($_lang['preview'], '../', 'target="_blank"'); // preview
$sitemenu[] = item($_lang['refresh_site'], 'index.php?a=26');    // clear-cache
$sitemenu[] = item($_lang['search'], 'index.php?a=71');          // search
if ($modx->hasPermission('new_document')) {
	$sitemenu[] = item($_lang['add_resource'], 'index.php?a=4'); // new-document
	$sitemenu[] = item($_lang['add_weblink'], 'index.php?a=72'); // new-weblink
}

// Elements Menu
$resourcemenu = array();
if($modx->hasPermission('new_template') || $modx->hasPermission('edit_template') || $modx->hasPermission('new_snippet') || $modx->hasPermission('edit_snippet') || $modx->hasPermission('new_chunk') || $modx->hasPermission('edit_chunk') || $modx->hasPermission('new_plugin') || $modx->hasPermission('edit_plugin')) {
	// Elements
	$resourcemenu[] = item($_lang['element_management'], 'index.php?a=76');
}
if($modx->hasPermission('file_manager')) {
	// Manage-Files
	$resourcemenu[] = item($_lang['manage_files'], 'index.php?a=31');
}
if($modx->hasPermission('manage_metatags') && $modx->config['show_meta'] == 1) {
	// Manage-Metatags
	$resourcemenu[] = item($_lang['manage_metatags'], 'index.php?a=81');
}

// Modules Menu Items
$modulemenu = array();
if($modx->hasPermission('new_module') || $modx->hasPermission('edit_module')) {
	// manage-modules
	$modulemenu[] = item($_lang['module_management'], 'index.php?a=106');
}
if($modx->hasPermission('exec_module')) {
	// Each module
	if ($_SESSION['mgrRole'] != 1) {
		// Display only those modules the user can execute
		$rs = $modx->db->query('SELECT DISTINCT sm.id, sm.name, mg.member
				FROM '.$modx->getFullTableName('site_modules').' AS sm
				LEFT JOIN '.$modx->getFullTableName('site_module_access').' AS sma ON sma.module = sm.id
				LEFT JOIN '.$modx->getFullTableName('member_groups').' AS mg ON sma.usergroup = mg.user_group
				WHERE (mg.member IS NULL OR mg.member = '.$modx->getLoginUserID().') AND sm.disabled != 1
				ORDER BY sm.editedon DESC');
	} else {
		// Admins get the entire list
		$rs = $modx->db->select('id,name', $modx->getFullTableName('site_modules'), 'disabled != 1', 'editedon DESC');
	}
	while ($content = $modx->db->getRow($rs)) {
		$modulemenu[] = item($content['name'], "index.php?a=112&amp;id={$content['id']}");
	}
}

// Security menu items (users)
$securitymenu = array();
if($modx->hasPermission('edit_user')) {
	// manager-users
	$securitymenu[] = item($_lang['user_management_title'], 'index.php?a=75');
}
if($modx->hasPermission('edit_web_user')) {
	// web-users
	$securitymenu[] = item($_lang['web_user_management_title'], 'index.php?a=99');
}
if($modx->hasPermission('new_role') || $modx->hasPermission('edit_role') || $modx->hasPermission('delete_role')) {
	// roles
	$securitymenu[] = item($_lang['role_management_title'], 'index.php?a=86');
}
if($modx->hasPermission('access_permissions') && $modx->config['use_udperms'] == 1) {
	// manager-perms
	$securitymenu[] = item($_lang['manager_permissions'], 'index.php?a=40');
}
if($modx->hasPermission('web_access_permissions') && $modx->config['use_udperms'] == 1) {
	// web-user-perms
	$securitymenu[] = item($_lang['web_permissions'], 'index.php?a=91');
}

// Tools Menu
$toolsmenu = array();
if($modx->hasPermission('bk_manager')) {
	// backup-mgr
	$toolsmenu[] = item($_lang['bk_manager'], 'index.php?a=93');
}
if($modx->hasPermission('remove_locks')) {
	// unlock-pages
	$toolsmenu[] = item($_lang['remove_locks'], 'javascript:removeLocks();', '');
}
if($modx->hasPermission('import_static')) {
	// import-html
	$toolsmenu[] = item($_lang['import_site'], 'index.php?a=95');
}
if($modx->hasPermission('export_static')) {
	// export-static-site
	$toolsmenu[] = item($_lang['export_site'], 'index.php?a=83');
}
if($modx->hasPermission('settings')) {
	// configuration
	$toolsmenu[] = item($_lang['edit_settings'], 'index.php?a=17');
}

// Reports Menu
$reportsmenu = array();
// site-sched
$reportsmenu[] = item($_lang['site_schedule'], 'index.php?a=70');
if($modx->hasPermission('view_eventlog')) {
	// eventlog
	$reportsmenu[] = item($_lang['eventlog_viewer'], 'index.php?a=114');
}
if($modx->hasPermission('logs')) {
	// manager-audit-trail
	$reportsmenu[] = item($_lang['view_logging'], 'index.php?a=13');
	// system-info
	$reportsmenu[] = item($_lang['view_sysinfo'], 'index.php?a=53');
}

// Output Menus where there are items to show
if (!empty($sitemenu)) {
	echo "\t",'<li id="limenu3" class="active"><a href="#menu3" onclick="new NavToggle(this); return false;">',$_lang['site'],'</a><ul class="subnav" id="menu3">',"\n\t\t",
	     implode("\n\t\t", $sitemenu),
	     "\n\t</ul></li>\n";
}
if (!empty($resourcemenu)) {
	echo "\t",'<li id="limenu5"><a href="#menu5" onclick="new NavToggle(this); return false;">',$_lang['elements'],'</a><ul class="subnav" id="menu5">',"\n\t\t",
	     implode("\n\t\t", $resourcemenu),
	     "\n\t</ul></li>\n";
}
if (!empty($modulemenu)) {
	echo "\t",'<li id="limenu9"><a href="#menu9" onclick="new NavToggle(this); return false;">',$_lang['modules'],'</a><ul class="subnav" id="menu9">',"\n\t\t",
	     implode("\n\t\t", $modulemenu),
	     "\n\t</ul></li>\n";
}
if (!empty($securitymenu)) {
	echo "\t",'<li id="limenu2"><a href="#menu2" onclick="new NavToggle(this); return false;">',$_lang['users'],'</a><ul class="subnav" id="menu2">',"\n\t\t",
	     implode("\n\t\t", $securitymenu),
	     "\n\t</ul></li>\n";
}
if (!empty($toolsmenu)) {
	echo "\t",'<li id="limenu1-1"><a href="#menu1-1" onclick="new NavToggle(this); return false;">',$_lang['tools'],'</a><ul class="subnav" id="menu1-1">',"\n\t\t",
	     implode("\n\t\t", $toolsmenu),
	     "\n\t</ul></li>\n";
}
if (!empty($reportsmenu)) {
	echo "\t",'<li id="limenu1-2"><a href="#menu1-2" onclick="new NavToggle(this); return false;">',$_lang['reports'],'</a><ul class="subnav" id="menu1-2">',"\n\t\t",
	     implode("\n\t\t", $reportsmenu),
	     "\n\t</ul></li>\n";
}
?>
	</ul>
</div>
</div>
</form>

<div id="menuSplitter"></div>
</body>
</html>
