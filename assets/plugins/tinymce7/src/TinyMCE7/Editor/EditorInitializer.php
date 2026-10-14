<?php

declare(strict_types=1);

namespace TinyMCE7\Editor;

use TinyMCE7\Config\ConfigRepository;
use TinyMCE7\Config\PreferenceResolver;
use TinyMCE7\Support\Language;

final class EditorInitializer
{
    private ElementSelector $elementSelector;
    private ConfigRepository $configRepository;
    private PreferenceResolver $preferences;
    private Language $language;
    private FileBrowserResolver $fileBrowserResolver;
    private ScriptFactory $scriptFactory;
    private TemplateRepository $templateRepository;

    public function __construct(
        ?ElementSelector $elementSelector = null,
        ?ConfigRepository $configRepository = null,
        ?PreferenceResolver $preferences = null,
        ?Language $language = null,
        ?FileBrowserResolver $fileBrowserResolver = null,
        ?ScriptFactory $scriptFactory = null,
        ?TemplateRepository $templateRepository = null
    ) {
        $this->elementSelector = $elementSelector ?? new ElementSelector();
        $this->configRepository = $configRepository ?? new ConfigRepository();
        $this->preferences = $preferences ?? new PreferenceResolver($this->configRepository);
        $this->language = $language ?? new Language();
        $this->fileBrowserResolver = $fileBrowserResolver ?? new FileBrowserResolver();
        $this->scriptFactory = $scriptFactory ?? new ScriptFactory();
        $this->templateRepository = $templateRepository ?? new TemplateRepository($this->preferences);
    }

