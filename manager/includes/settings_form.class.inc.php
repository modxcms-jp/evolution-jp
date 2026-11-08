<?php

/**
 * グローバル設定の画面を、設定の定義（settings_form.definition.inc.php）から HTML にする。
 *
 * 出力のエスケープ:
 * - 見出し・説明・ラジオのラベル・前後の文字・タブ名は、言語ファイルとコードからだけ来る信頼する HTML としてそのまま出す
 * - 設定の値、select の選択肢の値と表示、属性の値は、出力の直前にここで hsc() する
 * - DB や環境から来る値を文に差し込むときは、説明の関数の中で hsc() する
 */
final class EvoSettingsForm
{
    /** パスの設定は、設置先の絶対パスをこの置き換え文字で保存する */
    const BASE_PATH_TOKEN = '[(base_path)]';

    /** @var array */
    private $tabs;
    /** @var array */
    private $values;
    /** @var string */
    private $scope;

    public function __construct(array $tabs, array $values, string $scope = 'system')
    {
        $this->tabs = $tabs;
        $this->values = $values;
        $this->scope = $scope;
    }

    /**
     * 定義を読む（1 回のリクエストで 1 回。文言は読んだときの言語の lang()）
     */
    public static function definition(): array
    {
        return include __DIR__ . '/settings_form.definition.inc.php';
    }

    /**
     * DB の system_settings に default.config.php を下敷きにした値
     */
    public static function storedValues(): array
    {
        $defaults = include MODX_CORE_PATH . 'default.config.php';
        $stored = [];
        $rs = db()->select('setting_name, setting_value', '[+prefix+]system_settings');
        while ($row = db()->getRow($rs)) {
            $stored[$row['setting_name']] = $row['setting_value'];
        }
        return array_merge(is_array($defaults) ? $defaults : [], $stored);
    }

    /**
     * 欄に出す値。storedValues() の結果にパスの置き換えと差し戻しを重ねる。
     * 差し戻しは取り出すとセッションから消えるので、1 回の表示で 1 回だけ呼ぶ。
     *
     * @param array $stored storedValues() の結果（呼び出し側が 1 回だけ作って evo()->config と共有する）
     */
    public static function systemValues(array $stored): array
    {
        $values = $stored;

        foreach (['filemanager_path', 'rb_base_dir', 'export_dir'] as $key) {
            $path = (string)($values[$key] ?? '');
            if (MODX_BASE_PATH !== '' && strpos($path, MODX_BASE_PATH) === 0) {
                $values[$key] = self::BASE_PATH_TOKEN . substr($path, strlen(MODX_BASE_PATH));
            }
        }

        if (manager()->hasFormValues()) {
            foreach (manager()->loadFormValues() as $key => $value) {
                if (is_scalar($value)) {
                    $values[$key] = (string)$value;
                }
            }
        }

        if (($values['site_url'] ?? '') === '[(site_url)]') {
            $values['site_url'] = MODX_SITE_URL;
        }
        if (($values['base_url'] ?? '') === '[(base_url)]') {
            $values['base_url'] = MODX_BASE_URL;
        }
        return $values;
    }

    /**
     * 管理画面の言語 [言語名 => 表示名]。lang フォルダの *.inc.php
     */
    public static function languages(): array
    {
        $names = [];
        foreach (scandir(MODX_CORE_PATH . 'lang') as $filename) {
            if (substr($filename, -8) === '.inc.php') {
                $names[] = substr($filename, 0, -8);
            }
        }
        sort($names);
        $languages = [];
        foreach ($names as $name) {
            $languages[$name] = ucwords(str_replace('_', ' ', $name));
        }
        return $languages;
    }

    /**
     * 言語キーを自分のファイルに持つ言語 [言語名 => 表示名]
     */
    public static function languagesHavingKey(string $key): array
    {
        static $keysByLanguage = [];
        $languages = [];
        foreach (self::languages() as $name => $title) {
            if (!isset($keysByLanguage[$name])) {
                // 英語で補うと、すべての言語が英語のキーを持つことになるので、ファイル単体で読む
                $keysByLanguage[$name] = array_keys(
                    evo()->readLexiconFile(sprintf('%slang/%s.inc.php', MODX_CORE_PATH, $name))
                );
            }
            if (in_array($key, $keysByLanguage[$name], true)) {
                $languages[$name] = $title;
            }
        }
        return $languages;
    }

