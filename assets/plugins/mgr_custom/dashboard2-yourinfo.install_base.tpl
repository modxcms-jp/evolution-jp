//<?php
/**
 * ダッシュボード・あなたの情報
 * 
 * ダッシュボードに「あなたの情報」を表示します。
 *
 * @category 	plugin
 * @version 	0.1
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * @internal	@events OnManagerWelcomeRender
 * @internal	@modx_category Manager and Admin
 * @internal    @installset base
 *
 * @author yama  / created: 2012/07/28
 */

global $_lang;

if(!empty($_SESSION['mgrLastlogin']))
{
     $Lastlogin = $modx->toDateFormat($_SESSION['mgrLastlogin']+$server_offset_time);
}
else $Lastlogin = '-';

$user_info = '
    <p>'.$_lang["yourinfo_message"].'</p>
    <table border="0" cellspacing="0" cellpadding="0">
      <tr>
        <td width="150">'.$_lang["yourinfo_username"].'</td>
        <td width="20">&nbsp;</td>
        <td><b>'.$modx->getLoginUserName().'</b></td>
      </tr>
      <tr>
        <td>'.$_lang["yourinfo_role"].'</td>
        <td>&nbsp;</td>
        <td><b>'.$_SESSION['mgrPermissions']['name'].'</b></td>
      </tr>
      <tr>
        <td>'.$_lang["yourinfo_previous_login"].'</td>
        <td>&nbsp;</td>
        <td><b>' . $Lastlogin . '</b></td>
      </tr>
      <tr>
        <td>'.$_lang["yourinfo_total_logins"].'</td>
        <td>&nbsp;</td>
        <td><b>'.($_SESSION['mgrLogincount']+1).'</b></td>
      </tr>
    </table>
';

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
	while($ph = $modx->db->getRow($rs))
	{
		$ph['editedon'] = $modx->toDateFormat($ph['editedon']);
		$ph['description'] = $ph['description']!='' ? ' - '.$ph['description'] : '';
		$recent_info .= $modx->parsePlaceholder($tpl,$ph);
	}
}
$recent_info.='</ul>';

$modx->setPlaceholder('recent_docs',$_lang['recent_docs']);
$ph = array();
$ph['UserInfo']       = $user_info;
$ph['info']           = $_lang['info'];
$ph['yourinfo_title'] = $_lang['yourinfo_title'];
$ph['RecentInfo']     = $recent_info;
$ph['activity_title'] = $_lang['activity_title'];

$block = <<< EOT
<div class="tab-page" id="tabYour" style="padding-left:0; padding-right:0">
	<h2 class="tab">[+yourinfo_title+]</h2>
	<script type="text/javascript">
		tpPane.addTabPage(document.getElementById("tabYour"));
	</script>
	<div class="sectionHeader">[+activity_title+]</div>
	<div class="sectionBody">
		[+RecentInfo+]
	</div>
	<div class="sectionHeader">[+yourinfo_title+]</div>
	<div class="sectionBody">
		[+UserInfo+]
	</div>
</div>
EOT;
$block = $modx->parsePlaceholder($block,$ph);
$modx->event->output($block);
