<?php
if(!isset($modx) || !$modx->isLoggedin()) exit;
// display system alert window if messages are available
if (count($modx->SystemAlertMsgQueque)>0) {
	echo $modx->manager->sysAlert($modx->SystemAlertMsgQueque);
}
if(in_array($modx->manager->action,array(85,27,4,72,131,132,133,74,13,11,12,77,78,87,88)))
    echo $modx->manager->loadDatePicker($modx->config['mgr_date_picker_path']);
?>
</body>
</html>
