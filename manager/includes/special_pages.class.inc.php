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
     * 削除も非公開もできないページ（サイトの設定で使っているページ）の設定名。文書がどれに当たるかを調べる順（トップページが先）。
     * 設定が空（0）のものは止めない。権限外告知のページが空のとき本体は error_page を使うが、それは error_page として止まる。
     * 言語ファイルに、各設定名で cannot_delete_<設定名>・cannot_delete_child_<設定名>・cannot_unpublish_<設定名>・
     * cannot_pub_dates_<設定名> が要る（判定は DocAPI::protectedPageSetting()、画面は actionConfig() の ids が引く）
     */
    public static function protectedSettings()
    {
        return array_merge(['site_start'], array_keys(self::all()));
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
