<?php
function getTmplvars($docid, $template_id, $docgrp)
{
    // SQL に渡す直前に整数にそろえる（差し戻しの値が入ることがある）
    $docid = (int)$docid;
    $template_id = (int)$template_id;

    if (!$template_id) {
        return [];
    }

    $tmplVars = [];

    $rs = db()->select(
        [
            'DISTINCT tv.*',
            'tvtpl.rank',
            'value'
            => $docid ?
                "tvtpl.rank, IF(tvc.value!='',tvc.value,tv.default_text)"
                :
                'tv.default_text',
            'tvtpl.rank'
        ],
        [
            '[+prefix+]site_tmplvars AS tv',
            'INNER JOIN [+prefix+]site_tmplvar_templates AS tvtpl ON tvtpl.tmplvarid=tv.id',
            $docid ?
                sprintf(
                    "LEFT JOIN [+prefix+]site_tmplvar_contentvalues AS tvc ON tvc.tmplvarid=tv.id AND tvc.contentid=%d",
                    $docid
                )
                :
                '',
            'LEFT JOIN [+prefix+]site_tmplvar_access AS tva ON tva.tmplvarid=tv.id'
        ],
        sprintf(
            "tvtpl.templateid=%d AND (1='%s' OR ISNULL(tva.documentgroup) %s)",
            $template_id,
            manager()->isAdmin() ? 1 : 0,
            $docgrp ? sprintf(' OR tva.documentgroup IN (%s)', $docgrp) : ''
        ),
        'tvtpl.rank,tv.rank, tv.id'
    );

    if (!db()->count($rs)) {
        return [];
    }
    while ($row = db()->getRow($rs)) {
        $tmplVars['tv' . $row['id']] = $row;
    }
    return $tmplVars;
}

function rteContent($htmlcontent, $editors)
{
    return textarea_tag(
        [
            'id' => 'ta',
            'name' => 'ta',
            'style' => 'width:100%;height:350px;'
        ],
        $htmlcontent
    )
        . html_tag('<span>', ['class' => 'mutate-field-title'], lang('which_editor_title'))
        . getEditors($editors);
}

// 選ばれているエディタ。差し戻しの値を優先する
function currentEditor()
{
    $restored = reloadFormValues() ?: [];
    return to_strvar(array_get($restored, 'which_editor')) ?: post_strvar('which_editor', config('which_editor'));
}

function getEditors($editors)
{
    if (!is_array($editors)) {
        return '';
    }

    if (!$editors) {
        return '';
    }

    $options = [
        html_tag('<option>', ['value' => 'none'], lang('none'))
    ];
    foreach ($editors as $editor) {
        $options[] = html_tag(
            '<option>',
            [
                'value' => $editor,
                'selected' => currentEditor() === $editor ? null : ''
            ],
            $editor
        );
    }
    return select_tag(
        [
            'id' => 'which_editor',
            'name' => 'which_editor'
        ],
        implode("\n", $options)
    );
}

function tpl_base_dir()
{
    return str_replace('\\', '/', __DIR__) . '/';
}

function sectionContent()
{
    if (doc('type') !== 'document') {
        return '';
    }
    $ph['header'] = lang('resource_content');
    $planetpl = function ($content) {
        return sprintf(
            '<textarea class="phptextarea" id="ta" name="ta" style="width:100%%; height: 400px;">%s</textarea>',
            $content
        );
    };
    if (config('use_editor') && doc('richtext')) {
        $editors = evo()->invokeEvent('OnRichTextEditorRegister');
        if ($editors) {
            $ph['body'] = rteContent(hsc_for_tpl(doc('content')), $editors);
        } else {
            $ph['body'] = $planetpl(hsc_for_tpl(doc('content')));
        }
    } else {
        $ph['body'] = $planetpl(hsc_for_tpl(doc('content')));
    }

    return parseText(file_get_tpl('section_content.tpl'), $ph);
}

/**
 * TV をメインタブの本文の下（#tv_body）に出すか。0 なら TV のタブ（#tabTVs）に出す（グローバル設定の tvs_below_content）
 */
