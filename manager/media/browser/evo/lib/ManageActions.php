<?php

/**
 * 管理モード(メニューの「ファイル管理」の範囲)だけで受ける操作。ManageScope::actions() だけが登録するので、
 * ピッカーの範囲では action の表に載らず 400 になる(操作の中で範囲は確かめない)。
 * api.php から読まれ、api.php の fbSendJson() / fbError() / fbAttempt() / fbNotFoundMessage() を使う。
 *
 * GET  readText (folder, file)                          -> {name, content, hash, writable, readOnly, encoding}
 * POST saveText (folder, target, content, hash)         -> {name, hash}
 * POST newfile  (folder, name)                          -> {name}
 *
 * テキストとして扱うのは、拡張子が BrowserConfig::TEXT_EXTENSIONS にあり、大きさが TEXT_MAX_BYTES 以下で、NUL を含まないファイルだけ。
 * UTF-8 でない中身(Shift_JIS・EUC-JP)は UTF-8 に変換して表示するが、保存はさせない(readOnly)。変換した UTF-8 で書き戻すと、
 * ファイルの文字コードが黙って変わるため。保存のときは、開いたときの中身の sha1(hash)を送り、今のファイルと違えば 409 modified で断る
 * (FTP などで同時に書き換えられたものを上書きしないため)。
 * エラーコード: invalid_name / not_found / not_text / too_large / read_failed / not_editable / read_only / modified / invalid_content /
 * save_failed / extension_not_allowed / protected / already_exists / create_failed
 */
class ManageActions
{
    /** テキストを UTF-8 以外で持つファイルの、文字コードの判定の候補(旧ファイル管理の表示と同じ) */
    const LEGACY_ENCODINGS = ['SJIS-win', 'SJIS', 'EUCJP-win', 'EUC-JP'];

    /**
     * テキストのファイルを開く。応答は JSON(HTML には混ぜない)で、MIME の推測もさせない
     */
    public static function readText(PathResolver $resolver, ManageScope $scope, DocumentParser $modx)
    {
        $dir = self::dirOrFail($resolver);
        $name = self::nameOrFail(getv('file', ''));
        $realPath = self::textFileOrFail($resolver, $dir, $name);

        $buffer = self::readOrFail($realPath);
        $content = $buffer;
        $encoding = 'UTF-8';
        if (!mb_check_encoding($buffer, 'UTF-8')) {
            // UTF-8 でない中身は、文字コードを判別できたものだけを変換して表示する(保存はさせない)
            $encoding = mb_detect_encoding($buffer, self::LEGACY_ENCODINGS, true);
            if ($encoding === false) {
                fbError('not_text', lang('fb_err_not_text'), 400);
            }
            $content = mb_convert_encoding($buffer, 'UTF-8', $encoding);
        }

        $writable = is_writable($realPath);
        header('X-Content-Type-Options: nosniff');
        fbSendJson([
            'name' => $name,
            'content' => $content,
            'hash' => sha1($buffer),
            'writable' => $writable,
            'readOnly' => !$writable || $encoding !== 'UTF-8',
            'encoding' => $encoding,
        ]);
    }

    /**
     * テキストのファイルを保存する。既にあるファイルの置き換えだけ(新しいファイルは newfile で作る)。リンクは断る
     */
    public static function saveText(PathResolver $resolver, ManageScope $scope, DocumentParser $modx)
    {
        $dir = self::dirOrFail($resolver);
        $name = self::nameOrFail(postv('target', ''));
        $realPath = self::textFileOrFail($resolver, $dir, $name);
        // 一覧に見えているファイルそのものだけを書き換える(リンクの先は、名前・拡張子・置き場所を確かめていない)
        if (is_link($dir['real'] . $name)) {
            fbError('not_editable', lang('fb_err_text_link'), 400);
        }

        $content = postv('content', '');
        if (!is_string($content) || !mb_check_encoding($content, 'UTF-8') || strpos($content, "\0") !== false) {
            fbError('invalid_content', lang('fb_err_not_text'), 400);
        }

        $current = self::readOrFail($realPath);
        // 開いたとき UTF-8 だったものだけ保存できる(そうでないものは、書き戻すと文字コードが黙って変わる)
        if (!mb_check_encoding($current, 'UTF-8')) {
            fbError('read_only', lang('fb_err_text_encoding'), 400);
        }
        if (!hash_equals(sha1($current), (string) postv('hash', ''))) {
            fbError('modified', lang('fb_err_text_modified'), 409);
        }

        // 改行は、保存する前のファイルが CRLF なら CRLF に、そうでなければ LF にそろえる(フォームの送信などで混ざった改行を整える)
        $content = preg_replace('/\r\n|\r|\n/', "\n", $content);
        if (strpos($current, "\r\n") !== false) {
            $content = str_replace("\n", "\r\n", $content);
        }
        if (strlen($content) > BrowserConfig::TEXT_MAX_BYTES) {
            fbError('too_large', self::tooLargeMessage(), 413);
        }

        // 既にあるファイルへ書くので、パーミッションは保たれる
        $written = fbAttempt(function () use ($realPath, $content) {
            return file_put_contents($realPath, $content, LOCK_EX);
        }, $reason);
        if ($written === false || $written !== strlen($content)) {
            $modx->logEvent(0, 3, 'Failed to save a text file in the file browser', 'File browser', [
                'to' => $realPath,
                'reason' => $reason,
            ]);
            fbError('save_failed', lang('fb_err_save_failed'), 500);
        }

        ChangeLog::record($scope, 'Modified File', [ChangeLog::relPath($dir, $name)]);
        fbSendJson(['name' => $name, 'hash' => sha1($content)]);
    }

