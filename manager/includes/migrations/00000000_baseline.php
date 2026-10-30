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
 * 後半（データの部）は、スキーマの部のあとに続く。
 */
return static function (EvoMigrationHelper $m) {
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
    // alias の索引（aliasidx）。245 文字の全体に索引を付けられるかは、表のエンジン・行の形式・文字セットで決まる
    // （utf8mb4 で 980 バイト。InnoDB の COMPACT / REDUNDANT は 767 バイトまで）。
    // 付けられない行の形式なら DYNAMIC に直してみて、それでも付けられないときは、前置き長つきの索引を残す
    $aliasFull = true;
    if ($m->columnExists('site_content', 'alias')) {
        $fit = $m->fullIndexFits('site_content', 'alias', 245);
        if (!$fit['fits'] && $m->useDynamicRowFormat('site_content')) {
            $m->log('Changed the row format to DYNAMIC', ['table' => 'site_content', 'reason' => $fit['reason']]);
            $fit = $m->fullIndexFits('site_content', 'alias', 245);
        }
        $aliasFull = $fit['fits'];
        if (!$aliasFull) {
            $prefixLength = max(1, min(245, intdiv($fit['limit'], $fit['char_bytes'])));
            // 列を縮める前に、縮めたあとの長さに収まる前置き長にしておく（今の前置きが短ければそのまま残す）
            $m->addPrefixIndex('site_content', 'aliasidx', 'alias', $prefixLength);
            $m->log('Kept the alias index as a prefix index in this environment', [
                'table' => 'site_content',
                'index' => 'aliasidx',
                'prefix_length' => $prefixLength,
                'reason' => $fit['reason'],
            ], 2);
        } else {
            // 前置き長の付いた索引（1.0.0J の aliasidx (alias(250))）は、列を縮める前に外す（索引は 6 で作り直す）。
            // 縮めた長さより長い前置きは、サーバーによってはエラーになる
            $aliasIndex = $m->index('site_content', 'aliasidx');
            if ($aliasIndex && $aliasIndex['prefix']) {
                $m->dropIndexIfExists('site_content', 'aliasidx');
            }
        }
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
    if ($aliasFull) {
        $m->addIndex('site_content', 'aliasidx', ['alias']);
    }
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

    // ======== データの部 ========
    // どれも版の条件は置かず、DB とファイルの状態が「古い形のまま」のときだけ変える。
    // 利用者が選んだ値は上書きしない。変えたときはログ（種別 1）に設定名・前の値・新しい値を残す。
    // 2 回目は条件に合う行が無いので、UPDATE / DELETE を流さない（先に SELECT で確かめる）。
    // 同梱物（プラグインのコードなど）の入れ替えはしない（アップデーターが利用者の選択どおりに入れ直す）
    $defaults = include MODX_CORE_PATH . 'default.config.php';

    $changeSetting = function ($name, $old, $new, $showValues = true) use ($m) {
        $m->saveSetting($name, $new);
        $m->log('Updated the setting', $showValues
            ? ['setting' => $name, 'old' => $old, 'new' => $new]
            : ['setting' => $name]);
    };

    // 8. ロール ID 1（Administrator）。コードが特別に扱う。無いときだけ今の定義で入れる（あれば利用者の変更を残す）
    if ($m->tableExists('user_roles') && !$m->rows(sprintf('SELECT 1 FROM %s WHERE `id`=1', $m->table('user_roles')))) {
        $permissions = [
            'frames', 'home', 'view_document', 'new_document', 'save_document', 'move_document', 'publish_document',
            'delete_document', 'empty_trash', 'action_ok', 'logout', 'help', 'messages', 'new_user', 'edit_user', 'logs',
            'edit_parser', 'save_parser', 'edit_template', 'settings', 'credits', 'new_template', 'save_template',
            'delete_template', 'edit_snippet', 'new_snippet', 'save_snippet', 'delete_snippet', 'edit_chunk', 'new_chunk',
            'save_chunk', 'delete_chunk', 'empty_cache', 'edit_document', 'change_password', 'error_dialog', 'about',
            'file_manager', 'save_user', 'delete_user', 'save_password', 'edit_role', 'save_role', 'delete_role', 'new_role',
            'access_permissions', 'bk_manager', 'new_plugin', 'edit_plugin', 'save_plugin', 'delete_plugin', 'new_module',
            'edit_module', 'save_module', 'exec_module', 'delete_module', 'view_eventlog', 'delete_eventlog',
            'manage_metatags', 'edit_doc_metatags', 'new_web_user', 'edit_web_user', 'save_web_user', 'delete_web_user',
            'web_access_permissions', 'view_unpublished', 'import_static', 'export_static', 'remove_locks', 'view_schedule',
        ];
        $fields = ['`id`', '`name`', '`description`'];
        $values = ['1', "'Administrator'", "'Site administrators have full access to all functions'"];
        foreach ($permissions as $permission) {
            $fields[] = "`{$permission}`";
            $values[] = '1';
        }
        $m->exec(
            sprintf('INSERT INTO %s (%s) VALUES (%s)', $m->table('user_roles'), implode(',', $fields), implode(',', $values)),
            'INSERT the Administrator role'
        );
        $m->log('Added the Administrator role (id 1)');
    }

    // 9. 設定ではない値が入っていた行を消す（#611・#615・#640）
    $notSettings = "'csrf_token','save_action','blockedcheck','tinymce7_template_docs','tinymce7_template_chunks','tinymce7_template_category'";
    if (
        $m->tableExists('user_settings')
        && $m->rows(sprintf('SELECT 1 FROM %s WHERE `setting_name` IN (%s) LIMIT 1', $m->table('user_settings'), $notSettings))
    ) {
        $m->exec(sprintf('DELETE FROM %s WHERE `setting_name` IN (%s)', $m->table('user_settings'), $notSettings));
        $m->log('Removed non-setting rows from user_settings');
    }
    // CSRF トークンに置き換わった validate_referer、判定に使わなくなった install_version、
    // URL の決定に使われていなかった site_url（今の「サイトのURL」は fixed_site_url）
    if ($m->tableExists('system_settings')) {
        foreach (['csrf_token', 'validate_referer', 'install_version', 'site_url'] as $name) {
            if ($m->deleteSetting($name)) {
                $m->log('Removed an obsolete setting', ['setting' => $name]);
            }
        }
    }

    // 10. 使えなくなったものを選んでいるときだけ、既定に戻す
    // 管理画面テーマ: 古い作りのもの（style.php が無い、manager/ か sysalert_style.php がある、MODxCarbon の新しい作りでないもの）
    if ($m->hasSetting('manager_theme')) {
        $theme = (string)$m->setting('manager_theme');
        if (basename($theme) !== $theme || strpos($theme, '..') !== false) {
            // フォルダ名として使えない値は、使えるかどうかを判定できないので変えない
            $m->log('manager_theme was not checked: it is not a folder name', ['setting' => 'manager_theme'], 2);
        } else {
            // 空の値も使えないものとして扱う（style.php が見つからない）
            $themeDir = MODX_MANAGER_PATH . 'media/style/' . $theme . '/';
            $unusable = !is_file($themeDir . 'style.php')
                || is_dir($themeDir . 'manager')
                || is_file($themeDir . 'sysalert_style.php')
                || ($theme === 'MODxCarbon' && !is_dir($themeDir . 'images/icons/32x'));
            if ($unusable) {
                // 戻す先の既定のテーマが使えないときは書き込まない（流し直すたびに同じ書き込みが出ないように）
                $defaultTheme = (string)$defaults['manager_theme'];
                if (is_file(MODX_MANAGER_PATH . 'media/style/' . $defaultTheme . '/style.php')) {
                    $changeSetting('manager_theme', $theme, $defaultTheme);
                } else {
                    $m->log(
                        'manager_theme was not changed: the default theme is not available',
                        ['setting' => 'manager_theme', 'old' => $theme, 'default' => $defaultTheme],
                        2
                    );
                }
            }
        }
    }
    // エディタ: FCKeditor の古い雛形が残っていて、それを選んでいるとき
    if (strcasecmp((string)$m->setting('which_editor', ''), 'FCKeditor') === 0) {
        $fckTemplate = MODX_BASE_PATH . 'assets/plugins/fckeditor/plugin.fckeditor.tpl';
        if (is_file($fckTemplate) && strpos((string)file_get_contents($fckTemplate), 'FCKeditor v2.1.1') !== false) {
            $changeSetting('which_editor', $m->setting('which_editor'), $defaults['which_editor']);
        }
    }
    // 上部メニュー: 古い既定のままのときだけ今の既定に（並べ替えた値は残す）
    if ($m->setting('topmenu_site') === 'home,preview,refresh_site,search,add_resource,add_weblink') {
        $changeSetting('topmenu_site', $m->setting('topmenu_site'), $defaults['topmenu_site']);
    }
    // コンテンツタイプの一覧: 古い既定の 2 つのどちらかと同じときだけ今の既定にする。
    // 行が無いときは書かない（既定は default.config.php から届くので、書くと今後の既定の変更が届かなくなる）。空にした値は残す
    $contentTypes = $m->setting('custom_contenttype');
    if (
        $contentTypes === 'text/css,text/html,text/javascript,text/plain,text/xml'
        || $contentTypes === 'application/rss+xml,application/pdf,application/msword,application/excel,text/html,text/css,text/xml,text/javascript,text/plain'
    ) {
        $changeSetting('custom_contenttype', $contentTypes, $defaults['custom_contenttype']);
    }

    // 11. メールの送信元: 初期値のままのときだけ、ロール ID 1 の管理者のうち ID が最も小さい人のアドレスにする
    if ($m->setting('emailsender') === 'you@example.com') {
        $admin = $m->rows(sprintf(
            "SELECT ua.`email` FROM %s ua JOIN %s mu ON mu.`id`=ua.`internalKey` WHERE ua.`role`=1 AND ua.`email`<>'' "
            . 'ORDER BY ua.`internalKey` ASC LIMIT 1',
            $m->table('user_attributes'),
            $m->table('manager_users')
        ), 'SELECT the email of the first administrator');
        if ($admin) {
            $changeSetting('emailsender', null, $admin[0]['email'], false);
        } else {
            $m->log('emailsender was not changed: no administrator with an email address', ['setting' => 'emailsender'], 2);
        }
    }
    // 文書グループによる権限: 設定の行が無いときだけ、文書グループの有無で決める（行があれば利用者の選択）
    if (!$m->hasSetting('use_udperms') && $m->tableExists('document_groups')) {
        $groups = $m->rows(sprintf('SELECT COUNT(*) AS n FROM %s', $m->table('document_groups')));
        $changeSetting('use_udperms', null, (int)$groups[0]['n'] > 0 ? '1' : '0');
    }
    // site_id: 無いか、配布物の既定値のままのときだけ作り直す
    $siteId = $m->setting('site_id');
    if ($siteId === null || $siteId === '' || $siteId === 'MzGeQ2faT4Dw06+U49x3') {
        $changeSetting('site_id', null, uniqid(''), false);
    }

    // 12. 親テンプレートの継承: 有効な Inherit Parent Template プラグインがあり、auto_template_logic の行が無いときだけ、
    // プラグインの設定から計算して保存し、プラグインを無効にする（旧 1.0.5J のインストーラの意図。保存し忘れていた不具合を直した）
    if ($m->tableExists('site_plugins') && !$m->hasSetting('auto_template_logic')) {
        $inherit = $m->rows(
            sprintf("SELECT `id`, `properties` FROM %s WHERE `name`='Inherit Parent Template' AND `disabled`=0", $m->table('site_plugins')),
            'SELECT the Inherit Parent Template plugin'
        );
        if ($inherit) {
            $properties = evo()->parseProperties($inherit[0]['properties']);
            $logic = ($properties['inheritTemplate'] ?? '') === 'From First Sibling' ? 'sibling' : 'parent';
            $changeSetting('auto_template_logic', null, $logic);
            $m->exec(
                sprintf("UPDATE %s SET `disabled`=1 WHERE `name`='Inherit Parent Template' AND `disabled`=0", $m->table('site_plugins')),
                'UPDATE the Inherit Parent Template plugin'
            );
            $m->log('Disabled the plugin replaced by auto_template_logic', ['plugin' => 'Inherit Parent Template']);
        }
    }
    // 1.0.7J で役目を終えたプラグイン（今のコードと組み合わせる理由が無い）。有効なら無効にする
    if ($m->tableExists('site_plugins')) {
        $legacy = $m->rows(
            sprintf(
                "SELECT `id`, `name` FROM %s WHERE `disabled`=0 AND `name` IN ('%s','%s')",
                $m->table('site_plugins'),
                db()->escape('Bindings機能の有効無効'),
                db()->escape('Bottom Button Bar')
            ),
            'SELECT the legacy plugins'
        );
        if ($legacy) {
            $ids = array_map('intval', array_column($legacy, 'id'));
            $m->exec(
                sprintf('UPDATE %s SET `disabled`=1 WHERE `id` IN (%s)', $m->table('site_plugins'), implode(',', $ids)),
                'UPDATE the legacy plugins'
            );
            $m->log('Disabled legacy plugins', ['plugin_ids' => $ids]);
        }
    }

    // 13. 文字列 'NULL' を空に戻す（以前のインストーラとリソースの複製が入れていた）。
    // BINARY で比べ、照合順序で 'null' や末尾空白付きの値まで一致しないようにする
    $nullTargets = [
        'site_snippets' => ['properties'],
        'site_plugins' => ['properties', 'moduleguid'],
        'site_modules' => ['properties', 'guid'],
        'site_tmplvars' => ['elements', 'display', 'display_params'],
        'site_content' => ['alias'],
    ];
    foreach ($nullTargets as $table => $columns) {
        foreach ($columns as $column) {
            if (!$m->columnExists($table, $column)) {
                continue;
            }
            $where = sprintf("BINARY `%s`='NULL'", $column);
            if ($m->rows(sprintf('SELECT 1 FROM %s WHERE %s LIMIT 1', $m->table($table), $where))) {
                $m->exec(sprintf("UPDATE %s SET `%s`='' WHERE %s", $m->table($table), $column, $where));
                $m->log("Emptied the literal 'NULL' strings", ['table' => $table, 'column' => $column]);
            }
        }
    }
    // 編集画面で保存したときにプロパティへ入った '&NULL=undefined;;' を取り除く
    foreach (['site_snippets', 'site_plugins', 'site_modules'] as $table) {
        if (!$m->columnExists($table, 'properties')) {
            continue;
        }
        $where = "`properties` LIKE '%&NULL=undefined;;%'";
        if ($m->rows(sprintf('SELECT 1 FROM %s WHERE %s LIMIT 1', $m->table($table), $where))) {
            $m->exec(sprintf(
                "UPDATE %s SET `properties`=TRIM(REPLACE(`properties`, '&NULL=undefined;;', '')) WHERE %s",
                $m->table($table),
                $where
            ));
            $m->log("Removed '&NULL=undefined;;' from the properties", ['table' => $table]);
        }
    }

    // 14. ファイル。サイトのルート（MODX_BASE_PATH）と管理画面（MODX_MANAGER_PATH）の下の、固定のパスだけを扱う
    // 同梱テーマの、common/ にまとめたファイルの古い写し（#794）。配布から外したファイルなので、利用者の選択ではない
    $themeCopies = [
        'AromaHop' => ['welcome.php', 'manager.lockout.tpl', 'config.php.sample'],
        'AuroraFlow' => ['welcome.php', 'welcome.tpl', 'manager.lockout.tpl', 'config.php.sample'],
        'RevoClassic' => ['welcome.php', 'welcome.tpl', 'manager.lockout.tpl', 'config.php.sample'],
        'RevoStyle' => ['welcome.php', 'manager.lockout.tpl', 'config.php.sample'],
        'RubberWhite' => ['welcome.php', 'welcome.tpl', 'manager.lockout.tpl', 'config.php.sample'],
        'SakuraFlow' => ['welcome.php', 'manager.lockout.tpl', 'config.php.sample'],
        'SoftSlate' => ['welcome.php', 'welcome.tpl', 'manager.lockout.tpl', 'config.php.sample'],
    ];
    $removed = [];
    $failed = [];
    foreach ($themeCopies as $theme => $names) {
        foreach ($names as $name) {
            $path = MODX_MANAGER_PATH . "media/style/{$theme}/{$name}";
            if (!is_file($path)) {
                continue;
            }
            if (@unlink($path)) {
                $removed[] = "{$theme}/{$name}";
            } else {
                $failed[] = "manager/media/style/{$theme}/{$name}";
            }
        }
    }
    if ($removed) {
        $m->log('Removed old copies of bundled theme files', ['files' => $removed]);
    }
    if ($failed) {
        // 消せないと、テーマ側の古いファイルが優先され続けるので、手で消すよう知らせる
        $m->log('Could not remove old copies of bundled theme files. Delete them by hand', ['files' => $failed], 2);
    }
    // 2012 年の脆弱な action.php（$_GET['include'] を読み込み、パスの確認が無い版）と中身が同じときだけ消す。
    // 利用者が置いた別の action.php は消さない
    $actionPhp = MODX_BASE_PATH . 'action.php';
    if (is_file($actionPhp)) {
        $source = (string)file_get_contents($actionPhp);
        if (
            strpos($source, "\$_GET['include']") !== false
            && strpos($source, 'MODX_API_MODE') !== false
            && strpos($source, 'if(strpos($path,MODX_MANAGER_PATH)!==0)') === false
        ) {
            if (@unlink($actionPhp)) {
                $m->log('Removed the vulnerable action.php', ['file' => 'action.php']);
            } else {
                $m->log('Could not remove the vulnerable action.php. Delete it by hand', ['file' => 'action.php'], 2);
            }
        }
    }
};
