<?php

/**
 * エレメント管理（a=76）の一覧。種類ごとの違い（テーブル・名前の列・編集の action・権限・状態の列）は types() の 1 か所に置く。
 * 取り出し（rows()）と描く処理（render()）を分け、描く処理は引数だけから HTML を作る。
 */
class ElementList
{
    /**
     * 種類の定義。tab のある種類は、エレメント管理に自分のタブを持つ（label・message・newLabel は言語のキー）。
     */
    public static function types(): array
    {
        return [
            'template' => [
                'table' => 'site_templates',
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
            '',
            implode(', ', $orderBy)
        );

        $rows = [];
        while ($row = db()->getRow($rs)) {
            $rows[] = [
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
     *   id              リストの id（絞り込みの入力欄の data-element-filter の値）
     *   showType        各行に種類の名前を出す（エレメント一覧のタブ）
     *   categoryDelete  カテゴリーの見出しに削除のリンクを出す
     *   labels          no_category / locked / delete_category / empty / no_match と、種類の名前（type => 名前）
     *   rtl             右から左の言語
     */
    public static function render(array $rows, array $options): string
    {
        $labels = $options['labels'];
        $listId = $options['id'];

        if (!$rows) {
            return sprintf('<p>%s</p>', hsc($labels['empty']));
        }

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['catid']][] = $row;
        }

        $html = sprintf('<ul class="element-list" id="%s">', hsc($listId));
        foreach ($groups as $catid => $items) {
            $category = $catid ? $items[0]['category'] : $labels['no_category'];
            $html .= sprintf(
                '<li class="element-category" data-category="%s"><strong>%s</strong>%s<ul>',
                hsc($category),
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
        $html .= '</ul>';
        $html .= sprintf(
            '<p class="element-filter-empty" id="%s-empty" hidden>%s</p>',
            hsc($listId),
            hsc($labels['no_match'])
        );
        return $html;
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
            '<li data-filter-text="%s"><span%s><a href="index.php?id=%d&amp;a=%d">%s</a>%s</span>%s %s%s</li>',
            hsc(implode(' ', [$row['id'], $row['name'], $row['description']])),
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
