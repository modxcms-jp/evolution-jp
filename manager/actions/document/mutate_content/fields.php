<?php
function fieldPagetitle()
{
    return renderTr(
        lang('resource_title'),
        input_text_tag(
            [
                'id' => 'field_pagetitle',
                'name' => 'pagetitle',
                'value' => doc('pagetitle|hsc')
            ]
        ) . tooltip(lang('resource_title_help'))
    );
}

function fieldLongtitle()
{
    return renderTr(
        lang('long_title'),
        input_text_tag(
            [
                'id' => 'field_longtitle',
                'name' => 'longtitle',
                'value' => doc('longtitle|hsc')
            ]
        ) . tooltip(lang('resource_long_title_help'))
    );
}

function fieldDescription()
{
    return renderTr(
        lang('resource_description'),
        textarea_tag(
            [
                'id' => 'field_description',
                'name' => 'description',
                'class' => 'inputBox',
                'rows' => '2'
            ],
            doc('description|hsc', '')
        )
            . tooltip(lang('resource_description_help')),
        'vertical-align:top;'
    );
}

function fieldAlias($id)
{
    if (!config('friendly_urls') || doc('type') !== 'document') {
        return renderTr(
            lang('resource_alias'),
            input_text_tag(
                [
                    'id' => 'field_alias',
                    'name' => 'alias',
                    'value' => doc('alias|hsc'),
                    'maxlength' => 100
                ]
            )
                . tooltip(lang('resource_alias_help'))
        );
    }
    return renderTr(
        lang('resource_alias'),
        get_alias_path($id)
            . input_text_tag(
                [
                    'id' => 'field_alias',
                    'name' => 'alias',
                    'value' => doc('alias|hsc'),
                    'size' => 20,
                    'style' => 'width:120px;',
                    'maxlength' => 50,
                    'onkeyup' => config('suffix_mode') ? 'change_url_suffix();' : '',
                    'placeholder' => doc('id')
                ]
            )
            . html_tag(
                '<span>',
                ['id' => "url_suffix"],
                call_user_func(function () {
                    if (doc('isfolder')) {
                        return '/';
                    }
                    if (!config('friendly_urls') || !config('suffix_mode')) {
                        return '';
                    }
                    if (!str_contains(doc('alias'), '.')) {
                        return config('friendly_url_suffix');
                    }
                    return '';
                })
            )
            . tooltip(lang('resource_alias_help'))
    );
}

// Web Link specific
function fieldWeblink()
{
    return renderTr(
        [
            lang('weblink'),
            html_tag(
                '<img>',
                [
                    'name' => 'llock',
                    'src' => style('tree_folder'),
                    'alt' => 'tree_folder',
                    'onclick' => 'enableLinkSelection(!allowLinkSelection);',
                    'style' => 'cursor:pointer;'
                ]
            )
        ],
        input_text_tag(
            [
                'id' => 'field_weblink',
                'name' => 'content',
                'value' => doc('content')
            ]
        )
            . html_tag(
                '<input>',
                [
                    'type' => 'button',
                    'onclick' => "BrowseFileServer('field_weblink')",
                    'value' => lang('insert')
                ]
            )
            . tooltip(lang('resource_weblink_help'))
    );
}

function fieldIntrotext()
{
    return renderTr(
        lang('resource_summary'),
        textarea_tag(
            [
                'id' => 'field_introtext',
                'name' => 'introtext',
                'class' => 'inputBox',
                'style' => 'height:60px;',
                'rows' => '3'
            ],
            doc('introtext|hsc', '')
        ) . tooltip(lang('resource_summary_help')),
        'vertical-align:top;'
    );
}

function fieldTemplate()
{
    return renderTr(
        lang('page_data_template'),
        select_tag(
            [
                'id' => 'field_template',
                'name' => 'template',
                'style' => 'width:308px'
            ],
            get_template_options()
        )
            . tooltip(lang('page_data_template_help'))
    );
}

