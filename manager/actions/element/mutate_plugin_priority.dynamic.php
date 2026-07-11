<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_plugin')) {
    alert()->setError(3);
    alert()->dumpError();
}

$updateMsg = '';

if (postv('listSubmitted')) {
    checkCsrfToken();

    $updatedCount = 0;
    foreach (postv() as $listName => $listValue) {
        if ($listName === 'listSubmitted' || strpos($listName, 'list_') !== 0) {
            continue;
        }
        $orderArray = explode(',', $listValue);
        $evtId = (int) substr($listName, 5);
        if ($evtId <= 0) {
            continue;
        }
        foreach ($orderArray as $key => $item) {
            if ($item === '') {
                continue;
            }
            $pluginId = (int) preg_replace('/^item_/', '', $item);
            if ($pluginId <= 0) {
                continue;
            }
            $field = ['priority' => $key];
            db()->update($field, '[+prefix+]site_plugin_events', "pluginid={$pluginId} AND evtid='{$evtId}'");
            $updatedCount++;
        }
    }

    $updateMsg = $updatedCount > 0
        ? '<span class="success" id="updated">Updated!<br /><br /> </span>'
        : '<span class="warning" id="updated">No changes to save.<br /><br /> </span>';

    // empty cache
    $modx->clearCache(); // first empty the cache
}

$f['evtname'] = 'sysevt.name';
$f['evtid'] = 'sysevt.id';
$f[] = 'pe.pluginid';
$f[] = 'plugs.name';
$f[] = 'pe.priority';
$from[] = '[+prefix+]system_eventnames sysevt';
$from[] = 'INNER JOIN [+prefix+]site_plugin_events pe ON pe.evtid = sysevt.id';
$from[] = 'INNER JOIN [+prefix+]site_plugins plugs ON plugs.id = pe.pluginid';
$rs = db()->select($f, $from, 'plugs.disabled=0', 'sysevt.name,pe.priority');

$insideUl = 0;
$preEvt = '';
$evtLists = '';
$sortables = [];
while ($row = db()->getRow($rs)) {
    if ($preEvt !== $row['evtid']) {
        $sortables[] = $row['evtid'];
        $evtLists .= $insideUl ? '</ul><br />' : '';
        $evtLists .= '<strong>' . hsc($row['evtname']) . '</strong><br /><ul id="' . (int)$row['evtid'] . '" class="sortableList" data-sortable="true" data-target="list_' . (int)$row['evtid'] . '" data-delimiter=",">';
        $insideUl = 1;
    }
    $evtLists .= '<li id="item_' . (int)$row['pluginid'] . '">' . hsc($row['name']) . '</li>';
    $preEvt = $row['evtid'];
}

if ($insideUl) {
    $evtLists .= '</ul>';
}
?>
<style type="text/css">
    ul.sortableList {
        padding-left: 20px;
        margin: 0;
        width: 300px;
    }

    ul.sortableList li {
        list-style: none;
        font-weight: bold;
        cursor: move;
        color: #444444;
        padding: 3px 5px;
        margin: 4px 0;
        border: 1px solid #CCCCCC;
        background-repeat: repeat-x;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
    }

    ul.sortableList li.dragging {
        opacity: 0.6;
    }

    #sortableListForm {
        display: none;
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
        window.evoCancelPluginPriority = function(el, fallbackUrl) {
            if (el.closest('#evoShellModal') && window.EvoShell) {
                window.EvoShell.closeModal();
            } else {
                document.location.href = fallbackUrl;
            }
        };
    })();
</script>

<h1><?= $_lang['plugin_priority_title'] ?></h1>

<div class="section">
<div class="sectionHeader"><?= $_lang['plugin_priority'] ?></div>
<div class="sectionBody">
<p><?= $_lang['plugin_priority_instructions'] ?></p>
<?= $updateMsg ?><span class="warning" style="display:none;" id="updating">Updating...<br /><br /> </span>
<?= $evtLists ?>
</div>
</div>

<div id="actions">
   <ul class="actionButtons">
        <li class="mutate"><a href="#" onclick="evoCancelPluginPriority(this, 'index.php?a=76'); return false;"><img src="<?= $_style["icons_cancel"] ?>" /> <?= $_lang['cancel'] ?></a></li>
        <li class="mutate"><a class="default" href="#" onclick="save(); return false;"><img src="<?= $_style["icons_save"] ?>" /> <?= $_lang['update'] ?></a></li>
        </ul>
</div>

<form action="index.php?a=100" method="post" name="sortableListForm" style="display: none;">
    <input type="hidden" name="listSubmitted" value="true" />
    <?= csrfTokenField() ?>
    <?php foreach ($sortables as $list): ?>
    <input type="hidden" id="list_<?= (int)$list ?>" name="list_<?= (int)$list ?>" value="" />
    <?php endforeach; ?>
</form>
