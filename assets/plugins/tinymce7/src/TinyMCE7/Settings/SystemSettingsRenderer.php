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
        $menubarPreference = $preferences->detectMenubarPreference();
        $menubarValue = '';
        if ($menubarPreference === true) {
            $menubarValue = '1';
        } elseif ($menubarPreference === false) {
            $menubarValue = '0';
        }
        $fieldId = 'tinymce7_entermode';
        $toolbarFieldId = 'tinymce7_toolbar_preset';
        $menubarFieldId = 'tinymce7_menubar';
        $cssSelectorsFieldId = 'tinymce7_css_selectors';

        $toolbarOptions = [
            ['value' => 'simple', 'label' => $this->t('tinymce7_toolbar_simple', 'Simple')],
            ['value' => 'basic', 'label' => $this->t('tinymce7_toolbar_basic', 'Basic')],
            ['value' => 'legacy', 'label' => $this->t('tinymce7_toolbar_legacy', 'Legacy (Default)')],
            ['value' => 'full', 'label' => $this->t('tinymce7_toolbar_full', 'Full')],
        ];
        if (!$isSystemSettingsScreen) {
            array_unshift($toolbarOptions, ['value' => '', 'label' => lang('user_use_config')]);
        }
        $options = [
            ['value' => '', 'label' => $isSystemSettingsScreen ? $this->t('tinymce7_entermode_default', 'TinyMCE default (paragraph)') : lang('user_use_config')],
            ['value' => 'p', 'label' => $this->t('tinymce7_entermode_p', 'Insert paragraph <p>')],
            ['value' => 'br', 'label' => $this->t('tinymce7_entermode_br', 'Insert line break <br>')],
        ];
        $menubarOptions = [
            ['value' => '', 'label' => $isSystemSettingsScreen ? $this->t('tinymce7_menubar_default', 'TinyMCE default (show)') : lang('user_use_config')],
            ['value' => '1', 'label' => $this->t('tinymce7_menubar_show', 'Show')],
            ['value' => '0', 'label' => $this->t('tinymce7_menubar_hide', 'Hide')],
        ];

        $html = [];
        $cssUrl = MODX_BASE_URL . 'assets/plugins/tinymce7/css/tinymce7.settings.css';
        $html[] = '<link rel="stylesheet" type="text/css" href="' . hsc($cssUrl) . '">';
        $html[] = '<table id="editorRow_TinyMCE7" class="settings editorRow">';
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
        $html[] = '  <tr class="row1">';
        $html[] = '    <th><label for="' . $menubarFieldId . '">' . hsc($this->t('tinymce7_menubar_label', 'Menubar visibility')) . '</label></th>';
        $html[] = '    <td>';
        $html[] = '      <select name="' . $menubarFieldId . '" id="' . $menubarFieldId . '" class="inputBox">';

        foreach ($menubarOptions as $option) {
            $value = hsc($option['value']);
            $label = hsc($option['label']);
            $selected = ($menubarValue === $option['value']) ? ' selected="selected"' : '';
            $html[] = '            <option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }

        $html[] = '      </select>';
        $html[] = '      <div>' . hsc($this->t('tinymce7_menubar_description', 'Choose whether TinyMCE 7 displays the menubar.')) . '</div>';
        $html[] = '    </td>';
        $html[] = '  </tr>';
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

        // CSSスタイルセレクタとひな形はサイト共通の設定なので、システム設定画面だけに出す
        if ($isSystemSettingsScreen) {
            $html[] = '  <tr class="row1">';
            $html[] = '    <th><label for="' . $cssSelectorsFieldId . '">' . hsc($this->t('tinymce7_css_selectors_label', 'CSS style selectors')) . '</label></th>';
            $html[] = '    <td>';
            $html[] = '      <input type="text" name="' . $cssSelectorsFieldId . '" id="' . $cssSelectorsFieldId . '" class="inputBox" style="width:400px;" value="' . hsc($preferences->detectCssSelectors()) . '">';
            $html[] = '      <div>' . hsc($this->t('tinymce7_css_selectors_description', 'Classes offered in the Formats list, as "Display name=class;Display name 2=class2".')) . '</div>';
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

    private function t(string $key, string $default): string
    {
        return $this->language->translate($key, $default);
    }
}
