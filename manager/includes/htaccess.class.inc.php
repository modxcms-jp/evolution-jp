<?php

/**
 * フレンドリー URL 用の .htaccess を置く（インストーラーと設定の保存の共通処理）。
 * 言語の文言は返さない。結果の定数を呼び出し側が文言やログに変える。
 *
 * 本体が書くのは、sample.htaccess の目印（# BEGIN MODX 〜 # END MODX）で囲んだ部分だけ。
 * 既存の .htaccess に書き換えの規則が無いときは、その部分を末尾に足す。
 * 目印の無い場所に利用者が書いた規則（自分で書いた RewriteEngine など）には触らない。
 */
class EvoHtaccess
{
    const SKIPPED = 'skipped';            // IIS のため何もしない
    const EXISTS = 'exists';              // 既にある（RewriteBase の書き換えが済んだ場合を含む）
    const PLACED = 'placed';              // sample.htaccess から置いた
    const ADDED = 'added';                // 既存の .htaccess に、目印で囲んだ書き換えの規則を足した
    const NO_SAMPLE = 'no_sample';        // .htaccess も sample.htaccess も無い
    const NO_REWRITE = 'no_rewrite';      // 既存の .htaccess に書き換えの規則が無く、足せなかった
    const FAILED_PLACE = 'failed_place';  // sample.htaccess を .htaccess にできなかった
    const FAILED_WRITE = 'failed_write';  // RewriteBase を書き込めなかった

    const MARKER_BEGIN = '# BEGIN MODX';
    const MARKER_END = '# END MODX';

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
                return self::addRules($htaccess, $body, $sample, $dir);
            }
            if ($rewrite_base && is_writable($htaccess)) {
                $rs = file_put_contents($htaccess, self::replaceRewriteBase($body, $dir));
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
            $body = self::replaceRewriteBase(file_get_contents($htaccess), $dir);
            if (!@file_put_contents($htaccess, $body)) {
                return self::FAILED_WRITE;
            }
        }
        return self::PLACED;
    }

    /**
     * 書き換えの規則が無い既存の .htaccess の末尾に、sample.htaccess の目印で囲んだ部分を足す
     */
    private static function addRules($htaccess, $body, $sample, $dir)
    {
        $block = is_file($sample) ? self::markedBlock(file_get_contents($sample)) : '';
        if ($block === '' || !is_writable($htaccess)) {
            return self::NO_REWRITE;
        }
        $block = preg_replace('@RewriteBase[^\r\n]+@', 'RewriteBase ' . $dir, $block);
        $separator = $body === '' || substr($body, -1) === "\n" ? '' : "\n";
        if (!file_put_contents($htaccess, $body . $separator . "\n" . $block . "\n")) {
            return self::NO_REWRITE;
        }
        return self::ADDED;
    }

    /**
     * RewriteBase を合わせる。目印があれば、その中だけを書き換える
     */
    private static function replaceRewriteBase($body, $dir)
    {
        $block = self::markedBlock($body);
        if ($block === '') {
            return preg_replace('@RewriteBase[^\r\n]+@', 'RewriteBase ' . $dir, $body);
        }
        return str_replace($block, preg_replace('@RewriteBase[^\r\n]+@', 'RewriteBase ' . $dir, $block), $body);
    }

    /**
     * 目印（# BEGIN MODX 〜 # END MODX）で囲んだ部分。目印を含む。無ければ空文字
     */
    private static function markedBlock($body)
    {
        $pattern = '@^' . preg_quote(self::MARKER_BEGIN, '@') . '\r?$.*?^' . preg_quote(self::MARKER_END, '@') . '\r?$@ms';
        return preg_match($pattern, $body, $m) ? $m[0] : '';
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
