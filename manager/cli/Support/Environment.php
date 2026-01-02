<?php
declare(strict_types=1);

namespace Evolution\CMS\Cli\Support;

use DocumentParser;

class Environment
{
    public static function prepareServer(string $basePath): void
    {
        static::defineBaseConstants();
        static::seedServerGlobals();
        static::defineApiFlags();
        static::ensureAutoload($basePath);
    }

    public static function bootDocumentParser(string $basePath): DocumentParser
    {
        require_once $basePath . '/manager/includes/document.parser.class.inc.php';
        $modx = new DocumentParser();
        $modx->getSettings();
        return $modx;
    }

    private static function defineBaseConstants(): void
    {
        if (!defined('MODX_BASE_PATH')) {
            include dirname(__DIR__, 3) . '/define-path.php';
        }
    }

    private static function defineApiFlags(): void
    {
        if (!defined('MODX_API_MODE')) {
            define('MODX_API_MODE', true);
        }
        if (!defined('IN_MANAGER_MODE')) {
            define('IN_MANAGER_MODE', false);
        }
        if (!defined('IN_PARSER_MODE')) {
            define('IN_PARSER_MODE', 'true');
        }
    }

    private static function ensureAutoload(string $basePath): void
    {
        $autoloadPath = $basePath . '/vendor/autoload.php';
        if (is_file($autoloadPath)) {
            require_once $autoloadPath;
        }
    }

    private static function seedServerGlobals(): void
    {
        $defaults = [
            'REQUEST_METHOD' => 'CLI',
            'SERVER_NAME'    => 'localhost',
            'HTTP_HOST'      => 'localhost',
            'SCRIPT_NAME'    => '/manager/cli/bin/cms',
            'REQUEST_URI'    => '/manager/cli/bin/cms',
        ];

        foreach ($defaults as $key => $value) {
            if (serverv($key)) {
                continue;
            }
            array_set($_SERVER, $key, $value);
        }
    }
}
