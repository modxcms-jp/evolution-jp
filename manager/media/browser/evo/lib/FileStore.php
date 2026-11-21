<?php

/**
 * ファイルブラウザの保存先への書き込み(フォルダーの作成・新しい名前でのファイルの保存・貼り付けた画像の置き場所)。
 * api.php は読み込むと認証と振り分けを実行するため、CLI など api.php の外からも使う処理はここに置く。
 * 範囲は PickerScope::forType()(セッションを見ない)で作り、PathResolver と合わせて渡す。
 * リクエストの値($_FILES・$_POST)と HTTP の応答は扱わない。失敗は戻り値(エラーの code か利用者に見せる文言)で返し、応答にするのは呼ぶ側。
 * 管理操作ログ(Uploaded File)の記録も、1 回の操作でまとめて書けるよう呼ぶ側が ChangeLog::record() で行う(Created Folder だけはここで記録する)。
 */
class FileStore
{
    /** 貼り付けた画像の名前を、保存する側が付けることを示す名前(拡張子の前)。TinyMCE が名前の無い画像に付ける名前と同じ */
    const PASTED_PLACEHOLDER = 'image';

    /** パスの最後の要素。basename() は PHP 7.4 ではロケールによって先頭のマルチバイト文字を落とすため使わない */
    public static function baseName($path)
    {
        return substr($path, strrpos($path, '/') + 1);
    }

    /**
     * 保存先のフォルダで空いているファイルのフルパスを探す。同名があれば 名前(1).拡張子 のように連番を付ける。
     * 見つからなければ null。
     *
     * $modx->move_uploaded_file() は保存先の名前をもう一度無害化する（設定によっては括弧などが _ になる）。
     * 空きを確かめた名前と実際に保存される名前がずれて上書きしないよう、候補ごとに同じ無害化をかける。
     * 守る場所に当たる候補は使えないものとして次の番号へ進む
     */
    public static function findFreePath(PathResolver $resolver, DocumentParser $modx, $dirReal, $safeName)
    {
        $dotPos = strrpos($safeName, '.');
        $baseName = $dotPos === false ? $safeName : substr($safeName, 0, $dotPos);
        $extension = $dotPos === false ? '' : substr($safeName, $dotPos);
        for ($suffix = 0; $suffix <= 200; $suffix++) {
            $candidate = $suffix === 0 ? $safeName : $baseName . '(' . $suffix . ')' . $extension;
            $candidatePath = $modx->sanitizeUploadedFilename($dirReal . $candidate);
            if ($resolver->resolveNewChild($dirReal, self::baseName($candidatePath)) === null) {
                continue;
            }
            // 壊れたシンボリックリンクや同名のフォルダも「使用中」として次の番号へ進む
            if (!file_exists($candidatePath) && !is_link($candidatePath)) {
                return $candidatePath;
            }
        }
        return null;
    }

    /**
     * アップロードされたファイル($_FILES の一時ファイル)を保存先のパスへ移す。既にあるファイル(空きを確保した空のファイルなど)は置き換える。
     * 縮小(image_limit_width)とパーミッションの設定は $modx->move_uploaded_file() が行う。失敗したらシステムログに残して false を返す。
     * アップロードされたファイルでないと PHP の move_uploaded_file() が失敗するので、CLI は bytesWriter() を使う
     */
    public static function storeUploaded(DocumentParser $modx, $originalName, $tmpPath, $targetPath)
    {
        $saved = Filesystem::attempt(function () use ($modx, $tmpPath, $targetPath) {
            return $modx->move_uploaded_file($tmpPath, $targetPath);
        }, $reason);
        if (!$saved) {
            self::logSaveFailure($originalName, $targetPath, $reason);
        }

        return $saved;
    }

    /**
     * バイト列を保存先へ書く書き手(saveNewFile() の $write に渡す)。$_FILES に頼らないので、CLI からも使える(パスから書く書き手は持たない。CLI は data: を復号したバイト列から書く)。
     * image_limit_width による縮小は、アップロードの経路($modx->move_uploaded_file())だけが行う。
     * この経路にも縮小を足すなら、DocumentParser の縮小(今は private の resizeImage())を公開する入口を設けてから、書き込んだあとに通す
     */
    public static function bytesWriter(DocumentParser $modx, $bytes)
    {
        return function ($targetPath) use ($modx, $bytes) {
            if (file_put_contents($targetPath, $bytes) === false) {
                return false;
            }
            // パーミッションを変えられない環境(IIS や一部の NFS/ACL)でも、保存できていれば成功として扱う
            @chmod($targetPath, $modx->newFilePermissions());

            return true;
        };
    }

