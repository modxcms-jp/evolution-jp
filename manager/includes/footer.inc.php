<?php
if(!defined('IN_MANAGER_MODE') || IN_MANAGER_MODE != 'true') exit();
// display system alert window if messages are available
if (count($modx->SystemAlertMsgQueque)>0) {
	echo $modx->manager->sysAlert($modx->SystemAlertMsgQueque);
}
?>
</body>
</html>
