<?php
if (!isset($modx) || !evo()->isLoggedin()) exit;
// display system alert window if messages are available
if (is_array($modx->SystemAlertMsgQueque) && count($modx->SystemAlertMsgQueque) > 0) {
    echo manager()->sysAlert($modx->SystemAlertMsgQueque);
}
if (in_array(manager()->action, array(85, 27, 4, 72, 131, 132, 133, 74, 13, 11, 12, 77, 78, 87, 88)))
    echo manager()->loadDatePicker(
        $modx->config(
            'mgr_date_picker_path'
            , 'media/script/air-datepicker/datepicker.inc.php'
        )
    );
?>
</body>
</html>