    public function handle($event): void
    {
        $params = is_array($event->params ?? null) ? $event->params : [];
        $requestedEditor = (string)($params['editor'] ?? '');

        if ($requestedEditor !== 'TinyMCE7') {
            return;
        }

        $elements = $this->elementSelector->normalize($params['elements'] ?? []);
        if ($elements === []) {
            return;
        }

        $configPath = MODX_BASE_PATH . 'assets/plugins/tinymce7/config/' . (!empty($params['forfrontend']) ? 'frontend.json' : 'manager.json');
        $config = $this->configRepository->load($configPath);

        if (empty($config['selector'])) {
            $config['selector'] = $this->elementSelector->buildSelector($elements);
        }

        if (!empty($params['height']) && is_scalar($params['height'])) {
            $config['height'] = $params['height'];
        }

        if (!empty($params['width']) && is_scalar($params['width'])) {
            $config['width'] = $params['width'];
        }

        $uiLanguage = $this->language->detectUiLanguage();

        if (array_key_exists('image_cropper', $config)) {
            $imageCropperOptions = is_array($config['image_cropper']) ? $config['image_cropper'] : [];
            unset($config['image_cropper']);
        } else {
            $imageCropperOptions = [];
        }

        if (empty($config['language'])) {
            $config['language'] = $uiLanguage;
        }

        if (empty($config['language_url'])) {
            $languageUrl = $this->language->languageUrl((string)$config['language']);
            if ($languageUrl !== '') {
                $config['language_url'] = $languageUrl;
            }
        }

        // URL handling configuration
        // See: https://www.tiny.cloud/docs/tinymce/latest/url-handling/
        $config['convert_urls'] = $config['convert_urls'] ?? false;
        $config['relative_urls'] = $config['relative_urls'] ?? false;

        // Set document_base_url to site root for correct relative path resolution in manager
        // Without this, relative URLs would be resolved from /manager/ directory
        if (empty($config['document_base_url']) && defined('MODX_SITE_URL')) {
            $config['document_base_url'] = MODX_SITE_URL;
        }

        $config = $this->preferences->applyToolbarPreset($config);
        $config = $this->preferences->applyMenubarPreference($config);
        $config = $this->preferences->applyEnterMode($config);

        $config = $this->applyEditorCssPath($config);
        $config = $this->applyTemplates($config, empty($params['forfrontend']));

        [$config, $fileBrowser] = $this->fileBrowserResolver->resolve($config, $params);

        $configJson = $this->configRepository->encode($config);

        $scripts = [
            $this->scriptFactory->scriptTag($this->scriptFactory->tinymceScriptUrl($config)),
        ];

        $imageCropperJson = json_encode($imageCropperOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($imageCropperJson === false) {
            $imageCropperJson = '{}';
        }

        $scripts[] = $this->scriptFactory->inlineScript('window.tinymce7CropperConfig = ' . $imageCropperJson . ';');

        if (!empty($imageCropperOptions['enabled'])) {
            $scripts[] = $this->scriptFactory->scriptTag(MODX_BASE_URL . 'assets/plugins/tinymce7/js/tinymce-cropper.js');
        }

        if ($fileBrowser === 'mcpuk') {
            $scripts[] = $this->scriptFactory->inlineScript($this->fileBrowserResolver->mcpukBootstrapScript());
            $scripts[] = $this->scriptFactory->scriptTag(MODX_BASE_URL . 'assets/plugins/tinymce7/js/mcpuk-picker.js');
        }

        $output = [];
        $output[] = implode("\n", $scripts);
        $output[] = '<script>';
        $output[] = '(function() {';
        $output[] = '    if (typeof tinymce === "undefined") {';
        $output[] = '        console.error("TinyMCE 7 is not loaded. Check assets/plugins/tinymce7/tinymce/js/tinymce/tinymce.min.js");';
        $output[] = '        return;';
        $output[] = '    }';
        $output[] = '    const config = ' . $configJson . ';';
        $output[] = '    if (!config.selector) {';
        $output[] = '        console.warn("TinyMCE7: selector is empty. Please set selector in config file.");';
        $output[] = '        return;';
        $output[] = '    }';
        $output[] = '    switch (' . json_encode($fileBrowser) . ') {';
        $output[] = '        case "mcpuk":';
        $output[] = '            config.file_picker_callback = window.mceModxFilePicker || undefined;';
        $output[] = '            break;';
        $output[] = '        default:';
        $output[] = '            if (!config.file_picker_callback) {';
        $output[] = '                delete config.file_picker_callback;';
        $output[] = '            }';
        $output[] = '    }';
        $output[] = '    tinymce.init(config);';
        $output[] = '})();';
        $output[] = '</script>';

        $event->output(implode("\n", $output));
    }

    /**
     * 「ひな形の挿入」（TinyMCE 7 で廃止された template プラグインの代替）を組み込む。
     * ひな形の本文はページに埋め込むため、管理画面のエディターに限る。
     */
    private function applyTemplates(array $config, bool $isManager): array
    {
        $templates = $isManager ? $this->templateRepository->all() : [];

        if ($templates === []) {
            if (isset($config['toolbar'])) {
                $config['toolbar'] = $this->removeToolbarItem($config['toolbar'], 'template');
            }

            return $config;
        }

        $config['plugins'] = $this->appendPlugin($config['plugins'] ?? [], 'modxtemplate');
        $externalPlugins = is_array($config['external_plugins'] ?? null) ? $config['external_plugins'] : [];
        $externalPlugins['modxtemplate'] = MODX_BASE_URL . 'assets/plugins/tinymce7/js/modx-template.js';
        $config['external_plugins'] = $externalPlugins;
        $config['modx_templates'] = $templates;
        $config['modx_template_labels'] = [
            'button' => $this->language->translate('tinymce7_template_button', 'Insert template'),
            'title' => $this->language->translate('tinymce7_template_dialog_title', 'Insert template'),
            'select' => $this->language->translate('tinymce7_template_select', 'Template'),
            'preview' => $this->language->translate('tinymce7_template_preview', 'Preview'),
            'insert' => $this->language->translate('tinymce7_template_insert', 'Insert'),
            'cancel' => $this->language->translate('tinymce7_template_cancel', 'Cancel'),
        ];

        return $config;
    }

    /**
     * @param string|array $plugins
     * @return string|array
     */
    private function appendPlugin($plugins, string $name)
    {
        if (is_array($plugins)) {
            if (!in_array($name, $plugins, true)) {
                $plugins[] = $name;
            }

            return $plugins;
        }

        $list = preg_split('/[\s,]+/', trim((string)$plugins), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!in_array($name, $list, true)) {
            $list[] = $name;
        }

        return implode(' ', $list);
    }

    /**
     * @param mixed $toolbar
     * @return mixed
     */
    private function removeToolbarItem($toolbar, string $item)
    {
        if (is_array($toolbar)) {
            return array_map(fn($row) => $this->removeToolbarItem($row, $item), $toolbar);
        }

        if (!is_string($toolbar)) {
            return $toolbar;
        }

        $pattern = '/(^|\s)' . preg_quote($item, '/') . '(?=\s|$)/';

        return trim(preg_replace('/\s{2,}/', ' ', preg_replace($pattern, '$1', $toolbar) ?? $toolbar) ?? $toolbar);
    }

    private function applyEditorCssPath(array $config): array
    {
        if (!function_exists('evo')) {
            return $config;
        }

        $editorCssPath = (string)evo()->config('editor_css_path', '');
        if ($editorCssPath === '') {
            return $config;
        }

        if (str_starts_with($editorCssPath, '/') || preg_match('@^https?://@', $editorCssPath)) {
            $cssUrl = $editorCssPath;
        } else {
            $cssUrl = defined('MODX_BASE_URL') ? MODX_BASE_URL . $editorCssPath : '/' . $editorCssPath;
        }

        $cssUrl = $this->appendEditorCssVersion($cssUrl, $editorCssPath);

        $existing = $config['content_css'] ?? [];
        if ($existing === false || $existing === '' || $existing === null) {
            $config['content_css'] = $cssUrl;
        } elseif (is_array($existing)) {
            $existing[] = $cssUrl;
            $config['content_css'] = $existing;
        } else {
            $config['content_css'] = [(string)$existing, $cssUrl];
        }

        return $config;
    }

    private function appendEditorCssVersion(string $cssUrl, string $editorCssPath): string
    {
        if (preg_match('@^https?://@', $editorCssPath)) {
            return $cssUrl;
        }

        $queryString = parse_url($cssUrl, PHP_URL_QUERY);
        if (is_string($queryString)) {
            parse_str($queryString, $queryParams);
            if (isset($queryParams['v'])) {
                return $cssUrl;
            }
        }

        $pathPart = parse_url($editorCssPath, PHP_URL_PATH);
        if (!is_string($pathPart) || $pathPart === '') {
            $pathPart = $editorCssPath;
        }

        $localPath = defined('MODX_BASE_PATH')
            ? MODX_BASE_PATH . ltrim($pathPart, '/')
            : ltrim($pathPart, '/');

        if (!is_file($localPath)) {
            return $cssUrl;
        }

        $mtime = filemtime($localPath);
        if ($mtime === false) {
            return $cssUrl;
        }

        $separator = str_contains($cssUrl, '?') ? '&' : '?';

        return $cssUrl . $separator . 'v=' . $mtime;
    }
}
