<?php

/**
 * 管理モード（メニューの「ファイル管理」の範囲）。
 * ルートは設定 filemanager_path（既定はサイトのルート）で、拡張子を問わずすべてのファイルを扱う。
 * 権限はロールの権限 file_manager だけで決める（設定 use_browser やウェブユーザーのセッションは見ない）。
 * 新しい名前のファイルを作る操作だけは、upload_files・upload_images・upload_media を合わせた許可リストで確かめる。
 */
class ManageScope implements BrowserScope
{
    /** @var DocumentParser */
    private $modx;
    /** @var BrowserConfig */
    private $config;
    /** @var PathGuard|null */
    private $guard;

    private function __construct(DocumentParser $modx, BrowserConfig $config)
    {
        $this->modx = $modx;
        $this->config = $config;
    }

    /**
     * この範囲を使ってよいかを、要求のたびに確かめる。管理画面のセッションと file_manager の権限だけを見る。
     * 権限はロールから読み直す（旧ファイル管理は、管理画面の要求ごとに読み直していた。ロールの変更をログインし直さずに反映する）。
     * 守る場所（guard()）も、読み直した権限から作る。
     *
     * @return ManageScope|array よければインスタンス、だめなら ['code' => コード, 'lang' => 言語キー, 'status' => HTTP ステータス]
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

    public function baseDir()
    {
        return $this->modx->config('filemanager_path') ?: MODX_BASE_PATH;
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

    /**
     * 拡張子が変わらないなら許し（.php のファイルの名前を変えられるように）、変わるときは新しい名前の許可リストで確かめる
     */
    public function isAllowedRename($oldName, $newName)
    {
        if (BrowserConfig::extensionOf($oldName) === BrowserConfig::extensionOf($newName)) {
            return true;
        }

        return $this->isAllowedNewName($newName);
    }

    /**
     * 複製は元と同じ拡張子になるので、拡張子は確かめない。
     * ドットで始まる名前(.htaccess など)は複製しない。新しい名前は sanitizeFilename() を通り、先頭のドットが落ちて
     * 別の名前(htaccess)のファイルになってしまうため。アップロード・新しいファイル・名前の変更と同じく、
     * 管理モードからは、ドットで始まる名前のファイルを新しく作らない(あるものの表示・編集・削除・移動・ダウンロードはできる)
     */
    public function isAllowedCopy($sourceName, $newName)
    {
        return strpos($sourceName, '.') !== 0;
    }

    /**
     * upload_maxsize が正の数ならその値。空なら null（本体では制限せず、PHP の upload_max_filesize と post_max_size だけが効く。旧ファイル管理と同じ）
     */
    public function maxUploadSize()
    {
        $size = (int) $this->modx->config('upload_maxsize');

        return $size > 0 ? $size : null;
    }

    public function downloadName()
    {
        return 'files';
    }

    /**
     * ルートからの相対パスに対する、サイトの公開 URL。ルートがサイトのルート（MODX_BASE_PATH）の外にあるときは null
     *
     * @param string $relPath ルートからの相対パス（/ 区切り）
     * @return string|null
     */
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
            'upload' => ['handler' => 'fbManageUpload', 'csrf' => true],
        ];
    }
}
