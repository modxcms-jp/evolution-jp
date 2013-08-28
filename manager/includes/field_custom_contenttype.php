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
    <?php echo form_text('txt_'.$input->setting_name,'',100,'style="width:200px;"');?>
    <input type="button" value="<?php echo $_lang["add"]; ?>" onclick='addContentType()' /><br />
    <table>
        <tr>
            <td valign="top">
                <select name="lst_<?php echo $input->setting_name?>" style="width:200px;" size="5">
                    <?php
                    foreach(explode(',',$custom_contenttype) as $v)
                    {
                        echo '<option value="'.$v.'">'.$v."</option>\n";
                    }
                    ?>
                </select>
                <input name="<?php echo $input->setting_name?>" type="hidden" value="<?php echo $settings[$input->setting_name]; ?>" />
            </td>
            <td valign="top">
                &nbsp;<input name="removecontenttype" type="button" value="<?php echo $_lang["remove"]; ?>" onclick='removeContentType()' />
            </td>
        </tr>
    </table><br />
    <?php echo l($input->description)?>
</td>