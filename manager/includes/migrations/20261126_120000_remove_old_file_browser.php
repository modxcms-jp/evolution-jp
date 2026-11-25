<?php

/**
 * 配布から外した旧ファイルブラウザ（manager/media/browser/mcpuk/）を消す。後継は manager/media/browser/evo/。
 * 上書きで更新したサイトには古いファイルが残って動き、1.3 で足したロールの権限とファイルの場所の決まりは、そこには効かない。
 *
 * 消すのは、過去の版が配布していたファイル（EvoRetiredFiles の一覧）と、空になったフォルダーだけ。
 * 利用者が足したファイルは消さず、そのフォルダーは残す。配布していた connectors/config.php は、消す connector だけが読む設定なので、
 * 書き換えていても一緒に消える。消せなければ、システムログと設定の確認の画面で知らせる。2 回目は、消すものが無いので何もしない。
 */
return static function (EvoMigrationHelper $m) {
    $m->removeRetiredFiles('mcpuk');
};
