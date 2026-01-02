<?php
declare(strict_types=1);

namespace Evolution\CMS\Cli\Infrastructure;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CacheStore
{
    private string $cachePath;

    public function __construct(string $cachePath)
    {
        $this->cachePath = rtrim($cachePath, '/') . '/';
    }

    /**
     * @return array<string, mixed>
     */
    public function summarize(): array
    {
        $this->ensureCacheDirectory();

        if (!is_dir($this->cachePath)) {
            return [
                'path' => $this->cachePath,
                'writable' => false,
                'files' => 0,
                'total_size' => 0,
            ];
        }

        [$files, $size] = $this->countFilesAndSize();

        return [
            'path' => $this->cachePath,
            'writable' => is_writable($this->cachePath),
            'files' => $files,
            'total_size' => $size,
        ];
    }

    private function ensureCacheDirectory(): void
    {
        if (is_dir($this->cachePath)) {
            return;
        }
        mkdir($this->cachePath, 0777, true);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function countFilesAndSize(): array
    {
        $count = 0;
        $bytes = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $this->cachePath,
                    FilesystemIterator::SKIP_DOTS
                )
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $count++;
                    $bytes += $file->getSize();
                }
            }
        } catch (\Throwable $throwable) {
            return [0, 0];
        }

        return [$count, $bytes];
    }
}
