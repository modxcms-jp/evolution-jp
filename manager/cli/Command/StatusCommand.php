<?php
declare(strict_types=1);

namespace Evolution\CMS\Cli\Command;

use Evolution\CMS\Cli\Service\SystemStatusService;
use Evolution\CMS\Cli\Support\ConsoleOutput;
use Evolution\CMS\Cli\Support\ExitCode;

class StatusCommand implements CommandInterface
{
    private SystemStatusService $service;
    private ConsoleOutput $output;

    public function __construct(SystemStatusService $service, ConsoleOutput $output)
    {
        $this->service = $service;
        $this->output = $output;
    }

    public function name(): string
    {
        return 'status';
    }

    public function description(): string
    {
        return 'CMSの状態を表示する';
    }

    /**
     * @param array<int, string> $arguments
     * @param array<string, bool> $options
     */
    public function execute(array $arguments, array $options): int
    {
        $status = $this->service->getStatus();

        if (isset($options['json'])) {
            $this->output->json($status);
            return ExitCode::SUCCESS;
        }

        $this->renderHumanReadable($status);
        return ExitCode::SUCCESS;
    }

    /**
     * @param array<string, mixed> $status
     */
    private function renderHumanReadable(array $status): void
    {
        $this->output->writeln('システムステータス');
        $this->output->writeln(sprintf(
            '- CMS: %s (%s / %s)',
            $status['cms']['full_name'] ?: $status['cms']['version'],
            $status['cms']['branch'],
            $status['cms']['release_date']
        ));
        $this->output->writeln(sprintf('- PHP: %s', $status['php']['version']));
        $this->output->writeln(sprintf(
            '- データベース: %s%s',
            $status['database']['version'] ?: '不明',
            $status['database']['connected'] ? '' : ' (未接続)'
        ));
        $this->output->writeln(sprintf(
            '- キャッシュ: %s (書き込み%s)',
            $status['cache']['path'],
            $status['cache']['writable'] ? '可能' : '不可'
        ));
        $this->output->writeln(sprintf(
            '  ファイル数: %d / 合計サイズ: %s',
            $status['cache']['files'],
            $this->humanReadableSize((int) $status['cache']['total_size'])
        ));
    }

    private function humanReadableSize(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $position = (int) floor(log($bytes, 1024));
        $position = min($position, count($units) - 1);

        $value = $bytes / pow(1024, $position);
        return sprintf('%.1f %s', $value, $units[$position]);
    }
}
