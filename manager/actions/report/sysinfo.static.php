<?php
if (!isset($modx) || !$modx->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('logs')) {
    alert()->setError(3);
    alert()->dumpError();
}
global $database_connection_method, $lastInstallTime;
?>
    <h1><?php echo lang('view_sysinfo'); ?></h1>
    <div id="actions">
        <ul class="actionButtons">
            <li id="Button5" class="mutate">
                <a href="#"
                   onclick="documentDirty=false;document.location.href='index.php?a=2';">
                    <img
                            alt="icons_cancel"
                            src="<?php echo style("icons_cancel") ?>"
                    /> <?php echo lang('cancel') ?>
                </a>
            </li>
        </ul>
    </div>

    <script type="text/javascript">
        function viewPHPInfo() {
            dontShowWorker = true; // prevent worker from being displayed
            window.location.href = "index.php?a=200";
        }
    </script>

    <div class="sectionBody">
        <div class="tab-pane" id="sysinfoPane">
            <div class="tab-page" id="tabServer">
                <h2 class="tab"><?php echo lang('view_sysinfo'); ?></h2>
                <!-- server -->
                <div class="sectionHeader"><?php echo lang('view_sysinfo'); ?></div>
                <div class="sectionBody" id="lyr2">
                    <table border="0" cellspacing="2" cellpadding="2">
                        <?php
                        echo render_tr(
                            lang('modx_version')
                            , config('settings_version')
                        );
                        echo render_tr(
                            lang('release_date')
                            , $modx_release_date
                        );
                        echo render_tr(
                            'システム更新日時'
                            , evo()->toDateFormat(config('lastInstallTime'))
                        );
                        echo render_tr(
                            'phpInfo()'
                            , sprintf(
                                '<a href="#" onclick="viewPHPInfo();return false;">%s</a>'
                                , lang('view')
                            )
                        );
                        echo render_tr(
                            lang('udperms_title')
                            , config('use_udperms') == 1 ? lang('enabled') : lang('disabled')
                        );
                        echo render_tr(
                            lang('servertime')
                            , strftime('%H:%M:%S', time())
                        );
                        echo render_tr(
                            lang('localtime')
                            , strftime(
                                '%H:%M:%S'
                                , time() + config('server_offset_time')
                            )
                        );
                        echo render_tr(
                            lang('serveroffset')
                            , (config('server_offset_time') / (60 * 60)) . ' h'
                        );
                        echo render_tr(
                            lang('database_name')
                            , db()->dbname
                        );
                        echo render_tr(
                            lang('database_server')
                            , db()->hostname
                        );
                        echo render_tr(
                            lang('database_version')
                            , db()->getVersion()
                        );
                        $rs = db()->query(
                            "show variables like 'character_set_database'"
                        );
                        $charset = db()->getRow($rs, 'num');
                        echo render_tr(
                            lang('database_charset')
                            , $charset[1]
                        );
                        $collation = db()->getRow(
                            db()->query("SHOW variables LIKE 'collation_database'"), 'num'
                        );
                        echo render_tr(lang('database_collation'), $collation[1]);
                        echo render_tr(lang('table_prefix'), db()->table_prefix);
                        echo render_tr(lang('cfg_base_path'), MODX_BASE_PATH);
                        echo render_tr(lang('cfg_base_url'), MODX_BASE_URL);
                        echo render_tr(lang('cfg_manager_url'), MODX_MANAGER_URL);
                        echo render_tr(lang('cfg_manager_path'), MODX_MANAGER_PATH);
                        echo render_tr(lang('cfg_site_url'), MODX_SITE_URL);
                        ?>
                    </table>
                </div>
            </div>

            <div class="tab-page" id="sysinfoDesc">
                <h2 class="tab"><?php echo lang('click_to_view_details'); ?></h2>
                <div class="sectionHeader">サポートに必要な情報</div>
                <div class="sectionBody" style="padding:10px 20px;">
                    <p>
                        <a href="http://forum.modx.jp/" target="_blank">公式フォーラム</a>でサポートを受けることができます。以下の情報を付記いただくと解決の助けとなります。<br/>
                        <a href="index.php?a=114">イベントログ</a>に重要なヒントが記録されていることもあります。
                    </p>
                    <?php
                    $info = array(
                        'OS' => sprintf('%s %s %s %s', PHP_OS, php_uname('r'), php_uname('v'), php_uname('m')),
                        'PHPのバージョン' => PHP_VERSION,
                        'セーフモード' => !ini_get('safe_mode') ? 'off' : 'on',
                        'php_sapi_name' => php_sapi_name(),
                        'MySQLのバージョン' => db()->server_info(),
                        'MySQLホスト情報' => db()->host_info(),
                        'MODXのバージョン' => config('settings_version'),
                        'サイトのURL' => config('site_url'),
                        'ホスト名' => gethostbyaddr($_SERVER['SERVER_ADDR']),
                        'MODX_BASE_URL' => MODX_BASE_URL,
                        'upload_tmp_dir' => sprintf(
                            '%s(ファイルアップロード処理のために一時的なファイル保存領域として用いるテンポラリディレクトリ。この値が空になっている時は、OSが認識するテンポラリディレクトリが用いられます)'
                            , ini_get('upload_tmp_dir')
                        ),
                        'memory_limit' => ini_get('memory_limit') . '(スクリプトが確保できる最大メモリ。通常はpost_max_sizeよりも大きい値にしますが、memory_limit・post_max_size・upload_max_filesizeの３つの値を同一に揃えても支障ありません。)',
                        'post_max_size' => ini_get('post_max_size') . '(POSTデータに許可される最大サイズ。POSTには複数のデータが含まれるので、通常はupload_max_filesizeよりも大きい値にします)',
                        'upload_max_filesize' => ini_get('upload_max_filesize') . '(アップロードを受け付けるファイルの最大サイズ)',
                        'max_execution_time' => ini_get('max_execution_time') . '秒(PHP処理の制限時間。スクリプト暴走の継続を防止します)',
                        'max_input_time' => ini_get('max_input_time') . '秒(POST・GET・ファイルアップロードなどの入力を処理する制限時間。回線の太さの影響を受けることもあります)',
                        'session.save_path' => ini_get('session.save_path') . '(セッションデータを保存するディレクトリ。CGI版PHPの場合はユーザの違いが原因でここに書き込み権限がない場合があるため、注意が必要です)',
                        'magic_quotes_gpc' => version_compare(PHP_VERSION,
                            '5.4') < 0 && get_magic_quotes_gpc() ? 'On' : 'Off' . '(クォート文字を自動的にエスケープします。トラブルの元になりやすいためOffを推奨します)',
                    );

                    echo '<p>' . getenv('SERVER_SOFTWARE') . '</p>' . "\n" . "\n";

                    echo '<table style="margin-bottom:20px;">';
                    foreach ($info as $key => $value) {
                        echo '<tr><td style="padding-right:30px;vertical-align:top;">' . $key . '</td><td>' . $value . '</td></tr>' . "\n";
                    }
                    echo '</table>' . "\n";
                    echo '<h4>mbstring</h4>' . "\n" . "\n";
                    echo '<table style="margin-bottom:20px;">';
                    $mb_get_info = mb_get_info();
                    $mb_get_info['http_input'] = ini_get('mbstring.http_input');
                    foreach ($mb_get_info as $key => $value) {
                        if (is_array($value)) {
                            $value = join(',', $value);
                        }
                        echo '<tr><td style="padding-right:30px;">' . $key . '</td><td>' . $value . '</td></tr>' . "\n";
                    }
                    echo '</table>' . "\n";

                    //Mysql char set
                    echo '<h4>MySQLの文字コード情報</h4>' . "\n" . "\n";
                    echo '<table style="margin-bottom:20px;">';
                    $res = db()->query("SHOW VARIABLES LIKE 'collation_database';");
                    $collation = db()->getRow($res, 'num');

                    echo '<tr><td style="padding-right:30px;">接続メソッド</td><td>' . $database_connection_method . '</td></tr>' . "\n";
                    echo '<tr><td style="padding-right:30px;">文字セット照合順序</td><td>' . $collation[1] . '</td></tr>' . "\n";
                    $rs = db()->query("SHOW VARIABLES LIKE 'char%';");
                    while ($row = db()->getRow($rs)) {
                        echo '<tr><td style="padding-right:30px;">' . $row['Variable_name'] . '</td><td>' . $row['Value'] . '</td></tr>' . "\n";
                    }
                    echo '</table>' . "\n";

                    ?>
                    <h3>さらに詳細な情報</h3>
                    <p>
                        <a href="index.php?a=200">phpinfo</a> をご覧ください。文字化け関係は<a href="index.php?a=200#module_mbstring">mbstring</a>、captcha関係は<a
                                href="index.php?a=200#module_gd">GDやFreeType</a>などを確認する必要があります。
                    </p>
                </div>
            </div>

            <!-- recent documents -->
            <div class="tab-page" id="tabActivity">
                <h2 class="tab"><?php echo lang('activity_title'); ?></h2>
                <div class="sectionHeader"><?php echo lang('activity_title'); ?></div>
                <div class="sectionBody" id="lyr1">
                    <?php echo lang('sysinfo_activity_message'); ?>
                    <p>
                        <style type="text/css">
                            table.grid {
                                border-collapse: collapse;
                                width: 100%;
                            }

                            table.grid td {
                                padding: 4px;
                                border: 1px solid #ccc;
                            }

                            table.grid a {
                                display: block;
                            }
                        </style>
                    <table class="grid">
                        <thead>
                        <tr>
                            <td><b><?php echo lang('id'); ?></b></td>
                            <td><b><?php echo lang('resource_title'); ?></b></td>
                            <td><b><?php echo lang('sysinfo_userid'); ?></b></td>
                            <td><b><?php echo lang('datechanged'); ?></b></td>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $field = 'id, pagetitle, editedby, editedon';
                        $rs = db()->select($field, evo()->getFullTableName('site_content'), 'deleted=0',
                            'editedon DESC', 20);
                        $limit = db()->getRecordCount($rs);
                        if ($limit < 1) {
                            echo sprintf('<p>%s</p>', lang('no_edits_creates'));
                        } else {
                            $i = 0;
                            $where = '';
                            while ($content = db()->getRow($rs)) {
                                if ($where !== "id={$content['editedby']}") {
                                    $where = "id={$content['editedby']}";
                                    $rs2 = db()->select('username', evo()->getFullTableName('manager_users'), $where);
                                    if (db()->getRecordCount($rs2) == 0) {
                                        $user = '-';
                                    } else {
                                        $r = db()->getRow($rs2);
                                        $user = $r['username'];
                                    }
                                }
                                $bgcolor = ($i % 2) ? '#EEEEEE' : '#FFFFFF';
                                echo "<tr bgcolor='$bgcolor'><td style='text-align:right;'>" . $content['id'] . "</td><td><a href='index.php?a=3&id=" . $content['id'] . "'>" . $content['pagetitle'] . "</a></td><td>" . $user . "</td><td>" . evo()->toDateFormat($content['editedon'] + config('server_offset_time')) . "</td></tr>";
                                $i++;
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- database -->
            <div class="tab-page" id="tabDatebase">
                <h2 class="tab"><?php echo lang('database_tables'); ?></h2>
                <div class="sectionHeader"><?php echo lang('database_tables'); ?></div>
                <div class="sectionBody" id="lyr4">
                    <table class="grid">
                        <thead>
                        <tr>
                            <td width="160"><b><?php echo lang('database_table_tablename'); ?></b></td>
                            <td width="50"><b><?php echo lang('database_table_engine'); ?></b></td>
                            <td width="40" align="right"><b><?php echo lang('database_table_records'); ?></b></td>
                            <td width="120" align="right"><b><?php echo lang('database_table_datasize'); ?></b></td>
                            <td width="120" align="right"><b><?php echo lang('database_table_overhead'); ?></b></td>
                            <td width="120" align="right"><b><?php echo lang('database_table_effectivesize'); ?></b>
                            </td>
                            <td width="120" align="right"><b><?php echo lang('database_table_indexsize'); ?></b></td>
                            <td width="120" align="right"><b><?php echo lang('database_table_totalsize'); ?></b></td>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        $rs = db()->query(
                            sprintf("SHOW TABLE STATUS FROM `%s` LIKE '%s%%'"
                                , db()->dbname
                                , db()->table_prefix
                            )
                        );
                        $limit = db()->getRecordCount($rs);
                        for ($i = 0; $i < $limit; $i++) {
                            $log_status = db()->getRow($rs);
                            $bgcolor = ($i % 2) ? '#EEEEEE' : '#FFFFFF';
                            ?>
                            <tr bgcolor="<?php echo $bgcolor; ?>" title="<?php echo $log_status['Comment']; ?>"
                                style="cursor:default">
                                <td><b style="color:#009933"><?php echo $log_status['Name']; ?></b></td>
                                <td><?php echo $log_status['Engine']; ?></td>
                                <td align="right"><?php echo $log_status['Rows']; ?></td>
                                <td dir="ltr"
                                    align="right"><?php echo evo()->nicesize($log_status['Data_length'] + $log_status['Data_free']); ?></td>
                                <?php

                                if (evo()->hasPermission('settings')) {
                                    if ($log_status['Data_free']) {
                                        echo "<td align='right'>" . ("<a href='index.php?a=54&mode=" . $action . "&t=" . $log_status['Name'] . "' title='" . lang('optimize_table') . "' ><span dir='ltr'>" . evo()->nicesize($log_status['Data_free']) . "</span></a>") . "</td>";
                                    } else {
                                        echo "<td align='right'>" . ("-") . "</td>";
                                    }
                                } else {
                                    echo "<td dir='ltr' align='right'>" . ($log_status['Data_free'] > 0 ? evo()->nicesize($log_status['Data_free']) : "-") . "</td>";
                                }
                                ?>
                                <td dir='ltr'
                                    align="right"><?php echo evo()->nicesize($log_status['Data_length'] - $log_status['Data_free']); ?></td>
                                <td dir='ltr'
                                    align="right"><?php echo evo()->nicesize($log_status['Index_length']); ?></td>
                                <td dir='ltr'
                                    align="right"><?php echo evo()->nicesize($log_status['Index_length'] + $log_status['Data_length'] + $log_status['Data_free']); ?></td>
                            </tr>
                            <?php
                            $total = $log_status['Index_length'] + $log_status['Data_length'];
                            $totaloverhead = $log_status['Data_free'];
                        }
                        ?>
                        <tr bgcolor="#e0e0e0">
                            <td valign="top"><b><?php echo lang('database_table_totals'); ?></b></td>
                            <td colspan="3">&nbsp;</td>
                            <td dir='ltr' align="right"
                                valign="top"><?php echo $totaloverhead > 0 ? "<b style='color:#990033'>" . evo()->nicesize($totaloverhead) . "</b><br />(" . number_format($totaloverhead) . " B)" : "-"; ?></td>
                            <td colspan="2">&nbsp;</td>
                            <td dir='ltr' align="right"
                                valign="top"><?php echo "<b>" . evo()->nicesize($total) . "</b><br />(" . number_format($total) . " B)"; ?></td>
                        </tr>
                        </tbody>
                    </table>
                    <?php
                    if ($totaloverhead > 0) { ?>
                        <p><?php echo lang('database_overhead'); ?></p>
                    <?php } ?>
                </div>
            </div>

            <!-- online users -->
            <div class="tab-page" id="tabOnlineUsers">
                <h2 class="tab"><?php echo lang('onlineusers_title'); ?></h2>
                <div class="sectionHeader"><?php echo lang('onlineusers_title'); ?></div>
                <div class="sectionBody" id="lyr5">

                    <?php
                    $html = lang('onlineusers_message') . '<b>' . strftime('%H:%M:%S',
                            time() + config('server_offset_time')) . '</b>):<br /><br />
				<table class="grid">
				<thead>
					<tr>
					<td><b>' . lang('onlineusers_user') . '</b></td>
					<td><b>' . lang('onlineusers_userid') . '</b></td>
					<td><b>' . lang('onlineusers_ipaddress') . '</b></td>
					<td><b>' . lang('onlineusers_lasthit') . '</b></td>
					<td><b>' . lang('onlineusers_action') . '</b></td>
					<td><b>' . lang('onlineusers_actionid') . '</b></td>
					</tr>
				</thead>
				<tbody>
		';

                    $timetocheck = (time() - (60 * 20));
                    include_once(config('core_path') . 'actionlist.inc.php');
                    $rs = db()->select(
                        '*', evo()->getFullTableName('active_users')
                        , sprintf('lasthit>%s', $timetocheck)
                        , 'username ASC'
                    );
                    $limit = db()->getRecordCount($rs);
                    if ($limit < 1) {
                        $html = "<p>" . lang('no_active_users_found') . "</p>";
                    } else {
                        while ($activeusers = db()->getRow($rs)) {
                            $currentaction = getAction($activeusers['action'], $activeusers['id']);
                            if ($activeusers['internalKey'] < 0) {
                                $webicon = sprintf(
                                    '<img align="absmiddle" src="media/style/%s/images/tree/globe.png" alt="Web user" >'
                                    , config('manager_theme')
                                );
                            } else {
                                $webicon = "";
                            }
                            $html .= sprintf(
                                '<tr bgcolor="#FFFFFF"><td><b>%s</b></td><td>%s&nbsp;%s</td><td>%s</td><td>%s</td><td>$currentaction</td><td align="right">%s</td></tr>'
                                , $activeusers['username']
                                , $webicon
                                , abs($activeusers['internalKey'])
                                , $activeusers['ip']
                                , strftime(
                                    '%H:%M:%S'
                                    , $activeusers['lasthit'] + config('server_offset_time')
                                )
                                , $activeusers['action']
                            );
                        }
                    }
                    echo $html;
                    ?>
                    </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script type="text/javascript">
        tp = new WebFXTabPane(document.getElementById('sysinfoPane'), false);
    </script>

<?php
function render_tr($label, $content) {
    $ph['label'] = $label;
    $ph['content'] = $content;
    $tpl = <<< EOT
<tr>
<td width="150">[+label+]
<td width="20">&nbsp;</td>
<td style="font-weight:bold;">[+content+]</td>
</tr>
EOT;
    return evo()->parseText($tpl, $ph);
}
