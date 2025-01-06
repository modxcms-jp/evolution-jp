<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('delete_template')) {
    alert()->setError(3);
    alert()->dumpError();
}
$id = preg_match('@^[0-9]+$@', getv('id')) ? getv('id') : 0;
$forced = getv('force', 0);

$tbl_site_content = evo()->getFullTableName('site_content');
$tbl_site_tmplvar_contentvalues = evo()->getFullTableName('site_tmplvar_contentvalues');
$tbl_site_tmplvars = evo()->getFullTableName('site_tmplvars');
$tbl_site_tmplvar_templates = evo()->getFullTableName('site_tmplvar_templates');
$tbl_site_tmplvar_access = evo()->getFullTableName('site_tmplvar_access');

// check for relations
if (!$forced) {
    $field = 'sc.id, sc.pagetitle,sc.description';
    $from = "{$tbl_site_content} sc INNER JOIN {$tbl_site_tmplvar_contentvalues} stcv ON stcv.contentid=sc.id";
    $where = "stcv.tmplvarid='{$id}'";
    $rs = db()->select($field, $from, $where);
    $count = db()->count($rs);
    if ($count > 0) {
        include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');
        ?>
        <script type="text/javascript">
            function deletedocument() {
                document.location.href = "index.php?id=<?= $id;?>&a=303&force=1";
            }
        </script>
        <h1><?= $_lang['tmplvars'] ?></h1>

        <div id="actions">
            <ul class="actionButtons">
                <li><a href="#" onclick="deletedocument();"><img
                            src="<?= $_style["icons_delete"] ?>"/> <?= $_lang["delete"] ?></a></li>
                <li class="mutate"><a href="index.php?a=301&id=<?= $id ?>"><img
                            src="<?= $_style["icons_cancel"] ?>"/> <?= $_lang["cancel"] ?></a></li>
            </ul>
        </div>

        <div class="section">
            <div class="sectionHeader"><?= $_lang['tmplvars'] ?></div>
            <div class="sectionBody">
        <?php
        echo "<p>" . $_lang['tmplvar_inuse'] . "</p>";
        echo "<ul>";
        while ($row = db()->getRow($rs)) {
            echo '<li><span style="width: 200px"><a href="index.php?id=' . $row['id'] . '&a=27">' . $row['pagetitle'] . '</a></span>' . ($row['description'] != '' ? ' - ' . $row['description'] : '') . '</li>';
        }
        echo "</ul>";
        echo '</div>';
        echo '</div>';
        include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
        exit;
    }
}

// invoke OnBeforeTVFormDelete event
$tmp = array("id" => $id);
evo()->invokeEvent("OnBeforeTVFormDelete", $tmp);

// delete variable
$rs = db()->delete($tbl_site_tmplvars, "id='{$id}'");
if (!$rs) {
    echo "Something went wrong while trying to delete the field...";
    exit;
}

header("Location: index.php?a=76");

// delete variable's content values
db()->delete($tbl_site_tmplvar_contentvalues, "tmplvarid='{$id}'");

// delete variable's template access
db()->delete($tbl_site_tmplvar_templates, "tmplvarid='{$id}'");

// delete variable's access permissions
db()->delete($tbl_site_tmplvar_access, "tmplvarid='{$id}'");

// invoke OnTVFormDelete event
$tmp = array("id" => $id);
evo()->invokeEvent("OnTVFormDelete", $tmp);