    /**
     * アップロードされたファイルの書き手(saveNewFile() の $write に渡す)。縮小とパーミッションは storeUploaded() が行う
     */
    public static function uploadedWriter(DocumentParser $modx, $originalName, $tmpPath)
    {
        return function ($targetPath) use ($modx, $originalName, $tmpPath) {
            return self::storeUploaded($modx, $originalName, $tmpPath, $targetPath);
        };
    }

    /**
     * 新しい名前のファイルとして保存してよいかを確かめる(保存はしない)。拡張子の許可・大きさの上限・名前の無害化を、ここ 1 か所で判定する。
     *
     * @param int|false $sizeLimit 大きさの上限(バイト)。false なら範囲の設定($scope->maxUploadSize())。CLI など、設定と別の上限で移す処理だけが渡す
     * @return array{safeName:string}|array{error:string} 失敗は error に、利用者に見せる文言
     */
    public static function checkNewFile(BrowserScope $scope, DocumentParser $modx, $originalName, $size, $sizeLimit = false)
    {
        // 拡張子は、実際に保存する名前（無害化した後）で確かめる
        $safeName = $modx->sanitizeFilename($originalName);
        if (!$scope->isAllowedNewName($safeName)) {
            return ['error' => lang('fb_err_extension_not_allowed')];
        }

        $maxSize = $sizeLimit === false ? $scope->maxUploadSize() : $sizeLimit;
        if ($maxSize !== null && $size > $maxSize) {
            return ['error' => lang('fb_err_too_large')];
        }

        return ['safeName' => $safeName];
    }

