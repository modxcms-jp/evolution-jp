<?php
// start cms session
function startCMSSession() {
    global $site_sessionname;
    $_ = crc32(__FILE__);
    $_ = sprintf('%u', $_);
    $_ = base_convert($_,10,36);
    $site_sessionname = 'evo' . $_;
    session_name($site_sessionname);
    session_set_cookie_params(0,MODX_BASE_URL);
    session_start();
    $cookieExpiration= 0;
    if(isset($_SESSION['mgrValidated']) || isset ($_SESSION['webValidated'])) {
        $context= isset($_SESSION['mgrValidated']) ? 'mgr' : 'web';
        if(isset($_SESSION["modx{$context}.session.cookie.lifetime"]) && is_numeric($_SESSION["modx{$context}.session.cookie.lifetime"]))
            $cookieLifetime= intval($_SESSION["modx{$context}.session.cookie.lifetime"]);
        
        if($cookieLifetime)
            $cookieExpiration= $_SERVER['REQUEST_TIME'] + $cookieLifetime;
        
        if(!isset($_SESSION['modx.session.created.time']))
            $_SESSION['modx.session.created.time'] = $_SERVER['REQUEST_TIME'];
    }
    setcookie(session_name(), session_id(), $cookieExpiration, MODX_BASE_URL);
}

function set_parser_mode() {
    if(defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == true) return;
    define('IN_PARSER_MODE', 'true');
    define('IN_MANAGER_MODE', 'false');
    
    if (!defined('MODX_API_MODE')) define('MODX_API_MODE', false);
    // set some settings, and address some IE issues
    @ini_set('url_rewriter.tags', '');
    @ini_set('session.use_trans_sid', 0);
    @ini_set('session.use_only_cookies',1);
    session_cache_limiter('');
    header('P3P: CP="NOI NID ADMa OUR IND UNI COM NAV"'); // header for weird cookie stuff. Blame IE.
    header('Cache-Control: private, must-revalidate');
}

class MODX_INIT {
    function __construct() {
    }
    
    function init_mgr() {
        // send anti caching headers
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("X-UA-Compatible: IE=edge;FF=3;OtherUA=4");
    }
    
    function get_base_path() {
        $self = 'manager/includes/initialize.functions.inc';
        $base_path = str_replace($self,'',str_replace('\\', '/', __FILE__));
        return $base_path;
    }
    
    function get_base_url($base_path) {
        if(defined('IN_MANAGER_MODE')) {
            if(strpos($_SERVER['REQUEST_URI'],'/manager/')!==false)
                return substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],'/manager/')) . '/';
            elseif(strpos($_SERVER['REQUEST_URI'],'/assets/')!==false)
                return substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],'/assets/')) . '/';
        }
        if(strpos(str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']),$_SERVER['SCRIPT_NAME'])===false) {
            if(strpos(str_replace('\\','/',$_SERVER['SCRIPT_FILENAME']),'/install/index.php')!==false)
                return substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],'/install/')) . '/';
            else {
                echo 'Missing base_url';
                exit;
            }
        }
        $dir = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
        $dir = substr($dir,0,strrpos($dir,'/')) . '/';
        $dir = preg_replace('@(.*?)/assets/.*$@', '$1', $dir);
        if(isset($_SERVER['REQUEST_URI']) && substr($_SERVER['REQUEST_URI'],0,2)==='/~') $dir = '/~' . substr($dir,1);
        $dir = rtrim($dir, '/') . '/';
        return $dir;
    }
    
    function get_host_name() {
        if(!isset($_SERVER['HTTP_HOST'])) return "";
        $host = $_SERVER['HTTP_HOST'];
        $pos = strpos($host,':');
        if($pos!==false && ($_SERVER['SERVER_PORT'] == 80 || $this->is_ssl())) {
            $host= substr($host,0,$pos);
        }
        return $host;
    }
    
    function get_site_url($base_url) {
        $scheme = $this->is_ssl() ? 'https://' : 'http://';
        $host = $this->get_host_name();
        $site_url = "{$scheme}{$host}{$base_url}";
        return rtrim($site_url,'/') . '/';
    }
    
    function is_ssl() {
        global $https_port;
        
        if(!isset($_SERVER['SERVER_PORT'])) return false;
        if((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') || $_SERVER['SERVER_PORT'] == $https_port) {
            return true;
        }
        else return false;
    }
    
    // set the document_root :|
    function fix_document_root() {
        if (isset($_SERVER['PATH_INFO']) && (!isset($_SERVER['DOCUMENT_ROOT']) || empty($_SERVER['DOCUMENT_ROOT']))) {
            $_SERVER['DOCUMENT_ROOT'] = str_replace($_SERVER['PATH_INFO'], '', str_replace('\\', '/', $_SERVER['PATH_TRANSLATED'])).'/';
        }
    }
    
    // check PHP version. MODX Evolution is compatible with php 4 (4.4.2+)
    function check_phpvar() {
        if(version_compare(phpversion(), '5.0.0') < 0) {
            echo 'MODX is compatible with PHP 5.0.0 and higher. Please upgrade your PHP installation!';
            exit;
        }
    }
    
    function fix_magic_quotes() {
        if (version_compare(PHP_VERSION, '5.4') < 0) @set_magic_quotes_runtime(0);
        
        // include_once the magic_quotes_gpc workaround
        if (get_magic_quotes_gpc()) include_once(dirname(__FILE__) . '/quotes_stripper.inc.php');
    }
    
    function fix_request_time() {
        if(!isset($_SERVER['REQUEST_TIME'])) $_SERVER['REQUEST_TIME'] = time();
    }
    
    function fix_server_addr() {
        if(!isset($_SERVER['SERVER_ADDR']) && isset($_SERVER['LOCAL_ADDR']))
            $_SERVER['SERVER_ADDR'] = $_SERVER['LOCAL_ADDR'];
        if(isset($_SERVER['HTTP_X_REMOTE_ADDR']))
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REMOTE_ADDR'];
        if($_SERVER['REMOTE_ADDR']==='::1')
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }
    
    function fix_ssl() {
        if(isset($_SERVER['HTTP_HTTPS']))
            $_SERVER['HTTPS'] = $_SERVER['HTTP_HTTPS'];
        elseif(isset($_SERVER['HTTP_X_SAKURA_HTTPS']))
            $_SERVER['HTTPS'] = $_SERVER['HTTP_X_SAKURA_HTTPS'];
        elseif(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'on' && $this->is_ssl())
            $_SERVER['HTTPS'] = 'on';
        if(isset($_SERVER['HTTPS'])) {
            if($_SERVER['HTTPS']==1) $_SERVER['HTTPS'] = 'on';
            elseif($_SERVER['HTTPS']==='off') unset($_SERVER['HTTPS']);
        }
    }
    
    function fix_favicon_req() {
        // From WordPress wp_favicon_request() load.php
        if(!isset($_SERVER['REQUEST_URI'])) return;
        if($_SERVER['REQUEST_URI']==='/favicon.ico') {
            header('Content-Type: image/vnd.microsoft.icon');
            header('Content-Length: 0');
            exit;
        }
    }
}
