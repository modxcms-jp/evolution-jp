<?php
/**
 * 子リソースの一覧（a=120）の状態（ページ・並べ替え）の読み方と、一覧へ戻るときの引数の作り方。
 * 一覧の画面と、公開・非公開・削除・削除の取り消しの processor が同じ規則を使うための 1 か所。
 * 汎用の表の部品（data_table.class.php）には、一覧固有の知識を入れない。
 */
include_once __DIR__ . '/data_table.class.php';

final class ChildDocumentListState
{
    /** URL の orderby に使える値と、対応する SQL の列名（キーが許可リスト） */
    public const SORT_COLUMNS = [
        'id' => 'sc.id',
        'pagetitle' => 'sc.pagetitle',
        'publishedon' => 'sc.publishedon',
        'editedon' => 'sc.editedon',
    ];

    /** 今のリクエストの page・orderby・orderdir を読む。parent は a=120 の id */
    public static function links(int $parent): DataTableLinks
    {
        return DataTableLinks::fromRequest(
            'index.php',
            $parent ? ['a' => 120, 'id' => $parent] : ['a' => 120],
            array_keys(self::SORT_COLUMNS)
        );
    }

    /** 並べ替え中の ORDER BY。並べ替えが無ければ null（呼び出し側が既定の順を使う） */
    public static function orderBy(DataTableLinks $links): ?string
    {
        $column = self::SORT_COLUMNS[$links->orderby()] ?? null;
        if ($column === null) {
            return null;
        }
        return sprintf('%s %s, sc.id DESC', $column, $links->orderdir() === 'desc' ? 'DESC' : 'ASC');
    }

    /** 一覧へ戻る URL（エスケープ前）。今の page と並べ替えを引き継ぐ */
    public static function returnUrl(int $parent): string
    {
        $q = ['r' => 1, 'a' => 120, 'id' => $parent] + self::returnParams();
        return 'index.php?' . http_build_query($q, '', '&');
    }

    /** URL の後ろに足す引数（`&page=2&orderby=…` の形。無ければ空）。JS に入れるときは js_literal() を通す */
    public static function returnQuery(): string
    {
        $q = self::returnParams();
        return $q ? '&' . http_build_query($q, '', '&') : '';
    }

    /** 引き継ぐ引数。page は 1 以上、orderby は許可リスト、orderdir は asc・desc だけ */
    private static function returnParams(): array
    {
        $links = self::links(0);
        $q = [];
        if ($links->page() > 0) {
            $q['page'] = $links->page();
        }
        if ($links->orderby() !== '') {
            $q['orderby'] = $links->orderby();
            $q['orderdir'] = $links->orderdir();
        }
        return $q;
    }
}
