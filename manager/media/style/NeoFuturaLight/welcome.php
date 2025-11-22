<?php
if (!isset($modx) || !evo()->isLoggedin()) exit;

$style_images_path = manager_style_image_path();
$style_tree_path = manager_style_image_path('tree');

function iconMessage()
{
    global $modx, $_lang;

    if (getv('a') != 2) return;
    if (evo()->hasPermission('messages')) {
        $ph['imgsrc'] = (sessionv('nrnewmessages', 0) > 0) ? 'icons/32x/mail_new.png' : 'icons/32x/mail.png';
        $ph['action'] = 'index.php?a=10';
        $ph['title'] = $_lang['inbox'];
        $modx->setPlaceholder('iconMessage', $modx->parseText(icontpl(), $ph));
    }
}

function iconElements()
{
    global $modx, $_lang;

    if (getv('a') != 2) return;
    if (evo()->hasPermission('new_template') || evo()->hasPermission('edit_template') || evo()->hasPermission('new_snippet') || evo()->hasPermission('edit_snippet') || evo()->hasPermission('new_plugin') || evo()->hasPermission('edit_plugin')) {
        $ph['imgsrc'] = 'icons/32x/elements.png';
        $ph['action'] = 'index.php?a=76';
        $ph['title'] = $_lang['element_management'];
        $modx->setPlaceholder('iconElements', $modx->parseText(icontpl(), $ph));
    }
}

function iconNewDoc()
{
    global $modx, $_lang;

    if (getv('a') != 2) return;
    if (evo()->hasPermission('new_document') || evo()->hasPermission('save_document')) {
        $ph['imgsrc'] = 'icons/32x/newdoc.png';
        $ph['action'] = 'index.php?a=4';
        $ph['title'] = $_lang['add_resource'];
        $modx->setPlaceholder('iconNewDoc', $modx->parseText(icontpl(), $ph));
    }
}

function iconSettings()
{
    global $modx, $_lang;

    if (getv('a') != 2) return;
    if (evo()->hasPermission('settings')) {
        $ph['imgsrc'] = 'icons/32x/settings.png';
        $ph['action'] = 'index.php?a=17';
        $ph['title'] = $_lang['edit_settings'];
        $modx->setPlaceholder('iconSettings', $modx->parseText(icontpl(), $ph));
    }
}

function iconResources()
{
    global $modx, $_lang;

    if (getv('a') != 2) return;
    if (evo()->hasPermission('view_document')) {
        $ph['imgsrc'] = 'icons/32x/resources.png';
        $ph['action'] = 'index.php?a=120';
        $ph['title'] = $_lang['view_child_resources_in_container'];
        $modx->setPlaceholder('iconResources', $modx->parseText(icontpl(), $ph));
    }
}

function iconHelp()
{
    global $modx, $_lang;

    if (getv('a') != 2) return;
    if (evo()->hasPermission('help')) {
        $ph['imgsrc'] = 'icons/32x/help.png';
        $ph['action'] = 'index.php?a=9';
        $ph['title'] = $_lang['help'];
        $modx->setPlaceholder('iconHelp', $modx->parseText(icontpl(), $ph));
    }
}

function iconFileManager()
{
    global $modx, $_lang;

    if (getv('a') != 2) return;
    if (evo()->hasPermission('file_manager')) {
        $ph['imgsrc'] = 'icons/32x/files.png';
        $ph['action'] = 'index.php?a=31';
        $ph['title'] = $_lang['manage_files'];
        $modx->setPlaceholder('iconFileManager', $modx->parseText(icontpl(), $ph));
    }
}

function iconEventLog()
{
    global $modx, $_lang;

    if (getv('a') != 2) return;
    if (evo()->hasPermission('view_eventlog')) {
        $ph['imgsrc'] = 'icons/32x/log.png';
        $ph['action'] = 'index.php?a=114';
        $ph['title'] = $_lang['eventlog'];
        $modx->setPlaceholder('iconEventLog', $modx->parseText(icontpl(), $ph));
    }
}

function iconSysInfo()
{
    global $modx, $_lang;

    if (getv('a') != 2) return;
    if (evo()->hasPermission('logs')) {
        $ph['imgsrc'] = 'icons/32x/info.png';
        $ph['action'] = 'index.php?a=53';
        $ph['title'] = $_lang['view_sysinfo'];
        $modx->setPlaceholder('iconSysInfo', $modx->parseText(icontpl(), $ph));
    }
}

function iconSearch()
{
    global $modx, $_lang;

    if (getv('a') != 2) return;
    $ph['imgsrc'] = 'icons/32x/search.png';
    $ph['action'] = 'index.php?a=71';
    $ph['title'] = $_lang['search_resource'];
    $modx->setPlaceholder('iconSearch', $modx->parseText(icontpl(), $ph));
}

