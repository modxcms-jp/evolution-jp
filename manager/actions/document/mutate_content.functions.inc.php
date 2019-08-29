<?php
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
    if (!is_array($editors)) {
        return '';
    }

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
                'selected' => evo()->input_post('which_editor',config('which_editor')) === $editor ? null : ''
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

function tpl_base_dir() {
    return str_replace('\\', '/', __DIR__) . '/tpl/mutate_content/';
}

function sectionContent() {
    if (doc('type') !== 'document') {
        return '';
    }
    $ph['header'] = lang('resource_content');
    $planetpl = function($content) {
        return sprintf(
            '<textarea class="phptextarea" id="ta" name="ta" style="width:100%%; height: 400px;">%s</textarea>'
            , $content
        );
    };
    if (config('use_editor') && doc('richtext')) {
        $editors = evo()->invokeEvent('OnRichTextEditorRegister');
        if($editors) {
            $ph['body'] = rteContent(doc('content|hsc'), $editors);
        } else {
            $ph['body'] = $planetpl(doc('content|hsc'));
        }
    } else {
        $ph['body'] = $planetpl(doc('content|hsc'));
    }

    return parseText(file_get_tpl('section_content.tpl'),$ph);
}

function sectionTV() {
    $ph = array();
    $ph['header'] = lang('settings_templvars');
    $ph['body'] = fieldsTV();
    return parseText(file_get_tpl('section_tv.tpl'),$ph);
}

function rte_fields() {
    static $rte_fields = null;
    if($rte_fields!==null) {
        return $rte_fields;
    }
    $rte_fields = array();
    if (config('use_editor')== 1 && doc('richtext') == 1) {
        $rte_fields[] = 'ta';
    }
    $tmplVars = getTmplvars(input_any('id'),doc('template'),getDocgrp());
    foreach($tmplVars as $tv) {
        $tvid = 'tv' . $tv['id'];
        // Go through and display all Template Variables
        if ($tv['type'] === 'richtext' || $tv['type'] === 'htmlarea') {
            $rte_fields[] = 'tv' . $tv['id'];
        }
    }
    return $rte_fields;
}

function getInitialValues($parent_id,$new_template_id) {
    return array(
        'menuindex'     => getMenuIndexAtNew($parent_id),
        'alias'         => getAliasAtNew(),
        'richtext'      => config('use_editor'),
        'published'     => config('publish_default'),
        'contentType'   => 'text/html',
        'content_dispo' => '0',
        'which_editor'  => config('which_editor'),
        'searchable'    => config('search_default'),
        'cacheable'     => config('cache_default'),
        'type'          => manager()->action==72 ? 'reference' : 'document',
        'richtext'      => manager()->action==72 ? 0 : 1,
        'parent'        => $parent_id,
        'template'      => $new_template_id ? $new_template_id : getDefaultTemplate()
    );
}

function getGroups($docid) {
    // Load up, the permissions from the parent (if new document) or existing document
    $rs = db()->select(
        'id, document_group'
        ,'[+prefix+]document_groups'
        , sprintf("document='%s'", $docid)
    );
    $groups = array();
    while ($row = db()->getRow($rs)) {
        $groups[] = sprintf('%s,%s', $row['document_group'], $row['id']);
    }
    return $groups;
}

