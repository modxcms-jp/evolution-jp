<?php
/**
 * 管理画面の検索（a=71）の取り出し。条件（ResourceSearchQuery）と探す対象から、件数とそのページの行を返す。
 * SQL はここで組み、画面（manager/actions/main/search.static.php）は表とページ送りを描くだけにする。
 *
 * 探す対象:
 *   resources  リソースだけ。ID・タイトルで並べ替える
 *   elements   エレメントだけ
 *   all        リソースとエレメント
 * elements・all は、リソースと種類ごとのエレメントの SELECT（kind・id・title・hit）を UNION ALL でまとめ、件数・並べ替え・ページ送りを
 * 1 つの問い合わせで行い、そのページの行だけ種類ごとに詳しい列を取る。種類をまたいで ID を比べても意味が無いので、名前・タイトルで並べる。
 * エレメントは語の条件だけで探す（ID・URL・親などはリソースにしか無い。ElementList::searchSelects()）。
 *
 * 語は本文まで探すので、並べ替えの指定が無いときは、タイトル（リソースは pagetitle・longtitle、エレメントは名前）に
 * 語を含むものを先に出し、その中でタイトル順に並べる（本文に含むだけのものの中に埋もれないように）。
 */
class SiteSearch
{
    public const SCOPES = ['resources', 'elements', 'all'];

    /** @var ResourceSearchQuery */
    private $query;
    /** @var string */
    private $scope;
    /** @var array エレメントの絞り込み（ElementList::searchSelects() の $filters） */
    private $elementFilters;
    /** @var string[] リソースの WHERE（AND でつなぐ） */
    private $where;
    /** @var string ID・URL から引けなかったときの理由（言語のキー） */
    private $targetError;
    /** @var string 閲覧できるリソースだけに絞る式（空なら絞らない） */
    private $accessWhere = '';
    /** @var string 本文の抜粋を作ってよいかの式（行の can_view） */
    private $canViewColumn = '1';

    /**
     * @param array{types?: string[], category?: int|null, state?: string} $elementFilters 「詳しく検索」のエレメントの条件
     */
    public function __construct(ResourceSearchQuery $query, string $scope, array $elementFilters = [])
    {
        $this->elementFilters = $elementFilters;
        $this->query = $query;
        $this->scope = in_array($scope, self::SCOPES, true) ? $scope : 'resources';

        $built = $query->toWhere();
        $this->where = $built['where'];
        $this->targetError = $built['error'];

        // 閲覧できるリソースの条件（文書グループ）。checkPermissions() と同じく、use_udperms が無効・管理者なら制限しない。
        // tree_show_protected が無効なら、ツリー（nodes.php の getNodes()）と同じく結果から外す（accessWhere）。
        // 有効なら、ツリーと同じくタイトルは出すが、本文の抜粋は閲覧できる行にだけ作る（can_view。document_data.static.php は拒否するため）
        $accessExpr = '';
        if (config('use_udperms') && !manager()->isAdmin()) {
            $mgrDocgroups = array_map('intval', (array) sessionv('mgrDocgroups', []));
            $access = 'privatemgr=0';
            if ($mgrDocgroups) {
                $access .= sprintf(
                    ' OR id IN (SELECT document FROM %s WHERE document_group IN (%s))',
                    db()->getFullTableName('document_groups'),
                    implode(',', $mgrDocgroups)
                );
            }
            $accessExpr = '(' . $access . ')';
        }
        $this->accessWhere = config('tree_show_protected') ? '' : $accessExpr;
        $this->canViewColumn = config('tree_show_protected') && $accessExpr !== '' ? $accessExpr : '1';
        if ($this->accessWhere !== '') {
            $this->where[] = $this->accessWhere;
        }
    }

    public function scope(): string
    {
        return $this->scope;
    }

    /**
     * 並べ替えのキー（URL の orderby の値） => ORDER BY の列。リクエストの値を直接 SQL に入れない
     *
     * @return array<string, string>
     */
    public function sortColumns(): array
    {
        return $this->scope === 'resources' ? ['id' => 'id', 'pagetitle' => 'pagetitle'] : ['pagetitle' => 'title'];
    }

    /** ID・URL から引けなかったときの理由（言語のキー）。リソースだけを探すときにだけ返す */
    public function targetError(): string
    {
        return $this->scope === 'resources' ? $this->targetError : '';
    }

    /** エレメントを探す語の条件（エレメントを探さないときは空） */
    public function elementConditions(): array
    {
        return $this->scope === 'resources' ? [] : $this->query->textConditions();
    }

    /**
     * エレメントを探せる条件があるか（語か、エレメントの絞り込み）。
     * エレメントを探す範囲なのに無いときは、語を入れるよう案内する
     */
    public function canSearchElements(): bool
    {
        return $this->scope !== 'resources'
            && ($this->elementConditions() || ElementList::hasFilters($this->elementFilters));
    }

