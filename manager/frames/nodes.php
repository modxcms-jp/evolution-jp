<?php
/**
 *  Tree Nodes
 *  Build and return document tree view nodes
 *
 */
if(!isset($modx) || !$modx->isLoggedin()) exit;

if($modx->getLoginUserType() !== 'manager') exit('Not Logged In!');

// save folderstate
if (isset($_GET['opened'])) $_SESSION['openedArray'] = $_GET['opened'];
if (isset($_GET['savestateonly'])) exit('savestateonly');
// setup sorting
if(isset($_REQUEST['tree_sortby']))  $_SESSION['tree_sortby'] = $_REQUEST['tree_sortby'];
if(isset($_REQUEST['tree_sortdir'])) $_SESSION['tree_sortdir'] = $_REQUEST['tree_sortdir'];

$indent    = intval($_GET['indent']);
$parent    = intval($_GET['parent']);
$expandAll = intval($_GET['expandAll']);

if (isset($_SESSION['openedArray'])) {
	$openedArray = explode('|', $_SESSION['openedArray']);
	foreach($openedArray as $i=>$v) {
		$openedArray[$i] = (int) $v;
	}
	$opened = array_filter($openedArray);
}
else
	$opened = array();
$opened2 = array();
$closed2 = array();

if($_SESSION['mgrDocgroups']) $docgrp = implode(',',$_SESSION['mgrDocgroups']);

// get document groups for current user
$mgrRole= (isset ($_SESSION['mgrRole']) && (string) $_SESSION['mgrRole']==='1') ? '1' : '0';

$output = getNodes($indent,$parent,$expandAll);

// check for deleted documents on reload
if ($expandAll==2)
{
	$rs = $modx->db->select('COUNT(id)','[+prefix+]site_content','deleted=1');
	if ($modx->db->getValue($rs) > 0) $output .= '<span id="binFull"></span>'; // add a special element to let system now that the bin is full
}

echo $output;

