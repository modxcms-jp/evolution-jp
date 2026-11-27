<?php

/**
 * リソースの右クリックメニューの項目の定義。
 * リソースツリー（manager/frames/tree.php）と子リソースの一覧（manager/actions/document/resources_list.static.php）が、
 * この表から同じ項目を描く。描く部品（マークアップ・開き方）は入口ごとに持つ。
 *
 * 項目を足す・権限や出し分けを変えるときは ITEMS だけを直す。押したあとの処理は、
 * ツリーは tree.php の menuHandler()、一覧は document-actions.js の EvoDocumentActions.menu() がキーで分ける。
 */
final class DocumentMenu
{
    /**
     * キー（action の番号か名前。ツリーの項目の id は「item<キー>」）=> 定義。並び順がメニューの並び順。
     *   group:   区切りのグループ。前の項目とグループが変わる所に区切り線を引く
     *   lang:    項目の文言の言語キー
     *   icon:    アイコンの style() のキー
     *   perms:   要る権限（すべて要る）。1 つでも無ければ項目を出さない
     *   setting: 有効なときだけ項目を出す設定名（無ければ常に出す）
     *   hide:    リソースの状態で隠す条件の並び。どれか 1 つの条件のキーがすべて状態と一致したら隠す。
     *            状態のキーは published・deleted・draft（下書きがある）・canPublish（利用者に publish_document の権限がある）。
     *            当てはめるのは document-actions.js の EvoDocumentActions.itemShown()
     */
    private const ITEMS = [
        '27' => [
            'group' => 'edit', 'lang' => 'edit_resource', 'icon' => 'icons_edit_document',
            'perms' => ['edit_document'],
            'hide' => [['draft' => true, 'canPublish' => false]],
        ],
        '120' => [
            'group' => 'edit', 'lang' => 'view_child_resources_in_container', 'icon' => 'icons_table',
            'perms' => ['view_document'],
        ],
        '4' => [
            'group' => 'edit', 'lang' => 'create_resource_here', 'icon' => 'icons_new_document',
            'perms' => ['new_document'],
        ],
        '51' => [
            'group' => 'edit', 'lang' => 'move_resource', 'icon' => 'icons_move_document',
            'perms' => ['move_document', 'save_document', 'publish_document'],
        ],
        '94' => [
            'group' => 'edit', 'lang' => 'resource_duplicate', 'icon' => 'icons_resource_duplicate',
            'perms' => ['new_document', 'save_document'],
        ],
        '61' => [
            'group' => 'state', 'lang' => 'publish_resource', 'icon' => 'icons_publish_document',
            'perms' => ['publish_document'],
            'hide' => [['published' => true], ['deleted' => true]],
        ],
        '62' => [
            'group' => 'state', 'lang' => 'unpublish_resource', 'icon' => 'icons_unpublish_resource',
            'perms' => ['publish_document'],
            'hide' => [['published' => false], ['deleted' => true]],
        ],
        '6' => [
            'group' => 'state', 'lang' => 'delete_resource', 'icon' => 'icons_delete',
            'perms' => ['delete_document'],
            'hide' => [['deleted' => true]],
        ],
        '63' => [
            'group' => 'state', 'lang' => 'undelete_resource', 'icon' => 'icons_undelete_resource',
            'perms' => ['delete_document'],
            'hide' => [['deleted' => false]],
        ],
        '64' => [
            'group' => 'state', 'lang' => 'delete_resource_complete', 'icon' => 'icons_delete_complete',
            'perms' => ['delete_document', 'empty_trash'],
            'hide' => [['deleted' => false]],
        ],
        '72' => [
            'group' => 'weblink', 'lang' => 'create_weblink_here', 'icon' => 'icons_weblink',
            'perms' => ['new_document'],
        ],
        'createDraft' => [
            'group' => 'view', 'lang' => 'create_draft', 'icon' => 'icons_new_document',
            'perms' => [], 'setting' => 'enable_draft',
            'hide' => [['draft' => true]],
        ],
        'editDraft' => [
            'group' => 'view', 'lang' => 'edit_draft', 'icon' => 'icons_edit_document',
            'perms' => [], 'setting' => 'enable_draft',
            'hide' => [['draft' => false]],
        ],
        '3' => [
            'group' => 'view', 'lang' => 'resource_overview', 'icon' => 'icons_information',
            'perms' => ['view_document'],
        ],
        'pv' => [
            'group' => 'view', 'lang' => 'preview_resource', 'icon' => 'icons_preview_resource',
            'perms' => [],
        ],
    ];

    /**
     * 今の利用者に、その項目の操作を出してよいか（権限と設定）。処理側（processor）が同じ決まりで断るのにも使う
     */
    public static function permitted(string $key): bool
    {
        $item = self::ITEMS[$key] ?? null;
        if (!$item) {
            return false;
        }
        foreach ($item['perms'] as $perm) {
            if (!evo()->hasPermission($perm)) {
                return false;
            }
        }
        return empty($item['setting']) || (bool) config($item['setting']);
    }

    /**
     * 今の利用者に出す項目を、並び順で返す。
     * 各要素は key（文字列）・text（文言。言語ファイルの値のまま）・icon（画像の URL）・separator（この項目の前に区切り線を引くか）
     */
    public static function items(): array
    {
        $items = [];
        $group = null;
        foreach (self::ITEMS as $key => $item) {
            $key = (string) $key;
            if (!self::permitted($key)) {
                continue;
            }
            $items[] = [
                'key' => $key,
                'text' => lang($item['lang']),
                'icon' => style($item['icon']),
                'separator' => $group !== null && $group !== $item['group'],
            ];
            $group = $item['group'];
        }
        return $items;
    }

    /**
     * 状態での出し分けに使う値（document-actions.js へ渡す。DocAPI の actionConfig() が EvoDocumentActions.config.menu に入れる）。
     * hide は「キー => 隠す条件の並び」、canPublish は利用者に publish_document の権限があるか
     */
    public static function stateConfig(): array
    {
        $hide = [];
        foreach (self::ITEMS as $key => $item) {
            if (!empty($item['hide'])) {
                $hide[(string) $key] = $item['hide'];
            }
        }
        return [
            'hide' => $hide,
            'canPublish' => (bool) evo()->hasPermission('publish_document'),
        ];
    }
}
