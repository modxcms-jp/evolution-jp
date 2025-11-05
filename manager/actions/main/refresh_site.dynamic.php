<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}

$now = $_SERVER['REQUEST_TIME'];

$where = "pub_date < {$now} AND pub_date!=0 AND published=0 AND ({$now} < unpub_date or unpub_date=0)";
$rs = db()->update(['published' => '1'], '[+prefix+]site_content', $where);
$num_rows_pub = db()->getAffectedRows();

$where = "unpub_date < {$now} AND unpub_date!=0 AND published=1";
$rs = db()->update(['published' => '0'], '[+prefix+]site_content', $where);
$num_rows_unpub = db()->getAffectedRows();

?>

<script type="text/javascript">
    doRefresh(1);
</script>
<h1><?= $_lang['refresh_title'] ?></h1>
<div class="section">
    <div class="sectionBody">
        <?php

        if (0 < $num_rows_pub) {
            printf('<p>' . $_lang["refresh_published"] . '</p>', $num_rows_pub);
        }
        if (0 < $num_rows_unpub) {
            printf('<p>' . $_lang["refresh_unpublished"] . '</p>', $num_rows_unpub);
        }

        $modx->clearCache(['showReport' => true]);

        // invoke OnSiteRefresh event
        evo()->invokeEvent("OnSiteRefresh");

        ?>
        <div>
            <ul class="actionButtons">
                <li id="Button5"><a href="#" onclick="documentDirty=false;document.location.href='index.php?a=2';"><img
                            alt="icons_cancel"
                            src="<?= $_style["icons_save"] ?>"/> <?= $_lang['close'] ?></a></li>
            </ul>
        </div>
    </div>
</div>
