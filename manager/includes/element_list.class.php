<?php

/**
 * エレメント管理（a=76）の一覧。種類ごとの違い（テーブル・名前の列・編集の action・権限・状態の列）は types() の 1 か所に置く。
 * 取り出し（rows()）と描く処理（render()）を分け、描く処理は引数だけから HTML を作る。
 */
class ElementList
{
    /**
     * 種類の定義。tab のある種類は、エレメント管理に自分のタブを持つ（label・message・newLabel は言語のキー）。
     * labelFields は名前・説明と一緒に探す列、contentFields は本文（コード・HTML）の列（検索 a=71 で使う）。
     */
    public static function types(): array
    {
        return [
            'template' => [
                'table' => 'site_templates',
                'contentFields' => ['content'],
                'nameField' => 'templatename',
                'editAction' => 16,
                'newAction' => 19,
                'perms' => ['new_template', 'edit_template'],
                'label' => 'manage_templates',
                'tab' => 'tabTemplates',
                'message' => 'template_management_msg',
                'newLabel' => 'new_template',
                'showId' => true,
            ],
            'tmplvar' => [
                'table' => 'site_tmplvars',
                'labelFields' => ['caption'],
                'contentFields' => ['elements', 'default_text'],
                'editAction' => 301,
                'newAction' => 300,
                'perms' => ['new_template', 'edit_template'],
                'label' => 'tmplvars',
                'tab' => 'tabVariables',
                'message' => 'tmplvars_management_msg',
                'newLabel' => 'new_tmplvars',
                'showId' => true,
                'orderByRank' => true,
            ],
            'chunk' => [
                'table' => 'site_htmlsnippets',
                'contentFields' => ['snippet'],
                'editAction' => 78,
                'newAction' => 77,
                'perms' => ['new_chunk', 'edit_chunk'],
                'label' => 'manage_htmlsnippets',
                'tab' => 'tabChunks',
                'message' => 'htmlsnippet_management_msg',
                'newLabel' => 'new_htmlsnippet',
                'stateField' => 'published',
            ],
            'snippet' => [
                'table' => 'site_snippets',
                'contentFields' => ['snippet'],
                'editAction' => 22,
                'newAction' => 23,
                'perms' => ['new_snippet', 'edit_snippet'],
                'label' => 'manage_snippets',
                'tab' => 'tabSnippets',
                'message' => 'snippet_management_msg',
                'newLabel' => 'new_snippet',
            ],
            'plugin' => [
                'table' => 'site_plugins',
                'contentFields' => ['plugincode'],
                'editAction' => 102,
                'newAction' => 101,
                'perms' => ['new_plugin', 'edit_plugin'],
                'label' => 'manage_plugins',
                'tab' => 'tabPlugins',
                'message' => 'plugin_management_msg',
                'newLabel' => 'new_plugin',
                'stateField' => 'disabled',
            ],
            'module' => [
                'table' => 'site_modules',
                'contentFields' => ['modulecode'],
                'editAction' => 108,
                'newAction' => 107,
                'perms' => ['new_module', 'edit_module'],
                'label' => 'modules',
            ],
        ];
    }

    public static function type(string $type): array
    {
        $types = static::types();
        if (!isset($types[$type])) {
            throw new InvalidArgumentException("Unknown element type: {$type}");
        }
        return $types[$type];
    }

