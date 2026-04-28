<?php

class SystemLogViewer
{
    public static function files(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $rootPath = realpath($root);
        if ($rootPath === false) {
            return [];
        }
        $rootPath = str_replace('\\', '/', $rootPath);
        $items = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $name = $file->getFilename();
            if (!preg_match('/^system-[0-9]{4}-[0-9]{2}-[0-9]{2}\.log(\.[0-9]+)?$/', $name)) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $relative = ltrim(substr($path, strlen($rootPath)), '/');
            $year = '';
            $month = '';
            if (preg_match('@^([0-9]{4})/([0-9]{2})/@', $relative, $matches)) {
                $year = $matches[1];
                $month = $matches[2];
            }
            $dateTimestamp = $file->getMTime();
            if (preg_match('/^system-([0-9]{4}-[0-9]{2}-[0-9]{2})\.log/', $name, $matches)) {
                $parsedTimestamp = strtotime($matches[1] . ' 00:00:00');
                if ($parsedTimestamp !== false) {
                    $dateTimestamp = $parsedTimestamp;
                }
            }
            $items[] = [
                'name' => $name,
                'relative' => $relative,
                'year' => $year,
                'month' => $month,
                'size' => self::formatBytes($file->getSize()),
                'lines' => null,
                'path' => $path,
                'date_ts' => $dateTimestamp,
                'mtime' => $file->getMTime(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }

        usort($items, function ($a, $b) {
            return $b['mtime'] <=> $a['mtime'];
        });

        return $items;
    }

    public static function latestFiles(array $files, int $limit = 100): array
    {
        return array_slice($files, 0, max(1, $limit));
    }

    public static function withLineCounts(array $files): array
    {
        foreach ($files as $index => $file) {
            if (isset($file['lines']) && $file['lines'] !== null) {
                continue;
            }
            $path = (string)array_get($file, 'path', '');
            $files[$index]['lines'] = $path !== '' ? self::countLines($path) : 0;
        }

        return $files;
    }

    public static function period(string $period): string
    {
        if (in_array($period, ['latest', 'recent30'], true)) {
            return $period;
        }

        if (preg_match('/^year:[0-9]{4}$/', $period)) {
            return $period;
        }

        return 'latest';
    }

    public static function filterFiles(array $files, string $period, string $month = ''): array
    {
        if (strpos($period, 'year:') === 0) {
            $year = substr($period, 5);
            return array_values(array_filter($files, function ($file) use ($year, $month) {
                return (string)array_get($file, 'year', '') === $year
                    && ($month === '' || (string)array_get($file, 'month', '') === $month);
            }));
        }

        if ($period === 'latest') {
            return $files;
        }

        $days = 30;
        $today = strtotime(date('Y-m-d') . ' 00:00:00');
        if ($today === false) {
            return $files;
        }
        $threshold = strtotime('-' . ($days - 1) . ' days', $today);
        if ($threshold === false) {
            return $files;
        }

        $filtered = array_values(array_filter($files, function ($file) use ($threshold) {
            return (int)array_get($file, 'date_ts', 0) >= $threshold;
        }));

        return $filtered ?: $files;
    }

    public static function months(array $files, string $period): array
    {
        if (strpos($period, 'year:') !== 0) {
            return [];
        }

        $year = substr($period, 5);
        $months = [];
        foreach ($files as $file) {
            if ((string)array_get($file, 'year', '') !== $year) {
                continue;
            }
            $month = (string)array_get($file, 'month', '');
            if ($month !== '') {
                $months[$month] = $month;
            }
        }

        krsort($months);

        return array_values($months);
    }

    public static function selectedMonth(string $month, array $months): string
    {
        if (in_array($month, $months, true)) {
            return $month;
        }

        return $months[0] ?? '';
    }

    public static function years(array $files): array
    {
        $years = [];
        foreach ($files as $file) {
            $year = (string)array_get($file, 'year', '');
            if ($year !== '') {
                $years[$year] = $year;
            }
        }

        krsort($years);

        return array_values($years);
    }

    public static function findFile(array $files, string $relative): array
    {
        foreach ($files as $file) {
            if ($file['relative'] === $relative) {
                return $file;
            }
        }

        return [];
    }

    public static function fileList(array $files): array
    {
        return array_map(function ($file) {
            return [
                'relative' => $file['relative'],
                'label' => self::fileLabel($file),
            ];
        }, $files);
    }

    public static function fileLabel(array $file): string
    {
        return sprintf(
            '%s (%s / %s lines)',
            $file['name'],
            $file['size'],
            $file['lines']
        );
    }

    public static function resolveFile(string $root, string $relative): string
    {
        if ($relative === '' || strpos($relative, "\0") !== false) {
            return '';
        }

        $rootPath = realpath($root);
        $path = realpath($root . $relative);
        if ($rootPath === false || $path === false || !is_file($path)) {
            return '';
        }

        $rootPath = rtrim(str_replace('\\', '/', $rootPath), '/') . '/';
        $path = str_replace('\\', '/', $path);
        if (strpos($path, $rootPath) !== 0) {
            return '';
        }
        if (!preg_match('/^system-[0-9]{4}-[0-9]{2}-[0-9]{2}\.log(\.[0-9]+)?$/', basename($path))) {
            return '';
        }

        return $path;
    }

    public static function readLatestEntries(
        string $root,
        array $files,
        string $level = '',
        string $query = '',
        string $olderCursorFile = '',
        int $olderBeforeLine = 0,
        int $limit = 20
    ): array {
        if (!$files) {
            return ['entries' => [], 'has_more' => false, 'before_line' => 0, 'cursor_file' => ''];
        }

        $startIndex = 0;
        if ($olderCursorFile !== '') {
            $startIndex = self::fileIndex($files, $olderCursorFile);
            if ($startIndex < 0) {
                return ['entries' => [], 'has_more' => false, 'before_line' => 0, 'cursor_file' => ''];
            }
        }

        $chunks = [];
        $remaining = max(1, $limit);
        $nextCursorFile = '';
        $nextBeforeLine = 0;
        $hasMore = false;
        $lastIndex = $startIndex - 1;

        for ($i = $startIndex, $count = count($files); $i < $count && $remaining > 0; $i++) {
            $lastIndex = $i;
            $fileInfo = $files[$i];
            $path = self::resolveFile($root, $fileInfo['relative']);
            if ($path === '') {
                continue;
            }

            $lineCursor = $i === $startIndex ? $olderBeforeLine : 0;
            $result = self::readEntries($path, $level, $query, $lineCursor, $remaining);
            if ($result['entries']) {
                $chunks[] = array_map(function ($entry) use ($fileInfo) {
                    $entry['file'] = $fileInfo['relative'];
                    return $entry;
                }, $result['entries']);
                $remaining -= count($result['entries']);
            }

            if ($result['has_more']) {
                $hasMore = true;
                $nextCursorFile = $fileInfo['relative'];
                $nextBeforeLine = (int)$result['before_line'];
                break;
            }
        }

        if (!$hasMore && $remaining <= 0 && isset($files[$lastIndex + 1])) {
            $hasMore = true;
            $nextCursorFile = $files[$lastIndex + 1]['relative'];
            $nextBeforeLine = 0;
        }

        $entries = [];
        foreach (array_reverse($chunks) as $chunk) {
            $entries = array_merge($entries, $chunk);
        }

        return [
            'entries' => $entries,
            'has_more' => $hasMore,
            'before_line' => $nextBeforeLine,
            'cursor_file' => $nextCursorFile,
        ];
    }

    public static function readEntries(string $path, string $level = '', string $query = '', int $olderBeforeLine = 0, int $limit = 20): array
    {
        $entries = [];
        $lineNumber = 0;
        $firstLine = 0;
        $hasMore = false;

        $file = new SplFileObject($path, 'r');
        while (!$file->eof()) {
            $line = trim((string)$file->fgets());
            $lineNumber++;
            if ($line === '') {
                continue;
            }
            if ($olderBeforeLine > 0 && $lineNumber >= $olderBeforeLine) {
                continue;
            }

            $data = json_decode($line, true);
            if (!is_array($data)) {
                $data = [
                    'timestamp' => '',
                    'level' => 'raw',
                    'message' => $line,
                    'context' => [],
                ];
            }

            $entryLevel = strtolower((string)array_get($data, 'level', ''));
            $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($level !== '' && $entryLevel !== $level) {
                continue;
            }
            if ($query !== '' && stripos($encoded, $query) === false) {
                continue;
            }

            $entries[] = self::normalizeEntry($data, $lineNumber);
            if (count($entries) > $limit) {
                array_shift($entries);
                $hasMore = true;
            }
        }

        if ($entries) {
            $firstLine = (int)$entries[0]['line'];
        }

        return [
            'entries' => $entries,
            'has_more' => $hasMore,
            'before_line' => $firstLine,
        ];
    }

    public static function jsonEncode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json !== false) {
            return $json;
        }