    /**
     * すべてのタブ（.tab-page）
     */
    public function render(): string
    {
        $html = '';
        foreach ($this->tabs as $tab) {
            $html .= $this->renderTab($tab);
        }
        return $html;
    }

    /**
     * 1 つのタブ（.tab-page）
     */
    public function renderTab(array $tab): string
    {
        $html = '<div class="tab-page" id="' . hsc($tab['id']) . '">' . "\n"
            . '<h2 class="tab">' . $tab['title'] . "</h2>\n";
        foreach ($tab['sections'] ?? [] as $section) {
            $html .= "<table class=\"settings\">\n";
            foreach ($section['rows'] as $row) {
                $html .= $this->renderRow($row);
            }
            $html .= "</table>\n";
        }
        return $html . "</div>\n";
    }

    private function renderRow(array $row): string
    {
        if ($row['type'] === 'event') {
            return $this->renderEvent($row['name'], !empty($row['padded']));
        }

        $attr = '';
        if (isset($row['showIf'])) {
            [$name, $expected] = $row['showIf'];
            $attr = ' data-show-if="' . hsc("{$name}={$expected}") . '"';
            if ($this->value($name) !== (string)$expected) {
                $attr .= ' style="display:none"';
            }
        }

        return "<tr{$attr}>\n"
            . $this->renderHeading($row) . "\n"
            . '<td>' . $this->renderCell($row) . "</td>\n"
            . "</tr>\n";
    }

    /**
     * 行の見出しのセル。lang_text は「言語から読み込む」select を見出しのセルに持つ
     */
    private function renderHeading(array $row): string
    {
        $label = $this->resolve($row['label'] ?? '');
        if ($row['type'] !== 'lang_text') {
            return '<th>' . $label . '</th>';
        }
        $reload = (string)$row['reload'];
        $options = '<option value="">' . hsc(lang('language_title')) . "</option>\n";
        foreach (self::languagesHavingKey($row['langKey']) as $name => $title) {
            $options .= sprintf('<option value="%s">%s</option>' . "\n", hsc($name), hsc($title));
        }
        $onchange = sprintf(
            'confirmLangChange(this, %s, %s);',
            js_literal($row['langKey']),
            js_literal($row['id'])
        );
        $select = sprintf(
            '<select name="%1$s" id="%1$s_select" onchange="%2$s">%3$s</select>',
            hsc($reload),
            hsc($onchange),
            $options
        );
        $body = "<br />\n<p>" . lang('update_settings_from_language') . "</p>\n" . $select;
        if (!empty($row['warningHeading'])) {
            return '<td nowrap class="warning" valign="top"><b>' . $label . '</b>' . $body . '</td>';
        }
        return '<th>' . $label . $body . '</th>';
    }

    /**
     * プラグインが返した HTML を、表の中の 1 行として出す
     */
    private function renderEvent(string $name, bool $padded = false): string
    {
        $output = evo()->invokeEvent($name);
        return '<tr class="row1" style="border-bottom:none;"><td colspan="2"' . ($padded ? '' : ' style="padding:0;"') . '>'
            . (is_array($output) ? implode('', $output) : '')
            . "</td></tr>\n";
    }

    private function renderCell(array $row): string
    {
        switch ($row['type']) {
            case 'text':
                return $this->resolve($row['before'] ?? '')
                    . $this->textInput($row)
                    . $this->resolve($row['after'] ?? '')
                    . '<br/>' . $this->resolve($row['desc'] ?? '');
            case 'textarea':
                return $this->textarea($row) . $this->resolve($row['desc'] ?? '');
            case 'select':
                return $this->select($row)
                    . (($row['br'] ?? true) ? '<br/>' : '')
                    . $this->resolve($row['desc'] ?? '');
            case 'path':
                return $this->path($row);
            case 'lang_text':
                return $this->langText($row);
            case 'content_types':
                return $this->contentTypes($row);
            case 'template':
                return $this->template($row);
            case 'yesno':
                $row['options'] = ['1' => lang('yes'), '0' => lang('no')];
                return $this->radio($row);
            case 'enabled':
                $row['options'] = ['1' => lang('enabled'), '0' => lang('disabled')];
                return $this->radio($row);
            case 'radio':
                return $this->radio($row);
        }
        return '';
    }

    private function textInput(array $row): string
    {
        $key = $row['key'];
        $size = (int)($row['size'] ?? 255) ?: 255;
        return sprintf(
            '<input type="text" name="%s" value="%s" maxlength="%d"%s%s%s />',
            hsc($key),
            hsc($this->value($key, $row)),
            $size,
            $size <= 10 ? sprintf(' style="width:%dem;"', $size) : '',
            !empty($row['disabled']) ? ' disabled' : '',
            $this->attributes($row['attr'] ?? [])
        );
    }

