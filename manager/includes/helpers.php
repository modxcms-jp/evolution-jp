<?php
function evo() {
    global $modx;
    return $modx;
}

function db() {
    return evo()->db;
}

function manager() {
    global $modx;
    return $modx->manager;
}

function hasPermission($permission) {
    return evo()->hasPermission($permission);
}

function config($key, $default=null) {
    return evo()->conf_var($key, $default);
}

function lang($key) {
    global $modx, $_lang;
    if(!$_lang) {
        include_once MODX_CORE_PATH . sprintf(
                'lang/%s.inc.php'
                , $modx->conf_var('manager_language', 'english')
            );
    }
    return $modx->array_get($_lang, $key, $key);
}

function style($key) {
    global $_style;
    return $_style[$key];
}

if (!function_exists('str_contains')) {
    function str_contains($str,$needle) {
        return strpos($str,$needle)!==false;
    }
}

function hsc($string) {
    return evo()->hsc($string);
}

function parseText($tpl,$ph) {
    foreach($ph as $k=>$v) {
        $k = sprintf('[+%s+]', $k);
        $tpl = str_replace($k,$v,$tpl);
    }
    return $tpl;
}

function html_tag($tag_name, $attrib=array(), $content=null) {
    return evo()->html_tag($tag_name, $attrib, $content);
}

function input_text_tag($props=array()) {
    $props['type'] = 'text';
    $props['maxlength'] = evo()->array_get($props,'maxlength',255);
    $props['class']     = evo()->array_get($props,'class','inputBox');
    foreach($props as $k=>$v) {
        if($v===false) {
            unset($props[$k]);
        }
    }
    return evo()->html_tag('input', $props);

}

function textarea_tag($props=array(), $content) {
    $props['class'] = evo()->array_get($props,'class','inputBox');
    return evo()->html_tag('textarea', $props, $content);
}

function select_tag($props=array(), $options) {
    $props['class'] = evo()->array_get($props,'class','inputBox');
    if(is_array($options)) {
        $options = implode("\n", $options);
    }
    return evo()->html_tag('select', $props, $options);
}

function img_tag($src,$props=array()) {
    $props['src'] = $src;
    return evo()->html_tag('img', $props);
}

function alert() {
    static $e=null;
    if($e) {
        return $e;
    }
    include_once(MODX_CORE_PATH . 'error.class.inc.php');
    $e = new errorHandler;
    return $e;
}

function array_get($array, $key=null, $default=null) {
    return evo()->array_get($array,$key, $default);
}

function request_intvar($key) {
    if (preg_match('@^[1-9][0-9]*$@', evo()->input_any($key))) {
        return evo()->input_any($key);
    }
    return 0;
}

function event() {
    return evo()->event;
}