if (!function_exists('get_template_options')) {
    function get_template_options()
    {
        $rs = db()->select(
            sprintf(
                "t.templatename, t.id, IFNULL(c.category,'%s') AS category",
                lang('no_category')
            ),
            [
                '[+prefix+]site_templates t',
                'LEFT JOIN [+prefix+]categories c ON t.category=c.id'
            ],
            '',
            'c.category, t.templatename ASC'
        );
        while ($row = db()->getRow($rs)) {
            $rows[$row['category']][] = $row;
        }
        $optgroups = [
            html_tag(
                '<option>',
                [
                    'value' => 0,
                    'selected' => !doc('template') ? null : ''
                ],
                '(blank)'
            )
        ];
        foreach ($rows as $category => $templates) {
            $optgroups[] = html_tag(
                '<optgroup>',
                ['label' => hsc($category)],
                implode("\n", option_tags($templates))
            );
        }
        return implode("\n", $optgroups);
    }
}

function option_tags($templates)
{
    foreach ($templates as $template) {
        if (!$template['id']) {
            continue;
        }
        $options[] = html_tag(
            '<option>',
            [
                'value' => $template['id'],
                'selected' => $template['id'] == doc('template') ? null : ''
            ],
            hsc(
                sprintf(
                    '[%s] %s',
                    $template['id'],
                    $template['templatename']
                )
            )
        );
    }
    return $options;
}

function fieldMenutitle()
{
    return renderTr(
        lang('resource_opt_menu_title'),
        input_text_tag(
            [
                'id' => 'field_menutitle',
                'name' => 'menutitle',
                'value' => doc('menutitle|hsc')
            ]
        )
            . tooltip(lang('resource_opt_menu_title_help'))
    );
}

function fieldMenuindex()
{
    return renderTr(
        lang('resource_opt_menu_index'),
        menuindex()
    );
}

function menuindex()
{
    $ph = [];
    $ph['menuindex'] = input_text_tag(
        [
            'name' => 'menuindex',
            'value' => doc('menuindex'),
            'style' => 'width:62px;',
            'maxlength' => 8
        ]
    );
    $ph['resource_opt_menu_index_help'] = tooltip(lang('resource_opt_menu_index_help'));
    $ph['resource_opt_show_menu'] = lang('resource_opt_show_menu');
    $ph['hidemenu'] = html_tag(
        'input',
        [
            'type' => 'checkbox',
            'name' => 'hidemenucheck',
            'class' => 'checkbox',
            'checked' => !doc('hidemenu') ? null : '',
            'onclick' => 'changestate(document.mutate.hidemenu);'
        ]
    );
    $ph['hidemenu_hidden'] = html_tag(
        '<input>',
        [
            'type' => 'hidden',
            'name' => 'hidemenu',
            'class' => 'hidden',
            'value' => doc('hidemenu') ? 1 : 0
        ]
    );
    $ph['resource_opt_show_menu_help'] = tooltip(lang('resource_opt_show_menu_help'));
    return parseText(
        file_get_tpl('field_menuindex.tpl'),
        $ph
    );
}

function fieldParent()
{
    $parent = db()->getRow(
        'id,pagetitle',
        '[+prefix+]site_content',
        sprintf('id=%s', doc('parent', 0))
    );
    $ph['pid'] = array_get($parent, 'id', 0);
    $ph['pname'] = $parent ? $parent['pagetitle'] : config('site_name');
    $ph['tooltip'] = tooltip(lang('resource_parent_help'));
    $ph['icon_tree_folder'] = style('tree_folder');
    return renderTr(
        lang('resource_parent'),
        parseText(file_get_tpl('field_parent_form.tpl'), $ph)
    );
}

