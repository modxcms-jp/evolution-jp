<?php

/**
 * 本体の「HTMLからサイトをインポート」（a=95）を廃止したので、その DB の跡を消す。
 * 同じ取り込みは、別配布のモジュール Import HTML（https://github.com/modxcms-jp/evo-import-html）で行える。
 *
 * - user_roles.import_static 列（この機能の権限）を消す。モジュールは本体の別の権限で動く
 * - 上部メニュー topmenu_tools が旧既定値のままのときだけ、import_site を外した今の既定値にする（変えた値は残す。
 *   残った import_site は、メニューの項目が無いので表示されない）
 * 2 回目は、消すものが無いので何もしない。
 */
return static function (EvoMigrationHelper $m) {
    if ($m->dropColumnIfExists('user_roles', 'import_static')) {
        $m->log('Dropped the column', ['table' => 'user_roles', 'column' => 'import_static']);
        $m->log('Removed "Import site from HTML". To keep using it, install the Import HTML module', [
            'module' => 'https://github.com/modxcms-jp/evo-import-html',
        ]);
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
