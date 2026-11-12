<?php
/** @var array $_lang
 * @var array $_style
 */
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!hasPermission('view_document')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = get_intvar('id');
if ($id && !evo()->checkPermissions($id)) {
    alert()->setError(3, lang('access_permission_denied'));
    alert()->dumpError();
}

include_once(MODX_CORE_PATH . 'controls/child_document_list_state.class.php');

// 一括の操作。ボタンは操作ごとの権限があるものだけ出し、チェックボックスと選択の部品はどれか 1 つの権限があれば出す
$canBatchMove = manager()->can('document.move');
$canBatchDelete = manager()->can('document.delete');
$canBatchPublish = manager()->can('document.publish');
$canBatch = $canBatchMove || $canBatchDelete || $canBatchPublish;

evo()->loadExtension('DocAPI');
evo()->updatePublishStatus();

if (!$id) {
    $current = [];
} else {
    $current = db()->getRow(
        db()->select('*', '[+prefix+]site_content', "id='" . $id . "'")
    );
    // 存在しない id は、リソースの表示（document_data.static.php）と同じく権限エラーの画面にする
    if (!$current) {
        alert()->setError(3);
        alert()->dumpError();
    }

    $_SESSION['itemname'] = $current['pagetitle'];

    foreach ($current as $k => $v) {
        $current[$k] = hsc($v);
    }
}

if (!isset($current['id'])) {
    $current['id'] = 0;
}

// 文書グループで絞る式（絞らない利用者・use_udperms=0 のときは空）。判定は ManagerAPI::documentAccessCondition()
$accessCond = manager()->documentAccessCondition('sc');

$keyword = ChildDocumentListState::keyword();
$keywordCondition = ChildDocumentListState::keywordCondition($keyword);
$where = [];
$where[] = "sc.parent='" . $id . "'";
// tree_show_protected は表示だけの設定。有効なら開けない文書も has_access=0 の印つきで出す
if ($accessCond !== '' && !evo()->config('tree_show_protected')) {
    $where[] = 'AND ' . $accessCond;
}
if ($keywordCondition !== null) {
    $where[] = $keywordCondition;
}
// 絞るときは、表の行と同じ has_access も取る（GROUP BY sc.id なので件数は DISTINCT sc.id と同じ）
if ($accessCond === '') {
    $countFields = 'DISTINCT sc.id';
} else {
    $countFields = sprintf('sc.id, MAX(IF(%s, 1, 0)) AS has_access', $accessCond);
    $where[] = 'GROUP BY sc.id';
}
$rs = db()->select(
    $countFields,
    "[+prefix+]site_content AS sc LEFT JOIN [+prefix+]document_groups AS dg ON dg.document = sc.id",
    $where
);
$numRecords = db()->count($rs);
// 「この一覧のすべてを選択」で使う全件の ID。件数を数えた問い合わせの結果を使い回す（DB の問い合わせは増やさない）。
// ID を data 属性に入れて、一括操作（移動の a=51・削除・公開・非公開の a=121）の batch[] はそのまま受ける形にした
// （「親の id と q」を送る方式は、受ける側で絞り込みを組み直す必要があり、一括操作と #1243 の絞り込みの両方に手が入る）。HTML の大きさは 1 件あたり数バイト。
// 表の行と同じく、一覧に見える（isContainAllowed()）ものだけを入れる。tree_show_protected=1 の絞る利用者には
// has_access=0 の子も一覧に出るが、一括操作の checkPermissions() で操作全体が止まるので、全件の ID からは外す
$allChildIds = [];
while ($row = db()->getRow($rs)) {
    if (isset($row['has_access']) && !(int) $row['has_access']) {
        continue;
    }
    if (manager()->isContainAllowed($row['id'])) {
        $allChildIds[] = (int) $row['id'];
    }
}

$links = ChildDocumentListState::links($id);

// 検索欄と、絞り込み・並べ替えの解除。ユーザー管理の一覧の検索欄と同じ作りで、表を包む POST のフォームの外に置く（フォームは入れ子にできない）
$kw = hsc($_lang['search']);
$listTools = '<form class="child-list-tools" name="childsearch" method="get" action="index.php">'
    . '<input type="hidden" name="a" value="120">'
    . ($id ? '<input type="hidden" name="id" value="' . (int) $id . '">' : '');
