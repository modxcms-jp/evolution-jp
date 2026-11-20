<?php
declare(strict_types=1);

namespace TinyMCE7\Settings;

use TinyMCE7\Config\PreferenceResolver;
use TinyMCE7\Support\Language;

final class SystemSettingsRenderer
{
    private ?PreferenceResolver $preferences;
    private Language $language;

    public function __construct(?PreferenceResolver $preferences = null, ?Language $language = null)
    {
        $this->preferences = $preferences;
        $this->language = $language ?? new Language();
    }

    public function render(): string
    {
        $isSystemSettingsScreen = $this->isSystemSettingsScreen();
        // ユーザー設定画面では、表示中の管理者ではなく編集中のユーザーの値を出す。
        // 未設定の欄は空（グローバル設定を使用）のまま保存され、user_settings に入らない
        $preferences = $this->preferences
            ?? new PreferenceResolver(null, $isSystemSettingsScreen ? null : $this->editedUserSettings());

        $toolbarPreset = $isSystemSettingsScreen
            ? $preferences->detectToolbarPreset()
            : ($preferences->detectToolbarPresetSetting() ?? '');
        $current = $preferences->detectEnterMode();
        if ($current !== 'p' && $current !== 'br') {
            $current = '';
        }
        $menubarValue = $this->visibilityValue($isSystemSettingsScreen
            ? $preferences->detectMenubarPreference()
            : $preferences->detectMenubarPreferenceSetting());
        $toolbarVisibilityValue = $this->visibilityValue($isSystemSettingsScreen
            ? $preferences->detectToolbarVisibility()
            : $preferences->detectToolbarVisibilitySetting());
        $fieldId = 'tinymce7_entermode';
        $toolbarFieldId = 'tinymce7_toolbar_preset';
        $cssSelectorsFieldId = 'tinymce7_css_selectors';

        $toolbarOptions = [];
        foreach ($preferences->toolbarPresetNames() as $name) {
            $toolbarOptions[] = ['value' => $name, 'label' => $this->t("tinymce7_toolbar_{$name}", $name)];
        }
        if (!$isSystemSettingsScreen) {
            array_unshift($toolbarOptions, ['value' => '', 'label' => lang('user_use_config')]);
        }
        $options = [
            ['value' => '', 'label' => $isSystemSettingsScreen ? $this->t('tinymce7_entermode_default', 'TinyMCE default (paragraph)') : lang('user_use_config')],
            ['value' => 'p', 'label' => $this->t('tinymce7_entermode_p', 'Insert paragraph <p>')],
            ['value' => 'br', 'label' => $this->t('tinymce7_entermode_br', 'Insert line break <br>')],
        ];

        $html = [];
        $cssUrl = site_asset(MODX_BASE_URL . 'assets/plugins/tinymce7/css/tinymce7.settings.css');
        $html[] = '<link rel="stylesheet" type="text/css" href="' . hsc($cssUrl) . '">';
        // グローバル設定では、functions.js が「リッチテキストエディターの使用」の切り替えで出し入れする
        $html[] = '<table id="editorRow_TinyMCE7" class="settings" data-show-if="use_editor=1">';
        $html[] = '  <tr class="row1">';
        $html[] = '    <th colspan="2" class="tinymce7-settings__header"><h4 class="tinymce7-settings__title">' . hsc($this->t('tinymce7_settings_header', 'TinyMCE 7')) . '</h4></th>';
        $html[] = '  </tr>';
        $html[] = '  <tr class="row1">';
        $html[] = '    <th><label for="' . $toolbarFieldId . '">' . hsc($this->t('tinymce7_toolbar_label', 'Toolbar layout')) . '</label></th>';
        $html[] = '    <td>';
        $html[] = '      <select name="' . $toolbarFieldId . '" id="' . $toolbarFieldId . '" class="inputBox">';

        foreach ($toolbarOptions as $option) {
            $value = hsc($option['value']);
            $label = hsc($option['label']);
            $selected = ($toolbarPreset === $option['value']) ? ' selected="selected"' : '';
            $html[] = '            <option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }

        $html[] = '      </select>';
        $html[] = '      <div>' . hsc($this->t('tinymce7_toolbar_description', 'Choose the TinyMCE 7 toolbar configuration.')) . '</div>';
        $html[] = '    </td>';
        $html[] = '  </tr>';
        $html = array_merge($html, $this->visibilityRow(
            'tinymce7_toolbar',
            $toolbarVisibilityValue,
            $this->t('tinymce7_toolbar_visibility_label', 'Toolbar visibility'),
            $this->t('tinymce7_toolbar_visibility_description', 'Choose whether TinyMCE 7 displays the toolbar.'),
            $isSystemSettingsScreen
        ));
        $html = array_merge($html, $this->visibilityRow(
            'tinymce7_menubar',
            $menubarValue,
            $this->t('tinymce7_menubar_label', 'Menubar visibility'),
            $this->t('tinymce7_menubar_description', 'Choose whether TinyMCE 7 displays the menubar.'),
            $isSystemSettingsScreen
        ));
        $html[] = '  <tr class="row1">';
        $html[] = '    <th><label for="' . $fieldId . '">' . hsc($this->t('tinymce7_entermode_label', 'Enter key behavior')) . '</label></th>';
        $html[] = '    <td>';
        $html[] = '      <select name="' . $fieldId . '" id="' . $fieldId . '" class="inputBox">';

        foreach ($options as $option) {
            $value = hsc($option['value']);
            $label = hsc($option['label']);
            $selected = ($current === $option['value']) ? ' selected="selected"' : '';
            $html[] = '            <option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }

        $html[] = '      </select>';
        $html[] = '      <div>' . hsc($this->t('tinymce7_entermode_description', 'Choose how TinyMCE 7 handles the Enter key.')) . '</div>';
        $html[] = '    </td>';
        $html[] = '  </tr>';

        // 編集欄の body の id・クラス、CSSスタイルセレクタ、ひな形はサイト共通の設定なので、システム設定画面だけに出す
        if ($isSystemSettingsScreen) {
            $bodyFields = [
                'tinymce7_body_id' => [
                    $this->t('tinymce7_body_id_label', 'Editor body ID'),
                    $preferences->detectBodyId(),
                    $this->t('tinymce7_body_id_description', 'ID added to the body of the editing area.'),
                ],
                'tinymce7_body_class' => [
                    $this->t('tinymce7_body_class_label', 'Editor body class'),
                    $preferences->detectBodyClass(),
                    $this->t('tinymce7_body_class_description', 'Classes added to the body of the editing area.'),
                ],
            ];
            foreach ($bodyFields as $name => [$label, $value, $description]) {
                $html[] = '  <tr class="row1">';
                $html[] = '    <th><label for="' . $name . '">' . hsc($label) . '</label></th>';
                $html[] = '    <td>';
                $html[] = '      <input type="text" name="' . $name . '" id="' . $name . '" class="inputBox" style="width:400px;" value="' . hsc($value) . '">';
                $html[] = '      <div>' . hsc($description) . '</div>';
                $html[] = '    </td>';
                $html[] = '  </tr>';
            }
            $html[] = '  <tr class="row1">';
            $html[] = '    <th><label for="' . $cssSelectorsFieldId . '">' . hsc($this->t('tinymce7_css_selectors_label', 'CSS style selectors')) . '</label></th>';
            $html[] = '    <td>';
            $html[] = '      <input type="text" name="' . $cssSelectorsFieldId . '" id="' . $cssSelectorsFieldId . '" class="inputBox" style="width:400px;" value="' . hsc($preferences->detectCssSelectors()) . '">';
            $html[] = '      <div>' . hsc($this->t('tinymce7_css_selectors_description', 'Classes offered in the Formats list, as "Display name=class;Display name 2=class2".')) . '</div>';
            $html[] = '    </td>';
            $html[] = '  </tr>';
            $html[] = '  <tr class="row1">';
            $html[] = '    <th><label for="tinymce7_link_rel">' . hsc($this->t('tinymce7_link_rel_label', 'Link rel')) . '</label></th>';
            $html[] = '    <td>';
            $html[] = '      <input type="text" name="tinymce7_link_rel" id="tinymce7_link_rel" class="inputBox" style="width:400px;" value="' . hsc($preferences->detectLinkRel()) . '">';
            $html[] = '      <div>' . hsc($this->t('tinymce7_link_rel_description', 'Values offered in the Rel list of the link dialog, as "Display name=value;Display name 2=value2".')) . '</div>';
            $html[] = '    </td>';
            $html[] = '  </tr>';
            $html[] = '  <tr class="row1">';
            $html[] = '    <th>' . hsc($this->t('tinymce7_template_label', 'Templates')) . '</th>';
            $html[] = '    <td>';
            $templateFields = [
                'tinymce7_template_docs' => [$this->t('tinymce7_template_docs', 'Resource IDs'), implode(',', $preferences->detectTemplateDocIds())],
                'tinymce7_template_chunks' => [$this->t('tinymce7_template_chunks', 'Chunk names'), implode(',', $preferences->detectTemplateChunkNames())],
                'tinymce7_template_category' => [$this->t('tinymce7_template_category', 'Chunk category'), $preferences->detectTemplateCategory()],
            ];
            foreach ($templateFields as $name => [$label, $value]) {
                $html[] = '      <div class="tinymce7-settings__template"><label><span>' . hsc($label) . '</span>'
                    . '<input type="text" class="inputBox" name="' . $name . '" value="' . hsc($value) . '"></label></div>';
            }
            $html[] = '      <div>' . hsc($this->t('tinymce7_template_description', 'Content registered here can be inserted from the "Insert template" button or the Insert menu. Separate multiple IDs or names with commas. When a category is specified, all chunks in that category are listed.')) . '</div>';
            $html[] = '    </td>';
            $html[] = '  </tr>';
        }

        $html[] = '</table>';

        return implode("\n", $html);
    }

    private function isSystemSettingsScreen(): bool
    {
        return function_exists('manager') && (int)manager()->action === 17;
    }

    /**
     * 編集中のユーザーの設定（ユーザー編集画面 a=11/12 とプロフィール a=74 がグローバル $usersettings に用意する）
     */
    private function editedUserSettings(): array
    {
        global $usersettings;

        return is_array($usersettings) ? $usersettings : [];
    }

    private function visibilityValue(?bool $visible): string
    {
        if ($visible === null) {
            return '';
        }

        return $visible ? '1' : '0';
    }

    /**
     * 表示する・表示しないを選ぶ行。ユーザー設定画面では「グローバル設定を使用」を先頭に足す
     */
    private function visibilityRow(string $fieldId, string $current, string $label, string $description, bool $isSystemSettingsScreen): array
    {
        $options = [
            ['value' => '1', 'label' => $this->t('tinymce7_visibility_show', 'Show')],
            ['value' => '0', 'label' => $this->t('tinymce7_visibility_hide', 'Hide')],
        ];
        if (!$isSystemSettingsScreen) {
            array_unshift($options, ['value' => '', 'label' => lang('user_use_config')]);
        }

        $html = [];
        $html[] = '  <tr class="row1">';
        $html[] = '    <th><label for="' . $fieldId . '">' . hsc($label) . '</label></th>';
        $html[] = '    <td>';
        $html[] = '      <select name="' . $fieldId . '" id="' . $fieldId . '" class="inputBox">';

        foreach ($options as $option) {
            $selected = ($current === $option['value']) ? ' selected="selected"' : '';
            $html[] = '            <option value="' . hsc($option['value']) . '"' . $selected . '>' . hsc($option['label']) . '</option>';
        }

        $html[] = '      </select>';
        $html[] = '      <div>' . hsc($description) . '</div>';
        $html[] = '    </td>';
        $html[] = '  </tr>';

        return $html;
    }

    private function t(string $key, string $default): string
    {
        return $this->language->translate($key, $default);
    }
}
