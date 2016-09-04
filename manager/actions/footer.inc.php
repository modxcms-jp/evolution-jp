<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
// display system alert window if messages are available
if (count($modx->SystemAlertMsgQueque)>0) {
	echo $modx->manager->sysAlert($modx->SystemAlertMsgQueque);
}
if(in_array($modx->manager->action,array(85,27,4,72,131,132,74,13,11,12,77,78,87,88)))
    echo $modx->manager->loadDatePicker($modx->config['mgr_date_picker_path']);
?>
<script>
    jQuery('#preLoader').hide();
</script>
</body>
</html>
