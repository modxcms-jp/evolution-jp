<?php
/**
 * Поле для выбора языка админки
 * User: tonatos
 * Date: 07.08.13
 * Time: 22:03
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

?>

<th><?=l($input->title)?></th>
<td>
    <select name="<?=$input->setting_name?>" size="1" class="inputBox">
        <?php echo get_lang_options(null, $settings[$input->setting_name]);?>
    </select><br />
    <?php echo l($input->description)?>
</td>