<?php
declare(strict_types=1);

$basePath = dirname(__DIR__, 2);

require_once $basePath . '/define-path.php';
require_once $basePath . '/manager/includes/helpers.php';

spl_autoload_register(static function ($class): void {
    $prefix = 'Evolution\\CMS\\Cli\\';
    $baseDir = __DIR__ . '/';

    $length = strlen($prefix);
    if (strncmp($class, $prefix, $length) !== 0) {
        return;
    }

    $relativeClass = substr($class, $length);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

use Evolution\CMS\Cli\Support\Environment;

Environment::prepareServer($basePath);

return Environment::bootDocumentParser($basePath);
