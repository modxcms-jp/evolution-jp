<?php
if (!defined('MODX_BASE_PATH')) {
    die('No direct access allowed.');
}

if (!function_exists('evo')) {
    /** @var DocumentParser $modx */
    global $modx;
    if (!isset($modx) || !is_object($modx)) {
        die('Evolution CMS context not available.');
    }
    function evo()
    {
        /** @var DocumentParser $modx */
        global $modx;
        return $modx;
    }
}

$event = evo()->event;
$eventName = $event->name ?? '';

switch ($eventName) {
    case 'OnRichTextEditorRegister':
        $event->output('TinyMCE7');
        break;

    case 'OnRichTextEditorInit':
        tinymce7HandleInit();
        break;
}

return;

function tinymce7HandleInit(): void
{
    $event = evo()->event;
    $params = is_array($event->params) ? $event->params : [];
    $requestedEditor = (string)($params['editor'] ?? '');

    if ($requestedEditor !== 'TinyMCE7') {
        return;
    }

    $elements = tinymce7NormalizeElements($params['elements'] ?? []);
    if ($elements === []) {
        return;
    }

    $configPath = MODX_BASE_PATH . 'assets/plugins/tinymce7/config/' . (!empty($params['forfrontend']) ? 'frontend.json' : 'manager.json');
    $config = tinymce7LoadConfig($configPath);

    if (empty($config['selector'])) {
        $config['selector'] = tinymce7BuildSelector($elements);
    }

    if (!empty($params['height']) && is_scalar($params['height'])) {
        $config['height'] = $params['height'];
    }

    if (!empty($params['width']) && is_scalar($params['width'])) {
        $config['width'] = $params['width'];
    }

    $config['language'] = $config['language'] ?? 'ja';
    $config['language_url'] = $config['language_url'] ?? tinymce7LanguageUrl($config['language']);
    $config['convert_urls'] = $config['convert_urls'] ?? false;
    $config['relative_urls'] = $config['relative_urls'] ?? false;

    $configJson = tinymce7EncodeConfig($config);

    $scripts = [
        tinymce7ScriptTag(tinymce7ScriptUrl()),
        tinymce7ScriptTag(MODX_BASE_URL . 'assets/plugins/tinymce7/js/elfinder-picker.js'),
    ];

    $output = [];
    $output[] = implode("\n", $scripts);
    $output[] = '<script>';
    $output[] = '(function() {';
    $output[] = '    if (typeof tinymce === "undefined") {';
    $output[] = '        console.error("TinyMCE 7 is not loaded. Check assets/plugins/tinymce7/tinymce.min.js");';
    $output[] = '        return;';
    $output[] = '    }';
    $output[] = '    const config = ' . $configJson . ';';
    $output[] = '    if (!config.selector) {';
    $output[] = '        console.warn("TinyMCE7: selector is empty. Please set selector in config file.");';
    $output[] = '        return;';
    $output[] = '    }';
    $output[] = '    config.file_picker_callback = window.mceElfinderPicker || undefined;';
    $output[] = '    tinymce.init(config);';
    $output[] = '})();';
    $output[] = '</script>';

    $event->output(implode("\n", $output));
}

function tinymce7LoadConfig(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $json = file_get_contents($path);
    if ($json === false) {
        return [];
    }

    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function tinymce7EncodeConfig(array $config): string
{
    $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return '{}';
    }

    return $json;
}

function tinymce7NormalizeElements($elements): array
{
    if (is_string($elements)) {
        $elements = explode(',', $elements);
    }

    if (!is_array($elements)) {
        return [];
    }

    $elements = array_filter(array_map('trim', $elements));

    return array_values(array_unique($elements));
}

function tinymce7BuildSelector(array $elements): string
{
    if ($elements === []) {
        return '';
    }

    $selectors = array_map(static function ($element) {
        return '#' . ltrim((string)$element, '#');
    }, $elements);

    return implode(',', $selectors);
}

function tinymce7ScriptUrl(): string
{
    $localPath = MODX_BASE_PATH . 'assets/plugins/tinymce7/tinymce.min.js';
    if (is_file($localPath)) {
        return MODX_BASE_URL . 'assets/plugins/tinymce7/tinymce.min.js';
    }

    return 'https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js';
}

function tinymce7LanguageUrl(string $language): string
{
    $language = strtolower(trim($language));
    $localPath = MODX_BASE_PATH . 'assets/plugins/tinymce7/langs/' . $language . '.js';
    if (is_file($localPath)) {
        return MODX_BASE_URL . 'assets/plugins/tinymce7/langs/' . $language . '.js';
    }

    return 'https://cdn.jsdelivr.net/npm/@tinymce/tinymce-i18n@latest/langs/' . $language . '.js';
}

function tinymce7ScriptTag(string $url): string
{
    $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    return '<script src="' . $escaped . '"></script>';
}
