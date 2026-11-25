<?php
declare(strict_types=1);

namespace TinyMCE7\Config;

final class PreferenceResolver
{
    private const WIDE_TOOLBAR_ITEMS = [
        'blocks' => 3,
        'fontfamily' => 3,
        'styles' => 3,
        'fontsize' => 2,
    ];

    /**
     * 「表示名=要素名.クラス名」で書ける要素名ごとの定義。
     * list はその要素のダイアログの「クラス」の選択肢を入れる設定キー、
     * withUnprefixed は要素名の無い項目も同じ選択肢に入れるか
     */
    private const CLASS_TARGETS = [
        'a' => ['list' => 'link_class_list', 'withUnprefixed' => true],
        'img' => ['list' => 'image_class_list', 'withUnprefixed' => false],
        'table' => ['list' => 'table_class_list', 'withUnprefixed' => false],
    ];

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
        if (!$this->detectMenubarPreference()) {
            $config['menubar'] = false;

            return $config;
        }

        // ツールバー構成で並びが決まっていればそれを使い、無ければ TinyMCE 既定のメニューを出す
        if (!array_key_exists('menubar', $config) || is_bool($config['menubar'])) {
            $config['menubar'] = true;
        }

        return $config;
    }

    public function applyToolbarVisibility(array $config): array
    {
        if (!$this->detectToolbarVisibility()) {
            $config['toolbar'] = false;
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
     * 要素名の無い項目は TinyMCE 3 の theme_advanced_styles と同じ書式定義（selector '*' と inline 'span' の mixed 書式）にする。
     * 要素全体を選んだときは要素に class を付け、文中の一部を選んだときは span で包む。
     * 「表示名=a.クラス名」のように要素名を付けた項目は、その要素にだけ class を付ける書式にする。
     * 要素名の無い項目は、リンクのダイアログの「クラス」にも出す。画像・表のダイアログには出さない（要素名ごとの扱いは CLASS_TARGETS）
     */
    public function applyCssSelectors(array $config): array
    {
        // importcss プラグインは既定でスタイル一覧を CSS から取り込んだクラスで置き換え、
        // 取り込めるクラスが無いと「書式」の欄が空で押せなくなるため、いつも追記にする
        $config['importcss_append'] = $config['importcss_append'] ?? true;

        $items = $this->parseCssItems($this->detectCssSelectors());

        if ($items === []) {
            return $config;
        }

        $existing = $config['style_formats'] ?? [];
        $config['style_formats'] = array_merge(is_array($existing) ? $existing : [], array_map([$this, 'toFormat'], $items));
        $config['style_formats_merge'] = $config['style_formats_merge'] ?? true;
        // 指定したクラスだけを出す（TinyMCE 3 と同じ）。CSS のクラスをすべて取り込むと一覧が長くなり、同じクラスが重なる
        if (isset($config['plugins'])) {
            $config['plugins'] = PluginList::remove($config['plugins'], 'importcss');
        }

        foreach (self::CLASS_TARGETS as $element => $target) {
            $values = array_filter($items, static function (array $item) use ($element, $target) {
                return $item['element'] === $element || ($target['withUnprefixed'] && $item['element'] === '');
            });
            if ($values !== [] && !isset($config[$target['list']])) {
                $config[$target['list']] = $this->choiceList($values);
            }
        }

        return $this->ensureStylesButton($config);
    }

    /**
     * リンクのダイアログの「rel」の選択肢を、設定「リンクの rel」から作る。
     * 設定ファイルで link_rel_list を指定していればそちらを使い、どちらも無ければ欄を出さない
     */
    public function applyLinkRel(array $config): array
    {
        if (isset($config['link_rel_list'])) {
            return $config;
        }

        $values = $this->parseNamedValues($this->detectLinkRel());
        if ($values !== []) {
            $config['link_rel_list'] = $this->choiceList($values);
        }

        return $config;
    }

    /**
     * 本文の iframe に sandbox を付ける設定（TinyMCE の sandbox_iframes）を渡す。付けないときだけ false にする。
     * 設定ファイルで sandbox_iframes を指定していればそちらを使う
     */
    public function applySandboxIframes(array $config): array
    {
        if (array_key_exists('sandbox_iframes', $config)) {
            return $config;
        }

        if (!$this->detectSandboxIframes()) {
            $config['sandbox_iframes'] = false;
        }

        return $config;
    }

    /**
     * 本文の iframe に sandbox を付けるか。未設定なら付ける
     */
    public function detectSandboxIframes(): bool
    {
        return $this->detectBool(['tinymce7_sandbox_iframes']) ?? true;
    }

    public function detectLinkRel(): string
    {
        return $this->detectString(['tinymce7_link_rel']);
    }

    /**
     * リンクのダイアログの選択肢（{title, value} の並び）にする。
     * 先頭の「None」は TinyMCE の言語ファイルで訳される。
     * 付く値が分かるよう、表示名と値が違えば「表示名 (値)」にする。
     */
    private function choiceList(array $values): array
    {
        $list = [['title' => 'None', 'value' => '']];

        foreach ($values as $item) {
            $title = $item['title'] === $item['value']
                ? $item['value']
                : "{$item['title']} ({$item['value']})";
            $list[] = ['title' => $title, 'value' => $item['value']];
        }

        return $list;
    }

    /**
     * 編集欄の body に付ける id・クラスを body_id・body_class に渡す。
     * 公開側の本文の入れ物と同じ id・クラスを付けると、「CSSファイルへのパス」の CSS が編集欄にも同じように効く。
     * 設定がある項目は、設定ファイル（manager.json など）の値より優先する
     */
    public function applyBodyAttributes(array $config): array
    {
        $bodyId = $this->detectBodyId();
        if ($bodyId !== '') {
            $config['body_id'] = $bodyId;
        }

        $bodyClass = $this->detectBodyClass();
        if ($bodyClass !== '') {
            $config['body_class'] = $bodyClass;
        }

        return $config;
    }

    public function detectBodyId(): string
    {
        return $this->detectString(['tinymce7_body_id']);
    }

    public function detectBodyClass(): string
    {
        return $this->detectString(['tinymce7_body_class']);
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

    /**
     * 「表示名=クラス名」の項目を {title, value, element} の並びにする。
     * 値が「要素名.クラス名」で、要素名が CLASS_TARGETS にあれば element に分ける。無ければ element は空。
     * 要素名を分けた後のクラス名は、CSS のセレクタと同じく「.」でつないで複数書ける（a.btn.primary は btn primary）
     */
    private function parseCssItems(string $value): array
    {
        return array_map(static function (array $item) {
            $element = '';
            if (preg_match('/^([A-Za-z][A-Za-z0-9]*)\.(\S.*)$/', $item['value'], $m)
                && isset(self::CLASS_TARGETS[strtolower($m[1])])) {
                $element = strtolower($m[1]);
                $item['value'] = trim(preg_replace('/[.\s]+/', ' ', $m[2]));
            }

            return $item + ['element' => $element];
        }, $this->parseNamedValues($value));
    }

    private function toFormat(array $item): array
    {
        if ($item['element'] !== '') {
            return [
                'title' => $item['title'],
                'selector' => $item['element'],
                'classes' => $item['value'],
            ];
        }

        return [
            'title' => $item['title'],
            'selector' => '*',
            'inline' => 'span',
            'classes' => $item['value'],
        ];
    }

    /**
     * 「表示名=値;表示名2=値2」を {title, value} の並びにする。表示名を省いたら値を表示名にする
     */
    public function parseNamedValues(string $value): array
    {
        $items = [];

        foreach (explode(';', $value) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            if (str_contains($entry, '=')) {
                [$title, $itemValue] = array_map('trim', explode('=', $entry, 2));
            } else {
                $title = $itemValue = $entry;
            }

            if ($itemValue === '') {
                continue;
            }

            $items[] = ['title' => $title !== '' ? $title : $itemValue, 'value' => $itemValue];
        }

        return $items;
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

        // 空の配列はボタンを出さない指定
        if ($rows === []) {
            return $config;
        }

        foreach ($rows as $row) {
            if ($this->rowHasStyles($row)) {
                return $config;
            }
        }

        // グループの配列は 1 本のツールバーなので、末尾にグループを足す
        if (is_array($rows[0])) {
            $rows[] = ['name' => 'styles', 'items' => ['styles']];
            $config['toolbar'] = $rows;

            return $config;
        }

        // 並びを崩さず行の長さをそろえるため、ボタンが少ない行の末尾に足す（同じ数なら後ろの行）
        $target = 0;
        foreach ($rows as $index => $row) {
            if ($this->countToolbarItems($row) <= $this->countToolbarItems($rows[$target])) {
                $target = $index;
            }
        }
        $rows[$target] = rtrim((string)$rows[$target]) . ' | styles';

        $config['toolbar'] = is_array($toolbar) ? $rows : $rows[0];

        return $config;
    }

    /**
     * 行の長さの目安。選択欄は幅に応じて、普通のボタン何個ぶんかで数える
     */
    private function countToolbarItems($row): int
    {
        $count = 0;
        foreach (preg_split('/[\s|]+/', (string)$row, -1, PREG_SPLIT_NO_EMPTY) as $item) {
            $count += self::WIDE_TOOLBAR_ITEMS[$item] ?? 1;
        }

        return $count;
    }

    private function rowHasStyles($row): bool
    {
        if (is_array($row)) {
            return in_array('styles', (array)($row['items'] ?? []), true);
        }

        return (bool)preg_match('/(^|[\s|])styles($|[\s|])/', (string)$row);
    }

    /**
     * ツールバー構成の名前（toolbar-presets.json のキーの並び）
     */
    public function toolbarPresetNames(): array
    {
        return array_keys(array_filter($this->repository->loadToolbarPresets(), 'is_array'));
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

        $names = $this->toolbarPresetNames();
        // 旧プラグインの構成名（classic・advanced）と、なくした構成（basic）の読み替え
        $aliases = array_combine($names, $names) + [
            'basic' => 'formats',
            'classic' => 'legacy',
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

    /**
     * メニューバーを出すか。未設定なら出さない
     */
    public function detectMenubarPreference(): bool
    {
        return $this->detectMenubarPreferenceSetting() ?? false;
    }

    /**
     * 設定されているメニューバーの表示。未設定なら null
     */
    public function detectMenubarPreferenceSetting(): ?bool
    {
        return $this->detectBool(['tinymce7_menubar', 'tinymce_menubar']);
    }

    /**
     * ツールバーを出すか。未設定なら出す
     */
    public function detectToolbarVisibility(): bool
    {
        return $this->detectToolbarVisibilitySetting() ?? true;
    }

    /**
     * 設定されているツールバーの表示。未設定なら null
     */
    public function detectToolbarVisibilitySetting(): ?bool
    {
        return $this->detectBool(['tinymce7_toolbar']);
    }

    private function detectBool(array $keys): ?bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $this->settings())) {
                continue;
            }

            $raw = $this->settings()[$key];

            if (is_bool($raw)) {
                return $raw;
            }

            if (is_int($raw) || is_float($raw)) {
                return (int)$raw !== 0;
            }

            if (!is_string($raw)) {
                continue;
            }

            $value = strtolower(trim($raw));

            if (in_array($value, ['1', 'true', 'yes', 'on', 'show'], true)) {
                return true;
            }

            if (in_array($value, ['0', 'false', 'no', 'off', 'hide'], true)) {
                return false;
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
