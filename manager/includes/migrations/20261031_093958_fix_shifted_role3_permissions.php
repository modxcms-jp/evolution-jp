<?php

/**
 * 投稿担当者（ロール 3）の権限のずれを直す。
 *
 * 1.0.23J から、install/sql/default_settings_custom.sql の投稿担当者の行は、列に move_document を足したとき
 * 値を末尾に足したため、move_document より後ろの値が 1 つずつずれていた（settings が付き、delete_document が外れるなど）。
 * 18 の権限がすべてずれた値のままの行だけを、正しい値にする。1 つでも違えば利用者が変えたものとみなして触らない。
 */
return static function (EvoMigrationHelper $m) {
    if (!$m->tableExists('user_roles')) {
        return;
    }

    // 列 => [ずれた値, 正しい値]
    $permissions = [
        'delete_document' => [0, 1],
        'empty_trash' => [1, 0],
        'help' => [0, 1],
        'settings' => [1, 0],
        'credits' => [0, 1],
        'delete_snippet' => [1, 0],
        'edit_chunk' => [0, 1],
        'new_chunk' => [1, 0],
        'save_chunk' => [0, 1],
        'delete_chunk' => [1, 0],
        'file_manager' => [0, 1],
        'delete_user' => [1, 0],
        'save_password' => [0, 1],
        'save_module' => [1, 0],
        'exec_module' => [0, 1],
        'web_access_permissions' => [1, 0],
        'view_unpublished' => [0, 1],
        'export_static' => [1, 0],
    ];

    $set = [];
    $where = ['`id`=3'];
    foreach ($permissions as $column => [$shifted, $fixed]) {
        $set[] = sprintf('`%s`=%d', $column, $fixed);
        $where[] = sprintf('`%s`=%d', $column, $shifted);
    }
    $m->exec(
        sprintf('UPDATE %s SET %s WHERE %s', $m->table('user_roles'), implode(', ', $set), implode(' AND ', $where)),
        'UPDATE the shifted permissions of role 3'
    );
    $fixedRows = (int)db()->getAffectedRows();
    if ($fixedRows) {
        $m->log('Fixed the shifted permissions of role 3', ['columns' => array_keys($permissions)]);
    }
};
