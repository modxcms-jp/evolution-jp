<?php

/**
 * フレンドリー URL 用の .htaccess を置く（インストーラーと設定の保存の共通処理）。
 * 言語の文言は返さない。結果の定数を呼び出し側が文言やログに変える。
 */
class EvoHtaccess
{
    const SKIPPED = 'skipped';            // IIS のため何もしない
    const EXISTS = 'exists';              // 既にある（RewriteBase の書き換えが済んだ場合を含む）
    const PLACED = 'placed';              // sample.htaccess から置いた
    const NO_SAMPLE = 'no_sample';        // .htaccess も sample.htaccess も無い
    const NO_REWRITE = 'no_rewrite';      // 既存の .htaccess に RewriteEngine が無い
    const FAILED_PLACE = 'failed_place';  // sample.htaccess を .htaccess にできなかった
    const FAILED_WRITE = 'failed_write';  // RewriteBase を書き込めなかった

    /**
     * @param string $base_path       .htaccess を置くディレクトリ（MODX_BASE_PATH）
     * @param string $base_url        サイトのベース URL
     * @param bool   $rewrite_base    既にある .htaccess の RewriteBase を base_url に合わせるか
     * @param string $server_software serverv('SERVER_SOFTWARE')
     */
    public static function ensure($base_path, $base_url, $rewrite_base, $server_software = '')
    {
        if (self::isIis($server_software)) {
            return self::SKIPPED;
        }

        $htaccess = $base_path . '.htaccess';
        $sample = $base_path . 'sample.htaccess';
        $dir = '/' . trim($base_url, '/');

        if (is_file($htaccess)) {
            $body = file_get_contents($htaccess);
            if (strpos($body, 'RewriteEngine') === false) {
                return self::NO_REWRITE;
            }
            if ($rewrite_base && is_writable($htaccess)) {
                $rs = file_put_contents($htaccess, preg_replace('@RewriteBase.+@', 'RewriteBase ' . $dir, $body));
                if (!$rs) {
                    return self::FAILED_WRITE;
                }
            }
            return self::EXISTS;
        }
        if (!is_file($sample)) {
            return self::NO_SAMPLE;
        }
        if (!@rename($sample, $htaccess)) {
            return self::FAILED_PLACE;
        }
        if ($base_url !== '/') {
            $body = preg_replace('@RewriteBase.+@', 'RewriteBase ' . $dir, file_get_contents($htaccess));
            if (!@file_put_contents($htaccess, $body)) {
                return self::FAILED_WRITE;
            }
        }
        return self::PLACED;
    }

    /**
     * IIS か（IIS は .htaccess を使わない）
     *
     * @param string $server_software serverv('SERVER_SOFTWARE')
     */
    private static function isIis($server_software)
    {
        return strpos((string) $server_software, 'IIS') !== false;
    }
}
