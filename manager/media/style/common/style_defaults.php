<?php

if (!function_exists('manager_style_apply_defaults')) {
    function manager_style_apply_defaults(array &$style, array $defaults): void
    {
        foreach ($defaults as $key => $value) {
            if (!isset($style[$key])) {
                $style[$key] = $value;
            }
        }
    }
}

if (!function_exists('manager_style_set_default_menu_height')) {
    function manager_style_set_default_menu_height(int $defaultHeight = 86): void
    {
        global $modx;

        if (!isset($modx->config['manager_menu_height']) || (int)$modx->config['manager_menu_height'] < $defaultHeight) {
            $modx->config['manager_menu_height'] = (string)$defaultHeight;
        }
    }
}

if (!function_exists('manager_style_set_tree_toolbar_defaults')) {
    function manager_style_set_tree_toolbar_defaults(array &$style, string $iconPath): void
    {
        $treeToolbarDefaults = [
            'add_doc_tree' => '<img src="' . $iconPath . 'page_add.png" />',
            'add_weblink_tree' => '<img src="' . $iconPath . 'link_add.png" />',
            'collapse_tree' => '<img src="' . $iconPath . 'arrow_up.png" />',
            'empty_recycle_bin' => '<img src="' . $iconPath . 'trash_full.png" />',
            'empty_recycle_bin_empty' => '<img src="' . $iconPath . 'trash.png" />',
            'expand_tree' => '<img src="' . $iconPath . 'arrow_down.png" />',
            'hide_tree' => '<img src="' . $iconPath . 'application_side_contract.png" />',
            'refresh_tree' => '<img src="' . $iconPath . 'refresh.png" />',
            'show_tree' => $iconPath . 'application_side_expand.png',
            'sort_tree' => '<img src="' . $iconPath . 'sort.png" />',
        ];

        manager_style_apply_defaults($style, $treeToolbarDefaults);
    }
}

