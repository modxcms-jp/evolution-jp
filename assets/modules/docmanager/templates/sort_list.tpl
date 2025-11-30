<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
    <title>[+lang.DM_module_title+]</title>
    [+csrf_meta+]
    <link rel="stylesheet" type="text/css" href="media/style[+theme+]/style.css"/>
    <script src="media/script/jquery/jquery.min.js" type="text/javascript"></script>
    <script type="text/javascript" src="../assets/modules/docmanager/js/docmanager.js"></script>
    <script type="text/javascript" src="media/script/dragdrop-sort.js"></script>
    <script type="text/javascript">
        (function() {
            function onReady(handler) {
                if (document.addEventListener) {
                    document.addEventListener('DOMContentLoaded', handler, false);
                } else if (window.attachEvent) {
                    window.attachEvent('onload', handler);
                } else {
                    var prev = window.onload;
                    window.onload = function() {
                        if (typeof prev === 'function') {
                            prev();
                        }
                        handler();
                    };
                }
            }

            onReady(function() {
                if (window.MODXSortable) {
                    window.MODXSortable.updateAll();
                }
                var disableTreeSelect = (function(value) {
                    if (typeof value === 'boolean') {
                        return value;
                    }
                    if (value == null) {
                        return false;
                    }
                    if (typeof value === 'number') {
                        return value !== 0;
                    }
                    var normalized = String(value).toLowerCase();
                    return normalized === 'true' || normalized === '1';
                })([+sort.disable_tree_select+]);
                if (disableTreeSelect && parent && parent.tree) {
                    parent.tree.ca = '';
                }
            });

            window.save = function() {
                if (window.MODXSortable) {
                    window.MODXSortable.updateAll();
                }
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
            background-image: url([+style_tree_path+]page.png);
        }

        ul.sortableList li.hasChildren {
            background-image: url([+style_tree_path+]folder.png);
        }

        ul.sortableList li.homeNode {
            background-image: url([+style_tree_path+]application_home.png);
        }

        ul.sortableList li.unavailableNode {
            background-image: url([+style_tree_path+]application_hourglass.png);
        }

        ul.sortableList li.unauthorizedNode {
            background-image: url([+style_tree_path+]application_hourglass.png);
        }

        ul.sortableList li.errorNode {
            background-image: url([+style_tree_path+]application_404.png);
        }

        ul.sortableList li.inMenuNode {font-weight:bold;} ul.sortableList li.unpublishedNode

        {background-color:#f6f3ea;}
    </style>
</head>
<body>
<h1>[+lang.DM_module_title+]</h1>
<form action="" method="post" name="resetform" style="display: none;">
    [+csrf_token+]
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
        <ul id="sortlist" class="sortableList" data-sortable="true" data-target="list" data-delimiter=";">
            [+sort.options+]
        </ul>
        <form action="" method="post" name="sortableListForm" style="display: none;">
            [+csrf_token+]
            <input type="hidden" name="tabAction" value="sortList"/>
            <input type="hidden" id="list" name="list" value=""/>
        </form>
    </div>
</div>
</body>
</html>
