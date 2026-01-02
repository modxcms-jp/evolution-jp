<?php
declare(strict_types=1);

namespace Evolution\CMS\Cli;

use Evolution\CMS\Cli\Command\CommandInterface;
use Evolution\CMS\Cli\Support\ConsoleOutput;
use Evolution\CMS\Cli\Support\ExitCode;

class Application
{
    /**
     * @var array<string, CommandInterface>
     */
    private $commands = [];

    private ConsoleOutput $output;

    /**
     * @param CommandInterface[] $commands
     */
    public function __construct(ConsoleOutput $output, array $commands)
    {
        $this->output = $output;
        foreach ($commands as $command) {
            $this->register($command);
        }
    }

    public function run(array $argv): int
    {
        [$arguments, $options] = $this->separateOptions(array_slice($argv, 1));
        $commandName = array_shift($arguments) ?: '';

        if (!$commandName) {
            $this->output->error('コマンドを指定してください。');
            return ExitCode::INVALID;
        }

        if (!isset($this->commands[$commandName])) {
            $this->output->error("未対応のコマンドです: {$commandName}");
            return ExitCode::INVALID;
        }

        $command = $this->commands[$commandName];
        return $command->execute($arguments, $options);
    }

    private function register(CommandInterface $command): void
    {
        $this->commands[$command->name()] = $command;
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, bool>}
     */
    private function separateOptions(array $arguments): array
    {
        $options = [];
        $positionals = [];

        foreach ($arguments as $argument) {
            if (strpos($argument, '--') === 0) {
                $optionName = substr($argument, 2);
                if ($optionName !== '') {
                    $options[$optionName] = true;
                }
                continue;
            }
            $positionals[] = $argument;
        }

        return [$positionals, $options];
    }
}
