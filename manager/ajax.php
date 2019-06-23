<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
if($modx->getLoginUserType() !== 'manager') exit('Not Logged In!');

switch ($_REQUEST['ajaxa']) {
    case 16:
        $rs = include(MODX_MANAGER_PATH . 'actions/element/ajax/mutate_templates.php');break;
}

echo $rs;
exit;