if ($links->orderby() !== '') {
    $listTools .= '<input type="hidden" name="orderby" value="' . hsc($links->orderby()) . '">'
        . '<input type="hidden" name="orderdir" value="' . hsc($links->orderdir()) . '">';
}
// 見出しは置かない（入力欄の placeholder と aria-label で分かる）。送るのは本物の送信ボタン（Enter でも送れる）。
// 並びは shell.css の .child-list-tools（flex）。ボタンの見た目は各テーマの .actionButtons の規則に任せる
$listTools .= '<input class="searchtext" name="q" type="text" size="15" maxlength="'
    . ChildDocumentListState::KEYWORD_MAX_LENGTH . '" value="' . hsc($keyword) . '" placeholder="'
    . hsc($_lang['resources_list_search_placeholder']) . '" aria-label="' . $kw . '">'
    . '<ul class="actionButtons"><li><button type="submit">' . $kw . '</button></li></ul>';
if ($keyword !== '') {
    $listTools .= '<a href="' . hsc($links->url(['q' => null, 'page' => null])) . '" title="'
        . hsc($_lang['resources_list_search_clear']) . '"><img src="' . $_style['icons_refresh']
        . '" alt="' . hsc($_lang['resources_list_search_clear']) . '"></a>';
}
if ($links->orderby() !== '') {
    $listTools .= '<a href="' . hsc($links->url(['orderby' => null, 'orderdir' => null, 'page' => null])) . '" title="'
        . hsc($_lang['resources_list_reset_order']) . '"><img src="' . $_style['sort']
        . '" alt="' . hsc($_lang['resources_list_reset_order']) . '"></a>';
}
$listTools .= '</form>';

// 表の上の行に出すページ送り（0 件のときは無い）
$navTop = '';

