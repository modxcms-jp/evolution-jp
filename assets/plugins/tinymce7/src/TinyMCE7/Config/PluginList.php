<?php
declare(strict_types=1);

namespace TinyMCE7\Config;

/**
 * TinyMCE の plugins（空白・カンマ区切りの文字列か配列）への足し引き。渡された形のまま返す
 */
final class PluginList
{
    public static function append($plugins, string $name)
    {
        $list = self::toList($plugins);
        if (!in_array($name, $list, true)) {
            $list[] = $name;
        }

        return self::fromList($list, $plugins);
    }

    public static function remove($plugins, string $name)
    {
        $list = array_values(array_filter(self::toList($plugins), static function ($plugin) use ($name) {
            return $plugin !== $name;
        }));

        return self::fromList($list, $plugins);
    }

    private static function toList($plugins): array
    {
        if (is_array($plugins)) {
            return array_values($plugins);
        }

        return preg_split('/[\s,]+/', trim((string)$plugins), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function fromList(array $list, $original)
    {
        return is_array($original) ? $list : implode(' ', $list);
    }
}
