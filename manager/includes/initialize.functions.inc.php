<?php
// start cms session
function startCMSSession()
{
    global $site_sessionname;

    if (session_status() == PHP_SESSION_NONE) {
        $site_sessionname = 'evo' . substr(easy_hash(__FILE__), 0, 7);
        session_name($site_sessionname);
        init::session_set_cookie_params();
        session_start();
    }
    if (sessionv('evo_sid_hash') !== md5(session_id())) {
        session_regenerate_id(true);
        $_SESSION['evo_sid_hash'] = md5(session_id());
    }
    if (sessionv('mgrValidated') || sessionv('webValidated')) {
        init::set_session_create_time();
    }
}

function set_parser_mode()
{
    if (defined('IN_MANAGER_MODE') && IN_MANAGER_MODE == true) {
        return;
    }
    define('IN_PARSER_MODE', 'true');
    define('IN_MANAGER_MODE', 'false');

    if (!defined('MODX_API_MODE')) {
        define('MODX_API_MODE', false);
    }

    if (!session_id()) {
        ini_set('url_rewriter.tags', '');
        ini_set('session.use_trans_sid', 0);
        ini_set('session.use_only_cookies', 1);
    }
    header('Cache-Control: private, must-revalidate');
    if (session_id()) {
        return;
    }
    session_cache_limiter('');
}

class init
{
    public static function init_mgr()
    {
        // send anti caching headers
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        header('Last-Modified: ' . gmdate("D, d M Y H:i:s") . " GMT");
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
    }