function fieldsTV()
{
    global $tmplVars;
    // $tmplVars = getTmplvars(request_intvar('id'),doc('template'),getDocgrp());
    $total = count($tmplVars);
    if (!$total) {
        return '';
    }

    $form_v = $_POST ? $_POST : [];
    $i = 0;
    $output = [];
    $hidden = [];
    $output[] = '<table class="mutate-tv-table" border="0" cellspacing="0" cellpadding="3">';
    $splitLine = renderSplit();
    foreach ($tmplVars as $tv) {
        $tvid = 'tv' . $tv['id'];
        // Go through and display all Template Variables
        // post back value
        if (isset($form_v[$tvid])) {
            switch ($tv['type']) {
                case 'checkbox':
                case 'listbox-multiple':
                    $tvPBV = implode('||', $form_v[$tvid]);
                    break;
                case 'url':
                    if ($form_v[$tvid . '_prefix'] === 'DocID') {
                        $tvPBV = sprintf('[~%s~]', $form_v[$tvid]);
                    } else {
                        $tvPBV = $form_v[$tvid . '_prefix'] . $form_v[$tvid];
                    }
                    break;
                default:
                    $tvPBV = $form_v[$tvid];
            }
        } else {
            $tvPBV = $tv['value'];
        }

        if ($tv['type'] === 'hidden') {
            $formElement = evo()->renderFormElement(
                'hidden',
                $tv['id'],
                $tv['default_text'],
                $tv['elements'],
                $tvPBV,
                '',
                $tv
            );
            $hidden[] = $formElement;
        } else {
            $ph = [];
            $ph['caption'] = evo()->hsc($tv['caption']);
            $ph['description'] = $tv['description'];
            $ph['zindex'] = ($tv['type'] === 'date') ? 'z-index:100;' : '';
            $ph['FormElement'] = evo()->renderFormElement(
                $tv['type'],
                $tv['id'],
                $tv['default_text'],
                $tv['elements'],
                $tvPBV,
                '',
                $tv
            );
            if ($ph['FormElement'] !== '') {
                $output[] = parseText(file_get_tpl('tv_row.tpl'), $ph);
                if ($i < $total) {
                    $output[] = $splitLine;
                }
            }
        }
        $i++;
    }

    if ($output && $output[$total + 1] === $splitLine) {
        array_pop($output);
    }

    $output[] = '</table>';

    return implode("\n", $output) . implode("\n", $hidden);
}

function fieldPublished()
{
    $published = function () {
        if (evo()->hasPermission('publish_document') || evo()->manager->action == 27) {
            return doc('published');
        }
        return 0;
    };
    $body = html_tag(
        'input',
        [
            'type' => 'checkbox',
            'class' => 'checkbox',
            'name' => 'publishedcheck',
            'checked' => $published() ? null : '',
            'onclick' => 'changestate(document.mutate.published);resetpubdate();',
            'disabled' => (!evo()->hasPermission('publish_document') || evo()->input_any('id') === config('site_start')) ? null : ''
        ]
    );
    $body .= html_tag(
        'input',
        [
            'name' => 'published',
            'class' => 'hidden',
            'value' => $published() ? 1 : 0
        ]
    );
    $body .= tooltip(lang('resource_opt_published_help'));
    return renderTr(lang('resource_opt_published'), $body);
}

function fieldPub_date($id = 0)
{
    $body = input_text_tag(
        [
            'name' => 'pub_date',
            'id' => 'pub_date',
            'value' => evo()->toDateFormat(doc('pub_date')),
            'class' => 'DatePicker imeoff',
            'disabled' => (!evo()->hasPermission('publish_document') || $id == config('site_start')) ? null : ''
        ]
    )
        . html_tag(
            '<a>',
            ['style' => "cursor:pointer; cursor:hand;"],
            img_tag(
                style('icons_cal_nodate'),
                [
                    'alt' => lang('remove_date')
                ]
            )
        )
        . tooltip(lang('page_data_publishdate_help'))
        . html_tag(
            '<div>',
            [
                'style' => 'line-height:1;margin:0;color: #555;font-size:10px'
            ],
            config('datetime_format') . ' HH:MM:SS'
        );
    return renderTr(lang('page_data_publishdate'), $body);
}

