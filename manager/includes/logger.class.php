<?php

class Logger
{
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    private const SYSTEM_TYPE = 'system';
    private const DEFAULT_MAX_BYTES = 104857600;

    private array $levels = [
        self::EMERGENCY,
        self::ALERT,
        self::CRITICAL,
        self::ERROR,
        self::WARNING,
        self::NOTICE,
        self::INFO,
        self::DEBUG,
    ];

    public function emergency($message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $level = $this->normalizeLevel($level);
        $context = array_merge($context, $this->collectContext($level, $context));
        $entry = [
            'timestamp' => date(DATE_ATOM, request_time()),
            'level' => $level,
            'message' => $this->normalizeMessage($message),
            'context' => $this->sanitizeValue($context),
        ];

        $logFile = $this->getLogFile(self::SYSTEM_TYPE);
        if ($logFile === '') {
            return;
        }
        $this->rotateIfNeeded($logFile);
        $this->writeLog($logFile, $entry);
    }

    private function normalizeLevel($level): string
    {
        $level = strtolower((string)$level);
        if (!in_array($level, $this->levels, true)) {
            return self::INFO;
        }
        return $level;
    }

    private function normalizeMessage($message): string
    {
        if (is_scalar($message) || $message === null) {
            return (string)$message;
        }

        $json = json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            return $json;
        }

