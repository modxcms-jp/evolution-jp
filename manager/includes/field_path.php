<?php
/**
 * Описание класса
 * User: tonatos
 * Date: 09.08.13
 * Time: 21:50
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

?>

<th><?php echo l($input->title)?></th>
<td>
    <?php echo $_lang['default']; ?> <span id="default_<?php echo $input->setting_name?>"><?php echo '[(base_path)]'; ?></span> <?php echo "({$base_path})";?><br />
    <?php echo form_text($input->setting_name,$settings[$input->setting_name],255,'id="'.$input->setting_name.'"');?>
    <input type="button" onclick="reset_path('<?php echo $input->setting_name?>');" value="<?php echo $_lang["reset"]; ?>" name="reset_<?php echo $input->setting_name?>"><br />
    <?php echo l($input->description)?>
</td>