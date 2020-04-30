<?php
// start cms session
function startCMSSession() {
    global $site_sessionname;
    
    $site_sessionname = 'evo' . base_convert(hash('crc32b', __FILE__), 16, 36);
    session_name($site_sessionname);
    session_set_cookie_params(0,MODX_BASE_URL);
    session_start();

    if(init::session('evo_sid_hash') !== md5(session_id())) {
        session_regenerate_id(true);
        $_SESSION['evo_sid_hash'] = md5(session_id());
    }

    if (isset($_SESSION['mgrValidated']) || isset($_SESSION['webValidated'])) {
        setcookie(
            $site_sessionname
            , session_id()
            , init::cookieExpiration()
            , MODX_BASE_URL
            , ''
            , false
            , true
        );
        init::set_session_create_time();
        return;
    }
    setcookie(
        $site_sessionname
        , session_id()
        , 0
        , MODX_BASE_URL
        , ''
        , false
        , true
    );
}

function set_parser_mode() {
    if(defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == true) {
        return;
    }
    define('IN_PARSER_MODE', 'true');
    define('IN_MANAGER_MODE', 'false');
    
    if (!defined('MODX_API_MODE')) {
        define('MODX_API_MODE', false);
    }

    // set some settings, and address some IE issues
    @ini_set('url_rewriter.tags', '');
    @ini_set('session.use_trans_sid', 0);
    @ini_set('session.use_only_cookies',1);
    header('P3P: CP="NOI NID ADMa OUR IND UNI COM NAV"'); // header for weird cookie stuff. Blame IE.
    header('Cache-Control: private, must-revalidate');
    if(session_id()) {
        return;
    }
    session_cache_limiter('');
}