function getNodes($indent,$parent=0,$expandAll,$output='')
{
	global $modx;
	global $_style,$modx_textdir,$_lang, $opened, $opened2, $closed2, $docgrp,$mgrRole;
	
	if($parent=='') $parent = 0;

	// setup spacer
	$spacer = get_spacer($indent);
	$tree_orderby = get_tree_orderby();
	$in_docgrp = !$docgrp ? '':"OR dg.document_group IN ({$docgrp})";
	$access = $modx->config['tree_show_protected'] ? '':"AND (1={$mgrRole} OR sc.privatemgr=0 {$in_docgrp})";
	
	$field  = 'DISTINCT sc.id,pagetitle,menutitle,parent,isfolder,published,deleted,type,menuindex,hidemenu,alias,contentType';
	$field .= ",privateweb, privatemgr,MAX(IF(1={$mgrRole} OR sc.privatemgr=0 {$in_docgrp}, 1, 0)) AS has_access, rev.status AS status";
	$from   = '[+prefix+]site_content AS sc';
	$from   .= ' LEFT JOIN [+prefix+]document_groups dg on dg.document = sc.id';
	$from   .= " LEFT JOIN [+prefix+]site_revision rev on rev.elmid = sc.id AND (rev.status='draft' OR rev.status='standby') AND rev.element='resource'";
	$where  = "parent='{$parent}' {$access} GROUP BY sc.id,rev.status";
	$result = $modx->db->select($field,$from,$where,$tree_orderby);
	$hasChild = $modx->db->getRecordCount($result);
	
	if(!isset($modx->config['limit_by_container'])) $modx->config['limit_by_container'] = '';
	
	if($modx->config['tree_page_click']!=='27'&&$parent!=0)
	{
		if($modx->config['limit_by_container']==='')             $container_status = 'asis';
		elseif($modx->config['limit_by_container'] === '0')      $container_status = 'container_only';
		elseif($modx->config['limit_by_container'] < $hasChild) $container_status = 'too_many';
		else $container_status = 'asis';
		if($container_status!=='asis' && $parent !=='0')
		{
			$where  = "isfolder=1 AND {$where}";
			$result = $modx->db->select($field,$from,$where,$tree_orderby);
			$hasChild = $modx->db->getRecordCount($result);
		}
	}
	
	$pad = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
	if($hasChild==0 && $container_status !== 'container_only')
	{
		if($container_status==='too_many') $msg = $_lang['too_many_resources'];
		else                               $msg = $_lang['empty_folder'];
		
		$tpl = tplEmptyFolder();
		$param = array('spacer'=>$spacer.$pad,'icon_deletedpage'=>$_style['tree_deletedpage'],'msg'=>$msg);
		if($msg) $output .= $modx->parseText($tpl,$param);
	}
	
	
	$loop_count = 0;
	$node_name_source = $modx->config['resource_tree_node_name'];
	global $privateweb,$privatemgr;
	while($row = $modx->db->getRow($result,'num')):
		$loop_count++;
		list($id,$pagetitle,$menutitle,$parent,$isfolder,$published,$deleted,$type,$menuindex,$hidemenu,$alias,$contenttype,$privateweb,$privatemgr,$hasAccess,$hasDraft) = $row;
		$nodetitle = getNodeTitle($node_name_source,$id,$pagetitle,$menutitle,$alias,$isfolder);
		
		$class = getClassName($published,$deleted,$hidemenu,$hasAccess);
		
		$ph['id']        = $id;
		$ph['hasdraft']    = !empty($hasDraft) ? 1 : 0;
		
		$draftDisplay = '';
		if($modx->config['enable_draft'])
	    {
	    	$tpl = '&nbsp;<img src="%s">&nbsp;';
    		if($hasDraft==='draft')       $draftDisplay = sprintf($tpl,$_style['tree_draft']);
    		elseif($hasDraft==='standby') $draftDisplay = sprintf($tpl,$_style['icons_date']);
		}
		
		$ph['alt']       = getAlt($id,$alias,$menuindex,$hidemenu,$privatemgr,$privateweb);
		$ph['parent']    = $parent;
		$ph['spacer']    = $spacer;
		$pagetitle = addslashes($pagetitle);
		$pagetitle = htmlspecialchars($pagetitle,ENT_QUOTES,$modx->config['modx_charset']);
		$ph['pagetitle'] = "'{$pagetitle}'";
		$ph['nodetitle'] = "'".addslashes($nodetitle)."'";
		$url = $modx->makeUrl($id,'','','full');
		$ph['url']       = "'{$url}'";
		$ph['published'] = $published;
		$ph['deleted']   = $deleted;
		$ph['nodetitleDisplay'] = '<span class="' . $class . '">' . $nodetitle . '</span>';
		$ph['pageIdDisplay']    = '<span>('.($modx_textdir==='rtl' ? '&rlm;':'').$id.')</span>';
		$ph['draftDisplay']   = $draftDisplay;
		$ph['_lang_click_to_context'] = $_lang['click_to_context'];
		
		if (!$isfolder)
		{
			$ph['pid']       = "'p{$id}'";
			$ph['pad']       = $pad;
			$ph['icon']      = getIcon($id,$contenttype,$isfolder);
			if($type==='reference') $ph['icon'] = $_style["tree_linkgo"];
			switch($modx->config['tree_page_click'])
			{
				case '27': $ph['ca'] = 'open';   break;
				case '3' : $ph['ca'] = 'docinfo';break;
				default  : $ph['ca'] = 'open';
			}
			$tpl = tplPageNode();
			$output .= parseNode($tpl,$ph,$id);
		}
		else
		{
			$ph['fid']       = "'f{$id}'";
			$ph['indent'] = $indent+1;
			switch($modx->config['tree_page_click'])
			{
				case '27': $ph['ca'] = 'open';   break;
				case '3' : $ph['ca'] = 'docinfo';break;
				default  : $ph['ca'] = 'doclist';
			}
			
			if($container_status === 'container_only' && $isfolder==1)
			{
				$where  = "parent='{$id}' AND isfolder=1 {$access} GROUP BY sc.id,rev.status";
				$result = $modx->db->select($field,$from,$where,$tree_orderby);
				$hasChild = $modx->db->getRecordCount($result);
			}
			
			$ph['icon'] = getIcon($id,$contenttype,$isfolder);
			if($type==='reference') $ph['icon'] = $_style["tree_linkgo"];
			$ph['private_status']         = ($privateweb == 1 || $privatemgr == 1) ? '1' : '0';
			
			// expandAll: two type for partial expansion
			if ($expandAll ==1 || ($expandAll == 2 && in_array($id, $opened)))
			{
				if($expandAll == 1) array_push($opened2, $id);
				if($container_status === 'container_only' && $hasChild==0)
					$ph['_style_tree_minusnode']  = $_style['tree_blanknode'];
				else
					$ph['_style_tree_minusnode']  = $_style['tree_minusnode'];
				$tpl = getFopenNode();
				$parseNode = parseNode($tpl,$ph,$id,$parent);
				if($parseNode)
				{
					$output .= $parseNode;
				}
				$indent++;
				$output = getNodes($indent,$id,$expandAll,$output);
				$indent--;
				$output .= '</div></div>';
			}
			else
			{
				if($container_status === 'container_only' && $hasChild==0)
					$ph['_style_tree_plusnode'] = $_style['tree_blanknode'];
				else
					$ph['_style_tree_plusnode'] = $_style['tree_plusnode'];
				$tpl = tplFcloseNode();
				$output .= parseNode($tpl,$ph,$id);
				if($parent!=0 && $container_status==='too_many' && $loop_count == $hasChild)
				{
					$tpl = tplEmptyFolder();
					$param = array('spacer'=>$spacer.$pad,'icon_deletedpage'=>$_style['tree_deletedpage'],'msg'=>$_lang['too_many_resources']);
					$output .= $modx->parseText($tpl,$param);
				}
				array_push($closed2, $id);
			}
		}
		// store vars in Javascript
		$a = array();
		if ($expandAll==1 && !empty($opened2)) {
			foreach ($opened2 as $d) {
				$a[] = sprintf('parent.openedArray[%d] = 1;', $d);
			}
		}
		elseif ($expandAll==0 && !empty($closed2)) {
			foreach ($closed2 as $d) {
				$a[] = sprintf('parent.openedArray[%d] = 0;', $d);
			}
		}
		if(!empty($a)) $output .= '<script type="text/javascript">' . "\n" . join("\n",$a) . "\n</script>";
	endwhile;
	return $output;
}

