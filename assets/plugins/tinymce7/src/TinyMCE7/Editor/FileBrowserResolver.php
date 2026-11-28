<?php
declare(strict_types=1);

namespace TinyMCE7\Editor;

require_once MODX_MANAGER_PATH . 'media/browser/evo/lib/PathGuard.php';
require_once MODX_MANAGER_PATH . 'media/browser/evo/lib/BrowserScope.php';
require_once MODX_MANAGER_PATH . 'media/browser/evo/lib/BrowserConfig.php';
require_once MODX_MANAGER_PATH . 'media/browser/evo/lib/PickerScope.php';

final class FileBrowserResolver
{
    /** TinyMCE の images_file_types の既定（本文に入った画像のうち、アップロードの対象にする拡張子） */
    private const TINYMCE_IMAGE_FILE_TYPES = ['jpeg', 'jpg', 'jpe', 'jfi', 'jif', 'jfif', 'png', 'gif', 'bmp', 'webp'];

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

        return rtrim($managerUrl, '/') . '/media/browser/evo/browser.php';
    }

    public function mcpukApiUrl(): string
    {
        $managerUrl = defined('MODX_MANAGER_URL') ? MODX_MANAGER_URL : MODX_BASE_URL . 'manager/';

        return rtrim($managerUrl, '/') . '/media/browser/evo/api.php';
    }

    /**
     * 今のセッションが、ファイルピッカーの API を使えるか（api.php が要求のたびに行う確かめと同じ PickerScope::authorize()）。
     * 使えないときは、画像編集も貼り付けのアップロードも付けない
     */
    public function canUsePicker(string $type = 'images'): bool
    {
        return \PickerScope::authorize(evo(), new \BrowserConfig(evo()), $type) instanceof \PickerScope;
    }

    /**
     * canUsePicker() に加えて、ファイルを置く権限 file_upload もあるか（api.php の uploadPasted が操作の表の perm で確かめるのと同じ PickerScope::allows()）
     */
    public function canUploadToPicker(string $type = 'images'): bool
    {
        $scope = \PickerScope::authorize(evo(), new \BrowserConfig(evo()), $type);

        return $scope instanceof \PickerScope && $scope->allows('file_upload');
    }

    /**
     * 貼り付け・ドロップした画像をアップロードする対象の拡張子。TinyMCE の既定（images_file_types）と、
     * ファイルピッカーの設定 upload_images（種類 images で保存できる拡張子）の重なり
     *
     * @return string[]
     */
    public function imagesFileTypes(): array
    {
        $allowed = (new \BrowserConfig(evo()))->allowedExtensions('images');

        return array_values(array_intersect(self::TINYMCE_IMAGE_FILE_TYPES, $allowed));
    }

    /**
     * @param array{url: string, csrfToken: string}|null $upload 貼り付けのアップロードを使うときの送り先と CSRF トークン
     */
    public function mcpukBootstrapScript(bool $imageEdit, ?array $upload): string
    {
        $settings = [
            'browserUrl' => $this->mcpukBrowserUrl(),
            'rbBaseUrl' => (new \BrowserConfig(evo()))->baseUrl(),
            'baseUrl' => MODX_BASE_URL,
            'types' => \BrowserConfig::TYPES,
            'editableExtensions' => \BrowserConfig::EDITABLE_EXTENSIONS,
            'imageEdit' => $imageEdit,
            'stripImagePaths' => (string) evo()->config('strip_image_paths') === '1',
        ];
        if ($upload !== null) {
            $settings['upload'] = $upload;
        }
        if (defined('MODX_SITE_URL')) {
            $settings['siteUrl'] = MODX_SITE_URL;
        }

        return 'window.MODX_FILE_PICKER = ' . json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';';
    }
}
