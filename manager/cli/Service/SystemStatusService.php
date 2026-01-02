<?php
declare(strict_types=1);

namespace Evolution\CMS\Cli\Service;

use Evolution\CMS\Cli\Infrastructure\CacheStore;

class SystemStatusService
{
    private CacheStore $cacheStore;

    public function __construct(CacheStore $cacheStore)
    {
        $this->cacheStore = $cacheStore;
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $version = evo()->getVersionData();

        return [
            'cms' => [
                'version'      => $version['version'] ?? '',
                'branch'       => $version['branch'] ?? '',
                'release_date' => $version['release_date'] ?? '',
                'full_name'    => $version['full_appname'] ?? '',
            ],
            'php' => [
                'version' => PHP_VERSION,
            ],
            'database' => $this->getDatabaseStatus(),
            'cache' => $this->cacheStore->summarize(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getDatabaseStatus(): array
    {
        $connected = db()->isConnected();
        if (!$connected) {
            $connected = db()->connect();
        }

        $version = $connected ? db()->getVersion() : null;

        return [
            'connected' => $connected,
            'version' => $version ?: null,
        ];
    }
}
