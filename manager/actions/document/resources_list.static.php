<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!hasPermission('view_document')) {
    alert()->setError(3);
    alert()->dumpError();
}
if (preg_match('@^[1-9][0-9]*$@', $_GET['id'])) {
    $id = $_GET['id'];
} else {
    $id = 0;
}

if (isset($_GET['pid'])) {
    $_GET['pid'] = intval($_GET['pid']);
}

evo()->loadExtension('DocAPI');

$modx->updatePublishStatus();

if (!$id) {
    $current = array();
} else {
    $rs = db()->select('*', '[+prefix+]site_content', "id='{$id}'");
    $current = db()->getRow($rs);

    // Set the item name for logging
    $_SESSION['itemname'] = $current['pagetitle'];

    foreach ($current as $k => $v) {
        $current[$k] = $modx->hsc($v);
    }
}

if (!isset($current['id'])) {
    $current['id'] = 0;
}
/**
 * "View Children" tab setup
 */

// Get access permissions

$docgrp = $_SESSION['mgrDocgroups'] ? implode(',', $_SESSION['mgrDocgroups']) : '';

$in_docgrp = !empty($docgrp) ? " OR dg.document_group IN ({$docgrp})" : '';

// Get child document count

$from = "[+prefix+]site_content AS sc LEFT JOIN [+prefix+]document_groups AS dg ON dg.document = sc.id";
$where = array();
$where[] = "sc.parent='{$id}'";
if ($_SESSION['mgrRole'] != 1 && !$modx->config['tree_show_protected']) {
    $where[] = sprintf("AND (sc.privatemgr=0 %s)", $in_docgrp);
}
$rs = db()->select('DISTINCT sc.id', $from, $where);
$numRecords = db()->count($rs);

if (!$numRecords) {
    $children_output = "<p>" . $_lang['resources_in_container_no'] . "</p>";
} else {
    $children_output = '';
    $f[] = 'DISTINCT sc.*';
    if ($_SESSION['mgrRole'] != 1) {
        $f['has_access'] = sprintf('MAX(IF(sc.privatemgr=0 %s, 1, 0))', $in_docgrp);
    }
    $f[] = 'rev.status';
    $from = array();
    $from[] = '[+prefix+]site_content sc';
    $from[] = 'LEFT JOIN [+prefix+]document_groups dg ON dg.document=sc.id';
    $from[] = "LEFT JOIN [+prefix+]site_revision rev on rev.elmid=sc.id AND (rev.status='draft' OR rev.status='pending' OR rev.status='standby') AND rev.element='resource'";
    $where = array();
    $where[] = "sc.parent='{$id}'";
    if ($_SESSION['mgrRole'] != 1 && !$modx->config['tree_show_protected']) {
        $where[] = sprintf("AND (sc.privatemgr=0 %s)", $in_docgrp);
    }
    $where[] = 'GROUP BY sc.id,rev.status';
    $orderby = 'sc.isfolder DESC, sc.publishedon DESC, if(sc.editedon=0,10000000000,sc.editedon) DESC, sc.id DESC';
    if (isset($_GET['page']) && preg_match('@^[1-9][0-9]*$@', $_GET['page'])) {
        $offset = $_GET['page'] - 1;
    } else {
        $offset = 0;
    }
    $limit = sprintf('%s,%s', ($offset * $modx->config['number_of_results']), $modx->config['number_of_results']);
    $rs = db()->select($f, $from, $where, $orderby, $limit);
    $docs = array();
    while ($row = db()->getRow($rs)) {
        $docid = $row['id'];
        $docs[$docid] = $row;
    }

    $rows = array();
    $tpl = '<div class="title">[+icon+][+statusIcon+]</div><a href="[+link+]">[+title+][+description+]</a>';
    foreach ($docs as $docid => $doc) {

        if (!$modx->manager->isContainAllowed($docid)) {
            continue;
        }

        if ($_SESSION['mgrRole'] == 1) {
            $doc['has_access'] = 1;
        }
        $doc = $modx->hsc($doc);

        $doc['icon'] = getIcon($doc);
        $doc['statusIcon'] = getStatusIcon($doc['status']);
        $doc['link'] = $doc['isfolder'] ? "index.php?a=120&amp;id={$docid}" : "index.php?a=27&amp;id={$docid}";
        $doc['title'] = getTitle($doc);
        $doc['description'] = getDescription($doc);

        $col = array();
        $col['checkbox'] = sprintf('<input type="checkbox" name="batch[]" value="%s" />', $docid);
        $col['docid'] = $docid;
        $col['title'] = $modx->parseText($tpl, $doc);
        $col['publishedon'] = getPublishedOn($doc);
        $col['editedon'] = getEditedon($doc['editedon']);
        $col['status'] = getStatus($doc);
        $rows[] = $col;
    }

    evo()->loadExtension('MakeTable');

    // CSS style for table
    $modx->table->setTableClass('grid');
    $modx->table->setRowHeaderClass('gridHeader');
    $modx->table->setRowDefaultClass('gridItem');
    $modx->table->setRowAlternateClass('gridAltItem');
    $modx->table->setColumnWidths('2%, 2%, 68%, 10%, 10%, 8%');
    $modx->table->setPageLimit($modx->config['number_of_results']);

    // Table header
    $header['checkbox'] = '<input type="checkbox" name="chkselall" onclick="selectAll()" />';
    $header['docid'] = $_lang['id'];
    $header['title'] = $_lang['resource_title'];
    $header['publishedon'] = $_lang['publish_date'];
    $header['editedon'] = $_lang['editedon'];
    $header['status'] = $_lang['page_data_status'];
    $qs = 'a=120';
    if ($id) {
        $qs .= "&id={$id}";
    }
    $pageNavBlock = $modx->table->renderPagingNavigation($numRecords, $qs);
    $children_output = $pageNavBlock . $modx->table->renderTable($rows, $header) . $pageNavBlock;
    if (hasPermission('move_document')) {
        $children_output .= '<div style="margin-top:10px;"><input type="submit" value="' . $_lang["document_data.static.php1"] . '" /></div>';
    }
}

