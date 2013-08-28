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
    <?php
    global $modx;
    $phm['sel']['BLOWFISH_Y'] = $settings[$input->setting_name]=='BLOWFISH_Y' ?  1 : 0;
    $phm['sel']['BLOWFISH_A'] = $settings[$input->setting_name]=='BLOWFISH_A' ?  1 : 0;
    $phm['sel']['SHA512']     = $settings[$input->setting_name]=='SHA512' ?  1 : 0;
    $phm['sel']['SHA256']     = $settings[$input->setting_name]=='SHA256' ?  1 : 0;
    $phm['sel']['MD5']        = $settings[$input->setting_name]=='MD5' ?  1 : 0;
    $phm['sel']['UNCRYPT']    = $settings[$input->setting_name]=='UNCRYPT' ?  1 : 0;
    $phm['e']['BLOWFISH_Y'] = $modx->manager->checkHashAlgorithm('BLOWFISH_Y') ? 0:1;
    $phm['e']['BLOWFISH_A'] = $modx->manager->checkHashAlgorithm('BLOWFISH_A') ? 0:1;
    $phm['e']['SHA512']     = $modx->manager->checkHashAlgorithm('SHA512') ? 0:1;
    $phm['e']['SHA256']     = $modx->manager->checkHashAlgorithm('SHA256') ? 0:1;
    $phm['e']['MD5']        = $modx->manager->checkHashAlgorithm('MD5') ? 0:1;
    $phm['e']['UNCRYPT']    = $modx->manager->checkHashAlgorithm('UNCRYPT') ? 0:1;
    ?>
    <?php echo wrap_label('CRYPT_BLOWFISH_Y (salt &amp; stretch)',form_radio($input->setting_name,'BLOWFISH_Y',$phm['sel']['BLOWFISH_Y'], '', $phm['e']['BLOWFISH_Y']));?><br />
    <?php echo wrap_label('CRYPT_BLOWFISH_A (salt &amp; stretch)',form_radio($input->setting_name,'BLOWFISH_A',$phm['sel']['BLOWFISH_A'], '', $phm['e']['BLOWFISH_A']));?><br />
    <?php echo wrap_label('CRYPT_SHA512 (salt &amp; stretch)'    ,form_radio($input->setting_name,'SHA512'    ,$phm['sel']['SHA512']    , '', $phm['e']['SHA512']));?><br />
    <?php echo wrap_label('CRYPT_SHA256 (salt &amp; stretch)'    ,form_radio($input->setting_name,'SHA256'    ,$phm['sel']['SHA256']    , '', $phm['e']['SHA256']));?><br />
    <?php echo wrap_label('CRYPT_MD5'       ,form_radio($input->setting_name,'MD5'       ,$phm['sel']['MD5']       , '', $phm['e']['MD5']));?><br />
    <?php echo wrap_label('UNCRYPT(32 chars salt + SHA-1 hash)'   ,form_radio($input->setting_name,'UNCRYPT'   ,$phm['sel']['UNCRYPT']   , '', $phm['e']['UNCRYPT']));?><br />
    <?php echo l($input->description)?>
</td>