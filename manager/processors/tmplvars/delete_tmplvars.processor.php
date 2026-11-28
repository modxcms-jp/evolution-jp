<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission(EvoElementTypes::get('tmplvar')['permissions']['delete'])) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = get_intvar('id');
if (!$id) {
    alert()->setError(2);
    alert()->dumpError();
}

if (!get_boolvar('force')) {
    $field = 'sc.id, sc.pagetitle,sc.description';
    $from = '[+prefix+]site_content sc INNER JOIN [+prefix+]site_tmplvar_contentvalues stcv ON stcv.contentid=sc.id';
    $where = sprintf('stcv.tmplvarid=%d', $id);
    $rs = db()->select($field, $from, $where);
    $count = db()->count($rs);
    if ($count > 0) {
        // 使用中かの判定（$count）は全件で行い、一覧の行だけ開ける文書に絞る
        $accessCond = manager()->documentAccessCondition('sc');
        if ($accessCond !== '') {
            $rs = db()->select($field, $from, "{$where} AND {$accessCond}");
        }
        include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
        ?>
        <script type="text/javascript">
            function deletedocument() {
                EvoCsrf.post("index.php?id=<?= $id;?>&a=303&force=1");
            }
        </script>
        <h1><?= lang('tmplvars') ?></h1>

        <div id="actions">
            <ul class="actionButtons">
                <li><a href="#" onclick="deletedocument();"><?= lang("delete") ?></a></li>
                <li class="mutate"><a href="<?= hsc(EvoFormFlow::url(['a' => EvoFormFlow::screen('tmplvar', 'edit'), 'id' => $id])) ?>"><?= lang("cancel") ?></a></li>
            </ul>
        </div>

        <div class="section">
            <div class="sectionHeader"><?= lang('tmplvars') ?></div>
            <div class="sectionBody">
        <?php
        echo "<p>" . lang('tmplvar_inuse') . "</p>";
        echo "<ul>";
        $shown = 0;
        while ($row = db()->getRow($rs)) {
            $shown++;
            echo '<li><span style="width: 200px"><a href="' . hsc(EvoFormFlow::url(['a' => EvoFormFlow::screen('document', 'edit'), 'id' => (int)$row['id']])) . '">' . hsc($row['pagetitle']) . '</a></span>' . ($row['description'] != '' ? ' - ' . hsc($row['description']) : '') . '</li>';
        }
        echo "</ul>";
        if ($count > $shown) {
            echo '<p>' . hsc(sprintf(lang('resources_hidden_by_permission'), $count - $shown)) . '</p>';
        }
        echo '</div>';
        echo '</div>';
        include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
        exit;
    }
}

include __DIR__ . '/../element/delete_element.processor.php';