        $fallback = self::utf8Normalize($payload);
        $json = json_encode($fallback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            return $json;
        }

        return json_encode([
            'entries' => [],
            'has_more' => false,
            'before_line' => 0,
            'cursor_file' => '',
            'error' => 'Failed to encode log response: ' . json_last_error_msg(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function fileIndex(array $files, string $relative): int
    {
        foreach ($files as $index => $file) {
            if ($file['relative'] === $relative) {
                return $index;
            }
        }

        return -1;
    }

    private static function normalizeEntry(array $data, int $lineNumber): array
    {
        $context = array_get($data, 'context', []);
        if (!is_array($context)) {
            $context = [];
        }

        return [
            'line' => $lineNumber,
            'timestamp' => (string)array_get($data, 'timestamp', ''),
            'timestamp_label' => self::formatTimestamp((string)array_get($data, 'timestamp', '')),
            'level' => strtolower((string)array_get($data, 'level', 'unknown')) ?: 'unknown',
            'message' => self::plainText((string)array_get($data, 'message', '')),
            'source' => (string)array_get($context, 'source', ''),
            'caller' => is_array(array_get($context, 'caller')) ? array_get($context, 'caller') : [],
            'context' => $context,
            'raw' => $data,
        ];
    }

    private static function formatTimestamp(string $timestamp): string
    {
        if ($timestamp === '') {
            return '';
        }

        $parsed = strtotime($timestamp);
        if ($parsed === false) {
            return $timestamp;
        }

        return date('Y-m-d H:i:s', $parsed);
    }

    private static function utf8Normalize($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::utf8Normalize($item);
            }
            return $value;
        }

        if (is_string($value)) {
            if (function_exists('mb_convert_encoding')) {
                return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
            if (function_exists('iconv')) {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
                if ($converted !== false) {
                    return $converted;
                }
            }
        }

        return $value;
    }

    private static function plainText(string $message): string
    {
        if ($message === '' || strpos($message, '<') === false) {
            return $message;
        }

        $message = preg_replace('@<(br|/p|/div|/tr|/table|/h[1-6])\b[^>]*>@i', "\n", $message);
        $message = strip_tags($message);
        $message = html_entity_decode($message, ENT_QUOTES | ENT_HTML5, config('modx_charset', 'utf-8'));
        $lines = array_map('trim', preg_split('/\R+/u', $message) ?: []);
        $lines = array_filter($lines, function ($line) {
            return $line !== '';
        });

        return implode("\n", $lines);
    }

    private static function countLines(string $path): int
    {
        $lines = 0;
        $file = new SplFileObject($path, 'r');
        while (!$file->eof()) {
            $file->fgets();
            $lines++;
        }

        return max(0, $lines - 1);
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = $bytes;
        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'GB') {
                return sprintf('%s %s', round($value, 1), $unit);
            }
            $value = $value / 1024;
        }

        return $bytes . ' B';
    }
}
