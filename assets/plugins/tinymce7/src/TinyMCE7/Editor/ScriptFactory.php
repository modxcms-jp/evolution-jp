<?php
declare(strict_types=1);

namespace TinyMCE7\Editor;

final class ScriptFactory
{
    private const BUNDLED_TINYMCE = 'assets/plugins/tinymce7/tinymce/js/tinymce/tinymce.min.js';

    public function tinymceScriptUrl(array $config = []): string
    {
        if (!empty($config['tinymce_script_url']) && is_string($config['tinymce_script_url'])) {
            return $config['tinymce_script_url'];
        }

        if ($this->usesBundledTinymce($config)) {
            return $this->versionedUrl(
                MODX_BASE_URL . self::BUNDLED_TINYMCE,
                MODX_BASE_PATH . self::BUNDLED_TINYMCE
            );
        }

        return 'https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js';
    }

    /**
     * 同梱の tinymce.min.js を読み込む設定か（tinymce_script_url の指定が無く、tinymce_use_local で同梱版がある）。
     */
    public function usesBundledTinymce(array $config = []): bool
    {
        if (!empty($config['tinymce_script_url']) && is_string($config['tinymce_script_url'])) {
            return false;
        }

        return !empty($config['tinymce_use_local']) && is_file(MODX_BASE_PATH . self::BUNDLED_TINYMCE);
    }

    /**
     * TinyMCE の cache_suffix に渡す値（同梱版の更新時刻）。同梱版を使わないとき、取得できないときは空文字。
     * TinyMCE が後から読むプラグイン・スキン・言語ファイルの URL に付く。
     */
    public function bundledCacheSuffix(array $config = []): string
    {
        if (!$this->usesBundledTinymce($config)) {
            return '';
        }

        $mtime = filemtime(MODX_BASE_PATH . self::BUNDLED_TINYMCE);

        return $mtime === false ? '' : 'v=' . $mtime;
    }

    /**
     * ローカルのファイルの更新時刻を v= で付けた URL を返す（JS・CSS のキャッシュ対策）。
     * ファイルが無い・更新時刻が取れない・URL に v が既にあるときは、そのまま返す。
     */
    public function versionedUrl(string $url, string $localPath): string
    {
        $queryString = parse_url($url, PHP_URL_QUERY);
        if (is_string($queryString)) {
            parse_str($queryString, $queryParams);
            if (isset($queryParams['v'])) {
                return $url;
            }
        }

        if (!is_file($localPath)) {
            return $url;
        }

        $mtime = filemtime($localPath);
        if ($mtime === false) {
            return $url;
        }

        return $url . (strpos($url, '?') === false ? '?' : '&') . 'v=' . $mtime;
    }

    /**
     * プラグインに同梱した JS（assets/plugins/tinymce7/ からの相対パス）の URL。
     */
    public function bundledAssetUrl(string $relativePath): string
    {
        return $this->versionedUrl(
            MODX_BASE_URL . 'assets/plugins/tinymce7/' . $relativePath,
            MODX_BASE_PATH . 'assets/plugins/tinymce7/' . $relativePath
        );
    }

    public function scriptTag(string $url): string
    {
        $escaped = hsc($url);

        return '<script src="' . $escaped . '"></script>';
    }

    public function inlineScript(string $script): string
    {
        return '<script>' . $script . '</script>';
    }
}
