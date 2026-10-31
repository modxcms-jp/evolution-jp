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

$mode = postv('mode', '');

include_once(MODX_CORE_PATH . 'mysql_dumper.class.inc.php');

$dumper = new Mysqldumper();
if ($mode === 'backup') {
    $tables = postv('chk', '');
    if (!is_array($tables)) {
        evo()->webAlertAndQuit('Please select a valid table from the list below', 'javascript:history.back(-1);');
        exit;
    }

    @set_time_limit(120); // set timeout limit to 2 minutes
    $dumper->setDBtables($tables);
    $dumper->addDropCommand(postv('droptables') !== null);
    // 書き終えてからヘッダーを送る（失敗したときに壊れたファイルをダウンロードさせない）
    $tempfile = tempnam(MODX_CACHE_PATH, 'bktemp');
    if ($tempfile !== false) {
        // 途中で止められても DB 全体のダンプを残さない
        register_shutdown_function(function () use ($tempfile) {
            if (is_file($tempfile)) {
                @unlink($tempfile);
            }
        });
    }
    if ($tempfile === false || !$dumper->dumpToFile($tempfile)) {
        if ($tempfile !== false && is_file($tempfile)) {
            unlink($tempfile);
        }
        alert()->setError(1, 'Unable to Backup Database');
        alert()->dumpError();
    }
    if (!headers_sent()) {
        header('Expires: 0');
        header('Cache-Control: private');
        header('Pragma: cache');
        header('Content-type: application/download');
        header('Content-Length: ' . filesize($tempfile));
        header(
            sprintf(
                'Content-Disposition: attachment; filename=%s-%s_database_backup.sql',
                strtolower(str_replace('/', '-', evo()->toDateFormat(time(), 'dateOnly'))),
                preg_replace('/[^0-9A-Za-z._-]/', '-', (string) evo()->getVersionData('version'))
            )
        );
    }
    readfile($tempfile);
    unlink($tempfile);
    exit;
}

