<?php

/**
 * 本体の「HTMLからサイトをインポート」（a=95）を、同梱のモジュール Import HTML へ移したので、その DB の跡を消す。
 *
 * - user_roles.import_static 列（この機能の権限）を消す。モジュールは文書の作成・保存の権限で動く
 * - 上部メニュー topmenu_tools が旧既定値のままのときだけ、import_site を外した今の既定値にする（変えた値は残す。
 *   残った import_site は、メニューの項目が無いので表示されない）
 * 2 回目は、消すものが無いので何もしない。
 */
return static function (EvoMigrationHelper $m) {
    if ($m->dropColumnIfExists('user_roles', 'import_static')) {
        $m->log('Dropped the column', ['table' => 'user_roles', 'column' => 'import_static']);
    }

    if (!$m->tableExists('system_settings')) {
        return;
    }
    $old = 'bk_manager,export_site,import_site,edit_settings';
    $new = 'bk_manager,export_site,edit_settings';
    if ($m->setting('topmenu_tools') === $old) {
        $m->saveSetting('topmenu_tools', $new);
        $m->log('Updated the setting', ['setting' => 'topmenu_tools', 'old' => $old, 'new' => $new]);
    }
};
