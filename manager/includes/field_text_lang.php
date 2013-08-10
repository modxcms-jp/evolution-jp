<?php
/**
 * Поле для ввода текста (text с выбором языка)
 * User: tonatos
 * Date: 01.08.13
 * Time: 22:42
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

?>

<th><?=l($input->title)?><br />
    <p>
        <?php echo $_lang["update_settings_from_language"]; ?>
    </p>
    <select name="reload_<?=$input->setting_name?>" id="reload_<?=$input->setting_name?>_select"
            onchange="confirmLangChange(this, '<?=$input->setting_name?>_default', '<?=$input->setting_name?>_input');">
        <?php echo get_lang_options($input->setting_name.'_default');?>
    </select></th>
<td>
    <?php echo form_text($input->setting_name,$settings[$input->setting_name],255,'id="'.$input->setting_name.'_input" style="width:400px"');?><br />
    <input type="hidden" id="<?=$input->setting_name?>_default_hidden" value="<?php echo addslashes(l($input->setting_name.'_default'));?>" /><br />
    <?php echo l($input->description)?>
</td>