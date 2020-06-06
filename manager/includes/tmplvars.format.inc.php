<?php
function getTVDisplayFormat($name, $value, $format, $paramstring = '', $tvtype = '', $docid = '', $sep = '') {
    global $modx;
    return $modx->tvProcessor($value, $format, $paramstring, $name, $tvtype, $docid, $sep);
}

function decodeParamValue($s) {
    global $modx;
    return $modx->decodeParamValue($s);
}

function parseInput($src, $delim = '||', $type = 'string', $columns = true) {
    global $modx;
    return $modx->parseInput($src, $delim, $type, $columns);
}

function getUnixtimeFromDateString($value) {
    global $modx;
    return $modx->getUnixtimeFromDateString($value);
}
