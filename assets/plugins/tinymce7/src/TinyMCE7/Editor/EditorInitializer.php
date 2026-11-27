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
        if (empty($params['forfrontend']) && !isset($config['modx_link_open'])) {
            // 本文の [~id~] のリンクを開くときに、公開側の URL を聞く先（js/link-open.js）
            $config['modx_link_open'] = [
                'url' => MODX_BASE_URL . 'assets/plugins/tinymce7/link-list.php',
                'missing' => $this->language->translate('tinymce7_link_open_missing', 'The linked document was not found.'),
            ];
        }
        if (empty($params['forfrontend']) && !isset($config['modx_tag_names_url'])) {
            // 本文のタグの補完と色付けに使う名前の一覧。エディターの初期化のときに取りに行き、同じ画面のエディターで分け合う（js/tag-assist.js）
            $config['modx_tag_names_url'] = MODX_BASE_URL . 'assets/plugins/tinymce7/tag-names.php';
        }
        $config = $this->preferences->applyCssSelectors($config);
        $config = $this->preferences->applyLinkRel($config);
        $config = $this->preferences->applySandboxIframes($config);
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
        $screen = $isManager ? $this->managerScreen() : ['target' => null, 'newDocument' => false];
        $canUploadPasted = $this->canUploadPasted($fileBrowser, $screen['target']);
        // 画面によらず、権限 file_upload が無いために受けないときは、新しいリソースでも貼り付けた画像を受けず、理由を知らせる（js/editor-setup.js）。
        // mcpuk でない・ピッカーを使えないなど、権限以外の理由では知らせない
        $uploadForbidden = $isManager
            && $fileBrowser === 'mcpuk'
            && $this->fileBrowserResolver->canUsePicker('images')
            && !$this->fileBrowserResolver->canUploadToPicker();
        $config = $this->applyPastedUpload($config, $canUploadPasted, $screen['newDocument'], $uploadForbidden);
        if ($isManager) {
            $config['modx_loss_notice'] = $this->lossNoticeLabels();
        }

        $configJson = $this->configRepository->encode($config);

        $scripts = [
            $this->scriptFactory->scriptTag($this->scriptFactory->tinymceScriptUrl($config), 'tinymce'),
        ];

        if ($fileBrowser === 'mcpuk') {
            $scripts[] = $this->scriptFactory->inlineScript($this->fileBrowserResolver->mcpukBootstrapScript(
                $canEditImage,
                $canUploadPasted ? $this->pastedUploadSettings($screen['target']) : null
            ));
            $scripts[] = $this->scriptFactory->scriptTag($this->scriptFactory->bundledAssetUrl('js/mcpuk-picker.js'));
        }

        if ($isManager) {
            $scripts[] = '<link rel="stylesheet" href="' . hsc($this->scriptFactory->bundledAssetUrl('css/loss-notice.css')) . '">';
            $scripts[] = $this->scriptFactory->scriptTag($this->scriptFactory->bundledAssetUrl('js/tag-assist.js'));
            $scripts[] = $this->scriptFactory->scriptTag($this->scriptFactory->bundledAssetUrl('js/link-open.js'));
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
        if ($isManager && $this->preferences->detectKeepScripts()) {
            // 正規表現は JSON で渡せないので JS 側で足す。設定ファイルの protect があれば消さずに足す
            $output[] = '    config.protect = (config.protect || []).concat(window.tinymce7KeepScriptsProtect);';
        }
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
     * 管理画面の、エディターを出している画面。貼り付けた画像の名前（pasted-doc-12-1.png）に使う、リソースかチャンクの ID の有無を決める。
     * manager()->action と id で判定する（リソースの編集 27・下書きの編集 131/132 と、チャンクの編集 78 は id が要る）。
     * target は、ID のある画面の ['kind' => 'doc'|'chunk', 'id' => int]。ID の無い画面（新しいリソース 4・72、新しいチャンク 77、ほか）は null。
     * newDocument は、新しいリソースの画面（保存するまで ID が無いので、アップロードせず data: 画像を置き、保存時の変換がファイルにする）
     *
     * @return array{target: array{kind: string, id: int}|null, newDocument: bool}
     */
    private function managerScreen(): array
    {
        // ManagerAPI は管理画面でだけ読み込まれる（forfrontend を付けずにフロントで呼ばれたときは、ID の無い画面）
        if (!evo()->isBackend() || !manager()) {
            return ['target' => null, 'newDocument' => false];
        }
        $action = (int) manager()->action;
        $id = request_intvar('id');

        if (in_array($action, [27, 131, 132], true) && $id > 0) {
            return ['target' => ['kind' => 'doc', 'id' => $id], 'newDocument' => false];
        }
        if (in_array($action, [4, 72, 27, 131, 132], true)) {
            return ['target' => null, 'newDocument' => true];
        }
        if ($action === 78 && $id > 0) {
            return ['target' => ['kind' => 'chunk', 'id' => $id], 'newDocument' => false];
        }

        return ['target' => null, 'newDocument' => false];
    }

    /**
     * 貼り付け・ドロップした画像を、ファイルピッカーの API でアップロードして本文に URL を入れるか。
     * 使えるのは、管理画面のエディターで、ID のある画面（managerScreen() の target）で、ファイルブラウザが mcpuk で、
     * このセッションがピッカーの images を使え（api.php と同じ PickerScope::authorize()）、アップロードの権限 file_upload があり（PickerScope::allows()）、アップロードできる画像の拡張子が 1 つ以上あるとき。
     * フロントのエディターでは使わない。フロントのページはキャッシュに入り、セッションごとの CSRF トークンを埋め込めないため。
     * 使えないときは、貼り付けの画像を受けない（applyPastedUpload()）
     *
     * @param array{kind: string, id: int}|null $target
     */
    private function canUploadPasted(string $fileBrowser, ?array $target): bool
    {
        return $target !== null
            && $fileBrowser === 'mcpuk'
            && function_exists('getCurrentCsrfToken')
            && $this->fileBrowserResolver->canUploadToPicker()
            && $this->fileBrowserResolver->imagesFileTypes() !== [];
    }

    /**
     * 貼り付けのアップロードの設定。どちらのときも、画像のダイアログのアップロードタブは出さない
     * （画像の入口はファイルピッカーの参照ボタンと、貼り付け・ドロップ）。
     * アップロードしないときは、新しいリソースだけ貼り付けた画像を受ける（data: のまま置き、保存時の変換が ID の決まった後にファイルにする）。
     * ほかは、本文に data: 画像が入らないよう受けない（チャンクは保存時の変換の対象外）。
     * 権限 file_upload が無いとき（$uploadForbidden）は、新しいリソースでも受けず、受けない理由の文言を渡す
     */
    private function applyPastedUpload(array $config, bool $canUploadPasted, bool $newDocument, bool $uploadForbidden): array
    {
        $config['image_uploadtab'] = false;
        if ($canUploadPasted) {
            $config['images_file_types'] = implode(',', $this->fileBrowserResolver->imagesFileTypes());
            $config['modx_upload_not_saved_message'] = $this->language->translate(
                'tinymce7_upload_not_saved',
                'Saving was cancelled because an image upload did not finish. Please save again.'
            );
        } else {
            $config['paste_data_images'] = $newDocument && !$uploadForbidden;
            if ($uploadForbidden) {
                $config['modx_upload_forbidden_message'] = $this->language->translate(
                    'tinymce7_upload_forbidden',
                    'You do not have permission to upload, so pasted images are not saved.'
                );
            }
        }

        return $config;
    }

    /**
     * 保存すると本文から失われる・変わるものを開いた時点で知らせる通知の文言（js/editor-setup.js の bindLossNotice）。
     * %s と %d は JS 側で、名前と件数に置き換える
     *
     * @return array<string, mixed>
     */
    private function lossNoticeLabels(): array
    {
        return [
            'lost' => $this->language->translate(
                'tinymce7_loss_lost',
                'If you save now, the following will be removed: %s.'
            ),
            'item' => $this->language->translate('tinymce7_loss_item', '%s (%d)'),
            'separator' => $this->language->translate('tinymce7_loss_separator', ', '),
            'names' => [
                'event' => $this->language->translate('tinymce7_loss_event', 'attributes such as onclick'),
                'javascript' => $this->language->translate('tinymce7_loss_javascript', 'values starting with javascript:'),
            ],
            'sandbox' => $this->language->translate(
                'tinymce7_loss_sandbox',
                'If you save now, a sandbox attribute is added to iframes, and embedded content may stop working.'
            ),
            'consult' => $this->language->translate(
                'tinymce7_loss_consult',
                'If you do not want this change, please consult the site administrator.'
            ),
        ];
    }

    /**
     * @param array{kind: string, id: int} $target
     * @return array{url: string, csrfToken: string, kind: string, id: int}
     */
    private function pastedUploadSettings(array $target): array
    {
        return [
            'kind' => $target['kind'],
            'id' => $target['id'],
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
