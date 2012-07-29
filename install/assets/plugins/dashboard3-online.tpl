//<?php
/**
 * ダッシュボード・オンライン情報
 * 
 * ダッシュボードに「オンラインユーザー」を表示します。
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

$ph['online'] = $_lang['online'];
$ph['onlineusers_title'] = $_lang['onlineusers_title'];
$timetocheck = (time()-(60*20));//+$server_offset_time;

include_once($modx->config['base_path'] . 'manager/includes/actionlist.inc.php');
$tbl_active_users = $modx->getFullTableName('active_users');
$rs = $modx->db->select('*',$tbl_active_users,"lasthit>'{$timetocheck}'",'username ASC');
$limit = $modx->db->getRecordCount($rs);
if($limit<2)
{
	$html = "<p>".$_lang['no_active_users_found']."</p>";
}
else
{
	$html = '<p>' . $_lang["onlineusers_message"].'<b>'.strftime('%H:%M:%S', time()+$server_offset_time).'</b>)</p>';
	$html .= '
	<table border="0" cellpadding="1" cellspacing="1" width="100%" bgcolor="#ccc">
	<thead>
	<tr>
	<td><b>'.$_lang["onlineusers_user"].'</b></td>
	<td><b>'.$_lang["onlineusers_userid"].'</b></td>
	<td><b>'.$_lang["onlineusers_ipaddress"].'</b></td>
	<td><b>'.$_lang["onlineusers_lasthit"].'</b></td>
	<td><b>'.$_lang["onlineusers_action"].'</b></td>
	</tr>
	</thead>
	<tbody>
	';
	while ($row = $modx->db->getRow($rs))
	{
		$currentaction = getAction($row['action'], $row['id']);
		$webicon = ($row['internalKey']<0)? '<img src="' . $style_path . 'tree/globe.gif" alt="Web user" />':'';
		$html.= "<tr bgcolor='#FFFFFF'><td><b>".$row['username']."</b></td><td>{$webicon}&nbsp;".abs($row['internalKey'])."</td><td>".$row['ip']."</td><td>".strftime('%H:%M:%S', $row['lasthit']+$server_offset_time)."</td><td>{$currentaction}</td></tr>";
	}
        $html.= '
                </tbody>
                </table>
        ';
    }
$ph['OnlineInfo'] = $html;



$block = <<< EOT
<div class="tab-page" id="tabOnline" style="padding-left:0; padding-right:0">
	<h2 class="tab">[+online+]</h2>
	<script type="text/javascript">tpPane.addTabPage( document.getElementById( "tabOnline" ) );</script>
	<div class="sectionHeader">[+onlineusers_title+]</div><div class="sectionBody">
		[+OnlineInfo+]
	</div>
</div>
EOT;

$block = $modx->parsePlaceholder($block,$ph);
$modx->event->output($block);
