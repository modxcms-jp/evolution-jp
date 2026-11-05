<?php

/**
 * ファイルブラウザ設定の解決。旧mcpuk ConnectorConfigBuilder の知見を踏襲する。
 */
class BrowserConfig
{
    const TYPES = ['images', 'media', 'files'];

    /** zip でまとめてダウンロードできるファイル数と、合計サイズ(バイト)の上限 */
    const ZIP_MAX_FILES = 200;
    const ZIP_MAX_BYTES = 268435456;

    /**
     * 画面で編集できる画像の拡張子(編集した結果を、元の拡張子のまま canvas から書き出せるものだけ)と、
     * 編集して保存できる画像の長い辺の上限(px)。画面と API の両方でこれを使う
     */
    const EDITABLE_EXTENSIONS = ['jpg', 'jpeg', 'png'];
    const EDIT_MAX_DIMENSION = 4096;
    /** 画面で編集を出す画像の画素数(幅×高さ)の上限。ブラウザが元の画像を展開するときのメモリを守る(5000 万画素で RGBA 約 200MB) */
    const EDIT_MAX_PIXELS = 50000000;

    /** 管理モードでテキストとして表示・編集できる拡張子(小文字)。拡張子だけの名前(.htaccess)は、名前全体(htaccess)をこの一覧と照らす */
    const TEXT_EXTENSIONS = ['txt', 'php', 'tpl', 'shtml', 'html', 'htm', 'xml', 'js', 'css', 'pagecache', 'htaccess', 'sample'];
    /** テキストとして開くファイルの大きさの上限(バイト) */
    const TEXT_MAX_BYTES = 2097152;

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

        return $this->extensionsOf($key);
    }

    /** upload_files・upload_images・upload_media を合わせた拡張子の許可リスト（旧ファイル管理の uploadablefiles() と同じ範囲） */
    public function allowedExtensionsAll()
    {
        return array_values(array_unique(array_merge(
            $this->extensionsOf('upload_files'),
            $this->extensionsOf('upload_images'),
            $this->extensionsOf('upload_media')
        )));
    }

    private function extensionsOf($settingKey)
    {
        $value = strtolower((string) $this->modx->config($settingKey));

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    public static function extensionOf($name)
    {
        $name = (string) $name;
        $dotPos = strrpos($name, '.');

        return $dotPos === false ? '' : strtolower(substr($name, $dotPos + 1));
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
        return self::hasExtensionIn($name, $this->allowedExtensions($type));
    }

    /** 合わせた許可リスト（allowedExtensionsAll()）に含まれるか。判定は isAllowedFilename() と同じ */
    public function isAllowedFilenameAll($name)
    {
        return self::hasExtensionIn($name, $this->allowedExtensionsAll());
    }

    private static function hasExtensionIn($name, array $allowed)
    {
        return strrpos((string) $name, '.') !== false && in_array(self::extensionOf($name), $allowed, true);
    }

    /**
     * 名前の拡張子が、画面で編集できる拡張子(EDITABLE_EXTENSIONS)か。大文字小文字は区別しない
     *
     * @param string $name ファイル名(ディレクトリを含まない)
     * @return bool
     */
    public static function isEditableFilename($name)
    {
        $name = (string) $name;
        $dotPos = strrpos($name, '.');
        if ($dotPos === false) {
            return false;
        }

        return in_array(strtolower(substr($name, $dotPos + 1)), self::EDITABLE_EXTENSIONS, true);
    }

    /** 名前の拡張子が TEXT_EXTENSIONS か（大文字小文字は区別しない。拡張子の無い名前は対象にしない） */
    public static function isTextFilename($name)
    {
        return in_array(self::extensionOf($name), self::TEXT_EXTENSIONS, true);
    }

    public function maxUploadSize()
    {
        $size = $this->modx->config('upload_maxsize');

        return empty($size) ? 5000000 : (int) $size;
    }

    /**
     * zip を作れる環境か(PHP の zip 拡張があるか)。API と画面の両方でこれを使う
     */
    public function canCreateZip()
    {
        return extension_loaded('zip') && class_exists('ZipArchive');
    }
}
