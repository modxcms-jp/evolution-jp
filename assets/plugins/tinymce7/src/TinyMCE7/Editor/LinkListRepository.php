<?php
declare(strict_types=1);

namespace TinyMCE7\Editor;

/**
 * リンクのダイアログ（TinyMCE の link_list）に並べる、サイト内の文書の木を作る。
 * 同じ範囲の文書について、公開側の URL も返す（url()。「リンクを開く」用）。
 *
 * 利用者が管理画面で見られる文書（文書グループと allowed_parents の範囲の中）だけを、メニュー名（無ければページタイトル）と ID で並べる。
 * 値は文書を移してもリンクが切れない `[~id~]`。未公開の文書は、下の文書ごと出さない（公開側でたどれないため）。削除済みは出さない。
 * url() は未公開の文書も返す（本文にある未公開の文書へのリンクの中身を確かめられるように）。
 * 件数が MAX_ITEMS を超えるときは、MAX_ITEMS 件に収まる深さまでで打ち切る（最上位だけで超えるときは先頭の MAX_ITEMS 件）。
 */
final class LinkListRepository
{
    public const MAX_ITEMS = 1000;

    private string $truncatedLabel;

    public function __construct(string $truncatedLabel = '')
    {
        $this->truncatedLabel = $truncatedLabel;
    }

    /**
     * @return array<int, array<string, mixed>> link_list にそのまま渡せる {title, value} と {title, menu} の並び
     */
    public function tree(): array
    {
        $rows = db()->getRows(db()->select(
            'sc.id,sc.pagetitle,sc.menutitle,sc.parent,sc.published',
            '[+prefix+]site_content AS sc',
            $this->visibleCondition(),
            'sc.menuindex ASC, sc.id ASC'
        )) ?: [];

        $byId = [];
        foreach ($rows as $row) {
            $byId[(int)$row['id']] = true;
        }

        $children = [];
        foreach ($rows as $row) {
            $parent = (int)$row['parent'];
            // 親が見えない（削除済み・権限なし・allowed_parents の範囲外）文書は最上位に置く
            $children[isset($byId[$parent]) ? $parent : 0][] = $row;
        }

        $children = $this->publishedOnly($children);
        $depthLimit = $this->depthLimit($children);
        $items = $this->build($children, 0, $depthLimit);
        // 打ち切ったことを末尾に示す。選んでも URL が空になるだけの項目
        if ($depthLimit !== null && $this->truncatedLabel !== '') {
            $items[] = ['title' => $this->truncatedLabel, 'value' => ''];
        }

        return $items;
    }

    /**
     * 一覧に出る文書の公開側の URL（リンクを開くとき用）。一覧に出ない文書（無い・削除済み・権限なし・範囲外）は null。
     */
    public function url(int $id): ?string
    {
        // [~0~] はトップページ（makeUrl() と同じ読み替え）。見える範囲の確かめはトップページの文書で行う
        if ($id === 0) {
            $id = (int)evo()->config('site_start');
        }
        $found = db()->getValue(db()->select(
            'sc.id',
            '[+prefix+]site_content AS sc',
            'sc.id=' . $id . ' AND ' . $this->visibleCondition()
        ));

        if (!$found) {
            return null;
        }
        $url = evo()->makeUrl($id, '', '', 'full');

        return is_string($url) && $url !== '' ? $url : null;
    }

    private function visibleCondition(): string
    {
        return 'sc.deleted=0' . $this->accessCondition() . $this->allowedParentsCondition();
    }

    /**
     * 文書グループで絞る条件。use_udperms が無効、または管理者なら絞らない。
     * 式の形はツリー（manager/frames/nodes.php の tree_visibility_condition()）と同じ。ただし use_udperms が無効なら絞らない
     * （ツリーは use_udperms を見ずに privatemgr で絞る。この一覧は保存の確かめ ManagerAPI::canOpenWithDocumentGroups() と同じ）。
     * tree_show_protected は見ない。一覧に出すのはリンクを張れる文書だけ。
     */
    private function accessCondition(): string
    {
        if (!evo()->config('use_udperms') || manager()->isAdmin()) {
            return '';
        }

        $groups = array_filter(array_map('intval', (array)sessionv('mgrDocgroups', [])));
        if (!$groups) {
            return ' AND sc.privatemgr=0';
        }

        return sprintf(
            ' AND (sc.privatemgr=0 OR sc.id IN (SELECT document FROM %s WHERE document_group IN (%s)))',
            db()->getFullTableName('document_groups'),
            implode(',', $groups)
        );
    }

    /**
     * allowed_parents の範囲で絞る条件。文書グループとは別の軸で、管理者でも設定があれば絞る（ツリーと同じ）。
     * 範囲の祖先は出さない。
     */
    private function allowedParentsCondition(): string
    {
        $cond = manager()->allowedDocsCondition('sc');

        return $cond === '' ? '' : ' AND ' . $cond;
    }

    /**
     * 根からたどって、公開中の文書だけを残す。未公開の文書のところで止めるので、その下の文書（公開中のものも）は残らない
     *
     * @param array<int, array<int, array>> $children 親の id => 子の行
     * @return array<int, array<int, array>>
     */
    private function publishedOnly(array $children): array
    {
        $result = [];
        $parents = [0];
        while ($parents) {
            $parent = array_shift($parents);
            foreach ($children[$parent] ?? [] as $row) {
                if ((int)$row['published'] !== 1) {
                    continue;
                }
                $result[$parent][] = $row;
                $parents[] = (int)$row['id'];
            }
        }

        return $result;
    }

    /**
     * 全体が MAX_ITEMS 件以内なら制限なし（null）。超えるときは、深さ N までの件数が MAX_ITEMS 以内になる最大の N（最小 1）。
     *
     * @param array<int, array<int, array>> $children 親の id => 子の行
     */
    private function depthLimit(array $children): ?int
    {
        $counts = [];
        $level = $children[0] ?? [];
        $depth = 1;
        while ($level) {
            $counts[$depth] = count($level);
            $next = [];
            foreach ($level as $row) {
                foreach ($children[(int)$row['id']] ?? [] as $child) {
                    $next[] = $child;
                }
            }
            $level = $next;
            $depth++;
        }

        if (array_sum($counts) <= self::MAX_ITEMS) {
            return null;
        }

        $limit = 1;
        $total = 0;
        foreach ($counts as $depth => $count) {
            $total += $count;
            if ($total > self::MAX_ITEMS) {
                break;
            }
            $limit = $depth;
        }

        return $limit;
    }

    /**
     * @param array<int, array<int, array>> $children
     */
    private function build(array $children, int $parent, ?int $depthLimit, int $depth = 1): array
    {
        $items = [];
        foreach ($children[$parent] ?? [] as $row) {
            if ($depth === 1 && count($items) >= self::MAX_ITEMS) {
                break;
            }
            $item = ['title' => $this->title($row), 'value' => '[~' . (int)$row['id'] . '~]'];
            $sub = $depthLimit === null || $depth < $depthLimit
                ? $this->build($children, (int)$row['id'], $depthLimit, $depth + 1)
                : [];
            if ($sub === []) {
                $items[] = $item;
                continue;
            }
            // 子のある文書は入れ子の見出しになり、見出し自身は選べないので、先頭に自分を置く
            $items[] = ['title' => $item['title'], 'menu' => array_merge([$item], $sub)];
        }

        return $items;
    }

    private function title(array $row): string
    {
        $menutitle = (string)$row['menutitle'];
        return sprintf('%s (%d)', $menutitle !== '' ? $menutitle : (string)$row['pagetitle'], (int)$row['id']);
    }
}
