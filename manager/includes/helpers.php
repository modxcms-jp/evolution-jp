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
    return evo()->config($key, $default);
}

function lang($key) {
    global $_lang;
    if(!$_lang) {
        include MODX_CORE_PATH . sprintf(
                'lang/%s.inc.php'
                , evo()->config('manager_language', 'english')
            );
    }
    return evo()->array_get($_lang, $key, $key);
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
    include_once(__DIR__ . '/error.class.inc.php');
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

function post($key=null, $default=null) {
    return evo()->input_post($key, $default);
}

function parent($id) {
    static $cache = null;
    if(isset($cache[$id])) {
        return $cache[$id];
    }
    echo $id;
    $cache[$id] = db()->getValue(
        db()->select(
            'parent'
            , '[+prefix+]site_content'
            , sprintf("id='%s'", $id))
    );
    return $cache[$id];
}

function str_format() {
    $args = func_get_args();
    $args[0] = str_replace('@{%([0-9]+)}@','%$1s',$args[0]);
    return call_user_func_array(
        'sprintf'
        , $args
    );
}

function getv($key=null,$default=null) {
    return evo()->input_get($key,$default);
}

function postv($key=null,$default=null) {
    return evo()->input_post($key,$default);
}

function anyv($key=null,$default=null) {
    return evo()->input_any($key,$default);
}

function serverv($key=null,$default=null) {
    return evo()->server($key,$default);
}

function sessionv($key=null,$default=null) {
    return evo()->session($key,$default);
}

function checked($cond) {
    if($cond) {
        return 'checked';
    }
    return '';
}

function selected($cond) {
    if($cond) {
        return 'selected';
    }
    return '';
}