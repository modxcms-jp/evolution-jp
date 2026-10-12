<?php
// index.php から require_once で読み込む。API モードのスクリプトがページ処理中に
// index.php を再度読み込んでも、関数の再宣言や shutdown 関数の二重登録が起きないよう別ファイルにしている。

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

function frontend_read_source_line(string $file, int $line): string
{
    if ($file === '' || $line <= 0 || !is_readable($file)) {
        return '';
    }

    try {
        $sourceFile = new SplFileObject($file, 'r');
        $sourceFile->seek($line - 1);
        return (string)$sourceFile->current();
    } catch (Throwable $e) {
        return '';
    }
}

function frontend_collect_cache_status(): string
{
    global $cache_type;
    if (!$cache_type) {
        return 'disabled';
    }
    if (!evo()) {
        return 'unknown';
    }
    if (evo()->documentGenerated === 1) {
        return 'generated';
    }
    if (evo()->documentGenerated === 0) {
        return 'from_cache';
    }
    return 'unknown'; // prepareResponse() 未到達
}

function frontend_log_uncaught_throwable(Throwable $exception): void
{
    try {
        $logger = new EvoLogger();
        $logger->critical($exception->getMessage(), [
            'source' => 'Frontend request',
            'exception' => [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => frontend_system_log_trace($exception->getTrace()),
            ],
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => memory_get_usage(),
            'memory_peak_usage' => memory_get_peak_usage(),
            'cache_status' => frontend_collect_cache_status(),
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
        $file = (string)($error['file'] ?? '');
        $line = (int)($error['line'] ?? 0);
        $source = frontend_read_source_line($file, $line);

        $logger = new EvoLogger();
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
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => memory_get_usage(),
            'memory_peak_usage' => memory_get_peak_usage(),
            'cache_status' => frontend_collect_cache_status(),
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
