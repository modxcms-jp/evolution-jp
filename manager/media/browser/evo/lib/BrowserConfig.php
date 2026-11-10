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

    /** ブラウザが <img> で表示できる画像の拡張子。一覧の image: true の正本(ThumbnailService::isDisplayableImageExtension() もこれを見る) */
    const DISPLAYABLE_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico', 'svg'];

    /** 画像の拡張子(表示できないもの tif・tiff・psd を含む)。管理モードでは、テキストとして開くのを断る */
    const IMAGE_EXTENSIONS = [...self::DISPLAYABLE_IMAGE_EXTENSIONS, 'tif', 'tiff', 'psd'];

    /** 管理モードのプレビューで <video>・<audio> として再生する拡張子(ブラウザが標準で再生できるもの)。一覧の応答の media が、この判定の正本 */
    const VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogv', 'mov'];
    const AUDIO_EXTENSIONS = ['mp3', 'm4a', 'aac', 'wav', 'ogg', 'oga', 'flac'];

    /**
     * 管理モードで新しく作れる(アップロード・名前の変更・zip の展開・新しいファイルの)拡張子(小文字)。
     * サーバーで実行されず、同じオリジンで開いてもスクリプトが動かない形式だけ。html・svg・js・xml などは設定で許したときだけ。
     * php・phtml・phar・shtml・htaccess・ini・tpl・pagecache・cgi・pl・py・sh なども、置くと実行や設定の書き換えにつながるので入れない
     * (設定 upload_files・upload_images・upload_media に書けば、サイトの判断で足せる。isAllowedNewExtension())
     */
    const MANAGE_CREATABLE_EXTENSIONS = [
        // テキスト
        'txt', 'md', 'markdown', 'json', 'yml', 'yaml', 'csv', 'tsv', 'log', 'css',
        // ラスター画像(svg は含まない)
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'ico', 'tif', 'tiff', 'psd',
        // 動画・音声
        ...self::VIDEO_EXTENSIONS,
        ...self::AUDIO_EXTENSIONS,
        'avi', 'mpg', 'mpeg', 'wmv', 'au',
        // 文書
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
        // アーカイブ
        'zip', 'gz', 'gzip', 'tar', 'tgz', 'rar', 'z', '7z',
        // フォント
        'woff', 'woff2', 'ttf', 'otf',
    ];

    /**
     * 管理モードで、開く(ダブルクリック・行のアイコン・右クリックの「開く」)と、中身を確かめずに最初からテキストエディターで開く拡張子(小文字)。拡張子だけの名前(.htaccess)は、名前全体(htaccess)をこの一覧と照らす。
     * ここに無いファイルも、クリックで中身を確かめてテキストなら開ける(ManageActions::textFileOrFail())
     */
    const TEXT_EXTENSIONS = [
        'txt', 'php', 'tpl', 'shtml', 'html', 'htm', 'xml', 'js', 'css', 'pagecache', 'htaccess', 'sample',
        // メンテナンスで触る文書・設定・記録の形式(旧ファイル管理には無かった)
        'md', 'markdown', 'json', 'yml', 'yaml', 'ini', 'log', 'csv',
    ];
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
        $rbBaseUrl = $this->rbBaseUrl();
        if ($this->rbBaseUrlHasHost()) {
            return rtrim($rbBaseUrl, '/');
        }

        return trim($rbBaseUrl, '/');
    }

    /** 設定の値を、自サイトの URL を含まない形にそろえたもの（既存の設定に残っている値も正しく扱うため、読むときにも通す） */
    private function rbBaseUrl()
    {
        return init::normalize_rb_base_url(
            (string) $this->modx->config('rb_base_url'),
            (string) $this->modx->config('site_url'),
            MODX_BASE_URL
        );
    }

    private function rbBaseUrlHasHost()
    {
        $parsed = parse_url($this->rbBaseUrl());

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

    /**
     * 管理モードで新しく作ってよい名前か。MANAGE_CREATABLE_EXTENSIONS か、設定の 3 つ(allowedExtensionsAll())に含まれる拡張子。
     * 大文字小文字は区別せず、拡張子の無い名前は許さない。名前は sanitizeFilename() を通した後のものを渡す
     */
    public function isAllowedNewExtension($name)
    {
        return self::hasExtensionIn($name, array_merge(self::MANAGE_CREATABLE_EXTENSIONS, $this->allowedExtensionsAll()));
    }

    /** 管理モードのプレビューで再生する種別。video / audio、どちらでもなければ空文字 */
    public static function mediaKindOf($name)
    {
        $ext = self::extensionOf($name);
        if (in_array($ext, self::VIDEO_EXTENSIONS, true)) {
            return 'video';
        }

        return in_array($ext, self::AUDIO_EXTENSIONS, true) ? 'audio' : '';
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

    /** 画像・動画・音声の拡張子か。中身を見ずにテキストとして開くのを断る対象 */
    public static function isNonTextMediaFilename($name)
    {
        return self::mediaKindOf($name) !== '' || in_array(self::extensionOf($name), self::IMAGE_EXTENSIONS, true);
    }

    public function maxUploadSize()
    {
        $size = (int) size_to_bytes($this->modx->config('upload_maxsize'));

        return $size > 0 ? $size : 5000000;
    }

    /**
     * zip を作れる環境か(PHP の zip 拡張があるか)。API と画面の両方でこれを使う
     */
    public function canCreateZip()
    {
        return extension_loaded('zip') && class_exists('ZipArchive');
    }
}