    /**
     * findFreePath() で見つけた空きの名前を、排他的に作成(x)して確保する。
     * 空きを確かめてから作るまでの間に同名ができても上書きしないよう、使われていたら次の候補を探す。
     *
     * @return array{path:string,handle:resource|false,reason:string}|null 空きが見つからなければ null。
     *         handle は作成したファイルのハンドル(作れなかった場合は false で、path は最後に試した候補、reason は警告の文)
     */
    public static function createFreeFile(PathResolver $resolver, DocumentParser $modx, $dirReal, $safeName)
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $targetPath = self::findFreePath($resolver, $modx, $dirReal, $safeName);
            if ($targetPath === null) {
                return null;
            }
            $handle = Filesystem::attempt(function () use ($targetPath) {
                return fopen($targetPath, 'xb');
            }, $reason);
            if ($handle !== false || (!file_exists($targetPath) && !is_link($targetPath))) {
                // 'reason' は handle が false(権限など)のときの失敗の理由。名前が既にあった場合はループで次を探す
                return ['path' => $targetPath, 'handle' => $handle, 'reason' => $reason];
            }
        }
        return null;
    }

    /**
     * フォルダーに、新しい名前でファイルを 1 つ保存する(同名があれば連番。上書きはしない)。
     * checkNewFile() で確かめ、空きの名前を排他的に確保(createFreeFile())してから、確保した空のファイルへ $write で書く。
     * 保存したら範囲のアップロードのイベントを発火する。管理操作ログへの記録は、呼ぶ側が ChangeLog::record() で行う。
     *
     * @param array $dir PathResolver::resolveDir() の戻り値
     * @param int $size 保存する中身の大きさ(バイト)
     * @param callable $write function (string $targetPath): bool。確保した(空の)ファイルのフルパスを受けて書き、書けたら true
     * @param int|false $sizeLimit checkNewFile() の $sizeLimit と同じ
     * @return array{name:string,size:int}|array{error:string} 失敗は error に、利用者に見せる文言
     */
    public static function saveNewFile(
        PathResolver $resolver,
        BrowserScope $scope,
        DocumentParser $modx,
        array $dir,
        $originalName,
        $size,
        callable $write,
        $sizeLimit = false
    ) {
        $checked = self::checkNewFile($scope, $modx, $originalName, $size, $sizeLimit);
        if (isset($checked['error'])) {
            return $checked;
        }

        $free = self::createFreeFile($resolver, $modx, $dir['real'], $checked['safeName']);
        if ($free === null) {
            return ['error' => lang('fb_err_too_many_same_name')];
        }
        $targetPath = $free['path'];
        if ($free['handle'] === false) {
            self::logSaveFailure($originalName, $targetPath, $free['reason']);
            return ['error' => lang('fb_err_save_failed')];
        }
        fclose($free['handle']);

        if (!$write($targetPath)) {
            @unlink($targetPath);
            return ['error' => lang('fb_err_save_failed')];
        }

        // プラグインがファイルを移動・削除してもよいよう、サイズはイベントの前に取る
        $saved = ['name' => self::baseName($targetPath), 'size' => filesize($targetPath)];

        // invokeEvent() の第2引数は参照渡しなので、変数に入れてから渡す
        $eventParams = [
            'filepath' => $dir['real'],
            'filename' => $saved['name'],
        ];
        $modx->invokeEvent($scope->uploadEvent(), $eventParams);

        return $saved;
    }

    private static function logSaveFailure($originalName, $targetPath, $reason)
    {
        evo()->logEvent(0, 3, 'Failed to save an uploaded file in the file browser', 'File browser', [
            'name' => $originalName,
            'to' => $targetPath,
            'reason' => $reason,
        ]);
    }

    /**
     * フォルダーを 1 つ作る。守る場所には作らず、同名があれば作らない。作ったら管理操作ログに残す。
     * $name は sanitizeFilename() を通した 1 要素の名前で渡す。
     *
     * @param array $parent PathResolver::resolveDir() の戻り値
     * @return string|null 作れたら null、だめならエラーの code(protected / already_exists / mkdir_failed)
     */
    public static function createFolder(
        PathResolver $resolver,
        BrowserScope $scope,
        DocumentParser $modx,
        array $parent,
        $name
    ) {
        $target = $resolver->resolveNewChild($parent['real'], $name);
        if ($target === null) {
            return 'protected';
        }
        if (file_exists($target)) {
            return 'already_exists';
        }

        $folderPermissions = $modx->newFolderPermissions();
        $created = Filesystem::attempt(function () use ($target, $folderPermissions) {
            return mkdir($target, $folderPermissions);
        }, $reason);
        if (!$created) {
            evo()->logEvent(0, 3, 'Failed to create a folder in the file browser', 'File browser', [
                'to' => $target,
                'reason' => $reason,
            ]);
            return 'mkdir_failed';
        }
        @chmod($target, $folderPermissions);

        ChangeLog::record($scope, 'Created Folder', [ChangeLog::relPath($parent, $name)]);

        return null;
    }

    /**
     * ルートからの相対パス(2026/11 など)のフォルダーを、無ければ 1 階層ずつ作って、そのフォルダーを返す。
     * 守る場所と同名のファイルがあるなど、使えない階層があれば失敗する
     *
     * @return array{dir:array{rel:string,real:string}}|array{error:string} error はエラーの code(not_found / protected / already_exists / mkdir_failed)
     */
    public static function ensureFolderPath(PathResolver $resolver, BrowserScope $scope, DocumentParser $modx, $relPath)
    {
        $dir = $resolver->resolveDir('');
        if ($dir === null) {
            return ['error' => 'not_found'];
        }

        foreach (explode('/', trim($relPath, '/')) as $segment) {
            $child = $resolver->resolveDir(ChangeLog::relPath($dir, $segment));
            if ($child === null) {
                $error = self::createFolder($resolver, $scope, $modx, $dir, $segment);
                $child = $error === null ? $resolver->resolveDir(ChangeLog::relPath($dir, $segment)) : null;
                if ($child === null) {
                    return ['error' => $error ?: 'mkdir_failed'];
                }
            }
            $dir = $child;
        }

        return ['dir' => $dir];
    }

    /**
     * エディタに貼り付けた画像の置き場所(種類 images のルートからの相対パス)。年/月(サイトの時刻)
     *
     * @param int $timestamp サイトの時刻(request_time() + server_offset_time)
     */
    public static function pastedFolder($timestamp)
    {
        return date('Y/m', (int) $timestamp);
    }

    /** 貼り付けた画像の名前。pasted-年月日-時分秒.拡張子(サイトの時刻) */
    public static function pastedName($timestamp, $extension)
    {
        return 'pasted-' . date('Ymd-His', (int) $timestamp) . '.' . $extension;
    }

    /**
     * 送られた名前が、貼り付けた画像の名前を保存する側が付けることを示す名前(image.png など)か。
     * 拡張子は小文字で返す。そうでなければ null(元の名前をそのまま使う)
     */
    public static function pastedPlaceholderExtension($name)
    {
        if (!preg_match('/^' . self::PASTED_PLACEHOLDER . '\.([A-Za-z0-9]+)$/', (string) $name, $m)) {
            return null;
        }

        return strtolower($m[1]);
    }
}
