<?php
/**
 * Поле для ввода текста
 * User: tonatos
 * Date: 01.08.13
 * Time: 22:42
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

?>

<th><?=l($input->title)?></th>
<td>
    <?php echo form_text($input->setting_name,$settings[$input->setting_name]);?><br />
    <?php echo l($input->description)?>
</td>