// context menu
include_once(MODX_CORE_PATH . 'controls/contextmenu.php');
$cm = new ContextMenu('cntxm', 180);
$contextMenu = getContextMenu($cm);
echo $contextMenu;

echo get_jscript($id, $cm);

?>
    <script type="text/javascript">
        function duplicatedocument() {
            if (confirm("<?php echo $_lang['confirm_resource_duplicate'];?>") == true) {
                document.location.href = "index.php?id=<?php echo $id;?>&a=94";
            }
        }

        function deletedocument() {
            if (confirm("<?php echo $_lang['confirm_delete_resource'];?>") == true) {
                document.location.href = "index.php?id=<?php echo $id;?>&a=6";
            }
        }

        function editdocument() {
            document.location.href = "index.php?id=<?php echo $id;?>&a=27";
        }

        function movedocument() {
            document.location.href = "index.php?id=<?php echo $id;?>&a=51";
        }
    </script>
    <script type="text/javascript" src="media/script/tablesort.js"></script>
    <h1><?php echo $_lang['view_child_resources_in_container'] ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php
            $tpl = '<li id="%s" class="mutate"><a href="#" onclick="%s"><img src="%s" /> %s</a></li>';
            if (hasPermission('save_document') && $id != 0 && $modx->manager->isAllowed($id)) {
                echo sprintf($tpl, 'Button1', 'editdocument();', $_style["icons_edit_document"], $_lang['edit']);
            }
            if (hasPermission('move_document') && hasPermission('save_document') && $id != 0 && $modx->manager->isAllowed($id)) {
                echo sprintf($tpl, 'Button2', 'movedocument();', $_style["icons_move_document"], $_lang['move']);
            }
            if ($modx->doc->canCopyDoc() && $id != 0 && $modx->manager->isAllowed($id)) {
                echo sprintf($tpl, 'Button4', 'duplicatedocument();', $_style["icons_resource_duplicate"],
                    $_lang['duplicate']);
            }
            if (hasPermission('delete_document') && hasPermission('save_document') && $id != 0 && $modx->manager->isAllowed($id)) {
                echo sprintf($tpl, 'Button3', 'deletedocument();', $_style["icons_delete_document"], $_lang['delete']);
            }

            $url = $modx->makeUrl($id);
            $prev = "window.open('{$url}','previeWin')";
            echo sprintf($tpl, 'Button6', $prev, $_style["icons_preview_resource"],
                $id == 0 ? $_lang["view_site"] : $_lang['view_resource']);
            $action = getReturnAction($current);
            $action = "documentDirty=false;document.location.href='{$action}'";
            echo sprintf($tpl, 'Button5', $action, $_style["icons_cancel"], $_lang['cancel']);
            ?>
        </ul>
    </div>

    <div class="section">
        <div class="sectionBody">
            <!-- View Children -->
            <?php if (hasPermission('new_document')) { ?>

                <ul class="actionButtons">
                    <li class="mutate"><a href="index.php?a=4&amp;pid=<?php echo $id ?>"><img
                                    src="<?php echo $_style["icons_new_document"]; ?>"
                                    align="absmiddle"/> <?php echo $_lang['create_resource_here'] ?></a></li>
                    <li class="mutate"><a href="index.php?a=72&amp;pid=<?php echo $id ?>"><img
                                    src="<?php echo $_style["icons_new_weblink"]; ?>"
                                    align="absmiddle"/> <?php echo $_lang['create_weblink_here'] ?></a></li>
                </ul>
            <?php }
            if ($numRecords > 0) {
                $topicPath = getTopicPath($id);
            }
            echo <<< EOT
