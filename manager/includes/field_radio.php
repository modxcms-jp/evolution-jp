<?php
/**
 * Поле для радиобуттонов
 * пример: radio||yes=1;no=0
 *
 * User: tonatos
 * Date: 02.08.13
 * Time: 22:44
 * 
 */
?>
<th><?=l($input->title)?></th>
<td>
    <?php
    $opts = explode(";",$options[1]);
    foreach ($opts as $opt){
        list($opt_name,$opt_value)= explode("=",$opt);
        echo wrap_label(l($opt_name),form_radio($input->setting_name,$opt_value, $settings[$input->setting_name]==$opt_value));
        echo "<br />";
    }
    ?>
    <?php echo l($input->description)?>
</td>