<?php
declare(strict_types=1);

namespace TinyMCE7\Config;

final class PreferenceResolver
{
    private ConfigRepository $repository;

    public function __construct(?ConfigRepository $repository = null)
    {
        $this->repository = $repository ?? new ConfigRepository();
    }

    public function applyToolbarPreset(array $config): array
    {
        $preset = $this->detectToolbarPreset();
        $presets = $this->repository->loadToolbarPresets();

        if (!isset($presets[$preset]) || !is_array($presets[$preset])) {
            return $config;
        }

        foreach ($presets[$preset] as $key => $value) {
            $config[$key] = $value;
        }

        return $config;
    }

    public function applyMenubarPreference(array $config): array
    {
        $preference = $this->detectMenubarPreference();

        if ($preference === null) {
            return $config;
        }

        if ($preference === false) {
            $config['menubar'] = false;

            return $config;
        }

        if (!array_key_exists('menubar', $config)) {
            $config['menubar'] = true;

            return $config;
        }

        if (is_bool($config['menubar'])) {
            $config['menubar'] = true;
        }

        return $config;
    }

    public function applyEnterMode(array $config): array
    {
        if (array_key_exists('newline_behavior', $config)) {
            return $config;
        }

        $hasLegacyNewlineFlags = false;

        if (isset($config['force_br_newlines']) || isset($config['force_p_newlines'])) {
            unset($config['force_br_newlines'], $config['force_p_newlines']);
            $hasLegacyNewlineFlags = true;
        }

        if (isset($config['forced_root_block']) && !in_array($config['forced_root_block'], ['', 'p'], true)) {
            return $config;
        }

        $mode = $this->detectEnterMode();

        if ($mode === null && !$hasLegacyNewlineFlags) {
            return $config;
        }

        unset($config['forced_root_block']);

        if ($mode === 'br' || ($mode === null && $hasLegacyNewlineFlags)) {
            $config['newline_behavior'] = 'linebreak';
        } elseif ($mode === 'p' || $mode === null) {
            $config['newline_behavior'] = 'default';
        }

        return $config;
    }

    /**
     * 「表示名=クラス名;...」形式のCSSスタイルセレクタ設定を style_formats に変換する。
     * TinyMCE 3 の theme_advanced_styles と同じ書式定義（selector '*' と inline 'span' の mixed 書式）にする。
     * 要素全体を選んだときは要素に class を付け、文中の一部を選んだときは span で包む。
     */
    public function applyCssSelectors(array $config): array
    {
        $formats = $this->parseCssSelectors($this->detectCssSelectors());

        if ($formats === []) {
            return $config;
        }

        $existing = $config['style_formats'] ?? [];
        $config['style_formats'] = array_merge(is_array($existing) ? $existing : [], $formats);
        $config['style_formats_merge'] = $config['style_formats_merge'] ?? true;
        // importcss プラグインは既定でスタイル一覧を CSS から取り込んだクラスで置き換えるため、追記にする
        $config['importcss_append'] = $config['importcss_append'] ?? true;

        return $this->ensureStylesButton($config);
    }

    public function detectCssSelectors(): string
    {
        // 一度保存されたら空でも旧キーへは戻らない（空にして無効化できるようにする）
        if (array_key_exists('tinymce7_css_selectors', evo()->config)) {
            return trim((string)evo()->config['tinymce7_css_selectors']);
        }

        $keys = ['tinymce_css_selectors', 'editor_css_selectors'];

        foreach ($keys as $key) {
            if (!isset(evo()->config[$key])) {
                continue;
            }

            $value = trim((string)evo()->config[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function parseCssSelectors(string $value): array
    {
        $formats = [];

        foreach (explode(';', $value) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if (str_contains($entry, '=')) {
                [$title, $classes] = array_map('trim', explode('=', $entry, 2));
            } else {
                $title = $classes = $entry;
            }

            if ($classes === '') {
                continue;
            }

            $formats[] = [
                'title' => $title !== '' ? $title : $classes,
                'selector' => '*',
                'inline' => 'span',
                'classes' => $classes,
            ];
        }

        return $formats;
    }

    private function ensureStylesButton(array $config): array
    {
        $toolbar = $config['toolbar'] ?? null;

        if ($toolbar === false) {
            return $config;
        }

        $rows = is_array($toolbar) ? $toolbar : [(string)$toolbar];

        foreach ($rows as $row) {
            if (preg_match('/(^|[\s|])styles($|[\s|])/', (string)$row)) {
                return $config;
            }
        }

        if ($toolbar === null || $toolbar === '' || $toolbar === true) {
            // TinyMCE 既定のツールバーには styles が含まれる
            return $config;
        }

        $rows[0] = 'styles ' . ltrim((string)$rows[0]);
        $config['toolbar'] = is_array($toolbar) ? $rows : $rows[0];

        return $config;
    }

    public function detectToolbarPreset(): string
    {
        $keys = ['tinymce7_toolbar_preset', 'tinymce_toolbar_preset'];

        $aliases = [
            'simple' => 'simple',
            'basic' => 'basic',
            'legacy' => 'legacy',
            'classic' => 'legacy',
            'full' => 'full',
            'advanced' => 'full',
        ];

        foreach ($keys as $key) {
            if (!isset(evo()->config[$key])) {
                continue;
            }

            $value = strtolower(trim((string)evo()->config[$key]));

            if ($value === '') {
                continue;
            }

            if (isset($aliases[$value])) {
                return $aliases[$value];
            }
        }

        return 'legacy';
    }

    public function detectMenubarPreference(): ?bool
    {
        $keys = ['tinymce7_menubar', 'tinymce_menubar'];

        foreach ($keys as $key) {
            if (!array_key_exists($key, evo()->config)) {
                continue;
            }

            $raw = evo()->config[$key];

            if (is_bool($raw)) {
                return $raw;
            }

            if (is_int($raw)) {
                return $raw !== 0;
            }

            if (is_string($raw)) {
                $value = strtolower(trim($raw));

                if ($value === '') {
                    continue;
                }

                if (in_array($value, ['1', 'true', 'yes', 'on', 'show'], true)) {
                    return true;
                }

                if (in_array($value, ['0', 'false', 'no', 'off', 'hide'], true)) {
                    return false;
                }
            }

            if (is_float($raw)) {
                return (int)$raw !== 0;
            }
        }

        return null;
    }

    public function detectEnterMode(): ?string
    {
        $keys = ['tinymce7_entermode', 'tinymce4_entermode', 'tinymce_entermode'];

        foreach ($keys as $key) {
            if (!isset(evo()->config[$key])) {
                continue;
            }

            $value = strtolower(trim((string)evo()->config[$key]));

            if ($value === 'br' || $value === 'p') {
                return $value;
            }
        }

        return null;
    }
}
