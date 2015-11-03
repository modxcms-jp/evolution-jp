<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

function iconMessage() {
	global $modx,$_lang;
	
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	$tpl = icontpl();
	if($modx->hasPermission('messages')) {
		$ph['imgsrc'] = ($_SESSION['nrnewmessages']>0) ? 'icons/32x/mail_new.png' : 'icons/32x/mail.png';
		$ph['action']    = 'index.php?a=10';
		$ph['title']   = $_lang['inbox'];
		$src = $modx->parseText($tpl,$ph);
		$modx->setPlaceholder('iconMessage',$src);
	}
}

function iconElements() {
	global $modx,$_lang;
	
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	$tpl = icontpl();
	if($modx->hasPermission('new_template') || $modx->hasPermission('edit_template') || $modx->hasPermission('new_snippet') || $modx->hasPermission('edit_snippet') || $modx->hasPermission('new_plugin') || $modx->hasPermission('edit_plugin')) {
		$ph['imgsrc'] = 'icons/32x/elements.png';
		$ph['action']    = 'index.php?a=76';
		$ph['title']   = $_lang['element_management'];
		$src = $modx->parseText($tpl,$ph);
		$modx->setPlaceholder('iconElements',$src);
	}
}

function iconNewDoc() {
	global $modx,$_lang;
	
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	$tpl = icontpl();
	if($modx->hasPermission('new_document')||$modx->hasPermission('save_document')) {
		$ph['imgsrc'] = 'icons/32x/newdoc.png';
		$ph['action']    = 'index.php?a=4';
		$ph['title']   = $_lang['add_resource'];
		$src = $modx->parseText($tpl,$ph);
		$modx->setPlaceholder('iconNewDoc',$src);
	}
}

function iconSettings() {
	global $modx,$_lang;
	
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	$tpl = icontpl();
	if($modx->hasPermission('settings')) {
		$ph['imgsrc'] = 'icons/32x/settings.png';
		$ph['action']    = 'index.php?a=17';
		$ph['title']   = $_lang['edit_settings'];
		$src = $modx->parseText($tpl,$ph);
		$modx->setPlaceholder('iconSettings',$src);
	}
}

function iconResources() {
	global $modx,$_lang;
	
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	$tpl = icontpl();
	if($modx->hasPermission('view_document')) {
		$ph['imgsrc'] = 'icons/32x/resources.png';
		$ph['action']    = 'index.php?a=120';
		$ph['title']   = $_lang['view_child_resources_in_container'];
		$src = $modx->parseText($tpl,$ph);
		$modx->setPlaceholder('iconResources',$src);
	}
}

function iconHelp() {
	global $modx,$_lang;
	
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	$tpl = icontpl();
	if($modx->hasPermission('help')) {
		$ph['imgsrc'] = 'icons/32x/help.png';
		$ph['action']    = 'index.php?a=9';
		$ph['title']   = $_lang['help'];
		$src = $modx->parseText($tpl,$ph);
		$modx->setPlaceholder('iconHelp',$src);
	}
}

function iconFileManager() {
	global $modx,$_lang;
	
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	$tpl = icontpl();
	if($modx->hasPermission('file_manager')) {
		$ph['imgsrc'] = 'icons/32x/files.png';
		$ph['action']    = 'index.php?a=31';
		$ph['title']   = $_lang['manage_files'];
		$src = $modx->parseText($tpl,$ph);
		$modx->setPlaceholder('iconFileManager',$src);
	}
}

function iconEventLog() {
	global $modx,$_lang;
	
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	$tpl = icontpl();
	if($modx->hasPermission('view_eventlog')) {
		$ph['imgsrc'] = 'icons/32x/log.png';
		$ph['action']    = 'index.php?a=114';
		$ph['title']   = $_lang['eventlog'];
		$src = $modx->parseText($tpl,$ph);
		$modx->setPlaceholder('iconEventLog',$src);
	}
}

function iconSysInfo() {
	global $modx,$_lang;
	
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	$tpl = icontpl();
	if($modx->hasPermission('logs')) {
		$ph['imgsrc'] = 'icons/32x/info.png';
		$ph['action']    = 'index.php?a=53';
		$ph['title']   = $_lang['view_sysinfo'];
		$src = $modx->parseText($tpl,$ph);
		$modx->setPlaceholder('iconSysInfo',$src);
	}
}

function iconSearch() {
	global $modx,$_lang;
	
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	$tpl = icontpl();
	$ph['imgsrc'] = 'icons/32x/search.png';
	$ph['action']    = 'index.php?a=71';
	$ph['title']   = $_lang['search_resource'];
	$src = $modx->parseText($tpl,$ph);
	$modx->setPlaceholder('iconSearch',$src);
}

