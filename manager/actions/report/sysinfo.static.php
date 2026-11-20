<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('logs')) {
    alert()->setError(3);
    alert()->dumpError();
}
global $database_connection_method;
require_once MODX_CORE_PATH . 'db_tables.class.inc.php';
?>
<h1><?= lang('view_sysinfo') ?></h1>
<div id="actions">
    <ul class="actionButtons">
        <li id="Button5" class="mutate">
            <a href="#"
                onclick="EvoShell.go('index.php?a=2', {discard: true});">
                <?= lang('cancel') ?>
            </a>
        </li>
    </ul>
</div>

<script type="text/javascript">
    function viewPHPInfo() {
        EvoShell.go("index.php?a=200");
    }
</script>

<div class="sectionBody">
    <div class="tab-pane" id="sysinfoPane">
        <div class="tab-page" id="tabServer">
            <h2 class="tab"><?= lang('view_sysinfo') ?></h2>
            <!-- server -->
            <div class="sectionHeader"><?= lang('view_sysinfo') ?></div>
            <div class="sectionBody" id="lyr2">
                <table border="0" cellspacing="2" cellpadding="2">
                    <?php
                    $charset = db()->getRow(
                        db()->query("show variables like 'character_set_database'"),
                        'num'
                    );
                    $collation = db()->getRow(
                        db()->query("SHOW variables LIKE 'collation_database'"),
                        'num'
                    );
                    // [見出し, 値]。値は HTML。画面の外から来る値は hsc_for_tpl() で文字にする
                    $items = [
                        [lang('modx_version'), hsc_for_tpl(evo()->getVersionData('version'))],
                        [lang('release_date'), hsc_for_tpl($modx_release_date)],
                        ['システム更新日時', hsc_for_tpl(evo()->toDateFormat(config('lastInstallTime')))],
                        [
                            'phpInfo()',
                            sprintf('<a href="#" onclick="viewPHPInfo();return false;">%s</a>', lang('view'))
                        ],
                        [lang('udperms_title'), config('use_udperms') == 1 ? lang('enabled') : lang('disabled')],
                        [lang('servertime'), date('H:i:s')],
                        [lang('localtime'), date('H:i:s')],
                        [lang('serveroffset'), hsc_for_tpl((config('server_offset_time') / (60 * 60)) . ' h')],
                        [lang('database_name'), hsc_for_tpl(db()->dbname)],
                        [lang('database_server'), hsc_for_tpl(db()->hostname)],
                        [lang('database_version'), hsc_for_tpl(db()->getVersion())],
                        [lang('database_charset'), hsc_for_tpl($charset[1])],
                        [lang('database_collation'), hsc_for_tpl($collation[1])],
                        [lang('table_prefix'), hsc_for_tpl(db()->table_prefix)],
                        [lang('cfg_base_path'), hsc_for_tpl(MODX_BASE_PATH)],
                        [lang('cfg_base_url'), hsc_for_tpl(MODX_BASE_URL)],
                        [lang('cfg_manager_url'), hsc_for_tpl(MODX_MANAGER_URL)],
                        [lang('cfg_manager_path'), hsc_for_tpl(MODX_MANAGER_PATH)],
                        [lang('cfg_site_url'), hsc_for_tpl(MODX_SITE_URL)],
                    ];
                    foreach ($items as [$label, $value]) {
                        echo EvoFieldRow::render([
                            'label' => $label,
                            'body' => '<strong>' . $value . '</strong>',
                        ]);
                    }
                    ?>
                </table>
            </div>
        </div>

        <div class="tab-page" id="sysinfoDesc">
            <h2 class="tab"><?= lang('click_to_view_details') ?></h2>
            <div class="sectionHeader">サポートに必要な情報</div>
            <div class="sectionBody">
                <p>
                    <a href="http://forum.modx.jp/" target="_blank">公式フォーラム</a>でサポートを受けることができます。以下の情報を付記いただくと解決の助けとなります。<br />
                    <a href="index.php?a=114"><?= hsc(lang('system_log', 'システムログ')) ?></a>に重要なヒントが記録されていることもあります。
                </p>
                <?php
                $info = [
                    'OS' => sprintf('%s %s %s %s', PHP_OS, php_uname('r'), php_uname('v'), php_uname('m')),
                    'PHPのバージョン' => PHP_VERSION,
                    'php_sapi_name' => php_sapi_name(),
                    'MySQLのバージョン' => db()->server_info(),
                    'MySQLホスト情報' => db()->host_info(),
                    'MODXのバージョン' => evo()->getVersionData('version'),
                    'サイトのURL' => config('site_url'),
                    'ホスト名' => serverv('SERVER_ADDR') ? gethostbyaddr(serverv('SERVER_ADDR')) : '',
                    'MODX_BASE_URL' => MODX_BASE_URL,
                    'upload_tmp_dir' => sprintf(
                        '%s(ファイルアップロード処理のために一時的なファイル保存領域として用いるテンポラリディレクトリ。この値が空になっている時は、OSが認識するテンポラリディレクトリが用いられます)',
                        ini_get('upload_tmp_dir')
                    ),
                    'memory_limit' => ini_get('memory_limit') . '(スクリプトが確保できる最大メモリ。通常はpost_max_sizeよりも大きい値にしますが、memory_limit・post_max_size・upload_max_filesizeの３つの値を同一に揃えても支障ありません。)',
                    'post_max_size' => ini_get('post_max_size') . '(POSTデータに許可される最大サイズ。POSTには複数のデータが含まれるので、通常はupload_max_filesizeよりも大きい値にします)',
                    'upload_max_filesize' => ini_get('upload_max_filesize') . '(アップロードを受け付けるファイルの最大サイズ)',
                    'max_execution_time' => ini_get('max_execution_time') . '秒(PHP処理の制限時間。スクリプト暴走の継続を防止します)',
                    'max_input_time' => ini_get('max_input_time') . '秒(POST・GET・ファイルアップロードなどの入力を処理する制限時間。回線の太さの影響を受けることもあります)',
                    'session.save_path' => ini_get('session.save_path') . '(セッションデータを保存するディレクトリ。CGI版PHPの場合はユーザの違いが原因でここに書き込み権限がない場合があるため、注意が必要です)'
                ];

                echo '<p>' . getenv('SERVER_SOFTWARE') . '</p>' . "\n" . "\n";

                echo '<table>';
                foreach ($info as $key => $value) {
                    echo '<tr><td style="padding-right:30px;vertical-align:top;">' . $key . '</td><td>' . $value . '</td></tr>' . "\n";
                }
                echo '</table>' . "\n";
                echo '<h4>mbstring</h4>' . "\n" . "\n";
                echo '<table>';
                $mb_get_info = mb_get_info();
                $mb_get_info['http_input'] = ini_get('mbstring.http_input');
                foreach ($mb_get_info as $key => $value) {
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    }
                    echo '<tr><td style="padding-right:30px;">' . $key . '</td><td>' . $value . '</td></tr>' . "\n";
                }
                echo '</table>' . "\n";

                //Mysql char set
                echo '<h4>MySQLの文字コード情報</h4>' . "\n" . "\n";
                echo '<table>';
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
            <h2 class="tab"><?= lang('activity_title') ?></h2>
            <div class="sectionHeader"><?= lang('activity_title') ?></div>
            <div class="sectionBody" id="lyr1">
                <?= lang('sysinfo_activity_message') ?>
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
                            <td><b><?= lang('id') ?></b></td>
                            <td><b><?= lang('resource_title') ?></b></td>
                            <td><b><?= lang('sysinfo_userid') ?></b></td>
                            <td><b><?= lang('datechanged') ?></b></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rs = db()->select(
                            'id, pagetitle, editedby, editedon',
                            evo()->getFullTableName('site_content'),
                            'deleted=0',
                            'editedon DESC',
                            20
                        );
                        if (!db()->count($rs)) {
                            echo sprintf('<p>%s</p>', lang('no_edits_creates'));
                        } else {
                            $where = '';
                            while ($content = db()->getRow($rs)) {
                                if ($where !== 'id=' . $content['editedby']) {
                                    $where = 'id=' . $content['editedby'];
                                    $rs2 = db()->select(
                                        'username',
                                        evo()->getFullTableName('manager_users'),
                                        $where
                                    );
                                    if (db()->count($rs2) == 0) {
                                        $user = '-';
                                    } else {
                                        $r = db()->getRow($rs2);
                                        $user = $r['username'];
                                    }
                                }
                                echo sprintf(
                                    '<tr><td style="text-align:right;">%s</td><td><a href="index.php?a=3&id=%s">%s</a></td><td>%s</td><td>%s</td></tr>',
                                    $content['id'],
                                    $content['id'],
                                    hsc($content['pagetitle']),
                                    hsc($user),
                                    evo()->toDateFormat($content['editedon'] + config('server_offset_time'))
                                );
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- database -->
        <div class="tab-page" id="tabDatebase">
            <h2 class="tab"><?= lang('database_tables') ?></h2>
            <div class="sectionHeader"><?= lang('database_tables') ?></div>
            <div class="sectionBody" id="lyr4">
                <table class="grid">
                    <thead>
                        <tr>
                            <td width="160"><b><?= lang('database_table_tablename') ?></b></td>
                            <td width="50"><b><?= lang('database_table_engine') ?></b></td>
                            <td width="40" align="right"><b><?= lang('database_table_records') ?></b></td>
                            <td width="120" align="right"><b><?= lang('database_table_datasize') ?></b></td>
                            <td width="120" align="right"><b><?= lang('database_table_overhead') ?></b></td>
                            <td width="120" align="right"><b><?= lang('database_table_effectivesize') ?></b>
                            </td>
                            <td width="120" align="right"><b><?= lang('database_table_indexsize') ?></b></td>
                            <td width="120" align="right"><b><?= lang('database_table_totalsize') ?></b></td>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $total = 0;
                        $totaloverhead = 0;
                        foreach (EvoDbTables::status() as $log_status) {
                        ?>
                            <tr title="<?= $log_status['Comment'] ?>"
                                style="cursor:default">
                                <td><b class="table-name"><?= $log_status['Name'] ?></b></td>
                                <td><?= $log_status['Engine'] ?></td>
                                <td align="right"><?= $log_status['Rows'] ?></td>
                                <td dir="ltr"
                                    align="right"><?= evo()->nicesize($log_status['Data_length'] + $log_status['Data_free']) ?></td>
                                <?php

                                if (evo()->hasPermission('settings')) {
                                    if ($log_status['Data_free']) {
                                        echo "<td align='right'>" . ("<a href='" . hsc('index.php?a=54&from=sysinfo&table=' . $log_status['Name']) . "' data-evo-post title='" . lang('optimize_table') . "' ><span dir='ltr'>" . evo()->nicesize($log_status['Data_free']) . "</span></a>") . "</td>";
                                    } else {
                                        echo "<td align='right'>" . ("-") . "</td>";
                                    }
                                } else {
                                    echo "<td dir='ltr' align='right'>" . ($log_status['Data_free'] > 0 ? evo()->nicesize($log_status['Data_free']) : "-") . "</td>";
                                }
                                ?>
                                <td dir='ltr'
                                    align="right"><?= evo()->nicesize($log_status['Data_length'] - $log_status['Data_free']) ?></td>
                                <td dir='ltr'
                                    align="right"><?= evo()->nicesize($log_status['Index_length']) ?></td>
                                <td dir='ltr'
                                    align="right"><?= evo()->nicesize($log_status['Index_length'] + $log_status['Data_length'] + $log_status['Data_free']) ?></td>
                            </tr>
                        <?php
                            $total += $log_status['Index_length'] + $log_status['Data_length'];
                            $totaloverhead += $log_status['Data_free'];
                        }
                        ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td valign="top"><b><?= lang('database_table_totals') ?></b></td>
                            <td colspan="3">&nbsp;</td>
                            <td dir='ltr' align="right"
                                valign="top"><?= $totaloverhead > 0 ? "<b class='overhead-warning'>" . evo()->nicesize($totaloverhead) . "</b><br />(" . number_format($totaloverhead) . " B)" : "-" ?></td>
                            <td colspan="2">&nbsp;</td>
                            <td dir='ltr' align="right"
                                valign="top"><?= "<b>" . evo()->nicesize($total) . "</b><br />(" . number_format($total) . " B)" ?></td>
                        </tr>
                    </tfoot>
                </table>
                <?php
                if ($totaloverhead > 0) { ?>
                    <p><?= lang('database_overhead') ?></p>
                <?php } ?>
            </div>
        </div>

        <!-- online users -->
        <div class="tab-page" id="tabOnlineUsers">
            <h2 class="tab"><?= lang('onlineusers_title') ?></h2>
            <div class="sectionHeader"><?= lang('onlineusers_title') ?></div>
            <div class="sectionBody" id="lyr5">

                <?php
                $html = lang('onlineusers_message') . '<b>' . date(
                    'H:i:s',
                    time() + config('server_offset_time')
                ) . '</b>):<br /><br />
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
                    '*',
                    evo()->getFullTableName('active_users'),
                    sprintf('lasthit>%s', $timetocheck),
                    'username ASC'
                );
                $tree_path = manager_style_image_path('tree');
                if (!db()->count($rs)) {
                    $html = "<p>" . lang('no_active_users_found') . "</p>";
                } else {
                    while ($activeusers = db()->getRow($rs)) {
                        $currentaction = getAction($activeusers['action'], $activeusers['id']);
                        if ($activeusers['internalKey'] < 0) {
                            $webicon = sprintf(
                                '<img align="absmiddle" src="%sglobe.png" alt="Web user" >',
                                $tree_path
                            );
                        } else {
                            $webicon = "";
                        }
                        $html .= sprintf(
                            '<tr><td><b>%s</b></td><td>%s&nbsp;%s</td><td>%s</td><td>%s</td><td>%s</td><td align="right">%s</td></tr>',
                            hsc($activeusers['username']),
                            $webicon,
                            abs($activeusers['internalKey']),
                            hsc($activeusers['ip']),
                            date(
                                'H:i:s',
                                $activeusers['lasthit'] + config('server_offset_time')
                            ),
                            $currentaction,
                            $activeusers['action']
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