        return print_r($message, true);
    }

    private function collectContext(string $level, array $givenContext = []): array
    {
        $context = [
            'request' => [
                'url' => serverv('REQUEST_URI', ''),
                'method' => serverv('REQUEST_METHOD', ''),
                'ip' => serverv('REMOTE_ADDR', ''),
            ],
            'user' => $this->getCurrentUserId(),
        ];

        if (!$this->shouldCollectTrace($level)) {
            return $context;
        }

        $trace = $this->filterTrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 16));
        $caller = $this->findCaller($trace, $givenContext);
        $context['caller'] = [
            'file' => $this->toRelativePath($caller['file'] ?? ''),
            'line' => (int)($caller['line'] ?? 0),
        ];
        if (!isset($givenContext['exception']) && !isset($givenContext['fatal'])) {
            $context['trace'] = $trace;
        }

        if (evo()) {
            if (is_object(evo()->event) && !empty(evo()->event->activePlugin)) {
                $context['active_plugin'] = evo()->event->activePlugin;
            }
            if (!empty(evo()->currentSnippet)) {
                $context['current_snippet'] = evo()->currentSnippet;
            }
        }

        return $context;
    }

    private function shouldCollectTrace(string $level): bool
    {
        return in_array($level, [
            self::EMERGENCY,
            self::ALERT,
            self::CRITICAL,
            self::ERROR,
            self::WARNING,
        ], true);
    }

    private function findCaller(array $trace, array $givenContext = []): array
    {
        if (isset($givenContext['error']) && is_array($givenContext['error'])) {
            return [
                'file' => $givenContext['error']['file'] ?? '',
                'line' => (int)($givenContext['error']['line'] ?? 0),
            ];
        }
        if (isset($givenContext['exception']) && is_array($givenContext['exception'])) {
            return [
                'file' => $givenContext['exception']['file'] ?? '',
                'line' => (int)($givenContext['exception']['line'] ?? 0),
            ];
        }
        if (isset($givenContext['fatal']) && is_array($givenContext['fatal'])) {
            return [
                'file' => $givenContext['fatal']['file'] ?? '',
                'line' => (int)($givenContext['fatal']['line'] ?? 0),
            ];
        }

        foreach ($trace as $frame) {
            $file = $frame['file'] ?? '';
            if ($file === '') {
                continue;
            }
            return $frame;
        }

        return [];
    }

    private function filterTrace(array $trace): array
    {
        $frames = [];
        foreach ($trace as $frame) {
            if ($this->isLogInfrastructureFrame($frame)) {
                continue;
            }
            $frames[] = [
                'file' => $this->toRelativePath($frame['file'] ?? ''),
                'line' => (int)($frame['line'] ?? 0),
                'function' => $frame['function'] ?? '',
                'class' => $frame['class'] ?? '',
            ];
        }

        return $frames;
    }

    private function isLogInfrastructureFrame(array $frame): bool
    {
        $file = $this->toRelativePath($frame['file'] ?? '');
        $function = $frame['function'] ?? '';
        $class = $frame['class'] ?? '';

        if ($class === 'Logger' || $file === 'manager/includes/logger.class.php') {
            return true;
        }

        return $class === 'DocumentParser' && in_array($function, [
            'logEvent',
            'messageQuit',
            'phpError',
        ], true);
    }

    private function getCurrentUserId(): int
    {
        if (!evo()) {
            return 0;
        }

        return (int)evo()->getLoginUserID();
    }

    private function getLogFile(string $type): string
    {
        $type = preg_replace('/[^a-z0-9_-]/i', '', $type) ?: self::SYSTEM_TYPE;
        $dir = MODX_BASE_PATH . 'temp/logs/' . $type . '/' . date('Y/m', request_time()) . '/';
        if (!$this->ensureLogDirectory($dir)) {
            return '';
        }

        return $dir . $type . '-' . date('Y-m-d', request_time()) . '.log';
    }

    private function ensureLogDirectory(string $dir): bool
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->reportFailure('Failed to create log directory: ' . $this->toRelativePath($dir));
            return false;
        }

        if (!is_writable($dir)) {
            @chmod($dir, 0775);
        }
        if (!is_writable($dir)) {
            $this->reportFailure('System log directory is not writable: ' . $this->toRelativePath($dir));
            return false;
        }

        $root = MODX_BASE_PATH . 'temp/logs/';
        if (!is_dir($root)) {
            return true;
        }

        $htaccess = $root . '.htaccess';
        if (!is_file($htaccess) && is_writable($root)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n", LOCK_EX);
        }

        return true;
    }

    private function rotateIfNeeded(string $logFile): void
    {
        if (!is_file($logFile) || @filesize($logFile) < $this->getMaxBytes()) {
            return;
        }

        $dir = dirname($logFile);
        if (!is_writable($dir)) {
            $this->reportFailure('System log directory is not writable for rotation: ' . $this->toRelativePath($dir));
            return;
        }

        for ($i = 9; $i >= 1; $i--) {
            $source = "{$logFile}.{$i}";
            $target = $logFile . '.' . ($i + 1);
            if (is_file($source)) {
                @rename($source, $target);
            }
        }

        @rename($logFile, "{$logFile}.1");
    }

    private function getMaxBytes(): int
    {
        $configured = (int)config('system_log_max_bytes', self::DEFAULT_MAX_BYTES);
        return $configured > 0 ? $configured : self::DEFAULT_MAX_BYTES;
    }

    private function writeLog(string $logFile, array $logEntry): void
    {
        $json = json_encode($logEntry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = json_encode([
                'timestamp' => date(DATE_ATOM, request_time()),
                'level' => self::ERROR,
                'message' => 'Failed to encode system log entry.',
                'context' => ['json_error' => json_last_error_msg()],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $jsonLine = $json . "\n";
        $jsonLine = str_replace(MODX_BASE_PATH, '{BASE_PATH}/', $jsonLine);
        $written = @file_put_contents($logFile, $jsonLine, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            $this->reportFailure('Failed to write system log: ' . $this->toRelativePath($logFile));
        }
    }

    private function reportFailure(string $message): void
    {
        error_log($message);
    }

    private function sanitizeValue($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->sanitizeValue($item);
            }
            return $value;
        }

        if (is_string($value)) {
            return $this->toRelativePath($value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return $this->normalizeMessage($value);
    }

    private function toRelativePath($path): string
    {
        if (!is_string($path) || $path === '') {
            return '';
        }

        $normalized = str_replace('\\', '/', $path);
        $base = str_replace('\\', '/', MODX_BASE_PATH);
        if (strpos($normalized, $base) === 0) {
            return ltrim(substr($normalized, strlen($base)), '/');
        }

        return str_replace(MODX_BASE_PATH, '{BASE_PATH}/', $path);
    }
}