function tvsBelowContent()
{
    return (string) config('tvs_below_content') === '1';
}

function sectionTV($tpl, $fields)
{
    $ph = [];
    $ph['header'] = lang('settings_templvars');
    $ph['body'] = $fields;
    return parseText($tpl, $ph);
}

function rte_fields()
{
    static $rte_fields = null;
    if ($rte_fields !== null) {
        return $rte_fields;
    }
    $rte_fields = [];
    if (config('use_editor') == 1 && doc('richtext') == 1) {
        $rte_fields[] = 'ta';
    }
    $tmplVars = getTmplvars(request_intvar('id'), doc('template'), getDocgrp());
    foreach ($tmplVars as $tv) {
        // Go through and display all Template Variables
        if ($tv['type'] === 'richtext' || $tv['type'] === 'htmlarea') {
            $rte_fields[] = 'tv' . $tv['id'];
        }
    }
    return $rte_fields;
}

function getGroups($docid)
{
    // Load up, the permissions from the parent (if new document) or existing document
    $rs = db()->select(
        'id, document_group',
        '[+prefix+]document_groups',
        sprintf('document=%d', (int)$docid)
    );
    $groups = [];
    while ($row = db()->getRow($rs)) {
        $groups[] = sprintf('%s,%s', $row['document_group'], $row['id']);
    }
    return $groups;
}

function getUDGroups($id)
{
    global $permissions_yes, $permissions_no;

    if (manager()->action == 27 && $id) {
        $docid = $id;
    } elseif (request_intvar('pid')) {
        $docid = request_intvar('pid');
    } else {
        $docid = (int)doc('parent');
    }
    $docid = (int)$docid;

    // Setup Basic attributes for each Input box
    $inputAttributes['type'] = 'checkbox';
    $inputAttributes['class'] = 'checkbox';
    $inputAttributes['name'] = 'document_groups[]';
    $inputAttributes['onclick'] = 'makePublic(false)';

    $permissions = []; // New Permissions array list (this contains the HTML)
    $access = manager()->documentGroupAccess();
    $permissions_yes = $access['yes']; // 出すグループのうち、所属している数
    $permissions_no = $access['no']; // 出すグループのうち、所属していない数
    $notPublic = false; // true if the document belongs to any of the listed groups

    // Query the permissions and names from above
    if ($docid) {
        $rs = db()->select(
            'dgn.*, `groups`.id AS link_id',
            [
                '[+prefix+]documentgroup_names AS dgn',
                sprintf(
                    'LEFT JOIN [+prefix+]document_groups AS `groups` ON `groups`.document_group=dgn.id AND `groups`.document=%d',
                    $docid
                )
            ],
            '',
            'name'
        );
    } else {
        $rs = db()->select(
            '*, NULL AS link_id',
            '[+prefix+]documentgroup_names',
            '',
            'name'
        );
    }
    // retain selected doc groups between post（配列でないときは空として扱う）
    $restored = reloadFormValues();
    $postedDocgroups = is_array(postv('document_groups')) ? postv('document_groups') : [];
    if ($restored !== null && !postv()) {
        // GET で差し戻されたときは、差し戻しの選択だけにする（外したグループも外れたまま）
        $docgroups = array_get($restored, 'document_groups', []);
        $restoredGroupIds = [];
        foreach (is_array($docgroups) ? $docgroups : [] as $value) {
            $restoredGroupIds[] = manager()->postedDocumentGroupId($value);
        }
        $groupsarray = [];
    } elseif ($docid) {
        $groupsarray = array_merge(getGroups($docid), $postedDocgroups);
    } else {
        $groupsarray = $postedDocgroups;
    }
    // Loop through the permissions list
    while ($row = db()->getRow($rs)) {
        // 変えてよいグループだけを出す
        if (!isset($access['ids'][(int)$row['id']])) {
            continue;
        }

        // Create an inputValue pair (group ID and group link (if it exists))
        $inputValue = sprintf(
            '%s,%s',
            $row['id'],
            $row['link_id'] ? $row['link_id'] : 'new'
        );

        // 差し戻しは、リンクの id が表示と合わないことがあるので、グループの id で照合する
        $checked = isset($restoredGroupIds)
            ? in_array((int)$row['id'], $restoredGroupIds, true)
            : in_array($inputValue, $groupsarray);
        if ($checked) {
            $notPublic = true;
            $inputAttributes['checked'] = 'checked';
        } else {
            unset($inputAttributes['checked']);
        }

        // Setup attributes for this Input box
        $inputAttributes['id'] = 'group-' . $row['id'];
        $inputAttributes['value'] = $inputValue;

        // Create attribute string list
        $inputString = [];
        foreach ($inputAttributes as $k => $v) {
            $inputString[] = sprintf('%s="%s"', $k, $v);
        }

        $permissions[] = "\t\t"
            . html_tag(
                '<li>',
                [],
                sprintf("<input %s />\n", implode(' ', $inputString))
                    . html_tag(
                        'label',
                        ['for' => 'group-' . $row['id']],
                        hsc($row['name'])
                    )
            );
    }

    if (!$permissions) {
        // 出すグループはあるが、どれにも所属していないときは、タブを出さない（空の配列）
        return $permissions_no && !$access['ids'] ? [] : false;
    }

    // Add the "All Document Groups" item if we have rights in both contexts
    if (hasPermission('access_permissions') && hasPermission('web_access_permissions')) {
        array_unshift(
            $permissions,
            html_tag(
                '<li>',
                [],
                html_tag(
                    '<input>',
                    [
                        'type' => 'checkbox',
                        'class' => 'checkbox',
                        'name' => 'chkalldocs',
                        'id' => 'groupall',
                        'checked' => !$notPublic ? null : '',
                        'onclick' => 'makePublic(true);'
                    ]
                )
                    . html_tag(
                        'label',
                        [
                            'for' => 'groupall',
                            'class' => 'mutate-field-title clickable-label'
                        ],
                        lang('all_doc_groups')
                    )
            )
        );
        // Output the permissions list...
    }

    return $permissions;
}