function tplPageNode()
{
	$src = <<< EOT
<div
	id="node[+id+]"
	p="[+parent+]"
	style="white-space: nowrap;"
><div>[+spacer+][+pad+]<img
	id="p[+id+]"
	align="absmiddle"
	title="[+_lang_click_to_context+]"
	style="cursor: pointer"
	src="[+icon+]"
	onclick="showPopup([+id+],[+pagetitle+],[+published+],[+deleted+],[+hasdraft+],event);return false;"
	oncontextmenu="this.onclick(event);return false;"
	onmouseover="setCNS(this, 1)"
	onmouseout="setCNS(this, 0)"
	onmousedown="itemToChange=[+id+]; selectedObjectName=[+pagetitle+]; selectedObjectDeleted=[+deleted+]; selectedObjectUrl=[+url+]"
/>&nbsp;<span
	p="[+parent+]"
	onclick="if(parent.tree.ca=='open'||parent.tree.ca=='docinfo'||parent.tree.ca=='doclist') parent.tree.ca='[+ca+]';treeAction([+id+], [+pagetitle+]); setSelected(this);"
	onmouseover="setHoverClass(this, 1);"
	onmouseout="setHoverClass(this, 0);"
	class="treeNode"
	onmousedown="itemToChange=[+id+]; selectedObjectName=[+pagetitle+]; selectedObjectDeleted=[+deleted+]; selectedObjectUrl=[+url+];"
	oncontextmenu="document.getElementById([+pid+]).onclick(event);return false;"
	title="[+alt+]">[+draftDisplay+][+nodetitleDisplay+]</span>[+pageIdDisplay+]</div></div>

EOT;
		return $src;
	}
	
	function getFopenNode()
	{
		$src = <<< EOT
<div id="node[+id+]" p="[+parent+]" style="white-space: nowrap;"><div>[+spacer+]<img
	id="s[+id+]"
	align="absmiddle"
	style="cursor:pointer;"
	src="[+_style_tree_minusnode+]"
	onclick="toggleNode(this,[+indent+],[+id+],0,[+private_status+]); return false;"
	oncontextmenu="this.onclick(event); return false;"
/>&nbsp;<img
	id="f[+id+]"
	align="absmiddle"
	title="[+_lang_click_to_context+]"
	style="cursor: pointer"
	src="[+icon+]"
	onclick="showPopup([+id+],[+pagetitle+],[+published+],[+deleted+],[+hasdraft+],event);return false;"
	oncontextmenu="this.onclick(event);return false;"
	onmouseover="setCNS(this, 1)"
	onmouseout="setCNS(this, 0)"
	onmousedown="itemToChange=[+id+]; selectedObjectName=[+pagetitle+]; selectedObjectDeleted=[+deleted+]; selectedObjectUrl=[+url+];"
/>&nbsp;<span
	onclick="if(parent.tree.ca=='open'||parent.tree.ca=='docinfo'||parent.tree.ca=='doclist') parent.tree.ca='[+ca+]';treeAction([+id+], [+pagetitle+]); setSelected(this);"
	onmouseover="setHoverClass(this, 1);"
	onmouseout="setHoverClass(this, 0);"
	class="treeNode"
	onmousedown="itemToChange=[+id+]; selectedObjectName=[+pagetitle+]; selectedObjectDeleted=[+deleted+]; selectedObjectUrl=[+url+];"
	oncontextmenu="document.getElementById([+fid+]).onclick(event);return false;"
	title="[+alt+]"
>[+draftDisplay+][+nodetitleDisplay+]</span>[+pageIdDisplay+]</div><div id="c[+id+]" style="display:block;">

EOT;
	return $src;
}

