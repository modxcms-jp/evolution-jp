<?php

/**
 * リソースツリー（manager/frames/nodes.php）の見え方の知識: 並べ方と、文書の行の見た目・位置を決める値。
 * ツリーの描画（nodes.php）と、保存のあとにツリーを読み直すかの判定（save_resource.processor.php）が使う。
 */
final class ResourceTree
{
    /** ツリーの並べ替えに使える列（ツリーの「並べ替え」の選択肢） */
    private const SORT_COLUMNS = ['isfolder', 'pagetitle', 'id', 'menuindex', 'createdon', 'editedon'];

    /**
     * ツリーに出ない site_content の列（nodes.php・tree.php・tree.js が読まない）。
     * ここに無い列は、ツリーに出るものとして扱う（知らない列が変わったら読み直す側に倒す）。
     * 場面によって出る列は nodeState() が戻す: content はウェブリンクのリンク先、editedon はツリーの名前にしているとき。
     * editedon が並びに効く分は、列の値ではなく兄弟の順（nodeState() の siblings）で見る
     */
    private const UNSHOWN_COLUMNS = [
        'longtitle', 'description', 'link_attributes', 'introtext', 'content',
        'richtext', 'template', 'searchable', 'cacheable', 'donthit', 'haskeywords', 'hasmetatags', 'content_dispo',
        'createdby', 'editedby', 'deletedby', 'publishedby',
        'editedon',
    ];

    /** ツリーの ORDER BY（site_content の別名は sc）。セッションの選択、無ければ既定の設定。選択肢に無い列は menuindex */
    public static function orderBy(): string
    {
        $sortby = sessionv('tree_sortby', config('resource_tree_sortby_default', 'menuindex'));
        if (!in_array($sortby, self::SORT_COLUMNS, true)) {
            $sortby = 'menuindex';
        }
        $sortdir = sessionv('tree_sortdir', 'asc');
        $sortdir = is_string($sortdir) && strtoupper($sortdir) === 'DESC' ? 'DESC' : 'ASC';
        $orderby = sprintf('sc.%s %s', $sortby, $sortdir);

        // Folder sorting gets special setup ;) Add menuindex and pagetitle
        if ($sortby === 'isfolder') {
            $orderby .= ', sc.menuindex ASC';
        }
        // 続きの読み込み（LIMIT）で順序が揺れないよう、一意なキーで並びを確定させる
        return $orderby . ', sc.editedon DESC, sc.id ASC';
    }

    /**
     * 文書 $id の、ツリーでの見た目と位置を決める値。保存の前後でこれが同じなら、ツリーを読み直さなくてよい。
     * - row: ツリーに出ない列を除いた site_content の行
     * - groups: 文書グループ
     * - siblings: 同じ親の文書の ID を、ツリーの並べ方（orderBy()）で並べたもの。
     *   並べ替えの値が同じ兄弟は editedon の新しい順になるので、保存で位置が動いたかはこの順で分かる
     */
    public static function nodeState(int $id): array
    {
        $row = db()->getRow(db()->select('*', '[+prefix+]site_content', 'id=' . $id));
        if (!$row) {
            return [];
        }
        $shown = [config('resource_tree_node_name')];
        if ($row['type'] === 'reference') {
            $shown[] = 'content';
        }

        return [
            'row' => array_diff_key($row, array_flip(array_diff(self::UNSHOWN_COLUMNS, $shown))),
            'groups' => manager()->currentDocumentGroupIds($id),
            'siblings' => db()->getColumn('id', db()->select(
                'sc.id',
                '[+prefix+]site_content AS sc',
                'sc.parent=' . (int)$row['parent'],
                self::orderBy()
            )),
        ];
    }
}
