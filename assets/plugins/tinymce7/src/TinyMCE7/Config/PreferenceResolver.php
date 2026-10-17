<?php
declare(strict_types=1);

namespace TinyMCE7\Config;

final class PreferenceResolver
{
    private ConfigRepository $repository;
    private ?array $settings;

    /**
     * @param array|null $settings 読む設定。省略時は evo()->config（ユーザー設定画面では編集中のユーザーの設定を渡す）
     */
    public function __construct(?ConfigRepository $repository = null, ?array $settings = null)
    {
        $this->repository = $repository ?? new ConfigRepository();
        $this->settings = $settings;
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
        if (array_key_exists('tinymce7_css_selectors', $this->settings())) {
            return trim((string)$this->settings()['tinymce7_css_selectors']);
        }

        $keys = ['tinymce_css_selectors', 'editor_css_selectors'];

        foreach ($keys as $key) {
            if (!isset($this->settings()[$key])) {
                continue;
            }

            $value = trim((string)$this->settings()[$key]);

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

        if ($toolbar === null || $toolbar === '' || $toolbar === true) {
            // TinyMCE 既定のツールバーには styles が含まれる
            return $config;
        }

        // toolbar は文字列、文字列の配列（行ごと）、{name, items} のグループ配列のいずれか
        $rows = is_array($toolbar) ? array_values($toolbar) : [$toolbar];

        foreach ($rows as $row) {
            if ($this->rowHasStyles($row)) {
                return $config;
            }
        }

        if (is_array($rows[0] ?? null)) {
            array_unshift($rows, ['name' => 'styles', 'items' => ['styles']]);
        } else {
            $rows[0] = 'styles ' . ltrim((string)($rows[0] ?? ''));
        }

        $config['toolbar'] = is_array($toolbar) ? $rows : $rows[0];

        return $config;
    }

    private function rowHasStyles($row): bool
    {
        if (is_array($row)) {
            return in_array('styles', (array)($row['items'] ?? []), true);
        }

        return (bool)preg_match('/(^|[\s|])styles($|[\s|])/', (string)$row);
    }

    public function detectToolbarPreset(): string
    {
        return $this->detectToolbarPresetSetting() ?? 'legacy';
    }

    /**
     * 設定されているツールバー構成。未設定なら null
     */
    public function detectToolbarPresetSetting(): ?string
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
            if (!isset($this->settings()[$key])) {
                continue;
            }

            $value = strtolower(trim((string)$this->settings()[$key]));

            if ($value === '') {
                continue;
            }

            if (isset($aliases[$value])) {
                return $aliases[$value];
            }
        }

        return null;
    }

    public function detectMenubarPreference(): ?bool
    {
        $keys = ['tinymce7_menubar', 'tinymce_menubar'];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->settings())) {
                continue;
            }

            $raw = $this->settings()[$key];

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

    /**
     * ひな形として使うリソースの ID（旧 TinyMCE 3 の mce_template_docs を互換キーとして読む）
     *
     * @return int[]
     */
    public function detectTemplateDocIds(): array
    {
        $ids = [];
        foreach ($this->splitList($this->detectString(['tinymce7_template_docs', 'mce_template_docs'])) as $value) {
            if (preg_match('/^[1-9][0-9]*$/', $value)) {
                $ids[] = (int)$value;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * ひな形として使うチャンクの名前（旧 TinyMCE 3 の mce_template_chunks を互換キーとして読む）
     *
     * @return string[]
     */
    public function detectTemplateChunkNames(): array
    {
        return $this->splitList($this->detectString(['tinymce7_template_chunks', 'mce_template_chunks']));
    }

    public function detectTemplateCategory(): string
    {
        return $this->detectString(['tinymce7_template_category']);
    }

    public function detectEnterMode(): ?string
    {
        $keys = ['tinymce7_entermode', 'tinymce4_entermode', 'tinymce_entermode'];

        foreach ($keys as $key) {
            if (!isset($this->settings()[$key])) {
                continue;
            }

            $value = strtolower(trim((string)$this->settings()[$key]));

            if ($value === 'br' || $value === 'p') {
                return $value;
            }
        }

        return null;
    }

    /**
     * 先に見つかったキーの値を返す。空でも保存済みなら優先する（設定画面で空にしたとき互換キーの値に戻らないように）
     */
    private function detectString(array $keys): string
    {
        foreach ($keys as $key) {
            if (!isset($this->settings()[$key]) || !is_scalar($this->settings()[$key])) {
                continue;
            }

            return trim((string)$this->settings()[$key]);
        }

        return '';
    }

    private function settings(): array
    {
        return $this->settings ?? evo()->config;
    }

    /**
     * @return string[]
     */
    private function splitList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $items = array_filter(array_map('trim', explode(',', $value)), 'strlen');

        return array_values(array_unique($items));
    }
}
