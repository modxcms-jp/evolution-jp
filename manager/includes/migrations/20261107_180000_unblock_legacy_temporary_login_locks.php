<?php

/**
 * 以前の版の一時ロックの行で、blocked を 0 にする（管理ユーザー・Web ユーザー）。
 *
 * 以前の版は、ログインの失敗回数が failed_login_attempts に達すると、blockeduntil に加えて blocked も 1 にしていた。
 * - 管理ユーザー: 1.2.4J と、main の 980c0baf1 から 5c34588c0 の前まで（manager/processors/login.processor.functions.php の failedLogin()）
 * - Web ユーザー: 1.2.3J 以前（assets/snippets/weblogin/weblogin.processor.inc.php。bc0284bbb で blocked を書かなくなった）
 * 今の版は一時ロックを blockeduntil と失敗回数だけで表し、期限の過ぎた一時ロックを外すときに blocked には触らない
 * （blocked は管理者が付けたブロック）。以前の形の行の blocked が残ると、期限が過ぎてもログインできないので、ここで 1 回だけ外す。
 * 対象の条件は legacyTemporaryLoginLockSqlCondition()（manager/includes/helpers.php）で、マイグレーションの前のログインと共有する。
 * blockeduntil と失敗回数はそのまま残すので、期限内の一時ロックは期限まで続く。
 * 管理者が付けたブロックがたまたま同じ形のときは、見分けられないので外れる。
 * failed_login_attempts はグローバル設定の値を使う（ユーザーごとに変える画面は無い）。
 * 外した行は、表ごとに internalKey の一覧をシステムログに残す。
 *
 * ログインの処理（legacyLoginLockMigrationId()）がこの ID を見て、記録が無いあいだだけ同じ条件で本人の行を外す。
 * 記録（migrations の表の行）を手で消して、日を置いて流し直さない。その間に管理者が同じ形のブロックを付けた行も外れるため。
 */
return static function (EvoMigrationHelper $m) {
    $condition = legacyTemporaryLoginLockSqlCondition($m->setting('failed_login_attempts', ''));

    foreach (['user_attributes', 'web_user_attributes'] as $table) {
        if (!$m->tableExists($table)) {
            continue;
        }
        $rows = $m->rows(
            sprintf('SELECT `internalKey` FROM %s WHERE %s', $m->table($table), $condition)
        );
        if (!$rows) {
            continue;
        }
        $m->exec(
            sprintf('UPDATE %s SET `blocked`=0 WHERE %s', $m->table($table), $condition)
        );
        $m->log('Unblocked users locked by failed logins in the old way', [
            'table' => $table,
            'internalKeys' => array_map('intval', array_column($rows, 'internalKey')),
        ]);
    }
};
