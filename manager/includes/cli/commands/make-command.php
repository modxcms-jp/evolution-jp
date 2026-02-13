<?php

$commandName = $args[0] ?? '';
$commandName = trim($commandName);
if ($commandName === '') {
    echo "Usage: php evo make:command command:name\n";
    exit(1);
}

$fileName = str_replace(':', '-', $commandName) . '.php';
$filePath = EVO_CLI_COMMANDS_PATH . $fileName;

if (is_file($filePath)) {
    fwrite(STDERR, "Command already exists: {$filePath}\n");
    exit(1);
}

$template = <<<'PHP'
<?php
/**
 * Command: %s
 * Description: TODO
 */

echo "Executing %s...\n";

// TODO: implement

echo "Done.\n";
PHP;

$content = sprintf($template, $commandName, $commandName);

$result = file_put_contents($filePath, $content);
if ($result === false) {
    fwrite(STDERR, "Failed to write: {$filePath}\n");
    exit(1);
}

chmod($filePath, 0644);

echo "Created: {$filePath}\n";
echo "Edit the file to implement your command.\n";