function mergeDraft($content, $draft)
{
    if (!hasPermission('publish_document')) {
        $draft['published'] = '0';
    }
    foreach ($content as $k => $v) {
        if (!is_array($v)) {
            continue;
        }
        $tvid = 'tv' . $v['id'];
        if (isset($draft[$tvid])) {
            $content[$k]['value'] = $draft[$tvid];
            unset($draft[$tvid]);
        } else {
            $content[$k]['value'] = null;
        }
    }
    $content = array_merge($content, $draft);
    return $content;
}

function tooltip($msg)
{
    return img_tag(
        style('icons_tooltip'),
        [
            'alt' => $msg,
            'title' => $msg,
            'onclick' => 'alert(this.alt);',
            'class' => 'tooltip',
            'tabindex' => '0'
        ]
    );
}

function get_alias_path($id)
{
    if (config('use_alias_path') === '0') {
        return MODX_BASE_URL;
    }

    // 新規で URL に pid が無いときは、差し戻しの親のパスを出す
    $pid = request_intvar('pid') ?: (!$id ? (int)doc('parent') : 0);

    if (!$id && !$pid) {
        return MODX_BASE_URL;
    }
    if (!$pid) {
        $path = evo()->getAliasListing($id, 'path');
    } else {
        if (evo()->getAliasListing($pid, 'path')) {
            $path = evo()->getAliasListing($pid, 'path') . '/' . evo()->getAliasListing($pid, 'alias');
        } else {
            $path = evo()->getAliasListing($pid, 'alias');
        }
    }

    if ($path === '') {
        $path = MODX_BASE_URL;
    } else {
        $path = MODX_BASE_URL . $path . '/';
    }

    if (30 < strlen($path)) {
        $path .= '<br />';
    }
    return $path;
}

/**
 * 日付の欄を空にするボタン。押したときの動きは datepicker.js（data-clear-date で欄の id を受け取る）
 */