function tplFcloseNode()
{
	$src = <<< EOT
<div id="node[+id+]" p="[+parent+]" style="white-space: nowrap;"><div>[+spacer+]<img
	id="s[+id+]"
	align="absmiddle"
	style="cursor: pointer"
	src="[+_style_tree_plusnode+]"
	onclick="toggleNode(this,[+indent+],[+id+],0,[+private_status+]); return false;"
	oncontextmenu="this.onclick(event); return false;"
/>&nbsp;<img
	id="f[+id+]"
	title="[+_lang_click_to_context+]"
	align="absmiddle"
	style="cursor: pointer"
	src="[+icon+]"
	onclick="showPopup([+id+],[+pagetitle+],[+published+],[+deleted+],[+hasdraft+],event);return false;"
	oncontextmenu="this.onclick(event);return false;"
	onmouseover="setCNS(this, 1)"
	onmouseout="setCNS(this, 0)"
	onmousedown="itemToChange=[+id+]; selectedObjectName=[+pagetitle+]; selectedObjectDeleted=[+deleted+]; selectedObjectUrl=[+url+];"
/>&nbsp;<span
	onclick="if(parent.tree.ca=='open'||parent.tree.ca=='docinfo'||parent.tree.ca=='doclist') parent.tree.ca='[+ca+]';treeAction([+id+], [+pagetitle+]); setSelected(this);"
	onmouseover="setHoverClass(this, 1);"
	onmouseout="setHoverClass(this, 0);"
	class="treeNode"
	onmousedown="itemToChange=[+id+]; selectedObjectName=[+pagetitle+]; selectedObjectDeleted=[+deleted+]; selectedObjectUrl=[+url+];"
	oncontextmenu="document.getElementById([+fid+]).onclick(event);return false;"
	title="[+alt+]"
>[+draftDisplay+][+nodetitleDisplay+]</span>[+pageIdDisplay+]</div><div id="c[+id+]" style="display:none;"></div></div>

EOT;
	return $src;
}