class init {
    public static function init_mgr() {
        // send anti caching headers
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate("D, d M Y H:i:s") . " GMT");
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('X-UA-Compatible: IE=edge;FF=3;OtherUA=4');
    }
    
    public static function get_base_path() {
        $self = 'manager/includes/initialize.functions.inc';
        return str_replace(array('\\', $self), array('/', ''), __FILE__);
    }
    
    public static function get_base_url($base_path) {
        $SCRIPT_NAME = $_SERVER['SCRIPT_NAME'];
        if(defined('IN_MANAGER_MODE')) {
            if(strpos($SCRIPT_NAME,'/manager/')!==false) {
                return substr($SCRIPT_NAME, 0, strrpos($SCRIPT_NAME, '/manager/') + 1);
            }
            elseif(strpos($SCRIPT_NAME,'/assets/')!==false)
                return substr($SCRIPT_NAME,0,strrpos($SCRIPT_NAME,'/assets/')+1);
        }
        
        if(strpos($SCRIPT_NAME,'/install/')!==false) {
            return substr($SCRIPT_NAME, 0, strrpos($SCRIPT_NAME, '/install/') + 1);
        }
        
        if(strpos($SCRIPT_NAME, '/~') === 0 && substr($SCRIPT_NAME,-9)==='index.php') {
            $dir = substr($SCRIPT_NAME,0,strrpos($SCRIPT_NAME,'/'));
            $pos = strrpos($dir,'/',-1);
            if($pos) {
                $dir = substr($dir, $pos);
            }
            return $dir.'/';
        }

        $dir = substr($SCRIPT_NAME,0,strrpos($SCRIPT_NAME,'/')+1);
        $dir = preg_replace('@(.*?)/assets/.*$@', '$1', $dir);
        if(strpos($SCRIPT_NAME, '/~') === 0) {
            $dir = '/~' . substr($dir, 1);
        }
        $dir = rtrim($dir, '/') . '/';
        return $dir;
    }
    
    public static function get_host_name() {
        if(!isset($_SERVER['HTTP_HOST'])) {
            return '';
        }
        $host = $_SERVER['HTTP_HOST'];
        $pos = strpos($host,':');
        if($pos!==false && ($_SERVER['SERVER_PORT'] == 80 || static::is_ssl())) {
            $host= substr($host,0,$pos);
        }
        return $host;
    }
    
    public static function get_site_url($base_url) {
        $scheme = static::is_ssl() ? 'https://' : 'http://';
        $host = static::get_host_name();
        $site_url = "{$scheme}{$host}{$base_url}";
        return rtrim($site_url,'/') . '/';
    }
    
    public static function is_ssl() {
        global $https_port;
        
        if(!isset($_SERVER['SERVER_PORT'])) {
            return false;
        }

        if((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') || $_SERVER['SERVER_PORT'] == $https_port) {
            return true;
        }

        return false;
    }
    
    // set the document_root :|
    public static function fix_document_root() {
        if (
            isset($_SERVER['PATH_INFO'])
            &&
            (!isset($_SERVER['DOCUMENT_ROOT']) || !$_SERVER['DOCUMENT_ROOT'])
        ) {
            $_SERVER['DOCUMENT_ROOT'] = str_replace(
            $_SERVER['PATH_INFO']
            , ''
            , str_replace(
                '\\'
                , '/'
                , $_SERVER['PATH_TRANSLATED'])
            ).'/';
        }
    }
    
    // check PHP version. MODX Evolution is compatible with php 4 (4.4.2+)
    public static function check_phpvar() {
        if(version_compare(phpversion(), '5.3.0') < 0) {
            echo 'MODX is compatible with PHP 5.3.0 and higher. Please upgrade your PHP installation!';
            exit;
        }
    }
    
    public static function fix_magic_quotes() {
        if (version_compare(PHP_VERSION, '5.4') < 0) @set_magic_quotes_runtime(0);
        
        // include_once the magic_quotes_gpc workaround
        if (get_magic_quotes_gpc()) include_once __DIR__ . '/quotes_stripper.inc.php';
    }
    
    public static function fix_request_time() {
        if(!isset($_SERVER['REQUEST_TIME'])) {
            $_SERVER['REQUEST_TIME'] = time();
        }
    }
    
    public static function fix_server_addr() {
        if(!isset($_SERVER['SERVER_ADDR']) && isset($_SERVER['LOCAL_ADDR'])) {
            $_SERVER['SERVER_ADDR'] = $_SERVER['LOCAL_ADDR'];
        }
        if(isset($_SERVER['HTTP_X_REMOTE_ADDR'])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REMOTE_ADDR'];
        }
        if($_SERVER['REMOTE_ADDR']==='::1') {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }
    }
    
    public static function fix_ssl() {
        if(isset($_SERVER['HTTP_HTTPS'])) {
            $_SERVER['HTTPS'] = $_SERVER['HTTP_HTTPS'];
        } elseif(isset($_SERVER['HTTP_X_SAKURA_HTTPS'])) {
            $_SERVER['HTTPS'] = $_SERVER['HTTP_X_SAKURA_HTTPS'];
        } elseif(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'on' && static::is_ssl()) {
            $_SERVER['HTTPS'] = 'on';
        }
        if(isset($_SERVER['HTTPS'])) {
            if($_SERVER['HTTPS']==1) {
                $_SERVER['HTTPS'] = 'on';
            } elseif($_SERVER['HTTPS']==='off') {
                unset($_SERVER['HTTPS']);
            }
        }
    }
    
    public static function fix_favicon_req() {
        // From WordPress wp_favicon_request() load.php
        if(!isset($_SERVER['REQUEST_URI'])) {
            return;
        }
        if($_SERVER['REQUEST_URI']==='/favicon.ico') {
            header('Content-Type: image/vnd.microsoft.icon');
            header('Content-Length: 0');
            exit;
        }
    }
    public static function session($key,$default=null) {
        if(!isset($_SESSION[$key])) {
            return $default;
        }
        return $_SESSION[$key];
    }
    public static function real_ip() {
        if(isset($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        if(isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return 'UNKNOWN';
    }

    public static function set_session_create_time() {
        if(isset($_SESSION['modx.session.created.time'])) {
            return;
        }

        if(isset($_SESSION['mgrLastlogin'])) {
            $_SESSION['modx.session.created.time'] = $_SESSION['mgrLastlogin'];
        } else {
            $_SESSION['modx.session.created.time'] = $_SERVER['REQUEST_TIME'];
        }
    }
    
    public static function cookieExpiration() {
        if (isset($_SESSION['mgrValidated'])) {
            $key = 'modx.mgr.session.cookie.lifetime';
        } else {
            $key = 'modx.web.session.cookie.lifetime';
        }
        $lifetime = init::session($key, 0);
        if(!preg_match('@^[1-9][0-9]+$@',$lifetime)) {
            return 0;
        }
        return $_SERVER['REQUEST_TIME'] + $lifetime;
    }
}