function clearDateButton($target, $disabled = false)
{
    return html_tag(
        '<button>',
        [
            'type' => 'button',
            'class' => 'icon-button',
            'data-clear-date' => $target,
            'title' => hsc(lang('remove_date')),
            'disabled' => $disabled ? null : ''
        ],
        img_tag(style('icons_cal_nodate'), ['alt' => hsc(lang('remove_date'))])
    );
}

/**
 * リソース編集の欄の行。$body（欄と、その後ろのボタン・説明のアイコン）は .field-inline で横に並べ、
 * $note（書式などの説明）は欄の下に .form-text で出す。
 * $field は行の data-field 属性に出す名前（assets/docs/jquery-migration.md の「編集画面の行と data-field 属性」）。
 * フィールドではない行は空にする
 */
function renderFieldTr($field, $head, $body, $rowstyle = '', $note = '')
{
    $heads = (array) $head;
    if (is_array($body)) {
        $body = implode("\n", $body);
    }

    return EvoFieldRow::render([
        'field' => $field,
        'label' => array_shift($heads),
        'label_extra' => implode("\n", $heads),
        'body' => '<div class="field-inline">' . $body . '</div>',
        'help' => $note,
        'class' => 'mutate-field-row',
        'attr' => $rowstyle !== '' ? ['style' => $rowstyle] : [],
    ]);
}