    public static function session_set_cookie_params($options = [])
    {
        $options += [
            'lifetime' => 3600 * 24 * 30,
            'path'     => MODX_BASE_URL,
            'domain'   => '',
            'secure'   => init::is_ssl() ? true : false,
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        // Cookieの寿命に合わせてサーバー側のセッション寿命も延ばす。
        // 未指定だとPHP既定(約24分)でGCされ、意図せずログアウトされる
        ini_set('session.gc_maxlifetime', (string)$options['lifetime']);
        session_set_cookie_params($options);
    }

    public static function get_base_path()
    {
        return str_replace(
            ['\\', 'manager/includes/initialize.functions.inc.php'],
            ['/', ''],
            __FILE__
        );
    }

    public static function get_base_url($base_path)
    {
        $SCRIPT_NAME = $_SERVER['SCRIPT_NAME'];
        if (defined('IN_MANAGER_MODE')) {
            if (strpos($SCRIPT_NAME, '/manager/') !== false) {
                return substr($SCRIPT_NAME, 0, strrpos($SCRIPT_NAME, '/manager/') + 1);
            }
            if (strpos($SCRIPT_NAME, '/assets/') !== false) {
                return substr($SCRIPT_NAME, 0, strrpos($SCRIPT_NAME, '/assets/') + 1);
            }
        }

        if (strpos($SCRIPT_NAME, '/install/') !== false) {
            return substr($SCRIPT_NAME, 0, strrpos($SCRIPT_NAME, '/install/') + 1);
        }

        if (strpos($SCRIPT_NAME, '/~') === 0 && substr($SCRIPT_NAME, -9) === 'index.php') {
            $dir = substr($SCRIPT_NAME, 0, strrpos($SCRIPT_NAME, '/'));
            $pos = strrpos($dir, '/', -1);
            if ($pos) {
                return substr($dir, $pos) . '/';
            }
            return $dir . '/';
        }

        $dir = preg_replace(
            '@(.*?)/assets/.*$@', '$1',
            substr($SCRIPT_NAME, 0, strrpos($SCRIPT_NAME, '/') + 1)
        );
        if (strpos($SCRIPT_NAME, '/~') === 0) {
            $dir = '/~' . substr($dir, 1);
        }
        return rtrim($dir, '/') . '/';
    }

    public static function get_host_name()
    {
        $host_name = serverv('server_name', serverv('HTTP_HOST'));
        if (!$host_name) {
            return '';
        }
        $pos = strpos($host_name, ':');
        if ($pos !== false && (serverv('SERVER_PORT') == 80 || static::is_ssl())) {
            return substr($host_name, 0, $pos);
        }
        return $host_name;
    }

    public static function get_site_url($base_url)
    {
        // サイトのホストが設定されていれば、常にそれを使う。無ければ今までどおり
        $host = static::site_host();
        if ($host === '') {
            if (static::is_installing() && static::request_host() !== '') {
                // インストールの前と途中はページのキャッシュが無いので、アクセスされたホスト（ポート込み）を使う
                $host = static::request_host();
            } else {
                $host = static::get_host_name();
            }
        }
        return sprintf(
            '%s%s%s/',
            static::is_ssl() ? 'https://' : 'http://',
            $host,
            rtrim($base_url, '/')
        );
    }

    /**
     * サイトのホスト（ホスト名[:ポート]）。サイトの URL は常にこのホストで作る。
     * .env（環境変数）の SITE_HOST、無ければシステム設定から書き出した basicConfig.php の $site_host（インストール中を除く）。
     * 既定のポート（http は 80、https は 443）は省いた形にそろえる。未設定や形式の誤りは空文字。
     */
    public static function site_host()
    {
        $value = env('SITE_HOST');
        if ((!is_string($value) || trim($value) === '') && !static::is_installing()) {
            // インストール中は、入れ直す前の古いキャッシュが残っていることがあるので読まない
            $value = static::basic_config_site_host();
        }
        return static::normalize_host((string) $value);
    }

    // basicConfig.php の $site_host。入口によっては初期化より後に basicConfig.php を読むため、
    // ファイルの該当行だけを直接読む（include すると、あとの include_once が読み込まなくなる）
    private static function basic_config_site_host()
    {
        if (isset($GLOBALS['site_host']) && is_string($GLOBALS['site_host'])) {
            return $GLOBALS['site_host'];
        }
        if (!defined('MODX_CACHE_PATH') || !is_file(MODX_CACHE_PATH . 'basicConfig.php')) {
            return '';
        }
        $src = (string) file_get_contents(MODX_CACHE_PATH . 'basicConfig.php');
        if (preg_match('/^\$site_host = \'([^\'\\\\]*)\';$/m', $src, $m)) {
            return $m[1];
        }
        return '';
    }

    // インストーラの中か、まだインストールしていない（設定ファイルが無い）
    public static function is_installing()
    {
        return defined('MODX_SETUP_PATH')
            || (defined('MODX_BASE_PATH') && !is_file(MODX_BASE_PATH . 'manager/includes/config.inc.php'));
    }

    // リクエストのホスト（Host ヘッダー）。インストール中の URL と、設定画面の注意にだけ使う
    public static function request_host()
    {
        return static::normalize_host((string) serverv('HTTP_HOST', ''));
    }

    // ホスト名[:ポート] を小文字にし、既定のポートを省く。形式が正しくなければ空文字
    public static function normalize_host($host)
    {
        $host = strtolower(trim((string) $host));
        if (!preg_match('/^(\[[0-9a-f:.]+\]|[a-z0-9]([a-z0-9.-]*[a-z0-9])?)(:(\d{1,5}))?$/', $host, $m)) {
            return '';
        }
        $port = $m[4] ?? '';
        if ($port === '' || $port === (static::is_ssl() ? '443' : '80')) {
            return $m[1];
        }
        return $m[1] . ':' . $port;
    }

    public static function is_ssl()
    {
        global $https_port;

        $https = serverv('HTTPS');
        if ($https !== null && strtolower($https) === 'on') {
            return true;
        }

        if (serverv('SERVER_PORT') == $https_port) {
            return true;
        }

        return false;
    }

    // set the document_root :|
    public static function fix_document_root()
    {
        if (!serverv('PATH_INFO') || serverv('DOCUMENT_ROOT')) {
            return;
        }
        $_SERVER['DOCUMENT_ROOT'] = str_replace(
            $_SERVER['PATH_INFO'],
            '',
            str_replace(
                '\\',
                '/',
                serverv('PATH_TRANSLATED')
            )
        ) . '/';
    }

    public static function fix_script_name()
    {
        $scriptName = (string) serverv('script_name');
        $serverName = (string) serverv('server_name');
        if ($serverName === '' || strpos($scriptName, '/' . $serverName) !== 0) {
            return;
        }
        $_SERVER['SCRIPT_NAME'] = substr($scriptName, strlen($serverName) + 1);
    }

    // check PHP version
    public static function check_phpvar()
    {
        if (version_compare(phpversion(), '7.4.0') >= 0) {
            return;
        }
        echo 'MODX is compatible with PHP 7.4.0 and higher. Please upgrade your PHP installation!';
        exit;
    }

    public static function fix_request_time()
    {
        if (isset($_SERVER['REQUEST_TIME'])) {
            return;
        }
        $_SERVER['REQUEST_TIME'] = time();
    }

    public static function fix_server_addr()
    {
        if (!serverv('SERVER_ADDR') && serverv('LOCAL_ADDR')) {
            $_SERVER['SERVER_ADDR'] = serverv('LOCAL_ADDR');
        }
        if (serverv('HTTP_X_REMOTE_ADDR')) {
            $_SERVER['REMOTE_ADDR'] = serverv('HTTP_X_REMOTE_ADDR');
        }
        if (serverv('REMOTE_ADDR') === '::1') {
            $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        }
    }

    public static function fix_ssl()
    {
        if (serverv('HTTP_X_FORWARDED_PROTO') === 'https') {
            $_SERVER['HTTPS'] = 'on';
            return;
        }
        if (serverv('HTTPS') !== 'on' && static::is_ssl()) {
            $_SERVER['HTTPS'] = 'on';
            return;
        }

        if (isset($_SERVER['HTTP_HTTPS'])) {
            $_SERVER['HTTPS'] = $_SERVER['HTTP_HTTPS'];
        } elseif (isset($_SERVER['HTTP_X_SAKURA_HTTPS'])) {
            $_SERVER['HTTPS'] = $_SERVER['HTTP_X_SAKURA_HTTPS'];
        }
        if (!isset($_SERVER['HTTPS'])) {
            return;
        }
        if ($_SERVER['HTTPS'] == 1) {
            $_SERVER['HTTPS'] = 'on';
        } elseif ($_SERVER['HTTPS'] === 'off') {
            unset($_SERVER['HTTPS']);
        }
    }

    public static function fix_favicon_req()
    {
        if (serverv('REQUEST_URI') !== '/favicon.ico') {
            return;
        }
        header('Content-Type: image/vnd.microsoft.icon');
        header('Content-Length: 0');
        exit;
    }

    public static function set_session_create_time()
    {
        if (sessionv('modx.session.created.time')) {
            return;
        }

        if (sessionv('mgrLastlogin')) {
            $_SESSION['modx.session.created.time'] = sessionv('mgrLastlogin');
        } else {
            $_SESSION['modx.session.created.time'] = request_time();
        }
    }

    public static function cookieExpiration()
    {
        $lifetime = sessionv(
            sprintf(
                'modx.%s.session.cookie.lifetime',
                sessionv('mgrValidated') ? 'mgr' : 'web'
            ),
            0
        );
        if (!preg_match('@^[1-9][0-9]+$@', $lifetime)) {
            return 0;
        }
        return serverv('REQUEST_TIME', 0) + $lifetime;
    }
}