if (!$numRecords) {
    $children_output = "<p>" . ($keyword !== '' ? $_lang['resources_list_search_no_match'] : $_lang['resources_in_container_no']) . "</p>";
} else {
    $children_output = '';
    $f = [];
    $f[] = 'DISTINCT sc.*';
    if ($accessCond !== '') {
        $f['has_access'] = sprintf('MAX(IF(%s, 1, 0))', $accessCond);
    }
    $f[] = 'rev.status';
    $where = [];
    $where[] = "sc.parent='" . $id . "'";
    if ($accessCond !== '' && !evo()->config('tree_show_protected')) {
        $where[] = 'AND ' . $accessCond;
    }
    if ($keywordCondition !== null) {
        $where[] = $keywordCondition;
    }
    $where[] = 'GROUP BY sc.id,rev.status';
    $perPage = (int) evo()->config('number_of_results') ?: 20;
    $pager = new DataTablePager($numRecords, $perPage, $links->page());
    $orderBy = ChildDocumentListState::orderBy($links) ?? transformOrderBy(
        evo()->config('manager_docs_orderby') ?: 'isfolder desc, publishedon desc, editedon desc, id desc'
    );
    // 数字だけで探したときは、ID が一致した文書を 1 件目にする
    $orderBy = ChildDocumentListState::prependIdMatch($keyword, $orderBy);
    $rs = db()->select(
        $f,
        [
            '[+prefix+]site_content sc',
            'LEFT JOIN [+prefix+]document_groups dg ON dg.document=sc.id',
            "LEFT JOIN [+prefix+]site_revision rev on rev.elmid=sc.id AND (rev.status='draft' OR rev.status='pending' OR rev.status='standby') AND rev.element='resource'"
        ],
        $where,
        $orderBy,
        sprintf('%d,%d', $pager->offset(), $pager->limit())
    );
    $docs = [];
    while ($row = db()->getRow($rs)) {
        $docs[$row['id']] = $row;
    }

    $rows = [];
    $tpl = '<div class="title">[+icon+][+statusIcon+]</div><a href="[+link+]">[+title+][+description+]</a>';
    foreach ($docs as $docid => $doc) {
        if (!manager()->isContainAllowed($docid)) {
            continue;
        }

        if ($accessCond === '') {
            $doc['has_access'] = 1;
        }
        $doc = hsc($doc);

        $doc['icon'] = getIcon($doc);
        $doc['statusIcon'] = getStatusIcon($doc['status']);
        $doc['link'] = $doc['isfolder'] ? "index.php?a=120&amp;id=" . $docid : "index.php?a=27&amp;id={$docid}";
        $doc['title'] = getTitle($doc);
        $doc['description'] = getDescription($doc);

        $col = [];
        if ($canBatch) {
            $col['checkbox'] = sprintf('<input type="checkbox" name="batch[]" value="%d">', (int) $docid);
        }
        $col['docid'] = $docid;
        $col['title'] = parseText($tpl, $doc);
        $col['publishedon'] = getPublishedOn($doc);
        $col['editedon'] = getEditedon($doc['editedon']);
        $col['status'] = getStatus($doc);
        $rows[] = $col;
    }

    $columns = [];
    if ($canBatch) {
        $columns[] = ['key' => 'checkbox', 'label' => '<input type="checkbox" data-select-all="batch[]" aria-label="' . hsc(lang('select_all')) . '">', 'labelHtml' => true, 'html' => true, 'class' => 'is-shrink'];
    }
    $columns = array_merge($columns, [
        ['key' => 'docid', 'label' => $_lang['id'], 'class' => 'is-shrink', 'sort' => 'id'],
        ['key' => 'title', 'label' => $_lang['resource_title'], 'html' => true, 'sort' => 'pagetitle'],
        ['key' => 'publishedon', 'label' => $_lang['publish_date'], 'html' => true, 'sort' => 'publishedon'],
        ['key' => 'editedon', 'label' => $_lang['editedon'], 'html' => true, 'sort' => 'editedon'],
        ['key' => 'status', 'label' => $_lang['page_data_status'], 'html' => true],
    ]);
    $table = new DataTable($columns, [
        'id' => 'children',
        'tableClass' => 'grid',
        'rowClasses' => ['gridItem', 'gridAltItem'],
        'selection' => $canBatch ? [
            'name' => 'batch[]',
            'ids' => $allChildIds,
            // 全件を batch[] の hidden で送ると max_input_vars を超えたぶんを PHP が黙って切り捨てる。
            // 上限は ini の値から、フォームのほかの値（parent・csrf_token・送信ボタンなど）の余裕 50 を引いた数
            'max' => max(0, (int) ini_get('max_input_vars') - 50),
            'labels' => [
                'selected' => lang('data_table_selected'),
                'page' => lang('data_table_selected_page'),
                'all' => lang('data_table_select_all_in_list'),
                'allSelected' => lang('data_table_selected_all_in_list'),
                'clear' => lang('data_table_clear_selection'),
            ],
        ] : null,
    ]);
    $nav = $pager->render($links, [
        'label' => lang('pagination_label'),
        'first' => lang('pagination_table_first'),
        'last' => lang('pagination_table_last'),
    ]);
    // 上のページ送りは表の上の行（作成のボタンの右）に出し、下は右に寄せる
    $navTop = $nav;
    $children_output = $table->render($rows, $links)
        . ($nav !== '' ? '<div class="resources-list-pager-bottom">' . $nav . '</div>' : '');
    if ($canBatch) {
        // ボタンごとの送り先は formaction（フォームに hidden の a を置くと、a の値が衝突する）。
        // 操作の種類は URL の op で渡し、今のページ・並べ替え・検索語（q）も同じ URL で引き継ぐ。削除は確認のダイアログを出す
        $returnQuery = ChildDocumentListState::returnQuery();
        // ボタンは選択件数の帯の隣に出す（data-table.js が data-selection-actions の欄を帯へ移す）。
        // 帯に「n 件を選択」と出るので、ボタンは短い文言にし、元の長い文言は title に残す
        $button = function ($label, $title, $a, $op = '', $confirm = '', $class = '') use ($returnQuery) {
            $url = 'index.php?a=' . $a . ($op !== '' ? '&op=' . $op . $returnQuery : '');
            // 見た目は各テーマの .actionButtons の規則に任せる（リンクにはできないので button で formaction を使う）
            return sprintf(
                '<li%s><button type="submit" title="%s" formaction="%s"%s>%s</button></li>',
                $class !== '' ? ' class="' . hsc($class) . '"' : '',
                hsc($title),
                hsc($url),
                $confirm !== '' ? ' onclick="' . hsc('return confirm(' . js_literal(stripcslashes($confirm)) . ');') . '"' : '',
                hsc($label)
            );
        };
        $buttons = '';
        if ($canBatchMove) {
            $buttons .= $button($_lang['move'], $_lang['resources_list_move_checked'], 51);
        }
        if ($canBatchPublish) {
            $buttons .= $button($_lang['publish_resource'], $_lang['resources_list_publish_checked'], 121, 'publish');
            $buttons .= $button($_lang['unpublish_resource'], $_lang['resources_list_unpublish_checked'], 121, 'unpublish');
        }
        if ($canBatchDelete) {
            $buttons .= $button($_lang['delete'], $_lang['resources_list_delete_checked'], 121, 'delete', $_lang['resources_list_confirm_delete_checked'], 'is-danger');
        }
        $children_output .= '<div class="resources-batch-actions" data-selection-actions="batch[]"><ul class="actionButtons">' . $buttons . '</ul></div>';
    }
}

