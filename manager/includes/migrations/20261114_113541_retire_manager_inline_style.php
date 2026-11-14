<?php

/**
 * グローバル設定の「追加のコード」（manager_inline_style）を廃止したので、その設定を消す。
 * 本文の文字の大きさはテーマの --font-size-base で決める。この設定の既定値（body の font-size を 0.75em にする <style>）は、
 * テーマの指定を後ろから上書きしていた。値が既定かどうかに関係なく消す（利用者の判断）。
 * ユーザーごとの設定（個人設定・ユーザー編集で決めた値）も消す。
 * 2 回目は、消すものが無いので何もしない。
 */
return static function (EvoMigrationHelper $m) {
    $name = 'manager_inline_style';
    if ($m->tableExists('system_settings') && $m->deleteSetting($name)) {
        $m->log('Removed an obsolete setting', ['setting' => $name]);
    }
    foreach (['user_settings', 'web_user_settings'] as $table) {
        if (!$m->tableExists($table)) {
            continue;
        }
        $where = sprintf("setting_name='%s'", db()->escape($name));
        if (!$m->rows(sprintf('SELECT 1 FROM %s WHERE %s LIMIT 1', $m->table($table), $where))) {
            continue;
        }
        $m->exec(sprintf('DELETE FROM %s WHERE %s', $m->table($table), $where));
        $m->log('Removed an obsolete user setting', ['table' => $table, 'setting' => $name]);
    }
};
