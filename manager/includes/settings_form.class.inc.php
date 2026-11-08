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
     * 欄に出す値。storedValues() にパスの置き換えと差し戻しを重ねる。
     * 差し戻しは取り出すとセッションから消えるので、1 回の表示で 1 回だけ呼ぶ。
     */
    public static function systemValues(): array
    {
        $values = self::storedValues();

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
            return $this->renderEvent($row['name']);
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
            . '<th>' . $this->resolve($row['label'] ?? '') . "</th>\n"
            . '<td>' . $this->renderCell($row) . "</td>\n"
            . "</tr>\n";
    }

    /**
     * プラグインが返した HTML を、表の中の 1 行として出す
     */
    private function renderEvent(string $name): string
    {
        $output = evo()->invokeEvent($name);
        return '<tr class="row1" style="border-bottom:none;"><td colspan="2" style="padding:0;">'
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
                return $this->select($row) . '<br/>' . $this->resolve($row['desc'] ?? '');
            case 'path':
                return $this->path($row);
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
