<?php

/**
 * ピッカー（TV の画像の欄・TinyMCE などから「ファイルを選ぶ」ための範囲）。
 * ルートは rb_base_dir の下の種類（images / media / files）で、種類ごとの拡張子の許可リストにあるファイルだけを扱う。
 */
class PickerScope implements BrowserScope
{
    private $modx;
    private $config;
    /** images / media / files */
    private $type;
    private $guard;

    private function __construct(DocumentParser $modx, BrowserConfig $config, $type)
    {
        $this->modx = $modx;
        $this->config = $config;
        $this->type = $type;
    }

    /**
     * 管理画面のセッションは常に許可。ウェブユーザーのセッションは、設定 rb_webuser が有効なときだけ許可する（既定は無効）。
     * どちらも設定 use_browser が有効で、種類が images / media / files のどれかであること。
     * 戻り値は、よければインスタンス、だめなら ['code', 'lang', 'status']
     */
    public static function authorize(DocumentParser $modx, BrowserConfig $config, $type = null)
    {
        if (!sessionv('mgrValidated')) {
            if (!sessionv('webValidated') || (int) $modx->config('rb_webuser') !== 1) {
                return ['code' => 'unauthorized', 'lang' => 'fb_err_unauthorized', 'status' => 403];
            }
        }
        if (!$config->isBrowserEnabled()) {
            return ['code' => 'forbidden', 'lang' => 'fb_err_forbidden', 'status' => 403];
        }

        $type = $type === null ? anyv('type', 'images') : $type;
        if (!$config->isValidType($type)) {
            return ['code' => 'invalid_type', 'lang' => 'fb_err_invalid_type', 'status' => 400];
        }

        // 種類のフォルダは設定で決めたルートではないので、その実体が rb_base_dir の実体の中にあることを確かめる
        // （種類のフォルダ自体が外を指すリンクなら、リンク先をピッカーのルートにしない）。assets/docs/file-storage.md を参照
        $base = new PathGuard($config->baseDir());
        if (!$base->isWithinBase($config->baseDir() . $type)) {
            return ['code' => 'invalid_root', 'lang' => 'fb_err_invalid_root', 'status' => 403];
        }

        return self::forType($modx, $config, $type);
    }

    /**
     * セッションも設定も見ずに、種類の範囲を作る(CLI など、ログインしたセッションの無い処理用)。
     * 呼ぶ側が、権限と種類の確かめ(authorize() が行う内容)を済ませていること
     */
    public static function forType(DocumentParser $modx, BrowserConfig $config, $type)
    {
        return new self($modx, $config, $type);
    }

    public function type()
    {
        return $this->type;
    }

    public function config()
    {
        return $this->config;
    }

    public function baseDir()
    {
        return $this->config->baseDir() . $this->type;
    }

    public function guard()
    {
        if ($this->guard === null) {
            $this->guard = new PathGuard($this->baseDir(), PathGuard::protectedPathsFor($this->modx));
        }

        return $this->guard;
    }

    /**
     * ファイルの path(SetUrl へ渡す相対パス)と url(ブラウザ内表示用の絶対 URL)。一覧と、アップロードの応答で同じ作り方を使う
     *
     * @param string $relPath この種類のフォルダー(images など)の下の、ルートからの相対パス
     * @return array{path:string,url:string}
     */
    public function fileUrls($relPath)
    {
        $urlPrefix = $this->config->urlPrefix();
        // 種類のフォルダ(images など)を頭に付けたパスが、rb_base_url の下の URL になる
        $relPath = $this->type . '/' . $relPath;

        // path: 読める形のまま渡し、FTP などで置かれた名前の % # ? だけは URL が切れないようエンコードする(% を最初に置き換える)
        $path = ($urlPrefix !== '' ? $urlPrefix . '/' : '')
            . str_replace(['%', '#', '?'], ['%25', '%23', '%3F'], $relPath);

        // url: パスの要素ごとにエンコードする
        $url = $this->config->baseUrl() . implode('/', array_map('rawurlencode', explode('/', $relPath)));

        return ['path' => $path, 'url' => $url];
    }

    public function listsFile($name)
    {
        return strpos($name, '.') !== 0 && $this->config->isAllowedFilename($this->type, $name);
    }

    public function listsFolder($name)
    {
        return strpos($name, '.') !== 0;
    }

    public function isAllowedNewName($name)
    {
        return $this->config->isAllowedFilename($this->type, $name);
    }

    public function isAllowedRename($oldName, $newName)
    {
        return $this->config->isAllowedFilename($this->type, $newName);
    }

    public function isAllowedCopy($sourceName, $newName)
    {
        return $this->config->isAllowedFilename($this->type, $newName);
    }

    /**
     * 管理画面のセッションはロールの権限で決める（要求のたびにロールから読み直し、変更をログインし直さずに反映する）。
     * ウェブユーザーのセッション（設定 rb_webuser）は、ロールを持たないので今までどおり許す
     */
    public function allows($permission)
    {
        if (!sessionv('mgrValidated')) {
            return true;
        }
        manager()->refreshMgrPermissions();

        return $this->modx->hasPermission($permission);
    }

    public function maxUploadSize()
    {
        return $this->config->maxUploadSize();
    }

    public function downloadName()
    {
        return $this->type;
    }

    public function uploadEvent()
    {
        return 'OnFileBrowserUpload';
    }

    public function name()
    {
        return 'picker';
    }

    public function logLabel()
    {
        return '[picker] ';
    }

    public function logRoot()
    {
        return $this->type . '/';
    }

    public function actions()
    {
        return [
            'list' => ['handler' => 'fbPickerList'],
            'thumb' => ['handler' => 'fbActionThumb'],
            'upload' => ['handler' => 'fbPickerUpload', 'csrf' => true, 'perm' => 'file_upload'],
            'uploadPasted' => ['handler' => 'fbPickerUploadPasted', 'csrf' => true, 'post' => true, 'perm' => 'file_upload'],
        ];
    }
}
