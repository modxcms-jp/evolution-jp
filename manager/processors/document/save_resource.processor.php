<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

if (!evo()->hasPermission('save_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

// 保存の画面（新規・更新）以外から来た値は止める。id は、新規なら無くてよいが、送られたなら正の整数で、更新では必須
$postedId = postv('id');
$hasPostedId = $postedId !== null && $postedId !== '' && $postedId !== '0';
if (postedMode() === '' || ($hasPostedId && !post_intvar('id')) || (postedMode() === 'edit' && !post_intvar('id'))) {
    alert()->setError(2);
    alert()->dumpError();
}

// 保存の画面で選んだ「保存後」を、次に文書の画面を開いたときの既定として覚える
$afterSave = EvoFormFlow::postedAfterSave();
EvoFormFlow::rememberDocumentAfterSave($afterSave);

include_once(MODX_BASE_PATH . 'manager/actions/document/mutate_content/functions.php');
evo()->loadExtension('DocAPI');
// global $form_v;
// $form_v = validated();

$id = validated('id');

checkDocPermission(validated('id'));

checkParent();
checkAllowedParents(validated('id'));
checkDocumentGroupsOpenable(validated('id'));

if (postedMode() === 'new') {
    // invoke OnBeforeDocFormSave event
    $param = [
        'mode' => 'new',
        'doc_vars' => getInputValues(evo()->doc->getNewDocID()),
        'tv_vars' => validated('template') ? get_tmplvars() : []
    ];
    evo()->invokeEvent('OnBeforeDocFormSave', $param);
    $param['doc_vars'] = refreshNewDocId($param['doc_vars']);
    $parentBeforeEvent = (int)validated('parent');
    $param = syncParentAfterEvent($param);
    if ((int)validated('parent') !== $parentBeforeEvent) {
        // イベントで親が変わった（ddAutoFolders など）。保存される親で付くグループをもう一度確かめる
        checkDocumentGroupsOpenable($id);
    }

    $newid = db()->insert(
        db()->escape($param['doc_vars']),
        '[+prefix+]site_content'
    );
    if (!$newid) {
        $msg = 'An error occured while attempting to save the new document: ' . db()->getLastError();
        evo()->manager->saveFormValues(postedScreen());
        evo()->webAlertAndQuit($msg, formReturnUrl());
    }
    convertDataUriAfterInsert($param['doc_vars'], $newid);

    if (!empty($param['tv_vars'])) {
        evo()->doc->saveTmplvars($newid, $param['tv_vars']);
    }

    setDocPermissionsNew($newid);
    updateParentStatus(validated('parent'));

    if (evo()->config('use_udperms')) {
        evo()->manager->setWebDocsAsPrivate($newid);
        evo()->manager->setMgrDocsAsPrivate($newid);
    }

    if (validated('syncsite')) {
        evo()->clearCache();
    }

    // invoke OnDocFormSave event
    validated('*id', $newid);
    evo()->event->vars = ['mode' => 'new', 'id' => $newid];
    evo()->invokeEvent('OnDocFormSave', evo()->event->vars);

    goNextAction($newid, validated('parent'), $afterSave, validated('type'));
    return;
}

if (postedMode() === 'edit') {
    checkProtectedPage(validated('id'), validated('published'), validated('pub_date'), validated('unpub_date'));
    validated('*isfolder', checkFolderStatus(validated('id')));

    $db_v = getExistsValues(validated('id'));
    // set publishedon and publishedby
    validated('*published', checkPublished($db_v));
    validated('*unpub_date', checkUnpub_date($db_v));
    validated('*publishedon', checkPublishedon($db_v['publishedon']));
    validated('*publishedby', checkPublishedby($db_v));
    validated('*pub_date', checkPub_date($db_v));
    $len = strlen(evo()->conf_var('friendly_url_suffix'));
    if (substr(validated('alias'), -$len) === evo()->conf_var('friendly_url_suffix')) {
        validated('*alias', substr(validated('alias'), 0, -$len));
    }
    // invoke OnBeforeDocFormSave event
    $param = [
        'mode' => 'upd',
        'id' => validated('id'),
        'doc_vars' => getInputValues(validated('id'), 'edit'),
        'tv_vars' => validated('template') ? get_tmplvars() : []
    ];
    evo()->invokeEvent('OnBeforeDocFormSave', $param);
    $param = syncParentAfterEvent($param);
    $rs = db()->update(
        db()->escape($param['doc_vars']),
        '[+prefix+]site_content',
        sprintf("id='%s'", validated('id'))
    );
    if (!$rs) {
        finishDataUriFiles(false);
        evo()->manager->saveFormValues(postedScreen());
        evo()->webAlertAndQuit(
            sprintf(
                "An error occured while attempting to save the edited document. The generated SQL is: <i> %s </i>.",
                db()->lastQuery()
            ),
            formReturnUrl()
        );
    }
    finishDataUriFiles(true);

    if ($param['tv_vars']) {
        evo()->doc->saveTmplvars(validated('id'), $param['tv_vars']);
    }

    setDocPermissionsEdit(validated('id'));
    updateParentStatus(validated('parent'));

    if ($db_v['parent'] != 0) {
        folder2doc($db_v['parent']);
    }

    if (evo()->config('use_udperms') == 1) {
        evo()->manager->setWebDocsAsPrivate(validated('id'));
        evo()->manager->setMgrDocsAsPrivate(validated('id'));
    }

    if (validated('syncsite')) {
        if (validated('published') != $db_v['published'] || validated('alias') != $db_v['alias']) {
            evo()->clearCache(['target' => 'sitecache']);
        } elseif (validated('parent') != $db_v['parent']) {
            evo()->clearCache(['target' => 'sitecache']);
        } else {
            evo()->clearCache(['target' => 'pagecache']);
        }
    }

    // invoke OnDocFormSave event
    evo()->event->vars = ['mode' => 'upd', 'id' => validated('id')];
    evo()->invokeEvent('OnDocFormSave', evo()->event->vars);
    goNextAction(validated('id'), validated('parent'), $afterSave, validated('type'));
    return;
}

header('Location: index.php?a=7');


// OnBeforeDocFormSave のプラグインが変えた親を、保存後の処理にも反映する
function syncParentAfterEvent($param)
{
    if (!isset($param['doc_vars']['parent'])) {
        return $param;
    }
    $parent = (int)$param['doc_vars']['parent'];
    if ($parent === (int)validated('parent')) {
        return $param;
    }
    validated('*parent', $parent);
    if (renumberAliasAfterParentChange($param)) {
        $param['doc_vars']['alias'] = evo()->manager->get_alias_num_in_folder(
            array_get($param['doc_vars'], 'id'),
            $parent
        );
        validated('*alias', $param['doc_vars']['alias']);
        return $param;
    }
    recheckAliasAfterParentChange($param, $parent);
    return $param;
}

// 別名が空で、フォルダ内の連番（automatic_alias=2）で振られていた場合は、新しい親で振り直す対象
// プラグインが別名を変えていたら、その別名を連番で上書きしない
function renumberAliasAfterParentChange($param)
{
    return evo()->config('friendly_urls')
        && (int)evo()->config('automatic_alias') === 2
        && (string) array_get($param['doc_vars'], 'alias', '') === (string) validated('alias')
        && trim(post_strvar('alias')) === '';
}

// 親が変わったあとの別名の重複を確かめ直す（get_alias() の重複確認と同じ条件）
// use_alias_path が無効なら重複の確認は親に依らないので、確かめ直さない
function recheckAliasAfterParentChange($param, $parent)
{
    $alias = (string)array_get($param['doc_vars'], 'alias', '');
    if ($alias === '' || !evo()->config('friendly_urls') || !evo()->config('use_alias_path')) {
        return;
    }
    if (evo()->config('allow_duplicate_alias')) {
        return;
    }
    _check_duplicate_alias((int)validated('id'), $alias, (string)$parent);
}

// OnBeforeDocFormSave のプラグインが同じ保存の中で文書を作ると、事前に決めた ID がぶつかる
function refreshNewDocId($doc_vars)
{
    if (empty($doc_vars['id'])) {
        return $doc_vars;
    }
    $rs = db()->select('id', '[+prefix+]site_content', sprintf('id=%d', (int)$doc_vars['id']), '', 1);
    if (!db()->count($rs)) {
        return $doc_vars;
    }
    $doc_vars['id'] = evo()->doc->getNewDocID();
    return $doc_vars;
}

function get_tmplvars()
{
    $tmplvars = [];
    foreach (evo()->doc->templateTmplvars(validated('template')) as $row) {
        $tvid = 'tv' . $row['id'];

        if (validated($tvid) === null && !in_array($row['type'], DocAPI::MULTI_VALUE_TV_TYPES, true)) {
            continue;
        }

        if ($row['type'] === 'url') {
            $value = normalize_url_tv_value(validated($tvid), validated($tvid . '_prefix', '--'));
        } else {
            $value = validated($tvid);
        }
        // 既定値と同じか空なら false（保存せず、あれば行を消す）
        $tmplvars[$row['id']] = evo()->doc->tmplvarValueForSave($row, $value);
    }
    return $tmplvars;
}

function get_alias($id, $alias, $parent, $pagetitle)
{
    if ($alias) {
        $alias = evo()->stripAlias($alias);
    }
    // friendly url alias checks
    if (!evo()->config('friendly_urls')) {
        return $alias;
    }

    if (!$parent) {
        $parent = '0';
    }
    if ($alias && !evo()->config('allow_duplicate_alias')) {
        return _check_duplicate_alias($id, $alias, $parent);
    }

    if ($alias || !evo()->config('automatic_alias')) {
        return $alias;
    }
    $i = evo()->config('automatic_alias');
    if ($i == 1) {
        return evo()->manager->get_alias_from_title($id, $pagetitle);
    }
    if ($i == 2) {
        return evo()->manager->get_alias_num_in_folder($id, $parent);
    }
    return $alias;
}

function _check_duplicate_alias($id, $alias, $parent)
{
    if (evo()->config('use_alias_path')) {
        $docid = db()->getValue(
            'id',
            '[+prefix+]site_content',
            sprintf(
                "id!='%s' AND alias='%s' AND parent=%s LIMIT 1",
                (int) $id,
                db()->escape($alias),
                (int) $parent
            )
        );
        if ($docid < 1) {
            $docid = db()->getValue(
                'id',
                '[+prefix+]site_content',
                sprintf(
                    "id='%s' AND alias='' AND parent='%s'",
                    db()->escape($alias),
                    (int) $parent
                )
            );
        }
    } else {
        $rs = db()->select(
            'id',
            '[+prefix+]site_content',
            sprintf(
                "id!='%s' AND alias='%s' LIMIT 1",
                (int) $id,
                db()->escape($alias)
            )
        );
        $docid = db()->getValue($rs);
        if ($docid < 1) {
            $docid = db()->getValue(
                db()->select(
                    'id',
                    '[+prefix+]site_content',
                    sprintf("id='%s' AND alias=''", db()->escape($alias))
                )
            );
        }
    }

    if (!$docid) {
        return $alias;
    }
    evo()->manager->saveFormValues(postedScreen());
    $url = formReturnUrl();

    // 戻った画面の選択欄の既定は直前の保存の選択（セッション）とは限らないので、close も含めて引き継ぐ
    $url .= '&after_save=' . EvoFormFlow::postedAfterSave();

    evo()->webAlertAndQuit(
        sprintf(
            lang('duplicate_alias_found'),
            $docid,
            $alias
        ),
        $url
    );
    return $alias;
}

// 自分自身・子孫・存在しないリソースを親にしていないか
function checkParent()
{
    $message = manager()->invalidParentMessage(
        postedMode() === 'edit' ? [validated('id')] : [],
        validated('parent')
    );
    if ($message === '') {
        return;
    }
    // 戻った編集画面で入力内容を復元できるよう、戻り先のアクションで保存しておく
    evo()->manager->saveFormValues(postedScreen());
    $url = formReturnUrl();
    evo()->webAlertAndQuit($message, $url);
}

function checkDocPermission($id)
{
    // get the document, but only if it already exists
    if (postedMode() === 'edit') {
        $rs = db()->select('parent', '[+prefix+]site_content', "id='{$id}'");
        $total = db()->count($rs);
        if ($total > 1) {
            alert()->setError(6);
            alert()->dumpError();
        } elseif ($total < 1) {
            alert()->setError(7);
            alert()->dumpError();
        }
        if (evo()->config('use_udperms') != 1) {
            return;
        }
        $existingDocument = db()->getRow($rs);

        // 保存しようとしている文書そのものにアクセスできるか
        if (!evo()->checkPermissions($id)) {
            evo()->webAlertAndQuit(lang('access_permission_denied'), 'index.php?a=2');
        }

        // check to see if the user is allowed to save the document in the place he wants to save it in
        if ($existingDocument['parent'] == validated('parent')) {
            return;
        }

        if (!evo()->checkPermissions(validated('parent'))) {
            evo()->manager->saveFormValues(postedScreen());
            evo()->webAlertAndQuit(
                sprintf(lang('access_permission_parent_denied'), $id, validated('alias')),
                formReturnUrl()
            );
        }
    } elseif (validated('parent') == 0 && !manager()->isAllowroot()) {
        alert()->setError(3);
        alert()->dumpError();
    } elseif (!evo()->hasPermission('new_document')) {
        alert()->setError(3);
        alert()->dumpError();
    } elseif (evo()->config('use_udperms') == 1 && !evo()->checkPermissions(validated('parent'))) {
        // 作成先の親にアクセスできるか
        alert()->setError(3);
        alert()->dumpError();
    }
}

// 保存後の文書グループで、自分が管理画面からその文書を開けなくなるときは止める
// 新規は保存される親（validated('parent')）から付くグループで確かめる
function checkDocumentGroupsOpenable($id)
{
    $afterGroups = postedMode() === 'edit'
        ? manager()->updatedDocumentGroupIds($id)
        : manager()->newDocumentGroupIds(validated('parent'));
    if (manager()->canOpenWithDocumentGroups($afterGroups)) {
        return;
    }
    evo()->manager->saveFormValues(postedScreen());
    evo()->webAlertAndQuit(lang('resource_permissions_error'), formReturnUrl());
}

// 許可するリソースの範囲の外の文書の更新と、範囲の外の親への作成・移動を止める
function checkAllowedParents($id)
{
    if (postedMode() === 'new') {
        if (!manager()->isInAllowedParents(validated('parent'))) {
            alert()->setError(3, lang('access_permission_parent_denied'));
            alert()->dumpError();
        }
        return;
    }

    if (!manager()->isInAllowedParents($id)) {
        evo()->webAlertAndQuit(lang('access_permission_denied'), 'index.php?a=2');
    }

    $currentParent = (int)db()->getValue(
        db()->select('parent', '[+prefix+]site_content', sprintf('id=%d', (int)$id))
    );
    if ($currentParent === (int)validated('parent')) {
        return;
    }
    if (!manager()->isInAllowedParents(validated('parent'))) {
        evo()->manager->saveFormValues(postedScreen());
        evo()->webAlertAndQuit(lang('access_permission_parent_denied'), formReturnUrl());
    }
}

function shouldConvertDataUri($fields)
{
    return $fields['type'] !== 'reference'
        && !empty($fields['content'])
        && evo()->getConfig('convert_datauri_to_file', 1);
}

// 新規の文書は、INSERT で ID が決まってから本文の Data URI をファイルにして、本文を書き直す
function convertDataUriAfterInsert($doc_vars, $newId)
{
    if (!shouldConvertDataUri($doc_vars)) {
        return;
    }
    $converted = convertDataUriToFiles($doc_vars['content'], $newId);
    if ($converted === $doc_vars['content']) {
        finishDataUriFiles(false);
        return;
    }
    $updated = db()->update(
        db()->escape(['content' => $converted]),
        '[+prefix+]site_content',
        sprintf('id=%d', (int)$newId)
    );
    finishDataUriFiles((bool)$updated);
}

function getInputValues($id = 0, $mode = 'new')
{
    $db_v_names = explode(
        ',',
        'content,pagetitle,longtitle,type,description,alias,link_attributes,isfolder,richtext,published,pub_date,unpub_date,parent,template,menuindex,searchable,cacheable,editedby,editedon,publishedon,publishedby,contentType,content_dispo,donthit,menutitle,hidemenu,introtext,createdby,createdon'
    );
    if ($id) {
        $fields['id'] = $id;
    }
    foreach ($db_v_names as $key) {
        if (validated($key) === null) {
            validated('*' . $key, '');
        }
        $fields[$key] = validated($key);
    }

    // 本文に貼り付けた画像（img の src の Data URI）をファイルに書き出す。新規の文書は ID が決まる INSERT のあとに行う
    if ($mode === 'edit' && shouldConvertDataUri($fields)) {
        $fields['content'] = convertDataUriToFiles($fields['content'], $id);
    }

    if ($fields['type'] === 'reference') {
        if (!empty($fields['content']) && !preg_match('{^[1-9][0-9]+$}', $fields['content'])) {
            $fetch_id = evo()->getIdFromUrl($fields['content']);
            if ($fetch_id) {
                $fields['content'] = $fetch_id;
            } else {
                $fields['content'] = strip_tags($fields['content']);
            }
        }
    }

    $fields['editedby'] = evo()->getLoginUserID();
    if ($mode === 'new') {
        $fields['createdon'] = request_time();
        $fields['createdby'] = evo()->getLoginUserID();
        $fields['publishedon'] = checkPublishedon(0);
    } elseif ($mode === 'edit') {
        unset($fields['createdby']);
        unset($fields['createdon']);
    }
    return $fields;
}

// サイトの設定で使っているページ（トップページなど）は、非公開にも公開日・公開終了日の設定にもできない
function checkProtectedPage($id, $published, $pub_date, $unpub_date)
{
    $error = evo()->doc->protectedPubError($id, $published, $pub_date, $unpub_date);
    if ($error === '') {
        return;
    }
    evo()->manager->saveFormValues(postedScreen());
    evo()->webAlertAndQuit($error, formReturnUrl());
    exit;
}

function checkFolderStatus($id)
{
    $isfolder = validated('isfolder');
    // check to see document is a folder
    $rs = db()->select('COUNT(id) AS count', '[+prefix+]site_content', "parent='{$id}'");
    if ($rs) {
        $row = db()->getRow($rs);
        if ($row['count'] > 0) {
            $isfolder = '1';
        }
    } else {
        evo()->webAlertAndQuit("An error occured while attempting to find the document's children.");
    }
    return $isfolder;
}

// keep original publish state, if change is not permitted
function getPublishPermission($field_name, $db_v)
{
    if (!evo()->hasPermission('publish_document')) {
        return $db_v[$field_name];
    }
    return validated($field_name);
}

function checkPublished($db_v)
{
    return getPublishPermission('published', $db_v);
}

function checkPub_date($db_v)
{
    if (!evo()->hasPermission('publish_document')) {
        return $db_v['pub_date'];
    }
    if (!evo()->config('auto_pub_date')) {
        return validated('pub_date');
    }
    if (validated('pub_date')) {
        return validated('pub_date');
    }
    if (!validated('published')) {
        return 0;
    }
    return validated('publishedon');
}

function checkUnpub_date($db_v)
{
    return getPublishPermission('unpub_date', $db_v);
}

function checkPublishedon($timestamp)
{
    if (!evo()->hasPermission('publish_document')) {
        return $timestamp;
    }

    if (validated('published') && validated('pub_date') && validated('pub_date') <= request_time()) {
        return validated('pub_date');
    }

    if (0 < $timestamp && validated('published')) {
        return $timestamp;
    }

    if (!validated('published')) {
        return 0;
    }

    return request_time();
}

function checkPublishedby($db_v)
{
    if (!evo()->hasPermission('publish_document')) {
        return $db_v['publishedby'];
    }

    if (validated('published') && validated('pub_date') && validated('pub_date') <= request_time()) {
        return $db_v['publishedby'];
    }

    if (0 < $db_v['publishedon'] && validated('published')) {
        return $db_v['publishedby'];
    }

    if (!validated('published')) {
        return 0;
    }

    return evo()->getLoginUserID();
}

function getExistsValues($id)
{
    $row = db()->getRow(
        db()->select('*', '[+prefix+]site_content', sprintf("id='%s'", $id))
    );
    if (!$row) {
        evo()->webAlertAndQuit(
            "An error occured while attempting to find the document's current parent.",
            EvoFormFlow::url(['a' => EvoFormFlow::screen('document', 'edit'), 'id' => $id])
        );
    }
    return $row;
}

// document access permissions
// 規則は ManagerAPI の setNewDocumentGroups()（変えてよいグループは送られた値、よくないグループは親から引き継ぐ）
function setDocPermissionsNew($newid)
{
    manager()->setNewDocumentGroups($newid, (int)validated('parent'));
}

// update parent folder status
function updateParentStatus($parent)
{
    if (!$parent) {
        return;
    }

    $rs = db()->update(
        'isfolder=1',
        '[+prefix+]site_content',
        sprintf("id='%s'", $parent)
    );
    if ($rs) {
        return;
    }
    evo()->webAlertAndQuit(
        "An error occured while attempting to change the document's parent to a folder."
    );
}

// 保存後の行き先（after_save: new・stay・close）に従って移動する
function goNextAction($id, $parent, $afterSave, $type)
{
    if ($afterSave === 'new') {
        $kind = $type === 'document' ? 'document' : 'weblink';
        header('Location: ' . EvoFormFlow::url([
            'a' => EvoFormFlow::screen($kind, 'new'),
            'pid' => $parent,
            'refresh' => 'tree',
            'after_save' => 'new',
        ]));
        return;
    }
    if ($afterSave === 'stay') {
        header('Location: ' . EvoFormFlow::url([
            'a' => EvoFormFlow::screen('document', 'edit'),
            'id' => $id,
            'refresh' => 'tree',
            'after_save' => 'stay',
        ]));
        return;
    }
    if ($parent) {
        header(
            sprintf('Location: index.php?a=120&id=%s&refresh=tree', $parent)
        );
        return;
    }
    header("Location: index.php?a=3&id=" . $id . "&refresh=tree");
}

function setDocPermissionsEdit($id)
{
    if (evo()->config('use_udperms') != 1) {
        return;
    }
    // 更新後のグループとの差で足す・外す（変えてよくないグループは残る）
    $exists_groups = manager()->currentDocumentGroupIds($id);
    $after_groups = manager()->updatedDocumentGroupIds($id, null, $exists_groups);
    $insertions = [];
    foreach (array_diff($after_groups, $exists_groups) as $group_id) {
        $insertions[] = sprintf('(%d,%d)', $group_id, (int)$id);
    }
    $exists_groups = array_diff($exists_groups, $after_groups);
    $saved = true;
    if ($insertions) {
        $sql_insert = sprintf(
            'INSERT INTO %s (document_group, document) VALUES %s',
            evo()->getFullTableName('document_groups'),
            implode(',', $insertions)
        );
        $rs = db()->query($sql_insert);
        if (!$rs) {
            $saved = false;
        }
    }
    if ($exists_groups) {
        $rs = db()->delete(
            '[+prefix+]document_groups',
            sprintf(
                'document=%d AND document_group IN (%s)',
                (int)$id,
                implode(',', $exists_groups)
            )
        );
        if (!$rs) {
            $saved = false;
        }
    }
    if ($saved) {
        return;
    }
    evo()->webAlertAndQuit('An error occured while saving document groups.');
}

function folder2doc($parent)
{
    $rs = db()->select(
        'COUNT(id) as total',
        '[+prefix+]site_content',
        "parent=" . $parent
    );
    if (!$rs) {
        echo "An error occured while attempting to find the old parents' children.";
    }
    $row = db()->getRow($rs);
    if ($row['total']) {
        return;
    }
    $rs = db()->update(
        'isfolder = 0',
        '[+prefix+]site_content',
        sprintf("id='%s'", $parent)
    );
    if ($rs) {
        return;
    }
    echo 'An error occured while attempting to change the old parent to a regular document.';
}

// 保存の画面の mode（new: 新規、edit: 更新）。それ以外は空文字
function postedMode()
{
    return EvoFormFlow::postedMode('document');
}

// 保存の画面の番号（4: 文書の新規、72: ウェブリンクの新規、27: 更新）。mode が無効なら 0。
// 新規は送られた種類（type）で決める。設定タブで種類を変えたあとの差し戻しも、その種類の画面へ戻る
function postedScreen()
{
    $mode = postedMode();
    if ($mode === '') {
        return 0;
    }
    return EvoFormFlow::screen(post_strvar('type') === 'reference' ? 'weblink' : 'document', $mode);
}

// 入力エラーで保存の画面（postedScreen()）へ戻す URL。入力は saveFormValues(postedScreen()) で差し戻す
function formReturnUrl()
{
    return evo()->manager->formReturnUrl(postedScreen(), post_intvar('id'), post_intvar('template'));
}

function validated($key = null, $default = null)
{
    static $form_v = null;

    if (!$form_v) {
        evo()->loadExtension('DocAPI');
        $form_v = evo()->doc->setValue(
            evo()->doc->initValue(
                evo()->doc->fixTvNest(postv())
            ),
            postedScreen()
        );
    }

    if (strpos($key, '*') === 0) {
        $form_v[substr($key, 1)] = $default;
        return;
    }

    if ($key) {
        return array_get($form_v, $key, $default);
    }

    return $form_v;
}
