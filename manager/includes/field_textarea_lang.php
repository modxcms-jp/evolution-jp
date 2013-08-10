<?php
/**
 * Поле для ввода текста (textarea с выбором языка)
 * User: tonatos
 * Date: 05.08.13
 * Time: 20:30
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

?>

    <th><?=l($input->title)?><br />
        <p>
            <?php echo $_lang["update_settings_from_language"]; ?>
        </p>
        <select name="reload_<?=$input->setting_name?>" id="reload_<?=$input->setting_name?>_select"
                onchange="confirmLangChange(this, '<?=$input->setting_name?>_default', '<?=$input->setting_name?>_textarea');">
            <?php echo get_lang_options($input->setting_name.'_default');?>
        </select>
    </th>
    <td>
        <textarea name="<?=$input->setting_name?>" id="<?=$input->setting_name?>_textarea" style="width:100%; height: 120px;"><?php
            echo isset($settings[$input->setting_name]) ? $settings[$input->setting_name] : l($input->setting_name.'_default');
         ?></textarea>
        <input type="hidden" id="<?=$input->setting_name?>_default_hidden" value="<?php echo addslashes(l($input->setting_name.'_default'));?>" /><br />
        <?php echo l($input->description)?>
    </td>
