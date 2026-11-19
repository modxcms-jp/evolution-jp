<?php

/**
 * エレメント（スニペット・チャンク・プラグイン・モジュール・テンプレート・テンプレート変数）の種類の定義
 */
final class EvoElementTypes
{
    /**
     * 種類 => 定義
     *
     * - table: 本体のテーブル（接頭辞なし）
     * - name_field: 名前の列
     * - label: エラーメッセージに出す名前
     * - permissions: delete（削除）・duplicate（複製）に要る権限
     * - events: before_delete・after_delete のイベント名
     * - actions: delete・duplicate のアクション番号
     * - refresh: 削除・複製のあとに読み直すもの（EvoFormFlow の refresh の値。モジュールだけ）。
     *   移動先の画面の番号は EvoFormFlow が持つ（種類のキーは同じ）
     * - copy_fields: 複製で写す列（名前の列を含む）
     * - guid_field: 複製のたびに作り直す GUID の列（モジュールだけ）
     * - relations: 関連テーブル。削除ではこの順に全部消す。
     *   key は本体の id を持つ列、copy は複製で写す列（写さないテーブルは null）
     */
    public static function all()
    {
        return [
            'snippet' => [
                'table' => 'site_snippets',
                'name_field' => 'name',
                'label' => 'snippet',
                'permissions' => ['delete' => 'delete_snippet', 'duplicate' => 'new_snippet'],
                'events' => [
                    'before_delete' => 'OnBeforeSnipFormDelete',
                    'after_delete' => 'OnSnipFormDelete',
                ],
                'actions' => ['delete' => 25, 'duplicate' => 98],
                'copy_fields' => ['name', 'description', 'snippet', 'properties', 'category'],
                'relations' => [],
            ],
            'chunk' => [
                'table' => 'site_htmlsnippets',
                'name_field' => 'name',
                'label' => 'chunk',
                'permissions' => ['delete' => 'delete_chunk', 'duplicate' => 'new_chunk'],
                'events' => [
                    'before_delete' => 'OnBeforeChunkFormDelete',
                    'after_delete' => 'OnChunkFormDelete',
                ],
                'actions' => ['delete' => 80, 'duplicate' => 97],
                'copy_fields' => ['name', 'description', 'snippet', 'category', 'editor_type'],
                'relations' => [],
            ],
            'plugin' => [
                'table' => 'site_plugins',
                'name_field' => 'name',
                'label' => 'plugin',
                'permissions' => ['delete' => 'delete_plugin', 'duplicate' => 'new_plugin'],
                'events' => [
                    'before_delete' => 'OnBeforePluginFormDelete',
                    'after_delete' => 'OnPluginFormDelete',
                ],
                'actions' => ['delete' => 104, 'duplicate' => 105],
                'copy_fields' => [
                    'name', 'description', 'disabled', 'moduleguid', 'plugincode', 'properties', 'category',
                ],
                'relations' => [
                    ['table' => 'site_plugin_events', 'key' => 'pluginid', 'copy' => ['evtid', 'priority']],
                ],
            ],
            'module' => [
                'table' => 'site_modules',
                'name_field' => 'name',
                'label' => 'module',
                'permissions' => ['delete' => 'delete_module', 'duplicate' => 'new_module'],
                'events' => [
                    'before_delete' => 'OnBeforeModFormDelete',
                    'after_delete' => 'OnModFormDelete',
                ],
                'actions' => ['delete' => 110, 'duplicate' => 111],
                'refresh' => 'menu',
                'copy_fields' => [
                    'name', 'description', 'disabled', 'category', 'wrap', 'icon', 'enable_resource',
                    'resourcefile', 'createdon', 'editedon', 'enable_sharedparams', 'properties', 'modulecode',
                ],
                'guid_field' => 'guid',
                'relations' => [
                    ['table' => 'site_module_depobj', 'key' => 'module', 'copy' => ['resource', 'type']],
                    ['table' => 'site_module_access', 'key' => 'module', 'copy' => ['usergroup']],
                ],
            ],
            'template' => [
                'table' => 'site_templates',
                'name_field' => 'templatename',
                'label' => 'template',
                'permissions' => ['delete' => 'delete_template', 'duplicate' => 'new_template'],
                'events' => [
                    'before_delete' => 'OnBeforeTempFormDelete',
                    'after_delete' => 'OnTempFormDelete',
                ],
                'actions' => ['delete' => 21, 'duplicate' => 96],
                'copy_fields' => ['templatename', 'description', 'content', 'category', 'parent'],
                'relations' => [
                    ['table' => 'site_tmplvar_templates', 'key' => 'templateid', 'copy' => ['tmplvarid', 'rank']],
                ],
            ],
            'tmplvar' => [
                'table' => 'site_tmplvars',
                'name_field' => 'name',
                'label' => 'template variable',
                'permissions' => ['delete' => 'delete_template', 'duplicate' => 'edit_template'],
                'events' => [
                    'before_delete' => 'OnBeforeTVFormDelete',
                    'after_delete' => 'OnTVFormDelete',
                ],
                'actions' => ['delete' => 303, 'duplicate' => 304],
                'copy_fields' => [
                    'type', 'name', 'caption', 'description', 'default_text', 'elements', 'rank',
                    'display', 'display_params', 'category',
                ],
                'relations' => [
                    ['table' => 'site_tmplvar_contentvalues', 'key' => 'tmplvarid', 'copy' => null],
                    ['table' => 'site_tmplvar_templates', 'key' => 'tmplvarid', 'copy' => ['templateid']],
                    ['table' => 'site_tmplvar_access', 'key' => 'tmplvarid', 'copy' => ['documentgroup']],
                ],
            ],
        ];
    }

    /**
     * 種類の定義。無い種類は null
     */
    public static function get($type)
    {
        return self::all()[$type] ?? null;
    }

    /**
     * 削除のあとの移動先（一覧の画面）。エスケープ前の文字列
     */
    public static function deletedUrl($type)
    {
        return EvoFormFlow::url(['a' => EvoFormFlow::screen($type, 'close')] + self::refreshQuery($type));
    }

    /**
     * 複製のあとの移動先（複製してできたエレメントの編集の画面）。エスケープ前の文字列
     */
    public static function duplicatedUrl($type, $newId)
    {
        return EvoFormFlow::url(
            ['a' => EvoFormFlow::screen($type, 'edit'), 'id' => (int) $newId] + self::refreshQuery($type)
        );
    }

    private static function refreshQuery($type)
    {
        $refresh = self::get($type)['refresh'] ?? null;
        return $refresh === null ? [] : ['refresh' => $refresh];
    }

    /**
     * アクション番号から種類を引く。$operation は 'delete' か 'duplicate'。無ければ null
     */
    public static function typeByAction($operation, $action)
    {
        foreach (self::all() as $type => $def) {
            if ($def['actions'][$operation] === (int) $action) {
                return $type;
            }
        }
        return null;
    }
}
