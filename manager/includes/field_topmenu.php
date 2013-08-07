<?php
/**
 * Группа полей для формирования верхнего меню сайта
 * User: tonatos
 * Date: 07.08.13
 * Time: 22:11
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

$tmenu_style = 'style="width:350px;"';

?>
<th><?php echo $_lang["topmenu_items_title"]?></th>
<td>
    <table>
        <tr><td><?php echo  $_lang['site']     . '</td><td>' . form_text('topmenu_site',$settings['topmenu_site'],'',$tmenu_style);?></td></tr>
        <tr><td><?php echo  $_lang['elements'] . '</td><td>' . form_text('topmenu_element',$settings['topmenu_element'],'',$tmenu_style);?></td></tr>
        <tr><td><?php echo  $_lang['users']    . '</td><td>' . form_text('topmenu_security',$settings['topmenu_security'],'',$tmenu_style);?></td></tr>
        <tr><td><?php echo  $_lang['user']     . '</td><td>' . form_text('topmenu_user',$settings['topmenu_user'],'',$tmenu_style);?></td></tr>
        <tr><td><?php echo  $_lang['tools']    . '</td><td>' . form_text('topmenu_tools',$settings['topmenu_tools'],'',$tmenu_style);?></td></tr>
        <tr><td><?php echo  $_lang['reports']  . '</td><td>' . form_text('topmenu_reports',$settings['topmenu_reports'],'',$tmenu_style);?></td></tr>
    </table>
    <div><?php echo $_lang["topmenu_items_message"];?></div>
</td>