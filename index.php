<?php
if (!isset($_SERVER['REQUEST_TIME_FLOAT'])) {
    $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
}
$mstart = memory_get_usage();
include 'define-path.php';

if (defined('IN_MANAGER_MODE')) {
    return;
}

if ($_GET['get']??'' === 'captcha') {
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
    include_once MODX_CACHE_PATH . 'basicConfig.php';
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
            [$head, $output] = explode('<!--__MODxCacheSpliter__-->', $output, 2);
            if (strpos($head, '"text/html";') === false) {
                $type = unserialize($head);
                header('Content-Type:' . $type . '; charset=utf-8');
            } else header('Content-Type:text/html; charset=utf-8');
            $msize = memory_get_peak_usage() - $mstart;
            $units = ['B', 'KB', 'MB'];
            $pos = 0;
            while ($msize >= 1024) {
                $msize /= 1024;
                $pos++;
            }
            $msize = round($msize, 2) . ' ' . $units[$pos];
            $totalTime = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']);
            $totalTime = sprintf('%2.4f s', $totalTime);
            $incs = get_included_files();
            $r = ['[^q^]' => '0', '[^qt^]' => '0s', '[^p^]' => $totalTime, '[^t^]' => $totalTime, '[^s^]' => 'bypass_cache', '[^m^]' => $msize, '[^f^]' => count($incs)];
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

if (is_file(MODX_BASE_PATH . '.env')) {
    require_once MODX_BASE_PATH . 'manager/includes/dotenv-loader.php';
    $dotenv = new Dotenv(MODX_BASE_PATH . '.env');
    $dotenv->load();
}
// initiate a new document parser
include_once MODX_BASE_PATH . 'manager/includes/document.parser.class.inc.php';
$evo = new DocumentParser;
$modx =& $evo;

$evo->mstart = $mstart;
$evo->cacheRefreshTime = $cacheRefreshTime;
if (isset($error_reporting)) {
    $evo->error_reporting = $error_reporting;
}

if (evo()->isFrontend()) {
    include_once MODX_MANAGER_PATH . 'includes/version_mismatch_guard.php';
    evo_guard_version_mismatch($modx);
}

function frontend_system_log_trace(array $trace): array
{
    $frames = [];
    foreach ($trace as $frame) {
        $frames[] = [
            'file' => $frame['file'] ?? '',
            'line' => (int)($frame['line'] ?? 0),
            'function' => $frame['function'] ?? '',
            'class' => $frame['class'] ?? '',
        ];
    }

    return $frames;
}

function frontend_system_log_error_type(int $type): string
{
    $types = [
        E_ERROR => 'ERROR',
        E_PARSE => 'PARSING ERROR',
        E_CORE_ERROR => 'CORE ERROR',
        E_CORE_WARNING => 'CORE WARNING',
        E_COMPILE_ERROR => 'COMPILE ERROR',
        E_COMPILE_WARNING => 'COMPILE WARNING',
    ];

    return $types[$type] ?? '';
}

function frontend_system_log_context(): array
{
    $context = [];
    if (evo()) {
        $context['document_identifier'] = evo()->documentIdentifier ?? '';
        $context['document_method'] = evo()->documentMethod ?? '';
        if (is_object(evo()->event) && !empty(evo()->event->activePlugin)) {
            $context['active_plugin'] = evo()->event->activePlugin;
        }
        if (!empty(evo()->currentSnippet)) {
            $context['current_snippet'] = evo()->currentSnippet;
        }
    }

    return $context;
}

function frontend_log_uncaught_throwable(Throwable $exception): void
{
    try {
        $logger = new Logger();
        $logger->critical($exception->getMessage(), [
            'source' => 'Frontend request',
            'exception' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => frontend_system_log_trace($exception->getTrace()),
            ],
            'frontend' => frontend_system_log_context(),
        ]);
    } catch (Throwable $loggingException) {
        error_log('Failed to write frontend throwable to system log: ' . $loggingException->getMessage());
    }
}

function frontend_log_shutdown_fatal(): void
{
    $error = error_get_last();
    if (!is_array($error)) {
        return;
    }

    $fatalTypes = [
        E_ERROR,
        E_PARSE,
        E_CORE_ERROR,
        E_COMPILE_ERROR,
    ];
    if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
        return;
    }

    try {
        $source = '';
        $file = (string)($error['file'] ?? '');
        $line = (int)($error['line'] ?? 0);
        if ($file !== '' && $line > 0 && is_readable($file)) {
            $lines = file($file);
            $source = $lines[$line - 1] ?? '';
        }

        $logger = new Logger();
        $logger->critical((string)($error['message'] ?? 'Fatal error'), [
            'source' => 'Frontend shutdown',
            'fatal' => [
                'type' => frontend_system_log_error_type((int)($error['type'] ?? 0)),
                'number' => (int)($error['type'] ?? 0),
                'message' => (string)($error['message'] ?? ''),
                'file' => $file,
                'line' => $line,
                'source' => $source,
            ],
            'frontend' => frontend_system_log_context(),
        ]);
    } catch (Throwable $loggingException) {
        error_log('Failed to write frontend fatal error to system log: ' . $loggingException->getMessage());
    }
}

function frontend_render_uncaught_error(): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header(sprintf('Content-Type: text/html; charset=%s', config('modx_charset', 'utf-8')));
    }

    echo '<!doctype html><html><head><title>Internal Server Error</title></head><body>';
    echo '<h1>Internal Server Error</h1>';
    echo '<p>The site encountered an error. Details were written to the system log.</p>';
    echo '</body></html>';
}

register_shutdown_function('frontend_log_shutdown_fatal');

// execute the parser if index.php was not included
if (defined('IN_PARSER_MODE') && IN_PARSER_MODE === 'true') {
    try {
        $result = $evo->executeParser();
        echo $result;
    } catch (Throwable $exception) {
        frontend_log_uncaught_throwable($exception);
        frontend_render_uncaught_error();
    }
}