    private function textarea(array $row): string
    {
        $key = $row['key'];
        return sprintf(
            '<textarea name="%s"%s>%s</textarea>',
            hsc($key),
            $this->attributes($row['attr'] ?? []),
            hsc($this->value($key, $row))
        );
    }

    private function select(array $row): string
    {
        $options = $this->resolve($row['options'] ?? []);
        if (!is_array($options)) {
            return '';
        }
        $selected = $this->value($row['key'], $row);
        $html = '';
        foreach ($options as $value => $label) {
            if (is_array($label)) {
                $html .= '<optgroup label="' . hsc((string)$value) . '">';
                foreach ($label as $v => $l) {
                    $html .= $this->option($v, $l, $selected);
                }
                $html .= "</optgroup>\n";
                continue;
            }
            $html .= $this->option($value, $label, $selected);
        }
        return sprintf(
            '<select name="%s" size="1" class="inputBox"%s%s>%s</select>',
            hsc($row['key']),
            !empty($row['disabled']) ? ' disabled' : '',
            $this->attributes($row['attr'] ?? []),
            $html
        );
    }

    private function option($value, $label, string $selected): string
    {
        return sprintf(
            '<option value="%s"%s>%s</option>' . "\n",
            hsc((string)$value),
            (string)$value === $selected ? ' selected="selected"' : '',
            hsc((string)$label)
        );
    }

    private function radio(array $row): string
    {
        $options = $this->resolve($row['options'] ?? []);
        if (!is_array($options)) {
            return '';
        }
        $current = $this->value($row['key']);
        if (!array_key_exists($current, $options) && isset($row['default'])) {
            $current = (string)$this->resolve($row['default']);
        }
        $html = '';
        foreach ($options as $value => $label) {
            $html .= sprintf(
                '<label><input type="radio" name="%s" value="%s"%s%s /> %s</label><br/>',
                hsc($row['key']),
                hsc((string)$value),
                (string)$value === $current ? ' checked="checked"' : '',
                !empty($row['disabled']) ? ' disabled' : '',
                $label
            ) . "\n";
        }
        return $html . $this->resolve($row['desc'] ?? '');
    }

    /**
     * 言語ファイルの文面を初期値に持つ複数行の欄。読み込みの select は見出しのセルに出す（renderHeading）
     */
    private function langText(array $row): string
    {
        $key = $row['key'];
        $langKey = $row['langKey'];
        // 初期文面の hidden（confirmLangChange() が、欄が初期文面のままかを見るのに読む）
        $hiddenName = substr($langKey, -8) === '_default' ? $langKey : $langKey . '_default';
        $value = $this->values[$key] ?? lang($langKey);
        return sprintf(
            '<textarea name="%s"%s>%s</textarea>' . "\n"
            . '<input type="hidden" name="%s" id="%s_hidden" value="%s" />' . "\n",
            hsc($key),
            $this->attributes(['id' => $row['id']] + ($row['attr'] ?? [])),
            hsc(is_scalar($value) ? (string)$value : ''),
            hsc($hiddenName),
            hsc($langKey),
            hsc(lang($langKey))
        ) . $this->resolve($row['desc'] ?? '');
    }

    /**
     * コンテンツタイプの追加・削除（functions.js の addContentType() などが、欄の名前に頼る）
     */
    private function contentTypes(array $row): string
    {
        $value = $this->value($row['key']);
        $options = '';
        foreach (explode(',', $value) as $type) {
            $options .= sprintf('<option value="%1$s">%1$s</option>' . "\n", hsc($type));
        }
        return '<input type="text" name="txt_custom_contenttype" value="" maxlength="100" style="width:200px;" />' . "\n"
            . sprintf('<input type="button" value="%s" onclick="addContentType()" /><br />', hsc(lang('add'))) . "\n"
            . '<table><tr><td valign="top">'
            . '<select name="lst_custom_contenttype" style="width:200px;" size="5">' . $options . '</select>'
            . sprintf('<input name="%s" type="hidden" value="%s" />', hsc($row['key']), hsc($value))
            . '</td><td valign="top">'
            . sprintf(
                '&nbsp;<input name="removecontenttype" type="button" value="%s" onclick="removeContentType()" />',
                hsc(lang('remove'))
            )
            . "</td></tr></table>\n<br />\n"
            . $this->resolve($row['desc'] ?? '');
    }

