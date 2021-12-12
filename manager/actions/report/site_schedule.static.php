<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('view_schedule')) {
    alert()->setError(3);
    alert()->dumpError();
}
?>
<script type="text/javascript" src="media/script/tablesort.js"></script>
<h1><?php echo lang('site_schedule') ?></h1>
<div id="actions">
    <ul class="actionButtons">
        <li
            id="Button5"
            class="mutate">
            <a
                href="#"
                onclick="documentDirty=false;document.location.href='index.php?a=2';"
            >
                <img
                    alt="icons_cancel"
                    src="<?php echo style('icons_cancel') ?>"
                /> <?php echo lang('cancel') ?>
            </a>
        </li>
    </ul>
</div>

<div class="section">
    <div class="sectionHeader"><?php echo lang("publish_events") ?></div>
    <div class="sectionBody" id="lyr1">
        <?php
        $rs = db()->select(
            'id, pagetitle, pub_date'
            , '[+prefix+]site_content'
            , 'pub_date > ' . request_time()
            , 'pub_date ASC'
        );
        $total = db()->count($rs);
        if ($total < 1) {
            echo "<p>" . lang("no_docs_pending_publishing") . "</p>";
        } else {
            ?>
            <table
                border="0"
                cellpadding="2"
                cellspacing="0"
                class="sortabletable sortable-onload-3 rowstyle-even"
                id="table-1"
                width="100%"
            >
                <thead>
                <tr bgcolor="#CCCCCC">
                    <th class="sortable"><b><?php echo lang('id'); ?></b></th>
                    <th class="sortable"><b><?php echo lang('resource'); ?></b></th>
                    <th class="sortable"><b><?php echo lang('publish_date'); ?></b></th>
                </tr>
                </thead>
                <tbody>
                <?php
                while ($row = db()->getRow($rs)) {
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><a href="index.php?a=3&id=<?php echo $row['id']; ?>"><?php echo $row['pagetitle'] ?></a>
                        </td>
                        <td><?php echo $modx->toDateFormat($row['pub_date'] + config('server_offset_time', 0)) ?></td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
            <?php
        }
        ?>
    </div>
</div>

<div class="section">
    <div class="sectionHeader"><?php echo lang("unpublish_events"); ?></div>
    <div class="sectionBody" id="lyr2"><?php
        //$db->debug = true;
        $rs = db()->select(
            'id, pagetitle, unpub_date'
            , '[+prefix+]site_content'
            , 'unpub_date > ' . request_time()
            , 'unpub_date ASC'
        );
        $total = db()->count($rs);
        if ($total < 1) {
            echo "<p>" . lang("no_docs_pending_unpublishing") . "</p>";
        } else {
            ?>
            <table border="0" cellpadding="2" cellspacing="0" class="sortabletable sortable-onload-3 rowstyle-even"
                   id="table-2" width="100%">
                <thead>
                <tr bgcolor="#CCCCCC">
                    <th class="sortable"><b><?php echo lang('id'); ?></b></th>
                    <th class="sortable"><b><?php echo lang('resource'); ?></b></th>
                    <th class="sortable"><b><?php echo lang('unpublish_date'); ?></b></th>
                </tr>
                </thead>
                <tbody>
                <?php
                while ($row = db()->getRow($rs)) {
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><a href="index.php?a=3&id=<?php echo $row['id']; ?>"><?php echo $row['pagetitle']; ?></a>
                        </td>
                        <td><?php echo $modx->toDateFormat($row['unpub_date'] + config('server_offset_time', 0)); ?></td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
            <?php
        }
        ?>
    </div>
</div>

<div class="section">
    <div class="sectionHeader">更新を予定している下書きリソースの一覧</div>
    <div class="sectionBody" id="lyr2"><?php
        //$db->debug = true;
        $rs = db()->select(
            'rv.*, sc.*, rv.pub_date AS pub_date'
            , array(
                '[+prefix+]site_revision rv',
                'INNER JOIN [+prefix+]site_content sc ON rv.elmid=sc.id'
            )
            , "0<rv.pub_date AND rv.status='standby' "
            , 'rv.pub_date ASC'
        );
        $total = db()->count($rs);
        if ($total < 1) {
            echo "<p>更新を予定している下書きリソースはありません。</p>";
        } else {
            ?>
            <table border="0" cellpadding="2" cellspacing="0" class="sortabletable sortable-onload-2 rowstyle-even"
                   id="table-2" width="100%">
                <thead>
                <tr bgcolor="#CCCCCC">
                    <th class="sortable"><b><?php echo lang('id'); ?></b></th>
                    <th class="sortable"><b><?php echo lang('resource'); ?></b></th>
                    <th class="sortable">更新予約日時</th>
                    <th class="sortable">操作</th>
                </tr>
                </thead>
                <tbody>
                <?php
                while ($row = db()->getRow($rs)) {
                    ?>
                    <tr>
                        <td><?php echo $row['elmid']; ?></td>
                        <td>
                            <a
                                href="<?php echo 'index.php?a=131&id=' . $row['elmid']; ?>"
                            >
                                <?php echo $row['pagetitle']; ?>
                            </a>
                        </td>
                        <td><?php echo $modx->toDateFormat($row['pub_date'] + config('server_offset_time', 0)); ?></td>
                        <td>
                            <a
                                href="<?php echo sprintf(
                                    '%s?revision=%s'
                                    , evo()->makeUrl($row['elmid'])
                                    , $row['version']
                                ); ?>"
                                target="_blank"
                            >
                                プレビュー
                            </a>
                            /
                            <a
                                href="index.php?a=134&id=<?php echo $row['elmid']; ?>&back=publist"
                                class="unpub_draft">公開取り消し</a>
                        </td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>
            <script type="text/javascript">
                jQuery('.unpub_draft').on('click', function () {
                    if (!confirm('公開設定を取り消してもよろしいですか？')) {
                        return false;
                    }
                });
            </script>
            <?php
        }
        ?>
    </div>
</div>
