<?php
/**
 * Поле для выбора визуального редактора
 * User: tonatos
 * Date: 08.08.13
 * Time: 23:12
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

?>

<th><?php echo l($input->title)?></th>
<td>
    <?php
    // invoke OnRichTextEditorRegister event
    $editors = $modx->invokeEvent("OnRichTextEditorRegister");
    if(is_array($editors))
    {
        $which_editor_sel = '<select name="'.$input->setting_name.'">';
        $which_editor_sel .= '<option value="none"' . ($settings[$input->setting_name]=='none' ? ' selected="selected"' : '') . '>' . $_lang["none"] . "</option>\n";
        foreach($editors as $editor)
        {
            $editor_sel = $settings[$input->setting_name]==$editor ? ' selected="selected"' : '';
            $which_editor_sel .= '<option value="' . $editor . '"' . $editor_sel . '>' . $editor . "</option>\n";
        }
        $which_editor_sel .= '</select><br />';
    } else {
        $which_editor_sel = '';
    }
    echo $which_editor_sel;
    ?>
    <br />
    <?php echo l($input->description)?>
</td>