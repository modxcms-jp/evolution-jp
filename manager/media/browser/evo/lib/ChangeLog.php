<?php

/**
 * ファイルブラウザの成功した変更の記録(範囲によらず共通)。
 * 管理操作ログ(manager_log。画面は a=13)に 1 件ごとに 1 行、システムログ(evo()->logEvent())に 1 要求の 1 操作につき 1 行を書く。
 * 範囲の印は BrowserScope::logLabel()(管理操作ログの message の先頭)と name()(システムログの scope)の値を書くだけで、範囲は見分けない。
 * 記録するのは成功した変更だけ。一覧・プレビュー・テキストの表示は記録しない(見るたびに増え、変更の追跡には要らないため)。
 * 失敗の記録は、各操作が evo()->logEvent(0, 3, ...) で行う。
 */
class ChangeLog
{
    /** 1 回の要求で管理操作ログに書く行数の上限。超えた分は書かず、最後に「ほか N 件(trace_id=...)」の 1 行を足す */
    const MAX_ROWS = 50;
    /** manager_log.itemname の長さ(文字数)。超えるときは先頭を省いて末尾を残す */
    const ITEMNAME_MAX = 255;

    /** @var int この要求で管理操作ログに書いた行数 */
    private static $rows = 0;

    /**
     * 成功した変更を記録する。
     *
     * @param BrowserScope $scope 記録する範囲(印を書くだけ)
     * @param string $operation 操作の名前(管理操作ログの message。Uploaded File・Created Folder・Created File・Renamed・Deleted File・
     *                          Deleted Folder・Moved・Copied・Saved Image・Modified File・Unzipped)
     * @param array $paths ルートからの相対パス。1 件ごとに 1 行になる。移動・名前の変更・複製は [元, 先] の配列で渡し、"元 → 先" と書く。
     *                     パスの頭には BrowserScope::logRoot() を付ける
     */
    public static function record(BrowserScope $scope, $operation, array $paths)
    {
        $root = $scope->logRoot();
        $lines = [];
        foreach ($paths as $path) {
            $lines[] = is_array($path)
                ? $root . $path[0] . ' → ' . $root . $path[1]
                : $root . $path;
        }
        $paths = $lines;
        if (!$paths) {
            return;
        }

        self::writeManagerLog($scope, (string) $operation, $paths);

        // システムログは全件のパスを 1 行に入れる(管理操作ログの 51 件目より後も、trace_id で引けばここに残っている)
        evo()->logEvent(0, 1, 'File changed in the file browser', 'File browser', [
            'scope' => $scope->name(),
            'operation' => (string) $operation,
            'paths' => $paths,
        ]);
    }

    /**
     * 記録に渡す、ルートからの相対パス(フォルダの相対パス + 名前)
     *
     * @param array $dir PathResolver::resolveDir() の戻り値
     * @param string $name フォルダの中の名前(サブフォルダを含む相対パスでもよい)
     * @return string
     */
    public static function relPath(array $dir, $name)
    {
        return ($dir['rel'] !== '' ? $dir['rel'] . '/' : '') . $name;
    }

    /**
     * 管理操作ログへ、1 件ごとに 1 行を書く(1 回の要求で MAX_ROWS 行まで)。
     * 書き込めるのは、管理画面にログインしている利用者だけ(ウェブユーザーのセッションでピッカーを使うときは、
     * 管理操作ログには書かない。manager_log の internalKey は管理ユーザーの ID のため)。
     * itemname と message は logHandler が db()->escape() してから書くので、ここでは前もってエスケープしない
     */
    private static function writeManagerLog(BrowserScope $scope, $operation, array $paths)
    {
        if (!sessionv('mgrValidated')) {
            return;
        }

        include_once MODX_CORE_PATH . 'log.class.inc.php';
        $log = new logHandler();
        $message = $scope->logLabel() . $operation;

        $omitted = 0;
        foreach ($paths as $path) {
            if (self::$rows >= self::MAX_ROWS) {
                $omitted++;
                continue;
            }
            self::$rows++;
            $log->initAndWriteLog($message, '', '', 31, 'x', self::itemName($path));
        }

        if ($omitted > 0) {
            $log->initAndWriteLog(
                $message,
                '',
                '',
                31,
                'x',
                sprintf(lang('fb_log_omitted'), $omitted, EvoLogger::traceId())
            );
        }
    }

    /**
     * itemname に入れる値。UTF-8 でない名前(FTP で置いたものなど)は、そのままでは管理ログ(utf8mb4)に書けないため置き換える。
     * 長いものは、名前の側(末尾)を残して先頭を省く
     */
    private static function itemName($path)
    {
        $path = mb_scrub($path, 'UTF-8');
        if (mb_strlen($path, 'UTF-8') > self::ITEMNAME_MAX) {
            $path = mb_substr($path, -(self::ITEMNAME_MAX - 1), null, 'UTF-8');
            $path = '…' . $path;
        }

        return $path;
    }
}
