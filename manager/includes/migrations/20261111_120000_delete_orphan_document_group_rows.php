<?php

/**
 * 存在しない文書グループ・文書を指す結びつきの行を消す。
 *
 * 以前の文書グループの削除（#1375 の前）や DocManager（#1389 の前）は、どこからも使われない行を残すことがあった。
 * 文書グループの id は AUTO_INCREMENT で、MySQL 5.7 では再起動のあと最大の id を受け継ぐことがあり、
 * 残った行が、あとから作った文書グループに結びつくおそれがある。
 *
 * 消す行:
 * - membergroup_access・webgroup_access・site_tmplvar_access: documentgroup が documentgroup_names に無い行
 * - document_groups: document_group が documentgroup_names に無い行、または document が site_content に無い行
 * 消した件数は、表ごとにシステムログに残す。
 *
 * 非公開の印（site_content の privatemgr・privateweb）は、document_groups と membergroup_access・webgroup_access を
 * 文書グループの id でつないで決まる。同じ id の行がどちらにも残っていると、存在しない文書グループの行だけで
 * 印が付いていることがあるので、document_groups の行が消えた文書だけ、消したあとの結びつきから印を求め直す
 * （ManagerAPI の setMgrDocsAsPrivate() と同じ結果になる SQL。ランナーの文脈で ManagerAPI が使えるとは限らないため）。
 * ページキャッシュは、ランナーが消さない決まりなので、呼び出し側（インストーラ・php evo migrate）が消す。
 *
 * 2 回目は消す行が無く、何も変えない。
 */
return static function (EvoMigrationHelper $m) {
    foreach ([
        'membergroup_access', 'webgroup_access', 'site_tmplvar_access',
        'document_groups', 'documentgroup_names', 'site_content',
    ] as $table) {
        if (!$m->tableExists($table)) {
            return;
        }
    }

    $names = $m->table('documentgroup_names');
    $contents = $m->table('site_content');
    $docGroups = $m->table('document_groups');

    // 表ごとの「どこからも使われない行」の条件
    $targets = [];
    foreach (['membergroup_access', 'webgroup_access', 'site_tmplvar_access'] as $table) {
        $full = $m->table($table);
        $targets[$table] = [
            'from' => $full,
            'where' => sprintf(
                'NOT EXISTS (SELECT 1 FROM %s n WHERE n.`id` = %s.`documentgroup`)',
                $names,
                $full
            ),
        ];
    }
    $targets['document_groups'] = [
        'from' => $docGroups,
        'where' => sprintf(
            'NOT EXISTS (SELECT 1 FROM %1$s n WHERE n.`id` = %3$s.`document_group`)'
            . ' OR NOT EXISTS (SELECT 1 FROM %2$s c WHERE c.`id` = %3$s.`document`)',
            $names,
            $contents,
            $docGroups
        ),
    ];

    // 印を求め直す文書は、消す前に控える
    $rows = $m->rows(sprintf(
        'SELECT DISTINCT `document` FROM %s WHERE %s',
        $docGroups,
        $targets['document_groups']['where']
    ));
    $docIds = array_values(array_filter(array_map('intval', array_column($rows, 'document'))));

    $deleted = [];
    foreach ($targets as $table => $target) {
        $count = $m->rows(sprintf('SELECT COUNT(*) AS c FROM %s WHERE %s', $target['from'], $target['where']));
        $deleted[$table] = (int)$count[0]['c'];
        if ($deleted[$table]) {
            $m->exec(sprintf('DELETE FROM %s WHERE %s', $target['from'], $target['where']));
        }
    }

    if (!array_sum($deleted)) {
        return;
    }
    $m->log('Deleted rows pointing to nonexistent document groups or documents', ['deleted' => $deleted]);

    if (!$docIds) {
        return;
    }
    $ids = implode(',', $docIds);
    foreach (['privatemgr' => 'membergroup_access', 'privateweb' => 'webgroup_access'] as $flag => $accessTable) {
        $m->exec(sprintf('UPDATE %s SET `%s`=0 WHERE `id` IN (%s)', $contents, $flag, $ids));
        $m->exec(sprintf(
            'UPDATE %1$s SET `%2$s`=1 WHERE `id` IN (%3$s) AND EXISTS ('
            . 'SELECT 1 FROM %4$s dg INNER JOIN %5$s a ON a.`documentgroup` = dg.`document_group`'
            . ' WHERE dg.`document` = %1$s.`id`)',
            $contents,
            $flag,
            $ids,
            $docGroups,
            $m->table($accessTable)
        ));
    }
};
