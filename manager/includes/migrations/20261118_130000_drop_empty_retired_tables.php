<?php

/**
 * 廃止した機能の表のうち、空のものを消す。
 * - event_log: システムイベントログ（今はファイルのシステムログに書く）
 * - keyword_xref・site_content_metatags・site_keywords・site_metatags: メタタグ・キーワード管理
 *
 * 新規インストールでは作らず、古い版から上げたサイトにだけ残っている。今のコードは読まない。
 * 1 行でも入っている表は、利用者のデータなので消さずに残し、残した表と件数をシステムログに書く。
 */
return static function (EvoMigrationHelper $m) {
    $tables = ['event_log', 'keyword_xref', 'site_content_metatags', 'site_keywords', 'site_metatags'];
    $dropped = [];
    $kept = [];
    foreach ($tables as $table) {
        if (!$m->tableExists($table)) {
            continue;
        }
        $count = $m->rows(sprintf('SELECT COUNT(*) AS n FROM %s', $m->table($table)));
        $rows = (int)$count[0]['n'];
        if ($rows > 0) {
            $kept[$table] = $rows;
            continue;
        }
        $m->dropTableIfExists($table);
        $dropped[] = $table;
    }
    if ($dropped) {
        $m->log('Dropped empty tables of retired features', ['tables' => $dropped]);
    }
    if ($kept) {
        $m->log('Kept tables of retired features because they have rows', ['rows' => $kept]);
    }
};
