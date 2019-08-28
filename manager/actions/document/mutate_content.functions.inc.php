<?php

function fieldPagetitle() {
    return renderTr(
        lang('resource_title')
        , input_text_tag(
            array(
                'name'=>'pagetitle',
                'value'=>doc('pagetitle|hsc')
            )
        ) . tooltip(lang('resource_title_help'))
    );
}

function fieldLongtitle() {
    return renderTr(
        lang('longtitle')
        , input_text_tag(
            array(
                'name'=>'longtitle',
                'value'=>doc('longtitle|hsc')
            )
        ) . tooltip(lang('resource_long_title_help'))
    );
}

function fieldDescription() {
    return  renderTr(
        lang('resource_description')
        , textarea_tag(
            array(
                'name'  => 'description',
                'class' => 'inputBox',
                'style' => 'height:43px;',
                'rows'  => '2'
            )
            , doc('description|hsc')
        )
        . tooltip(lang('resource_description_help'))
        , 'vertical-align:top;'
    );
}

function fieldAlias($id) {
    $props = array(
        'name'  => 'alias',
        'value' => doc('alias|hsc')
    );
    if(!config('friendly_urls') || doc('type') !== 'document') {
        $props['maxlength'] = 100;
        return renderTr(
            lang('resource_alias')
            , input_text_tag($props) . tooltip(lang('resource_alias_help')));
    }

    $props['size']      = 20;
    $props['style']     = 'width:120px;';
    $props['maxlength'] = 50;
    if (config('suffix_mode')) {
        $props['onkeyup'] = 'change_url_suffix();';
    }
    $props['placeholder'] = doc('id');
    $body = get_alias_path($id)
        . input_text_tag($props)
        . html_tag(
            '<span>'
            , array('id'=>"url_suffix")
            , call_user_func(function () {
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
        . tooltip(lang('resource_alias_help'));
    return renderTr(lang('resource_alias'), $body);
}

// Web Link specific
function fieldWeblink() {
    return renderTr(
        lang('weblink') . html_tag(
            '<img>'
            , array(
                'name'    => 'llock',
                'src'     => style('tree_folder'),
                'alt'     => 'tree_folder',
                'onclick' => 'enableLinkSelection(!allowLinkSelection);',
                'style'   => 'cursor:pointer;'
            )
        )
        , input_text_tag(
            array(
                'name'  => 'ta',
                'value' => doc('content') ? strip_tags(stripslashes(doc('content'))) : 'http://'
            )
        )
        . html_tag(
            '<input>'
            , array(
                'type'    => 'button',
                'onclick' => "BrowseFileServer('field_ta')",
                'value'   => lang('insert')
            )
        )
        . tooltip(lang('resource_weblink_help'))
    );
}

function fieldIntrotext() {
    return renderTr(
        lang('resource_summary')
        , textarea_tag(
            array(
                'name'=>"introtext",
                'class'=>"inputBox",
                'style'=>"height:60px;",
                'rows'=>"3"
            )
            , doc('introtext|hsc')
        ) . tooltip(lang('resource_summary_help'))
        , 'vertical-align:top;'
    );
}

function fieldTemplate() {
    return renderTr(
        lang('page_data_template')
        , select_tag(
            array(
                'id'   => 'template',
                'name' => 'template',
                'style'=> 'width:308px'
            )
            , get_template_options()
        )
        . tooltip(lang('page_data_template_help'))
    );
}

function fieldMenutitle() {
    return renderTr(
        lang('resource_opt_menu_title')
        , input_text_tag(
            array(
                'name' => 'menutitle',
                doc('menutitle|hsc')
            )
        )
        . tooltip(lang('resource_opt_menu_title_help'))
    );
}

function fieldMenuindex() {
    return renderTr(
        lang('resource_opt_menu_index')
        , menuindex()
    );
}

function fieldParent() {
    return renderTr(
        lang('resource_parent')
        , getParentForm(
            getParentName(
                doc('parent')
            )
        )
    );
}

function getTmplvars($docid,$template_id,$docgrp) {
    if(!$docid || !$template_id) {
        return array();
    }

    static $tmplVars = null;
    if($tmplVars!==null) {
        return $tmplVars;
    }
    $tmplVars = array();

    $rs = db()->select(
        array(
            'DISTINCT tv.*',
            'value' => "tvtpl.rank, IF(tvc.value!='',tvc.value,tv.default_text)"
        )
        , array(
            '[+prefix+]site_tmplvars AS tv',
            'INNER JOIN [+prefix+]site_tmplvar_templates AS tvtpl ON tvtpl.tmplvarid=tv.id',
            sprintf(
                "LEFT JOIN [+prefix+]site_tmplvar_contentvalues AS tvc ON tvc.tmplvarid=tv.id AND tvc.contentid='%s'"
                , $docid
            ),
            'LEFT JOIN [+prefix+]site_tmplvar_access AS tva ON tva.tmplvarid=tv.id'
        )
        , sprintf(
            "tvtpl.templateid='%s' AND (1='%s' OR ISNULL(tva.documentgroup) %s)"
            , $template_id
            , evo()->session_var('mgrRole')
            , $docgrp ? sprintf(' OR tva.documentgroup IN (%s)', $docgrp) : ''
        )
        , 'tvtpl.rank,tv.rank, tv.id'
    );

    if(!db()->getRecordCount($rs)) {
        return array();
    }
    while ($row = db()->getRow($rs)) {
        $tmplVars[$row['name']] = $row;
    }
    return $tmplVars;
}

function rteContent($htmlcontent,$editors) {
    return textarea_tag(
            array(
                'id'    => 'ta',
                'name'  => 'ta',
                'style' => 'width:100%;height:350px;'
            )
            , $htmlcontent
        )
        . html_tag('<span>', array('class'=>'warning'), lang('which_editor_title'))
        . getEditors($editors)
        ;
}

function getEditors($editors) {
    global $selected_editor;
    if (!is_array($editors)) return '';

    if(!$editors) {
        return '';
    }

    $options = array(
        html_tag('<option>', array('value'=>'none'), lang('none'))
    );
    foreach ($editors as $editor) {
        $options[] = html_tag(
            '<option>'
            , array(
                'value'    => $editor,
                'selected' => $selected_editor === $editor ? null : ''
            )
            , $editor
        );
    }
    return select_tag(array(
            'id'   => 'which_editor',
            'name' => 'which_editor'
        )
        , implode("\n", $options)
    );
}

function getTplSectionContent() {
    return file_get_contents(__DIR__ . '/tpl/mutate_content/section_content.tpl');
}

function getTplSectionTV() {
    return file_get_contents(__DIR__ . '/tpl/mutate_content/section_tv.tpl');
}

function getTplTVRow() {
    return file_get_contents(__DIR__ . '/tpl/mutate_content/tv_row.tpl');
}

function sectionContent() {
    global $rte_field;
    if (doc('type') !== 'document')
        return '';

    $tpl = getTplSectionContent();
    $htmlcontent = htmlspecialchars(doc('content'));

    $ph['header'] = lang('resource_content');
    $planetpl = '<textarea class="phptextarea" id="ta" name="ta" style="width:100%; height: 400px;">'.$htmlcontent.'</textarea>';
    if (evo()->config['use_editor'] == 1 && doc('richtext') == 1) {
        // invoke OnRichTextEditorRegister event
        $editors = evo()->invokeEvent('OnRichTextEditorRegister');
        if($editors) {
            $ph['body'] = rteContent($htmlcontent, $editors);
        } else {
            $ph['body'] = $planetpl;
        }
        $rte_field = array('ta');
    } else {
        $ph['body'] = $planetpl;
    }

    return parseText($tpl,$ph);
}

function sectionTV() {
    $tpl = getTplSectionTV();
    $ph = array();
    $ph['header'] = lang('settings_templvars');
    $ph['body'] = fieldsTV();
    return parseText($tpl,$ph);
}

function rte_fields() {
    $rte_fields = array();
    if (evo()->config['use_editor'] == 1 && doc('richtext') == 1) {
        $rte_fields[] = 'ta';
    }
}
function fieldsTV() {
    global $tmplVars, $rte_field;

    $tpl = getTplTVRow();
    $total = count($tmplVars);
    $form_v = $_POST ? $_POST : array();
    if(empty($total)) return '';

    $i = 0;
    $output = array();
    $hidden = array();
    $output[] = '<table style="position:relative;" border="0" cellspacing="0" cellpadding="3" width="96%">';
    $splitLine = renderSplit();
    foreach($tmplVars as $tv) {
        $tvid = 'tv' . $tv['id'];
        // Go through and display all Template Variables
        if ($tv['type'] === 'richtext' || $tv['type'] === 'htmlarea') {
            // Add richtext editor to the list
            if (is_array($rte_field)) {
                $rte_field[] = $tvid;
            } else {
                $rte_field = array($tvid);
            }
        }

        // post back value
        if(isset($form_v[$tvid])){
            switch( $tv['type'] ){
                case 'checkbox':
                case 'listbox-multiple':
                    $tvPBV = implode('||', $form_v[$tvid]);
                    break;
                case 'url':
                    if( $form_v[$tvid.'_prefix'] === 'DocID' ) {
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

        if($tv['type']==='hidden') {
            $formElement = evo()->renderFormElement(
                'hidden'
                , $tv['id']
                , $tv['default_text']
                , $tv['elements']
                , $tvPBV
                , ''
                , $tv
            );
            $hidden[] = $formElement;
        } else {
            $ph = array();
            $ph['caption']     = evo()->hsc($tv['caption']);
            $ph['description'] = $tv['description'];
            $ph['zindex']      = ($tv['type'] === 'date') ? 'z-index:100;' : '';
            $ph['FormElement'] = evo()->renderFormElement(
                $tv['type']
                , $tv['id']
                , $tv['default_text']
                , $tv['elements']
                , $tvPBV
                , ''
                , $tv
            );
            if($ph['FormElement']!=='')
            {
                $output[] = parseText($tpl,$ph);
                if ($i < $total) $output[] = $splitLine;
            }
        }
        $i++;
    }

    if($output && $output[$total+1]===$splitLine) array_pop($output);

    $output[] = '</table>';

    return implode("\n",$output) . join("\n", $hidden);
}

function fieldPublished() {
    if(!evo()->hasPermission('publish_document')) {
        if(evo()->manager->action==27) {
            $published = doc('published');
        } else {
            $published = 0;
        }
    } else {
        $published = evo()->documentObject['published'];
    }

    $body = input_checkbox('published',$published==1);
    $body .= input_hidden('published',$published==1);
    $body .= tooltip(lang('resource_opt_published_help'));
    return renderTr(lang('resource_opt_published'),$body);
}

function fieldPub_date($id=0) {
    $body = input_text_tag(
            array(
                'name'  => 'pub_date',
                'id'    => 'pub_date',
                'value' => evo()->toDateFormat(doc('pub_date')),
                'class' => 'DatePicker imeoff',
                'disabled' => (!evo()->hasPermission('publish_document') || $id==config('site_start')) ? null : ''
            )
        )
        . html_tag(
            '<a>'
            , array('style'=>"cursor:pointer; cursor:hand;")
            , img_tag(
                style('icons_cal_nodate')
                , array(
                    'alt' => lang('remove_date')
                )
            )
        )
        . tooltip(lang('page_data_publishdate_help'))
        . html_tag(
            '<div>'
            , array(
                'style'=>'line-height:1;margin:0;color: #555;font-size:10px'
            )
            , config('datetime_format') . ' HH:MM:SS'
        );
    return renderTr(lang('page_data_publishdate'), $body);
}

function fieldUnpub_date($id) {
    if(!evo()->hasPermission('publish_document')) {
        return '';
    }
    $body = input_text_tag(
            array(
                'id'       => 'unpub_date',
                'name'     => 'unpub_date',
                'class'    => 'DatePicker imeoff',
                'value'    => evo()->toDateFormat(doc('unpub_date')),
                'onblur'   => 'documentDirty=true;',
                'disabled' => (!evo()->hasPermission('publish_document') || $id==config('site_start')) ? null : ''
            )
        )
        . html_tag(
            '<a>'
            , array('style' => 'cursor:pointer; cursor:hand')
            , img_tag(style('icons_cal_nodate'), array('alt'=>lang('remove_date')))
        )
        . tooltip(lang('page_data_unpublishdate_help'))
        . html_tag(
            '<div>'
            , array(
                'style' => 'line-height:1;margin:0;color: #555;font-size:10px'
            )
            , config('datetime_format') . ' HH:MM:SS'
        );
    return renderTr(lang('page_data_unpublishdate'),$body);
}

function getDocId() {
    if (preg_match('@^[1-9][0-9]*$@', evo()->input_any('id'))) {
        return evo()->input_any('id');
    }
    return '0';
}

function input_any($key) {
    if (preg_match('@^[1-9][0-9]*$@', evo()->input_any($key))) {
        return evo()->input_any($key);
    }
    return '0';
}

function getInitialValues() {
    global $default_template;

    $init_v['menuindex'] = getMenuIndexAtNew();
    $init_v['alias']     = getAliasAtNew();
    $init_v['richtext']  = evo()->config['use_editor'];
    $init_v['published'] = evo()->config['publish_default'];
    $init_v['contentType'] = 'text/html';
    $init_v['content_dispo'] = '0';
    $init_v['which_editor'] = evo()->config['which_editor'];
    $init_v['searchable'] = evo()->config['search_default'];
    $init_v['cacheable'] = evo()->config['cache_default'];

    if(evo()->manager->action==4) {
        $init_v['type'] = 'document';
    } elseif(evo()->manager->action==72) {
        $init_v['type'] = 'reference';
    }

    if(isset($_GET['pid'])) {
        $init_v['parent'] = $_GET['pid'];
    }

    if(isset ($_REQUEST['newtemplate'])) {
        $init_v['template'] = $_REQUEST['newtemplate'];
    } else {
        $init_v['template'] = $default_template;
    }

    return $init_v;
}

function fieldLink_attributes() {
    $body  = input_text_tag(
            array(
                'name'=>'link_attributes',
                'value'=>doc('link_attributes|hsc')
            )
        )
        . tooltip(lang('link_attributes_help'))
    ;
    return renderTr(lang('link_attributes'),$body);
}

function fieldIsfolder() {
    $haschildren = db()->getValue(
        db()->select(
            'count(id)'
            ,'[+prefix+]site_content'
            , sprintf("parent='%s'", input_any('id'))
        )
    );
    $body = html_tag(
            'input'
            ,array(
                'type'     => 'checkbox',
                'name'     => 'isfoldercheck',
                'checked'  => doc('isfolder')==1 ? null : '',
                'disabled' => input_any('id')!=0 && 0<$haschildren ? null : '',
                'class'    => 'checkbox',
                'onclick'  => 'changestate(document.mutate.isfolder);'
            )
        )
        . html_tag(
            'input'
            ,array(
                'type' => 'hidden',
                'name' =>'isfolder',
                'value'=> doc('isfolder')==1 ? 1 : 0
            )
        )
        . tooltip(lang('resource_opt_folder_help'));
    return renderTr(lang('resource_opt_folder'),$body);
}

function fieldRichtext() {
    $disabled = (evo()->config['use_editor']!=1) ? ' disabled="disabled"' : '';
    $cond = (!isset(evo()->documentObject['richtext']) || evo()->documentObject['richtext']!=0);
    $body = input_checkbox('richtext',$cond,$disabled);
    $body .= input_hidden('richtext',$cond);
    $body .= tooltip(lang('resource_opt_richtext_help'));
    return renderTr(lang('resource_opt_richtext'),$body);
}

function fieldDonthit() {
    global $docObject;
    $cond = ($docObject['donthit']!=1);
    $body = input_checkbox('donthit',$cond);
    $body .= input_hidden('donthit',!$cond);
    $body .= tooltip(lang('resource_opt_trackvisit_help'));
    return renderTr(lang('track_visitors_title'),$body);
}


function fieldSearchable() {
    global $docObject;
    $cond = ($docObject['searchable']==1);
    $body = input_checkbox('searchable',$cond);
    $body .= input_hidden('searchable',$cond);
    $body .= tooltip(lang('page_data_searchable_help'));
    return renderTr(lang('page_data_searchable'),$body);
}

function fieldCacheable() {
    global $docObject;
    $cond = ($docObject['cacheable']==1);
    $disabled = (evo()->config['cache_type']==='0') ? ' disabled' : '';
    $body = input_checkbox('cacheable',$cond,$disabled);
    $body .= input_hidden('cacheable',$cond);
    $body .= tooltip(lang('page_data_cacheable_help'));
    return renderTr(lang('page_data_cacheable'),$body);
}

function fieldSyncsite() {
    $disabled = (evo()->config['cache_type']==0) ? ' disabled' : '';
    $body = input_checkbox('syncsite',true,$disabled);
    $body .= input_hidden('syncsite');
    $body .= tooltip(lang('resource_opt_emptycache_help'));
    return renderTr(lang('resource_opt_emptycache'),$body);
}

function fieldType() {
    $body = select_tag(array(
                'name'  => 'type',
                'class' => 'inputBox',
                'style' => 'width:200px'
            )
            , array(
                html_tag(
                    '<option>'
                    , array(
                        'value'=>'document',
                        'selected' => (doc('type')!=='reference') ? null : ''
                    )
                    ,lang('resource_type_webpage')),
                html_tag(
                    '<option>'
                    , array(
                        'value'=>'reference',
                        'selected' => (doc('type')==='reference') ? null : ''
                    )
                    ,lang('resource_type_weblink'))
            )
        )
        .tooltip(lang('resource_type_message'))
    ;
    return renderTr(lang('resource_type'),$body);
}

function fieldContentType() {
    global $docObject;

    if($docObject['type'] === 'reference') {
        return '';
    }
    $tpl = <<< EOT
<select name="contentType" class="inputBox" style="width:200px">
	[+option+]
</select>
EOT;
    $ct = explode(',', evo()->config['custom_contenttype']);
    $option = array();
    foreach ($ct as $value)
    {
        $ph['selected'] = $docObject['contentType'] === $value ? ' selected' : '';
        $ph['value'] = $value;
        $option[] = parseText('<option value="[+value+]" [+selected+]>[+value+]</option>',$ph);
    }
    $ph = array();
    $ph['option'] = join("\n", $option);
    $body = parseText($tpl,$ph) . tooltip(lang('page_data_contentType_help'));
    return renderTr(lang('page_data_contentType'),$body);
}

function fieldContent_dispo() {
    global $docObject;

    if($docObject['type'] === 'reference') return;
    $tpl = <<< EOT
<select name="content_dispo" size="1" style="width:200px">
	<option value="0" [+sel_inline+]>[+inline+]</option>
	<option value="1" [+sel_attachment+]>[+attachment+]</option>
</select>
EOT;
    $ph = array();
    $ph['sel_attachment'] = $docObject['content_dispo']==1 ? 'selected' : '';
    $ph['sel_inline'] = $ph['sel_attachment']==='' ? 'selected' : '';
    $ph['inline']     = lang('inline');
    $ph['attachment'] = lang('attachment');
    $body = parseText($tpl,$ph);
    return renderTr(lang('resource_opt_contentdispo'),$body);
}

function getGroups($docid) {
    // Load up, the permissions from the parent (if new document) or existing document
    $rs = db()->select('id, document_group','[+prefix+]document_groups',"document='{$docid}'");
    $groupsarray = array();
    while ($row = db()->getRow($rs))
    {
        $groupsarray[] = $row['document_group'].','.$row['id'];
    }
    return $groupsarray;
}

function getUDGroups($id)
{
    global $docObject, $permissions_yes, $permissions_no;

    $form_v = $_POST;
    $groupsarray = array();

    if (evo()->manager->action == 27) {
        $docid = $id;
    } elseif (!empty($_REQUEST['pid'])) {
        $docid = $_REQUEST['pid'];
    } else {
        $docid = $docObject['parent'];
    }

    if (0 < $docid) {
        $groupsarray = getGroups($docid);
        // Load up the current permissions and names
        $field = 'dgn.*, groups.id AS link_id';
        $from[] = '[+prefix+]documentgroup_names AS dgn';
        $from[] = "LEFT JOIN [+prefix+]document_groups AS `groups` ON `groups`.document_group = dgn.id AND groups.document = {$docid}";
        $from = implode(' ', $from);
    } else {
        // Just load up the names, we're starting clean
        $field = '*, NULL AS link_id';
        $from = '[+prefix+]documentgroup_names';
    }

    $isManager = evo()->hasPermission('access_permissions');
    $isWeb = evo()->hasPermission('web_access_permissions');

    // Setup Basic attributes for each Input box
    $inputAttributes['type'] = 'checkbox';
    $inputAttributes['class'] = 'checkbox';
    $inputAttributes['name'] = 'docgroups[]';
    $inputAttributes['onclick'] = 'makePublic(false)';

    $permissions = array(); // New Permissions array list (this contains the HTML)
    $permissions_yes = 0; // count permissions the current mgr user has
    $permissions_no = 0; // count permissions the current mgr user doesn't have

    // retain selected doc groups between post
    if (isset($form_v['docgroups']))
        $groupsarray = array_merge($groupsarray, $form_v['docgroups']);

    // Query the permissions and names from above
    $rs = db()->select($field, $from, '', 'name');

    // Loop through the permissions list
    while ($row = db()->getRow($rs)) {
        // Create an inputValue pair (group ID and group link (if it exists))
        $inputValue = $row['id'] . ',' . ($row['link_id'] ? $row['link_id'] : 'new');
        $inputId = 'group-' . $row['id'];

        $checked = in_array($inputValue, $groupsarray);
        if ($checked) {
            $notPublic = true;
        } // Mark as private access (either web or manager)

        // Skip the access permission if the user doesn't have access...
        if ((!$isManager && $row['private_memgroup'] == '1') || (!$isWeb && $row['private_webgroup'] == '1')) {
            continue;
        }

        // Setup attributes for this Input box
        $inputAttributes['id'] = $inputId;
        $inputAttributes['value'] = $inputValue;
        if ($checked) {
            $inputAttributes['checked'] = 'checked';
        } else {
            unset($inputAttributes['checked']);
        }

        // Create attribute string list
        $inputString = array();
        foreach ($inputAttributes as $k => $v) {
            $inputString[] = $k . '="' . $v . '"';
        }

        // Make the <input> HTML
        $inputHTML = '<input ' . implode(' ', $inputString) . ' />' . "\n";

        // does user have this permission?
        $from = "[+prefix+]membergroup_access mga, [+prefix+]member_groups mg";
        $where = "mga.membergroup = mg.user_group AND mga.documentgroup = {$row['id']} AND mg.member = {$_SESSION['mgrInternalKey']}";
        $count = db()->getValue(db()->select('COUNT(mg.id)', $from, $where));

        if ($count > 0) {
            ++$permissions_yes;
        } else {
            ++$permissions_no;
        }

        $permissions[] = "\t\t"
            . html_tag(
                '<li>'
                , array()
                , $inputHTML . html_tag('<label>', array('for'=>$inputId), $row['name'])
            );
    }

    if(!empty($permissions)) {
        // Add the "All Document Groups" item if we have rights in both contexts
        if ($isManager && $isWeb)
        {
            array_unshift($permissions,"\t\t".'<li><input type="checkbox" class="checkbox" name="chkalldocs" id="groupall"' . checked(!$notPublic) . ' onclick="makePublic(true);" /><label for="groupall" class="warning">' . lang('all_doc_groups') . '</label></li>');
            // Output the permissions list...
        }
    }

    // if mgr user doesn't have access to any of the displayable permissions, forget about them and make doc public
    if($_SESSION['mgrRole'] != 1 && ($permissions_yes == 0 && $permissions_no > 0))
    {
        $permissions = array();
    }
    return $permissions;
}

function getTplHead()
{
    $tpl = <<< EOT
[+JScripts+]
<form name="mutate" id="mutate" class="content" method="post" enctype="multipart/form-data" action="index.php" onsubmit="documentDirty=false;">
	<input type="hidden" name="a" value="[+a+]" />
	<input type="hidden" name="id" value="[+id+]" />
	<input type="hidden" name="mode" value="[+mode+]" />
	<input type="hidden" name="MAX_FILE_SIZE" value="[+upload_maxsize+]" />
	<input type="hidden" name="newtemplate" value="" />
	<input type="hidden" name="pid" value="[+pid+]" />
	<input type="hidden" name="token" value="[+token+]" />
	<input type="submit" name="save" style="display:none" />
	[+OnDocFormPrerender+]
	
	<fieldset id="create_edit">
	<h1 class="[+class+]">[+title+]</h1>

	[+actionButtons+]

	<div class="sectionBody">
	<div class="tab-pane" id="documentPane">
EOT;
    return $tpl;
}

function getTplFoot()
{
    $tpl = <<< EOT
		[+OnDocFormRender+]
	</div><!--div class="tab-pane" id="documentPane"-->
	</div><!--div class="sectionBody"-->
	</fieldset>
	<script>
		tpSettings = new WebFXTabPane(document.getElementById('documentPane'), [+remember_last_tab+] );
	</script>
</form>
[+OnRichTextEditorInit+]
EOT;
    return $tpl;
}

function getTplTabGeneral()
{
    $tpl = <<< EOT
<!-- start main wrapper -->
	<!-- General -->
	<div class="tab-page" id="tabGeneral">
		<h2 class="tab" id="tabGeneralHeader">[+_lang_settings_general+]</h2>
		<table width="99%" border="0" cellspacing="5" cellpadding="0">
			[+fieldPagetitle+]
			[+fieldLongtitle+]
			[+fieldDescription+]
			[+fieldAlias+]
			[+fieldWeblink+]
			[+fieldIntrotext+]
			[+fieldTemplate+]
			[+fieldMenutitle+]
			[+fieldMenuindex+]
			[+renderSplit+]
			[+fieldParent+]
		</table>
		[+sectionContent+]
		[+sectionTV+]
	</div><!-- end #tabGeneral -->
EOT;
    return $tpl;
}

function getTplTabTV()
{
    $tpl = <<< EOT
<!-- TVs -->
<div class="tab-page" id="tabTVs">
	<h2 class="tab" id="tabTVsHeader">[+_lang_tv+]</h2>
	[+TVFields+]
</div>
EOT;
    return $tpl;
}

function getTplTabSettings()
{
    $tpl = <<< EOT
	<!-- Settings -->
	<div class="tab-page" id="tabSettings">
		<h2 class="tab" id="tabSettingsHeader">[+_lang_settings_page_settings+]</h2>
		<table width="99%" border="0" cellspacing="5" cellpadding="0">
			[+fieldPublished+]
			[+fieldPub_date+]
			[+fieldUnpub_date+]
			[+renderSplit1+]
			[+fieldType+]
			[+fieldContentType+]
			[+fieldContent_dispo+]
			[+renderSplit2+]
			[+fieldLink_attributes+]
			[+fieldIsfolder+]
			[+fieldRichtext+]
			[+fieldDonthit+]
			[+fieldSearchable+]
			[+fieldCacheable+]
			[+fieldSyncsite+]
		</table>
	</div><!-- end #tabSettings -->
EOT;
    return $tpl;
}

function getTplTabAccess() {
    $tpl = <<< EOT
<!-- Access Permissions -->
<div class="tab-page" id="tabAccess">
	<h2 class="tab" id="tabAccessHeader">[+_lang_access_permissions+]</h2>
	<script type="text/javascript">
		/* <![CDATA[ */
		function makePublic(b) {
			var notPublic = false;
			var f = document.forms['mutate'];
			var chkpub = f['chkalldocs'];
			var chks = f['docgroups[]'];
			if (!chks && chkpub) {
				chkpub.checked=true;
				return false;
			} else if (!b && chkpub) {
				if (!chks.length) notPublic = chks.checked;
				else for (i = 0; i < chks.length; i++) if (chks[i].checked) notPublic = true;
				chkpub.checked = !notPublic;
			} else {
				if (!chks.length) chks.checked = (b) ? false : chks.checked;
				else for (i = 0; i < chks.length; i++) if (b) chks[i].checked = false;
				chkpub.checked = true;
			}
		}
		/* ]]> */
	</script>
	<p>[+_lang_access_permissions_docs_message+]</p>
	<ul>
		[+UDGroups+]
	</ul>
</div><!-- end #tabAccess -->
EOT;
    return $tpl;
}

function mergeDraft($id,$content) {
    $revision_content = evo()->revision->getDraft($id);
    foreach($content as $k=>$v) {
        if(!is_array($v)) continue;
        $tvid = 'tv'.$v['id'];
        if(isset($revision_content[$tvid])) {
            $content[$k]['value'] = $revision_content[$tvid];
            unset($revision_content[$tvid]);
        }
    }
    $content = array_merge($content, $revision_content);
    if(!evo()->hasPermission('publish_document')) $content['published'] = '0';
    return $content;
}

function input_text($name,$value,$other='',$maxlength='255') {
    $ph['name']      = $name;
    $ph['value']     = $value;
    $ph['maxlength'] = $maxlength;
    $ph['other']     = $other;
    $ph['class']     = 'inputBox';
    switch($name)
    {
        case 'menuindex':
            $ph['class'] .= ' number imeoff';
            break;
    }

    $tpl = '<input name="[+name+]" id="field_[+name+]" type="text" maxlength="[+maxlength+]" value="[+value+]" class="[+class+]" [+other+] />';
    return parseText($tpl,$ph);
}

function input_checkbox($name,$checked,$other='') {
    $ph['name']    = $name;
    $ph['checked'] = ($checked) ? 'checked="checked"' : '';
    $ph['other']   = $other;
    $ph['resetpubdate'] = ($name === 'published') ? 'resetpubdate();' : '';
    if($name === 'published')
    {
        $id = (isset($_REQUEST['id'])&&preg_match('@^[1-9][0-9]*$@',$_REQUEST['id'])) ? $_REQUEST['id'] : 0;

        if(!evo()->hasPermission('publish_document') || $id===evo()->config['site_start'])
        {
            $ph['other'] = 'disabled="disabled"';
        }
    }
    $tpl = '<input name="[+name+]check" type="checkbox" class="checkbox" [+checked+] onclick="changestate(document.mutate.[+name+]);[+resetpubdate+]" [+other+] />';
    return parseText($tpl,$ph);
}

function checked($cond=false) {
    if($cond) {
        return ' checked="checked"';
    }
    return '';
}

function disabled($cond=false) {
    if($cond) {
        return ' disabled="disabled"';
    }
    return '';
}

function tooltip($msg) {
    global $_style;

    return html_tag(
        '<img>'
        , array(
            'src'     => $_style['icons_tooltip_over'],
            'title'   => $msg,
            'alt'     => $msg,
            'onclick' => 'alert(this.alt);',
            'style'   => 'cursor:help;',
            'class'   => 'tooltip'
        )
    );
}

function input_hidden($name,$cond=true) {
    $ph['name']  = $name;
    $ph['value'] = ($cond) ? '1' : '0';
    $tpl = '<input type="hidden" name="[+name+]" class="hidden" value="[+value+]" />';
    return parseText($tpl,$ph);
}

function ab_preview($id=0) {
    global $_style;
    $tpl = '<li id="preview"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = $_style["icons_preview_resource"];
    $ph['alt'] = 'preview resource';
    $ph['label'] = lang('preview');
    return parseText($tpl,$ph);
}

function ab_save() {
    global $_style;

    $tpl = '<li id="save" class="primary mutate"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a>[+select+]</li>';
    $ph['icon'] = $_style["icons_save"];
    $ph['alt'] = 'icons_save';
    $ph['label'] = lang('update');

    $ph['select'] = '<span class="and"> + </span><select id="stay" name="stay">%s</select>';
    $saveAfter = isset($_REQUEST['stay']) ? $_REQUEST['stay'] : $_SESSION['saveAfter'];
    $selected = array('new'=>'', 'stay'=>'', 'close'=>'');
    if (evo()->hasPermission('new_document')
        && $saveAfter === 'new')    $selected['new']   = 'selected';
    elseif($saveAfter === 'stay')   $selected['stay']  = 'selected';
    elseif($saveAfter === 'close')  $selected['close'] = 'selected';
    else                         $selected['close'] = 'selected';

    if (evo()->doc->mode !== 'draft'&&evo()->hasPermission('new_document')&&evo()->hasPermission('save_document'))
        $option[] = sprintf('<option id="stay1" value="new" %s >%s</option>', $selected['new'], lang('stay_new'));

    $option[] = sprintf('<option id="stay2" value="stay" %s >%s</option>'    , $selected['stay'], lang('stay'));
    if(evo()->doc->mode==='draft' && evo()->hasPermission('publish_document')) {
        if(evo()->revision->hasStandby)
            $option[] = sprintf('<option id="stay4" value="save_standby">%s</option>'     , '下書採用日時を再指定');
        else
            $option[] = sprintf('<option id="stay4" value="save_draft">%s</option>'     , '下書きを採用');
    }
    $option[] = sprintf('<option id="stay3" value="close" %s >%s</option>'     , $selected['close'], lang('close'));

    $ph['select'] = sprintf($ph['select'], join("\n", $option));

    return parseText($tpl,$ph);
}

function ab_open_draft($id) {
    global $_style;

    $tpl = '<li id="opendraft" class="opendraft mutate"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = $_style["icons_save"];
    $ph['alt'] = 'icons_draft';
    $ph['label'] = lang('open_draft');
    return parseText($tpl,$ph);
}

function ab_create_draft($id) {
    global $_style;

    if(!evo()->config['enable_draft']) return false;

    if(!evo()->hasPermission('edit_document')) return false;

    $tpl = '<li id="createdraft" class="mutate"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = $_style["icons_save"];
    $ph['alt'] = 'icons_draft';
    $ph['label'] = lang('create_draft');

    return parseText($tpl,$ph);
}

function ab_cancel($id) {
    global $_style;

    $tpl = '<li id="cancel" class="mutate"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = $_style["icons_cancel"];
    $ph['alt'] = 'icons_cancel';
    $ph['label'] = lang('cancel');
    return parseText($tpl,$ph);
}

function ab_move() {
    global $_style;

    $tpl = '<li id="move" class="mutate"><a href="#"><img src="[+icon+]" /> [+label+]</a></li>';
    $ph['icon'] = $_style["icons_move_document"];
    $ph['label'] = lang('move');
    return parseText($tpl,$ph);
}

function ab_duplicate() {
    global $_style;

    $tpl = '<li id="duplicate"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = $_style["icons_resource_duplicate"];
    $ph['alt'] = 'icons_resource_duplicate';
    $ph['label'] = lang('duplicate');
    return parseText($tpl,$ph);
}

function ab_delete() {
    global $_style;

    $tpl = '<li id="delete"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = $_style["icons_delete_document"];
    $ph['alt'] = 'icons_delete_document';
    $ph['label'] = lang('delete');
    return parseText($tpl,$ph);
}

function ab_undelete() {
    global $_style;

    $tpl = '<li id="undelete"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = $_style["icons_undelete_resource"];
    $ph['alt'] = 'icons_undelete_document';
    $ph['label'] = lang('undelete_resource');
    return parseText($tpl,$ph);
}

function ab_delete_draft() {
    global $_style;

    $tpl = '<li id="deletedraft"><a href="#"><img src="[+icon+]" alt="[+alt+]" /> [+label+]</a></li>';
    $ph['icon'] = $_style["icons_delete_document"];
    $ph['alt'] = 'icons_delete_document';
    $ph['label'] = lang('delete_draft');
    return parseText($tpl,$ph);
}

function get_alias_path($id) {
    $pid = (int)$_REQUEST['pid'];

    if (evo()->config['use_alias_path']==='0') {
        return MODX_BASE_URL;
    }

    if ($pid) {
        if (evo()->getAliasListing($pid, 'path')) {
            $path = evo()->getAliasListing($pid, 'path') . '/' . evo()->getAliasListing($pid, 'alias');
        } else {
            $path = evo()->getAliasListing($pid, 'alias');
        }
    } elseif (!$id) {
        return MODX_BASE_URL;
    } else {
        $path = evo()->getAliasListing($id, 'path');
    }

    if($path === '') {
        $path = MODX_BASE_URL;
    } else {
        $path = MODX_BASE_URL . $path . '/';
    }

    if(30 < strlen($path)) {
        $path .= '<br />';
    }
    return $path;
}

function renderTr($head, $body,$rowstyle='') {
    if(!is_array($head)) {
        $ph['head'] = $head;
        $ph['extra_head'] = '';
    }
    else {
        $i = 0;
        foreach($head as $v) {
            if($i===0) $ph['head'] = $v;
            else $extra_head[] = $v;
            $i++;
        }
        $ph['extra_head'] = join("\n", $extra_head);
    }
    if(is_array($body)) $body = join("\n", $body);
    $ph['body'] = $body;
    $ph['rowstyle'] = $rowstyle;

    $tpl =<<< EOT
	<tr style="height: 24px;[+rowstyle+]">
		<td width="120" align="left">
			<span class="warning">[+head+]</span>[+extra_head+]
		</td>
		<td>
			[+body+]
		</td>
	</tr>
EOT;
    return parseText($tpl, $ph);
}

function getDefaultTemplate() {
    $pid = (isset($_REQUEST['pid']) && !empty($_REQUEST['pid'])) ? $_REQUEST['pid'] : '0';
    $site_start = evo()->config['site_start'];

    if(evo()->config['auto_template_logic']==='sibling') :
        $where = "id!='{$site_start}' AND isfolder=0 AND parent='{$pid}'";
        $orderby = 'published DESC,menuindex ASC';
        $rs = db()->select('template', '[+prefix+]site_content', $where, $orderby, '1');
    elseif(evo()->config['auto_template_logic']==='parent' && $pid!=0) :
        $rs = db()->select('template','[+prefix+]site_content',"id='{$pid}'");
    endif;

    if(isset($rs)&&db()->getRecordCount($rs)==1) {
        $row = db()->getRow($rs);
        $default_template = $row['template'];
    }

    if(!isset($default_template))
        $default_template = evo()->config['default_template']; // default_template is already set

    return $default_template;
}

// check permissions
function checkPermissions($id) {
    global $e;

    $isAllowed = evo()->manager->isAllowed($id);
    if (!isset($_GET['pid'])&&!$isAllowed)
    {
        $e->setError(3);
        $e->dumpError();
    }

    switch (evo()->manager->action) {
        case 27:
            if (!evo()->hasPermission('view_document')) {
                evo()->config['remember_last_tab'] = 0;
                $e->setError(3);
                $e->dumpError();
            }
            evo()->manager->remove_locks('27');
            break;
        case 72:
        case 4:
            if (!evo()->hasPermission('new_document')) {
                $e->setError(3);
                $e->dumpError();
            } elseif(isset($_REQUEST['pid']) && $_REQUEST['pid'] != '0') {
                // check user has permissions for parent
                $targetpid = empty($_REQUEST['pid']) ? 0 : $_REQUEST['pid'];
                if (!evo()->checkPermissions($targetpid)) {
                    $e->setError(3);
                    $e->dumpError();
                }
            }
            break;
        case 132:
        case 131:
            if (!evo()->hasPermission('view_document')) {
                $e->setError(3);
                $e->dumpError();
            }
            break;
        default:
            $e->setError(3);
            $e->dumpError();
    }

    if (evo()->manager->action == 27 && !evo()->checkPermissions($id)) {
        $_ = array();
        $_[] = '<br /><br />';
        $_[] = '<div class="section">';
        $_[] = sprintf('<div class="sectionHeader">%s</div>',lang('access_permissions'));
        $_[] = '<div class="sectionBody">';
        $_[] = sprintf('	<p>%s</p>',lang('access_permission_denied'));
        $_[] = '</div>';
        $_[] = '</div>';
        echo join("\n",$_);
        include(MODX_MANAGER_PATH . 'actions/footer.inc.php');
        exit;
    }
}

function checkDocLock($id) {
    global $e;
    $rs = db()->select(
        'internalKey, username'
        , '[+prefix+]active_users'
        , sprintf(
            "acstion='%s' AND id='%s'"
            , evo()->manager->action
            , $id
        )
    );
    if (db()->getRecordCount($rs) <= 1) {
        return;
    }
    while ($row = db()->getRow($rs)) {
        if ($row['internalKey'] == evo()->getLoginUserID()) {
            continue;
        }
        $msg = sprintf(lang('lock_msg'), $row['username'], lang('resource'));
        $e->setError(5, $msg);
        $e->dumpError();
    }
}

// get document groups for current user
function getDocgrp() {
    if (isset($_SESSION['mgrDocgroups'])||!empty($_SESSION['mgrDocgroups'])) {
        return implode(',', $_SESSION['mgrDocgroups']);
    }
    else return '';
}

function getValuesFromDB($id,$docgrp) {
    global $e;

    if($id==='0') return array();

    $access  = "1='{$_SESSION['mgrRole']}' OR sc.privatemgr=0";
    $access .= empty($docgrp) ? '' : " OR dg.document_group IN ({$docgrp})";
    $from = "[+prefix+]site_content AS sc LEFT JOIN [+prefix+]document_groups AS dg ON dg.document=sc.id";
    $rs = db()->select('DISTINCT sc.*', $from, "sc.id='{$id}' AND ({$access})");
    $limit = db()->getRecordCount($rs);
    if ($limit > 1)
    {
        $e->setError(6);
        $e->dumpError();
    }
    if ($limit < 1)
    {
        $e->setError(3);
        $e->dumpError();
    }
    return db()->getRow($rs);
}

// restore saved form
function mergeReloadValues($docObject) {
    if (evo()->manager->hasFormValues())
        $restore_v = evo()->manager->loadFormValues();

    if ($restore_v != false)
    {
        $docObject = array_merge($docObject, $restore_v);
        if(isset($restore_v['ta'])) $docObject['content'] = $restore_v['ta'];
    }

    if (!isset($docObject['pub_date'])||empty($docObject['pub_date']))
        $docObject['pub_date'] = '';
    else
        $docObject['pub_date'] = evo()->toTimeStamp($docObject['pub_date']);

    if (!isset($docObject['unpub_date'])||empty($docObject['unpub_date']))
        $docObject['unpub_date'] = '';
    else
        $docObject['unpub_date'] = evo()->toTimeStamp($docObject['unpub_date']);

    if(isset ($_POST['which_editor'])) $docObject['which_editor'] = $_POST['which_editor'];

    return $docObject;
}

function checkViewUnpubDocPerm($published,$editedby) {
    if(evo()->manager->action!=27) return;
    if(evo()->hasPermission('view_unpublished')) return;
    if($published!=='0')                         return;

    $userid = evo()->getLoginUserID();
    if ($userid != $editedby) {
        evo()->config['remember_last_tab'] = 0;
        evo()->event->setError(3);
        evo()->event->dumpError();
    }
}

// increase menu index if this is a new document
function getMenuIndexAtNew() {
    if (evo()->config['auto_menuindex']==1) {
        return db()->getValue(
                db()->select(
                    'count(id)'
                    , '[+prefix+]site_content'
                    , sprintf("parent='%s'", evo()->input_any('pid', 0))
                )
            ) + 1;
    }
    return '0';
}

function getAliasAtNew() {
    if(evo()->config['automatic_alias'] === '2') {
        return evo()->manager->get_alias_num_in_folder(
            0
            , evo()->input_any('pid',0)
        );
    }
    return '';
}

function getJScripts($docid) {
    $ph = array();
    $browser_url = MODX_BASE_URL . 'manager/media/browser/mcpuk/browser.php';
    $ph['imanager_url'] = evo()->conf_var('imanager_url', $browser_url . '?Type=images');
    $ph['fmanager_url'] = evo()->conf_var('fmanager_url', $browser_url . '?Type=files');
    $ph['preview_url']  = evo()->makeUrl($docid,'','','full',true);
    $ph['preview_mode'] = evo()->config['preview_mode'] ? evo()->config['preview_mode'] : '0';
    $ph['lang_confirm_delete_resource'] = lang('confirm_delete_resource');
    $ph['lang_confirm_delete_draft_resource'] = lang('confirm_delete_draft_resource');
    $ph['lang_confirm_undelete'] = lang('confirm_undelete');
    $ph['id'] = $docid;
    $ph['docParent']   = doc('parent');
    $ph['docIsFolder'] = doc('isfolder');
    $ph['docMode'] = evo()->doc->mode;
    $ph['lang_mutate_content.dynamic.php1'] = lang('mutate_content.dynamic.php1');
    $ph['style_tree_folder'] = style('tree_folder');
    $ph['style_icons_set_parent'] = style('icons_set_parent');
    $ph['style_tree_folder'] = style('tree_folder');
    $ph['lang_confirm_resource_duplicate'] = lang('confirm_resource_duplicate');
    $ph['lang_illegal_parent_self'] = lang('illegal_parent_self');
    $ph['lang_illegal_parent_child'] = lang('illegal_parent_child');
    $ph['action'] = evo()->manager->action;
    $ph['suffix'] = evo()->config['friendly_url_suffix'];

    return parseText(
        file_get_contents(MODX_MANAGER_PATH . 'media/style/common/jscripts.tpl')
        , $ph
    );
}

function get_template_options() {
    global $modx;

    $rs = $modx->db->select(
        sprintf(
            "t.templatename, t.id, IFNULL(c.category,'%s') AS category"
            , lang('no_category')
        )
        , '[+prefix+]site_templates t LEFT JOIN [+prefix+]categories c ON t.category=c.id'
        , ''
        , 'c.category, t.templatename ASC'
    );

    while ($row = $modx->db->getRow($rs)) {
        $rows[$row['category']][] = $row;
    }
    $option_tags = function($templates) {
        $options = array(
            html_tag(
                '<option>'
                , array('value'=>0)
                , '(blank)'
            )
        );
        foreach($templates as $template) {
            $options[] = html_tag(
                '<option>'
                ,array(
                    'value'    => $template['id'],
                    'selected' => $template['id']==doc('template') ? null : ''
                )
                , hsc($template['templatename'])
            );
        }
        return implode("\n", $options);
    };
    foreach ($rows as $category=>$templates) {
        $optgroups[] = html_tag(
            '<optgroup>'
            , array('label'=>hsc($category))
            , $option_tags($templates)
        );
    }
    return implode("\n", $optgroups);
}

function menuindex() {
    global $docObject;

    $tpl = <<< EOT
<table cellpadding="0" cellspacing="0" style="width:333px;">
	<tr>
		<td style="white-space:nowrap;">
			[+menuindex+]
			<input type="button" value="&lt;" onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+'')-1;elm.value=v>0? v:0;elm.focus();" />
			<input type="button" value="&gt;" onclick="var elm = document.mutate.menuindex;var v=parseInt(elm.value+'')+1;elm.value=v>0? v:0;elm.focus();" />
			[+resource_opt_menu_index_help+]
		</td>
		<td style="text-align:right;">
			<span class="warning">[+resource_opt_show_menu+]</span>&nbsp;
			[+hidemenu+]
			[+hidemenu_hidden+]
			[+resource_opt_show_menu_help+]
		</td>
	</tr>
</table>
EOT;
    $ph = array();
    $ph['menuindex'] = input_text_tag(
        array(
            'name'      => 'menuindex',
            'value'     => doc('menuindex'),
            'style'     => 'width:62px;',
            'maxlength' => 8
        )
    );
    $ph['resource_opt_menu_index_help'] = tooltip(lang('resource_opt_menu_index_help'));
    $ph['resource_opt_show_menu'] = lang('resource_opt_show_menu');
    $cond = ($docObject['hidemenu']!=1);
    $ph['hidemenu'] = input_checkbox('hidemenu',$cond);
    $ph['hidemenu_hidden'] = input_hidden('hidemenu',!$cond);
    $ph['resource_opt_show_menu_help'] = tooltip(lang('resource_opt_show_menu_help'));
    return parseText($tpl, $ph);
}

function renderSplit() {
    return <<< EOT
<tr>
	<td colspan="2"><div class="split"></div></td>
</tr>
EOT;
}

function getParentName(&$v_parent) {
    global $e;

    $parentlookup = false;
    if (isset($_REQUEST['id'])) {
        if ($v_parent != 0) {
            $parentlookup = $v_parent;
        }
    } elseif (isset($_REQUEST['pid'])) {
        if ($_REQUEST['pid'] != 0) {
            $parentlookup = $_REQUEST['pid'];
        }
    } elseif (isset($v_parent)) {
        if ($v_parent != 0) {
            $parentlookup = $v_parent;
        }
    }

    if (preg_match('@^[1-9][0-9]*$@', $parentlookup)) {
        $rs = db()->select('*', '[+prefix+]site_content', sprintf("id='%s'", $parentlookup));
        if (db()->getRecordCount($rs) != 1) {
            $e->setError(8);
            $e->dumpError();
        }
        $parentrs = db()->getRow($rs);
        return $parentrs['pagetitle'];
    }

    return evo()->config['site_name'];
}

function getParentForm($pname) {
    global $docObject,$_style;

    $tpl = <<< EOT
&nbsp;<img alt="tree_folder" name="plock" src="[+icon_tree_folder+]" onclick="enableParentSelection(!allowParentSelection);" style="cursor:pointer;" />
<b><span id="parentName" onclick="enableParentSelection(!allowParentSelection);" style="cursor:pointer;" >
[+pid+] ([+pname+])</span></b>
[+tooltip+]
<input type="hidden" name="parent" value="[+pid+]" />
EOT;
    $ph['pid'] = isset($_REQUEST['pid']) ? $_REQUEST['pid'] : $docObject['parent'];
    $ph['pname'] = $pname;
    $ph['tooltip'] = tooltip(lang('resource_parent_help'));
    $ph['icon_tree_folder'] = $_style['tree_folder'];
    return parseText($tpl,$ph);
}

function getActionButtons($id) {
    global $modx, $docObject;

    $tpl = <<< EOT
<div id="actions">
	<ul class="actionButtons">
		[+saveButton+]
		[+moveButton+]
		[+duplicateButton+]
		[+deleteButton+]
		[+draftButton+]
		[+previewButton+]
		[+cancelButton+]
	</ul>
</div>
EOT;
    switch(evo()->manager->action)
    {
        case '4':
        case '72':
            if(evo()->hasPermission('new_document'))
                $ph['saveButton'] = ab_save();
            break;
        case '27':
            if(evo()->hasPermission('save_document'))
                $ph['saveButton'] = ab_save();
            break;
        case '132':
        case '131':
            $ph['saveButton'] = ab_save();
            break;
        default:
            $ph['saveButton'] = '';
    }

    $ph['moveButton']      = '';
    $ph['duplicateButton'] = '';
    $ph['deleteButton']    = '';
    if(evo()->doc->mode==='draft') {
        if(evo()->revision->hasDraft||evo()->revision->hasStandby) {
            $ph['deleteButton'] = ab_delete_draft();
        }
    }
    elseif ($id != evo()->config['site_start']) {
        if(evo()->manager->action==27 && evo()->doc->canSaveDoc()) {
            if(evo()->hasPermission('move_document')) {
                $ph['moveButton'] = ab_move();
            }
            if(evo()->doc->canCreateDoc()) {
                $ph['duplicateButton'] = ab_duplicate();
            }
            if(evo()->doc->canDeleteDoc()) {
                if ($docObject['deleted'] == 0) {
                    $ph['deleteButton'] = ab_delete();
                } else {
                    $ph['deleteButton'] = ab_undelete();
                }
            }
        }
    }

    if (evo()->manager->action == 27) {
        if(evo()->revision->hasDraft||evo()->revision->hasStandby) {
            $ph['draftButton'] = ab_open_draft($id);
        } else {
            $ph['draftButton'] = ab_create_draft($id);
        }

    } else {
        $ph['draftButton'] = '';
    }

    $ph['previewButton'] = ab_preview($id);
    $ph['cancelButton']  = ab_cancel($id);

    return preg_replace('@\[\+[^\]]+\+\]@', '', parseText($tpl,$ph));
}

function config($key) {
    global $config;
    return $config[$key];
}

function doc($key) {
    if(str_contains($key,'|hsc')) {
        return hsc(
            evo()->array_get(
                evo()->documentObject
                , str_replace('|hsc','',$key)
            )
        );
    }
    return evo()->array_get(evo()->documentObject, $key);
}

function lang($key) {
    global $_lang;
    return $_lang[$key];
}

function style($key) {
    global $_style;
    return $_style[$key];
}

if (!function_exists('str_contains')) {
    function str_contains($str,$needle) {
        return strpos($str,$needle)!==false;
    }
}

function hsc($string) {
    return evo()->hsc($string);
}

function parseText($tpl,$ph) {
    foreach($ph as $k=>$v) {
        $k = "[+{$k}+]";
        $tpl = str_replace($k,$v,$tpl);
    }
    return $tpl;
}

function parseLang($tpl) {
    global $_lang;
    foreach($_lang as $k=>$v) {
        $k = "[%{$k}%]";
        $tpl = str_replace($k,$v,$tpl);
    }
    return $tpl;
}

function evo() {
    global $modx;
    return $modx;
}

function db() {
    return evo()->db;
}

function html_tag($tag_name, $attrib=array(), $content=null) {return evo()->html_tag($tag_name, $attrib, $content);
}

function input_text_tag($props=array()) {
    $props['type'] = 'text';
    $props['maxlength'] = evo()->array_get($props,'maxlength',255);
    $props['class']     = evo()->array_get($props,'class','inputBox');
    foreach($props as $k=>$v) {
        if($v===false) {
            unset($props[$k]);
        }
    }
    return html_tag('input', $props);

}

function textarea_tag($props=array(), $content) {
    $props['class'] = evo()->array_get($props,'class','inputBox');
    return html_tag('textarea', $props, $content);
}

function select_tag($props=array(), $options) {
    $props['class'] = evo()->array_get($props,'class','inputBox');
    if(is_array($options)) {
        $options = implode("\n", $options);
    }
    return html_tag('select', $props, $options);
}

function textarea($props=array(), $content) {
    $props['class'] = evo()->array_get($props,'class','inputBox');
    return html_tag('textarea', $props, $content);

}

function img_tag($src,$props=array()) {
    $props['src'] = $src;
    return html_tag('img', $props);
}

function a_tag($href,$props=array(),$string) {
    $props['href'] = $href;
    return html_tag('img', $props, $string);
}
