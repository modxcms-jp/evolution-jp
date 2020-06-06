<?php
if (!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') {
    exit();
}
if (evo()->getLoginUserType() !== 'manager') {
    exit('Not Logged In!');
}

if (anyv('ajaxa') == 16) {
    echo include(MODX_MANAGER_PATH . 'actions/element/ajax/mutate_templates.php');
}

exit;