if (!function_exists('getDefaultTemplate')) {
    function getDefaultTemplate()
    {
        static $default_template = null;
        if ($default_template !== null) {
            return $default_template;
        }

        $default_template = config('default_template');

        if (!request_intvar('pid')) {
            return $default_template;
        }

        if (config('auto_template_logic') === 'sibling') {
            $rs = db()->select(
                'template',
                '[+prefix+]site_content',
                sprintf(
                    'id!=%d AND isfolder=0 AND parent=%d',
                    (int)config('site_start'),
                    request_intvar('pid')
                ),
                'published DESC,menuindex ASC',
                1
            );
        } elseif (config('auto_template_logic') === 'parent') {
            $rs = db()->select(
                'template',
                '[+prefix+]site_content',
                sprintf('id=%d', request_intvar('pid'))
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
}

// check permissions
function checkPermissions($id)
{
    global $modx;

    $isAllowed = manager()->isAllowed($id);
    // 新規作成（4・72）で pid を指定したときは、ここでは止めない
    // （後段で、作成先の親の文書グループのアクセス権だけを確かめる）
    $isEditAction = in_array(manager()->action, [27, 131, 132]);
    if (!$isAllowed && ($isEditAction || get_strvar('pid') === '')) {
        alert()->setError(3);
        alert()->dumpError();
    }

    $i = manager()->action;
    if ($isEditAction) {
        // 編集・下書きの画面は文書の ID が必須
        abort_if_invalid_id('id', true);
    }
    if ($i == 27) {
        if (!hasPermission('view_document')) {
            $modx->config['remember_last_tab'] = 0;
            alert()->setError(3);
            alert()->dumpError();
        }
        manager()->remove_locks('27');
        if (!evo()->checkPermissions($id)) {
            alert()->setError(3, lang('access_permission_denied'));
            alert()->dumpError();
        }
    } elseif ($i == 72 || $i == 4) {
        if (!hasPermission('new_document')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        if (request_intvar('pid')) {
            if (!evo()->checkPermissions(request_intvar('pid'))) {
                alert()->setError(3);
                alert()->dumpError();
            }
        }
    } elseif ($i == 132 || $i == 131) {
        if (!hasPermission('view_document')) {
            alert()->setError(3);
            alert()->dumpError();
        }
        if (!evo()->checkPermissions($id)) {
            alert()->setError(3, lang('access_permission_denied'));
            alert()->dumpError();
        }
    } else {
        alert()->setError(3);
        alert()->dumpError();
    }
}

function checkDocLock($id)
{
    $username = manager()->editLockedBy((int) manager()->action, (int) $id);
    if ($username !== '') {
        alert()->setError(5, sprintf(lang('lock_msg'), $username, lang('resource')));
        alert()->dumpError();
    }
}

// get document groups for current user
function getDocgrp()
{
    if (sessionv('mgrDocgroups')) {
        return implode(',', sessionv('mgrDocgroups'));
    } else {
        return '';
    }
}

function db_value($id, $docgrp)
{
    if ($id === '0') {
        return [];
    }

    $rs = db()->select(
        'DISTINCT sc.*',
        '[+prefix+]site_content AS sc LEFT JOIN [+prefix+]document_groups AS dg ON dg.document=sc.id',
        sprintf(
            "sc.id='%s' %s",
            $id,
            (manager()->isAdmin() || !$docgrp) ? '' : sprintf(
                'AND (sc.privatemgr=0 OR dg.document_group IN (%s))',
                $docgrp
            )
        )
    );
    $limit = db()->count($rs);
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

if (!function_exists('default_value')) {
    function default_value($parent_id, $new_template_id)
    {
        return [
            'menuindex' => getMenuIndexAtNew($parent_id),
            'alias' => getAliasAtNew(),
            'richtext' => config('use_editor'),
            'published' => config('publish_default'),
            'contentType' => 'text/html',
            'content_dispo' => '0',
            'which_editor' => config('which_editor'),
            'searchable' => config('search_default'),
            'cacheable' => config('cache_default'),
            'type' => manager()->action == 72 ? 'reference' : 'document',
            'parent' => $parent_id,
            'template' => $new_template_id ? $new_template_id : getDefaultTemplate(),
            'pagetitle' => '',
            'longtitle' => '',
            'menutitle' => '',
            'description' => '',
            'introtext' => '',
            'link_attributes' => '',
            'pub_date' => '',
            'unpub_date' => '',
            'isfolder' => 0,
            'content' => ''
        ];
    }
}

// mergeReloadValues() で重ねた差し戻しの入力。無ければ null（TV・ドキュメントグループの描画が読む）
function reloadFormValues($values = false)
{
    static $restored = null;
    if ($values !== false) {
        $restored = $values;
    }
    return $restored;
}

// restore saved form
// POST での開き直しと、保存の処理が入力エラーで GET に戻したときの、どちらも自分の画面のキーの差し戻しを読む
function mergeReloadValues($docObject)
{
    if (!manager()->hasFormValues()) {
        return $docObject;
    }
    $populate = manager()->loadFormValues();
    // 別のリソース（新規か id が違う）向けの入力は混ぜない
    if (!$populate || (int)array_get($populate, 'id', 0) !== request_intvar('id')) {
        return $docObject;
    }
    // GET で戻った新規で URL に pid があり、差し戻しの親と違えば、別の新規作成の画面なので重ねない
    // （差し戻しの戻り先の URL には pid を付けない）
    if (!postv() && !request_intvar('id') && request_intvar('pid')
        && request_intvar('pid') !== (int)array_get($populate, 'parent', 0)) {
        return $docObject;
    }
    reloadFormValues($populate);
    $docObject = array_merge($docObject, $populate);
    if (array_key_exists('ta', $populate)) {
        $docObject['content'] = $populate['ta'];
    }
    if (array_get($populate, 'which_editor')) {
        $docObject['which_editor'] = $populate['which_editor'];
    }

    foreach (['pub_date', 'unpub_date'] as $dateField) {
        // 解釈できない差し戻しの入力は、入力した文字列のまま残す
        $docObject[$dateField] = array_get($docObject, $dateField)
            ? manager()->restoredDateValue($docObject[$dateField])
            : '';
    }
    return $docObject;
}

function checkViewUnpubDocPerm($published, $editedby)
{
    if (manager()->action != 27 || hasPermission('view_unpublished') || $published) {
        return;
    }

    if (evo()->getLoginUserID() != $editedby) {
        global $modx;
        $modx->config['remember_last_tab'] = 0;
        alert()->setError(3);
        alert()->dumpError();
    }
}

// increase menu index if this is a new document
function getMenuIndexAtNew($parent_id)
{
    if (config('auto_menuindex') == 1) {
        return db()->getValue(
            db()->select(
                'count(id)',
                '[+prefix+]site_content',
                sprintf('parent=%d', (int)$parent_id)
            )
        ) + 1;
    }
    return '0';
}

function getAliasAtNew()
{
    if (config('automatic_alias') === '2') {
        return manager()->get_alias_num_in_folder(
            0,
            request_intvar('pid')
        );
    }
    return '';
}

function getJScripts($docid)
{
    $ph = [];
    $browser_url = MODX_BASE_URL . 'manager/media/browser/evo/browser.php';
    $ph['imanager_url'] = js_literal(config('imanager_url', $browser_url . '?type=images'));
    $ph['fmanager_url'] = js_literal(config('fmanager_url', $browser_url . '?type=files'));
    $ph['preview_url'] = js_literal(evo()->makeUrl($docid, '', '', 'full', true));
    $ph['preview_mode'] = (int)config('preview_mode', 1);
    // confirm()・alert() の文言。言語ファイルの文字列は JS 向けに \n を含むため、改行へ戻してから JS のリテラルにする
    $langKeys = [
        'confirm_delete_draft_resource',
        'confirm_reset_pub_dates',
        'illegal_parent_self',
        'illegal_parent_child',
        'error_occurred',
    ];
    foreach ($langKeys as $key) {
        $ph['lang_' . $key] = js_literal(stripcslashes(lang($key)));
    }
    $ph['browser_js_ver'] = asset_version(MODX_MANAGER_PATH . 'media/browser/browser.js');
    $ph['id'] = $docid;
    $ph['docParent'] = (int) doc('parent');
    $ph['docIsFolder'] = (int) doc('isfolder');
    // 削除の確認に「サブリソースも…」の行を出すかは、isfolder ではなく実際の子の有無で決める
    evo()->loadExtension('DocAPI');
    $ph['docHasChildren'] = $docid && evo()->doc->hasLiveChildren($docid) ? 'true' : 'false';
    $ph['docMode'] = evo()->doc->mode;
    $ph['style_tree_folder'] = style('tree_folder');
    $ph['style_icons_set_parent'] = style('icons_set_parent');
    $ph['action'] = manager()->action;
    $ph['suffix'] = js_literal(config('friendly_url_suffix'));

    return parseText(
        file_get_contents(MODX_MANAGER_PATH . 'media/style/common/jscripts.tpl'),
        $ph
    );
}


function renderSplit()
{
    return <<< EOT
<tr>
	<td colspan="2"><div class="split"></div></td>
</tr>
EOT;
}

function file_get_tpl($path)
{
    $dir = (string)config('custom_tpl_dir');
    if ($dir !== '' && is_file(MODX_BASE_PATH . $dir . $path)) {
        return file_get_contents(MODX_BASE_PATH . $dir . $path);
    }
    return file_get_contents(tpl_base_dir() . $path);
}

function collect_template_ph($id, $OnDocFormPrerender, $OnDocFormRender, $OnRichTextEditorInit)
{
    return [
        'JScripts' => getJScripts($id),
        'OnDocFormPrerender' => is_array($OnDocFormPrerender) ? implode("\n", $OnDocFormPrerender) : '',
        'id' => $id,
        // 空は上限なし（MAX_FILE_SIZE=0。ファイルピッカー・ファイルマネージャーと同じ）
        'upload_maxsize' => max(0, (int) size_to_bytes(config('upload_maxsize'))),
        'mode' => EvoFormFlow::mode('document') ?: EvoFormFlow::mode('weblink'),
        'a' => (evo()->doc->mode === 'normal' && hasPermission('save_document')) ? 5 : 128,
        'pid' => request_intvar('pid'),
        'title' => (evo()->doc->mode === 'normal') ? lang('create_resource_title') : lang('create_draft_title'),
        'class' => (evo()->doc->mode === 'normal') ? '' : 'draft',
        '(ID:%s)' => $id ? sprintf('(ID:%s)', $id) : '',
        'page_title_attr' => ($id && doc('pagetitle') !== '') ? ' data-page-title="' . hsc_for_tpl(doc('pagetitle')) . '"' : '',
        'actionButtons' => getActionButtons($id),
        'OnDocFormRender' => is_array($OnDocFormRender) ? implode("\n", $OnDocFormRender) : '',
        'OnRichTextEditorInit' => $OnRichTextEditorInit,
        'remember_last_tab' => EvoFormFlow::rememberTab() ? 'true' : 'false'
    ];
}

if (!function_exists('collect_tab_general_ph')) {
    function collect_tab_general_ph($docid)
    {
        return [
            '_lang_settings_general' => lang('settings_general'),
            '_lang_group_basic' => lang('mutate_group_basic'),
            '_lang_group_menu' => lang('mutate_group_menu'),
            'fieldPagetitle' => fieldPagetitle(),
            'fieldLongtitle' => fieldLongtitle(),
            'fieldDescription' => fieldDescription(),
            'fieldAlias' => fieldAlias($docid),
            'fieldWeblink' => doc('type') === 'reference' ? fieldWeblink() : '',
            'fieldIntrotext' => fieldIntrotext(),
            'fieldTemplate' => fieldTemplate(),
            'fieldMenutitle' => fieldMenutitle(),
            'fieldMenuindex' => fieldMenuindex(),
            'renderSplit' => renderSplit(),
            'fieldParent' => fieldParent(),
            'sectionContent' => sectionContent(),
            'sectionTV' => tvsBelowContent()
                ? sectionTV(file_get_tpl('section_tv.tpl'), fieldsTV()) : ''
        ];
    }
}

function collect_tab_tv_ph()
{
    return [
        'TVFields' => fieldsTV(),
        '_lang_tv' => lang('tmplvars')
    ];
}

if (!function_exists('collect_tab_settings_ph')) {
    function collect_tab_settings_ph($docid)
    {
        $ph = [];
        $ph['_lang_settings_page_settings'] = lang('settings_page_settings');
        $ph['_lang_group_publish'] = lang('mutate_group_publish');
        $ph['_lang_group_type'] = lang('mutate_group_type');
        $ph['_lang_group_behavior'] = lang('mutate_group_behavior');
        $ph['fieldPublished'] = evo()->doc->mode === 'normal' ? fieldPublished() : '';
        $ph['fieldPub_date'] = fieldPub_date($docid);
        $ph['fieldUnpub_date'] = fieldUnpub_date($docid);

        $ph['renderSplit1'] = $ph['fieldPub_date'] ? renderSplit() : '';
        $ph['renderSplit2'] = renderSplit();

        $ph['fieldType'] = fieldType();
        $ph['fieldContentType'] = (doc('type') === 'reference') ? html_tag(
            '<input>',
            [
                'type' => 'hidden',
                'name' => 'contentType',
                'value' => hsc(doc('contentType'))
            ]
        ) : fieldContentType();
        $ph['fieldContent_dispo'] = (doc('type') === 'reference') ? html_tag(
            '<input>',
            [
                'type' => 'hidden',
                'name' => 'content_dispo',
                'value' => hsc(doc('content_dispo'))
            ]
        ) : fieldContent_dispo();
        $ph['fieldLink_attributes'] = fieldLink_attributes();
        $ph['fieldIsfolder'] = fieldIsfolder();
        $ph['fieldRichtext'] = fieldRichtext();
        $ph['fieldDonthit'] = config('track_visitors') ? fieldDonthit() : '';
        $ph['fieldSearchable'] = fieldSearchable();
        $ph['fieldCacheable'] = doc('type') === 'document' ? fieldCacheable() : '';
        $ph['fieldSyncsite'] = fieldSyncsite();
        return $ph;
    }
}

/**
 * 保存の処理が書いた data: 画像のファイルを持つストア。DB の更新が済むまで、管理操作ログに書かず、消せるように持っておく
 * （finishDataUriFiles() で確定する。確定しないまま処理が終わったら、書いたファイルを消す）。
 * images の範囲が作れないとき（images のフォルダの実体が rb_base_dir の外にあるなど）は、ストアでなく EvoDataUriStore::create() のエラー（['code', ...]）を返す
 *
 * @return EvoDataUriStore|array|null $create が false で、まだ作っていないときは null
 */
function dataUriSaveStore($create = true)
{
    static $store = null;
    if ($store === null && $create) {
        require_once MODX_CORE_PATH . 'datauri_store.class.inc.php';
        $store = EvoDataUriStore::create(evo());
        if ($store instanceof EvoDataUriStore) {
            register_shutdown_function(function () use ($store) {
                $store->discardAll();
            });
        }
    }

    return $store;
}

/**
 * convertDataUriToFiles() が書いたファイルを確定する。DB の更新が済んだら true（管理操作ログに書く）、
 * 失敗したら false（書いたファイルを消す）
 */
function finishDataUriFiles($committed)
{
    $store = dataUriSaveStore(false);
    if (!($store instanceof EvoDataUriStore)) {
        return;
    }
    $committed ? $store->record() : $store->discardAll();
}

/**
 * コンテンツ内の img の src の Data URI を、ファイルピッカーの images/年/月/ にファイルとして書き、その URL に置き換える
 *
 * 置き場所・名前・拡張子と大きさの確かめ・同名の連番・OnFileBrowserUpload・管理操作ログはエディターに貼り付けた画像と同じ
 * （EvoDataUriStore）。年月は保存のときのサイトの時刻、名前は文書の ID と連番。書けなかった Data URI は置き換えずに残し、
 * システムログに残す。ロールにファイルのアップロードの権限（file_upload）が無いときは、何も書かずに残す。
 * 書いたファイルは、呼ぶ側が DB の更新のあとに finishDataUriFiles() で確定する
 *
 * @param string $content 処理対象のコンテンツ
 * @param int $docid ドキュメント ID（ファイルの名前に入る。新規の文書は INSERT のあとに呼ぶ）
 * @return string 変換後のコンテンツ
 */
function convertDataUriToFiles($content, $docid)
{
    if (empty($content) || !is_string($content) || strpos($content, 'data:') === false) {
        return $content;
    }

    $store = dataUriSaveStore();
    if (!($store instanceof EvoDataUriStore)) {
        evo()->logEvent(0, 3, sprintf('Data URI not converted: the images folder of the file browser cannot be used (%s) (docid: %s)', $store['code'], $docid), 'convertDataUriToFiles');
        return $content;
    }
    $timestamp = request_time() + (int)evo()->config('server_offset_time', 0);
    $deniedLogged = false;

    return EvoDataUri::replaceImgSrc(
        $content,
        function ($dataUri, $dimensions) use ($store, $timestamp, $docid, &$deniedLogged) {
            if (!$store->allowsUpload()) {
                if (!$deniedLogged) {
                    evo()->logEvent(0, 2, sprintf('Data URI not converted: the role lacks the file_upload permission (docid: %s)', $docid), 'convertDataUriToFiles');
                    $deniedLogged = true;
                }
                return null;
            }
            $prepared = $store->prepare($dataUri, $dimensions, $docid);
            $result = isset($prepared['error']) ? $prepared : $store->place($prepared, $docid, $timestamp);
            if (isset($result['error'])) {
                $message = $result['param'] !== null
                    ? sprintf($result['error'] . ' (docid: %s)', $result['param'], $docid)
                    : sprintf($result['error'] . ' (docid: %s)', $docid);
                evo()->logEvent(0, $result['level'], $message, 'convertDataUriToFiles');
                return null;
            }

            $logMsg = sprintf(
                'Data URI converted: %s -> %s (size: %d bytes)',
                $prepared['mime'],
                $result['path'],
                strlen($prepared['bytes'])
            );
            if ($prepared['resized']) {
                $logMsg .= sprintf(
                    ' [resized, requested %sx%s]',
                    !empty($dimensions['width']) ? $dimensions['width'] : 'auto',
                    !empty($dimensions['height']) ? $dimensions['height'] : 'auto'
                );
            }
            evo()->logEvent(0, 1, $logMsg, 'convertDataUriToFiles');

            return $result['path'];
        }
    );
}