function getUDGroups($id) {
    global $permissions_yes, $permissions_no;

    $form_v = $_POST;
    $groupsarray = array();

    if (manager()->action == 27) {
        $docid = $id;
    } elseif (!empty($_REQUEST['pid'])) {
        $docid = $_REQUEST['pid'];
    } else {
        $docid = doc('parent');
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
        if ((!hasPermission('access_permissions') && $row['private_memgroup'] == '1') || (!hasPermission('web_access_permissions') && $row['private_webgroup'] == '1')) {
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
        $count = db()->getValue(
            db()->select(
                'COUNT(mg.id)'
                , '[+prefix+]membergroup_access mga, [+prefix+]member_groups mg'
                , sprintf(
                    'mga.membergroup=mg.user_group AND mga.documentgroup=%s AND mg.member=%s'
                    , $row['id']
                    , $_SESSION['mgrInternalKey']
                )
            )
        );

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

    // if mgr user doesn't have access to any of the displayable permissions, forget about them and make doc public
    if($_SESSION['mgrRole'] != 1 && !$permissions_yes && $permissions_no) {
        return array();
    }

    if($permissions) {
        // Add the "All Document Groups" item if we have rights in both contexts
        if (hasPermission('access_permissions') && hasPermission('web_access_permissions')) {
            array_unshift(
                $permissions
                , html_tag(
                    '<li>'
                    , array(),
                    html_tag('<input>',array(
                            'type'=> 'checkbox',
                            'class'=> 'checkbox',
                            'name'=> 'chkalldocs',
                            'id'=>'groupall',
                            'checked' => !$notPublic ? null : '',
                            'onclick'=> 'makePublic(true);'
                        )
                    )
                    . html_tag('label', array(
                            'for'=> 'groupall',
                            'class'=> 'warning'
                        )
                        , lang('all_doc_groups')))
            );
            // Output the permissions list...
        }
    }

    return $permissions;
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
    if(!hasPermission('publish_document')) $content['published'] = '0';
    return $content;
}

function tooltip($msg) {
    return html_tag(
        '<img>'
        , array(
            'src'     => style('icons_tooltip_over'),
            'title'   => $msg,
            'alt'     => $msg,
            'onclick' => 'alert(this.alt);',
            'style'   => 'cursor:help;',
            'class'   => 'tooltip'
        )
    );
}

function get_alias_path($id) {
    $pid = (int)$_REQUEST['pid'];

    if (config('use_alias_path')==='0') {
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

    return parseText(file_get_tpl('render_tr.tpl'), $ph);
}

function getDefaultTemplate() {
    static $default_template = null;
    if($default_template!==null) {
        return $default_template;
    }

    $default_template = config('default_template');

    if(!input_any('pid')) {
        return $default_template;
    }

    if (config('auto_template_logic') === 'sibling') {
        $rs = db()->select(
            'template'
            , '[+prefix+]site_content'
            , sprintf("id!='%s' AND isfolder=0 AND parent='%s'", config('site_start'), input_any('pid'))
            , 'published DESC,menuindex ASC'
            , 1
        );
    } elseif (config('auto_template_logic') === 'parent') {
        $rs = db()->select(
            'template'
            , '[+prefix+]site_content'
            , sprintf("id='%s'", input_any('pid'))
        );
    } else {
        $default_template = config('default_template');
        return $default_template;
    }

    $default_template = db()->getValue($rs);
    if (!$default_template) {
        $default_template = config('default_template');
    }
    return $default_template;
}

// check permissions
function checkPermissions($id) {
    global $modx;

    $isAllowed = manager()->isAllowed($id);
    if (!isset($_GET['pid'])&&!$isAllowed) {
        alert()->setError(3);
        alert()->dumpError();
    }

    switch (manager()->action) {
        case 27:
            if (!hasPermission('view_document')) {
                $modx->config['remember_last_tab'] = 0;
                alert()->setError(3);
                alert()->dumpError();
            }
            manager()->remove_locks('27');
            break;
        case 72:
        case 4:
            if (!hasPermission('new_document')) {
                alert()->setError(3);
                alert()->dumpError();
            } elseif(evo()->input_any('pid')) {
                // check user has permissions for parent
                $targetpid = empty($_REQUEST['pid']) ? 0 : $_REQUEST['pid'];
                if (!evo()->checkPermissions($targetpid)) {
                    alert()->setError(3);
                    alert()->dumpError();
                }
            }
            break;
        case 132:
        case 131:
            if (!hasPermission('view_document')) {
                alert()->setError(3);
                alert()->dumpError();
            }
            break;
        default:
            alert()->setError(3);
            alert()->dumpError();
    }

    if (manager()->action == 27 && !evo()->checkPermissions($id)) {
        $_ = array();
        $_[] = '<br /><br />';
        $_[] = '<div class="section">';
        $_[] = sprintf('<div class="sectionHeader">%s</div>',lang('access_permissions'));
        $_[] = '<div class="sectionBody">';
        $_[] = sprintf('	<p>%s</p>',lang('access_permission_denied'));
        $_[] = '</div>';
        $_[] = '</div>';
        echo implode("\n",$_);
        include(MODX_MANAGER_PATH . 'actions/footer.inc.php');
        exit;
    }
}

function checkDocLock($id) {
    $rs = db()->select(
        'internalKey, username'
        , '[+prefix+]active_users'
        , sprintf(
            "acstion='%s' AND id='%s'"
            , manager()->action
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
        alert()->setError(5, $msg);
        alert()->dumpError();
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
    if($id==='0') {
        return array();
    }

    $access  = sprintf("1='%s' OR sc.privatemgr=0", $_SESSION['mgrRole']);
    $access .= !$docgrp ? '' : sprintf(' OR dg.document_group IN (%s)', $docgrp);
    $rs = db()->select(
        'DISTINCT sc.*'
        , '[+prefix+]site_content AS sc LEFT JOIN [+prefix+]document_groups AS dg ON dg.document=sc.id'
        , sprintf("sc.id='%s' AND (%s)", $id, $access)
    );
    $limit = db()->getRecordCount($rs);
    if ($limit > 1) {
        alert()->setError(6);
        alert()->dumpError();
    }
    if ($limit < 1) {
        alert()->setError(3);
        alert()->dumpError();
    }
    return db()->getRow($rs);
}

// restore saved form
function mergeReloadValues($docObject) {
    if (manager()->hasFormValues()) {
        $populate = manager()->loadFormValues();
        if ($populate) {
            $docObject = array_merge($docObject, $populate);
            if(evo()->array_get($populate,'ta')) {
                $docObject['content'] = $populate['ta'];
            }
            if(evo()->array_get($populate,'which_editor')) {
                $docObject['which_editor'] = $populate['which_editor'];
            }
        }
    }

    if (evo()->array_get($docObject, 'pub_date')) {
        $docObject['pub_date'] = evo()->toTimeStamp($docObject['pub_date']);
    } else {
        $docObject['pub_date'] = '';
    }

    if (evo()->array_get($docObject, 'unpub_date')) {
        $docObject['unpub_date'] = evo()->toTimeStamp($docObject['unpub_date']);
    } else {
        $docObject['unpub_date'] = '';
    }
    return $docObject;
}

function checkViewUnpubDocPerm($published,$editedby) {
    if(manager()->action!=27 || hasPermission('view_unpublished') || $published) {
        return;
    }

    if (evo()->getLoginUserID() != $editedby) {
        global $modx;
        $modx->config['remember_last_tab'] = 0;
        evo()->event->setError(3);
        evo()->event->dumpError();
    }
}

// increase menu index if this is a new document
function getMenuIndexAtNew($parent_id) {
    if (config('auto_menuindex')==1) {
        return db()->getValue(
                db()->select(
                    'count(id)'
                    , '[+prefix+]site_content'
                    , sprintf("parent='%s'", $parent_id)
                )
            ) + 1;
    }
    return '0';
}

function getAliasAtNew() {
    if(config('automatic_alias') === '2') {
        return manager()->get_alias_num_in_folder(
            0
            , input_any('pid')
        );
    }
    return '';
}

function getJScripts($docid) {
    $ph = array();
    $browser_url = MODX_BASE_URL . 'manager/media/browser/mcpuk/browser.php';
    $ph['imanager_url'] = config('imanager_url', $browser_url . '?Type=images');
    $ph['fmanager_url'] = config('fmanager_url', $browser_url . '?Type=files');
    $ph['preview_url']  = evo()->makeUrl($docid,'','','full',true);
    $ph['preview_mode'] = config('preview_mode') ? config('preview_mode') : '0';
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
    $ph['action'] = manager()->action;
    $ph['suffix'] = config('friendly_url_suffix');

    return parseText(
        file_get_contents(MODX_MANAGER_PATH . 'media/style/common/jscripts.tpl')
        , $ph
    );
}


function renderSplit() {
    return <<< EOT
<tr>
	<td colspan="2"><div class="split"></div></td>
</tr>
EOT;
}

function doc($key) {
    global $docObject;
    if(str_contains($key,'|hsc')) {
        return hsc(
            evo()->array_get(
                $docObject
                , str_replace('|hsc','',$key)
            )
        );
    }
    return evo()->array_get($docObject, $key);
}


function file_get_tpl ($path) {
    if(is_file(MODX_BASE_PATH . config('custom_tpl_dir') . $path)) {
        return file_get_contents(MODX_BASE_PATH . config('custom_tpl_dir') . $path);
    }
    return file_get_contents(tpl_base_dir() . $path);
}

function collect_template_ph($id, $OnDocFormPrerender, $OnDocFormRender, $OnRichTextEditorInit) {
    return array(
        'JScripts' => getJScripts($id),
        'OnDocFormPrerender' => is_array($OnDocFormPrerender) ? implode("\n", $OnDocFormPrerender) : '',
        'id' => $id,
        'upload_maxsize' => config('upload_maxsize', 3145728),
        'mode' => manager()->action,
        'a' =>  (evo()->doc->mode === 'normal' && hasPermission('save_document')) ? 5 : 128,
        'pid' => input_any('pid'),
        'title' => (evo()->doc->mode==='normal') ? lang('create_resource_title') : lang('create_draft_title'),
        'class' => (evo()->doc->mode==='normal') ? '' : 'draft',
        '(ID:%s)' => $id ? sprintf('(ID:%s)', $id) : '',
        'actionButtons' => getActionButtons($id),
        'token' => manager()->makeToken(),
        'OnDocFormRender'      => is_array($OnDocFormRender) ? implode("\n", $OnDocFormRender) : '',
        'OnRichTextEditorInit' => $OnRichTextEditorInit,
        'remember_last_tab' =>  (config('remember_last_tab') === '2' || evo()->input_get('stay') === '2') ? 'true' : 'false'
    );
}

function collect_tab_general_ph($docid) {
    return array(
        '_lang_settings_general' => lang('settings_general'),
        'fieldPagetitle'   => fieldPagetitle(),
        'fieldLongtitle'   => fieldLongtitle(),
        'fieldDescription' => fieldDescription(),
        'fieldAlias'       => fieldAlias($docid),
        'fieldWeblink'     => doc('type')==='reference' ? fieldWeblink() : '',
        'fieldIntrotext'   => fieldIntrotext(),
        'fieldTemplate'    => fieldTemplate(),
        'fieldMenutitle'   => fieldMenutitle(),
        'fieldMenuindex'   => fieldMenuindex(),
        'renderSplit'      => renderSplit(),
        'fieldParent'      => fieldParent(),

        'sectionContent' =>  sectionContent(),
        'sectionTV'      =>  config('tvs_below_content') ? sectionTV() : ''
    );
}

function collect_tab_tv_ph() {
    return array(
        'TVFields' => fieldsTV(),
        '_lang_tv' => lang('tmplvars')
    );
}

function collect_tab_settings_ph($docid) {
    $ph = array();
    $ph['_lang_settings_page_settings'] = lang('settings_page_settings');
    $ph['fieldPublished'] = (evo()->doc->mode==='normal') ? fieldPublished() : '';
    $ph['fieldPub_date']   = fieldPub_date($docid);
    $ph['fieldUnpub_date'] = fieldUnpub_date($docid);

    $ph['renderSplit1'] = $ph['fieldPub_date'] ? renderSplit() : '';
    $ph['renderSplit2'] = renderSplit();

    $ph['fieldType'] = fieldType();
    $ph['fieldContentType'] = (doc('type') === 'reference') ? html_tag(
        '<input>'
        , array(
            'type' => 'hidden',
            'name' => 'contentType',
            'value' => doc('contentType')
        )
    ) : fieldContentType();
    $ph['fieldContent_dispo'] = (doc('type') === 'reference') ? html_tag(
        '<input>'
        , array(
            'type' => 'hidden',
            'name' => 'content_dispo',
            'value' => doc('content_dispo')
        )
    ) : fieldContent_dispo();
    $ph['fieldLink_attributes'] = fieldLink_attributes();
    $ph['fieldIsfolder']   = fieldIsfolder();
    $ph['fieldRichtext']   = fieldRichtext();
    $ph['fieldDonthit']    = config('track_visitors')==='1' ? fieldDonthit() : '';
    $ph['fieldSearchable'] = fieldSearchable();
    $ph['fieldCacheable']  = doc('type') === 'document' ? fieldCacheable() : '';
    $ph['fieldSyncsite']   = fieldSyncsite();
    return $ph;
}