// context menu
include_once(MODX_CORE_PATH . 'controls/contextmenu.php');
$cm = new ContextMenu('cntxm', 180);
echo getContextMenu($cm);

echo get_jscript($id, $cm);

?>
    <script type="text/javascript" src="<?= manager_asset('media/script/data-table.js') ?>"></script>
    <h1><?= $_lang['view_child_resources_in_container'] ?></h1>

    <div id="actions">
        <ul class="actionButtons">
            <?php
            $tpl = '<li id="%s" class="mutate"><a href="#" onclick="%s">%s</a></li>';
            if (manager()->can('document.edit') && $id != 0 && manager()->isInAllowedParents($id)) {
                echo sprintf($tpl, 'Button1', 'editdocument();', $_lang['edit']);
            }
            if (manager()->can('document.move') && $id != 0 && manager()->isInAllowedParents($id)) {
                echo sprintf($tpl, 'Button2', 'movedocument();', $_lang['move']);
            }
            if (manager()->can('document.duplicate') && $id != 0 && manager()->isInAllowedParents($id)) {
                echo sprintf($tpl, 'Button4', 'duplicatedocument();', $_lang['duplicate']);
            }
            if (manager()->can('document.delete') && $id != 0 && manager()->isInAllowedParents($id)) {
                echo sprintf($tpl, 'Button3', 'deletedocument();', $_lang['delete']);
            }

            $prev = sprintf("window.open(%s,'previeWin')", hsc(js_literal(evo()->makeUrl($id))));
            echo sprintf($tpl, 'Button6', $prev,
                $id == 0 ? $_lang["view_site"] : $_lang['view_resource']);
            $action = "EvoShell.go('" . getReturnAction($current) . "', {discard: true});";
            echo sprintf($tpl, 'Button5', $action, $_lang['cancel']);
            ?>
        </ul>
    </div>

    <div class="section">
        <div class="sectionBody">
            <!-- View Children -->
            <?php
            // 1 行目: パンくず（今どのフォルダか。0 件でも出す）と検索。検索は別のフォームなので、表のフォームの外に置く
            echo '<div class="resources-list-head">' . getTopicPath($id) . $listTools . '</div>';
            // チェックボックスなどでの Enter では送らない（既定の送信ボタンが無く、先頭の有効なボタン＝移動や公開が押されたことになるため）。
            // フォームの属性に置くので、シェルの画面遷移で要素が作り直されても重ならない。ボタン・リンクの Enter は通常どおり
            $enterGuard = hsc("if (event.key === 'Enter' && !event.target.closest('button, a, textarea, input[type=submit], input[type=button]')) { event.preventDefault(); }");
            // 2 行目: 作成のボタン（表に子を足す操作）とページ送り。0 件でも作成のボタンは出す
            $createButtons = '';
            if (manager()->canCreateDocumentUnder($id)) {
                $createButtons = sprintf(
                    '<ul class="actionButtons resources-list-create">'
                    . '<li class="mutate"><a href="index.php?a=4&amp;pid=%1$d">%2$s</a></li>'
                    . '<li class="mutate"><a href="index.php?a=72&amp;pid=%1$d">%3$s</a></li>'
                    . '</ul>',
                    $id,
                    $_lang['create_resource_here'],
                    $_lang['create_weblink_here']
                );
            }
            // 中身が無いとき（作成の権限が無く、ページ送りも無い）は出さない
            $toolbar = ($createButtons . $navTop) !== '' ? '<div class="resources-list-toolbar">' . $createButtons . $navTop . '</div>' : '';
            echo <<< EOT
