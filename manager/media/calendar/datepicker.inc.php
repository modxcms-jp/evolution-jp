<?php
class DATEPICKER {
    function __construct() {
    }
    function getDP() {
        global $modx;
        $ph['dayNames']   = $this->csv2jsArray($_lang['day_names']);
        $ph['monthNames'] = $this->csv2jsArray($_lang['month_names']);
        $ph['datepicker_offset'] = $modx->config['datepicker_offset'];
        $ph['datetime_format']   = $modx->config['datetime_format'];
        $tpl = file_get_contents(MODX_MANAGER_PATH . 'media/calendar/datepicker.tpl');
        return parseText($tpl,$ph);
    }
    function csv2jsArray($text) {
        return "['" . join("','", explode(',',$text)) . "']";
    }
}
