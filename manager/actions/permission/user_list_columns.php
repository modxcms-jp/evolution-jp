<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

/**
 * ユーザー一覧（a=75）とウェブユーザー一覧（a=99）で共通の列。
 * 行には id・username・fullname・email・thislogin・logincount・blocked が要る。
 *
 * @param int $editAction ユーザー名のリンク先の画面（12 か 88）
 * @param array $extraColumns メールアドレスの後ろに差し込む、一覧ごとの列
 */
function userListColumns($editAction, array $extraColumns = [])
{
    return array_merge(
        [
            [
                'key' => 'username',
                'label' => lang('username'),
                'html' => true,
                'format' => function ($value, array $row) use ($editAction) {
                    $id = (int) $row['id'];
                    return sprintf(
                        '<a class="gridRowIcon" href="#" data-row-menu="%d" title="%s"><img src="%s" /></a>'
                        . '<span class="%s"><a href="index.php?a=%d&amp;id=%d" title="%s">%s</a></span>',
                        $id,
                        hsc(lang('click_to_context')),
                        hsc(style('icons_user')),
                        $row['blocked'] ? 'unpublished' : 'published',
                        (int) $editAction,
                        $id,
                        hsc(lang('click_to_edit_title')),
                        hsc($value)
                    );
                },
            ],
            ['key' => 'fullname', 'label' => lang('user_full_name')],
            ['key' => 'email', 'label' => lang('email')],
        ],
        $extraColumns,
        [
            [
                'key' => 'thislogin',
                'label' => lang('user_thislogin'),
                'class' => 'is-shrink',
                'format' => function ($value) {
                    if (!$value) {
                        return '-';
                    }
                    return evo()->mb_strftime(evo()->toDateFormat(null, 'formatOnly') . ' %H:%M', $value);
                },
            ],
            [
                'key' => 'logincount',
                'label' => lang('user_logincount'),
                'class' => 'is-shrink is-end',
            ],
            [
                'key' => 'blocked',
                'label' => lang('user_block'),
                'class' => 'is-shrink is-center',
                'format' => function ($value) {
                    return $value ? lang('yes') : '-';
                },
            ],
        ]
    );
}

/**
 * 一覧の並び順。休止中を下に、ログインの新しい順。
 */
function userListOrderBy($alias)
{
    return userBlockedSqlCondition($alias) . " ASC, {$alias}.thislogin DESC";
}
