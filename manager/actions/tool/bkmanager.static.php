<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('bk_manager')) {
    alert()->setError(3);
    alert()->dumpError();
}
// 復元は DB を丸ごと書き換えるため、管理者だけに許す
$canRestore = manager()->isAdmin();

require_once MODX_CORE_PATH . 'backup_manager.class.inc.php';
$backup = new EvoBackupManager();

if (empty($isPaneRequest)) { // シェルの断片要求ではheader/footerを重複出力しない
    include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');  // start normal header
}
$ph['result_msg'] = '';
$ph['snapshot_msg'] = '';
$restoreHtml = '';
$snapshotProblem = $backup->store()->problem();
$snapshotPathUnusable = sprintf(
    '<div class="errmsg">%s</div>',
    evo()->parseText(lang('bkmgr_snapshot_path_unusable'), ['snapshot_path' => hsc($backup->store()->dir())])
);
$uploadLimits = array_filter([
    ini_size_to_bytes(ini_get('upload_max_filesize')),
    ini_size_to_bytes(ini_get('post_max_size')),
], function ($bytes) {
    return $bytes > 0;
});
$uploadMax = $uploadLimits ? min($uploadLimits) : 0;
$restoreTooLarge = evo()->parseText(
    lang('bkmgr_restore_too_large'),
    [
        'upload_max_filesize' => hsc((string) ini_get('upload_max_filesize')),
        'post_max_size'       => hsc((string) ini_get('post_max_size')),
    ]
);
if (sessionv('result_msg')) {
    $restoreResult = sessionv('restore_result', []);
    $restoreResult = is_array($restoreResult) ? $restoreResult : [];
    $preRestore = hsc($restoreResult['pre_restore'] ?? '');
    switch (sessionv('result_msg')) {
        case 'import_ok':
            $restoreHtml = sprintf('<div class="okmsg">%s</div>', lang('bkmgr_import_ok'));
            break;
        case 'restore_warn':
            // 復元は成功したが、設定のテーブルが無くキャッシュを作り直さなかった
            $restoreHtml = sprintf(
                '<div class="okmsg">%s</div><div class="errmsg">%s</div>',
                lang('bkmgr_import_ok'),
                evo()->parseText(
                    lang('bkmgr_restore_warn'),
                    ['pre_restore' => $preRestore]
                )
            );
            break;
        case 'restore_failed':
            $restoreHtml = sprintf(
                '<div class="errmsg">%s</div>',
                evo()->parseText(
                    lang('bkmgr_restore_failed'),
                    [
                        'failed_at'   => (int) ($restoreResult['failed_at'] ?? 0),
                        'error'       => hsc($restoreResult['error'] ?? ''),
                        'pre_restore' => $preRestore,
                    ]
                )
            );
            break;
        case 'restore_no_snapshot':
            $restoreHtml = sprintf('<div class="errmsg">%s</div>', lang('bkmgr_restore_no_snapshot'));
            break;
        case 'restore_no_file':
            $restoreHtml = sprintf('<div class="errmsg">%s</div>', lang('bkmgr_restore_no_file'));
            break;
        case 'restore_too_large':
            $restoreHtml = sprintf('<div class="errmsg">%s</div>', $restoreTooLarge);
            break;
        case 'restore_upload_failed':
            $restoreHtml = sprintf('<div class="errmsg">%s</div>', lang('bkmgr_restore_upload_failed'));
            break;
        case 'restore_has_delimiter':
            $restoreHtml = sprintf('<div class="errmsg">%s</div>', lang('bkmgr_restore_has_delimiter'));
            break;
        case 'restore_prefix_mismatch':
            $foreignTables = array_map('strval', (array) ($restoreResult['foreign_tables'] ?? []));
            $restoreHtml = sprintf(
                '<div class="errmsg">%s</div>',
                evo()->parseText(lang('bkmgr_restore_prefix_mismatch'), [
                    // ダンプから来た名前なので、parseText() の置き換えのタグとして読まれないようにする
                    'dump_prefix' => hsc_for_tpl(isset($restoreResult['dump_prefix']) ? "'{$restoreResult['dump_prefix']}'" : '?'),
                    'site_prefix' => hsc_for_tpl("'" . db()->table_prefix . "'"),
                    'tables'      => hsc_for_tpl(implode(', ', array_slice($foreignTables, 0, 5)) . (count($foreignTables) > 5 ? ', …' : '')),
                ])
            );
            break;
        case 'restore_invalid_file':
            $restoreHtml = sprintf('<div class="errmsg">%s</div>', lang('bkmgr_restore_invalid_file'));
            break;
        case 'snapshot_ok':
            $ph['snapshot_msg'] = sprintf('<div class="okmsg">%s</div>', lang('bkmgr_snapshot_ok'));
            break;
        case 'snapshot_invalid_file_name':
            $ph['snapshot_msg'] = sprintf('<div class="errmsg">%s</div>', lang('bkmgr_invalid_file_name'));
            break;
        case 'snapshot_mkdir_failed':
            $ph['snapshot_msg'] = sprintf(
                '<div class="errmsg">%s</div>',
                evo()->parseText(lang('bkmgr_alert_mkdir'), ['snapshot_path' => hsc($backup->store()->dir())])
            );
            break;
        case 'snapshot_failed':
            $ph['snapshot_msg'] = sprintf('<div class="errmsg">%s</div>', lang('bkmgr_snapshot_failed'));
            break;
        case 'snapshot_path_unusable':
            $ph['snapshot_msg'] = $snapshotPathUnusable;
            break;
        case 'restore_path_unusable':
            $restoreHtml = $snapshotPathUnusable;
            break;
    }
    // restore1（アップロード）の結果はリストアのタブ、restore2（一覧）の結果はスナップショットのタブに出す
    if (($restoreResult['mode'] ?? '') === 'restore2') {
        $ph['snapshot_msg'] .= $restoreHtml;
    } else {
        $ph['result_msg'] .= $restoreHtml;
    }
    sessionv('*result_msg', '');
    sessionv('*restore_result', []);
}

