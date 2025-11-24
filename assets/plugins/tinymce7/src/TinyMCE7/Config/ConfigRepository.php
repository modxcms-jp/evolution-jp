<?php
declare(strict_types=1);

namespace TinyMCE7\Config;

final class ConfigRepository
{
    public function load(string $path): array
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

    public function encode(array $config): string
    {
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return '{}';
        }

        return $json;
    }

    public function loadToolbarPresets(): array
    {
        static $presets;

        if ($presets !== null) {
            return $presets;
        }

        $path = MODX_BASE_PATH . 'assets/plugins/tinymce7/config/toolbar-presets.json';
        if (!is_file($path)) {
            $presets = [];

            return $presets;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            $presets = [];

            return $presets;
        }

        $data = json_decode($json, true);
        $presets = is_array($data) ? $data : [];

        return $presets;
    }
}
