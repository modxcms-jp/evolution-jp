<?php

require_once __DIR__ . '/datauri.class.inc.php';
require_once __DIR__ . '/filesystem.class.inc.php';
require_once __DIR__ . '/../media/browser/evo/lib/PathGuard.php';
require_once __DIR__ . '/../media/browser/evo/lib/BrowserScope.php';
require_once __DIR__ . '/../media/browser/evo/lib/PickerScope.php';
require_once __DIR__ . '/../media/browser/evo/lib/PathResolver.php';
require_once __DIR__ . '/../media/browser/evo/lib/BrowserConfig.php';
require_once __DIR__ . '/../media/browser/evo/lib/ChangeLog.php';
require_once __DIR__ . '/../media/browser/evo/lib/FileStore.php';

/**
 * data: 画像を、ファイルピッカーの images/年/月/pasted-doc-{文書ID}-{連番}.拡張子 に書く。
 * 置き場所・名前・拡張子と大きさの確かめ・同名の連番・OnFileBrowserUpload は FileStore（ピッカーと共通）に任せ、
 * 復号と縮小は EvoDataUri に任せる。保存時の変換（convertDataUriToFiles()）と CLI の content:extract-datauri が使う。
 * 管理操作ログ（Uploaded File）は、呼ぶ側が書き込みを確定させてから record() で 1 回にまとめて書く。
 */
class EvoDataUriStore
{
    private $modx;
    private $scope;
    private $resolver;
    private $sizeLimit;
    /** 書いたファイルの、images のルートからの相対パス */
    private $saved = [];

    /**
     * @param int|false $sizeLimit 大きさの上限（バイト）。false ならピッカーの設定（upload_maxsize）
     */
    public function __construct(DocumentParser $modx, $sizeLimit = false)
    {
        $this->modx = $modx;
        // 守る場所の判定（PathGuard）が manager() を使う。管理画面の外（CLI）では読み込まれていない
        if (!$modx->manager) {
            $modx->loadExtension('ManagerAPI');
        }
        $this->scope = PickerScope::forType($modx, new BrowserConfig($modx), 'images');
        $this->resolver = new PathResolver($this->scope);
        $this->sizeLimit = $sizeLimit;
    }

    /**
     * 復号と縮小までして、書けるかを確かめる（書かない）。
     * $dimensions は img の width・height 属性。縮小の決まりは保存時の変換と同じ（元より小さいときだけ縮める）
     *
     * @param int $docId 画像を入れる文書の ID（ファイルの名前に入る）
     * @return array{mime:string,extension:string,bytes:string,original_size:int,resized:bool}|array{error:string,level:int,param:mixed,mime?:string,size?:int}
     *         拡張子や大きさで断ったときは、error に加えて mime・size（縮小のあとの大きさ）を返す
     */
    public function prepare($dataUri, $dimensions, $docId)
    {
        $decoded = EvoDataUri::decode($dataUri);
        if (isset($decoded['error'])) {
            return $decoded;
        }

        $decoded['original_size'] = strlen($decoded['bytes']);
        $decoded['resized'] = false;
        if ($dimensions && strpos($decoded['mime'], 'image/') === 0) {
            $resized = EvoDataUri::resize($decoded['bytes'], $decoded['mime'], $dimensions, 0);
            if ($resized !== null) {
                $decoded['resized'] = $resized !== $decoded['bytes'];
                $decoded['bytes'] = $resized;
            }
        }

        $checked = FileStore::checkNewFile(
            $this->scope,
            $this->modx,
            self::fileName($docId, 1, $decoded['extension']),
            strlen($decoded['bytes']),
            $this->sizeLimit
        );
        if (isset($checked['error'])) {
            return [
                'error' => '%s',
                'level' => 2,
                'param' => $checked['error'] . ' (' . $decoded['extension'] . ')',
                'mime' => $decoded['mime'],
                'size' => strlen($decoded['bytes']),
            ];
        }

        return $decoded;
    }

    /**
     * prepare() の結果を、images/年/月/ に書く
     *
     * @param array $prepared prepare() の成功の戻り値
     * @param int $docId 画像を入れる文書の ID
     * @param int $timestamp 置き場所（年/月）を決める時刻（サイトの時刻。CLI は文書の更新日時）
     * @return array{path:string,rel:string}|array{error:string,level:int,param:mixed} path は本文などに入れる URL（ピッカーが返す path と同じ形）
     */
    public function place(array $prepared, $docId, $timestamp)
    {
        $folder = FileStore::ensureFolderPath($this->resolver, $this->scope, $this->modx, FileStore::pastedFolder($timestamp));
        if (isset($folder['error'])) {
            return ['error' => 'Failed to prepare the folder: %s', 'level' => 3, 'param' => $folder['error']];
        }

        $saved = FileStore::saveNewFile(
            $this->resolver,
            $this->scope,
            $this->modx,
            $folder['dir'],
            self::fileName($docId, self::nextNumber($folder['dir']['real'], $docId), $prepared['extension']),
            strlen($prepared['bytes']),
            FileStore::bytesWriter($this->modx, $prepared['bytes']),
            $this->sizeLimit
        );
        if (isset($saved['error'])) {
            return ['error' => 'Failed to save the file: %s', 'level' => 3, 'param' => $saved['error']];
        }

        $rel = ChangeLog::relPath($folder['dir'], $saved['name']);
        $this->saved[$rel] = $folder['dir']['real'] . $saved['name'];

        return ['path' => $this->scope->fileUrls($rel)['path'], 'rel' => $rel];
    }

    /**
     * ファイルの名前。名前の決まりはここ 1 か所に置く（FileStore::pastedName() が文書の ID を取る形になったら、そちらに寄せる）
     */
    private static function fileName($docId, $number, $extension)
    {
        return 'pasted-doc-' . (int)$docId . '-' . (int)$number . '.' . $extension;
    }

    /**
     * そのフォルダの、同じ文書の ID の画像の連番の最大 + 1（1 から。拡張子は問わない）。
     * 消えた番号は使い回さない（ブラウザーや CDN が、同じ URL の古い画像を出し続けるため）
     */
    private static function nextNumber($dirReal, $docId)
    {
        $max = 0;
        $pattern = '/^pasted-doc-' . (int)$docId . '-([0-9]+)\./';
        foreach (scandir($dirReal) ?: [] as $name) {
            if (preg_match($pattern, $name, $m)) {
                $max = max($max, (int)$m[1]);
            }
        }

        return $max + 1;
    }

    /** place() で書いたファイルを消す（そのあとの DB の更新に失敗したときなど）。管理操作ログにも書かない */
    public function discard($rel)
    {
        if (isset($this->saved[$rel])) {
            @unlink($this->saved[$rel]);
            unset($this->saved[$rel]);
        }
    }

    /** ロールの権限 file_upload でファイルを置いてよいか（管理画面のセッションが無い CLI は常に true） */
    public function allowsUpload()
    {
        return $this->scope->allows('file_upload');
    }

    /** place() で書いて、まだ discard() も record() もしていないファイルを、すべて消す */
    public function discardAll()
    {
        foreach (array_keys($this->saved) as $rel) {
            $this->discard($rel);
        }
    }

    /** place() で書いて、まだ discard() も record() もしていないファイルの相対パス */
    public function saved()
    {
        return array_keys($this->saved);
    }

    /** 書いたファイルを管理操作ログに書く（Uploaded File。貼り付けのアップロードと同じ） */
    public function record()
    {
        ChangeLog::record($this->scope, 'Uploaded File', array_keys($this->saved));
        $this->saved = [];
    }
}
