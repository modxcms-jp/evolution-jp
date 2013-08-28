<?php
/**
 * Поле для ввода текста (небольшая textarea)
 * User: tonatos
 * Date: 01.08.13
 * Time: 22:42
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

?>

<th><?php echo l($input->title)?></th>
<td>
    <textarea name="<?php echo $input->setting_name?>" id="<?php echo $input->setting_name?>" style="width:300px; height: 4em;"><?php echo $settings[$input->setting_name] ?></textarea><br />
    <?php echo l($input->description)?>
</td>