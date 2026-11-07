<?php

/**
 * 以前の版の一時ロックの行で、blocked を 0 にする（管理ユーザー・Web ユーザー）。
 *
 * 以前の版は、ログインの失敗回数が failed_login_attempts に達すると、blockeduntil に加えて blocked も 1 にしていた。
 * 今の版は一時ロックを blockeduntil と失敗回数だけで表し、期限の過ぎた一時ロックを外すときに blocked には触らない
 * （blocked は管理者が付けたブロック）。以前の形の行の blocked が残ると、期限が過ぎてもログインできないので、ここで 1 回だけ外す。
 * 対象は blocked=1・blockeduntil が 0 でない・失敗回数が failed_login_attempts 以上の行で、isTemporaryLoginLock() と同じ見分け方。
 * blockeduntil と失敗回数はそのまま残すので、期限内の一時ロックは期限まで続く。
 * 管理者が付けたブロックがたまたま同じ形のときは、見分けられないので外れる。
 * failed_login_attempts はグローバル設定の値を使う（ユーザーごとに変える画面は無い）。
 */
return static function (EvoMigrationHelper $m) {
    // 設定の行はインストールで必ず入る。無いときは manager/includes/default.config.php の既定値
    $limit = (int)$m->setting('failed_login_attempts', 5);

    foreach (['user_attributes', 'web_user_attributes'] as $table) {
        if (!$m->tableExists($table)) {
            continue;
        }
        $m->exec(
            sprintf(
                'UPDATE %s SET `blocked`=0 WHERE `blocked`=1 AND `blockeduntil`<>0 AND `failedlogincount`>=%d',
                $m->table($table),
                $limit
            )
        );
        $users = (int)db()->getAffectedRows();
        if ($users) {
            $m->log('Unblocked users locked by failed logins in the old way', ['table' => $table, 'users' => $users]);
        }
    }
};
