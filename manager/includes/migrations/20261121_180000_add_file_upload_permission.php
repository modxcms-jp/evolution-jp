<?php

/**
 * ロールの権限 file_upload（ファイルのアップロード）を足す。
 *
 * 今の動きを変えないため、列を足すときに既存のすべてのロールを 1 にする。
 * 列が既にあるときは何もしない（利用者が外した値を戻さない）。
 */
return static function (EvoMigrationHelper $m) {
    if (!$m->addColumn('user_roles', 'file_upload', "int(1) NOT NULL DEFAULT '0' AFTER `file_manager`")) {
        return;
    }
    $m->exec(
        sprintf('UPDATE %s SET `file_upload`=1', $m->table('user_roles')),
        'UPDATE user_roles to allow file_upload'
    );
    $m->log('Added the file_upload permission to all roles');
};
