<?php
/**
 * Поле для выбора шаблона
 * User: tonatos
 * Date: 05.08.13
 * Time: 20:44
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

?>

    <th><?php echo l($input->title)?></th>
    <td>
        <select name="default_template" class="inputBox" onchange="wrap=document.getElementById('template_reset_options_wrapper');if(this.options[this.selectedIndex].value != '<?php echo $default_template;?>'){wrap.style.display='block';}else{wrap.style.display='none';}" style="width:150px">
            <?php
            $field = 't.templatename, t.id, c.category';
            $from = "[+prefix+]site_templates t LEFT JOIN [+prefix+]categories c ON t.category = c.id";
            $orderby = 'c.category, t.templatename ASC';
            $rs = $modx->db->select($field,$from,'',$orderby);
            $currentCategory = '';
            while ($row = $modx->db->getRow($rs))
            {
                $thisCategory = $row['category'];
                if($thisCategory == null)
                {
                    $thisCategory = $_lang["no_category"];
                }
                if($thisCategory != $currentCategory)
                {
                    if($closeOptGroup)
                    {
                        echo "\t\t\t\t\t</optgroup>\n";
                    }
                    echo "\t\t\t\t\t<optgroup label=\"{$thisCategory}\">\n";
                    $closeOptGroup = true;
                }
                else
                {
                    $closeOptGroup = false;
                }
                $selectedtext = $row['id'] == $settings[$input->setting_name] ? ' selected="selected"' : '';
                if ($selectedtext)
                {
                    $oldTmpId = $row['id'];
                    $oldTmpName = $row['templatename'];
                }
                echo "\t\t\t\t\t".'<option value="'.$row['id'].'"'.$selectedtext.'>'.$row['templatename']."</option>\n";
                $currentCategory = $thisCategory;
            }
            if($thisCategory != '')
            {
                echo "\t\t\t\t\t</optgroup>\n";
            }
            ?>
        </select><br />
        <div id="template_reset_options_wrapper" style="display:none;">
            <?php echo wrap_label($_lang["template_reset_all"],form_radio('reset_template','1'));?><br />
            <?php echo wrap_label(sprintf($_lang["template_reset_specific"],$oldTmpName),form_radio('reset_template','2'));?>
        </div>
        <input type="hidden" name="old_template" value="<?php echo $oldTmpId; ?>" />
        <?php echo l($input->description)?>
    </td>
