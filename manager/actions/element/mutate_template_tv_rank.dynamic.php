<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_template')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (!is_numeric(anyv('id'))) {
    echo 'Template ID is NaN';
    exit;
}
$id = intval(anyv('id'));

$updateMsg = '';

if (postv('listSubmitted')) {
    checkCsrfToken();

    $updatedCount = 0;
    foreach (postv() as $listName => $listValue) {
        if ($listName === 'listSubmitted' || $listName === 'csrf_token') {
            continue;
        }
        $orderArray = explode(';', rtrim($listValue, ';'));
        foreach ($orderArray as $key => $item) {
            if ($item === '') {
                continue;
            }
            $tmplvar = (int) preg_replace('/^item_/', '', $item);
            if ($tmplvar <= 0) {
                continue;
            }
            db()->update(['rank' => $key], '[+prefix+]site_tmplvar_templates',
                "tmplvarid='{$tmplvar}' AND templateid='{$id}'");
            $updatedCount++;
        }
    }

    $updateMsg = $updatedCount > 0
        ? '<span class="success" id="updated">Updated!<br /><br /></span>'
        : '<span class="warning" id="updated">No changes to save.<br /><br /></span>';

    // empty cache
    $modx->clearCache(); // first empty the cache
}

$field = 'tv.name AS `name`, tv.id AS `id`, tr.templateid, tr.rank, tm.templatename';
$from = '[+prefix+]site_tmplvar_templates tr';
$from .= ' INNER JOIN [+prefix+]site_tmplvars tv ON tv.id = tr.tmplvarid';
$from .= ' INNER JOIN [+prefix+]site_templates tm ON tr.templateid = tm.id';
$where = "tr.templateid='{$id}'";
$orderby = 'tr.rank, tv.rank, tv.id';

$rs = db()->select($field, $from, $where, $orderby);
$limit = db()->count($rs);
$evtLists = '';

if ($limit > 0) {
    for ($i = 0; $i < $limit; $i++) {
        $row = db()->getRow($rs);
        if (!$row) {
            continue;
        }
        if ($i === 0) {
            $evtLists .= '<strong>' . hsc($row['templatename']) . '</strong><br /><ul id="sortlist" class="sortableList" data-sortable="true" data-target="list" data-delimiter=";">';
        }
        $evtLists .= '<li id="item_' . (int)$row['id'] . '" class="sort">' . hsc($row['name']) . '</li>';
    }

    if ($evtLists !== '') {
        $evtLists .= '</ul>';
    }
}

$misc_path = manager_style_image_path('misc');
$cancelUrl = 'index.php?a=16&id=' . (int)anyv('id');
?>
<style type="text/css">
    ul.sortableList {
        padding-left: 20px;
        margin: 0;
        width: 300px;
        font-family: Arial, sans-serif;
    }

    ul.sortableList li {
        list-style: none;
        font-weight: bold;
        cursor: move;
        color: #444444;
        padding: 3px 5px;
        margin: 4px 0;
        border: 1px solid #CCCCCC;
        background-image: url("<?= $misc_path ?>fade.gif");
        background-repeat: repeat-x;
        -webkit-user-select: none;
        -moz-user-select: none;
        user-select: none;
    }

    ul.sortableList li.dragging {
        opacity: 0.6;
    }
</style>
<script type="text/javascript" src="media/script/dragdrop-sort.js"></script>
<script type="text/javascript">
    (function() {
        window.save = function() {
            if (window.MODXSortable) {
                window.MODXSortable.updateAll();
            }
            if (document.sortableListForm) {
                document.sortableListForm.submit();
            }
        };
        window.evoCancelTvRank = function(el, fallbackUrl) {
            if (el.closest('#evoShellModal') && window.EvoShell) {
                window.EvoShell.closeModal();
            } else {
                document.location.href = fallbackUrl;
            }
        };
    })();
</script>

<h1><?= $_lang["template_tv_edit_title"] ?></h1>

<div class="section">
<div class="sectionHeader"><?= $_lang['template_tv_edit'] ?></div>
<div class="sectionBody">
<p><?= $_lang["template_tv_edit_message"] ?></p>
<?= $updateMsg ?><span class="warning" style="display:none;" id="updating">Updating...<br /><br /> </span>
<?= $evtLists ?>
</div>
</div>

<div id="actions">
    <ul class="actionButtons">
        <li class="mutate"><a href="#" onclick="evoCancelTvRank(this, '<?= $cancelUrl ?>'); return false;"><img src="<?= $_style["icons_cancel"] ?>"> <?= $_lang['cancel'] ?></a></li>
        <li class="mutate"><a class="default" href="#" onclick="save(); return false;"><img src="<?= $_style["icons_save"] ?>" /> <?= $_lang['update'] ?></a></li>
    </ul>
</div>

<form action="index.php?a=117&amp;id=<?= $id ?>" method="post" name="sortableListForm" style="display: none;">
    <input type="hidden" name="listSubmitted" value="true" />
    <input type="hidden" id="list" name="list" value="" />
    <?= csrfTokenField() ?>
</form>