function get_tree_orderby()
{
	global $modx;
	if (!isset($_SESSION['tree_sortby']) && !isset($_SESSION['tree_sortdir']))
	{
		// This is the first startup, set default sort order
		switch($modx->config['resource_tree_node_name'])
		{
			case 'createdon':
			case 'editedon':
			case 'publishedon':
			case 'pub_date':
			case 'unpub_date':
				$_SESSION['tree_sortby'] = $modx->config['resource_tree_node_name'];
				$_SESSION['tree_sortdir'] = 'DESC';
				break;
			default:
				$_SESSION['tree_sortby'] = 'menuindex';
				$_SESSION['tree_sortdir'] = 'ASC';
		}
	}
	$orderby = trim($orderby);
	$orderby = $modx->db->escape($_SESSION['tree_sortby']." ".$_SESSION['tree_sortdir']);
	if(empty($orderby)) $orderby = 'sc.menuindex ASC';

	// Folder sorting gets special setup ;) Add menuindex and pagetitle
	if($_SESSION['tree_sortby'] == 'isfolder') $orderby .= ', sc.menuindex ASC';
	$orderby  .= ', sc.editedon DESC';
	return $orderby;
}

function get_spacer($indent)
{
	$spacer = '';
	for ($i = 1; $i <= $indent; $i++)
	{
		if($i!==1) $spacer .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
		else       $spacer .= '&nbsp;&nbsp;&nbsp;';
	}
	return $spacer;
}

function getNodeTitle($node_name_source,$id,$pagetitle,$menutitle,$alias,$isfolder) {
	global $modx;
	
	switch($node_name_source)
	{
		case 'menutitle':
			$rs = $menutitle ? $menutitle : $pagetitle;
			break;
		case 'alias':
			$nodetitle = $alias ? $alias : $id;
			if((strpos($alias, '.') === false) || ($modx->config['suffix_mode']!=='1'))
			{
				if($isfolder!=1 || $modx->config['make_folders']!=='1')
					$nodetitle .= $modx->config['friendly_url_suffix'];
			}
			$rs = $modx->config['friendly_url_prefix'] . $nodetitle;
			break;
		case 'pagetitle':
			$rs = $pagetitle;
			break;
		case 'createdon':
		case 'editedon':
		case 'publishedon':
		case 'pub_date':
		case 'unpub_date':
			$doc = $modx->getDocumentObject('id',$id);
			$date = $doc[$node_name_source];
			if(!empty($date)) $rs = $modx->toDateFormat($date);
			else              $rs = '- - -';
			break;
		default:
			$rs = $pagetitle;
	}
	
	return evo()->hsc(str_replace(array("\r\n", "\n", "\r"), ' ', $rs));
}

