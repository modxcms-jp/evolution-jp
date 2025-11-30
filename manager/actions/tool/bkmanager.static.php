<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('bk_manager')) {
    alert()->setError(3);
    alert()->dumpError();
}

if (!config('snapshot_path') || strpos(config('snapshot_path'), MODX_BASE_PATH) === false) {
    if (is_dir(MODX_BASE_PATH . 'temp/backup/')) {
        $modx->config['snapshot_path'] = MODX_BASE_PATH . 'temp/backup/';
    } elseif (is_dir(MODX_BASE_PATH . 'assets/backup/')) {
        $modx->config['snapshot_path'] = MODX_BASE_PATH . 'assets/backup/';
    }
}

$mode = postv('mode', '');

include_once(MODX_CORE_PATH . 'mysql_dumper.class.inc.php');

$misc_path = manager_style_image_path('misc');

$dumper = new Mysqldumper();
if ($mode === 'backup') {
    $tables = postv('chk', '');
    if (!is_array($tables)) {
        evo()->webAlertAndQuit('Please select a valid table from the list below', 'history.back(-1);');
        exit;
    }

    /*
     * Code taken from Ralph A. Dahlgren MySQLdumper Snippet - Etomite 0.6 - 2004-09-27
     * Modified by Raymond 3-Jan-2005
     * Perform MySQLdumper data dump
     */
    @set_time_limit(120); // set timeout limit to 2 minutes
    $dumper->setDBtables($tables);
    $dumper->addDropCommand((isset($_POST['droptables']) ? true : false));
    $output = $dumper->createDump();
    $dumper->dumpSql($output);
    if (!$output) {
        alert()->setError(1, 'Unable to Backup Database');
        alert()->dumpError();
    }
    exit;
}

if ($mode === 'snapshot') {
    if (!is_dir(rtrim(config('snapshot_path'), '/'))) {
        mkdir(rtrim(config('snapshot_path'), '/'));
        @chmod(rtrim(config('snapshot_path'), '/'), 0777);
    }
    if (!is_file(config('snapshot_path') . '.htaccess')) {
        file_put_contents(
            config('snapshot_path') . '.htaccess',
            "order deny,allow\ndeny from all\n"
        );
    }
    if (!is_writable(rtrim(config('snapshot_path'), '/'))) {
        echo evo()->parseText(
            $_lang['bkmgr_alert_mkdir'],
            ['snapshot_path' => config('snapshot_path')]
        );
        exit;
    }

    $today = strtolower(
        str_replace(
            ['/', ' ', ':'],
            ['-', '-', ''],
            evo()->toDateFormat(request_time())
        )
    );
    global $path, $settings_version;
    $filename = "{$today}-{$settings_version}.sql";

    @set_time_limit(120); // set timeout limit to 2 minutes
    $dumper->mode = 'snapshot';
    $output = $dumper->createDump();
    $dumper->snapshot(config('snapshot_path') . $filename, $output);

    $pattern = config('snapshot_path') . '*.sql';
    $files = glob($pattern, GLOB_NOCHECK);
    $total = ($files[0] !== $pattern) ? count($files) : 0;
    arsort($files);
    while (10 < $total && $limit < 50) {
        $del_file = array_pop($files);
        unlink($del_file);
        $total = count($files);
        $limit++;
    }

    if (!empty($output)) {
        $_SESSION['result_msg'] = 'snapshot_ok';
        header("Location: index.php?a=93");
    } else {
        alert()->setError(1, 'Unable to Backup Database');
        alert()->dumpError();
    }
    exit;
}

