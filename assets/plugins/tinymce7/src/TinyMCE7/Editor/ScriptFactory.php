<?php
declare(strict_types=1);

namespace TinyMCE7\Editor;

final class ScriptFactory
{
    public function tinymceScriptUrl(array $config = []): string
    {
        if (!empty($config['tinymce_script_url']) && is_string($config['tinymce_script_url'])) {
            return $config['tinymce_script_url'];
        }

        $localPath = MODX_BASE_PATH . 'assets/plugins/tinymce7/tinymce/js/tinymce/tinymce.min.js';
        $preferLocal = !empty($config['tinymce_use_local']);
        if ($preferLocal && is_file($localPath)) {
            return MODX_BASE_URL . 'assets/plugins/tinymce7/tinymce/js/tinymce/tinymce.min.js';
        }

        return 'https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js';
    }

    public function scriptTag(string $url): string
    {
        $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        return '<script src="' . $escaped . '"></script>';
    }

    public function inlineScript(string $script): string
    {
        return '<script>' . $script . '</script>';
    }
}
