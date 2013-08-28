<?php
/**
 * Поле для списка ролей
 * User: tonatos
 * Date: 01.08.13
 * Time: 22:42
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

?>

<th><?php echo l($input->title)?></th>
<td>
    <select name="<?php echo $input->setting_name?>">
        <?php

        global $modx;

        $rs = $modx->db->select('id,name', '[+prefix+]user_roles', 'id!=1', 'save_role DESC,new_role DESC,id ASC');
        $tpl = '<option value="[+id+]" [+selected+]>[+name+]</option>';
        $options = "\n";
        while($ph=$modx->db->getRow($rs))
        {
            $ph['selected'] = ($settings[$input->setting_name] == $ph['id']) ? ' selected' : '';
            $options .= $modx->parsePlaceholder($tpl,$ph);
        }
        echo  $options;

        ?>
    </select><br />
    <?php echo l($input->description)?>
</td>