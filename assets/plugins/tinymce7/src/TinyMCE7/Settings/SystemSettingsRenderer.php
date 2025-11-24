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
        $html[] = '<link rel="stylesheet" type="text/css" href="' . htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') . '">';
        $html[] = '<table id="editorRow_TinyMCE7" class="settings editorRow">';
        $html[] = '  <tr class="row1">';
        $html[] = '    <th colspan="2" class="tinymce7-settings__header"><h4 class="tinymce7-settings__title">' . htmlspecialchars($this->t('tinymce7_settings_header', 'TinyMCE 7'), ENT_QUOTES, 'UTF-8') . '</h4></th>';
        $html[] = '  </tr>';
        $html[] = '  <tr class="row1">';
        $html[] = '    <th><label for="' . $toolbarFieldId . '">' . htmlspecialchars($this->t('tinymce7_toolbar_label', 'Toolbar layout'), ENT_QUOTES, 'UTF-8') . '</label></th>';
        $html[] = '    <td>';
        $html[] = '      <select name="' . $toolbarFieldId . '" id="' . $toolbarFieldId . '" class="inputBox">';

        foreach ($toolbarOptions as $option) {
            $value = htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8');
            $selected = ($toolbarPreset === $option['value']) ? ' selected="selected"' : '';
            $html[] = '            <option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }

        $html[] = '      </select>';
        $html[] = '      <div>' . htmlspecialchars($this->t('tinymce7_toolbar_description', 'Choose the TinyMCE 7 toolbar configuration.'), ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '    </td>';
        $html[] = '  </tr>';
        $html[] = '  <tr class="row1">';
        $html[] = '    <th><label for="' . $menubarFieldId . '">' . htmlspecialchars($this->t('tinymce7_menubar_label', 'Menubar visibility'), ENT_QUOTES, 'UTF-8') . '</label></th>';
        $html[] = '    <td>';
        $html[] = '      <select name="' . $menubarFieldId . '" id="' . $menubarFieldId . '" class="inputBox">';

        foreach ($menubarOptions as $option) {
            $value = htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8');
            $selected = ($menubarValue === $option['value']) ? ' selected="selected"' : '';
            $html[] = '            <option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }

        $html[] = '      </select>';
        $html[] = '      <div>' . htmlspecialchars($this->t('tinymce7_menubar_description', 'Choose whether TinyMCE 7 displays the menubar.'), ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '    </td>';
        $html[] = '  </tr>';
        $html[] = '  <tr class="row1">';
        $html[] = '    <th><label for="' . $fieldId . '">' . htmlspecialchars($this->t('tinymce7_entermode_label', 'Enter key behavior'), ENT_QUOTES, 'UTF-8') . '</label></th>';
        $html[] = '    <td>';
        $html[] = '      <select name="' . $fieldId . '" id="' . $fieldId . '" class="inputBox">';

        foreach ($options as $option) {
            $value = htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8');
            $selected = ($current === $option['value']) ? ' selected="selected"' : '';
            $html[] = '            <option value="' . $value . '"' . $selected . '>' . $label . '</option>';
        }

        $html[] = '      </select>';
        $html[] = '      <div>' . htmlspecialchars($this->t('tinymce7_entermode_description', 'Choose how TinyMCE 7 handles the Enter key.'), ENT_QUOTES, 'UTF-8') . '</div>';
        $html[] = '    </td>';
        $html[] = '  </tr>';
        $html[] = '</table>';

        return implode("\n", $html);
    }

    private function t(string $key, string $default): string
    {
        return $this->language->translate($key, $default);
    }
}
