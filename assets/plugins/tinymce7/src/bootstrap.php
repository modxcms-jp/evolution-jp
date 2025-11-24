<?php

if (!defined('MODX_BASE_PATH')) {
    die('No direct access allowed.');
}

if (!function_exists('evo')) {
    /** @var DocumentParser $modx */
    global $modx;

    if (!isset($modx) || !is_object($modx)) {
        die('Evolution CMS context not available.');
    }

    function evo()
    {
        /** @var DocumentParser $modx */
        global $modx;

        return $modx;
    }
}

require_once __DIR__ . '/autoload.php';