?>
<script>
    function selectAll() {
        var f = document.forms['frmdb'];
        f.querySelectorAll('input[name="chk[]"]').forEach(function (c) {
            c.checked = f.chkselall.checked;
        });
    }

    // 成功するとダウンロードが始まるだけで、iframe は読み込みを終えない。読み込んだら失敗の応答
    function onBackupFrameLoad() {
        var doc;
        try {
            doc = this.contentDocument;
        } catch (e) {
            doc = null;
        }
        if (!doc || !doc.body) {
            return;
        }
        var error = doc.getElementById('evoBackupError');
        var message = '';
        if (error) {
            message = error.innerHTML;
        } else if (doc.body.textContent.trim() !== '') {
            message = <?= js_literal(lang('bkmgr_backup_unexpected')) ?>;
        }
        doc.body.innerHTML = '';
        if (message !== '') {
            EvoDialog.alert(message, <?= js_literal(lang('warning')) ?>, null, {icon: 'warning'});
        }
    }

    function backup() {
        var f = document.forms['frmdb'];
        if (!f.querySelector('input[name="chk[]"]:checked')) {
            EvoDialog.alert(<?= js_literal(lang('bkmgr_backup_no_tables')) ?>, <?= js_literal(lang('warning')) ?>, null, {icon: 'warning'});
            return false;
        }
        document.querySelector('iframe[name="fileDownloader"]').onload = onBackupFrameLoad;
        f.target = 'fileDownloader';
        f.submit();
        return false;
    }

    // post_max_size を超えると本文ごと捨てられ、サーバーは理由を返せないので、送る前に確かめる
    function restoreUpload() {
        var f = document.forms['mutate'];
        var file = f.sqlfile.files[0];
        var max = <?= (int) $uploadMax ?>;
        if (file && max > 0 && file.size > max) {
            EvoDialog.alert(<?= js_literal($restoreTooLarge) ?>, <?= js_literal(lang('warning')) ?>, null, {icon: 'warning'});
            return false;
        }
        f.save.click();
        return false;
    }
</script>
<h1><?= lang('bk_manager') ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <li
            id="Button5"
            class="mutate"><a
                href="#"
                onclick="EvoShell.go('index.php?a=2', {discard: true});"><?= lang('cancel') ?></a></li>
    </ul>
</div>