    /**
     * 数字だけで探したとき、その数字を文字として含むリソースの件数（"20" と囲めば文字として探せる、という案内のため）。
     * リソースだけを探すときにだけ数える。リンク先の検索と同じく、文書グループの条件も掛ける
     */
    public function asTextCount(): int
    {
        $bareNumber = $this->query->bareNumber();
        if ($this->scope !== 'resources' || $bareNumber === '') {
            return 0;
        }
        $where = (new ResourceSearchQuery('"' . $bareNumber . '"'))->toWhere()['where'];
        if ($this->accessWhere !== '') {
            $where[] = $this->accessWhere;
        }
        return (int) db()->getValue(db()->select('COUNT(id)', '[+prefix+]site_content', implode(' AND ', $where)));
    }

    /**
     * 件数を数えてから、そのページの行だけを取る。
     * 行は kind（resource か、エレメントの種類）付きで、ページの並びのとおり。
     * リソースの行は site_content の列（本文は先頭だけの content_head）と can_view、エレメントの行は ElementList::rowsByIds() の形。
     *
     * @param string $orderby sortColumns() のキー（空なら既定の、タイトルに当たるものが先・タイトル昇順）
     * @param string $orderdir asc / desc
     * @param string[] $terms タイトルに当たるか・本文の中の当たった行を見る語（ResourceSearchQuery::terms()）
     * @return array{total: int, pager: DataTablePager, rows: array}
     */
    public function fetch(string $orderby, string $orderdir, int $page, int $perPage, array $terms): array
    {
        $dir = $orderdir === 'desc' ? 'DESC' : 'ASC';
        $columns = $this->sortColumns();
        $order = [];
        if ($this->scope === 'resources') {
            $sort = isset($columns[$orderby])
                ? sprintf('%s %s, id %s', $columns[$orderby], $dir, $dir)
                : sprintf('%s DESC, pagetitle ASC, id ASC', $this->titleHit($terms));
            $total = (int) db()->getValue(
                db()->select('COUNT(id)', '[+prefix+]site_content', implode(' and ', $this->where))
            );
            $pager = new DataTablePager($total, $perPage, $page);
            $rs = db()->select(
                $this->resourceFields(),
                '[+prefix+]site_content',
                implode(' and ', $this->where),
                $sort,
                $this->limit($pager)
            );
            $rows = [];
            while ($row = db()->getRow($rs)) {
                $rows[] = ['kind' => 'resource'] + $row;
            }
            return ['total' => $total, 'pager' => $pager, 'rows' => $rows];
        }

        $parts = ElementList::searchSelects($this->elementConditions(), $terms, $this->elementFilters);
        if ($this->scope === 'all') {
            array_unshift($parts, sprintf(
                "SELECT 'resource' AS kind, id, %s AS title, %s AS hit FROM [+prefix+]site_content WHERE %s",
                ElementList::sortableTitle('pagetitle'),
                $this->titleHit($terms),
                implode(' AND ', $this->where)
            ));
        }
        if (!$parts) {
            return ['total' => 0, 'pager' => new DataTablePager(0, $perPage, 1), 'rows' => []];
        }
        $union = '(' . implode(' UNION ALL ', $parts) . ') AS hits';
        $total = (int) db()->getValue(db()->select('COUNT(*)', $union));
        $pager = new DataTablePager($total, $perPage, $page);
        $sort = isset($columns[$orderby])
            ? sprintf('title %s, kind ASC, id %s', $dir, $dir)
            : 'hit DESC, title ASC, kind ASC, id ASC';
        $rs = db()->select('kind, id', $union, '', $sort, $this->limit($pager));
        $idsByKind = [];
        while ($row = db()->getRow($rs)) {
            $idsByKind[$row['kind']][] = (int) $row['id'];
            $order[] = [$row['kind'], (int) $row['id']];
        }

        $details = [];
        foreach ($idsByKind as $kind => $ids) {
            $details[$kind] = $kind === 'resource' ? $this->resourcesByIds($ids) : ElementList::rowsByIds($kind, $ids, $terms);
        }
        $rows = [];
        foreach ($order as [$kind, $id]) {
            if (isset($details[$kind][$id])) {
                $rows[] = ['kind' => $kind] + $details[$kind][$id];
            }
        }
        return ['total' => $total, 'pager' => $pager, 'rows' => $rows];
    }

    /** リソースのタイトル（pagetitle・longtitle）に語のどれかを含むかの式 */
    private function titleHit(array $terms): string
    {
        return ResourceSearchQuery::likeAny(['pagetitle', 'longtitle'], $terms);
    }

    /** リソースの行の列。本文は抜粋に使う先頭だけを取る（大きな本文を全件ぶん読まない） */
    private function resourceFields(): string
    {
        return 'id, contenttype, pagetitle, longtitle, description, SUBSTRING(content, 1, 5000) AS content_head, '
            . $this->canViewColumn . ' AS can_view, deleted, published, isfolder, type';
    }

    /** @param int[] $ids */
    private function resourcesByIds(array $ids): array
    {
        $rs = db()->select(
            $this->resourceFields(),
            '[+prefix+]site_content',
            'id IN (' . implode(',', array_map('intval', $ids)) . ')'
        );
        $rows = [];
        while ($row = db()->getRow($rs)) {
            $rows[(int) $row['id']] = $row;
        }
        return $rows;
    }

    private function limit(DataTablePager $pager): string
    {
        return $pager->limit() > 0 ? sprintf('%d, %d', $pager->offset(), $pager->limit()) : '';
    }
}
