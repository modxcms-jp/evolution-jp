<?php
/**
 * @return DocumentParser
 */
function evo()
{
    global $modx;
    if (!$modx) {
        return null;
    }
    return $modx;
}

/**
 * @return DBAPI
 */
function db()
{
    return evo()->db;
}

/**
 * @return ManagerAPI
 */
function manager()
{
    return evo()->manager;
}

function hasPermission($key = null)
{
    return evo()->hasPermission($key);
}

function config($key, $default = null)
{
    return evo()->config($key, $default);
}

function docid()
{
    if(event()->name === 'OnDocFormSave') {
        return globalv('id');
    }
    return evo()->documentIdentifier;
}

function base_path()
{
    if (defined('MODX_BASE_PATH')) {
        return constant('MODX_BASE_PATH');
    }
    exit('base_path not defined.');
}

function lang($key, $default = null)
{
    global $_lang;
    if (!$_lang) {
        include MODX_CORE_PATH . sprintf(
                'lang/%s.inc.php',
                evo()->config('manager_language', 'english')
            );
    }
    return array_get($_lang, $key, $default ? $default : $key);
}

function style($key)
{
    global $_style;
    return array_get($_style, $key);
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle)
    {
        return strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        return substr_compare($haystack, $needle, -strlen($needle)) === 0;
    }
}

function hsc($string = '', $flags = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, $encode = null, $double_encode = true)
{
    if ($string === null) {
        return '';
    }

    if (is_object($string)) {
        return $string;
    }

    if (is_array($string)) {
        foreach ($string as $i => $v) {
            $string[$i] = hsc($v, $flags, $encode, $double_encode);
        }

        return $string;
    }

    if ($encode === null) {
        $encode = 'utf-8';
    }

    if (!is_string($string)) {
        if (is_bool($string)) {
            $string = $string ? '1' : '';
        } else {
            $string = (string) $string;
        }
    }

    return htmlspecialchars($string, $flags, $encode, $double_encode);
}

function parseText($tpl, $ph, $left = '[+', $right = '+]', $execModifier = false)
{
    if (evo()) {
        return evo()->parseText($tpl, $ph, $left, $right, $execModifier);
    }
    foreach ($ph as $k => $v) {
        $k = sprintf('[+%s+]', $k);
        $tpl = str_replace($k, $v, $tpl);
    }
    return $tpl;
}

function html_tag($tag_name, $attrib = [], $content = null)
{
    return evo()->html_tag($tag_name, $attrib, $content);
}

function input_text_tag($props = [])
{
    $props['type'] = 'text';
    $props['maxlength'] = array_get($props, 'maxlength', 255);
    $props['class'] = array_get($props, 'class', 'inputBox');
    foreach ($props as $k => $v) {
        if ($v === false) {
            unset($props[$k]);
        }
    }
    return evo()->html_tag('input', $props);
}

function textarea_tag($props = [], $content='')
{
    $props['class'] = array_get($props, 'class', 'inputBox');
    return evo()->html_tag('textarea', $props, $content);
}

function select_tag($props = [], $options='')
{
    $props['class'] = array_get($props, 'class', 'inputBox');
    if (is_array($options)) {
        $options = implode("\n", $options);
    }
    return evo()->html_tag('select', $props, $options);
}

function img_tag($src, $props = [])
{
    $props['src'] = $src;
    return evo()->html_tag('img', $props);
}

function alert()
{
    static $e = null;
    if ($e) {
        return $e;
    }
    include_once(__DIR__ . '/error.class.inc.php');
    $e = new errorHandler;
    return $e;
}

function array_get($array, $key = null, $default = null, $validate = null)
{
    if ($key === null || trim($key) == '') {
        return $array;
    }

    if (isset($array[$key])) {
        if ($validate && is_callable($validate) && !$validate($array[$key])) {
            return $default;
        }
        return $array[$key];
    }
    $segments = explode('.', $key);
    foreach ($segments as $segment) {
        if (!is_array($array) || !isset($array[$segment])) {
            return $default;
        }
        $array = $array[$segment];
    }
    if ($validate && is_callable($validate) && !$validate($array)) {
        return $default;
    }
    return $array;
}

function array_set(&$array, $key, $value)
{
    if (is_null($key)) {
        return $array = $value;
    }

    $keys = explode('.', $key);
    foreach ($keys as $i => $key) {
        if (count($keys) === 1) {
            break;
        }
        unset($keys[$i]);
        if (!isset($array[$key]) || !is_array($array[$key])) {
            $array[$key] = [];
        }

        $array = &$array[$key];
    }

    $array[array_shift($keys)] = $value;

    return $array;
}