include_once(MODX_MANAGER_PATH . 'actions/header.inc.php');  // start normal header
if (sessionv('result_msg')) {
    switch (sessionv('result_msg')) {
        case 'import_ok':
            $ph['result_msg'] = sprintf('<div class="okmsg">%s</div>', $_lang['bkmgr_import_ok']);
            break;
        case 'snapshot_ok':
            $ph['result_msg'] = sprintf('<div class="okmsg">%s</div>', $_lang['bkmgr_snapshot_ok']);
            break;
    }
    $_SESSION['result_msg'] = '';
} else {
    $ph['result_msg'] = '';
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
    <?= isset($_REQUEST['r']) ? " doRefresh(" . $_REQUEST['r'] . ");" : ""; ?>
</script>
<h1><?= $_lang['bk_manager'] ?></h1>

<div id="actions">
    <ul class="actionButtons">
        <li
            id="Button5"
            class="mutate"><a
                href="#"
                onclick="documentDirty=false;document.location.href='index.php?a=2';"><img
                    alt="icons_cancel"
                    src="<?= $_style["icons_cancel"] ?>" /> <?= $_lang['cancel'] ?></a></li>
    </ul>
</div>

<div class="sectionBody">
    <div class="tab-pane" id="dbmPane">
        <div class="tab-page" id="tabBackup">
            <h2 class="tab"><?= $_lang['backup'] ?></h2>
            <form name="frmdb" method="post">
                <input type="hidden" name="mode" value="" />
                <p><?= $_lang['table_hoverinfo'] ?></p>

                <p class="actionButtons"><a class="primary" href="#" onclick="backup();return false;"><img
                            src="<?= $misc_path ?>ed_save.gif" /> <?= $_lang['database_table_clickbackup'] ?>
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
                        $i = 0;
                        $totaloverhead = 0;
                        $total = 0;
                        while ($row = db()->getRow($rs)) {
                            $bgcolor = ($i % 2) ? '#EEEEEE' : '#FFFFFF';

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
                                '<td align="right">' . $row['Rows'] . '</td>' . "\n";
                            echo '<td align="right">' . $row['Collation'] . '</td>' . "\n";

                            // Enable record deletion for certain tables (TRUNCATE TABLE) if they're not already empty
                            $truncateable = [
                                db()->table_prefix . 'event_log',
                                db()->table_prefix . 'manager_log',
                            ];
                            if (evo()->hasPermission('settings') && in_array(
                                $row['Name'],
                                $truncateable
                            ) && $row['Rows'] > 0) {
                                echo '<td dir="ltr" align="right">' .
                                    '<a href="index.php?a=54&mode=' . $action . '&u=' . $row['Name'] . '" title="' . $_lang['truncate_table'] . '">' . evo()->nicesize($row['Data_length'] + $row['Data_free']) . '</a>' .
                                    '</td>' . "\n";
                            } else {
                                echo '<td dir="ltr" align="right">' . evo()->nicesize($row['Data_length'] + $row['Data_free']) . '</td>' . "\n";
                            }

                            if (evo()->hasPermission('settings')) {
                                echo '<td align="right">' . ($row['Data_free'] > 0 ?
                                    '<a href="index.php?a=54&mode=' . $action . '&t=' . $row['Name'] . '" title="' . $_lang['optimize_table'] . '">' . evo()->nicesize($row['Data_free']) . '</a>' :
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
        <div class="tab-page" id="tabRestore">
            <h2 class="tab"><?= $_lang["bkmgr_restore_title"] ?></h2>
            <?= $ph['result_msg'] ?>
            <?= $_lang["bkmgr_restore_msg"] ?>
            <form method="post" name="mutate" enctype="multipart/form-data" action="index.php">
                <input type="hidden" name="a" value="305" />
                <input type="hidden" name="mode" value="restore1" />
                <script type="text/javascript">
                    function showhide(a) {
                        var f = document.getElementById('sqlfile');
                        var t = document.getElementById('textarea');
                        if (a === 'file') {
                            f.style.display = 'block';
                            t.style.display = 'none';
                        } else {
                            t.style.display = 'block';
                            f.style.display = 'none';
                        }
                    }
                </script>
                <?php
                if (sessionv('textarea')) {
                    $value = sessionv('textarea');
                    unset($_SESSION['textarea']);
                    $_SESSION['console_mode'] = 'text';
                    $f_display = 'none';
                    $t_display = 'block';
                } else {
                    $value = '';
                    $_SESSION['console_mode'] = 'file';
                    $f_display = 'block';
                    $t_display = 'none';
                }

                if (sessionv('last_result')) {
                    $last_result = sessionv('last_result');
                    unset($_SESSION['last_result']);
                    if (!$last_result) {
                        $result = '';
                    } else {
                        $last_result = array_merge([], array_diff($last_result, ['']));
                        foreach ($last_result['0'] as $k => $v) {
                            $title[] = $k;
                        }
                        $result = '<tr><th>' . implode('</th><th>', $title) . '</th></tr>';
                        foreach ($last_result as $row) {
                            $result_value = [];
                            if ($row) {
                                foreach ($row as $k => $v) {
                                    $result_value[] = $v;
                                }
                                $result .= '<tr><td>' . implode('</td><td>', $result_value) . '</td></tr>';
                            }
                        }
                        $style = '<style type="text/css">table th {border:1px solid #ccc;background-color:#ddd;}</style>';
                        $result = $style . '<table>' . $result . '</table>';
                    }
                }

                ?>
                <p>
                    <label>
                        <input
                            type="radio"
                            name="sel"
                            onclick="showhide('file');"
                            <?= checked(!isset($_SESSION['console_mode']) || sessionv('console_mode') !== 'text') ?> /> <?= $_lang["bkmgr_run_sql_file_label"] ?>
                    </label>
                    <label>
                        <input
                            type="radio"
                            name="sel"
                            onclick="showhide('textarea');"
                            <?= checked(sessionv('console_mode') === 'text') ?> /> <?= $_lang["bkmgr_run_sql_direct_label"] ?>
                    </label>
                </p>
                <div>
                    <input
                        type="file"
                        name="sqlfile"
                        id="sqlfile"
                        size="70"
                        style="display:<?= $f_display ?>;" />
                </div>
                <div id="textarea" style="display:<?= $t_display ?>;">
                    <textarea
                        name="textarea"
                        style="width:500px;height:200px;"><?= $value ?></textarea>
                </div>
                <div class="actionButtons" style="margin-top:10px;">
                    <a
                        href="#"
                        class="primary"
                        onclick="document.mutate.save.click();"><img
                            alt="icons_save"
                            src="<?= $_style["icons_save"] ?>" /> <?= $_lang["bkmgr_run_sql_submit"] ?>
                    </a>
                </div>
                <input type="submit" name="save" style="display:none;" />
            </form>
            <?php
            if (isset($result)) {
                echo '<div style="margin-top:20px;"><p style="font-weight:bold;"><?= $_lang["bkmgr_run_sql_result"];?></p>' . $result . '</div>';
            }
            ?>
        </div>
        <?php
        $today = evo()->toDateFormat(request_time());
        $today = str_replace(['/', ' '], '-', $today);
        $today = str_replace(':', '', $today);
        $today = strtolower($today);
        global $settings_version;
        $filename = "{$today}-{$settings_version}.sql";
        ?>
        <div class="tab-page" id="tabSnapshot">
            <h2 class="tab"><?= $_lang["bkmgr_snapshot_title"] ?></h2>
            <?= $ph['result_msg'] ?>
            <?= evo()->parseText(
                $_lang["bkmgr_snapshot_msg"],
                ['snapshot_path' => config('snapshot_path')]
            ); ?>
            <form method="post" name="snapshot" action="index.php">
                <input type="hidden" name="a" value="307" />
                <input type="hidden" name="mode" value="snapshot" />
                <table>
                    <tr>
                        <th><?= $_lang["bk.contentOnly"] ?></th>
                        <td><input type="checkbox" name="contentsOnly" value="1" /></td>
                    </tr>
                    <tr>
                        <th><?= $_lang["bk.fileName"] ?></th>
                        <td><input type="text" name="file_name" size="50" value="<?= $filename ?>" /></td>
                    </tr>
                </table>
                <div class="actionButtons" style="margin-top:10px;margin-bottom:10px;">
                    <a href="#" class="primary" onclick="document.snapshot.save.click();">
                        <img alt="icons_save"
                            src="<?= $_style["icons_add"] ?>" /><?= $_lang["bkmgr_snapshot_submit"] ?>
                    </a>
                    <input type="submit" name="save" style="display:none;" />
            </form>
        </div>
        <style type="text/css">
            table {
                background-color: #fff;
                border-collapse: collapse;
            }

            table td {
                padding: 4px;
            }
        </style>
        <div class="sectionHeader"><?= $_lang["bkmgr_snapshot_list_title"] ?></div>
        <div class="sectionBody">
            <form method="post" name="restore2" action="index.php">
                <input type="hidden" name="a" value="305" />
                <input type="hidden" name="mode" value="restore2" />
                <input type="hidden" name="filename" value="" />
                <?php
                $pattern = config('snapshot_path') . '*.sql';
                $files = glob($pattern, GLOB_NOCHECK);
                $total = ($files[0] !== $pattern) ? count($files) : 0;
                if (is_array($files) && $total) {
                    echo '<ul>';
                    arsort($files);
                    $tpl = '<li>[+filename+] ([+filesize+]) (<a href="#" onclick="document.restore2.filename.value=\'[+filename+]\';document.restore2.save.click()">' . $_lang["bkmgr_restore_submit"] . '</a>)</li>' . "\n";
                    while ($file = array_shift($files)) {
                        $timestamp = filemtime($file);
                        $filename = substr($file, strrpos($file, '/') + 1);
                        $filesize = evo()->nicesize(filesize($file));
                        $output[$timestamp] = str_replace(['[+filename+]', '[+filesize+]'], [$filename, $filesize], $tpl);
                    }
                    krsort($output);
                    foreach ($output as $v) {
                        echo $v;
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
include_once(MODX_MANAGER_PATH . 'actions/footer.inc.php');
function checked($cond)
{
    if ($cond) {
        return 'checked';
    }
    return '';
}