if (!function_exists('manager_style_set_defaults')) {
    function manager_style_set_defaults(
        &$style,
        array $lang,
        ?string $iconPath = null,
        ?string $treePath = null,
        ?string $miscPath = null
    ): void
    {
        if (!is_array($style)) {
            $style = [];
        }

        $iconPath = $iconPath ?: manager_style_image_path('icons');
        $treePath = $treePath ?: manager_style_image_path('tree');
        $miscPath = $miscPath ?: manager_style_image_path('misc');

        manager_style_set_tree_toolbar_defaults($style, $iconPath);

        $treeDefaults = [
            'tree_blanknode' => $treePath . 'empty.png',
            'tree_deletedpage' => $treePath . 'deletedpage.png',
            'tree_folder' => $treePath . 'folder.png',
            'tree_deletedfolder' => $treePath . 'deletedfolder.png',
            'tree_folderopen' => $treePath . 'folderopen.png',
            'tree_folder_secure' => $treePath . 'application_double_key.png',
            'tree_folderopen_secure' => $treePath . 'application_double_key.png',
            'tree_globe' => $treePath . 'globe.png',
            'tree_linkgo' => $treePath . 'link_go.png',
            'tree_minusnode' => $treePath . 'minusnode.png',
            'tree_page' => $treePath . 'page.png',
            'tree_page_home' => $treePath . 'application_home.png',
            'tree_page_404' => $treePath . 'application_404.png',
            'tree_page_hourglass' => $treePath . 'application_hourglass.png',
            'tree_page_info' => $treePath . 'application_info.png',
            'tree_page_blank' => $treePath . 'application.png',
            'tree_page_css' => $treePath . 'application_css.png',
            'tree_page_html' => $treePath . 'page.png',
            'tree_page_xml' => $treePath . 'application_xml.png',
            'tree_page_js' => $treePath . 'application_js.png',
            'tree_page_rss' => $treePath . 'application_rss.png',
            'tree_page_pdf' => $treePath . 'application_pdf.png',
            'tree_page_word' => $treePath . 'application_word.png',
            'tree_page_excel' => $treePath . 'application_excel.png',
            'tree_page_jpg' => $treePath . 'picture.png',
            'tree_page_png' => $treePath . 'picture.png',
            'tree_page_gif' => $treePath . 'picture.png',
            'tree_plusnode' => $treePath . 'plusnode.png',
            'tree_showtree' => '<img src="' . $treePath . 'sitemap.png" align="absmiddle" />',
            'tree_weblink' => $treePath . 'link_go.png',
            'tree_draft' => $treePath . 'pencil.png',
            'tree_page_secure' => $treePath . 'application_key.png',
            'tree_page_blank_secure' => $treePath . 'application_html_secure.png',
            'tree_page_css_secure' => $treePath . 'application_css_secure.png',
            'tree_page_html_secure' => $treePath . 'application_html_secure.png',
            'tree_page_xml_secure' => $treePath . 'application_xml_secure.png',
            'tree_page_js_secure' => $treePath . 'application_js_secure.png',
            'tree_page_rss_secure' => $treePath . 'application_rss_secure.png',
            'tree_page_pdf_secure' => $treePath . 'application_pdf_secure.png',
            'tree_page_word_secure' => $treePath . 'application_word_secure.png',
            'tree_page_excel_secure' => $treePath . 'application_excel_secure.png',
        ];

        $iconDefaults = [
            'icons_add' => $iconPath . 'add.png',
            'icons_cal' => $iconPath . 'cal.gif',
            'icons_cal_nodate' => $iconPath . 'cal_nodate.gif',
            'icons_cancel' => $iconPath . 'stop.png',
            'icons_close' => $iconPath . 'stop.png',
            'icons_delete' => $iconPath . 'delete.png',
            'icons_delete_document' => $iconPath . 'delete.png',
            'icons_delete_complete' => $iconPath . 'trash_full.png',
            'icons_resource_overview' => $iconPath . 'page_white_magnify.png',
            'icons_resource_duplicate' => $iconPath . 'page_white_copy.png',
            'icons_edit_document' => $iconPath . 'write.png',
            'icons_email' => $iconPath . 'email.png',
            'icons_folder' => $iconPath . 'folder.png',
            'icons_home' => $iconPath . 'home.gif',
            'icons_information' => $iconPath . 'information.png',
            'icons_loading_doc_tree' => $iconPath . 'information.png',
            'icons_mail' => $iconPath . 'email.png',
            'icons_message_forward' => $iconPath . 'forward.gif',
            'icons_message_reply' => $iconPath . 'reply.gif',
            'icons_modules' => $iconPath . '32x/modules.gif',
            'icons_move_document' => $iconPath . 'page_white_go.png',
            'icons_new_document' => $iconPath . 'page_white_add.png',
            'icons_new_weblink' => $iconPath . 'world_link.png',
            'icons_preview_resource' => $iconPath . 'page_white_magnify.png',
            'icons_publish_document' => $iconPath . 'clock_play.png',
            'icons_refresh' => $iconPath . 'refresh.png',
            'icons_save' => $iconPath . 'save.png',
            'icons_set_parent' => $iconPath . 'stick.gif',
            'icons_table' => $iconPath . 'table.gif',
            'icons_undelete_resource' => $iconPath . 'b092.gif',
            'icons_unpublish_resource' => $iconPath . 'clock_stop.png',
            'icons_user' => $iconPath . 'vcard.png',
            'icons_view_document' => $iconPath . 'context_view.gif',
            'icons_weblink' => $iconPath . 'world_link.png',
            'icons_working' => $iconPath . 'exclamation.png',
            'sort' => $iconPath . 'sort.png',
            'icons_date' => $iconPath . 'date.gif',
        ];

        $indicatorDefaults = [
            'icons_tooltip' => $iconPath . 'b02.gif',
            'icons_tooltip_over' => $iconPath . 'b02_trans.gif',
        ];

        $largeIconDefaults = [
            'icons_backup_large' => $iconPath . '32x/backup.png',
            'icons_mail_large' => $iconPath . '32x/mail.png',
            'icons_mail_new_large' => $iconPath . '32x/mail_new.png',
            'icons_modules_large' => $iconPath . '32x/modules.gif',
            'icons_resources_large' => $iconPath . '32x/resources.png',
            'icons_elements_large' => $iconPath . '32x/elements.png',
            'icons_security_large' => $iconPath . '32x/users.png',
            'icons_webusers_large' => $iconPath . '32x/users.png',
            'icons_sysinfo_large' => $iconPath . '32x/info.png',
            'icons_search_large' => $iconPath . '32x/search.png',
            'icons_log_large' => $iconPath . '32x/log.png',
            'icons_files_large' => $iconPath . '32x/files.png',
            'icons_help_large' => $iconPath . '32x/help.png',
        ];

        $miscDefaults = [
            'ajax_loader' => '<p>' . $lang['loading_page'] . '</p><p><img src="' . $miscPath . 'ajax-loader.gif" alt="Please wait" /></p>',
            'tx' => $miscPath . '_tx_.gif',
        ];

        manager_style_apply_defaults($style, $treeDefaults);
        manager_style_apply_defaults($style, $iconDefaults);
        manager_style_apply_defaults($style, $indicatorDefaults);
        manager_style_apply_defaults($style, $largeIconDefaults);
        manager_style_apply_defaults($style, $miscDefaults);
    }
}
