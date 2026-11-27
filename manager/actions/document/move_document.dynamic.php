<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('move_document') || !evo()->hasPermission('save_document') || !evo()->hasPermission('publish_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

// 移動するリソース。1 件なら id、複数なら batch。1 つでも整数として読めなければ止める
$ids = [];
if (anyv('id')) {
    $ids[] = request_intvar('id');
} elseif (is_array(anyv('batch'))) {
    foreach (anyv('batch') as $v) {
        $ids[] = to_intvar($v);
    }
}
if (!$ids || in_array(0, $ids, true)) {
    alert()->setError(2);
    alert()->dumpError();
}
$id = implode(',', $ids);

// check permissions on the documents
foreach ($ids as $v) {
    if (!$modx->checkPermissions($v)) {
        show_perm_error();
        exit;
    }
}

echo get_src_js();
$parent = get_parentid($id);
echo get_src_content($id, $parent);


function get_src_content($id, $parent)
{
    global $_lang;
    include_once MODX_CORE_PATH . 'controls/child_document_list_state.class.php';
    $redirect = hsc($parent ? ChildDocumentListState::listUrl((int) $parent) : 'index.php?a=2');
    $pickerLabel = hsc(lang('parent_picker_open'));
    // 言語ファイルの文字列は JS の alert() 向けに \n を含むため、改行へ戻してから渡す
    $newParentLabel = hsc(lang('new_parent'));
    $illegalParentSelf = hsc(stripcslashes(lang('illegal_parent_self')));
    $illegalParentChild = hsc(stripcslashes(lang('illegal_parent_child')));
    $src = <<< EOT
<h1>{$_lang['move_resource_title']}</h1>
<div id="actions">
	<ul class="actionButtons">
	  <li><a href="#" data-move-action="save" class="primary">{$_lang['save']}</a></li>
	  <li class="mutate"><a href="#" data-move-action="cancel" data-move-url="{$redirect}">{$_lang['cancel']}</a></li>
	</ul>
</div>

<div class="section">
<div class="sectionBody">
<p>{$_lang['move_resource_message']}</p>
<form method="post" action="index.php" data-move-form data-shell-tree="full" data-label-new-parent="{$newParentLabel}" data-illegal-parent-self="{$illegalParentSelf}" data-illegal-parent-child="{$illegalParentChild}">
<input type="hidden" name="a" value="52">
<input type="hidden" name="id" value="{$id}">
<p>{$_lang['resource_to_be_moved']}: <b>{$id}</b></p>
<p><span id="parentName" class="warning">{$_lang['move_resource_new_parent']}</span>
<button type="button" class="button" data-move-action="picker">{$pickerLabel}</button></p>
<input type="hidden" name="new_parent" value="" class="inputBox">
<br />
</form>
</div>
</div>
EOT;
    return $src;
}


function get_src_js()
{
    return sprintf(
        '<script type="text/javascript" src="%s"></script>' . "\n" . '<script type="text/javascript">EvoMoveDocument.init();</script>' . "\n",
        hsc(manager_asset('media/script/move-document.js'))
    );
}

function show_perm_error()
{
    echo sprintf(
        '<br /><br /><div class="section"><div class="sectionHeader">%s</div><div class="sectionBody"><p>%s</p></div></div>',
        lang('access_permissions'),
        lang('access_permission_denied')
    );
    include(MODX_MANAGER_PATH . 'actions/footer.inc.php');
}

function get_parentid($id)
{
    if (strpos($id, ',') !== false) {
        $id = substr($id, 0, strpos($id, ','));
    }
    return db()->getValue(
        db()->select(
            'parent',
            '[+prefix+]site_content',
            sprintf("id='%s'", $id)
        )
    );
}
