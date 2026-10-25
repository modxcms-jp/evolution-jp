<?php
/**
 * baseline: ランナーを入れる前の DB（1.0.0J 以降のどの版でも）を、今の版を新規インストールした形にそろえる。
 *
 * 前半（スキーマの部）。最終形は install/sql/create_tables.sql で、この中の定義はそれと同じにする
 * （create_tables.sql を変えたら、同じ変更のマイグレーションも足す）。
 *
 * - 版の条件は置かない。条件はすべて DB の状態（列があるか、最終形と同じか）で決める。
 * - 表を作るのは create_tables.sql だけ。ここは既にある表を変えるだけで、表が無ければ何もしない。
 * - 最終形の DB に流しても何も変えない。UPDATE も、変える行があるときだけ流す。
 *
 * 後半（データの部）は M4 で、この関数の末尾の印の位置に足す。
 */
return function (EvoMigrationHelper $m) {
    $prefix = db()->config['table_prefix'];

    // 1. 長さの確認（何も変えない）。縮める列に収まらない値があれば、ここで止める。
    // 黙って切ると URL（alias）が変わり、飛ばすと最終形にならないまま記録されるため
    $limits = [
        ['site_content', 'alias', 245],
        ['documentgroup_names', 'name', 191],
        ['membergroup_names', 'name', 191],
        ['webgroup_names', 'name', 191],
    ];
    $violations = [];
    foreach ($limits as [$table, $column, $limit]) {
        if (!$m->columnExists($table, $column)) {
            continue;
        }
        $rows = $m->rows(sprintf(
            'SELECT `id`, CHAR_LENGTH(`%1$s`) AS `len` FROM %2$s WHERE CHAR_LENGTH(`%1$s`) > %3$d ORDER BY `id`',
            $column,
            $m->table($table),
            (int)$limit
        ), "SELECT rows of {$table}.{$column} longer than {$limit}");
        foreach ($rows as $row) {
            $violations[] = [
                'table' => $prefix . $table,
                'column' => $column,
                'limit' => $limit,
                'id' => (int)$row['id'],
                'length' => (int)$row['len'],
            ];
        }
    }
    if ($violations) {
        $lines = [];
        foreach ($violations as $v) {
            $lines[] = sprintf(
                '%s.%s (max %d characters): id=%d, length=%d',
                $v['table'],
                $v['column'],
                $v['limit'],
                $v['id'],
                $v['length']
            );
        }
        throw new EvoMigrationException(
            sprintf(
                "%d value(s) are longer than the upgraded column allows. Nothing was changed. "
                . "Shorten these values (for example with php evo db:query), then run the upgrade again.\n%s",
                count($violations),
                implode("\n", $lines)
            ),
            ['violations' => $violations]
        );
    }

    // 2. 古い表・列の片付け
    // elmid の無い site_revision は、今のコードが読めない古い形の履歴。create_tables.sql が今の形で作り直す
    if ($m->tableExists('site_revision') && !$m->columnExists('site_revision', 'elmid')) {
        $m->dropTableIfExists('site_revision');
        $m->log('Dropped the old-format site_revision table', ['table' => 'site_revision']);
    }
    // 1.0.10J のアップグレード用 SQL だけが足した列。今のコードは読まない
    if ($m->dropColumnIfExists('site_templates', 'doc_encoding')) {
        $m->log('Dropped the unused column', ['table' => 'site_templates', 'column' => 'doc_encoding']);
    }

    // 3. 列の追加。AFTER の基準になる列を先に足す
    $addColumns = [
        ['site_content', 'publishedon', "int(20) NOT NULL DEFAULT '0' COMMENT 'Date the document was published' AFTER `deletedby`"],
        ['site_content', 'publishedby', "int(10) NOT NULL DEFAULT '0' COMMENT 'ID of user who published the document' AFTER `publishedon`"],
        ['site_content', 'link_attributes', "varchar(255) NOT NULL DEFAULT '' COMMENT 'Link attriubtes' AFTER `alias`"],
        ['site_content', 'alias_visible', "int(2) NOT NULL DEFAULT '1'"],
        ['site_htmlsnippets', 'published', "int(1) NOT NULL DEFAULT '1' AFTER `description`"],
        ['site_htmlsnippets', 'pub_date', "int(20) NOT NULL DEFAULT '0' AFTER `published`"],
        ['site_htmlsnippets', 'unpub_date', "int(20) NOT NULL DEFAULT '0' AFTER `pub_date`"],
        ['site_plugins', 'error_reporting', "varchar(8) NOT NULL DEFAULT 'inherit' COMMENT 'PHP error level override' AFTER `properties`"],
        ['site_snippets', 'error_reporting', "varchar(8) NOT NULL DEFAULT 'inherit' COMMENT 'PHP error level override' AFTER `properties`"],
        ['site_templates', 'parent', "int(10) NOT NULL DEFAULT '0' AFTER `content`"],
        ['site_plugin_events', 'priority', "int(10) NOT NULL DEFAULT '0' COMMENT 'determines plugin run order' AFTER `evtid`"],
        ['site_tmplvar_templates', 'rank', "int(11) NOT NULL DEFAULT '0' AFTER `templateid`"],
        ['user_attributes', 'street', "varchar(255) NOT NULL DEFAULT '' AFTER `country`"],
        ['user_attributes', 'city', "varchar(255) NOT NULL DEFAULT '' AFTER `street`"],
        ['web_user_attributes', 'street', "varchar(255) NOT NULL DEFAULT '' AFTER `country`"],
        ['web_user_attributes', 'city', "varchar(255) NOT NULL DEFAULT '' AFTER `street`"],
    ];
    // 権限の列（途中の版で欠けていることがある）。足したときだけ、管理者（ロール ID 1）に付ける
    $roleColumns = [
        'edit_chunk' => 'delete_snippet',
        'new_chunk' => 'edit_chunk',
        'save_chunk' => 'new_chunk',
        'delete_chunk' => 'save_chunk',
        'empty_trash' => 'delete_document',
        'view_unpublished' => 'web_access_permissions',
        'import_static' => 'view_unpublished',
        'export_static' => 'import_static',
        'remove_locks' => 'export_static',
        'view_schedule' => 'remove_locks',
        'publish_document' => 'save_document',
        'move_document' => 'save_document',
    ];

    $added = [];
    foreach ($addColumns as [$table, $column, $definition]) {
        if ($m->addColumn($table, $column, $definition)) {
            $added[] = "{$table}.{$column}";
        }
    }
    $addedRoleColumns = [];
    foreach ($roleColumns as $column => $after) {
        if ($m->addColumn('user_roles', $column, "int(1) NOT NULL DEFAULT '0' AFTER `{$after}`")) {
            $addedRoleColumns[] = $column;
        }
    }
    if ($added || $addedRoleColumns) {
        $m->log('Added missing columns', ['columns' => array_merge($added, array_map(function ($column) {
            return "user_roles.{$column}";
        }, $addedRoleColumns))]);
    }
    if ($addedRoleColumns) {
        $set = [];
        foreach ($addedRoleColumns as $column) {
            $set[] = "`{$column}`=1";
        }
        $m->exec(
            sprintf('UPDATE %s SET %s WHERE `id`=1', $m->table('user_roles'), implode(', ', $set)),
            'UPDATE the added permission columns of role 1'
        );
        $m->log('Granted the added permissions to role 1', ['columns' => $addedRoleColumns]);
    }

    // 4. NULL の片付けと、列の変更（型・NULL 可否・既定値が最終形と違うときだけ）
    // 前置き長の付いた索引（1.0.0J の aliasidx (alias(250))）は、列を縮める前に外す（索引は 6 で作り直す）。
    // 縮めた長さより長い前置きは、サーバーによってはエラーになる
    $aliasIndex = $m->index('site_content', 'aliasidx');
    if ($aliasIndex && $aliasIndex['prefix']) {
        $m->dropIndexIfExists('site_content', 'aliasidx');
    }

    // [表, 列, 定義, 型, NULL 可か, 既定値, NULL を NOT NULL にする前に入れる値（null なら入れない）]
    $modifications = [
        ['active_users', 'id', "int(10) NOT NULL DEFAULT '0'", 'int(10)', false, '0', '0'],
        ['active_users', 'ip', "varchar(50) NOT NULL DEFAULT ''", 'varchar(50)', false, '', ''],
        ['documentgroup_names', 'name', "varchar(191) NOT NULL DEFAULT ''", 'varchar(191)', false, '', ''],
        [
            'documentgroup_names', 'private_memgroup',
            "tinyint(4) NOT NULL DEFAULT '0' COMMENT 'determine whether the document group is private to manager users'",
            'tinyint(4)', false, '0', '0',
        ],
        [
            'documentgroup_names', 'private_webgroup',
            "tinyint(4) NOT NULL DEFAULT '0' COMMENT 'determines whether the document is private to web users'",
            'tinyint(4)', false, '0', '0',
        ],
        ['membergroup_names', 'name', "varchar(191) NOT NULL DEFAULT ''", 'varchar(191)', false, '', ''],
        ['webgroup_names', 'name', "varchar(191) NOT NULL DEFAULT ''", 'varchar(191)', false, '', ''],
        ['site_content', 'alias', "varchar(245) DEFAULT ''", 'varchar(245)', true, '', null],
        ['site_content', 'template', "int(10) NOT NULL DEFAULT '0'", 'int(10)', false, '0', '0'],
        ['site_templates', 'content', 'mediumtext NOT NULL', 'mediumtext', false, null, ''],
        ['site_tmplvar_contentvalues', 'value', 'mediumtext', 'mediumtext', true, null, null],
        ['user_attributes', 'comment', 'text', 'text', true, null, null],
        ['web_user_attributes', 'comment', 'text', 'text', true, null, null],
        ['web_user_attributes', 'country', "varchar(25) NOT NULL DEFAULT ''", 'varchar(25)', false, '', ''],
    ];
    $modified = [];
    foreach ($modifications as [$table, $column, $definition, $type, $nullable, $default, $fill]) {
        $current = $m->column($table, $column);
        if (!$current) {
            continue;
        }
        // NULL が残っていると NOT NULL にできない。NULL 可の列のときだけ先に埋める（最終形では UPDATE を流さない）
        if ($fill !== null && !$nullable && $current['IS_NULLABLE'] === 'YES') {
            $m->exec(
                sprintf(
                    "UPDATE %s SET `%s`='%s' WHERE `%s` IS NULL",
                    $m->table($table),
                    $column,
                    db()->escape($fill),
                    $column
                ),
                "UPDATE the NULL values of {$table}.{$column}"
            );
        }
        if ($m->modifyColumn($table, $column, $definition, $type, $nullable, $default)) {
            $modified[] = "{$table}.{$column}";
        }
    }
    if ($modified) {
        $m->log('Changed columns to the current definition', ['columns' => $modified]);
    }

    // 5. 重複行の除去と、主キー・UNIQUE
    // 付ける前に、キーの列が同じ行を 1 行だけ残して消す（消した件数は removeDuplicates() がログに残す）。
    // すでに最終形の主キー・UNIQUE があるときは、重複は有り得ないので何も流さない
    // [表, 主キーの列, 重複したとき残す行（空なら読んだときに効く最後の行）, 同じ ALTER で外す索引]
    $primaryKeys = [
        ['site_plugin_events', ['pluginid', 'evtid'], 'priority ASC', []],
        ['site_tmplvar_templates', ['tmplvarid', 'templateid'], '`rank` ASC', ['idx_tmplvarid', 'idx_templateid']],
        ['system_settings', ['setting_name'], '', ['setting_name']],
        ['user_settings', ['user', 'setting_name'], '', []],
        ['web_user_settings', ['webuser', 'setting_name'], '', []],
    ];
    foreach ($primaryKeys as [$table, $columns, $keep, $dropIndexes]) {
        if (!$m->tableExists($table)) {
            continue;
        }
        if ($m->primaryKey($table) !== $columns) {
            $m->removeDuplicates($table, $columns, $keep);
        }
        $m->syncPrimaryKey($table, $columns, $dropIndexes);
    }

    // [表, 索引名, 列, 重複したとき残す行]
    $uniqueKeys = [
        ['member_groups', 'ix_group_member', ['user_group', 'member'], 'id ASC'],
        ['web_groups', 'ix_group_user', ['webgroup', 'webuser'], 'id ASC'],
    ];
    foreach ($uniqueKeys as [$table, $name, $columns, $keep]) {
        if (!$m->tableExists($table)) {
            continue;
        }
        $current = $m->index($table, $name);
        if (!$current || !$current['unique'] || $current['columns'] !== $columns || $current['prefix']) {
            $m->removeDuplicates($table, $columns, $keep);
        }
        $m->addIndex($table, $name, $columns, 'UNIQUE');
    }

    // 6. 通常の索引と FULLTEXT
    // 作るだけで足りるもの（無ければ作り、違えば作り直す）
    $m->addIndex('user_settings', 'setting_name', ['setting_name']);
    $m->addIndex('user_settings', 'user', ['user']);
    $m->addIndex('web_user_settings', 'setting_name', ['setting_name']);
    $m->addIndex('web_user_settings', 'webuserid', ['webuser']);
    $m->addIndex('site_content', 'aliasidx', ['alias']);
    $m->addIndex('site_content', 'typeidx', ['type']);
    // 最終形に無い索引
    $m->dropIndexIfExists('site_content', 'content_ft_idx');
    $m->dropIndexIfExists('site_content', 'id');
    // コアは使っていない（MATCH ... AGAINST は無い）が、外部のスニペットが使うかもしれないので新規インストールとそろえる。
    // 大きな表では作るのに時間がかかるので、かかった秒数を残す
    $started = microtime(true);
    if ($m->addIndex('site_tmplvar_contentvalues', 'value_ft_idx', ['value'], 'FULLTEXT')) {
        $m->log('Created the FULLTEXT index', [
            'table' => 'site_tmplvar_contentvalues',
            'index' => 'value_ft_idx',
            'seconds' => round(microtime(true) - $started, 2),
        ]);
    }

    // 7. type の推定。type が空の行だけ（条件が type='' なので、何度流しても同じ）
    if ($m->columnExists('site_content', 'type')) {
        $siteContent = $m->table('site_content');
        $untyped = $m->rows("SELECT COUNT(*) AS n FROM {$siteContent} WHERE `type`=''");
        if ($untyped && (int)$untyped[0]['n'] > 0) {
            $inference = [
                ['reference', 'text/html', "(`content` LIKE 'http://%' OR `content` LIKE 'https://%')"],
                ['document', 'text/xml', "(`alias` LIKE '%.rss' OR `alias` LIKE '%.xml')"],
                ['document', 'text/javascript', "`alias` LIKE '%.js'"],
                ['document', 'text/css', "`alias` LIKE '%.css'"],
                ['document', 'text/html', '1=1'],
            ];
            foreach ($inference as [$type, $contentType, $condition]) {
                $m->exec(sprintf(
                    "UPDATE %s SET `type`='%s', `contentType`='%s' WHERE `type`='' AND %s",
                    $siteContent,
                    $type,
                    $contentType,
                    $condition
                ));
            }
            $m->log('Set the type of documents that had none', ['table' => 'site_content', 'count' => (int)$untyped[0]['n']]);
        }
    }

    // ---- データの部（M4）はここに足す ----
};
