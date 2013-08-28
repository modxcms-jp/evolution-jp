<?php
/**
 * Поле для выподающего списка (в качестве option используется $options[1] массив значений разделенных ";" )
 * User: tonatos
 * Date: 01.08.13
 * Time: 22:42
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

?>

<th><?php echo l($input->title)?></th>
<td>

    <select name="<?php echo $input->setting_name?>" size="1" class="inputBox" style="width:250px;">

        <?php
        $opts = explode(";",$options[1]);
        foreach ($opts as $opt){
            if (!empty($opt)){
                if (strpos($opt,"=")!==false){
                    list($key,$value) = explode("=",$opt);
                }else{
                    $key=$value=$opt;
                }
                echo "<option value='$key'";
                if ($settings[$input->setting_name]==$key) echo " selected='selected'";
                echo ">$value</option>";
            }
        }
        ?>
    </select><br />
    <?php echo l($input->description)?>
</td>