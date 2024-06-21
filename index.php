<?php
if (!isset($_SERVER['REQUEST_TIME_FLOAT'])) {
    $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
}
$mstart = memory_get_usage();

include 'define-path.php';

if (defined('IN_MANAGER_MODE')) {
    return;
}

if (isset($_GET['get']) && $_GET['get'] === 'captcha') {
    include_once MODX_BASE_PATH . 'manager/media/captcha/veriword.php';
    return;
}

if (is_file('vendor/autoload.php')) {
    require 'vendor/autoload.php';
}

$cache_type = 1;
$cacheRefreshTime = 0;
$site_sessionname = '';
$site_status = '1';
if (is_file(MODX_CACHE_PATH . 'basicConfig.php')) {
    include_once(MODX_CACHE_PATH . 'basicConfig.php');
}

if (isset($conditional_get) && $conditional_get == 1) {
    include_once(MODX_BASE_PATH . "manager/includes/conditional_get.inc.php");
} elseif (!defined('MODX_API_MODE')
    && $cache_type == 2
    && $site_status != 0
    && count($_POST) < 1
    && (time() < $cacheRefreshTime || $cacheRefreshTime == 0)) {
    session_name($site_sessionname);
    session_cache_limiter('');
    session_start();
    if (!isset($_SESSION['mgrValidated'])) {
        session_write_close();
        $uri_parent_dir = substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'], '/')) . '/';
        $uri_parent_dir = ltrim($uri_parent_dir, '/');
        $target = MODX_CACHE_PATH . 'pages/' . $uri_parent_dir . hash('crc32b', $_SERVER['REQUEST_URI']) . '.pageCache.php';
        if (is_file($target)) {
            $handle = fopen($target, 'rb');
            $output = fread($handle, filesize($target));
            unset($handle);
            list($head, $output) = explode('<!--__MODxCacheSpliter__-->', $output, 2);
            if (strpos($head, '"text/html";') === false) {
                $type = unserialize($head);
                header('Content-Type:' . $type . '; charset=utf-8');
            } else header('Content-Type:text/html; charset=utf-8');
            $msize = memory_get_peak_usage() - $mstart;
            $units = array('B', 'KB', 'MB');
            $pos = 0;
            while ($msize >= 1024) {
                $msize /= 1024;
                $pos++;
            }
            $msize = round($msize, 2) . ' ' . $units[$pos];
            $totalTime = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
            $totalTime = sprintf('%2.4f s', $totalTime);
            $incs = get_included_files();
            $r = array('[^q^]' => '0', '[^qt^]' => '0s', '[^p^]' => $totalTime, '[^t^]' => $totalTime, '[^s^]' => 'bypass_cache', '[^m^]' => $msize, '[^f^]' => count($incs));
            $output = strtr($output, $r);
            if (is_file(MODX_BASE_PATH . 'autoload.php'))
                $loaded_autoload = include MODX_BASE_PATH . 'autoload.php';
            if ($output !== false) {
                echo $output;
                exit;
            }
        }
    }
}
if (!isset($loaded_autoload) && is_file(MODX_BASE_PATH . 'autoload.php')) {
    include_once MODX_BASE_PATH . 'autoload.php';
}

// initiate a new document parser
include_once(MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php');
$evo = new DocumentParser;
$modx =& $evo;

$evo->mstart = $mstart;
$evo->cacheRefreshTime = $cacheRefreshTime;
if (isset($error_reporting)) {
    $evo->error_reporting = $error_reporting;
}

// execute the parser if index.php was not included
if (defined('IN_PARSER_MODE') && IN_PARSER_MODE === 'true') {
    $result = $evo->executeParser();
    echo $result;
}
