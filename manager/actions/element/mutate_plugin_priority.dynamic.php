<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_plugin')) {
    alert()->setError(3);
    alert()->dumpError();
}

$updateMsg = '';

if (isset($_POST['listSubmitted'])) {
    $updateMsg .= '<span class="success" id="updated">Updated!<br /><br /> </span>';

    foreach ($_POST as $listName => $listValue) {
        if ($listName === 'listSubmitted') {
            continue;
        }
        $orderArray = explode(',', $listValue);
        if (substr($listName, 0, 5) === 'list_') {
            $listName = substr($listName, 5);
        }
        if (count($orderArray) > 0) {
            foreach ($orderArray as $key => $item) {
                if ($item == '') {
                    continue;
                }
                $pluginId = ltrim($item, 'item_');
                $field['priority'] = $key;
                db()->update($field, '[+prefix+]site_plugin_events', "pluginid={$pluginId} AND evtid='{$listName}'");
            }
        }
    }
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
        $evtLists .= '<strong>' . $row['evtname'] . '</strong><br /><ul id="' . $row['evtid'] . '" class="sortableList">';
        $insideUl = 1;
    }
    $evtLists .= '<li id="item_' . $row['pluginid'] . '">' . $row['name'] . '</li>';
    $preEvt = $row['evtid'];
}

$evtLists .= '</ul>';

$header = '
<!doctype html>
<head>
        <title>MODX</title>
        <meta http-equiv="Content-Type" content="text/html; charset=' . $modx_manager_charset . '" />
        <link rel="stylesheet" type="text/css" href="media/style/' . $manager_theme . '/style.css" />
        <script type="text/javascript" src="media/script/jquery/jquery.min.js"></script>
';

$header .= sprintf(<<<'HTML'

        <style type="text/css">
        .topdiv {border: 0;}
                .subdiv {border: 0;}
                li {list-style:none;}
                .tplbutton {text-align: right;}
                ul.sortableList
                {
                        padding-left: 20px;
                        margin: 0;
                        width: 300px;
                }

                ul.sortableList li
                {
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
        ul.sortableList li.dragging {opacity: 0.6;}
        #sortableListForm {display:none;}
        </style>
    <script>
        (function() {
            var listIds = %s;
            var dragItem = null;

            function hasListItemClass(el) {
                return el && el.tagName === 'LI';
            }

            function clearDragging(el) {
                if (!el || !el.className) return;
                el.className = el.className.replace(/\s*dragging\s*/g, ' ').replace(/\s{2,}/g, ' ').replace(/^\s+|\s+$/g, '');
            }

            function updateHidden(list) {
                if (!list || !list.id) return;
                var field = document.getElementById('list_' + list.id);
                if (!field) return;
                var ids = [];
                var children = list.children || list.childNodes;
                for (var i = 0; i < children.length; i++) {
                    var child = children[i];
                    if (child && child.nodeType === 1 && child.id) {
                        ids.push(child.id);
                    }
                }
                field.value = ids.join(',');
            }

            function onDragStart(e) {
                dragItem = this;
                this.className += ' dragging';
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                    try {
                        e.dataTransfer.setData('text/plain', this.id);
                    } catch (err) {}
                }
            }

            function findItemWithin(list, target) {
                while (target && target !== list && (target.nodeType !== 1 || target.tagName !== 'LI')) {
                    target = target.parentNode;
                }
                if (target && target !== list && hasListItemClass(target)) {
                    return target;
                }
                return null;
            }

            function onDragOver(e) {
                if (!dragItem) return;
                if (e.preventDefault) {
                    e.preventDefault();
                }
                var list = this;
                var target = findItemWithin(list, e.target || e.srcElement);
                if (!target || target === dragItem) {
                    return;
                }
                var rect = target.getBoundingClientRect();
                var isAfter = (e.clientY || 0) - rect.top > rect.height / 2;
                list.insertBefore(dragItem, isAfter ? target.nextSibling : target);
            }

            function onDrop(e) {
                if (e.preventDefault) {
                    e.preventDefault();
                }
                updateHidden(this);
            }

            function onDragEnd() {
                clearDragging(this);
                var parent = this.parentNode;
                dragItem = null;
                if (parent) {
                    updateHidden(parent);
                }
            }

            function prepareList(list) {
                if (!list) return;
                var items = list.getElementsByTagName('li');
                for (var i = 0; i < items.length; i++) {
                    var item = items[i];
                    item.setAttribute('draggable', 'true');
                    item.ondragstart = onDragStart;
                    item.ondragend = onDragEnd;
                }
                if (list.addEventListener) {
                    list.addEventListener('dragover', onDragOver, false);
                    list.addEventListener('drop', onDrop, false);
                } else if (list.attachEvent) {
                    list.attachEvent('ondragover', onDragOver);
                    list.attachEvent('ondrop', onDrop);
                }
                updateHidden(list);
            }

            function init() {
                for (var i = 0; i < listIds.length; i++) {
                    var list = document.getElementById(listIds[i]);
                    prepareList(list);
                }
            }

            if (document.addEventListener) {
                document.addEventListener('DOMContentLoaded', init, false);
            } else if (window.attachEvent) {
                window.attachEvent('onload', init);
            }

            window.save = function() {
                for (var i = 0; i < listIds.length; i++) {
                    var list = document.getElementById(listIds[i]);
                    updateHidden(list);
                }
                if (document.sortableListForm) {
                    document.sortableListForm.submit();
                }
            };
        })();
    </script>
HTML
, json_encode($sortables));
$header .= '</head>
<body>

<h1>' . $_lang['plugin_priority_title'] . '</h1>

<div id="actions">
   <ul class="actionButtons">
        <li class="mutate"><a href="#" onclick="save();"><img src="' . $_style["icons_save"] . '" /> ' . $_lang['update'] . '</a></li>
                <li class="mutate"><a href="#" onclick="document.location.href=\'index.php?a=76\';"><img src="' . $_style["icons_cancel"] . '" /> ' . $_lang['cancel'] . '</a></li>
        </ul>
</div>

<div class="section">
<div class="sectionHeader">' . $_lang['plugin_priority'] . '</div>
<div class="sectionBody">
<p>' . $_lang['plugin_priority_instructions'] . '</p>
';

echo $header;

echo $updateMsg . '<span class="warning" style="display:none;" id="updating">Updating...<br /><br /> </span>';

echo $evtLists;

echo '<form action="" method="post" name="sortableListForm" style="display: none;">
            <input type="hidden" name="listSubmitted" value="true" />';

foreach ($sortables as $list) {
    echo '<input type="hidden" id="list_' . $list . '" name="list_' . $list . '" value="" />';
}

echo '	</form>
	</div>
</div>
';