function getIcon($id, $contenttype, $isfolder='0') {
	global $modx,$_style,$privateweb,$privatemgr;
	
	// icons by content type
	$icons = array(
		'application/rss+xml'      => $_style["tree_page_rss"],
		'application/pdf'          => $_style["tree_page_pdf"],
		'application/vnd.ms-word'  => $_style["tree_page_word"],
		'application/vnd.ms-excel' => $_style["tree_page_excel"],
		'text/css'                 => $_style["tree_page_css"],
		'text/html'                => $_style["tree_page_html"],
		'text/plain'               => $_style["tree_page"],
		'text/xml'                 => $_style["tree_page_xml"],
		'text/javascript'          => $_style["tree_page_js"],
		'image/gif'                => $_style["tree_page_gif"],
		'image/jpg'                => $_style["tree_page_jpg"],
		'image/png'                => $_style["tree_page_png"]
	);
	$iconsPrivate = array(
		'application/rss+xml'      => $_style["tree_page_rss_secure"],
		'application/pdf'          => $_style["tree_page_pdf_secure"],
		'application/vnd.ms-word'  => $_style["tree_page_word_secure"],
		'application/vnd.ms-excel' => $_style["tree_page_excel_secure"],
		'text/css'   => $_style["tree_page_css_secure"],
		'text/html'  => $_style["tree_page_html_secure"],
		'text/plain' => $_style["tree_page_secure"],
		'text/xml'   => $_style["tree_page_xml_secure"],
		'text/javascript' => $_style["tree_page_js_secure"],
		'image/gif'  => $_style["tree_page_gif"],
		'image/jpg'  => $_style["tree_page_jpg"],
		'image/png'  => $_style["tree_page_png"]
	);
	
	if($id == $modx->config['site_start'])                $rs = $_style["tree_page_home"];
	elseif($id == $modx->config['error_page'])            $rs = $_style["tree_page_404"];
	elseif($id == $modx->config['site_unavailable_page']) $rs = $_style["tree_page_hourglass"];
	elseif($id == $modx->config['unauthorized_page'])     $rs = $_style["tree_page_info"];
	else {
		if (!$privateweb&&!$privatemgr) :
			if($isfolder)                                 $rs = $_style['tree_folder'];
			elseif (isset($icons[$contenttype]))          $rs = $icons[$contenttype];
			else                                          $rs = $_style['tree_page'];
		else :
			if($isfolder)                                 $rs = $_style['tree_folderopen_secure'];
			elseif (isset($iconsPrivate[$contenttype]))   $rs = $iconsPrivate[$contenttype];
			else                                          $rs = $_style['tree_page_secure'];
		endif;
	}
	
	return $rs;
}

function getClassName($published,$deleted,$hidemenu,$hasAccess) {
	$protectedClass = $hasAccess==0 ? ' protectedNode' : '';
	if    ($deleted==1)   $rs = 'deletedNode';
	elseif($published==0) $rs = 'unpublishedNode';
	elseif($hidemenu==1)  $rs = "notInMenuNode{$protectedClass}";
	else                  $rs = "publishedNode{$protectedClass}";
	return $rs;
}

function getAlt($id,$alias='',$menuindex,$hidemenu,$privatemgr,$privateweb) {
	global $modx,$_lang;
	
	$_[] = "[{$id}] ";
	$_[] = $_lang['alias'] . ': ' . (!empty($alias) ? $alias : '-');
	$_[] = "{$_lang['resource_opt_menu_index']}: {$menuindex}";
	$_[] = "{$_lang['resource_opt_show_menu']}: " . ($hidemenu==1 ? $_lang['no']:$_lang['yes']);
	$_[] = "{$_lang['page_data_web_access']}: "   . ($privateweb  ? $_lang['private']:$_lang['public']);
	$_[] = "{$_lang['page_data_mgr_access']}: "   . ($privatemgr  ? $_lang['private']:$_lang['public']);
	$alt = join("\n", $_);
	$alt = addslashes($alt);
	return htmlspecialchars($alt,ENT_QUOTES,$modx->config['modx_charset']);
}

function tplEmptyFolder() {
	return '<div style="white-space:nowrap;">[+spacer+]<img align="absmiddle" src="[+icon_deletedpage+]">&nbsp;<span class="emptyNode">[+msg+]</span></div>';

}

function parseNode($tpl,$param,$id) {
	global $modx;

	$_tmp = $modx->config['limit_by_container'];
	$modx->config['limit_by_container'] = '';
	if($modx->manager->isContainAllowed($id)===false) return;
	$modx->config['limit_by_container'] = $_tmp;
	$modx->event->vars = array();
    $modx->event->vars = & $param;
    $modx->event->vars['tpl'] = & $tpl;
    $evtOut = $modx->invokeEvent('OnManagerNodePrerender', $param);
    if (is_array($evtOut)) $evtOut = implode("\n", $evtOut);
    else $evtOut = '';
    
	$node = $modx->parseText($tpl,$param);
    $node = "{$evtOut}{$node}";
	
    $param['node'] = $node;
    $evtOut = $modx->invokeEvent('OnManagerNodeRender',$param);
    $modx->event->vars = array();
    if (is_array($evtOut)) $evtOut = implode("\n", $evtOut);
    else $evtOut = '';
    
    if ($evtOut !== '') $node = $evtOut;
    
    return $node;
}
