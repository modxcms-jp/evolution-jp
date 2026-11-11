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
 * 消した行が 1 件以上あれば、件数を表ごとにシステムログに残す。
 *
 * 動きが変わる点（どれも、今の文書グループの削除（ManagerAPI::deleteDocumentGroup()）と同じ結果にそろえる）:
 * - 削除済みの文書グループだけに結びついていた TV は、絞られる利用者にも届くようになる。
 * - 存在しない文書グループの id の membergroup_access・webgroup_access の行で届いていた TV や文書は、届かなくなる。
 * - 存在しない id の document_groups とアクセスの行がそろって残っていた文書は、非公開の印が 0 になり得る。
 *
 * 非公開の印（site_content の privatemgr・privateweb）は、結びつきから求める値なので、消した行の有無に関わらず
 * 毎回、ManagerAPI の setMgrDocsAsPrivate()・setWebDocsAsPrivate() で全件を求め直す。
 * DELETE のあとで止まっても、流し直しで印が直る。正しいサイトでは値は変わらない。
 * アップデーターでは、このあと install/instprocessor.php の後片付け（マイグレーションの記録の前）でも、同じ ManagerAPI で全件を求め直す。
 * ページキャッシュは、ランナーが消さない決まりなので、呼び出し側（インストーラ・php evo migrate）が消す。
 *
 * 2 回目は消す行が無く、ログも出さない（印は同じ値に求め直される）。
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

    $deleted = [];
    foreach ($targets as $table => $target) {
        $m->exec(sprintf('DELETE FROM %s WHERE %s', $target['from'], $target['where']));
        $deleted[$table] = (int)db()->getAffectedRows();
    }

    if (array_sum($deleted)) {
        $m->log('Deleted rows pointing to nonexistent document groups or documents', ['deleted' => $deleted]);
    }

    manager()->setWebDocsAsPrivate();
    manager()->setMgrDocsAsPrivate();
};
