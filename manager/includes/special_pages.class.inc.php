<?php

/**
 * エラーページ・権限外告知のページ・メンテナンス中ページ（グローバル設定の文書 ID）の確認
 */
final class EvoSpecialPages
{
    /**
     * 設定キー => [言語キーの接頭辞, 設定名の言語キー]
     */
    public static function all()
    {
        return [
            'error_page' => ['errorpage', 'errorpage_title'],
            'unauthorized_page' => ['unauthorizedpage', 'unauthorizedpage_title'],
            'site_unavailable_page' => ['siteunavailablepage', 'siteunavailable_page_title'],
        ];
    }

    /**
     * 'missing'（無い）/ 'deleted'（削除済み）/ 'unpublished'（非公開）/ ''（問題なし）
     * $doc には文書の行（無いときは空）が入る
     */
    public static function problem($id, &$doc = null)
    {
        $doc = db()->getRow(
            db()->select(
                'deleted, published, privateweb',
                '[+prefix+]site_content',
                where('id', '=', (int) $id)
            )
        );
        if (!$doc) {
            return 'missing';
        }
        if ($doc['deleted']) {
            return 'deleted';
        }
        return $doc['published'] ? '' : 'unpublished';
    }
}