<script type="text/javascript">
    function selectAll() {
        var f = document.forms['mutate'];
        var c = f.elements['batch[]'];
        for(i=0;i<c.length;i++){
            c[i].checked=f.chkselall.checked;
        }
    }
</script>
{$topicPath}
<form name="mutate" id="mutate" class="content" method="post" enctype="multipart/form-data" action="index.php">
<input type="hidden" name="a" value="51" />
{$children_output}
</form>
EOT;
            ?>
            <style type="text/css">
                h3 {
                    font-size: 1em;
                    padding-bottom: 0;
                    margin-bottom: 0;
                }

                div.title {
                    float: left;
                }

                div.title a:link, div.title a:visited {
                    overflow: hidden;
                    display: block;
                    color: #333;
                }
            </style>

        </div>
    </div>

<?php
function getTitle($doc) {
    global $modx, $_style;

    $doc['class'] = _getClasses($doc);
    $tpl = '<span [+class+] oncontextmenu="document.getElementById(\'icon[+id+]\').onclick(event);return false;">[+pagetitle+]</span>';
    $title = $modx->parseText($tpl, $doc);
    if ($doc['type'] === 'reference') {
        return sprintf('<img src="%s" /> ', $_style['tree_weblink']) . $title;
    }
    return $title;
}

function getIcon($doc) {
    global $modx;

    $doc['iconpath'] = _getIconPath($doc);

    $tpl = '<img src="[+iconpath+]" id="icon[+id+]" onclick="return showContentMenu([+id+],event);" />';
    return $modx->parseText($tpl, $doc);
}

function getDescription($doc) {
    global $modx;

    $len = mb_strlen($doc['pagetitle'] . $doc['description'], $modx->config['modx_charset']);
    $tpl = '<span style="color:#777;">%s</span>';
    if ($len < 50) {
        if (!empty($doc['description'])) {
            return sprintf(' ' . $tpl, $doc['description']);
        } else {
            return '';
        }
    } else {
        return sprintf('<br />' . $tpl, $doc['description']);
    }
}

function _getClasses($doc) {
    $classes = array();
    $classes[] = 'withmenu';
    if ($doc['deleted'] === '1') {
        $classes[] = 'deletedNode';
    }
    if ($doc['has_access'] === '0') {
        $classes[] = 'protectedNode';
    }
    if ($doc['published'] === '0') {
        $classes[] = 'unpublishedNode';
    }
    return ' class="' . implode(' ', $classes) . '"';
}

function getPublishedOn($doc) {
    global $modx;

    if ($doc['publishedon']) {
        return sprintf('<span class="nowrap">%s</span>', $modx->toDateFormat($doc['publishedon']));
    }

    if ($doc['pub_date']) {
        return sprintf('<span class="nowrap disable">%s</span>', $modx->toDateFormat($doc['pub_date']));
    }

    return '-';
}