function tabYourInfo()
{
    global $modx, $_lang;

    if (getv('a') != 2) return;

    $ph = $_lang;

    if (sessionv('mgrLastlogin')) {
        $Lastlogin = $modx->toDateFormat(sessionv('mgrLastlogin', 0) + config('server_offset_time'));
    } else {
        $Lastlogin = '-';
    }

    $ph['UserName'] = $modx->getLoginUserName();
    $ph['name'] = sessionv('mgrPermissions.name');
    $ph['Lastlogin'] = $Lastlogin;
    $ph['Logincount'] = sessionv('mgrLogincount', 0) + 1;


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
    $user_info = $modx->parseText($tpl, $ph);

    // recent document info
    $uid = evo()->getLoginUserID();
    $field = 'id, pagetitle, description, editedon, editedby';
    $tbl_site_content = evo()->getFullTableName('site_content');
    $where = "deleted=0 AND editedby='{$uid}'";
    $rs = db()->select($field, $tbl_site_content, $where, 'editedon DESC', 10);

    $recent_info = $_lang["activity_message"] . '<br /><br /><ul>';

    if (db()->count($rs) < 1) {
        $recent_info .= '<li>' . $_lang['no_activity_message'] . '</li>';
    } else {
        $tpl = '<li><b>[+editedon+]</b> - [[+id+]] <a href="index.php?a=3&amp;id=[+id+]">[+pagetitle+]</a>[+description+]</li>';
        while ($row = db()->getRow($rs)) {
            $row['editedon'] = $modx->toDateFormat($row['editedon']);
            if ($row['description'] != '') {
                $row['description'] = ' - ' . $row['description'];
            }
            $recent_info .= $modx->parseText($tpl, $row);
        }
    }
    $recent_info .= '</ul>';

    $modx->setPlaceholder('recent_docs', $_lang['recent_docs']);
    $ph['UserInfo'] = $user_info;
    $ph['RecentInfo'] = $recent_info;

    $tpl = <<< TPL
<div class="tab-page" id="tabYour">
	<h2 class="tab">[+yourinfo_title+]</h2>
    <script type="text/javascript">tpPane.addTabPage( document.getElementById( "tabYour" ) );</script>
	<div class="sectionHeader">[+activity_title+]</div>
	<div class="sectionBody">[+RecentInfo+]</div>
	<div class="sectionHeader">[+yourinfo_title+]</div>
	<div class="sectionBody">[+UserInfo+]</div>
</div>
TPL;
    $tabYourInfo = $modx->parseText($tpl, $ph);
    $modx->setPlaceholder('tabYourInfo', $tabYourInfo);
}

function tabOnlineUser()
{
    global $modx, $_lang, $style_tree_path;

    if (getv('a') != 2) return;
    $ph = $_lang;
    $timetocheck = (time() - (60 * 20));//+$server_offset_time;

    include_once($modx->config['core_path'] . 'actionlist.inc.php');
    $rs = db()->select('*', '[+prefix+]active_users', "lasthit>'{$timetocheck}'", 'username ASC');
    $total = db()->count($rs);
    if ($total == 1) {
        $ph['OnlineInfo'] = $modx->parseText('<p>[+no_active_users_found+]</p>', $ph);
    } else {
        $tr = [];
        while ($row = db()->getRow($rs)) {
            $currentaction = getAction($row['action'], $row['id']);
            $webicon = ($row['internalKey'] < 0) ? '<img src="' . $style_tree_path . 'globe.png" alt="Web user" />' : '';
            $tr[] = sprintf(
                "<tr><td><b>%s</b></td><td>%s&nbsp;%d</td><td>%s</td><td>%s</td><td>%s</td></tr>",
                $row['username'],
                $webicon,
                abs($row['internalKey']),
                $row['ip'],
                date('H:i:s', $row['lasthit'] + config('server_offset_time')),
                $currentaction
            );
        }
        if (!empty($tr)) $ph['userlist'] = implode("\n", $tr);
        $ph['now'] = date('H:i:s', time() + config('server_offset_time'));
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
        $ph['OnlineInfo'] = $modx->parseText($tpl, $ph);

    }
    $tpl = <<< TPL
<div class="tab-page" id="tabOnline">
	<h2 class="tab">[+online+]</h2>
    <script type="text/javascript">tpPane.addTabPage( document.getElementById( "tabOnline" ) );</script>
	<div class="sectionHeader">[+onlineusers_title+]</div>
    <div class="sectionBody">[+OnlineInfo+]</div>
</div>
TPL;
    $tabOnlineUser = $modx->parseText($tpl, $ph);
    $modx->setPlaceholder('tabOnlineUser', $tabOnlineUser);
}

function icontpl()
{
    global $style_images_path;

    return '<span class="wm_button" style="border:0"><a class="hometblink" href="[+action+]"><img src="' . $style_images_path . '[+imgsrc+]" /><br />[+title+]</a></span>' . "\n";
}
