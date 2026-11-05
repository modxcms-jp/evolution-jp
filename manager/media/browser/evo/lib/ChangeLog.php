<?php

/**
 * ファイルブラウザの成功した変更の記録(範囲によらず共通)。
 * 管理操作ログ(manager_log。画面は a=13)に 1 件ごとに 1 行、システムログ(evo()->logEvent())に 1 操作につき 1 行を書く。
 * 範囲の印は BrowserScope::logLabel()(管理操作ログの message の先頭)と name()(システムログの scope)の値を書くだけで、範囲は見分けない。
 * 記録するのは成功した変更だけ(一覧・プレビュー・テキストの表示は見るたびに増え、変更の追跡に要らないため)。失敗は各操作が logEvent() で記録する。
 */
class ChangeLog
{
    /** 1 回の要求で管理操作ログに書く行数の上限。超えた分は最後に「ほか N 件(trace_id=...)」の 1 行にまとめる */
    const MAX_ROWS = 50;
    /** manager_log.itemname の長さ(文字数)。超えるときは先頭を省く */
    const ITEMNAME_MAX = 255;

    private static $rows = 0;

    /**
     * 成功した変更を記録する。$paths はルートからの相対パス(1 件ごとに 1 行)。移動・名前の変更・複製は [元, 先] の配列で渡し、"元 → 先" と書く。
     * パスの頭には BrowserScope::logRoot() を付ける
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

    public static function relPath(array $dir, $name)
    {
        return ($dir['rel'] !== '' ? $dir['rel'] . '/' : '') . $name;
    }

    /**
     * 管理操作ログへ、1 件ごとに 1 行を書く(1 回の要求で MAX_ROWS 行まで)。管理画面にログインしている利用者だけが書ける
     * (manager_log の internalKey は管理ユーザーの ID のため)。itemname と message は logHandler が escape するので、前もってエスケープしない
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
     * itemname に入れる値。UTF-8 でない名前(FTP で置いたものなど)は管理ログ(utf8mb4)に書けないため置き換え、長いものは先頭を省く
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
