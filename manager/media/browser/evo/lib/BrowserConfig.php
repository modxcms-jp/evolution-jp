<?php

/**
 * ファイルブラウザ設定の解決。旧mcpuk ConnectorConfigBuilder の知見を踏襲する。
 */
class BrowserConfig
{
    const TYPES = ['images', 'media', 'files'];

    /** @var DocumentParser */
    private $modx;

    public function __construct(DocumentParser $modx)
    {
        $this->modx = $modx;
    }

    public function isBrowserEnabled()
    {
        return (int) $this->modx->config('use_browser') === 1;
    }

    public function baseDir()
    {
        return rtrim($this->modx->config('rb_base_dir'), '/') . '/';
    }

    public function baseUrl()
    {
        $prefix = $this->urlPrefix();
        if ($this->rbBaseUrlHasHost()) {
            return $prefix . '/';
        }

        return rtrim($this->modx->config('site_url'), '/') . '/' . ($prefix !== '' ? $prefix . '/' : '');
    }

    /**
     * SetUrl(TV値等)へ渡すパスのプレフィックス。旧mcpukと同じく
     * rb_base_urlがホストを含まない限り相対(例: content)を返す。
     */
    public function urlPrefix()
    {
        $rbBaseUrl = (string) $this->modx->config('rb_base_url');
        if ($this->rbBaseUrlHasHost()) {
            return rtrim($rbBaseUrl, '/');
        }

        return trim($rbBaseUrl, '/');
    }

    private function rbBaseUrlHasHost()
    {
        $parsed = parse_url((string) $this->modx->config('rb_base_url'));

        return is_array($parsed) && !empty($parsed['host']);
    }

    public function isValidType($type)
    {
        return in_array($type, self::TYPES, true);
    }

    public function allowedExtensions($type)
    {
        $key = [
            'images' => 'upload_images',
            'media' => 'upload_media',
            'files' => 'upload_files',
        ][$type] ?? null;

        if ($key === null) {
            return [];
        }

        $value = strtolower((string) $this->modx->config($key));

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * 名前の拡張子が、この種別で許している拡張子（allowedExtensions()）に含まれるかを返す。
     * アップロードと名前の変更で同じ基準を使うため、ここで確かめる。
     * 名前は DocumentParser::sanitizeFilename() を通した後のものを渡す。大文字小文字は区別せず、拡張子の無い名前は許さない。
     *
     * @param string $type images / media / files
     * @param string $name ファイル名（ディレクトリを含まない）
     * @return bool
     */
    public function isAllowedFilename($type, $name)
    {
        $name = (string) $name;
        $dotPos = strrpos($name, '.');
        if ($dotPos === false) {
            return false;
        }

        return in_array(strtolower(substr($name, $dotPos + 1)), $this->allowedExtensions($type), true);
    }

    public function maxUploadSize()
    {
        $size = $this->modx->config('upload_maxsize');

        return empty($size) ? 5000000 : (int) $size;
    }
}
