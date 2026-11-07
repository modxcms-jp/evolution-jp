<?php

/**
 * 使用エディター（which_editor）が旧 TinyMCE の名前（TinyMCE）のままなら、TinyMCE 7 の名前（TinyMCE7）にする。
 *
 * アップデーターは旧 TinyMCE のプラグインを TinyMCE 7 の @legacy_names で止めて TinyMCE 7 を入れるが、
 * which_editor は TinyMCE のまま残る。TinyMCE 7 が登録する名前は TinyMCE7 なので、合う選択肢が無く、
 * 編集画面ではエディターが「なし」になる。グローバル設定と、管理画面のユーザーごとの設定（user_settings）の両方を読み替える。
 * 旧 TinyMCE を選んでいた設定を、同じエディターの新しい名前に移すだけで、ほかの値（none やほかのエディター）には触らない。
 */
return static function (EvoMigrationHelper $m) {
    $oldName = 'TinyMCE';
    $newName = 'TinyMCE7';

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