<form name="mutate" id="mutate" class="content" method="post" enctype="multipart/form-data" action="index.php" onkeydown="{$enterGuard}">
<input type="hidden" name="parent" value="{$id}" />
{$toolbar}
{$children_output}
</form>
EOT;
            ?>
            <style type="text/css">
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
function getTitle($doc)
{
    global $_style;

    $doc['class'] = _getClasses($doc);
    $tpl = '<span [+class+] data-row-menu="[+id+]">[+pagetitle+]</span>';
    $title = parseText($tpl, $doc);
    if ($doc['type'] === 'reference') {
        return sprintf('<img src="%s" /> ', $_style['tree_weblink']) . $title;
    }
    return $title;
}

function getIcon($doc)
{
    $doc['iconpath'] = _getIconPath($doc);

    $tpl = '<img src="[+iconpath+]" data-row-menu="[+id+]" />';
    return parseText($tpl, $doc);
}

function getDescription($doc)
{
    $len = mb_strlen($doc['pagetitle'] . $doc['description'], evo()->config('modx_charset'));
    $tpl = '<span class="resource-description">%s</span>';
    if ($len < 50) {
        if (empty($doc['description'])) {
            return '';
        }
        return sprintf(' ' . $tpl, $doc['description']);
    }
    return sprintf('<br />' . $tpl, $doc['description']);
}

