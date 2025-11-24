<?php
declare(strict_types=1);

namespace TinyMCE7\Editor;

final class FileBrowserResolver
{
    /**
     * @param array $config
     * @param array $params
     * @return array{0: array, 1: string}
     */
    public function resolve(array $config, array $params): array
    {
        $browser = null;

        if (!empty($params['tinymce7_file_browser']) && is_string($params['tinymce7_file_browser'])) {
            $browser = strtolower((string)$params['tinymce7_file_browser']);
        } elseif (!empty($params['file_browser']) && is_string($params['file_browser'])) {
            $browser = strtolower((string)$params['file_browser']);
        } elseif (isset($config['tinymce7_file_browser']) && is_string($config['tinymce7_file_browser'])) {
            $browser = strtolower((string)$config['tinymce7_file_browser']);
            unset($config['tinymce7_file_browser']);
        }

        switch ($browser) {
            case 'mcpuk':
            case null:
                return [$config, 'mcpuk'];
            case 'none':
            default:
                return [$config, 'none'];
        }
    }

    public function mcpukBrowserUrl(): string
    {
        $managerUrl = defined('MODX_MANAGER_URL') ? MODX_MANAGER_URL : MODX_BASE_URL . 'manager/';

        return rtrim($managerUrl, '/') . '/media/browser/mcpuk/browser.php?editor=tinymce7';
    }

    public function mcpukBootstrapScript(): string
    {
        $snippets = [];
        $snippets[] = 'window.MODX_FILE_BROWSER_URL = ' . json_encode($this->mcpukBrowserUrl()) . ';';
        $snippets[] = 'window.MODX_BASE_URL = ' . json_encode(MODX_BASE_URL) . ';';
        if (defined('MODX_SITE_URL')) {
            $snippets[] = 'window.MODX_SITE_URL = ' . json_encode(MODX_SITE_URL) . ';';
        }

        return implode('', $snippets);
    }
}
