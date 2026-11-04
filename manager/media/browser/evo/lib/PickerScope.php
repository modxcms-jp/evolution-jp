<?php

/**
 * ピッカー（TV の画像の欄・TinyMCE などから「ファイルを選ぶ」ための範囲）。
 * ルートは rb_base_dir の下の種類（images / media / files）で、種類ごとの拡張子の許可リストにあるファイルだけを扱う。
 */
class PickerScope implements BrowserScope
{
    /** @var DocumentParser */
    private $modx;
    /** @var BrowserConfig */
    private $config;
    /** @var string images / media / files */
    private $type;
    /** @var PathGuard|null */
    private $guard;

    private function __construct(DocumentParser $modx, BrowserConfig $config, $type)
    {
        $this->modx = $modx;
        $this->config = $config;
        $this->type = $type;
    }

    /**
     * この範囲を使ってよいかを確かめる。管理画面のセッションは常に許可。ウェブユーザーのセッションは、
     * システム設定「ウェブユーザーのファイルブラウザ利用」(rb_webuser) が有効なときだけ許可する（既定は無効）。
     * どちらも設定 use_browser が有効で、種類が images / media / files のどれかであること。
     *
     * @param string|null $type 種類。null なら type のリクエスト値（無ければ images）
     * @return PickerScope|array よければインスタンス、だめなら ['code' => コード, 'lang' => 言語キー, 'status' => HTTP ステータス]
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

    public function isAllowedCopy($name)
    {
        return $this->config->isAllowedFilename($this->type, $name);
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

    public function actions()
    {
        return [
            'list' => ['handler' => 'fbPickerList'],
            'thumb' => ['handler' => 'fbActionThumb'],
            'upload' => ['handler' => 'fbPickerUpload', 'csrf' => true],
        ];
    }
}