if (empty($isPaneRequest)) { // シェルの断片要求ではheader/footerを重複出力しない
    include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');  // start normal header
}
$ph['result_msg'] = '';
$ph['snapshot_msg'] = '';
$restoreHtml = '';
if (sessionv('result_msg')) {
    $restoreResult = sessionv('restore_result', []);
    $restoreResult = is_array($restoreResult) ? $restoreResult : [];
    $preRestore = hsc($restoreResult['pre_restore'] ?? '');
    switch (sessionv('result_msg')) {
        case 'import_ok':
            $restoreHtml = sprintf('<div class="okmsg">%s</div>', $_lang['bkmgr_import_ok']);
            break;
        case 'restore_warn':
            // 復元は成功したが、設定のテーブルが無くキャッシュを作り直さなかった
            $restoreHtml = sprintf(
                '<div class="okmsg">%s</div><div class="errmsg">%s</div>',
                $_lang['bkmgr_import_ok'],
                evo()->parseText(
                    $_lang['bkmgr_restore_warn'],
                    ['pre_restore' => $preRestore]
                )
            );
            break;
        case 'restore_failed':
            $restoreHtml = sprintf(
                '<div class="errmsg">%s</div>',
                evo()->parseText(
                    $_lang['bkmgr_restore_failed'],
                    [
                        'failed_at'   => (int) ($restoreResult['failed_at'] ?? 0),
                        'error'       => hsc($restoreResult['error'] ?? ''),
                        'pre_restore' => $preRestore,
                    ]
                )
            );
            break;
        case 'restore_no_snapshot':
            $restoreHtml = sprintf('<div class="errmsg">%s</div>', $_lang['bkmgr_restore_no_snapshot']);
            break;
        case 'snapshot_ok':
            $ph['snapshot_msg'] = sprintf('<div class="okmsg">%s</div>', $_lang['bkmgr_snapshot_ok']);
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
<script language="javascript">
    function selectAll() {
        var f = document.forms['frmdb'];
        var c = f.elements['chk[]'];
        for (i = 0; i < c.length; i++) {
            c[i].checked = f.chkselall.checked;
        }
    }

    function backup() {
        var f = document.forms['frmdb'];
        f.mode.value = 'backup';
        f.target = 'fileDownloader';
        f.submit();
        return false;
    }
    <?= anyv('r') !== null ? ' doRefresh(' . (int) anyv('r') . ');' : ''; ?>
</script>
<h1><?= $_lang['bk_manager'] ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <li
            id="Button5"
            class="mutate"><a
                href="#"
                onclick="documentDirty=false;document.location.href='index.php?a=2';"><?= $_lang['cancel'] ?></a></li>
    </ul>
</div>

<div class="sectionBody">
    <div class="tab-pane" id="dbmPane">
        <div class="tab-page" id="tabBackup">
            <h2 class="tab"><?= $_lang['backup'] ?></h2>
            <form name="frmdb" method="post">
                <input type="hidden" name="mode" value="" />
                <p><?= $_lang['table_hoverinfo'] ?></p>

                <p class="actionButtons"><a class="primary" href="#" onclick="backup();return false;"><?= $_lang['database_table_clickbackup'] ?>
                    </a></p>
                <p>
                    <label>
                        <input
                            type="checkbox" name="droptables"
                            checked="checked" /><?= $_lang['database_table_droptablestatements'] ?>
                    </label>
                </p>
                <table style="width:100%;background-color:#ccc;">
                    <thead>
                        <tr>
                            <td width="160">
                                <label>
                                    <input
                                        name="chkselall"
                                        onclick="selectAll()"
                                        title="Select All Tables"
                                        type="checkbox" /><b><?= $_lang['database_table_tablename'] ?></b>
                                </label>
                            </td>
                            <td align="right"><b><?= $_lang['database_table_records'] ?></b></td>
                            <td align="right"><b><?= $_lang['database_collation'] ?></b></td>
                            <td align="right"><b><?= $_lang['database_table_datasize'] ?></b></td>
                            <td align="right"><b><?= $_lang['database_table_overhead'] ?></b></td>
                            <td align="right"><b><?= $_lang['database_table_effectivesize'] ?></b></td>
                            <td align="right"><b><?= $_lang['database_table_indexsize'] ?></b></td>
                            <td align="right"><b><?= $_lang['database_table_totalsize'] ?></b></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rs = db()->query(sprintf(
                            "SHOW TABLE STATUS FROM `%s` LIKE '%s%%'",
                            db()->dbname,
                            db()->table_prefix
                        ));
                        $truncateable = [
                            db()->table_prefix . 'event_log',
                            db()->table_prefix . 'manager_log',
                        ];
                        $i = 0;
                        $totaloverhead = 0;
                        $total = 0;
                        while ($row = db()->getRow($rs)) {
                            $bgcolor = ($i % 2) ? '#EEEEEE' : '#FFFFFF';
                            $isTruncateable = in_array($row['Name'], $truncateable, true);
                            $isInnoDb = strtolower((string)($row['Engine'] ?? '')) === 'innodb';
                            $recordCount = (int)$row['Rows'];
                            if ($isTruncateable) {
                                $recordCount = (int)db()->getValue(
                                    db()->select(
                                        'COUNT(*)',
                                        $row['Name']
                                    )
                                );
                            }

                            if (isset($dumper->_dbtables) && !empty($dumper->_dbtables)) {
                                $table_string = implode(',', $dumper->_dbtables);
                            } else {
                                $table_string = '';
                            }

                            echo '<tr bgcolor="' . $bgcolor . '" title="' . $row['Comment'] . '" style="cursor:default">' . "\n" .
                                '<td><label><input type="checkbox" name="chk[]" value="' . $row['Name'] . '"' . (strstr(
                                    $table_string,
                                    $row['Name']
                                ) === false ? '' : ' checked="checked"') . ' /><b class="table-name">' . $row['Name'] . '</b></label></td>' . "\n" .
                                '<td align="right">' . (
                                    evo()->hasPermission('settings') && $isTruncateable && $recordCount > 0
                                    ? '<a href="' . hsc('index.php?a=54&mode=' . $action . '&u=' . $row['Name']) . '" data-evo-post title="' . $_lang['truncate_table'] . '">' . $recordCount . '</a>'
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
                                    '<a href="' . hsc('index.php?a=54&mode=' . $action . '&t=' . $row['Name']) . '" data-evo-post title="' . $_lang['optimize_table'] . '">' . evo()->nicesize($row['Data_free']) . '</a>' :
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
                            $i++;
                        }
                        ?>
                        <tr bgcolor="#CCCCCC">
                            <td valign="top"><b><?= $_lang['database_table_totals'] ?></b></td>
                            <td colspan="3">&nbsp;</td>
                            <td dir="ltr" align="right"
                                valign="top"><?= $totaloverhead > 0 ? '<b class="overhead-warning">' . evo()->nicesize($totaloverhead) . '</b><br />(' . number_format($totaloverhead) . ' B)' : '-' ?></td>
                            <td colspan="2">&nbsp;</td>
                            <td dir="ltr" align="right"
                                valign="top"><?= "<b>" . evo()->nicesize($total) . "</b><br />(" . number_format($total) . " B)" ?></td>
                        </tr>
                    </tbody>
                </table>
                <?php
                if ($totaloverhead > 0) {
                    echo '<p>' . $_lang['database_overhead'] . '</p>';
                }
                ?>
            </form>
        </div>
        <!-- This iframe is used when downloading file backup file -->
        <iframe name="fileDownloader" width="1" height="1" style="display:none; width:1px; height:1px;"></iframe>
        <?php if ($canRestore) { ?>
        <div class="tab-page" id="tabRestore">
            <h2 class="tab"><?= $_lang["bkmgr_restore_title"] ?></h2>
            <?= $ph['result_msg'] ?>
            <?= $_lang["bkmgr_restore_msg"] ?>
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
                        onclick="document.mutate.save.click();"><?= $_lang["bkmgr_run_sql_submit"] ?>
                    </a>
                </div>
                <input type="submit" name="save" style="display:none;" />
            </form>
        </div>
        <?php } ?>
        <div class="tab-page" id="tabSnapshot">
            <h2 class="tab"><?= $_lang["bkmgr_snapshot_title"] ?></h2>
            <?= $ph['snapshot_msg'] ?>
            <?= evo()->parseText(
                $_lang["bkmgr_snapshot_msg"],
                ['snapshot_path' => hsc($backup->dir())]
            ); ?>
            <style type="text/css">
                /* シェル化でツリー等と同一documentになったため、コンテンツ領域内に限定する */
                #mainPane table, body > .tab-page table {
                    background-color: #fff;
                    border-collapse: collapse;
                }

                #mainPane table td, body > .tab-page table td {
                    padding: 4px;
                }
            </style>
            <form method="post" name="snapshot" action="index.php">
                <input type="hidden" name="a" value="307" />
                <input type="hidden" name="mode" value="snapshot" />
                <table>
                    <tr>
                        <th><?= $_lang["bkmgr_content_only"] ?></th>
                        <td><input type="checkbox" name="contentsOnly" value="1" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang["bkmgr_file_name"] ?></th>
                        <td><input type="text" name="file_name" size="50" value="" /><br /><?= evo()->parseText($_lang["bkmgr_file_name_hint"], ['keep' => EvoBackupManager::KEEP]) ?></td>
                    </tr>
                </table>
                <div class="actionButtons">
                    <a href="#" class="primary" onclick="document.snapshot.save.click();">
                        <?= $_lang["bkmgr_snapshot_submit"] ?>
                    </a>
                    <input type="submit" name="save" style="display:none;" />
                </div>
            </form>
        <div class="sectionHeader"><?= $_lang["bkmgr_snapshot_list_title"] ?></div>
        <div class="sectionBody">
            <form method="post" name="restore2" action="index.php">
                <input type="hidden" name="a" value="305" />
                <input type="hidden" name="mode" value="restore2" />
                <input type="hidden" name="filename" value="" />
                <?php
                $files = $backup->files();
                if ($files) {
                    echo '<ul>';
                    $tpl = $canRestore
                        ? '<li>[+filename+] ([+filesize+]) (<a href="#" onclick="document.restore2.filename.value=[+filename_js+];document.restore2.save.click()">' . $_lang["bkmgr_restore_submit"] . '</a>)</li>' . "\n"
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
                    echo $_lang["bkmgr_snapshot_nothing"];
                }
                ?>
                <input type="submit" name="save" style="display:none;" />
            </form>
        </div>
    </div>

</div>

</div>
<script type="text/javascript">
    tpDBM = new WebFXTabPane(document.getElementById('dbmPane'));
</script>

<?php
if (empty($isPaneRequest)) {
    include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
}
