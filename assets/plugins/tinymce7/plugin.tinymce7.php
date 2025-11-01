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

if (!function_exists('tinymce7HandleInit')) {
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

        [$config, $fileBrowser] = tinymce7ResolveFileBrowser($config, $params);

        $configJson = tinymce7EncodeConfig($config);

        $scripts = [
            tinymce7ScriptTag(tinymce7ScriptUrl()),
        ];

        if ($fileBrowser === 'elfinder') {
            $scripts[] = tinymce7ScriptTag(MODX_BASE_URL . 'assets/plugins/tinymce7/js/elfinder-picker.js');
        } elseif ($fileBrowser === 'mcpuk') {
            $scripts[] = tinymce7InlineScript(tinymce7McpukBootstrapScript());
            $scripts[] = tinymce7ScriptTag(MODX_BASE_URL . 'assets/plugins/tinymce7/js/mcpuk-picker.js');
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
        $output[] = '        case "elfinder":';
        $output[] = '            config.file_picker_callback = window.mceElfinderPicker || undefined;';
        $output[] = '            break;';
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

if (!function_exists('tinymce7LoadConfig')) {
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
}

if (!function_exists('tinymce7EncodeConfig')) {
    function tinymce7EncodeConfig(array $config): string
    {
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return '{}';
        }

        return $json;
    }
}

if (!function_exists('tinymce7NormalizeElements')) {
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
}

if (!function_exists('tinymce7BuildSelector')) {
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
}

if (!function_exists('tinymce7ScriptUrl')) {
    function tinymce7ScriptUrl(): string
    {
        $localPath = MODX_BASE_PATH . 'assets/plugins/tinymce7/tinymce/js/tinymce/tinymce.min.js';
        if (is_file($localPath)) {
            return MODX_BASE_URL . 'assets/plugins/tinymce7/tinymce/js/tinymce/tinymce.min.js';
        }

        return 'https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js';
    }
}

if (!function_exists('tinymce7ResolveFileBrowser')) {
    function tinymce7ResolveFileBrowser(array $config, array $params): array
    {
        $browser = null;

        if (!empty($params['tinymce7_file_browser']) && is_string($params['tinymce7_file_browser'])) {
            $browser = strtolower((string)$params['tinymce7_file_browser']);
        } elseif (!empty($params['file_browser']) && is_string($params['file_browser'])) {
            $browser = strtolower((string)$params['file_browser']);
        } elseif (isset($config['tinymce7_file_browser']) && is_string($config['tinymce7_file_browser'])) {
            $browser = strtolower((string)$config['tinymce7_file_browser']);
            unset($config['tinymce7_file_browser']);
        }

        switch ($browser) {
            case 'elfinder':
                return [$config, 'elfinder'];
            case 'mcpuk':
            case null:
                return [$config, 'mcpuk'];
            case 'none':
            default:
                return [$config, 'none'];
        }
    }
}

if (!function_exists('tinymce7LanguageUrl')) {
    function tinymce7LanguageUrl(string $language): string
    {
        $language = strtolower(trim($language));
        $localPath = MODX_BASE_PATH . 'assets/plugins/tinymce7/langs/' . $language . '.js';
        if (is_file($localPath)) {
            return MODX_BASE_URL . 'assets/plugins/tinymce7/langs/' . $language . '.js';
        }

        return 'https://cdn.jsdelivr.net/npm/@tinymce/tinymce-i18n@latest/langs/' . $language . '.js';
    }
}

if (!function_exists('tinymce7ScriptTag')) {
    function tinymce7ScriptTag(string $url): string
    {
        $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        return '<script src="' . $escaped . '"></script>';
    }
}

if (!function_exists('tinymce7InlineScript')) {
    function tinymce7InlineScript(string $script): string
    {
        return '<script>' . $script . '</script>';
    }
}

if (!function_exists('tinymce7McpukBrowserUrl')) {
    function tinymce7McpukBrowserUrl(): string
    {
        $managerUrl = defined('MODX_MANAGER_URL') ? MODX_MANAGER_URL : MODX_BASE_URL . 'manager/';

        return rtrim($managerUrl, '/') . '/media/browser/mcpuk/browser.php?editor=tinymce7';
    }
}

if (!function_exists('tinymce7McpukBootstrapScript')) {
    function tinymce7McpukBootstrapScript(): string
    {
        $snippets = [];
        $snippets[] = 'window.MODX_FILE_BROWSER_URL = ' . json_encode(tinymce7McpukBrowserUrl()) . ';';
        $snippets[] = 'window.MODX_BASE_URL = ' . json_encode(MODX_BASE_URL) . ';';
        if (defined('MODX_SITE_URL')) {
            $snippets[] = 'window.MODX_SITE_URL = ' . json_encode(MODX_SITE_URL) . ';';
        }

        return implode('', $snippets);
    }
}