    /**
     * 空のテキストファイルを作る。拡張子は新しい名前の許可リストで確かめる。同じ名前があれば 409(空の中身で上書きしない)
     */
    public static function newfile(PathResolver $resolver, ManageScope $scope, DocumentParser $modx)
    {
        $dir = self::dirOrFail($resolver);
        $name = $modx->sanitizeFilename(postv('name', ''), false);
        if ($name === '') {
            fbError('invalid_name', lang('fb_err_invalid_name'), 400);
        }
        if (!$scope->isAllowedNewName($name)) {
            fbError('extension_not_allowed', lang('fb_err_extension_not_allowed'), 400);
        }

        $target = $resolver->resolveNewChild($dir['real'], $name);
        if ($target === null) {
            fbError('protected', lang('fb_err_protected'), 403);
        }
        // 同じ名前のファイル・フォルダー(リンク切れのリンクも含む)があれば、作らない
        if (file_exists($target) || is_link($target)) {
            fbError('already_exists', lang('fb_err_already_exists'), 409);
        }

        // x: 同時に同じ名前が作られても上書きしない。既にあるリンクもたどらない
        $handle = fbAttempt(function () use ($target) {
            return fopen($target, 'xb');
        }, $reason);
        if ($handle === false) {
            if (file_exists($target) || is_link($target)) {
                fbError('already_exists', lang('fb_err_already_exists'), 409);
            }
            $modx->logEvent(0, 3, 'Failed to create a file in the file browser', 'File browser', [
                'to' => $target,
                'reason' => $reason,
            ]);
            fbError('create_failed', lang('fb_err_create_failed'), 500);
        }
        fclose($handle);
        // パーミッションを変えられない環境でも、作れていれば成功として扱う
        @chmod($target, $modx->newFilePermissions());

        ChangeLog::record($scope, 'Created File', [ChangeLog::relPath($dir, $name)]);
        fbSendJson(['name' => $name]);
    }

    /**
     * folder の解決。見つからない・ルートの外・守る場所なら 404
     */
    private static function dirOrFail(PathResolver $resolver)
    {
        $folder = anyv('folder', '');
        $dir = $resolver->resolveDir($folder);
        if ($dir === null) {
            fbError('not_found', fbNotFoundMessage($folder, lang('fb_err_folder_not_found')), 404);
        }

        return $dir;
    }

    /**
     * 既にあるファイルを指す名前(パスの 1 要素)。使えない名前なら 400
     */
    private static function nameOrFail($value)
    {
        $name = is_string($value) ? PathResolver::sanitizeSegment($value) : '';
        if ($name === '') {
            fbError('invalid_name', lang('fb_err_invalid_name'), 400);
        }

        return $name;
    }

    /**
     * テキストとして扱えるファイルの実パス。ルートの中にあって守る場所でない(resolveFile())、拡張子が TEXT_EXTENSIONS にある、
     * 大きさが TEXT_MAX_BYTES 以下であること。どれかを満たさなければ、その理由で失敗にする
     */
    private static function textFileOrFail(PathResolver $resolver, array $dir, $name)
    {
        $realPath = $resolver->resolveFile($dir['real'], $name);
        if ($realPath === null) {
            fbError('not_found', fbNotFoundMessage($name, lang('fb_err_target_not_found')), 404);
        }
        if (!BrowserConfig::isTextFilename($name)) {
            fbError('not_text', lang('fb_err_not_text'), 400);
        }
        $size = @filesize($realPath);
        if ($size === false) {
            fbError('read_failed', lang('fb_err_read_failed'), 500);
        }
        if ($size > BrowserConfig::TEXT_MAX_BYTES) {
            fbError('too_large', self::tooLargeMessage(), 413);
        }

        return $realPath;
    }

    /**
     * ファイルの中身を読む。確かめた後に大きくなっていたときのため、上限を 1 バイト超えて読んで、超えていれば失敗にする。
     * NUL を含むものはバイナリとみなして失敗にする
     */
    private static function readOrFail($realPath)
    {
        $buffer = fbAttempt(function () use ($realPath) {
            return file_get_contents($realPath, false, null, 0, BrowserConfig::TEXT_MAX_BYTES + 1);
        });
        if ($buffer === false) {
            fbError('read_failed', lang('fb_err_read_failed'), 500);
        }
        if (strlen($buffer) > BrowserConfig::TEXT_MAX_BYTES) {
            fbError('too_large', self::tooLargeMessage(), 413);
        }
        if (strpos($buffer, "\0") !== false) {
            fbError('not_text', lang('fb_err_not_text'), 400);
        }

        return $buffer;
    }

    private static function tooLargeMessage()
    {
        return sprintf(lang('fb_err_text_too_large'), (int) (BrowserConfig::TEXT_MAX_BYTES / 1048576));
    }
}
