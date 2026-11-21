<?php

declare(strict_types=1);

namespace TinyMCE7\Editor;

use TinyMCE7\Config\ConfigRepository;
use TinyMCE7\Config\PluginList;
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

        if (empty($config['language'])) {
            $config['language'] = $uiLanguage;
        }

        if (empty($config['language_url'])) {
            $languageUrl = $this->language->languageUrl((string)$config['language']);
            if ($languageUrl !== '') {
                // languageUrl() はローカルの言語ファイル（MODX_BASE_URL 起点）だけを返す
                $config['language_url'] = $this->scriptFactory->versionedUrl(
                    $languageUrl,
                    MODX_BASE_PATH . substr($languageUrl, strlen(MODX_BASE_URL))
                );
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
        $config = $this->preferences->applyToolbarVisibility($config);
        $config = $this->preferences->applyEnterMode($config);
        // 「書式」を足す行を画面に出るボタンで決めるため、ひな形のボタンの有無を先に決める
        $config = $this->applyTemplates($config, empty($params['forfrontend']));
        if (empty($params['forfrontend']) && !isset($config['link_list'])) {
            // ダイアログを開くたびに、ログイン中の利用者のセッションで取りに行く
            $config['link_list'] = MODX_BASE_URL . 'assets/plugins/tinymce7/link-list.php';
        }
        $config = $this->preferences->applyCssSelectors($config);
        $config = $this->preferences->applyLinkRel($config);
        $config = $this->preferences->applyBodyAttributes($config);

        $config = $this->applyEditorCssPath($config);

        // 同梱の TinyMCE が後から読むプラグイン・スキン・言語ファイルのキャッシュ対策（利用者の cache_suffix を優先）
        if (empty($config['cache_suffix'])) {
            $cacheSuffix = $this->scriptFactory->bundledCacheSuffix($config);
            if ($cacheSuffix !== '') {
                $config['cache_suffix'] = $cacheSuffix;
            }
        }

        [$config, $fileBrowser] = $this->fileBrowserResolver->resolve($config, $params);
        // 画像編集もピッカーの API を使うので、ピッカーを使えない利用者には付けない
        // 管理画面ではセッションで決める。フロントのページはキャッシュに入りセッションごとに変えられないので、mcpuk なら出し、使えない人が開いたときはピッカーが断る
        $isManager = empty($params['forfrontend']);
        $canEditImage = $fileBrowser === 'mcpuk' && (!$isManager || $this->fileBrowserResolver->canUsePicker());
        if ($canEditImage) {
            $config['modx_image_edit_label'] = $this->language->translate('tinymce7_image_edit', 'Edit image');
        }
        $canUploadPasted = $this->canUploadPasted($fileBrowser, $isManager);
        $config = $this->applyPastedUpload($config, $canUploadPasted);
        // 受けない理由が権限のときだけ、貼り付け・ドロップした画像を捨てる理由を知らせる（js/editor-setup.js）。mcpuk でないなどの理由では出さない
        if ($isManager && $fileBrowser === 'mcpuk' && !$canUploadPasted
            && $this->fileBrowserResolver->canUsePicker('images')
            && !$this->fileBrowserResolver->canUploadToPicker()) {
            $config['modx_upload_forbidden_message'] = $this->language->translate(
                'tinymce7_upload_forbidden',
                'You do not have permission to upload, so pasted images are not saved.'
            );
        }

        $configJson = $this->configRepository->encode($config);

        $scripts = [
            $this->scriptFactory->scriptTag($this->scriptFactory->tinymceScriptUrl($config)),
        ];

        if ($fileBrowser === 'mcpuk') {
            $scripts[] = $this->scriptFactory->inlineScript($this->fileBrowserResolver->mcpukBootstrapScript(
                $canEditImage,
                $canUploadPasted ? $this->pastedUploadSettings() : null
            ));
            $scripts[] = $this->scriptFactory->scriptTag($this->scriptFactory->bundledAssetUrl('js/mcpuk-picker.js'));
        }

        $scripts[] = $this->scriptFactory->scriptTag($this->scriptFactory->bundledAssetUrl('js/editor-setup.js'));

        $output = [];
        $output[] = implode("\n", $scripts);
        $output[] = '<script>';
        $output[] = '(function() {';
        $output[] = '    if (typeof tinymce === "undefined") {';
        $output[] = '        console.error("TinyMCE 7 is not loaded. Check assets/plugins/tinymce7/tinymce/js/tinymce/tinymce.min.js");';
        $output[] = '        return;';
        $output[] = '    }';
        $output[] = '    if (typeof window.tinymce7EditorSetup !== "function") {';
        $output[] = '        console.error("TinyMCE7: assets/plugins/tinymce7/js/editor-setup.js is not loaded.");';
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
        if ($canUploadPasted) {
            $output[] = '            config.images_upload_handler = window.mceModxImageUpload || undefined;';
        }
        $output[] = '            break;';
        $output[] = '        default:';
        $output[] = '            if (!config.file_picker_callback) {';
        $output[] = '                delete config.file_picker_callback;';
        $output[] = '            }';
        $output[] = '    }';
        // 設定ファイルやサイトが setup を持っていても上書きせず、先に呼んでからエディター共通の設定を重ねる
        $output[] = '    const userSetup = typeof config.setup === "function" ? config.setup : null;';
        $output[] = '    config.setup = function(editor) {';
        $output[] = '        if (userSetup) {';
        $output[] = '            userSetup.call(this, editor);';
        $output[] = '        }';
        $output[] = '        window.tinymce7EditorSetup(editor);';
        $output[] = '    };';
        $output[] = '    tinymce.init(config);';
        $output[] = '})();';
        $output[] = '</script>';

        $event->output(implode("\n", $output));
    }

    /**
     * 貼り付け・ドロップした画像を、ファイルピッカーの API でアップロードして本文に URL を入れるか。
     * 使えるのは、管理画面のエディターで、ファイルブラウザが mcpuk で、このセッションがピッカーの images を使え（api.php と同じ PickerScope::authorize()）、アップロードの権限 file_upload があり（PickerScope::allows()）、
     * アップロードできる画像の拡張子が 1 つ以上あるとき。
     * フロントのエディターでは使わない。フロントのページはキャッシュに入り、セッションごとの CSRF トークンを埋め込めないため。
     * 使えないときは、本文に data: 画像が入らないよう、貼り付けの画像を受けない（applyPastedUpload()）
     */
    private function canUploadPasted(string $fileBrowser, bool $isManager): bool
    {
        return $isManager
            && $fileBrowser === 'mcpuk'
            && function_exists('getCurrentCsrfToken')
            && $this->fileBrowserResolver->canUploadToPicker()
            && $this->fileBrowserResolver->imagesFileTypes() !== [];
    }

    /**
     * 貼り付けのアップロードの設定。どちらのときも、画像のダイアログのアップロードタブは出さない
     * （画像の入口はファイルピッカーの参照ボタンと、貼り付け・ドロップ）
     */
    private function applyPastedUpload(array $config, bool $canUploadPasted): array
    {
        $config['image_uploadtab'] = false;
        if ($canUploadPasted) {
            $config['images_file_types'] = implode(',', $this->fileBrowserResolver->imagesFileTypes());
            $config['modx_upload_not_saved_message'] = $this->language->translate(
                'tinymce7_upload_not_saved',
                'Saving was cancelled because an image upload did not finish. Please save again.'
            );
        } else {
            $config['paste_data_images'] = false;
        }

        return $config;
    }

    /**
     * @return array{url: string, csrfToken: string}
     */
    private function pastedUploadSettings(): array
    {
        return [
            'url' => $this->fileBrowserResolver->mcpukApiUrl(),
            'csrfToken' => (string) getCurrentCsrfToken(),
        ];
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

        $config['plugins'] = PluginList::append($config['plugins'] ?? [], 'modxtemplate');
        $externalPlugins = is_array($config['external_plugins'] ?? null) ? $config['external_plugins'] : [];
        $externalPlugins['modxtemplate'] = $this->scriptFactory->bundledAssetUrl('js/modx-template.js');
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

        $localPath = $this->localCssPath($cssUrl);
        if ($localPath === null) {
            return $cssUrl;
        }

        return $this->scriptFactory->versionedUrl($cssUrl, $localPath);
    }

    /**
     * サイト内を指す CSS の URL から、ファイルの場所を求める（サイトの外なら null）。
     * URL は MODX_BASE_URL を含むので、それを除いて MODX_BASE_PATH に付ける。
     */
    private function localCssPath(string $cssUrl): ?string
    {
        $urlPath = parse_url($cssUrl, PHP_URL_PATH);
        if (!is_string($urlPath) || $urlPath === '') {
            return null;
        }

        $baseUrl = defined('MODX_BASE_URL') ? MODX_BASE_URL : '/';
        if (strpos($urlPath, $baseUrl) !== 0) {
            return null;
        }

        $basePath = defined('MODX_BASE_PATH') ? MODX_BASE_PATH : '';

        return $basePath . substr($urlPath, strlen($baseUrl));
    }
}
