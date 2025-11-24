<?php
declare(strict_types=1);

namespace TinyMCE7\Editor;

final class ElementSelector
{
    /**
     * @param mixed $elements
     * @return string[]
     */
    public function normalize($elements): array
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

    /**
     * @param string[] $elements
     */
    public function buildSelector(array $elements): string
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
