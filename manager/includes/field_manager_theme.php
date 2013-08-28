<?php
/**
 * Поле для выбора темы админки
 * User: tonatos
 * Date: 07.08.13
 * Time: 21:54
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
?>
<th><?php echo l($input->title)?></th>
<td><select name="<?php echo $input->setting_name?>" size="1" class="inputBox" onchange="document.forms['settings'].theme_refresher.value = Date.parse(new Date())">
<?php
$files = glob($base_path . 'manager/media/style/*/style.php');
foreach($files as $file)
{
    $file = str_replace('\\','/',$file);
    if($file!="." && $file!=".." && substr($file,0,1) != '.')
    {
        $themename = substr(dirname($file),strrpos(dirname($file),'/')+1);
        $selectedtext = $themename==$settings[$input->setting_name] ? "selected='selected'" : "" ;
        echo "<option value='$themename' $selectedtext>".ucwords(str_replace("_", " ", $themename))."</option>";
    }
}
?>
</select><br />
<input type="hidden" name="theme_refresher" value="" />
<input type="hidden" name="manager_direction" value="<?php echo isset($manager_direction)&&!empty($manager_direction) ? $manager_direction : 'ltr';?>" />
    <?php echo l($input->description)?>
</td>