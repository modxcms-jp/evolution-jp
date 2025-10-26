<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('save_template')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (!is_numeric($_REQUEST['id'])) {
    echo 'Template ID is NaN';
    exit;
}
$id = intval($_REQUEST['id']);

$basePath = $modx->config['base_path'];
$siteURL = MODX_SITE_URL;

$updateMsg = '';

if (isset($_POST['listSubmitted'])) {
    $updateMsg .= '<span class="success" id="updated">Updated!<br /><br /></span>';
    foreach ($_POST as $listName => $listValue) {
        if ($listName === 'listSubmitted') {
            continue;
        }
        $orderArray = explode(';', rtrim($listValue, ';'));
        foreach ($orderArray as $key => $item) {
            if (strlen($item) == 0) {
                continue;
            }
            $tmplvar = ltrim($item, 'item_');
            db()->update(array('rank' => $key), '[+prefix+]site_tmplvar_templates',
                "tmplvarid='{$tmplvar}' AND templateid='{$id}'");
        }
    }
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

if ($limit > 1) {
    for ($i = 0; $i < $limit; $i++) {
        $row = db()->getRow($rs);
        if ($i == 0) {
            $evtLists .= '<strong>' . $row['templatename'] . '</strong><br /><ul id="sortlist" class="sortableList">';
        }
        $evtLists .= '<li id="item_' . $row['id'] . '" class="sort">' . $row['name'] . '</li>';
    }
}

if ($evtLists !== '') {
    $evtLists .= '</ul>';
}

$header = '
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">
<head>
        <title>MODx</title>
        <meta http-equiv="Content-Type" content="text/html; charset=' . $modx_manager_charset . '" />
        <link rel="stylesheet" type="text/css" href="media/style/' . $manager_theme . '/style.css" />';

$header .= <<<HTML
    <style type="text/css">
        .topdiv {
                        border: 0;
                }

                .subdiv {
                        border: 0;
                }

                li {list-style:none;}

                ul.sortableList {
                        padding-left: 20px;
                        margin: 0px;
                        width: 300px;
                        font-family: Arial, sans-serif;
                }

                ul.sortableList li {
                        font-weight: bold;
                        cursor: move;
                        color: #444444;
                        padding: 3px 5px;
                        margin: 4px 0px;
                        border: 1px solid #CCCCCC;
                        background-image: url("media/style/$manager_theme/images/misc/fade.gif");
                        background-repeat: repeat-x;
                        -webkit-user-select: none;
                        -moz-user-select: none;
                        user-select: none;
                }

                ul.sortableList li.dragging {
                        opacity: 0.6;
                }
        </style>
    <script type="text/javascript">
        (function() {
            var sortList;
            var dragItem = null;
            var listField;

            function hasSortClass(el) {
                return el && el.className && (' ' + el.className + ' ').indexOf(' sort ') !== -1;
            }

            function clearDragging(el) {
                if (!el || !el.className) return;
                el.className = el.className.replace(/\s*dragging\s*/g, ' ').replace(/\s{2,}/g, ' ').replace(/^\s+|\s+$/g, '');
            }

            function updateListField() {
                if (!sortList) return;
                if (!listField) {
                    listField = document.getElementById('list');
                }
                if (!listField) return;
                var ids = [];
                var children = sortList.children || sortList.childNodes;
                for (var i = 0; i < children.length; i++) {
                    var child = children[i];
                    if (child && child.nodeType === 1 && child.id) {
                        ids.push(child.id);
                    }
                }
                listField.value = ids.join(';');
            }

            function onDragStart(e) {
                dragItem = this;
                this.className += ' dragging';
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                    try {
                        e.dataTransfer.setData('text/plain', this.id);
                    } catch (err) {
                        // IE may throw errors for setData with unsupported formats; ignore.
                    }
                }
            }

            function findListItem(target) {
                while (target && target !== sortList && target.nodeType === 1 && target.tagName !== 'LI') {
                    target = target.parentNode;
                }
                if (target && target.tagName === 'LI' && hasSortClass(target)) {
                    return target;
                }
                return null;
            }

            function onDragOver(e) {
                if (!dragItem) return;
                if (e.preventDefault) {
                    e.preventDefault();
                }
                var target = findListItem(e.target || e.srcElement);
                if (!target || target === dragItem) {
                    return;
                }
                var rect = target.getBoundingClientRect();
                var isAfter = (e.clientY || 0) - rect.top > rect.height / 2;
                sortList.insertBefore(dragItem, isAfter ? target.nextSibling : target);
            }

            function onDrop(e) {
                if (e.preventDefault) {
                    e.preventDefault();
                }
                updateListField();
            }

            function onDragEnd() {
                clearDragging(this);
                dragItem = null;
                updateListField();
            }

            function prepareItems() {
                var items = sortList.getElementsByTagName('li');
                for (var i = 0; i < items.length; i++) {
                    var item = items[i];
                    if (!hasSortClass(item)) {
                        continue;
                    }
                    item.setAttribute('draggable', 'true');
                    item.ondragstart = onDragStart;
                    item.ondragend = onDragEnd;
                }
            }

            function init() {
                sortList = document.getElementById('sortlist');
                if (!sortList) {
                    return;
                }
                prepareItems();
                if (sortList.addEventListener) {
                    sortList.addEventListener('dragover', onDragOver, false);
                    sortList.addEventListener('drop', onDrop, false);
                } else if (sortList.attachEvent) {
                    sortList.attachEvent('ondragover', onDragOver);
                    sortList.attachEvent('ondrop', onDrop);
                }
                updateListField();
            }

            if (document.addEventListener) {
                document.addEventListener('DOMContentLoaded', init, false);
            } else if (window.attachEvent) {
                window.attachEvent('onload', init);
            }

            window.save = function() {
                updateListField();
                if (document.sortableListForm) {
                    document.sortableListForm.submit();
                }
            };
        })();
        </script>
HTML;

$header .= '</head>
<body>

<h1>' . $_lang["template_tv_edit_title"] . '</h1>

<div id="actions">
    <ul class="actionButtons">
        <li class="mutate"><a class="default" href="#" onclick="save();"><img src="' . $_style["icons_save"] . '" /> ' . $_lang['update'] . '</a></li>
		<li class="mutate"><a href="#" onclick="document.location.href=\'index.php?a=16&amp;id=' . anyv('id') . '\';"><img src="' . $_style["icons_cancel"] . '"> ' . $_lang['cancel'] . '</a></li>
	</ul>
</div>

<div class="section">
<div class="sectionHeader">' . $_lang['template_tv_edit'] . '</div>
<div class="sectionBody">
<p>' . $_lang["template_tv_edit_message"] . '</p>';

echo $header;

echo $updateMsg . "<span class=\"warning\" style=\"display:none;\" id=\"updating\">Updating...<br /><br /> </span>";

echo $evtLists;

echo '
</div>
</div>
<form action="" method="post" name="sortableListForm" style="display: none;">
            <input type="hidden" name="listSubmitted" value="true" />
            <input type="hidden" id="list" name="list" value="" />
</form>';
