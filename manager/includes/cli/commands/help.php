<?php

$commands = [];
$files = glob(EVO_CLI_COMMANDS_PATH . '*.php');
if (is_array($files)) {
    foreach ($files as $file) {
        $name = basename($file, '.php');
        $commands[] = str_replace('-', ':', $name);
    }
}

sort($commands);

echo "Usage:\n";
echo "  php evo <command> [args]\n\n";

echo "Commands:\n";
foreach ($commands as $name) {
    echo "  {$name}\n";
}
