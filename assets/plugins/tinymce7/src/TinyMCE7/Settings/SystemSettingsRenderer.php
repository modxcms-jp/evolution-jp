<?php
declare(strict_types=1);

namespace TinyMCE7\Settings;

use TinyMCE7\Config\PreferenceResolver;
use TinyMCE7\Support\Language;

final class SystemSettingsRenderer
{
    private PreferenceResolver $preferences;
    private Language $language;

    public function __construct(?PreferenceResolver $preferences = null, ?Language $language = null)
    {
        $this->preferences = $preferences ?? new PreferenceResolver();
        $this->language = $language ?? new Language();
    }

    public function render(): string
    {
        $toolbarPreset = $this->preferences->detectToolbarPreset();
        $current = $this->preferences->detectEnterMode();
        if ($current !== 'p' && $current !== 'br') {
            $current = '';
        }
        $menubarPreference = $this->preferences->detectMenubarPreference();
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
        $options = [
            ['value' => '', 'label' => $this->t('tinymce7_entermode_default', 'TinyMCE default (paragraph)')],
            ['value' => 'p', 'label' => $this->t('tinymce7_entermode_p', 'Insert paragraph <p>')],
            ['value' => 'br', 'label' => $this->t('tinymce7_entermode_br', 'Insert line break <br>')],
        ];
        $menubarOptions = [
            ['value' => '', 'label' => $this->t('tinymce7_menubar_default', 'TinyMCE default (show)')],
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

        // サイト共通の設定なのでシステム設定画面だけに出す。ユーザー設定画面に出すと、
        // 表示中の管理者の値がそのユーザーの個別設定として保存されてしまう
        if ($this->isSystemSettingsScreen()) {
            $html[] = '  <tr class="row1">';
            $html[] = '    <th><label for="' . $cssSelectorsFieldId . '">' . hsc($this->t('tinymce7_css_selectors_label', 'CSS style selectors')) . '</label></th>';
            $html[] = '    <td>';
            $html[] = '      <input type="text" name="' . $cssSelectorsFieldId . '" id="' . $cssSelectorsFieldId . '" class="inputBox" style="width:400px;" value="' . hsc($this->preferences->detectCssSelectors()) . '">';
            $html[] = '      <div>' . hsc($this->t('tinymce7_css_selectors_description', 'Classes offered in the Formats list, as "Display name=class;Display name 2=class2".')) . '</div>';
            $html[] = '    </td>';
            $html[] = '  </tr>';
        }

        $html[] = '  <tr class="row1">';
        $html[] = '    <th>' . hsc($this->t('tinymce7_template_label', 'Templates')) . '</th>';
        $html[] = '    <td>';
        $templateFields = [
            'tinymce7_template_docs' => [$this->t('tinymce7_template_docs', 'Resource IDs'), implode(',', $this->preferences->detectTemplateDocIds())],
            'tinymce7_template_chunks' => [$this->t('tinymce7_template_chunks', 'Chunk names'), implode(',', $this->preferences->detectTemplateChunkNames())],
            'tinymce7_template_category' => [$this->t('tinymce7_template_category', 'Chunk category'), $this->preferences->detectTemplateCategory()],
        ];
        foreach ($templateFields as $name => [$label, $value]) {
            $html[] = '      <div class="tinymce7-settings__template"><label><span>' . hsc($label) . '</span>'
                . '<input type="text" class="inputBox" name="' . $name . '" value="' . hsc($value) . '"></label></div>';
        }
        $html[] = '      <div>' . hsc($this->t('tinymce7_template_description', 'Content registered here can be inserted from the "Insert template" button or the Insert menu. Separate multiple IDs or names with commas. When a category is specified, all chunks in that category are listed.')) . '</div>';
        $html[] = '    </td>';
        $html[] = '  </tr>';
        $html[] = '</table>';

        return implode("\n", $html);
    }

    private function isSystemSettingsScreen(): bool
    {
        return function_exists('manager') && (int)manager()->action === 17;
    }

    private function t(string $key, string $default): string
    {
        return $this->language->translate($key, $default);
    }
}