function fieldUnpub_date($id)
{
    if (!evo()->hasPermission('publish_document')) {
        return '';
    }
    $body = input_text_tag(
        [
            'id' => 'unpub_date',
            'name' => 'unpub_date',
            'class' => 'DatePicker imeoff',
            'value' => evo()->toDateFormat(doc('unpub_date')),
            'onblur' => 'documentDirty=true;',
            'disabled' => (!evo()->hasPermission('publish_document') || $id == config('site_start')) ? null : ''
        ]
    )
        . html_tag(
            '<a>',
            ['style' => 'cursor:pointer; cursor:hand'],
            img_tag(style('icons_cal_nodate'), ['alt' => lang('remove_date')])
        )
        . tooltip(lang('page_data_unpublishdate_help'))
        . html_tag(
            '<div>',
            [
                'style' => 'line-height:1;margin:0;color: #555;font-size:10px'
            ],
            config('datetime_format') . ' HH:MM:SS'
        );
    return renderTr(lang('page_data_unpublishdate'), $body);
}

function fieldLink_attributes()
{
    $body = input_text_tag(
        [
            'name' => 'link_attributes',
            'value' => doc('link_attributes|hsc')
        ]
    )
        . tooltip(lang('link_attributes_help'));
    return renderTr(lang('link_attributes'), $body);
}

function fieldIsfolder()
{
    $body = html_tag(
        'input',
        [
            'name' => 'isfoldercheck',
            'type' => 'checkbox',
            'class' => 'checkbox',
            'checked' => doc('isfolder') ? null : '',
            'disabled' => request_intvar('id') && evo()->hasChildren(request_intvar('id'), '') ? null : '',
            'onclick' => 'changestate(document.mutate.isfolder);'
        ]
    )
        . html_tag(
            'input',
            [
                'type' => 'hidden',
                'name' => 'isfolder',
                'value' => doc('isfolder') ? 1 : 0
            ]
        )
        . tooltip(lang('resource_opt_folder_help'));
    return renderTr(lang('resource_opt_folder'), $body);
}

function fieldRichtext()
{
    $body = html_tag(
        'input',
        [
            'name' => 'richtextcheck',
            'type' => 'checkbox',
            'class' => 'checkbox',
            'checked' => doc('richtext') ? null : '',
            'disabled' => !config('use_editor') ? null : '',
            'onclick' => 'changestate(document.mutate.richtext);'
        ]
    )
        . html_tag(
            'input',
            [
                'type' => 'hidden',
                'name' => 'richtext',
                'value' => doc('richtext') ? 1 : 0
            ]
        )
        . tooltip(lang('resource_opt_richtext_help'));
    return renderTr(lang('resource_opt_richtext'), $body);
}

function fieldDonthit()
{
    $body = html_tag(
        'input',
        [
            'name' => 'donthitcheck',
            'type' => 'checkbox',
            'class' => 'checkbox',
            'checked' => !doc('donthit') ? null : '',
            'disabled' => !config('track_visitors') ? null : '',
            'onclick' => 'changestate(document.mutate.donthit);'
        ]
    )
        . html_tag(
            'input',
            [
                'type' => 'hidden',
                'name' => 'donthit',
                'value' => !doc('donthit') ? 1 : 0
            ]
        )
        . tooltip(lang('resource_opt_trackvisit_help'));
    return renderTr(lang('track_visitors_title'), $body);
}