function _getClasses($doc)
{
    $classes = [];
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

function getPublishedOn($doc)
{
    global $modx;

    if ($doc['publishedon']) {
        return sprintf('<span class="nowrap">%s</span>', evo()->toDateFormat($doc['publishedon']));
    }

    if ($doc['pub_date']) {
        return sprintf('<span class="nowrap disable">%s</span>', evo()->toDateFormat($doc['pub_date']));
    }

    return '-';
}

function getEditedon($editedon)
{
    global $modx;

    if ($editedon) {
        return sprintf('<span class="nowrap">%s</span>', evo()->toDateFormat($editedon));
    }
    return '-';
}

function getStatusIcon($status)
{
    global $_style;

    if (!evo()->config['enable_draft']) {
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

function getStatus($doc)
{
    global $_lang;

    if (!$doc['published'] && (request_time() < $doc['pub_date'] || $doc['unpub_date'] < request_time())) {
        return parseText('<span class="unpublishedDoc">[+page_data_unpublished+]</span>', $_lang);
    }
    return parseText('<span class="publishedDoc">[+page_data_published+]</span>', $_lang);
}

function _getIconPath($doc)
{
    global $_style;

    switch ($doc['id']) {
        case evo()->config('site_start')           :
            return $_style['tree_page_home'];
        case evo()->config('error_page', evo()->config('site_start'))           :
            return $_style['tree_page_404'];
        case evo()->config('unauthorized_page')    :
            return $_style['tree_page_info'];
        case evo()->config('site_unavailable_page'):
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

function get_jscript($id, $cm)
{
    global $modx_textdir;

    $contextm = $cm->getClientScriptObject();
    $textdir = $modx_textdir === 'rtl' ? '-190' : '';
    // メニューの操作後の戻り先に、今のページ・並べ替え・検索語（q）を残す
    $returnQuery = ChildDocumentListState::returnQuery();
    $page = $returnQuery !== '' ? ' + ' . js_literal($returnQuery) : '';

    $siteStart = (int) evo()->config('site_start');

    $js_confirm_resource_duplicate = js_literal(stripcslashes(lang('confirm_resource_duplicate')));
    $js_confirm_delete_resource = js_literal(stripcslashes(lang('confirm_delete_resource')));
    $js_confirm_publish = js_literal(stripcslashes(lang('confirm_publish')));
    $js_confirm_unpublish = js_literal(stripcslashes(lang('confirm_unpublish')));
    $js_confirm_undelete = js_literal(stripcslashes(lang('confirm_undelete')));

    $block = <<< EOT
<style type="text/css">
a span.withmenu {border:1px solid transparent;}
a span.withmenu:hover {border:1px solid #ccc;background-color:#fff;}
.nowrap {white-space:nowrap;}
.disable {color:#777;}
</style>
<script type="text/javascript">
    function duplicatedocument() {
        if (confirm({$js_confirm_resource_duplicate}) == true) {
            EvoCsrf.post("index.php?id={$id}&a=94");
        }
    }
    function deletedocument() {
        if (confirm({$js_confirm_delete_resource}) == true) {
            EvoCsrf.post("index.php?id={$id}&a=6");
        }
    }
    function editdocument() {
        EvoShell.go("index.php?id={$id}&a=27");
    }
    function movedocument() {
        EvoShell.go("index.php?id={$id}&a=51");
    }

    // AJAXで再訪した際にスクリプトが再実行されるため、再宣言可能なvarを使う
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
        let id = selectedItem;
        const gotoUrl = EvoShell.go;
        switch(a) {
            case 27:        // edit
                gotoUrl('index.php?a=27&id='+id);
                break;
            case 4:         // new Resource
                gotoUrl('index.php?a=4&pid='+id);
                break;
            case 51:        // move
                gotoUrl('index.php?a=51&id='+id{$page});
                break;
            case 94:        // duplicate
                if(confirm({$js_confirm_resource_duplicate})==true)
                {
                    EvoCsrf.post('index.php?a=94&id='+id{$page});
                }
                break;
            case 61:        // publish
                if(confirm({$js_confirm_publish})==true)
                {
                    EvoCsrf.post('index.php?a=61&id='+id{$page});
                }
                break;
            case 62:        // unpublish
                if (id != {$siteStart})
                {
                    if(confirm({$js_confirm_unpublish})==true)
                    {
                        EvoCsrf.post("index.php?a=62&id=" + id{$page});
                    }
                }
                else
                {
                    alert('Document is linked to site_start variable and cannot be unpublished!');
                }
                break;
            case 6:         // delete
                if(confirm({$js_confirm_delete_resource})==true)
                {
                    EvoCsrf.post('index.php?a=6&id='+id{$page});
                }
                break;
            case 63:        // undelete
                if(confirm({$js_confirm_undelete})==true)
                {
                    EvoCsrf.post("index.php?a=63&id=" + id{$page});
                }
                break;
            case 72:         // new Weblink
                gotoUrl('index.php?a=72&pid='+id);
                break;
            case 3:        // view
                gotoUrl('index.php?a=3&id='+id);
                break;
        }
    }
    // シェルの遷移で積み重ならないよう 1 回だけ登録する（contextm は実行時に最新の var を読む）
    if (!window.evoContextMenuClose) {
        window.evoContextMenuClose = true;
        document.addEventListener('click', function(){
            contextm.style.visibility = "hidden";
        });
    }

    // 表は画面の差し替えで消える。<script> を表の後ろに置かず、Evo.ready で表ができてから登録する
    Evo.ready(function () {
        const table = document.querySelector('[data-table="children"]');
        if (!table) {
            return;
        }
        table.addEventListener('click', function (e) {
            const el = e.target.closest('[data-row-menu]');
            // タイトルのクリックはリンクとして動かす。メニューはアイコンのクリックで開く
            if (el && el.tagName === 'IMG') {
                showContentMenu(Number(el.dataset.rowMenu), e);
                e.preventDefault();
            }
        });
        table.addEventListener('contextmenu', function (e) {
            const el = e.target.closest('[data-row-menu]');
            if (el) {
                showContentMenu(Number(el.dataset.rowMenu), e);
                e.preventDefault();
            }
        });
    });
</script>
EOT;
    return $block;
}

function getReturnAction($current)
{
    if (!isset($current['parent'])) {
        return 'index.php?a=2';
    }

    if (!manager()->isInAllowedParents($current['parent'])) {
        return 'index.php?a=120';
    }

    if (!$current['parent']) {
        return 'index.php?a=120';
    }

    return 'index.php?a=120&id=' . $current['parent'];
}

function getTopicPath($id)
{
    if ($id == 0) {
        return '';
    }

    $parents = array_merge(
        [evo()->config('site_start')],
        array_reverse(evo()->getParentIds($id))
    );
    $parents[] = $id;

    foreach ($parents as $topic) {
        $rs = db()->select(
            "IF(alias='', id, alias) AS alias",
            '[+prefix+]site_content',
            "id='" . $topic . "'"
        );
        $doc = db()->getRow($rs);
        if ($topic == evo()->config('site_start')) {
            $topics[] = sprintf('<a href="index.php?a=120">%s</a>', 'Home');
            continue;
        }
        if ($topic == $id) {
            $topics[] = hsc($doc['alias']);
            continue;
        }
        if (!evo()->checkPermissions($topic)) {
            // ツリーと同じく、アクセスできない文書は tree_show_protected が有効なら表示のみ（リンクなし）、無効なら出さない
            $topics[] = evo()->config('tree_show_protected') ? hsc($doc['alias']) : '…';
        } elseif (manager()->isInAllowedParents($topic)) {
            $topics[] = sprintf('<a href="index.php?a=120&id=%d">%s</a>', (int)$topic, hsc($doc['alias']));
        } else {
            $topics[] = hsc($doc['alias']);
        }
    }
    return sprintf(
        '<div class="resources-topic-path">%s</div>',
        implode(' / ', $topics)
    );
}

function getContextMenu($cm)
{
    if (manager()->can('document.edit')) {
        $cm->addItem(lang('edit_resource'), "js:menuAction(27)", style('icons_edit_document'));
    }
    if (manager()->can('document.create')) {
        $cm->addItem(lang('create_resource_here'), "js:menuAction(4)", style('icons_new_document'));
    }
    if (manager()->can('document.move')) {
        $cm->addItem(lang('move_resource'), "js:menuAction(51)", style('icons_move_document'));
    }
    if (manager()->can('document.duplicate')) {
        $cm->addItem(lang('resource_duplicate'), "js:menuAction(94)", style('icons_resource_duplicate'));
    }
    if (0 < $cm->i) {
        $cm->addSeparator();
        $cm->i = 0;
    }
    if (manager()->can('document.publish')) {
        $cm->addItem(lang('publish_resource'), "js:menuAction(61)", style('icons_publish_document'));
    }
    if (manager()->can('document.publish')) {
        $cm->addItem(lang('unpublish_resource'), "js:menuAction(62)", style('icons_unpublish_resource'));
    }
    if (manager()->can('document.delete')) {
        $cm->addItem(lang('delete_resource'), "js:menuAction(6)", style('icons_delete'));
    }
    if (manager()->can('document.delete')) {
        $cm->addItem(lang('undelete_resource'), "js:menuAction(63)", style('icons_undelete_resource'));
    }
    if (0 < $cm->i) {
        $cm->addSeparator();
        $cm->i = 0;
    }
    if (manager()->can('document.create')) {
        $cm->addItem(lang('create_weblink_here'), "js:menuAction(72)", style('icons_weblink'));
    }
    if (0 < $cm->i) {
        $cm->addSeparator();
        $cm->i = 0;
    }
    if (hasPermission('view_document')) {
        $cm->addItem(lang('resource_overview'), "js:menuAction(3)", style('icons_resource_overview'));
    }
    return $cm->render();
}

// "field [asc|desc], ..." => "sc.field DESC, ..."; unknown fields are skipped (TVs can not be used)
function transformOrderBy($input) {
    $meta = db()->getTableMetaData(evo()->getFullTableName('site_content'));
    $columns = $meta ? array_keys($meta) : [];
    $parts = [];
    foreach (explode(',', $input) as $part) {
        $words = preg_split('/\s+/', trim($part));
        if (!in_array($words[0], $columns, true)) {
            continue;
        }
        $dir = strtoupper($words[1] ?? '') === 'ASC' ? 'ASC' : 'DESC';
        $parts[] = sprintf('sc.%s %s', $words[0], $dir);
    }
    return $parts ? implode(', ', $parts) : 'sc.isfolder DESC, sc.publishedon DESC, sc.editedon DESC, sc.id DESC';
}
