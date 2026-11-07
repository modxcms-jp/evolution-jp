<?php

/**
 * 管理画面のメッセージ機能（Issue #1069）を廃止したので、その DB の跡を消す。
 *
 * - user_messages 表と、user_roles.messages 列（メッセージ機能の権限）を消す
 * - 機能の設定 mail_check_timeperiod・number_of_messages・pm2email を消す
 * - 上部メニュー topmenu_user が旧既定値のままのときだけ、messages を外した今の既定値にする（変えた値は残す）
 * 2 回目は、消すものが無いので何もしない。
 */
return static function (EvoMigrationHelper $m) {
    if ($m->dropTableIfExists('user_messages')) {
        $m->log('Dropped the table', ['table' => 'user_messages']);
    }
    if ($m->dropColumnIfExists('user_roles', 'messages')) {
        $m->log('Dropped the column', ['table' => 'user_roles', 'column' => 'messages']);
    }

    if (!$m->tableExists('system_settings')) {
        return;
    }
    foreach (['mail_check_timeperiod', 'number_of_messages', 'pm2email'] as $name) {
        if ($m->deleteSetting($name)) {
            $m->log('Removed an obsolete setting', ['setting' => $name]);
        }
    }

    // 古い既定のままのときだけ今の既定に（並べ替えた値は残す）
    $old = 'change_user_pf,change_password,messages';
    $new = 'change_user_pf,change_password';
    if ($m->setting('topmenu_user') === $old) {
        $m->saveSetting('topmenu_user', $new);
        $m->log('Updated the setting', ['setting' => 'topmenu_user', 'old' => $old, 'new' => $new]);
    }
};
