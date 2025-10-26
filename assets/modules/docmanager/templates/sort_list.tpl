<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <title>[+lang.DM_module_title+]</title>
    <link rel="stylesheet" type="text/css" href="media/style[+theme+]/style.css"/>
    <script src="media/script/jquery/jquery.min.js" type="text/javascript"></script>
    <script type="text/javascript" src="../assets/modules/docmanager/js/docmanager.js"></script>
    <script type="text/javascript">
        (function() {
            var sortList;
            var dragItem = null;
            var listField;

            function updateHidden() {
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

            function clearDragging(el) {
                if (!el || !el.className) return;
                el.className = el.className.replace(/\s*dragging\s*/g, ' ').replace(/\s{2,}/g, ' ').replace(/^\s+|\s+$/g, '');
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

            function findTarget(list, target) {
                while (target && target !== list && (target.nodeType !== 1 || target.tagName !== 'LI')) {
                    target = target.parentNode;
                }
                if (target && target !== list && target.tagName === 'LI') {
                    return target;
                }
                return null;
            }

            function onDragOver(e) {
                if (!dragItem) return;
                if (e.preventDefault) {
                    e.preventDefault();
                }
                var target = findTarget(sortList, e.target || e.srcElement);
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
                updateHidden();
            }

            function onDragEnd() {
                clearDragging(this);
                dragItem = null;
                updateHidden();
            }

            function prepareItems() {
                var items = sortList.getElementsByTagName('li');
                for (var i = 0; i < items.length; i++) {
                    var item = items[i];
                    item.setAttribute('draggable', 'true');
                    item.ondragstart = onDragStart;
                    item.ondragend = onDragEnd;
                }
            }

            function init() {
                sortList = document.getElementById('sortlist');
                if (!sortList) return;
                prepareItems();
                if (sortList.addEventListener) {
                    sortList.addEventListener('dragover', onDragOver, false);
                    sortList.addEventListener('drop', onDrop, false);
                } else if (sortList.attachEvent) {
                    sortList.attachEvent('ondragover', onDragOver);
                    sortList.attachEvent('ondrop', onDrop);
                }
                updateHidden();
                if ([+sort.disable_tree_select+] == true) {
                    parent.tree.ca = '';
                }
            }

            if (document.addEventListener) {
                document.addEventListener('DOMContentLoaded', init, false);
            } else if (window.attachEvent) {
                window.attachEvent('onload', init);
            }

            window.save = function() {
                updateHidden();
                if (document.sortableListForm) {
                    document.sortableListForm.submit();
                }
            };

            window.reset = function() {
                if (document.resetform) {
                    document.resetform.submit();
                }
            };
        })();

        parent.tree.updateTree();
    </script>
    <style type="text/css">
        ul.sortableList li {
            cursor: move;
            border: 1px solid #ccc;
            background: #eee no-repeat 2px center;
            margin: 2px 0 5px;
            list-style: none;
            padding: 1px 4px 1px 24px;
            min-height: 20px;
            width: 50%;
        }

        ul.sortableList li.dragging {
            opacity: 0.6;
        }

        ul.sortableList li.noChildren {
            background-image: url(media/style[+theme+]/images/tree/page.png);
        }

        ul.sortableList li.hasChildren {
            background-image: url(media/style[+theme+]/images/tree/folder.png);
        }

        ul.sortableList li.homeNode {
            background-image: url(media/style[+theme+]/images/tree/application_home.png);
        }

        ul.sortableList li.unavailableNode {
            background-image: url(media/style[+theme+]/images/tree/application_hourglass.png);
        }

        ul.sortableList li.unauthorizedNode {
            background-image: url(media/style[+theme+]/images/tree/application_hourglass.png);
        }

        ul.sortableList li.errorNode {
            background-image: url(media/style[+theme+]/images/tree/application_404.png);
        }

        ul.sortableList li.inMenuNode {font-weight:bold;} ul.sortableList li.unpublishedNode

        {background-color:#f6f3ea;}
    </style>
</head>
<body>
<h1>[+lang.DM_module_title+]</h1>
<form action="" method="post" name="resetform" style="display: none;">
    <input name="actionkey" type="hidden" value="0"/>
</form>
<div id="actions">
    <ul class="actionButtons">
        <li id="Button1"><a href="#" onclick="document.location.href='index.php?a=2';"><img
                        src="media/style[+theme+]/images/icons/stop.png" align="absmiddle"> [+lang.DM_close+]</a></li>
        <li id="Button2" style="display:[+sort.save+]"><a href="#" onclick="save();"><img
                        src="media/style[+theme+]/images/icons/save.png" align="absmiddle"> [+lang.DM_save+]</a></li>
        <li id="Button4"><a href="#" onclick="reset();"><img src="media/style[+theme+]/images/icons/cancel.png"
                                                             align="absmiddle"> [+lang.DM_cancel+]</a></li>
    </ul>
</div>
<div class="section">
    <div class="sectionHeader">[+lang.DM_sort_title+]</div>
    <div class="sectionBody">
        [+sort.message+]
        <ul id="sortlist" class="sortableList">
            [+sort.options+]
        </ul>
        <form action="" method="post" name="sortableListForm" style="display: none;">
            <input type="hidden" name="tabAction" value="sortList"/>
            <input type="hidden" id="list" name="list" value=""/>
        </form>
    </div>
</div>
</body>
</html>
