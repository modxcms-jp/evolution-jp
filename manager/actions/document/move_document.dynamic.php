<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('move_document') || !evo()->hasPermission('save_document') || !evo()->hasPermission('publish_document')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (isset($_REQUEST['id'])) {
    $id = (int)$_REQUEST['id'];
} elseif (isset($_REQUEST['batch'])) {
    $id = join(',', $_REQUEST['batch']);
} else {
    alert()->setError(2);
    alert()->dumpError();
}

// check permissions on the document
if (!$modx->checkPermissions($id)) {
    show_perm_error();
    exit;
}

echo get_src_js();
$parent = get_parentid($id);
echo get_src_content($id, $parent);


function get_src_content($id, $parent)
{
    global $_lang, $_style;
    $redirect = $parent == 0 ? 'index.php?a=2' : "index.php?a=120&amp;id={$parent}";
    $src = <<< EOT
<h1>{$_lang['move_resource_title']}</h1>
<div id="actions">
	<ul class="actionButtons">
	  <li><a href="#" onclick="document.newdocumentparent.submit();" class="primary"><img src="{$_style["icons_save"]}" /> {$_lang['save']}</a></li>
	  <li class="mutate"><a href="#" onclick="documentDirty=false;document.location.href='{$redirect}'"><img src="{$_style["icons_cancel"]}" /> {$_lang['cancel']}</a></li>
	</ul>
</div>

<div class="section">
<div class="sectionBody">
<p>{$_lang['move_resource_message']}</p>
<form method="post" action="index.php" name='newdocumentparent'>
<input type="hidden" name="a" value="52">
<input type="hidden" name="id" value="{$id}">
<p>{$_lang['resource_to_be_moved']}: <b>{$id}</b></p>
<p><span id="parentName" class="warning">{$_lang['move_resource_new_parent']}</span></p>
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
    foreach ($_REQUEST['batch'] as $v) {
        $ids[] = sprintf("id='%s'", db()->escape($v));
    }
    $where = implode(' OR ', $ids);
    $rs = db()->select('pagetitle', '[+prefix+]site_content', $where);
    while ($row = db()->getRow($rs)) {
        echo $row['pagetitle'] . '<br />';
    }
}

function get_src_js()
{
    global $_lang;
    $src = <<< EOT
<script language="javascript">
top.mainMenu.defaultTreeFrame();
parent.tree.ca = "move";
function setMoveValue(pId, pName) {
    if (pId===0 || checkParentChildRelation(pId, pName)) {
        document.newdocumentparent.new_parent.value=pId;
        document.getElementById('parentName').innerHTML = "{$_lang['new_parent']}: <b>" + pId + "</b> (" + pName + ")";
    }
}

// check if the selected parent is a child of this document
function checkParentChildRelation(pId, pName) {
    const id = document.newdocumentparent.id.value;
    const tdoc = parent.tree.document;
    let pn = tdoc.getElementById("node"+pId);
    if (!pn) return false;
    if (pn.id.substr(4)===id) {
        alert("{$_lang['illegal_parent_self']}");
        return false;
    } else {
        while (pn.p>0) {
            pn = tdoc.getElementById("node"+pn.p);
            if (pn.id.substr(4)===id) {
                alert("{$_lang['illegal_parent_child']}");
                return false;
            }
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
