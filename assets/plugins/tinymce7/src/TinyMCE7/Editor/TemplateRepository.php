<?php
declare(strict_types=1);

namespace TinyMCE7\Editor;

use TinyMCE7\Config\PreferenceResolver;

/**
 * 「ひな形の挿入」ダイアログに並べるひな形を集める。
 *
 * 並び順は、設定したリソース ID 順 → 設定したチャンク名順 → カテゴリ内のチャンク（名前順）。
 * 本文は MODX タグを含めて保存されているまま返す（挿入後に通常どおり解析される）。
 */
final class TemplateRepository
{
    private PreferenceResolver $preferences;

    public function __construct(?PreferenceResolver $preferences = null)
    {
        $this->preferences = $preferences ?? new PreferenceResolver();
    }

    /**
     * @return array<int, array{title: string, description: string, content: string}>
     */
    public function all(): array
    {
        return array_merge(
            $this->fromResources($this->preferences->detectTemplateDocIds()),
            $this->fromChunks(
                $this->preferences->detectTemplateChunkNames(),
                $this->preferences->detectTemplateCategory()
            )
        );
    }

    /**
     * @param int[] $ids
     */
    private function fromResources(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // 公開状態・管理画面のアクセス権の判定は getDocuments() に任せる
        $docs = evo()->getDocuments($ids, 1, 0, 'id,pagetitle,menutitle,description,content', '', '');
        if (!is_array($docs)) {
            return [];
        }

        $byId = [];
        foreach ($docs as $doc) {
            $byId[(int)$doc['id']] = $doc;
        }

        $templates = [];
        foreach ($ids as $id) {
            if (!isset($byId[$id])) {
                continue;
            }
            $doc = $byId[$id];
            $templates[] = $this->item(
                (string)($doc['menutitle'] ?: $doc['pagetitle']),
                (string)$doc['description'],
                (string)$doc['content']
            );
        }

        return $templates;
    }

    /**
     * @param string[] $names
     */
    private function fromChunks(array $names, string $category): array
    {
        $templates = [];
        $seen = [];

        if ($names !== []) {
            $rows = db()->getRows(db()->select(
                'id,name,description,snippet',
                '[+prefix+]site_htmlsnippets',
                sprintf("name IN ('%s') AND published=1", implode("','", db()->escape($names)))
            ));
            $byName = [];
            foreach ($rows ?: [] as $row) {
                $byName[$row['name']] = $row;
            }
            foreach ($names as $name) {
                if (!isset($byName[$name])) {
                    continue;
                }
                $seen[(int)$byName[$name]['id']] = true;
                $templates[] = $this->chunkItem($byName[$name]);
            }
        }

        if ($category !== '') {
            $rows = db()->getRows(db()->select(
                'sn.id,sn.name,sn.description,sn.snippet',
                '[+prefix+]site_htmlsnippets sn INNER JOIN [+prefix+]categories cat ON cat.id=sn.category',
                sprintf("cat.category='%s' AND sn.published=1", db()->escape($category)),
                'sn.name ASC'
            ));
            foreach ($rows ?: [] as $row) {
                if (isset($seen[(int)$row['id']])) {
                    continue;
                }
                $templates[] = $this->chunkItem($row);
            }
        }

        return $templates;
    }

    private function chunkItem(array $row): array
    {
        $description = (string)$row['description'];
        // チャンクの説明は未入力だと既定値 "Chunk" が入るので、ひな形の説明としては出さない
        if ($description === 'Chunk') {
            $description = '';
        }

        return $this->item((string)$row['name'], $description, (string)$row['snippet']);
    }

    private function item(string $title, string $description, string $content): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'content' => $content,
        ];
    }
}
