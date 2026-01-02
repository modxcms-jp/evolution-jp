<?php
declare(strict_types=1);

namespace Evolution\CMS\Cli\Command;

interface CommandInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * @param array<int, string> $arguments
     * @param array<string, bool> $options
     */
    public function execute(array $arguments, array $options): int;
}
