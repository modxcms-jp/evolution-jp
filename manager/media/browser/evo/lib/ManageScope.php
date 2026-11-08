<?php

/**
 * 管理モード（メニューの「ファイル管理」の範囲）。ルートは設定 filemanager_path（既定はサイトのルート）で、拡張子を問わず扱う。
 * 権限はロールの権限 file_manager だけで決める（設定 use_browser やウェブユーザーのセッションは見ない）。
 * 新しい名前のファイルを作る操作だけは、upload_files・upload_images・upload_media を合わせた許可リストで確かめる。
 */
class ManageScope implements BrowserScope
{
    private $modx;
    private $config;
    private $guard;

    private function __construct(DocumentParser $modx, BrowserConfig $config)
    {
        $this->modx = $modx;
        $this->config = $config;
    }

    /**
     * 要求のたびに確かめる。管理画面のセッションと file_manager の権限だけを見る。
     * 権限はロールから読み直す（ロールの変更をログインし直さずに反映する。旧ファイル管理と同じ）。守る場所（guard()）もその権限から作る。
     *
     * @return ManageScope|array よければインスタンス、だめなら ['code', 'lang', 'status']
     */
    public static function authorize(DocumentParser $modx, BrowserConfig $config)
    {
        if (!sessionv('mgrValidated') || sessionv('usertype') !== 'manager') {
            return ['code' => 'unauthorized', 'lang' => 'fb_err_unauthorized', 'status' => 403];
        }
        manager()->refreshMgrPermissions();
        if (!$modx->hasPermission('file_manager')) {
            return ['code' => 'forbidden', 'lang' => 'fb_err_manage_forbidden', 'status' => 403];
        }

        return new self($modx, $config);
    }

    /** ファイル管理のルート(設定 filemanager_path。空ならサイトのルート)。ルートの決め方はここだけに置く */
    public static function rootPath(DocumentParser $modx)
    {
        return $modx->config('filemanager_path') ?: MODX_BASE_PATH;
    }

    public function baseDir()
    {
        return self::rootPath($this->modx);
    }

    public function guard()
    {
        if ($this->guard === null) {
            $this->guard = new PathGuard($this->baseDir(), PathGuard::protectedPathsFor($this->modx));
        }

        return $this->guard;
    }

    public function listsFile($name)
    {
        return true;
    }

    public function listsFolder($name)
    {
        return true;
    }

    public function isAllowedNewName($name)
    {
        return $this->config->isAllowedFilenameAll($name);
    }

    /** 拡張子が変わらないなら許し（.php のファイルの名前を変えられるように）、変わるときは新しい名前の許可リストで確かめる */
    public function isAllowedRename($oldName, $newName)
    {
        if (BrowserConfig::extensionOf($oldName) === BrowserConfig::extensionOf($newName)) {
            return true;
        }

        return $this->isAllowedNewName($newName);
    }

    /**
     * 複製は元と同じ拡張子なので拡張子は確かめない。ドットで始まる名前(.htaccess など)は複製しない
     * （sanitizeFilename() で先頭のドットが落ちて別の名前になるため。管理モードは、ドットで始まる名前のファイルを新しく作らない）
     */
    public function isAllowedCopy($sourceName, $newName)
    {
        return strpos($sourceName, '.') !== 0;
    }

    /** upload_maxsize が正の数ならその値。空なら null（本体では制限せず、PHP の上限だけが効く。旧ファイル管理と同じ） */
    public function maxUploadSize()
    {
        $size = (int) $this->modx->config('upload_maxsize');

        return $size > 0 ? $size : null;
    }

    /**
     * ツリーとパンくずの一番上の名前(ルートのフォルダの名前。ルートが / のときは /)。
     * basename() は PHP 7.4 でロケールによってマルチバイトの名前を崩すことがあるため使わない
     */
    public function rootLabel()
    {
        $root = PathGuard::normalize($this->baseDir());
        if ($root === false) {
            return '/';
        }
        $pos = strrpos($root, '/');
        $label = $pos === false ? $root : substr($root, $pos + 1);

        return $label === '' ? '/' : $label;
    }

    public function downloadName()
    {
        return 'files';
    }

    /** ルートからの相対パス($relPath)に対する公開 URL。ルートがサイトのルート（MODX_BASE_PATH）の外にあるときは null */
    public function publicUrl($relPath)
    {
        $sitePath = realpath(MODX_BASE_PATH);
        $rootPath = realpath($this->baseDir());
        if ($sitePath === false || $rootPath === false) {
            return null;
        }
        $sitePath = rtrim(str_replace('\\', '/', $sitePath), '/') . '/';
        $rootPath = rtrim(str_replace('\\', '/', $rootPath), '/') . '/';
        if (strpos($rootPath, $sitePath) !== 0) {
            return null;
        }

        $path = substr($rootPath, strlen($sitePath)) . $relPath;

        return rtrim($this->modx->config('site_url'), '/') . '/'
            . implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    public function uploadEvent()
    {
        return 'OnFileManagerUpload';
    }

    public function name()
    {
        return 'manage';
    }

    public function logLabel()
    {
        return '';
    }

    public function logRoot()
    {
        return '';
    }

    public function actions()
    {
        return [
            'list' => ['handler' => 'fbManageList'],
            'thumb' => ['handler' => 'fbActionThumb'],
            'upload' => ['handler' => 'fbManageUpload', 'csrf' => true],
            // 管理モードだけ(lib/ManageActions.php)。ピッカーの表には載らないので 400
            'readText' => ['handler' => 'ManageActions::readText'],
            'saveText' => ['handler' => 'ManageActions::saveText', 'csrf' => true, 'post' => true],
            'newfile' => ['handler' => 'ManageActions::newfile', 'csrf' => true, 'post' => true],
            'unzip' => ['handler' => 'ManageActions::unzip', 'csrf' => true, 'post' => true],
        ];
    }
}