function request_intvar($key)
{
    if (preg_match('@^[1-9][0-9]*$@', anyv($key, 0))) {
        return anyv($key);
    }
    return 0;
}

/**
 * @return SystemEvent
 */
function event()
{
    return evo()->event;
}

function parent($docid)
{
    if (evo()) {
        return evo()->getParentID($docid ? $docid : docid());
    }
}

function uparent($docid = null, $top = 0)
{
    if (evo()) {
        return evo()->getUltimateParentId($docid ? $docid : docid(), $top);
    }
}

function exprintf()
{
    $args = func_get_args();
    $args[0] = str_replace('@{%([0-9]+)}@', '%$1s', $args[0]);
    return call_user_func_array(
        'sprintf',
        $args
    );
}

function getv($key = null, $default = null)
{
    $request = $_GET;
    if(isset($request[$key]) && $request[$key]==='') {
        unset($request[$key]);
    }
    return array_get($request, $key, $default);
}

function postv($key = null, $default = null)
{
    return array_get($_POST, $key, $default);
}

function anyv($key = null, $default = null)
{
    return array_get($_REQUEST, $key, $default);
}

function serverv($key = null, $default = null)
{
    return array_get($_SERVER, strtoupper($key), $default);
}

/**
 * Check if the current request is a POST request
 *
 * @return bool
 */
function is_post()
{
    return serverv('REQUEST_METHOD') === 'POST';
}

/**
 * Check if the current request is a GET request
 *
 * @return bool
 */
function is_get()
{
    return serverv('REQUEST_METHOD') === 'GET';
}

function sessionv($key = null, $default = null)
{
    if (strpos($key, '*') === 0) {
        return array_set($_SESSION, ltrim($key, '*'), $default);
    }
    return array_get($_SESSION, $key, $default);
}

function globalv($key = null, $default = null)
{
    if (strpos($key, '*') === 0) {
        return array_set($GLOBALS, ltrim($key, '*'), $default);
    }
    return array_get($GLOBALS, $key, $default);
}

function cookiev($key = null, $default = null)
{
    return array_get($_COOKIE, $key, $default);
}

function filev($key = null, $default = null)
{
    return array_get($_FILES, $key, $default);
}

function pr($content)
{
    if (is_array($content)) {
        echo '<pre>' . print_r(array_map('hsc', $content), true) . '</pre>';
        return;
    }
    echo '<pre>' . hsc($content) . '</pre>';
}

function doc($key, $default = '')
{
    global $modx, $docObject;
    $doc = [];
    if (isset($docObject)) {
        $doc = &$docObject;
    } elseif (!empty($modx->documentObject)) {
        $doc = &$modx->documentObject;
    } elseif (!empty(evo()->documentIdentifier)) {
        $docObject = evo()->getDocumentObject('id', evo()->documentIdentifier);
        $doc = &$docObject;
    }
    if (empty(evo()->documentIdentifier)) {
        if (!empty($doc['id'])) {
            $modx->documentIdentifier = $doc['id'];
        } else {
            global $id;
            if (!empty($id)) {
                $modx->documentIdentifier = $id;
            }
        }
    }
    if (strpos($key, '*') === 0) {
        $value = $default;
        $doc[substr($key, 1)] = $value;
        return $value;
    }
    if (str_contains($key, '@parent')) {
        $a = evo()->getDocumentObject('id', doc('parent'));
        $key = str_replace('@parent', '', $key);
    } elseif (str_contains($key, '@up')) {
        $a = evo()->getDocumentObject('id', uparent());
        $key = str_replace('@up', '', $key);
    } elseif (str_contains($key, '@inherit')) {
        $key = str_replace('@inherit', '', $key);
        $a = evo()->getDocumentObject(
            'id',
            evo()->inheritDocId($key, docid())
        );
    } elseif (evo()->isFrontEnd()) {
        $a = evo()->documentObject;
    } else {
        $a = $doc;
    }
    if (strpos($key, ':') !== false) {
        // modifierを設定
        $modifiers = explode(':', $key);
        $key = array_shift($modifiers);
        $value = evo()->applyFilter(
            array_get($a, $key, $default),
            $key,
            implode(':', $modifiers)
        );
    }
    // $keyが「|」で区切られている場合は値が有効なキーを探す
    if (strpos($key, '|') !== false) {
        $keys = explode('|', $key);
        foreach ($keys as $key) {
            if (array_get($a, $key)) {
                return array_get($a, $key, $default);
            }
            if (array_get($a, $key . '.value')) {
                return array_get($a, $key . '.value', $default);
            }
        }
    }
    return isset($a[$key]) && is_array($a[$key])
        ? array_get($a, $key . '.value', $default)
        : array_get($a, $key, $default)
    ;
}