function getEditedon($editedon) {
    global $modx;

    if ($editedon) {
        return sprintf('<span class="nowrap">%s</span>', $modx->toDateFormat($editedon));
    }
    return '-';
}

function getStatusIcon($status) {
    global $modx, $_style;

    if (!$modx->config['enable_draft']) {
        return '';
    }

    $tpl = '&nbsp;<img src="%s">&nbsp;';
    switch ($status) {
        case 'draft'   :
            return sprintf($tpl, $_style['tree_draft']);
        case 'standby' :
            return sprintf($tpl, $_style['icons_date']);
        default        :
            return '';
    }
}

function getStatus($doc) {
    global $modx, $_lang;

    if (!$doc['published'] && (request_time() < $doc['pub_date'] || $doc['unpub_date'] < request_time())) {
        return $modx->parseText('<span class="unpublishedDoc">[+page_data_unpublished+]</span>', $_lang);
    }
    return $modx->parseText('<span class="publishedDoc">[+page_data_published+]</span>', $_lang);
}

function _getIconPath($doc) {
    global $modx, $_style;

    switch ($doc['id']) {
        case $modx->config('site_start')           :
            return $_style['tree_page_home'];
        case $modx->config('error_page',$modx->config('site_start'))           :
            return $_style['tree_page_404'];
        case $modx->config('unauthorized_page')    :
            return $_style['tree_page_info'];
        case $modx->config('site_unavailable_page'):
            return $_style['tree_page_hourglass'];
    }

    if (!$doc['isfolder']) {
        if ($doc['privatemgr'] == 1) {
            return $_style['tree_page_html_secure'];
        }
        return $_style['tree_page_html'];
    }
    if ($doc['privatemgr'] == 1) {
        return $_style['tree_folderopen_secure'];
    }
    return $_style['tree_folder'];
}

