<?php
/**
 * Поле для радиобуттонов
 * пример: radio||yes=1;no=0[||depend||список зависимых полей через зяпятую]
 *
 * User: tonatos
 * Date: 02.08.13
 * Time: 22:44
 * 
 */

if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

if ($options[2]=="depend"){
    $depend_fields = explode(",",$options[3]);
    if (!empty($depend_fields)){
        foreach($depend_fields as $field){
            $depend_list[$field] = $input->setting_name;
        }
        ?>

        <script type="text/javascript">
            $j(function(){
                $j("input[name=<?php echo $input->setting_name?>]").change(function(){
                    if (($j(this).val()==1)||($j(this).val()=="yes")){
                        $j(".<?php echo $input->setting_name?>_depend").fadeIn();
                    }else{
                        $j(".<?php echo $input->setting_name?>_depend").fadeOut();
                    }
                });
            });
        </script>

        <?php
    }
}

?>

<th><?php echo l($input->title)?></th>
<td>
    <?php
    $opts = explode(";",$options[1]);
    foreach ($opts as $opt){
        list($opt_name,$opt_value)= explode("=",$opt);
        echo wrap_label(l($opt_name),form_radio($input->setting_name,$opt_value, $settings[$input->setting_name]==$opt_value,"id='{$input->setting_name}_{$opt_value}'"));
        echo "<br />";
    }
    ?>
    <?php echo l($input->description)?>
</td>