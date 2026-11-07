<?php

/**
 * 使用エディター（which_editor）が旧 TinyMCE の名前（TinyMCE）のままなら、TinyMCE 7 の名前（TinyMCE7）にする。
 *
 * アップデーターは旧 TinyMCE のプラグインを TinyMCE 7 の @legacy_names で止めて TinyMCE 7 を入れるが、
 * which_editor は TinyMCE のまま残る。TinyMCE 7 が登録する名前は TinyMCE7 なので、合う選択肢が無く、
 * 編集画面ではエディターが「なし」になる。グローバル設定と、管理画面のユーザーごとの設定（user_settings）の両方を読み替える。
 * 旧 TinyMCE を選んでいた設定を、同じエディターの新しい名前に移すだけで、ほかの値（none やほかのエディター）には触らない。
 * TinyMCE 7 の行がすべて止めてあり、旧 TinyMCE の行が有効なサイトは、利用者が旧版を選び直したとみなして書き換えない。
 * アップデーターで TinyMCE 7 のチェックを外して旧版を残すときと、TinyMCE 7 を別の名前で手作業で入れたときは、
 * マイグレーションの時点では見分けられないので書き換わる。
 */
return static function (EvoMigrationHelper $m) {
    $oldName = 'TinyMCE';
    $newName = 'TinyMCE7';

    // 読み替える値が無ければ何もしない
    $current = (string)$m->setting('which_editor', '');
    $globalMatches = strcasecmp($current, $oldName) === 0;
    $hasUserSettings = $m->tableExists('user_settings');
    $userMatches = $hasUserSettings && $m->rows(
        sprintf(
            "SELECT 1 FROM %s WHERE `setting_name`='which_editor' AND LOWER(`setting_value`)='%s' LIMIT 1",
            $m->table('user_settings'),
            db()->escape(strtolower($oldName))
        ),
        'SELECT which_editor of user_settings'
    );
    if (!$globalMatches && !$userMatches) {
        return;
    }

    if ($m->tableExists('site_plugins')) {
        // assets/plugins/tinymce7/tinymce.install_base.tpl の名前と @legacy_names と同じ（小文字で比べる）
        $newPlugin = 'tinymce 7 rich text editor';
        $legacyPlugins = ['tinymce rich text editor', 'tinymce'];
        $plugins = $m->rows(
            sprintf(
                "SELECT `name`, `disabled` FROM %s WHERE LOWER(`name`) IN ('%s')",
                $m->table('site_plugins'),
                implode("','", db()->escape(array_merge([$newPlugin], $legacyPlugins)))
            ),
            'SELECT the TinyMCE plugins'
        );
        // 名前ごとに、行があるか（キーがあるか）と、有効な行が 1 つでもあるか
        $enabled = [];
        foreach ($plugins as $plugin) {
            $name = strtolower($plugin['name']);
            $enabled[$name] = !empty($enabled[$name]) || (int)$plugin['disabled'] === 0;
        }
        $legacyEnabled = false;
        foreach ($legacyPlugins as $legacyPlugin) {
            $legacyEnabled = $legacyEnabled || !empty($enabled[$legacyPlugin]);
        }
        if (isset($enabled[$newPlugin]) && !$enabled[$newPlugin] && $legacyEnabled) {
            $m->log('which_editor was not changed: the old TinyMCE plugin is enabled instead of TinyMCE 7', [
                'setting' => 'which_editor',
            ], 2);
            return;
        }
    }

    if ($globalMatches) {
        $m->saveSetting('which_editor', $newName);
        $m->log('Updated the setting', ['setting' => 'which_editor', 'old' => $current, 'new' => $newName]);
    }

    if (!$userMatches) {
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