function fieldSearchable()
{
    $body = html_tag(
        'input',
        [
            'name' => 'searchablecheck',
            'type' => 'checkbox',
            'class' => 'checkbox',
            'checked' => doc('searchable') ? null : '',
            'onclick' => 'changestate(document.mutate.searchable);'
        ]
    )
        . html_tag(
            'input',
            [
                'type' => 'hidden',
                'name' => 'searchable',
                'value' => doc('searchable') ? 1 : 0
            ]
        )
        . tooltip(lang('resource_opt_trackvisit_help'));
    return renderTr(lang('page_data_searchable'), $body);
}

function fieldCacheable()
{
    $body = html_tag(
        'input',
        [
            'name' => 'cacheablecheck',
            'type' => 'checkbox',
            'class' => 'checkbox',
            'checked' => doc('cacheable') ? null : '',
            'disabled' => !config('cache_type') ? null : '',
            'onclick' => 'changestate(document.mutate.cacheable);'
        ]
    )
        . html_tag(
            'input',
            [
                'type' => 'hidden',
                'name' => 'cacheable',
                'value' => doc('cacheable') ? 1 : 0
            ]
        )
        . tooltip(lang('page_data_cacheable_help'));
    return renderTr(lang('page_data_cacheable'), $body);
}

function fieldSyncsite()
{

    $cache_type = db()->getValue(
        'setting_value',
        '[+prefix+]system_settings',
        "setting_name='cache_type'"
    );
    $body = html_tag(
        'input',
        [
            'name' => 'syncsitecheck',
            'type' => 'checkbox',
            'class' => 'checkbox',
            'checked' => null,
            'disabled' => !$cache_type ? null : '',
            'onclick' => 'changestate(document.mutate.syncsite);'
        ]
    )
        . html_tag(
            'input',
            [
                'type' => 'hidden',
                'name' => 'syncsite',
                'value' => 1
            ]
        )
        . tooltip(lang('resource_opt_emptycache_help'));
    return renderTr(lang('resource_opt_emptycache'), $body);
}

function fieldType()
{
    return renderTr(
        lang('resource_type'),
        select_tag(
            [
                'name' => 'type',
                'style' => 'width:200px'
            ],
            [
                html_tag(
                    '<option>',
                    [
                        'value' => 'document',
                        'selected' => doc('type') !== 'reference' ? null : ''
                    ],
                    lang('resource_type_webpage')
                ),
                html_tag(
                    '<option>',
                    [
                        'value' => 'reference',
                        'selected' => doc('type') === 'reference' ? null : ''
                    ],
                    lang('resource_type_weblink')
                )
            ]
        )
            . tooltip(lang('resource_type_message'))
    );
}

function fieldContentType()
{
    if (doc('type') === 'reference') {
        return '';
    }
    $custom_contenttype = config('custom_contenttype');
    if (!$custom_contenttype) {
        return '';
    }
    $ct = explode(',', $custom_contenttype);
    $option = [];
    foreach ($ct as $value) {
        $option[] = html_tag(
            '<option>',
            [
                'value' => $value,
                'selected' => doc('contentType') === $value ? null : ''
            ],
            $value
        );
    }
    $body = parseText(
        file_get_tpl('field_content_type.tpl'),
        [
            'option' => implode("\n", $option)
        ]
    )
        . tooltip(lang('page_data_contentType_help'));
    return renderTr(lang('page_data_contentType'), $body);
}

function fieldContent_dispo()
{
    if (doc('type') === 'reference') {
        return '';
    }
    return renderTr(
        lang('resource_opt_contentdispo'),
        select_tag(
            [
                'name' => 'content_dispo',
                'style' => 'width:200px',
                'size' => 1
            ],
            [
                html_tag(
                    '<option>',
                    [
                        'value' => 0,
                        'selected' => !doc('content_dispo') ? null : ''
                    ],
                    lang('inline')
                ),
                html_tag(
                    '<option>',
                    [
                        'value' => 1,
                        'selected' => doc('content_dispo') ? null : ''
                    ],
                    lang('attachment')
                )
            ]
        )
            . tooltip(lang('resource_opt_contentdispo_help'))
    );
}
