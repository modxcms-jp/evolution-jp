<?php
/**
 * Created by JetBrains PhpStorm.
 * User: tonatos
 * Date: 05.08.13
 * Time: 21:00
 * To change this template use File | Settings | File Templates.
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

?>

<th><?php echo l($input->title)?></th>
<td>
    <select name="<?php echo $input->setting_name?>" size="1" class="inputBox">
        <?php
        for($i=-24; $i<25; $i++)
        {
            $seconds = $i*60*60;
            $selectedtext = $seconds==$settings[$input->setting_name] ? "selected='selected'" : "" ;
            echo '<option value="' . $seconds . '" ' . $selectedtext . '>' . $i . "</option>\n";
        }
        ?>
    </select><br />
    <?php printf(l($input->description), strftime('%H:%M:%S', time()), strftime('%H:%M:%S', time()+$server_offset_time)); ?>
</td>
