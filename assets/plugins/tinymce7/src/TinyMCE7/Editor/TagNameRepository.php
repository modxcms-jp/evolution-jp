<?php
declare(strict_types=1);

namespace TinyMCE7\Editor;

/**
 * 本文のタグの補完と色付け（js/tag-assist.js）に使う名前を集める。
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
            'chunk' => $this->elements('site_htmlsnippets', 'description', 'published'),
            'snippet' => $this->elements('site_snippets', 'description'),
            'field' => array_merge($this->resourceFields(), $this->elements('site_tmplvars', 'caption')),
            'setting' => $this->settings(),
        ];
    }

    /**
     * @param string $publishedColumn 0 のとき無効を表す列（無効の無い種類は空）。本体は published=1 のチャンクだけを読む
     * @return array<int, array{name: string, note: string, disabled: bool}>
     */
    private function elements(string $table, string $noteColumn, string $publishedColumn = ''): array
    {
        $columns = "name,{$noteColumn} AS note" . ($publishedColumn !== '' ? ",{$publishedColumn} AS published" : '');
        $rows = db()->getRows(db()->select(
            $columns,
            "[+prefix+]{$table}",
            "name!=''",
            'name ASC'
        )) ?: [];

        return array_map(static function (array $row) use ($publishedColumn): array {
            // 説明に HTML を書くエレメント（同梱のスニペットの版の表記など）があるので、候補には文字だけを出す。
            // 不正な UTF-8 では /u の置き換えが null を返すので、文字列で受ける（出口の json_encode が置き換える）
            $note = trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$row['note']), ENT_QUOTES, 'UTF-8')));
            return [
                'name' => (string)$row['name'],
                'note' => $note,
                'disabled' => $publishedColumn !== '' && (int)$row['published'] === 0,
            ];
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
