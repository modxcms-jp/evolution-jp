<?php
declare(strict_types=1);

namespace TinyMCE7\Editor;

/**
 * 本文でタグを入力するときの補完（js/editor-setup.js の bindTagAutocomplete）に出す名前を集める。
 *
 * 種類ごとに {name, note, disabled} の並びを返す。note は候補の横に出す説明（無ければ空）。
 * disabled は、書いても中身が出ないもの（無効のチャンク）。本文の中のタグの見せ方を変えるのに使う。
 * - chunk: チャンク（{{name}}）。note は説明
 * - snippet: スニペット（[[name]]）。note は説明
 * - field: リソース変数（site_content の列）とテンプレート変数（[*name*]）。テンプレート変数の note はキャプション
 * - setting: 設定（[(name)]）。読み込んだ設定の名前だけで、値は返さない
 */
final class TagNameRepository
{
    /**
     * @return array<string, array<int, array{name: string, note: string, disabled: bool}>>
     */
    public function all(): array
    {
        return [
            'chunk' => $this->elements('site_htmlsnippets', 'description', 'published=0'),
            'snippet' => $this->elements('site_snippets', 'description'),
            'field' => array_merge($this->resourceFields(), $this->elements('site_tmplvars', 'caption')),
            'setting' => $this->settings(),
        ];
    }

    /**
     * @param string $disabledCondition 無効を表す条件（無効の無い種類は '0'）
     * @return array<int, array{name: string, note: string, disabled: bool}>
     */
    private function elements(string $table, string $noteColumn, string $disabledCondition = '0'): array
    {
        $rows = db()->getRows(db()->select(
            "name,{$noteColumn} AS note,({$disabledCondition}) AS disabled",
            "[+prefix+]{$table}",
            "name!=''",
            'name ASC'
        )) ?: [];

        return array_map(static function (array $row): array {
            // 説明に HTML を書くエレメント（同梱のスニペットの版の表記など）があるので、候補には文字だけを出す
            $note = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$row['note']), ENT_QUOTES, 'UTF-8')));
            return ['name' => (string)$row['name'], 'note' => $note, 'disabled' => (bool)$row['disabled']];
        }, $rows);
    }

    /**
     * @return array<int, array{name: string, note: string, disabled: bool}>
     */
    private function resourceFields(): array
    {
        $columns = db()->getTableMetaData(evo()->getFullTableName('site_content')) ?: [];

        return array_map(static function (string $name): array {
            return ['name' => $name, 'note' => '', 'disabled' => false];
        }, array_keys($columns));
    }

    /**
     * @return array<int, array{name: string, note: string, disabled: bool}>
     */
    private function settings(): array
    {
        $names = array_filter(array_keys(evo()->config), 'is_string');
        sort($names);

        return array_map(static function (string $name): array {
            return ['name' => $name, 'note' => '', 'disabled' => false];
        }, array_values($names));
    }
}
