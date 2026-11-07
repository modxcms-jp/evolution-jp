<?php

/**
 * 使用エディター（which_editor）が旧 TinyMCE の名前（TinyMCE）のままなら、TinyMCE 7 の名前（TinyMCE7）にする。
 *
 * アップデーターは旧 TinyMCE のプラグインを TinyMCE 7 の @legacy_names で止めて TinyMCE 7 を入れるが、
 * which_editor は TinyMCE のまま残る。TinyMCE 7 が登録する名前は TinyMCE7 なので、合う選択肢が無く、
 * 編集画面ではエディターが「なし」になる。グローバル設定と、管理画面のユーザーごとの設定（user_settings）の両方を読み替える。
 * 旧 TinyMCE を選んでいた設定を、同じエディターの新しい名前に移すだけで、ほかの値（none やほかのエディター）には触らない。
 * TinyMCE 7 の行がすべて止めてあり、旧 TinyMCE の行が有効なサイトは、利用者が旧版を選び直したとみなして書き換えない。
 * アップデーターで TinyMCE 7 のチェックを外して旧版を残すときは、マイグレーションの時点では分からないので書き換わる。
 */
return static function (EvoMigrationHelper $m) {
    $oldName = 'TinyMCE';
    $newName = 'TinyMCE7';

    if ($m->tableExists('site_plugins')) {
        // assets/plugins/tinymce7/tinymce.install_base.tpl の名前と @legacy_names と同じ
        $legacyNames = ['TinyMCE Rich Text Editor', 'TinyMCE'];
        $plugins = $m->rows(
            sprintf(
                "SELECT `name`, MIN(`disabled`) AS `disabled` FROM %s WHERE `name` IN ('%s') GROUP BY `name`",
                $m->table('site_plugins'),
                implode("','", db()->escape(array_merge(['TinyMCE 7 Rich Text Editor'], $legacyNames)))
            ),
            'SELECT the TinyMCE plugins'
        );
        $enabled = [];
        $exists = [];
        foreach ($plugins as $plugin) {
            $exists[$plugin['name']] = true;
            $enabled[$plugin['name']] = (int)$plugin['disabled'] === 0;
        }
        $legacyEnabled = false;
        foreach ($legacyNames as $legacyName) {
            $legacyEnabled = $legacyEnabled || !empty($enabled[$legacyName]);
        }
        if (isset($exists['TinyMCE 7 Rich Text Editor']) && empty($enabled['TinyMCE 7 Rich Text Editor']) && $legacyEnabled) {
            $m->log('which_editor was not changed: the old TinyMCE plugin is enabled instead of TinyMCE 7', [
                'setting' => 'which_editor',
            ], 2);
            return;
        }
    }

    $current = (string)$m->setting('which_editor', '');
    if (strcasecmp($current, $oldName) === 0) {
        $m->saveSetting('which_editor', $newName);
        $m->log('Updated the setting', ['setting' => 'which_editor', 'old' => $current, 'new' => $newName]);
    }

    if (!$m->tableExists('user_settings')) {
        return;
    }
    $m->exec(
        sprintf(
            "UPDATE %s SET `setting_value`='%s' WHERE `setting_name`='which_editor' AND LOWER(`setting_value`)='%s'",
            $m->table('user_settings'),
            db()->escape($newName),
            db()->escape(strtolower($oldName))
        ),
        'UPDATE which_editor of user_settings'
    );
    $updatedUsers = (int)db()->getAffectedRows();
    if ($updatedUsers) {
        $m->log('Updated which_editor of user settings', ['old' => $oldName, 'new' => $newName, 'users' => $updatedUsers]);
    }
};
