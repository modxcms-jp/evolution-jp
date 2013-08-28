<?php
/**
 * Поле для ввода текста
 * User: tonatos
 * Date: 01.08.13
 * Time: 22:42
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if (!function_exists("form_text")){
    function form_text($name,$value,$maxlength='255',$add='',$readonly=false)
    {
        if($readonly) $readonly = ' disabled';
        if($add)      $add = ' ' . $add;
        if(empty($maxlength)) $maxlength = '255';
        if($maxlength<=10) $maxlength = 'maxlength="' . $maxlength . '" style="width:' . $maxlength . 'em;"';
        else               $maxlength = 'maxlength="' . $maxlength . '"';
        return '<input type="text" ' . $maxlength . ' name="' . $name . '" value="' . $value . '"' . $readonly . $add . ' />';
    }
}
?>

<th><?php echo l($input->title)?></th>
<td>
    <?php echo form_text($input->setting_name,$settings[$input->setting_name]);?>
    <?php if ($input->error):?>
        <span class="fail"><?php echo $error?></span>
    <?php endif;?>
    <br />
    <?php echo l($input->description)?>
</td>