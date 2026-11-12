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

    /** SMTP のパスワードは値を出さず、この伏せ字を出す（保存の処理がこの値を無視する） */
    const PASSWORD_MASK = '********************';

    /**
     * ユーザー編集画面の「ユーザー」タブ（mutate_user/tpl/form/tab_settings.php）が手書きで出す設定。
     * 欄を足したらここにも足す（ユーザー設定として保存してよい名前に含める）
     */
    const USER_TAB_KEYS = ['allow_manager_access', 'allowed_parents', 'allowed_ip', 'allowed_days'];

    /**
     * メールの文面の設定名 => 言語キー（SSOT）。設定画面の定義と、インストーラー・アップデーターが
     * 行の無いときに言語ファイルの文面を入れる処理（install/instprocessor.php）の両方がこれを使う
     */
    const MAIL_TEXT_LANG_KEYS = [
        'signupemail_message' => 'system_email_signup',
        'websignupemail_message' => 'system_email_websignup',
        'webpwdreminder_message' => 'system_email_webreminder',
    ];

    /** @var array */
    private $tabs;
    /** @var array */
    private $values;
    /** @var array */
    private $stored;
    /** @var string */
    private $scope;
    /** @var string[] render() の中でプラグインのイベントが出した欄の名前 */
    private $pluginFieldNames = [];

    /**
     * @param array $values 欄に出す値（system は systemValues() の結果、user・profile はそのユーザーの値。差し戻しを含む）
     * @param array $stored 保存されているシステム設定（storedValues() の結果。差し戻しを含まない）。
     *                      「今の設定は何か」を判るのに使う（一括変更の基準、user・profile の条件の行と「システム設定に従う」の値）
     * @param string $scope system（グローバル設定）・user（ユーザー編集画面）・profile（プロフィール）。
     *                      user・profile は、その scope の行だけを同じタブ・小見出し・順で出す
     */
    public function __construct(array $tabs, array $values, array $stored, string $scope = 'system')
    {
        $this->tabs = $tabs;
        $this->values = $values;
        $this->stored = $stored;
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
            foreach (self::restoredValues(manager()->loadFormValues()) as $key => $value) {
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
     * 管理画面のテーマ [テーマ名 => 表示名]。manager/media/style/*\/style.php があるもの
     */
    public static function themes(): array
    {
        $themes = [];
        foreach (glob(MODX_MANAGER_PATH . 'media/style/*/style.php') ?: [] as $file) {
            $name = basename(dirname($file));
            $themes[$name] = ucwords(str_replace('_', ' ', $name));
        }
        return $themes;
    }

    /**
     * ユーザーのロール [id => 名前]（id 1 の管理者を除く）
     */
    public static function roles(): array
    {
        $rs = db()->select(
            'id, name',
            '[+prefix+]user_roles',
            'id!=1',
            'save_role DESC, new_role DESC, id ASC'
        );
        $roles = [];
        while ($role = db()->getRow($rs)) {
            $roles[$role['id']] = $role['name'];
        }
        return $roles;
    }

    /**
     * その scope の画面に出す設定キー（topmenu の欄も含む）。
     * ユーザー設定として保存してよい本体の名前で、プラグインの欄は含まない。
     * user には、ユーザー編集画面の「ユーザー」タブが手書きで出す USER_TAB_KEYS を含む
     */
    public static function names(string $scope): array
    {
        $names = [];
        foreach (self::scopeRows($scope) as $row) {
            if (($row['type'] ?? '') === 'topmenu') {
                $names = array_merge($names, array_keys($row['items']));
            } elseif (isset($row['key'])) {
                $names[] = $row['key'];
            }
        }
        return $scope === 'user' ? array_merge($names, self::USER_TAB_KEYS) : $names;
    }

    /**
     * その scope の行のうち、「システム設定に従う」（userDefault）が付く設定キー。
     * この設定は、チェックを外せば空の値も保存できる
     */
    public static function userDefaultNames(string $scope): array
    {
        $names = [];
        foreach (self::scopeRows($scope) as $row) {
            if (!empty($row['userDefault']) && isset($row['key'])) {
                $names[] = $row['key'];
            }
        }
        return $names;
    }

    /**
     * 欄の値を保存の形にする。その設定の行に fromInput があれば通し、無ければそのまま返す。
     * 保存処理と差し戻しが使う（入力の形と保存の形の変換は、行の定義だけに置く）。
     * 読めない値は null（保存しないことを表す。差し戻しでは入力のまま残す）
     *
     * @param mixed $value
     * @return mixed
     */
    public static function storedValue(string $key, $value)
    {
        static $converters = null;
        if ($converters === null) {
            $converters = [];
            foreach (['system', 'user'] as $scope) {
                foreach (self::scopeRows($scope) as $row) {
                    if (isset($row['key'], $row['fromInput'])) {
                        $converters[$row['key']] = $row['fromInput'];
                    }
                }
            }
        }
        return isset($converters[$key]) ? $converters[$key]($value) : $value;
    }

    /**
     * 差し戻す値を保存の形に戻す（欄の値はいつも保存の形で、表示の関数を 1 回だけ通る）。
     * 文字列以外（配列など）はそのまま。読めない値（storedValue() が null）は入力のまま残す
     */
    public static function restoredValues(array $formValues): array
    {
        foreach ($formValues as $key => $value) {
            if (is_string($value)) {
                $formValues[$key] = self::storedValue((string)$key, $value) ?? $value;
            }
        }
        return $formValues;
    }

    /**
     * その scope に出す定義の行（names() と userDefaultNames() が同じ走査を使う。定義は 1 回だけ読む）
     */
    private static function scopeRows(string $scope): array
    {
        static $definition = null;
        $definition = $definition ?? self::definition();
        $rows = [];
        foreach ($definition as $tab) {
            foreach ($tab['sections'] ?? [] as $section) {
                foreach ($section['rows'] as $row) {
                    if (in_array($scope, $row['scope'] ?? ['system'], true)) {
                        $rows[] = $row;
                    }
                }
            }
        }
        return $rows;
    }

    /**
     * HTML の入力欄（input / select / textarea / button）の名前。コメントや script などの送信されない部分は見ない。
     * PHP が POST を受け取るときと同じ形にそろえる（「[」より前だけを使い、「.」と空白は「_」になる）
     */
    public static function fieldNames(string $html): array
    {
        $html = preg_replace(
            ['@<!--.*?-->@s', '@<(script|template|style|noscript)\b.*?</\1\s*>@is', '@(<textarea\b[^>]*>).*?</textarea\s*>@is'],
            ['', '', '$1'],
            $html
        );
        preg_match_all('@<(?:input|select|textarea|button)\b[^>]*>@i', $html, $tags);
        $names = [];
        foreach ($tags[0] as $tag) {
            // disabled の欄は送信されない
            if (preg_match('@\sdisabled\b@i', $tag)) {
                continue;
            }
            if (!preg_match('@\sname\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))@i', $tag, $m)) {
                continue;
            }
            $name = html_entity_decode(implode('', array_slice($m, 1)), ENT_QUOTES);
            $names[] = str_replace(['.', ' '], '_', strstr($name, '[', true) ?: $name);
        }
        return $names;
    }

    /**
     * render() の中でプラグインのイベント（OnInterfaceSettingsRender など）が出した欄の名前。
     * ユーザーの画面（user・profile）でだけ集める。render() の後に呼ばないと空
     */
    public function pluginFieldNames(): array
    {
        return $this->pluginFieldNames;
    }

    /**
     * table.settings の表の共通の見た目（設定画面・ユーザー編集画面・プロフィールで同じものを出す）
     */
    public static function style(): string
    {
        return <<<'HTML'
<style type="text/css">
    table.settings {
        border-collapse: collapse;
        width: 100%;
    }

    table.settings tr {
        border-bottom: 1px dotted #ccc;
    }

    table.settings th {
        font-size: inherit;
        vertical-align: top;
        text-align: left;
    }

    table.settings th, table.settings td {
        padding: 5px;
    }

    table.settings.user-scope td input[type=text] {
        width: 250px;
    }
</style>

HTML;
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
        $html = '';
        $visible = false;
        $tableClass = $this->isSystem() ? 'settings' : 'settings user-scope';
        foreach ($tab['sections'] ?? [] as $section) {
            $rows = array_filter($section['rows'], function (array $row): bool {
                return in_array($this->scope, $row['scope'] ?? ['system'], true);
            });
            if (!$rows) {
                // その scope に出す行が無い小見出しは出さない
                continue;
            }
            // user・profile は、行が条件ですべて隠れるとき、小見出しと表も隠す（行は送るために残す）
            $hidden = !$this->isSystem() && count(array_filter($rows, [$this, 'isConditionHidden'])) === count($rows);
            $visible = $visible || !$hidden;
            $style = $hidden ? ' style="display:none"' : '';
            if (!empty($section['title'])) {
                $html .= '<h3 class="settings-section"' . $style . '>' . $section['title'] . "</h3>\n";
            }
            $html .= '<table class="' . $tableClass . '"' . $style . ">\n";
            foreach ($rows as $row) {
                $html .= $this->renderRow($row);
            }
            $html .= "</table>\n";
        }
        $event = $this->renderEventSection($tab);
        $html .= $event;
        if ($html === '') {
            // その scope に出すものが無いタブは出さない
            return '';
        }
        // 中身がすべて隠れるタブは、タブの見出しも隠す（tabpane.js は隠れた見出しのタブを選ばない）
        $tabStyle = !$this->isSystem() && !$visible && $event === '' ? ' style="display:none"' : '';
        return '<div class="tab-page" id="' . hsc($tab['id']) . '">' . "\n"
            . '<h2 class="tab"' . $tabStyle . '>' . $tab['title'] . "</h2>\n"
            . $html . "</div>\n";
    }

    /**
     * 条件（showIf）に合わず初めから隠れる行。
     * system は画面の値、user・profile は切り替える欄（ラジオ）が画面に無いのでシステム設定の値で決める
     */
    private function isConditionHidden(array $row): bool
    {
        if (!isset($row['showIf'])) {
            return false;
        }
        [$name, $expected] = $row['showIf'];
        $current = $this->isSystem() ? $this->value($name) : (string)($this->stored[$name] ?? '');
        return $current !== (string)$expected;
    }

    /**
     * タブの末尾でプラグインが返した HTML。出力が空なら何も出さない。
     * タブに小見出しがあるときだけ、小見出し「プラグイン」を付ける
     */
    private function renderEventSection(array $tab): string
    {
        if (empty($tab['event']) || !in_array($this->scope, $tab['eventScope'] ?? ['system'], true)) {
            return '';
        }
        $output = evo()->invokeEvent($tab['event']);
        $output = is_array($output) ? implode('', $output) : '';
        if ($output === '') {
            return '';
        }
        if (!$this->isSystem()) {
            $this->pluginFieldNames = array_merge($this->pluginFieldNames, self::fieldNames($output));
        }
        $heading = '';
        foreach ($tab['sections'] ?? [] as $section) {
            if (!empty($section['title'])) {
                $heading = '<h3 class="settings-section">' . lang('settings_section_plugins') . "</h3>\n";
                break;
            }
        }
        return $heading . "<table class=\"settings\">\n"
            . '<tr class="row1" style="border-bottom:none;"><td colspan="2"' . (empty($tab['eventPadded']) ? ' style="padding:0;"' : '') . '>'
            . $output . "</td></tr>\n</table>\n";
    }

    private function renderRow(array $row): string
    {
        $attr = '';
        if (isset($row['showIf']) && $this->isSystem()) {
            [$name, $expected] = $row['showIf'];
            $attr = ' data-show-if="' . hsc("{$name}={$expected}") . '"';
        }
        if ($this->isConditionHidden($row)) {
            $attr .= ' style="display:none"';
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
            '<select name="%s" id="%s" onchange="%s">%s</select>',
            hsc($reload),
            hsc($row['reloadId'] ?? "{$reload}_select"),
            hsc($onchange),
            $options
        );
        return '<th>' . $label . "<br />\n<p>" . lang('update_settings_from_language') . "</p>\n" . $select . '</th>';
    }

    private function renderCell(array $row): string
    {
        switch ($row['type']) {
            case 'text':
                return $this->resolve($row['before'] ?? '')
                    . $this->textInput($row)
                    . $this->resolve($row['after'] ?? '')
                    . $this->userDefaultBox($row)
                    . (($row['br'] ?? true) ? '<br/>' : '') . $this->desc($row);
            case 'textarea':
                return $this->textarea($row)
                    . ($this->userDefaultBox($row) !== '' ? '<br />' . $this->userDefaultBox($row) : '')
                    . $this->desc($row);
            case 'select':
                $field = $this->select($row);
                if ($field === '') {
                    // 選択肢が無い（options が null）ときは、欄を出さず説明だけを出す
                    return $this->desc($row);
                }
                return $field
                    . (($row['br'] ?? true) ? '<br/>' : '')
                    . $this->desc($row);
            case 'password':
                return $this->password($row);
            case 'topmenu':
                return $this->topmenu($row);
            case 'path':
                if (!$this->isSystem()) {
                    // 既定値の表示とリセットは、グローバル設定だけ（空は「システム設定に従う」）
                    return $this->textInput($row) . '<br/>' . $this->desc($row);
                }
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
            $this->rowAttributes($row)
        );
    }

    private function textarea(array $row): string
    {
        $key = $row['key'];
        return sprintf(
            '<textarea name="%s"%s>%s</textarea>',
            hsc($key),
            $this->rowAttributes($row),
            hsc($this->value($key, $row))
        );
    }

    private function select(array $row): string
    {
        $options = $this->resolve($row['options'] ?? []);
        if (!is_array($options)) {
            if ($this->isSystem()) {
                return '';
            }
            // ユーザー画面は欄を残す（保存済みの値を選択肢に出す）。選択肢は定義の fallbackOptions
            $options = $row['fallbackOptions'] ?? [];
        }
        if (!$this->isSystem()) {
            // 空の選択肢は「システム設定に従う」
            $options = ['' => lang('user_use_config')] + $options;
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
            $this->rowAttributes($row),
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
        $current = isset($row['value'])
            ? (string)$this->resolve($row['value'])
            : $this->value($row['key']);
        if (!array_key_exists($current, $options) && isset($row['default'])) {
            $current = (string)$this->resolve($row['default']);
        }
        $html = '';
        foreach ($options as $value => $label) {
            $html .= sprintf(
                '<label><input type="radio" name="%s" value="%s"%s%s /> %s</label>' . (!empty($row['inline']) ? '' : '<br/>'),
                hsc($row['key']),
                hsc((string)$value),
                (string)$value === $current ? ' checked="checked"' : '',
                !empty($row['disabled']) ? ' disabled' : '',
                $label
            ) . "\n";
        }
        return $html . $this->desc($row);
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
        $value = is_scalar($value) ? (string)$value : '';
        $attr = $this->attributes(['id' => $row['id']] + ($row['attr'] ?? []));
        if (($row['field'] ?? 'textarea') === 'text') {
            $field = sprintf(
                '<input type="text" name="%s" value="%s" maxlength="255"%s /><br/>',
                hsc($key),
                hsc($value),
                $attr
            );
        } else {
            $field = sprintf('<textarea name="%s"%s>%s</textarea>', hsc($key), $attr, hsc($value));
        }
        return $field . "\n"
            . sprintf(
                '<input type="hidden" name="%s" id="%s_hidden" value="%s" />',
                hsc($hiddenName),
                hsc($langKey),
                hsc(lang($langKey))
            ) . "\n"
            . (!empty($row['hiddenBr']) ? '<br/>' : '')
            . $this->desc($row);
    }

    /**
     * SMTP のパスワード。保存されている値は出さず、伏せ字を出す
     */
    private function password(array $row): string
    {
        return sprintf(
            '<input type="text" name="%s" maxlength="255" value="%s" autocomplete="off"%s />',
            hsc($row['key']),
            self::PASSWORD_MASK,
            $this->attributes($row['attr'] ?? ['style' => 'width: 250px;'])
        ) . "\n" . $this->desc($row);
    }

    /**
     * 上部メニューの項目名（1 行の中の入れ子の表に、項目ごとの欄を並べる）
     */
    private function topmenu(array $row): string
    {
        $html = '<table>';
        foreach ($row['items'] as $key => $label) {
            $html .= '<tr><td>' . $label . '</td><td>'
                . $this->textInput(['key' => $key, 'attr' => ['style' => 'width:350px;']])
                . "</td></tr>\n";
        }
        return $html . '</table><div>' . $this->desc($row) . '</div>';
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
            . $this->desc($row);
    }

    /**
     * 既定のテンプレート。今と違うテンプレートを選ぶと、既存のリソースへの一括変更のラジオが出る
     */
    private function template(array $row): string
    {
        $key = $row['key'];
        $current = $this->value($key);
        $saved = (string)($this->stored[$key] ?? '');
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
            if ($saved == $template['id'] && !$savedId) {
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
            . "{wrap.style.display='block';}"
            // 保存済みの値に戻したときは、一括変更の選択も外す（隠れたラジオが送られて保存の動きが変わらないように）
            . "else{wrap.style.display='none';wrap.querySelectorAll('input[type=radio]').forEach(function(r){r.checked=false;});}",
            (int)$saved
        );
        // 保存されているテンプレートと違う値で差し戻されたとき（変えた状態で戻ったとき）は、一括変更の選択を初めから出す
        $changed = (int)$current !== (int)$saved;
        $reset = (string)($this->values['reset_template'] ?? '');
        return sprintf(
            '<select name="%s" class="inputBox" onchange="%s" style="width:150px">' . "\n"
            . '<option value="">(blank)</option>' . "\n"
            . '%s</select><br />' . "\n"
            . '<div id="template_reset_options_wrapper" style="display:%s;">' . "\n"
            . '<label><input type="radio" name="reset_template" value="1"%s /> %s</label><br/>' . "\n"
            . '<label><input type="radio" name="reset_template" value="2"%s /> %s</label>' . "\n"
            . '</div>' . "\n"
            . '<input type="hidden" name="old_template" value="%d"/>' . "\n",
            hsc($key),
            hsc($onchange),
            implode("\n", $options),
            $changed ? 'block' : 'none',
            $changed && $reset === '1' ? ' checked' : '',
            lang('template_reset_all'),
            $changed && $reset === '2' ? ' checked' : '',
            sprintf(lang('template_reset_specific'), hsc($savedName)),
            (int)$savedId
        ) . $this->desc($row);
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
            . $this->desc($row);
    }

    /**
     * 行の値。無い（空の）ときは行の default を使う
     */
    private function value(string $key, array $row = []): string
    {
        if (!$this->isSystem() && isset($row['userValue'])) {
            $value = (string)$row['userValue']($this->values, $this->stored);
            return isset($row['toInput']) ? (string)$row['toInput']($value) : $value;
        }
        $value = $this->values[$key] ?? '';
        $value = is_scalar($value) ? (string)$value : '';
        // user・profile は、空を既定値で補わない（空は「システム設定に従う」のまま出す）
        if ($value === '' && isset($row['default']) && $this->isSystem()) {
            return (string)$this->resolve($row['default']);
        }
        // 保存の形から、欄に出す形へ
        return isset($row['toInput']) ? (string)$row['toInput']($value) : $value;
    }

    private function isSystem(): bool
    {
        return $this->scope === 'system';
    }

    /**
     * 説明。user・profile は userDesc があればそれを出す
     */
    private function desc(array $row)
    {
        $desc = !$this->isSystem() && isset($row['userDesc']) ? $row['userDesc'] : ($row['desc'] ?? '');
        return $this->resolve($desc);
    }

    /**
     * 欄に足す属性。user・profile は userAttr を重ねる
     */
    private function rowAttributes(array $row): string
    {
        $attr = $row['attr'] ?? [];
        if (!$this->isSystem()) {
            $attr = array_merge($attr, $row['userAttr'] ?? []);
        }
        return $this->attributes($attr);
    }

    /**
     * 差し戻した画面では、送ったときのチェックを優先する。そうでなければ、ユーザーの値が無いとき
     */
    private function isFollowingSystem(string $key): bool
    {
        $sent = $this->values["default_{$key}"] ?? null;
        if ($sent !== null) {
            return (string)$sent === '1';
        }
        return ($this->values[$key] ?? null) === null;
    }

    /**
     * 「システム設定に従う」のチェックボックス（user・profile の userDefault の行）。
     * ユーザーの値が無いとき（保存すると値を持たない）にチェックを付ける
     */
    private function userDefaultBox(array $row): string
    {
        if ($this->isSystem() || empty($row['userDefault'])) {
            return '';
        }
        return sprintf(
            '&nbsp;&nbsp;<label><input type="checkbox" name="default_%s" value="1"%s /> %s</label>',
            hsc($row['key']),
            $this->isFollowingSystem($row['key']) ? ' checked="checked"' : '',
            lang('user_use_config')
        );
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
