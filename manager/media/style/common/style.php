<?php

declare(strict_types=1);

if (!function_exists('apply_tree_toolbar_style_defaults')) {
    function apply_tree_toolbar_style_defaults(string $iconPath, array &$style): void
    {
        $treeToolbarIcons = [
            'add_doc_tree' => 'page_add.png',
            'add_weblink_tree' => 'link_add.png',
            'collapse_tree' => 'arrow_up.png',
            'empty_recycle_bin' => 'trash_full.png',
            'empty_recycle_bin_empty' => 'trash.png',
        ];

        foreach ($treeToolbarIcons as $key => $filename) {
            if (isset($style[$key])) {
                continue;
            }

            $style[$key] = '<img src="' . $iconPath . $filename . '" />';
        }
    }
}