    /**
     * 既定のテンプレート。今と違うテンプレートを選ぶと、既存のリソースへの一括変更のラジオが出る
     */
    private function template(array $row): string
    {
        $key = $row['key'];
        $current = $this->value($key);
        $rs = db()->select(
            't.templatename, t.id, c.category',
            '[+prefix+]site_templates t LEFT JOIN [+prefix+]categories c ON t.category = c.id',
            '',
            'c.category, t.templatename ASC'
        );
        $groups = [];
        $savedId = 0;
        $savedName = '(blank)';
        while ($template = db()->getRow($rs)) {
            $selected = $current == $template['id'];
            if ($selected && !$savedId) {
                $savedId = $template['id'];
                $savedName = $template['templatename'];
            }
            $category = $template['category'] == null ? lang('no_category') : $template['category'];
            $groups[$category][] = sprintf(
                '<option value="%s"%s>%s</option>',
                hsc($template['id']),
                $selected ? ' selected' : '',
                hsc($template['templatename'])
            );
        }
        $options = [];
        foreach ($groups as $category => $items) {
            $options[] = sprintf('<optgroup label="%s">%s</optgroup>', hsc((string)$category), implode("\n", $items));
        }
        $onchange = sprintf(
            "wrap=document.getElementById('template_reset_options_wrapper');"
            . "if((this.options[this.selectedIndex].value|0) != %d)"
            . "{wrap.style.display='block';}else{wrap.style.display='none';}",
            (int)$current
        );
        return sprintf(
            '<select name="%s" class="inputBox" onchange="%s" style="width:150px">' . "\n"
            . '<option value="">(blank)</option>' . "\n"
            . '%s</select><br />' . "\n"
            . '<div id="template_reset_options_wrapper" style="display:none;">' . "\n"
            . '<label><input type="radio" name="reset_template" value="1" /> %s</label><br/>' . "\n"
            . '<label><input type="radio" name="reset_template" value="2" /> %s</label>' . "\n"
            . '</div>' . "\n"
            . '<input type="hidden" name="old_template" value="%d"/>' . "\n",
            hsc($key),
            hsc($onchange),
            implode("\n", $options),
            lang('template_reset_all'),
            sprintf(lang('template_reset_specific'), hsc($savedName)),
            (int)$savedId
        ) . $this->resolve($row['desc'] ?? '');
    }

    /**
     * 既定値の表示とリセットボタンを持つパスの欄
     */
    private function path(array $row): string
    {
        $key = $row['key'];
        $default = (string)$this->resolve($row['defaultPath']);
        $hint = '';
        if (!empty($row['absHint'])) {
            $hint = ' (' . hsc(str_replace(self::BASE_PATH_TOKEN, MODX_BASE_PATH, $default)) . ')';
        }
        $reset = sprintf(
            'document.getElementById(%s).value = document.getElementById(%s).textContent.trim();',
            js_literal($key),
            js_literal("default_{$key}")
        );
        return lang('default') . sprintf(' <span id="default_%s">%s</span>%s<br/>', hsc($key), hsc($default), $hint) . "\n"
            . sprintf(
                '<input type="text" name="%1$s" value="%2$s" maxlength="255" id="%1$s" />' . "\n"
                . '<input type="button" onclick="%3$s" value="%4$s" name="reset_%1$s"><br/>',
                hsc($key),
                hsc($this->value($key, $row)),
                hsc($reset),
                hsc(lang('reset'))
            ) . "\n"
            . $this->resolve($row['desc'] ?? '');
    }

    /**
     * 行の値。無い（空の）ときは行の default を使う
     */
    private function value(string $key, array $row = []): string
    {
        $value = $this->values[$key] ?? '';
        $value = is_scalar($value) ? (string)$value : '';
        if ($value === '' && isset($row['default'])) {
            return (string)$this->resolve($row['default']);
        }
        return $value;
    }

    /**
     * 定義の値が関数ならこの画面の値を渡して呼ぶ（Closure だけを関数とみなす。'time' などの文字列は呼ばない）
     */
    private function resolve($item)
    {
        return $item instanceof Closure ? $item($this->values) : $item;
    }

    /**
     * 欄に足す属性 [名前 => 値]。値は出力の直前に hsc() する
     */
    private function attributes(array $attr): string
    {
        $html = '';
        foreach ($attr as $name => $value) {
            $html .= sprintf(' %s="%s"', $name, hsc($value));
        }
        return $html;
    }
}
