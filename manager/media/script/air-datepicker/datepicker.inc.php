<?php
class DATEPICKER {
    function __construct() {
    }
    function getDP() {
        return file_get_contents(MODX_MANAGER_PATH . 'media/script/air-datepicker/datepicker.tpl');
    }
}