function ob_get_include($path)
{
    if (!is_file($path)) {
        return false;
    }
    ob_start();
    $return = include $path;
    return ob_get_clean() ?: $return;
}

function easy_hash($seed)
{
    return strtr(rtrim(base64_encode(pack('H*', hash('adler32', $seed))), '='), '+/', '-_');
}

function device()
{
    if (!serverv('http_user_agent')) {
        return 'pc';
    }

    $ua = strtolower(serverv('http_user_agent'));

    if (strpos($ua, 'ipad') !== false) {
        return 'tablet';
    }
    if (strpos($ua, 'iphone') !== false || strpos($ua, 'ipod') !== false) {
        return 'smartphone';
    }

    if (strpos($ua, 'android') === false) {
        if (strpos($ua, 'windows phone') !== false) {
            return 'smartphone';
        }
        if (strpos($ua, 'docomo') !== false || strpos($ua, 'softbank') !== false) {
            return 'mobile';
        }
        if (strpos($ua, 'up.browser') !== false) {
            return 'mobile';
        }
        if (strpos($ua, 'bot') !== false || strpos($ua, 'spider') !== false) {
            return 'bot';
        }
        return 'pc';
    }

    if (strpos($ua, 'mobile') !== false) {
        return 'smartphone';
    }

    return 'tablet';
}

function datetime_format($format, $timestamp = '', $default = '')
{
    if (!$timestamp || strpos($timestamp, '0000-00-00') === 0) {
        return $default;
    }
    if (!preg_match('@^[0-9]+$@', $timestamp)) {
        $timestamp = strtotime(
            preg_replace('@^([0-9]+)/([0-9]+)/([0-9]+)@', '$1-$2-$3', $timestamp)
        );
    }
    if (strpos($format, '%') === false) {
        return date($format, $timestamp);
    }

    return evo()->mb_strftime($format, $timestamp);
}

function request_uri()
{
    return serverv('request_uri');
}

function request_time()
{
    static $request_time = null;

    if ($request_time) {
        return $request_time;
    }

    $request_time = serverv('request_time') ?: time();

    return $request_time;
}

function real_ip()
{
    return serverv(
        'http_client_ip',
        serverv(
            'http_x_forwarded_for',
            serverv(
                'remote_addr',
                'UNKNOWN'
            )
        )
    );
}

function user_agent()
{
    return serverv('http_user_agent', '');
}

function remove_tags($value, $params = '')
{
    if (stripos($params, 'style') === false && stripos($value, '</style>') !== false) {
        $value = preg_replace('#<style.*?>.*?</style>#is', '', $value);
    }
    if (stripos($params, 'script') === false && stripos($value, '</script>') !== false) {
        $value = preg_replace('@<script.*?>.*?</script>@is', '', $value);
    }
    if (strpos($params, '[[') === false && strpos($value, ']]') !== false) {
        $value = preg_replace('@\[\[.+?\]\]@s', '', $value);
    }
    return strip_tags($value, $params);
}

function manager_style_image_path($subdir = '')
{
    $theme = config('manager_theme', '');
    $subdir = trim((string)$subdir, '/');
    $segments = $subdir !== '' ? '/' . $subdir : '';
    $relative = sprintf('media/style/%s/images%s', $theme, $segments);
    $absolute = MODX_MANAGER_PATH . $relative;

    if ($theme && is_dir($absolute)) {
        return rtrim($relative, '/') . '/';
    }

    $relative = 'media/style/common/images' . $segments;
    return rtrim($relative, '/') . '/';
}

function manager_style_image_url($subdir = '')
{
    return MODX_MANAGER_URL . manager_style_image_path($subdir);
}

function manager_style_placeholders()
{
    static $paths = null;
    if ($paths !== null) {
        return $paths;
    }

    $paths = [
        'style_images_path' => manager_style_image_path(),
        'style_icons_path' => manager_style_image_path('icons'),
        'style_misc_path' => manager_style_image_path('misc'),
        'style_tree_path' => manager_style_image_path('tree'),
    ];

    return $paths;
}

function set_manager_style_placeholders()
{
    foreach (manager_style_placeholders() as $key => $value) {
        evo()->setPlaceholder($key, $value);
    }
}

if (!function_exists('env')) {
    /**
     * 環境変数を取得するヘルパー関数
     *
     * @param string $key 環境変数のキー
     * @param mixed $default デフォルト値（環境変数が存在しない場合）
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        // "true", "false", "null" 文字列を適切な型に変換
        switch (strtolower($value)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
        }

        return $value;
    }
}