function get_jscript($id, $cm) {
    global $modx, $_lang, $modx_textdir;

    $contextm = $cm->getClientScriptObject();
    $textdir = $modx_textdir === 'rtl' ? '-190' : '';
    $page = (isset($_GET['page'])) ? " + '&page={$_GET['page']}'" : '';

    $block = <<< EOT
<style type="text/css">
a span.withmenu {border:1px solid transparent;}
a span.withmenu:hover {border:1px solid #ccc;background-color:#fff;}
.nowrap {white-space:nowrap;}
.disable {color:#777;}
</style>
<script type="text/javascript">
    var selectedItem;
    var contextm = {$contextm};
    function showContentMenu(id,e){
        selectedItem=id;
        //offset menu if RTL is selected
        contextm.style.left = (e.pageX || (e.clientX + (document.documentElement.scrollLeft || document.body.scrollLeft))){$textdir}+10+"px";
        contextm.style.top = (e.pageY || (e.clientY + (document.documentElement.scrollTop || document.body.scrollTop)))-150 + 'px';
        contextm.style.visibility = "visible";
        e.cancelBubble=true;
        return false;
    }

    function menuAction(a) {
        var id = selectedItem;
        switch(a) {
            case 27:        // edit
                window.location.href='index.php?a=27&id='+id;
                break;
            case 4:         // new Resource
                window.location.href='index.php?a=4&pid='+id;
                break;
            case 51:        // move
                window.location.href='index.php?a=51&id='+id{$page};
                break;
            case 94:        // duplicate
                if(confirm("{$_lang['confirm_resource_duplicate']}")==true)
                {
                    window.location.href='index.php?a=94&id='+id{$page};
                }
                break;
            case 61:        // publish
                if(confirm("{$_lang['confirm_publish']}")==true)
                {
                    window.location.href='index.php?a=61&id='+id{$page};
                }
                break;
            case 62:        // unpublish
                if (id != {$modx->config['site_start']})
                {
                    if(confirm("{$_lang['confirm_unpublish']}")==true)
                    {
                        window.location.href="index.php?a=62&id=" + id{$page};
                    }
                }
                else
                {
                    alert('Document is linked to site_start variable and cannot be unpublished!');
                }
                break;
            case 6:         // delete
                if(confirm("{$_lang['confirm_delete_resource']}")==true)
                {
                    window.location.href='index.php?a=6&id='+id{$page};
                }
                break;
            case 63:        // undelete
                if(confirm("{$_lang['confirm_undelete']}")==true)
                {
                    top.main.document.location.href="index.php?a=63&id=" + id{$page};
                }
                break;
            case 72:         // new Weblink
                window.location.href='index.php?a=72&pid='+id;
                break;
            case 3:        // view
                window.location.href='index.php?a=3&id='+id;
                break;
        }
    }
    document.addEvent('click', function(){
        contextm.style.visibility = "hidden";
    });
</script>
EOT;
    return $block;
}

function getReturnAction($current) {
    if (!isset($current['parent'])) {
        return 'index.php?a=2';
    }

    if (!manager()->isAllowed($current['parent'])) {
        return 'index.php?a=120';
    }

    if (!$current['parent']) {
        return 'index.php?a=120';
    }

    return 'index.php?a=120&id='.$current['parent'];
}

function getTopicPath($id) {
    if ($id == 0) {
        return '';
    }

    $parents = array_merge(
        array(evo()->config('site_start'))
        , array_reverse(evo()->getParentIds($id))
    );
    $parents[] = $id;

    foreach ($parents as $topic) {
        $rs = db()->select(
            "IF(alias='', id, alias) AS alias"
            , '[+prefix+]site_content'
            , "id='" . $topic . "'"
        );
        $doc = db()->getRow($rs);
        if ($topic == evo()->config('site_start')) {
            $topics[] = sprintf('<a href="index.php?a=120">%s</a>', 'Home');
        } elseif ($topic == $id) {
            $topics[] = sprintf('%s', $doc['alias']);
        } elseif (manager()->isAllowed($topic)) {
            $topics[] = sprintf('<a href="index.php?a=120&id=%s">%s</a>', $topic, $doc['alias']);
        } else {
            $topics[] = sprintf('%s', $doc['alias']);
        }
    }
    return sprintf(
        '<div style="margin-bottom:10px;">%s</div>'
        , implode(' / ', $topics)
    );
}

function getContextMenu($cm) {
    if (hasPermission('edit_document')) {
        $cm->addItem(lang('edit_resource'), "js:menuAction(27)", style('icons_edit_document'));
    }
    if (hasPermission('new_document')) {
        $cm->addItem(lang('create_resource_here'), "js:menuAction(4)", style('icons_new_document'));
    }
    if (hasPermission('move_document') && hasPermission('save_document') && hasPermission('publish_document')) {
        $cm->addItem(lang('move_resource'), "js:menuAction(51)", style('icons_move_document'));
    }
    if (hasPermission('new_document')) {
        $cm->addItem(lang('resource_duplicate'), "js:menuAction(94)", style('icons_resource_duplicate'));
    }
    if (0 < $cm->i) {
        $cm->addSeparator();
        $cm->i = 0;
    }
    if (hasPermission('publish_document')) {
        $cm->addItem(lang('publish_resource'), "js:menuAction(61)", style('icons_publish_document'));
    }
    if (hasPermission('publish_document')) {
        $cm->addItem(lang('unpublish_resource'), "js:menuAction(62)", style('icons_unpublish_resource'));
    }
    if (hasPermission('delete_document')) {
        $cm->addItem(lang('delete_resource'), "js:menuAction(6)", style('icons_delete'));
    }
    if (hasPermission('delete_document')) {
        $cm->addItem(lang('undelete_resource'), "js:menuAction(63)", style('icons_undelete_resource'));
    }
    if (0 < $cm->i) {
        $cm->addSeparator();
        $cm->i = 0;
    }
    if (hasPermission('new_document')) {
        $cm->addItem(lang('create_weblink_here'), "js:menuAction(72)", style('icons_weblink'));
    }
    if (0 < $cm->i) {
        $cm->addSeparator();
        $cm->i = 0;
    }
    if (hasPermission('view_document')) {
        $cm->addItem(lang('resource_overview'), "js:menuAction(3)", style('icons_resource_overview'));
    }
    //$cm->addItem($_lang["preview_resource"], "js:menuAction(999)",$_style['icons_preview_resource'],0);
    return $cm->render();
}