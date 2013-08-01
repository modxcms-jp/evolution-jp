<?php
/**
 * Поле для ввода текста (небольшая textarea)
 * User: tonatos
 * Date: 01.08.13
 * Time: 22:42
 * 
 */


?>

<th><?=l($input->title)?></th>
<td>
    <textarea name="<?=$input->setting_name?>" id="<?=$input->setting_name?>" style="width:300px; height: 4em;"><?php echo $settings[$input->setting_name] ?></textarea><br />
    <?php echo l($input->description)?>
</td>