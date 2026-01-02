<?php
declare(strict_types=1);

namespace Evolution\CMS\Cli\Support;

class ConsoleOutput
{
    public function writeln(string $message = ''): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    public function error(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }

    public function json(array $payload): void
    {
        $this->writeln(
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
            ) ?: '{}'
        );
    }
}
