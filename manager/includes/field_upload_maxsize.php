<?php
/**
 * Описание класса
 * User: tonatos
 * Date: 09.08.13
 * Time: 22:03
 * 
 */

if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();

?>

<th><?php echo l($input->title)?></th>
<td>
    <?php
    if(version_compare(ini_get('upload_max_filesize'), ini_get('post_max_size'),'<'))
    {
        $limit_size = ini_get('upload_max_filesize');
    }
    else $limit_size = ini_get('post_max_size');

    if(version_compare(ini_get('memory_limit'), $limit_size,'<'))
    {
        $limit_size = ini_get('memory_limit');
    }
    if(empty($settings[$input->setting_name]))
    {
        $limit_size_bytes = $limit_size;
        $last = strtolower($limit_size_bytes[strlen($limit_size_bytes)-1]);
        switch($last)
        {
            case 'g':
                $limit_size_bytes *= 1024;
            case 'm':
                $limit_size_bytes *= 1024;
            case 'k':
                $limit_size_bytes *= 1024;
        }
        $settings[$input->setting_name] = $limit_size_bytes;
    }
    ?>
    <?php echo form_text($input->setting_name,intval($settings[$input->setting_name]));?><br />
    <?php echo sprintf($_lang[$input->description],$limit_size);?>
</td>