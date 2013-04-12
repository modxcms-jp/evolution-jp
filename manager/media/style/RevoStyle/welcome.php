<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

function welcomeRevoStyle($modx,$_lang)
{
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	
	$tpl = '<a class="hometblink" href="[+action+]"><img src="[(site_url)]manager/media/style/RevoStyle/images/[+imgpath+]" /><br />[+title+]</a>' . "\n";
	$tpl = '<span class="wm_button" style="border:0">' . $tpl . '</span>';
	
	if($modx->hasPermission('new_document')||$modx->hasPermission('save_document')) {
		$ph['imgpath'] = 'icons/32x/newdoc.png';
		$ph['action']    = 'index.php?a=4';
		$ph['title']   = $_lang['add_resource'];
		$src = $modx->parsePlaceholder($tpl,$ph);
		$modx->setPlaceholder('NewDocIcon',$src);
	}
	
	if($modx->hasPermission('settings')) {
		$ph['imgpath'] = 'icons/32x/settings.png';
		$ph['action']    = 'index.php?a=17';
		$ph['title']   = $_lang['edit_settings'];
		$src = $modx->parsePlaceholder($tpl,$ph);
		$modx->setPlaceholder('SettingsIcon',$src);
	}
}

function tabYourInfo($modx,$_lang) {
	global $server_offset_time;
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
    $user_info = $modx->parsePlaceholder($tpl,$ph);
    
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
    		$recent_info .= $modx->parsePlaceholder($tpl,$row);
    	}
    }
    $recent_info.='</ul>';
    
    $modx->setPlaceholder('recent_docs',$_lang['recent_docs']);
    $ph['UserInfo']       = $user_info;
    $ph['RecentInfo']     = $recent_info;
    
    $tpl = <<< TPL
<div class="tab-page" id="tabYour">
	<h2 class="tab">[+yourinfo_title+]</h2>
	<script type="text/javascript">tpPane.addTabPage(document.getElementById("tabYour"));</script>
	<div class="sectionHeader">[+activity_title+]</div>
	<div class="sectionBody">[+RecentInfo+]</div>
	<div class="sectionHeader">[+yourinfo_title+]</div>
	<div class="sectionBody">[+UserInfo+]</div>
</div>
TPL;
    $tabYourInfo = $modx->parsePlaceholder($tpl,$ph);
    $modx->setPlaceholder('tabYourInfo',$tabYourInfo);
}

function tabOnlineUser($modx,$_lang)
{
	if(!isset($_GET['a']) || $_GET['a']!=='2') return;
	$ph = $_lang;
    $timetocheck = (time()-(60*20));//+$server_offset_time;
    
    include_once($modx->config['core_path'] . 'actionlist.inc.php');
    $rs = $modx->db->select('*','[+prefix+]active_users', "lasthit>'{$timetocheck}'", 'username ASC');
    $total = $modx->db->getRecordCount($rs);
    if($total==1)
    {
    	$ph['OnlineInfo'] = $modx->parsePlaceholder('<p>[+no_active_users_found+]</p>',$ph);
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
        $ph['OnlineInfo'] = $modx->parsePlaceholder($tpl,$ph);
    
    }
    $tpl = <<< TPL
<div class="tab-page" id="tabOnline">
	<h2 class="tab">[+online+]</h2>
	<script type="text/javascript">tpPane.addTabPage(document.getElementById("tabOnline"));</script>
	<div class="sectionHeader">[+onlineusers_title+]</div>
    <div class="sectionBody">[+OnlineInfo+]</div>
</div>
TPL;
    $tabOnlineUser = $modx->parsePlaceholder($tpl,$ph);
    $modx->setPlaceholder('tabOnlineUser',$tabOnlineUser);
}
