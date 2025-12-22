<?php
declare(strict_types=1);

namespace TinyMCE7\Support;

final class Language
{
    private array $lexicon = [];
    private bool $lexiconLoaded = false;

    public function detectUiLanguage(): string
    {
        $default = 'en';
        $managerLanguage = '';

        $modx = evo();
        if (is_object($modx)) {
            $managerLanguage = (string)$modx->config('manager_language', '');
        }

        $normalized = strtolower(trim($managerLanguage));
        if ($normalized === '') {
            return $default;
        }

        $normalized = str_replace('_', '-', $normalized);
        $normalized = preg_replace('/-(utf|utf8|utf-8)$/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/-(1251|1252|latin1|latin2|iso8859-1|iso8859-2|iso8859-5)$/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z-]/', '', $normalized) ?? $normalized;

        $languageMap = [
            'arabic' => 'ar',
            'bulgarian' => 'bg',
            'catalan' => 'ca',
            'chinese-simplified' => 'zh_CN',
            'chinese-traditional' => 'zh_TW',
            'croatian' => 'hr',
            'czech' => 'cs',
            'danish' => 'da',
            'dutch' => 'nl',
            'english' => 'en',
            'english-british' => 'en_GB',
            'estonian' => 'et',
            'finnish' => 'fi',
            'french' => 'fr',
            'german' => 'de',
            'greek' => 'el',
            'hebrew' => 'he',
            'hungarian' => 'hu',
            'italian' => 'it',
            'japanese' => 'ja',
            'korean' => 'ko',
            'latvian' => 'lv',
            'lithuanian' => 'lt',
            'norwegian' => 'nb_NO',
            'persian' => 'fa',
            'polish' => 'pl',
            'portuguese' => 'pt_PT',
            'portuguese-br' => 'pt_BR',
            'romanian' => 'ro',
            'russian' => 'ru',
            'slovak' => 'sk',
            'slovenian' => 'sl',
            'spanish' => 'es',
            'swedish' => 'sv_SE',
            'thai' => 'th',
            'turkish' => 'tr',
            'ukrainian' => 'uk',
            'vietnamese' => 'vi',
        ];

        if (isset($languageMap[$normalized])) {
            return $languageMap[$normalized];
        }

        $base = strtok($normalized, '-');
        if ($base !== false) {
            if (isset($languageMap[$base])) {
                return $languageMap[$base];
            }

            if (strlen($base) === 2) {
                return $base;
            }
        }

        if (strpos($normalized, 'english') === 0) {
            return 'en';
        }

        return $default;
    }

    public function languageUrl(string $language): string
    {
        $language = trim($language);
        if ($language === '') {
            $language = 'en';
        }

        $languageFile = $language . '.js';
        $localPaths = [
            'assets/plugins/tinymce7/tinymce/js/tinymce/langs/' . $languageFile,
            'assets/plugins/tinymce7/langs/' . strtolower($language) . '.js',
        ];

        foreach ($localPaths as $relativePath) {
            $fullPath = MODX_BASE_PATH . $relativePath;
            if (is_file($fullPath)) {
                return MODX_BASE_URL . $relativePath;
            }
        }

        // No CDN fallback: Tiny Cloud 公式の言語パックはローカル配置前提のため、
        // 言語ファイルが見つからない場合は language_url を指定しない（TinyMCE 既定 = 英語）。
        return '';
    }

    public function lexicon(): array
    {
        if ($this->lexiconLoaded) {
            return $this->lexicon;
        }

        $this->lexicon = [];
        $languageKeys = ['english'];

        $managerLanguage = '';
        $modx = evo();
        if (is_object($modx)) {
            $managerLanguage = (string)$modx->config('manager_language', '');
        }

        $managerLanguage = trim($managerLanguage);
        if ($managerLanguage !== '' && strcasecmp($managerLanguage, 'english') !== 0) {
            $dashPosition = strpos($managerLanguage, '-');
            if ($dashPosition !== false) {
                $base = substr($managerLanguage, 0, $dashPosition);
                if ($base !== '' && strcasecmp($base, 'english') !== 0) {
                    $languageKeys[] = $base;
                }
            }

            $languageKeys[] = $managerLanguage;
        }

        $languageKeys = array_values(array_unique($languageKeys));

        foreach ($languageKeys as $langKey) {
            $this->lexicon = array_merge($this->lexicon, $this->loadLexiconFor($langKey));
        }

        $this->lexiconLoaded = true;

        return $this->lexicon;
    }

    public function translate(string $key, string $default): string
    {
        $lexicon = $this->lexicon();

        if (isset($lexicon[$key]) && is_string($lexicon[$key]) && $lexicon[$key] !== '') {
            return $lexicon[$key];
        }

        return $default;
    }

    private function loadLexiconFor(string $language): array
    {
        $lexicon = [];
        $language = trim($language);

        if ($language === '') {
            return $lexicon;
        }

        $languageVariants = $this->languageVariants($language);

        $paths = [];
        $srcLangDir = dirname(__DIR__) . '/lang';
        foreach ($languageVariants as $variant) {
            $paths[] = $srcLangDir . "/{$variant}.inc.php";
            $paths[] = $srcLangDir . "/{$variant}.php";
            $paths[] = MODX_BASE_PATH . "manager/includes/lang/{$variant}/tinymce7.inc.php";
            $paths[] = MODX_BASE_PATH . "manager/includes/lang/{$variant}/tinymce7.php";
            $paths[] = MODX_BASE_PATH . "assets/plugins/tinymce7/langs/mgr/{$variant}.inc.php";
            $paths[] = MODX_BASE_PATH . "assets/plugins/tinymce7/langs/mgr/{$variant}.php";
        }

        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $_lang = [];
            include $path;

            if (isset($_lang) && is_array($_lang)) {
                $lexicon = array_merge($lexicon, $_lang);
            }
            unset($_lang);
        }

        return $lexicon;
    }

    /**
     * @return list<string>
     */
    private function languageVariants(string $language): array
    {
        $variants = [];

        $addVariant = static function (string $candidate) use (&$variants): void {
            if ($candidate === '') {
                return;
            }

            if (!in_array($candidate, $variants, true)) {
                $variants[] = $candidate;
            }
        };

        $addVariant($language);

        if (strpos($language, '-') !== false) {
            $addVariant(str_replace('-', '_', $language));
        }

        $lowercase = strtolower($language);
        if ($lowercase !== $language) {
            $addVariant($lowercase);

            if (strpos($lowercase, '-') !== false) {
                $addVariant(str_replace('-', '_', $lowercase));
            }
        }

        return $variants;
    }
}