function tabYourInfo() {
	global $modx,$_lang,$server_offset_time;
	
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	
	$ph = $_lang;
	
    if(!empty($_SESSION['mgrLastlogin']))
    {
         $Lastlogin = $modx->toDateFormat($_SESSION['mgrLastlogin']+$server_offset_time);
    }
    else $Lastlogin = '-';
    
	$ph['UserName']   = $modx->getLoginUserName();
	$ph['name']       = $_SESSION['mgrPermissions']['name'];
	$ph['Lastlogin']  = $Lastlogin;
	$ph['Logincount'] = $_SESSION['mgrLogincount'] + 1;
	
    
    $tpl = <<< TPL
<p>[+yourinfo_message+]</p>
<table border="0" cellspacing="0" cellpadding="0">
  <tr>
    <td width="150">[+yourinfo_username+]</td>
    <td width="20">&nbsp;</td>
    <td><b>[+UserName+]</b></td>
  </tr>
  <tr>
    <td>[+yourinfo_role+]</td>
    <td>&nbsp;</td>
    <td><b>[+name+]</b></td>
  </tr>
  <tr>
    <td>[+yourinfo_previous_login+]</td>
    <td>&nbsp;</td>
    <td><b>[+Lastlogin+]</b></td>
  </tr>
  <tr>
    <td>[+yourinfo_total_logins+]</td>
    <td>&nbsp;</td>
    <td><b>[+Logincount+]</b></td>
  </tr>
</table>
TPL;
    $user_info = $modx->parseText($tpl,$ph);
    
    // recent document info
    $uid = $modx->getLoginUserID();
    $field = 'id, pagetitle, description, editedon, editedby';
    $tbl_site_content = $modx->getFullTableName('site_content');
    $where = "deleted=0 AND editedby='{$uid}'";
    $rs = $modx->db->select($field,$tbl_site_content,$where,'editedon DESC',10);
    
    $recent_info = $_lang["activity_message"].'<br /><br /><ul>';
    
    if($modx->db->getRecordCount($rs) < 1) $recent_info .= '<li>'.$_lang['no_activity_message'].'</li>';
    else
    {
    	$tpl = '<li><b>[+editedon+]</b> - [[+id+]] <a href="index.php?a=3&amp;id=[+id+]">[+pagetitle+]</a>[+description+]</li>';
    	while($row = $modx->db->getRow($rs))
    	{
    		$row['editedon'] = $modx->toDateFormat($row['editedon']);
    		$row['description'] = $row['description']!='' ? ' - '.$row['description'] : '';
    		$recent_info .= $modx->parseText($tpl,$row);
    	}
    }
    $recent_info.='</ul>';
    
    $modx->setPlaceholder('recent_docs',$_lang['recent_docs']);
    $ph['UserInfo']       = $user_info;
    $ph['RecentInfo']     = $recent_info;
    
    $tpl = <<< TPL
<div class="tab-page" id="tabYour">
	<h2 class="tab">[+yourinfo_title+]</h2>
	<div class="sectionHeader">[+activity_title+]</div>
	<div class="sectionBody">[+RecentInfo+]</div>
	<div class="sectionHeader">[+yourinfo_title+]</div>
	<div class="sectionBody">[+UserInfo+]</div>
</div>
TPL;
    $tabYourInfo = $modx->parseText($tpl,$ph);
    $modx->setPlaceholder('tabYourInfo',$tabYourInfo);
}

function tabOnlineUser()
{
	global $modx,$_lang;
	
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	$ph = $_lang;
    $timetocheck = (time()-(60*20));//+$server_offset_time;
    
    include_once($modx->config['core_path'] . 'actionlist.inc.php');
    $rs = $modx->db->select('*','[+prefix+]active_users', "lasthit>'{$timetocheck}'", 'username ASC');
    $total = $modx->db->getRecordCount($rs);
    if($total==1)
    {
    	$ph['OnlineInfo'] = $modx->parseText('<p>[+no_active_users_found+]</p>',$ph);
    }
    else
    {
    	$tr = array();
    	while ($row = $modx->db->getRow($rs))
    	{
    		$currentaction = getAction($row['action'], $row['id']);
    		$webicon = ($row['internalKey']<0)? '<img src="media/style/' . $modx->config['manager_theme'] . '/images/tree/globe.gif" alt="Web user" />':'';
    		$tr[] = "<tr><td><b>".$row['username']."</b></td><td>{$webicon}&nbsp;".abs($row['internalKey'])."</td><td>".$row['ip']."</td><td>".strftime('%H:%M:%S', $row['lasthit']+$server_offset_time)."</td><td>{$currentaction}</td></tr>";
    	}
    	if(!empty($tr)) $ph['userlist'] = join("\n",$tr);
        $ph['now'] = strftime('%H:%M:%S', time()+$server_offset_time);
    	$tpl = <<< TPL
<p>[+onlineusers_message+]<b>[+now+]</b>)</p>
<table width="100%" class="grid">
<thead>
<tr>
<th>[+onlineusers_user+]</th>
<th>[+onlineusers_userid+]</th>
<th>[+onlineusers_ipaddress+]</th>
<th>[+onlineusers_lasthit+]</th>
<th>[+onlineusers_action+]</th>
</tr>
</thead>
<tbody>
[+userlist+]
</tbody>
</table>
TPL;
        $ph['OnlineInfo'] = $modx->parseText($tpl,$ph);
    
    }
    $tpl = <<< TPL
<div class="tab-page" id="tabOnline">
	<h2 class="tab">[+online+]</h2>
	<div class="sectionHeader">[+onlineusers_title+]</div>
    <div class="sectionBody">[+OnlineInfo+]</div>
</div>
TPL;
    $tabOnlineUser = $modx->parseText($tpl,$ph);
    $modx->setPlaceholder('tabOnlineUser',$tabOnlineUser);
}

function icontpl()
{
	return '<span class="wm_button" style="border:0"><a class="hometblink" href="[+action+]"><img src="media/style/RevoStyle/images/[+imgsrc+]" /><br />[+title+]</a></span>' . "\n";
}