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
        // サイトのURLが設定されていれば、そのホスト（ポート込み）を使う。https と書かれていれば https にする
        $fixed = static::fixed_site_url();
        if ($fixed) {
            $https = $fixed['https'] || static::is_ssl();
            $host = $fixed['host'];
        } else {
            $https = static::is_ssl();
            if (static::is_installing() && static::request_host() !== '') {
                // インストールの前と途中はページのキャッシュが無いので、アクセスされたホスト（ポート込み）を使う
                $host = static::request_host();
            } else {
                $host = static::get_host_name();
            }
        }
        return sprintf(
            '%s%s%s/',
            $https ? 'https://' : 'http://',
            $host,
            rtrim($base_url, '/')
        );
    }

    /**
     * 設定されたサイトのURL（.env の SITE_URL、無ければシステム設定から書き出した basicConfig.php の $fixed_site_url）。
     * インストール中は basicConfig.php を読まない（入れ直す前の古いキャッシュが残っていることがあるため）。
     * 未設定や形式の誤りは null。返すのは ['https' => bool, 'host' => 'ホスト名[:ポート]']。パスは使わない
     */
    public static function fixed_site_url()
    {
        $url = static::env_site_url();
        if (!$url && !static::is_installing()) {
            $url = static::parse_site_url(static::basic_config_fixed_site_url());
        }
        return $url;
    }

    // .env（環境変数）の SITE_URL。未設定や形式の誤りは null（システム設定の値を使う）
    public static function env_site_url()
    {
        $value = env('SITE_URL');
        return is_string($value) ? static::parse_site_url($value) : null;
    }

    // basicConfig.php の $fixed_site_url。入口によっては初期化より後に basicConfig.php を読むため、
    // ファイルの該当行だけを直接読む（include すると、あとの include_once が読み込まなくなる）
    private static function basic_config_fixed_site_url()
    {
        if (isset($GLOBALS['fixed_site_url']) && is_string($GLOBALS['fixed_site_url'])) {
            return $GLOBALS['fixed_site_url'];
        }
        if (!defined('MODX_CACHE_PATH') || !is_file(MODX_CACHE_PATH . 'basicConfig.php')) {
            return '';
        }
        $src = (string) file_get_contents(MODX_CACHE_PATH . 'basicConfig.php');
        if (preg_match('/^\$fixed_site_url = \'([^\'\\\\]*)\';$/m', $src, $m)) {
            return $m[1];
        }
        return '';
    }

    /**
     * サイトのURL（http://ホスト名[:ポート]/パス）を読み取る。スキームを省いたものは http とみなす。
     * ホストは小文字にし、スキームの既定のポート（http は 80、https は 443）は省く。形式が正しくなければ null
     */
    public static function parse_site_url($value)
    {
        $value = trim((string) $value);
        if (!preg_match('@^(?:(https?)://)?([^/?#]+)(?:[/?#].*)?$@i', $value, $m)) {
            return null;
        }
        $https = strtolower($m[1]) === 'https';
        $host = static::normalize_host($m[2], $https);
        if ($host === '') {
            return null;
        }
        return ['https' => $https, 'host' => $host];
    }

    // 保存する形にそろえる（例：https://example.com/）。パスはサイトの設置場所（MODX_BASE_URL）に合わせる。形式が正しくなければ空文字
    public static function normalize_site_url($value, $base_url = '/')
    {
        $url = static::parse_site_url($value);
        if (!$url) {
            return '';
        }
        return sprintf('%s://%s%s/', $url['https'] ? 'https' : 'http', $url['host'], rtrim($base_url, '/'));
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

    // ホスト名[:ポート] を小文字にし、既定のポートを省く（$https を省くと今のリクエストに合わせる）。形式が正しくなければ空文字
    public static function normalize_host($host, $https = null)
    {
        $host = strtolower(trim((string) $host));
        if (!preg_match('/^(\[[^\]]+\]|[^:\[\]]+)(?::(\d{1,5}))?$/', $host, $m)) {
            return '';
        }
        $name = $m[1];
        $port = $m[2] ?? '';
        if (!static::is_valid_host_name($name)) {
            return '';
        }
        if ($port !== '' && ((int) $port < 1 || (int) $port > 65535)) {
            return '';
        }
        if ($https === null) {
            $https = static::is_ssl();
        }
        if ($port === '' || (int) $port === ($https ? 443 : 80)) {
            return $name;
        }
        return $name . ':' . (int) $port;
    }

    // ホスト名（DNS 名・IPv4）か、角かっこで囲んだ IPv6 アドレスか
    private static function is_valid_host_name($name)
    {
        if ($name[0] === '[') {
            return filter_var(substr($name, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        }
        if (strlen($name) > 253) {
            return false;
        }
        foreach (explode('.', $name) as $label) {
            if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $label)) {
                return false;
            }
        }
        return true;
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
