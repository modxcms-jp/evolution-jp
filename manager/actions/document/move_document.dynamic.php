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
    global $_lang, $_style;
    $redirect = $parent == 0 ? 'index.php?a=2' : "index.php?a=120&amp;id={$parent}";
    $pickerLabel = hsc(lang('parent_picker_open'));
    $src = <<< EOT
<h1>{$_lang['move_resource_title']}</h1>
<div id="actions">
	<ul class="actionButtons">
	  <li><a href="#" onclick="document.newdocumentparent.submit();" class="primary">{$_lang['save']}</a></li>
	  <li class="mutate"><a href="#" onclick="documentDirty=false;document.location.href='{$redirect}'">{$_lang['cancel']}</a></li>
	</ul>
</div>

<div class="section">
<div class="sectionBody">
<p>{$_lang['move_resource_message']}</p>
<form method="post" action="index.php" name='newdocumentparent'>
<input type="hidden" name="a" value="52">
<input type="hidden" name="id" value="{$id}">
<p>{$_lang['resource_to_be_moved']}: <b>{$id}</b></p>
<p><span id="parentName" class="warning">{$_lang['move_resource_new_parent']}</span>
<button type="button" onclick="openParentPicker();">{$pickerLabel}</button></p>
<input type="hidden" name="new_parent" value="" class="inputBox">
<br />
<input type="save" value="Move" style="display:none">
</form>
</div>
</div>
EOT;
    return $src;
}

function batch_move()
{
    $batch = anyv('batch');
    if (!$batch) {
        return;
    }
    foreach ($batch as $v) {
        $ids[] = sprintf("id='%s'", db()->escape($v));
    }
    $where = implode(' OR ', $ids);
    $rs = db()->select('pagetitle', '[+prefix+]site_content', $where);
    while ($row = db()->getRow($rs)) {
        echo hsc($row['pagetitle']) . '<br />';
    }
}

function get_src_js()
{
    $newParentLabel = js_literal(lang('new_parent'));
    // 文言は JS の文字列へそのまま埋め込む前提で改行を \n の 2 文字で持つので、改行に直してから渡す
    $illegalParentSelf = js_literal(str_replace('\n', "\n", lang('illegal_parent_self')));
    $illegalParentChild = js_literal(str_replace('\n', "\n", lang('illegal_parent_child')));
    $src = <<< EOT
<script language="javascript">
top.mainMenu.defaultTreeFrame();
parent.tree.ca = "move";
function setMoveValue(pId, pName) {
    if (pId===0 || checkParentChildRelation(pId, pName)) {
        document.newdocumentparent.new_parent.value=pId;
        const label = document.getElementById('parentName');
        const id = document.createElement('b');
        id.textContent = pId;
        label.textContent = {$newParentLabel} + ': ';
        label.append(id, ' (' + pName + ')');
    }
}

// ツリーで省略されたリソースも移動先にできるよう、タイトルか ID で検索して選ぶ。
// 移動するリソース自身と子孫はダイアログ側で選べないようにしている
function openParentPicker() {
    EvoShell.openPicker(
        'index.php?a=135&id=' + encodeURIComponent(document.newdocumentparent.id.value),
        function (picked) {
            document.newdocumentparent.new_parent.value = picked.id;
            const label = document.getElementById('parentName');
            const id = document.createElement('b');
            id.textContent = picked.id;
            label.textContent = {$newParentLabel} + ': ';
            label.append(id, ' (' + picked.pagetitle + ')');
        }
    );
}

// 選んだ移動先が、移動するリソース（複数移動ではそのどれか）自身か子孫なら止める。
// ツリーのノードは親を独自の属性 p に持つので getAttribute で読む
function checkParentChildRelation(pId, pName) {
    const ids = document.newdocumentparent.id.value.split(',');
    let pn = document.getElementById("node"+pId);
    if (!pn) return false;
    if (ids.includes(pn.id.substr(4))) {
        alert({$illegalParentSelf});
        return false;
    }
    while (pn.getAttribute("p") > 0) {
        pn = document.getElementById("node"+pn.getAttribute("p"));
        if (!pn) break;
        if (ids.includes(pn.id.substr(4))) {
            alert({$illegalParentChild});
            return false;
        }
    }
    return true;
}
</script>

EOT;
    return $src;
}

function show_perm_error()
{
    global $_lang;
    echo sprintf(
        '<br /><br /><div class="section"><div class="sectionHeader">%s</div><div class="sectionBody"><p>%s</p></div></div>',
        $_lang['access_permissions'],
        $_lang['access_permission_denied']
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