    public static function canView(string $type): bool
    {
        foreach (static::type($type)['perms'] as $perm) {
            if (evo()->hasPermission($perm)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 1 つの種類の行を、カテゴリー → （TV は rank →）名前の順で返す。
     * 行の形: id, name, description, locked, category, catid, state（'disabled' | 'unpublished' | ''）, type
     */
    public static function rows(string $type): array
    {
        return static::select($type, [], []);
    }

    /**
     * 検索（a=71）の UNION ALL の部分。権限のある種類ごとに、条件に当たる行の kind（種類）・id・title を返す SELECT。
     * $conditions は ResourceSearchQuery::textConditions() の形（value・negate・scope）。
     * 探す範囲をエレメントの列に読み替える（searchWhere()）。エレメントは名前より本文で探すことが多いので、
     * 項目を付けない語（any）は「本文も探す」に関係なく本文も探す。
     *   any                            → 名前・説明・labelFields・contentFields
     *   title・pagetitle・longtitle    → 名前・説明・labelFields
     *   content                        → contentFields
     *   alias                          → なし
     * 読み替える列が無い条件は、含む条件ならその種類を出さず、除く条件なら掛けない。
     *
     * @param array<int, array{value: string, negate: bool, scope: string}> $conditions
     * @return string[]
     */
    public static function searchSelects(array $conditions): array
    {
        if (!$conditions) {
            return [];
        }
        $selects = [];
        foreach (static::types() as $type => $def) {
            if (!static::canView($type)) {
                continue;
            }
            $where = static::searchWhere($def, $conditions);
            if ($where === null) {
                continue;
            }
            $selects[] = sprintf(
                "SELECT '%s' AS kind, e.id AS id, %s AS title FROM [+prefix+]%s AS e WHERE %s",
                $type,
                static::sortableTitle('e.' . ($def['nameField'] ?? 'name')),
                $def['table'],
                $where ? implode(' AND ', $where) : '1=1'
            );
        }
        return $selects;
    }

    /**
     * UNION ALL でまとめて並べ替える名前の式。テーブルごとに照合順序が違っても混ぜられるよう、1 つにそろえる
     */
    public static function sortableTitle(string $column): string
    {
        return "CONVERT({$column} USING utf8mb4) COLLATE utf8mb4_unicode_ci";
    }

    /**
     * 1 つの種類の、id で指した行（id => 行）。行は rows() の形に、hit（本文の中で最初に当たった行
     * ['line' => 行番号, 'text' => 行の文字列]。無ければ null）を足したもの。
     *
     * @param int[] $ids
     * @param string[] $terms 本文の中の当たった行を探す語（ResourceSearchQuery::terms()）
     */
    public static function rowsByIds(string $type, array $ids, array $terms): array
    {
        if (!$ids) {
            return [];
        }
        $def = static::type($type);
        $where = ['e.id IN (' . implode(',', array_map('intval', $ids)) . ')'];
        $rows = [];
        foreach (static::select($type, $where, $def['contentFields']) as $row) {
            $row['hit'] = static::hitLine($row['content'], $terms);
            unset($row['content']);
            $rows[$row['id']] = $row;
        }
        return $rows;
    }

    /**
     * searchSelects() の WHERE（AND でつなぐ式）。この種類では何も当たらないときは null
     */
    private static function searchWhere(array $def, array $conditions): ?array
    {
        $labelColumns = array_merge([$def['nameField'] ?? 'name', 'description'], $def['labelFields'] ?? []);
        $scopeColumns = [
            'any' => array_merge($labelColumns, $def['contentFields']),
            'title' => $labelColumns,
            'pagetitle' => $labelColumns,
            'longtitle' => $labelColumns,
            'content' => $def['contentFields'],
            'alias' => [],
        ];
        $where = [];
        foreach ($conditions as $c) {
            $columns = $scopeColumns[$c['scope']] ?? [];
            if (!$columns) {
                if ($c['negate']) {
                    continue;
                }
                return null;
            }
            $like = ResourceSearchQuery::escapeLike($c['value']);
            $expr = '(' . implode(' OR ', array_map(static function ($column) use ($like) {
                return sprintf("IFNULL(e.%s,'') LIKE '%%%s%%'", $column, $like);
            }, $columns)) . ')';
            $where[] = $c['negate'] ? "NOT {$expr}" : $expr;
        }
        return $where;
    }

    /**
     * 本文の中で、語を最初に含む行（大文字・小文字を区別しない）。長い行は語の前後を切り出す。
     *
     * @param string[] $texts 本文の列の値
     * @param string[] $terms
     */
    public static function hitLine(array $texts, array $terms): ?array
    {
        if (!$terms) {
            return null;
        }
        foreach ($texts as $text) {
            foreach (preg_split('/\R/u', mb_scrub((string) $text)) ?: [] as $n => $line) {
                foreach ($terms as $term) {
                    $at = mb_stripos($line, $term);
                    if ($at === false) {
                        continue;
                    }
                    $line = trim($line);
                    $at = mb_stripos($line, $term);
                    $len = 160;
                    if (mb_strlen($line) > $len) {
                        $start = max(0, min($at - 50, mb_strlen($line) - $len));
                        $line = ($start > 0 ? '…' : '') . mb_substr($line, $start, $len)
                            . ($start + $len < mb_strlen($line) ? '…' : '');
                    }
                    return ['line' => $n + 1, 'text' => $line];
                }
            }
        }
        return null;
    }

    /**
     * 1 つの種類の行を取り出す。$where は呼ぶ側で安全にした式（AND でつなぐ）。
     * $contentColumns を渡すと、その列の値を行の content（列名 => 値）に入れる。
     */
    private static function select(string $type, array $where, array $contentColumns): array
    {
        $def = static::type($type);
        $nameField = $def['nameField'] ?? 'name';
        $stateField = $def['stateField'] ?? '';

        $fields = [
            'e.id',
            "e.{$nameField} AS name",
            'e.description',
            'e.locked',
            'c.id AS catid',
            'c.category',
        ];
        if ($stateField !== '') {
            $fields[] = "e.{$stateField} AS state_value";
        }
        foreach ($contentColumns as $column) {
            $fields[] = "e.{$column} AS content_{$column}";
        }
        $orderBy = ['c.category IS NULL', 'c.category ASC'];
        if (!empty($def['orderByRank'])) {
            $orderBy[] = 'e.rank ASC';
        }
        $orderBy[] = 'name ASC';

        $rs = db()->select(
            implode(', ', $fields),
            sprintf(
                '[+prefix+]%s AS e LEFT JOIN [+prefix+]categories AS c ON e.category = c.id',
                $def['table']
            ),
            implode(' AND ', $where),
            implode(', ', $orderBy)
        );

        $rows = [];
        while ($row = db()->getRow($rs)) {
            $content = [];
            foreach ($contentColumns as $column) {
                $content[$column] = (string) ($row["content_{$column}"] ?? '');
            }
            $rows[] = [
                'content' => $content,
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'description' => (string) $row['description'],
                'locked' => (bool) $row['locked'],
                'category' => (string) ($row['category'] ?? ''),
                'catid' => (int) ($row['catid'] ?? 0),
                'state' => static::state($stateField, $row['state_value'] ?? null),
                'type' => $type,
            ];
        }
        return $rows;
    }

    /**
     * 権限のある種類をまとめ、カテゴリー → 名前の順に並べ直して返す（エレメント一覧のタブ）。
     */
    public static function allRows(): array
    {
        $rows = [];
        foreach (array_keys(static::types()) as $type) {
            if (static::canView($type)) {
                $rows = array_merge($rows, static::rows($type));
            }
        }
        usort($rows, static function ($a, $b) {
            if (($a['catid'] === 0) !== ($b['catid'] === 0)) {
                return $a['catid'] === 0 ? 1 : -1;
            }
            return strcasecmp($a['category'], $b['category'])
                ?: strcasecmp($a['name'], $b['name']);
        });
        return $rows;
    }

    private static function state(string $field, $value): string
    {
        if ($field === 'disabled' && (int) $value === 1) {
            return 'disabled';
        }
        if ($field === 'published' && (string) $value === '0') {
            return 'unpublished';
        }
        return '';
    }

    /**
     * カテゴリーごとにまとめたリストを描く。
     *
     * $options:
     *   showType        各行に種類の名前を出す（エレメント一覧のタブ）
     *   categoryDelete  カテゴリーの見出しに削除のリンクを出す
     *   labels          no_category / locked / delete_category / empty と、種類の名前（type => 名前）
     *   rtl             右から左の言語
     */
    public static function render(array $rows, array $options): string
    {
        $labels = $options['labels'];

        if (!$rows) {
            return sprintf('<p>%s</p>', hsc($labels['empty']));
        }

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['catid']][] = $row;
        }

        $html = '<ul class="element-list">';
        foreach ($groups as $catid => $items) {
            $category = $catid ? $items[0]['category'] : $labels['no_category'];
            $html .= sprintf(
                '<li><strong>%s</strong>%s<ul>',
                hsc($category),
                ($catid && !empty($options['categoryDelete']))
                    ? sprintf(
                        ' (<a href="#" data-delete-category="%d">%s</a>)',
                        $catid,
                        hsc($labels['delete_category'])
                    )
                    : ''
            );
            foreach ($items as $row) {
                $html .= static::renderItem($row, $options);
            }
            $html .= '</ul></li>';
        }
        return $html . '</ul>';
    }

    private static function renderItem(array $row, array $options): string
    {
        $def = static::type($row['type']);
        $labels = $options['labels'];
        $showType = !empty($options['showType']);
        $showId = !$showType && !empty($def['showId']);

        $text = $showId ? "[{$row['id']}] {$row['name']}" : $row['name'];
        $classes = [
            'disabled' => 'disabledPlugin',
            'unpublished' => 'unpublished',
        ];
        $class = $classes[$row['state']] ?? '';

        $description = element_description_html($row['description']);
        if ($showType && $description !== '') {
            $description = ' - ' . $description;
        }

        return sprintf(
            '<li><span%s><a href="index.php?id=%d&amp;a=%d">%s</a>%s</span>%s %s%s</li>',
            $class !== '' ? ' class="' . $class . '"' : '',
            $row['id'],
            $def['editAction'],
            hsc($text),
            !empty($options['rtl']) ? '&rlm;' : '',
            $showType ? ' (' . hsc($labels['types'][$row['type']] ?? '') . ')' : '',
            $description,
            $row['locked'] ? ' <em>(' . hsc($labels['locked']) . ')</em>' : ''
        );
    }
}