<div class="sectionBody">
    <div class="tab-pane" id="dbmPane">
        <div class="tab-page" id="tabBackup">
            <h2 class="tab"><?= lang('backup') ?></h2>
            <form name="frmdb" method="post" action="index.php">
                <input type="hidden" name="a" value="306" />
                <p><?= lang('table_hoverinfo') ?></p>

                <p class="actionButtons"><a class="primary" href="#" onclick="backup();return false;"><?= lang('database_table_clickbackup') ?>
                    </a></p>
                <p>
                    <label>
                        <input
                            type="checkbox" name="droptables"
                            checked="checked" /><?= lang('database_table_droptablestatements') ?>
                    </label>
                </p>
                <table class="grid">
                    <thead>
                        <tr>
                            <th>
                                <label>
                                    <input
                                        name="chkselall"
                                        onclick="selectAll()"
                                        title="Select All Tables"
                                        type="checkbox" /><?= lang('database_table_tablename') ?>
                                </label>
                            </th>
                            <th class="is-end"><?= lang('database_table_records') ?></th>
                            <th class="is-end"><?= lang('database_collation') ?></th>
                            <th class="is-end"><?= lang('database_table_datasize') ?></th>
                            <th class="is-end"><?= lang('database_table_overhead') ?></th>
                            <th class="is-end"><?= lang('database_table_effectivesize') ?></th>
                            <th class="is-end"><?= lang('database_table_indexsize') ?></th>
                            <th class="is-end"><?= lang('database_table_totalsize') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $truncateable = EvoDbTables::truncatable();
                        $totaloverhead = 0;
                        $total = 0;
                        foreach (EvoDbTables::status() as $row) {
                            $isTruncateable = in_array($row['Name'], $truncateable, true);
                            $isInnoDb = strtolower((string)($row['Engine'] ?? '')) === 'innodb';
                            $recordCount = (int)$row['Rows'];

                            echo '<tr title="' . $row['Comment'] . '" style="cursor:default">' . "\n" .
                                '<td><label><input type="checkbox" name="chk[]" value="' . $row['Name'] . '" /><b class="table-name">' . $row['Name'] . '</b></label></td>' . "\n" .
                                '<td align="right">' . (
                                    evo()->hasPermission('settings') && $isTruncateable && $recordCount > 0
                                    ? '<a href="' . hsc('index.php?a=54&from=bkmanager&truncate=' . $row['Name']) . '" data-evo-post title="' . lang('truncate_table') . '">' . $recordCount . '</a>'
                                    : $recordCount
                                ) . '</td>' . "\n";
                            echo '<td align="right">' . $row['Collation'] . '</td>' . "\n";

                            if ($isInnoDb) {
                                echo '<td dir="ltr" align="right">-</td>' . "\n";
                            } else {
                                echo '<td dir="ltr" align="right">' . evo()->nicesize($row['Data_length'] + $row['Data_free']) . '</td>' . "\n";
                            }

                            if ($isInnoDb) {
                                echo '<td align="right">-</td>' . "\n";
                            } elseif (evo()->hasPermission('settings')) {
                                echo '<td align="right">' . ($row['Data_free'] > 0 ?
                                    '<a href="' . hsc('index.php?a=54&from=bkmanager&table=' . $row['Name']) . '" data-evo-post title="' . lang('optimize_table') . '">' . evo()->nicesize($row['Data_free']) . '</a>' :
                                    '-') .
                                    '</td>' . "\n";
                            } else {
                                echo '<td align="right">' . ($row['Data_free'] > 0 ? evo()->nicesize($row['Data_free']) : '-') . '</td>' . "\n";
                            }

                            echo '<td dir="ltr" align="right">' . evo()->nicesize($row['Data_length'] - $row['Data_free']) . '</td>' . "\n" .
                                '<td dir="ltr" align="right">' . evo()->nicesize($row['Index_length']) . '</td>' . "\n" .
                                '<td dir="ltr" align="right">' . evo()->nicesize($row['Index_length'] + $row['Data_length'] + $row['Data_free']) . '</td>' . "\n" .
                                "</tr>";

                            $total = $total + $row['Index_length'] + $row['Data_length'];
                            $totaloverhead = $totaloverhead + $row['Data_free'];
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td valign="top"><b><?= lang('database_table_totals') ?></b></td>
                            <td colspan="3">&nbsp;</td>
                            <td dir="ltr" align="right"
                                valign="top"><?= $totaloverhead > 0 ? '<b class="overhead-warning">' . evo()->nicesize($totaloverhead) . '</b><br />(' . number_format($totaloverhead) . ' B)' : '-' ?></td>
                            <td colspan="2">&nbsp;</td>
                            <td dir="ltr" align="right"
                                valign="top"><?= "<b>" . evo()->nicesize($total) . "</b><br />(" . number_format($total) . " B)" ?></td>
                        </tr>
                    </tfoot>
                </table>
                <?php
                if ($totaloverhead > 0) {
                    echo '<p>' . lang('database_overhead') . '</p>';
                }
                ?>
            </form>
        </div>
        <!-- This iframe is used when downloading file backup file -->
        <iframe name="fileDownloader" width="1" height="1" style="display:none; width:1px; height:1px;"></iframe>
        <?php if ($canRestore) { ?>
        <div class="tab-page" id="tabRestore">
            <h2 class="tab"><?= lang("bkmgr_restore_title") ?></h2>
            <?= $ph['result_msg'] ?>
            <?= lang("bkmgr_restore_msg") ?>
            <form method="post" name="mutate" enctype="multipart/form-data" action="index.php">
                <input type="hidden" name="a" value="305" />
                <input type="hidden" name="mode" value="restore1" />
                <div>
                    <input type="file" name="sqlfile" id="sqlfile" size="70" />
                </div>
                <div class="actionButtons">
                    <a
                        href="#"
                        class="primary"
                        onclick="return restoreUpload();"><?= lang("bkmgr_run_sql_submit") ?>
                    </a>
                </div>
                <input type="submit" name="save" style="display:none;" />
            </form>
        </div>
        <?php } ?>
        <div class="tab-page" id="tabSnapshot">
            <h2 class="tab"><?= lang("bkmgr_snapshot_title") ?></h2>
            <?= $ph['snapshot_msg'] ?>
            <?php if ($ph['snapshot_msg'] === '' && in_array($snapshotProblem, [EvoSnapshotStore::OUTSIDE, EvoSnapshotStore::SHARED], true)) : ?>
                <?= $snapshotPathUnusable ?>
            <?php endif; ?>
            <?= evo()->parseText(
                lang("bkmgr_snapshot_msg"),
                ['snapshot_path' => hsc($backup->store()->dir())]
            ); ?>
            <style type="text/css">
                #dbmPane table {
                    border-collapse: collapse;
                }

                #dbmPane td {
                    padding: 4px;
                }
            </style>
            <form method="post" name="snapshot" action="index.php">
                <input type="hidden" name="a" value="307" />
                <table>
                    <tr>
                        <th><?= lang("bkmgr_content_only") ?></th>
                        <td><input type="checkbox" name="contentsOnly" value="1" /></td>
                    </tr>
                    <tr>
                        <th><?= lang("bkmgr_file_name") ?></th>
                        <td><input type="text" name="file_name" size="50" value="" /><br /><?= evo()->parseText(lang("bkmgr_file_name_hint"), ['keep' => EvoSnapshotStore::KEEP]) ?></td>
                    </tr>
                </table>
                <div class="actionButtons">
                    <a href="#" class="primary" onclick="document.snapshot.save.click();">
                        <?= lang("bkmgr_snapshot_submit") ?>
                    </a>
                    <input type="submit" name="save" style="display:none;" />
                </div>
            </form>
        <div class="sectionHeader"><?= lang("bkmgr_snapshot_list_title") ?></div>
        <div class="sectionBody">
            <form method="post" name="restore2" action="index.php">
                <input type="hidden" name="a" value="305" />
                <input type="hidden" name="mode" value="restore2" />
                <input type="hidden" name="filename" value="" />
                <?php
                $files = $backup->store()->files();
                if ($files) {
                    echo '<ul>';
                    $tpl = $canRestore
                        ? '<li>[+filename+] ([+filesize+]) (<a href="#" onclick="document.restore2.filename.value=[+filename_js+];document.restore2.save.click()">' . lang("bkmgr_restore_submit") . '</a>)</li>' . "\n"
                        : '<li>[+filename+] ([+filesize+])</li>' . "\n";
                    foreach ($files as $file) {
                        // 置き換えた値の中を再び置換しないよう、strtr() で一度だけ置換する
                        echo strtr($tpl, [
                            '[+filename+]' => hsc($file['name']),
                            '[+filename_js+]' => hsc(js_literal($file['name'])),
                            '[+filesize+]' => evo()->nicesize($file['size']),
                        ]);
                    }
                    echo '</ul>';
                } else {
                    echo lang("bkmgr_snapshot_nothing");
                }
                ?>
                <input type="submit" name="save" style="display:none;" />
            </form>
        </div>
    </div>

</div>

</div>
<script>
    tpDBM = new WebFXTabPane(document.getElementById('dbmPane'));
</script>

<?php
if (empty($isPaneRequest)) {
    include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
}
