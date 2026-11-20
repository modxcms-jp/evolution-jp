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
    $rs = db()->select(
        'sc.id, sc.pagetitle,sc.description',
        '[+prefix+]site_content sc INNER JOIN [+prefix+]site_tmplvar_contentvalues stcv ON stcv.contentid=sc.id',
        where('stcv.`tmplvarid`', '=', $id)
    );
    if (db()->count($rs) > 0) {
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
        while ($row = db()->getRow($rs)) {
            echo '<li><span style="width: 200px"><a href="' . hsc(EvoFormFlow::url(['a' => EvoFormFlow::screen('document', 'edit'), 'id' => (int)$row['id']])) . '">' . hsc($row['pagetitle']) . '</a></span>' . ($row['description'] != '' ? ' - ' . hsc($row['description']) : '') . '</li>';
        }
        echo "</ul>";
        echo '</div>';
        echo '</div>';
        include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
        exit;
    }
}

include __DIR__ . '/../element/delete_element.processor.php';
