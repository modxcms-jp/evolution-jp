<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!evo()->hasPermission('logs')) {
    alert()->setError(3);
    alert()->dumpError();
}

$rs = db()->select('DISTINCT internalKey, username, action, itemid, itemname', '[+prefix+]manager_log');
$logs = [];
while ($row = db()->getRow($rs)) {
    $logs[] = $row;
}

$icons_path = manager_style_image_path('icons');
?>
<h1><?= lang('mgrlog_view') ?></h1>
<div id="actions">
    <ul class="actionButtons">
        <li
            id="Button5"
            class="mutate">
            <a
                href="#"
                onclick="EvoShell.go('index.php?a=2', {discard: true});"><?= lang('cancel') ?>
            </a>
        </li>
    </ul>
</div>
<div class="sectionBody">
    <form action="index.php" name="logging" class="mutate" method="GET">
        <input type="hidden" name="a" value="13">
        <div class="tab-pane" id="logPane">
            <div class="tab-page" id="tabGeneral">
                <h2 class="tab"><?= lang('general') ?></h2>
                <table border="0" cellpadding="2" cellspacing="0">
                    <tbody>
                        <tr style="background-color:#fff;">
                            <td
                                style="width:120px;"><b><?= lang('mgrlog_msg') ?></b></td>
                            <td align="right">
                                <input
                                    type="text"
                                    name="message"
                                    class="inputbox"
                                    style="width:240px"
                                    value="<?= hsc(get_strvar('message')) ?>" />
                            </td>
                        </tr>
                        <tr>
                            <td><b><?= lang('mgrlog_user') ?></b></td>
                            <td align="right">
                                <select name="searchuser" class="inputBox" style="width:240px">
                                    <option value="0"><?= lang('mgrlog_anyall') ?></option>
                                    <?php
                                    // get all users currently in the log
                                    $logs_user = record_sort(array_unique_multi($logs, 'internalKey'), 'username');
                                    foreach ($logs_user as $row) {
                                        echo sprintf(
                                            '<option value="%s" %s>%s</option>',
                                            $row['internalKey'],
                                            get_intvar('searchuser') && (int)$row['internalKey'] === get_intvar('searchuser') ? 'selected="selected"' : '',
                                            hsc($row['username'])
                                        ) . "\n";
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                </table>
            </div>
            <div class="tab-page" id="tabSettings">
                <h2 class="tab"><?= lang('option') ?></h2>
                <table border="0" cellpadding="2" cellspacing="0">
                    <tbody>
                        <tr>
                            <td><b><?= lang('mgrlog_action') ?></b></td>
                            <td align="right">
                                <select name="action" class="inputBox" style="width:240px;">
                                    <option value="0"><?= lang('mgrlog_anyall') ?></option>
                                    <?php
                                    // get all available actions in the log
                                    include_once(MODX_CORE_PATH . 'actionlist.inc.php');
                                    $logs_actions = record_sort(array_unique_multi($logs, 'action'), 'action');
                                    foreach ($logs_actions as $row) {
                                        $action = getAction($row['action']);
                                        if ($action == 'Idle') {
                                            continue;
                                        }
                                        echo sprintf(
                                            '<option value="%s" %s>%s - %s</option>',
                                            $row['action'],
                                            get_intvar('action') && (int)$row['action'] === get_intvar('action') ? 'selected="selected"' : '',
                                            $row['action'],
                                            $action
                                        ) . "\n";
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr style="background-color:#fff;">
                            <td><b><?= lang('mgrlog_itemid') ?></b></td>
                            <td align="right">
                                <select name="itemid" class="inputBox" style="width:240px">
                                    <option value="0"><?= lang('mgrlog_anyall') ?></option>
                                    <?php
                                    // get all itemid currently in logging
                                    $logs_items = record_sort(array_unique_multi($logs, 'itemid'), 'itemid');
                                    foreach ($logs_items as $row) {
                                        $selectedtext = $row['itemid'] == get_strvar('itemid') ? ' selected="selected"' : '';
                                        echo sprintf(
                                            '<option value="%s"%s>%s</option>',
                                            hsc($row['itemid']),
                                            $selectedtext,
                                            hsc($row['itemid'])
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><b><?= lang('mgrlog_itemname') ?></b></td>
                            <td align="right">
                                <select name="itemname" class="inputBox" style="width:240px">
                                    <option value="0"><?= lang('mgrlog_anyall') ?></option>
                                    <?php
                                    // get all itemname currently in logging
                                    $logs_names = record_sort(array_unique_multi($logs, 'itemname'), 'itemname');
                                    foreach ($logs_names as $row) {
                                        echo sprintf(
                                            '<option value="%s"%s>%s</option>',
                                            hsc($row['itemname']),
                                            $row['itemname'] == get_strvar('itemname') ? ' selected="selected"' : '',
                                            hsc($row['itemname'])
                                        );
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><b><?= lang('mgrlog_datefr') ?></b></td>
                            <td align="right">
                                <input type="text" id="datefrom" name="datefrom" class="DatePicker"
                                    value="<?= hsc(get_strvar('datefrom')) ?>" />
                                <a
                                    onclick="document.logging.datefrom.value=''; return true;"
                                    style="cursor:pointer; cursor:hand"><img
                                        src="<?= $icons_path ?>cal_nodate.gif"
                                        border="0" alt="No date" /></a>
                            </td>
                        </tr>
                        <tr style="background-color:#fff;">
                            <td><b><?= lang('mgrlog_dateto') ?></b></td>
                            <td align="right">
                                <input
                                    type="text"
                                    id="dateto"
                                    name="dateto"
                                    class="DatePicker"
                                    value="<?= hsc(get_strvar('dateto')) ?>" />
                                <a
                                    onclick="document.logging.dateto.value=''; return true;"
                                    style="cursor:pointer; cursor:hand">
                                    <img
                                        src="<?= $icons_path ?>cal_nodate.gif"
                                        border="0"
                                        alt="No date" />
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td><b><?= lang('mgrlog_results') ?></b></td>
                            <td align="right">
                                <input
                                    type="text" name="nrresults"
                                    class="inputbox" style="width:100px"
                                    value="<?= hsc(get_strvar('nrresults', config('number_of_logs'))) ?>" />
                                <img src="<?= style('tx') ?>" border="0" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ul class="actionButtons" style="margin-left:5px;">
                <li><a
                        href="#" class="default"
                        onclick="documentDirty=false;document.logging.log_submit.click();">
                        <?= lang('search') ?>
                    </a>
                </li>
                <li>
                    <a href="index.php?a=2" onclick="documentDirty=false;">
                        <?= lang('cancel') ?>
                    </a>
                </li>
            </ul>
            <input
                type="submit"
                name="log_submit"
                value="<?= lang('mgrlog_searchlogs') ?>"
                style="display:none;" />
        </div>
    </form>
</div>
<script>
    tpMgrLogSearch = new WebFXTabPane(document.getElementById('logPane'));
</script>

<?php if (getv('log_submit')) : ?>
    <div class="section">
        <div class="sectionHeader"><?= lang('mgrlog_qresults') ?></div>
        <div class="sectionBody" id="lyr2">
            <?php
            // get the selections the user made.
            $where = [];
            if (get_intvar('searchuser')) {
                $where[] = sprintf("internalKey='%d'", get_intvar('searchuser'));
            }
            if (get_intvar('action')) {
                $where[] = sprintf('action=%d', get_intvar('action'));
            }
            if (get_strvar('itemid')) {
                $where[] = sprintf("itemid='%s'", db()->escape(get_strvar('itemid')));
            }
            if (get_strvar('itemname')) {
                $where[] = sprintf("itemname='%s'", db()->escape(get_strvar('itemname')));
            }
            if (get_strvar('message')) {
                $where[] = sprintf("message LIKE '%%%s%%'", db()->escape(get_strvar('message')));
            }
            // date stuff
            if (get_strvar('datefrom')) {
                $where[] = sprintf('timestamp > %d', (int)evo()->toTimeStamp(get_strvar('datefrom')));
            }
            if (get_strvar('dateto')) {
                $where[] = sprintf('timestamp < %d', (int)evo()->toTimeStamp(get_strvar('dateto')));
            }

            // Number of result to display on the page, will be in the LIMIT of the sql query also
            $int_num_result = get_intvar('nrresults') ?: (int)config('number_of_logs');

            include_once(MODX_CORE_PATH . 'controls/data_table.class.php');

            // 並べ替えのキー => ORDER BY の式（リクエストの値を直接 SQL に入れない）
            $sortColumns = [
                'timestamp' => 'timestamp',
                'action' => 'action',
                'itemid' => 'itemid',
                'username' => 'username',
            ];
            $linkParams = [
                'a' => 13,
                'searchuser' => get_intvar('searchuser') ?: '',
                'action' => get_intvar('action') ?: '',
                'itemid' => get_strvar('itemid'),
                'itemname' => get_strvar('itemname'),
                'message' => get_strvar('message'),
                'datefrom' => get_strvar('datefrom'),
                'dateto' => get_strvar('dateto'),
                'nrresults' => $int_num_result,
                'log_submit' => get_strvar('log_submit'),
            ];
            $links = DataTableLinks::fromRequest(
                'index.php',
                array_filter($linkParams, function ($v) {
                    return $v !== '';
                }),
                array_keys($sortColumns)
            );
            $orderby = 'timestamp DESC, id DESC';
            if ($links->orderby() !== '') {
                $dir = $links->orderdir() === 'desc' ? 'DESC' : 'ASC';
                $orderby = sprintf('%s %s, id %s', $sortColumns[$links->orderby()], $dir, $dir);
            }

            // build the sql
            $total = (int)db()->getValue(
                db()->select(
                    'COUNT(id)',
                    '[+prefix+]manager_log',
                    implode(' AND ', $where)
                )
            );
            if ($total < 1) {
                echo '<p>' . lang('mgrlog_emptysrch') . '</p>';
            } else {
                $pager = new DataTablePager($total, $int_num_result, $links->page());
                $rs = db()->select(
                    '*',
                    '[+prefix+]manager_log',
                    implode(' AND ', $where),
                    $orderby,
                    $pager->limit() > 0 ? sprintf('%d, %d', $pager->offset(), $pager->limit()) : ''
                );
                $rows = [];
                while ($row = db()->getRow($rs)) {
                    $rows[] = $row;
                }

                echo '<p>' . str_replace('href="index.php?a=55"', 'href="index.php?a=55" data-evo-post', lang('mgrlog_sortinst')) . '</p>';

                // 「X 件目から Y 件目（全 Z 件）」
                $lower = $pager->offset() + 1;
                $upper = min($total, $pager->offset() + count($rows));
                echo sprintf(
                    '<p>%s %d %s %d (%d %s)</p>',
                    lang('paging_showing'),
                    $lower,
                    lang('paging_to'),
                    $upper,
                    $total,
                    lang('paging_total')
                );

                $table = new DataTable(
                    [
                        [
                            'key' => 'timestamp',
                            'label' => lang('mgrlog_time'),
                            'sort' => 'timestamp',
                            'format' => function ($value) {
                                return evo()->toDateFormat($value + config('server_offset_time'));
                            },
                        ],
                        [
                            'key' => 'action',
                            'label' => lang('mgrlog_action'),
                            'sort' => 'action',
                            'html' => true,
                            'format' => function ($value, array $row) {
                                return sprintf('[%s] %s', hsc($value), hsc($row['message']));
                            },
                        ],
                        [
                            'key' => 'itemid',
                            'label' => lang('mgrlog_itemid'),
                            'sort' => 'itemid',
                            'html' => true,
                            'format' => function ($value, array $row) {
                                if (!preg_match('/^[1-9][0-9]*$/', (string)$value)) {
                                    return '-';
                                }
                                $label = sprintf('[%s] %s', hsc($value), hsc($row['itemname']));
                                if (in_array($row['action'], [3, 27, 5])) {
                                    return sprintf('<a href="index.php?a=3&amp;id=%s">%s</a>', hsc($value), $label);
                                }
                                return $label;
                            },
                        ],
                        [
                            'key' => 'username',
                            'label' => lang('mgrlog_username'),
                            'sort' => 'username',
                            'html' => true,
                            'format' => function ($value, array $row) {
                                return sprintf(
                                    '<a href="index.php?a=13&amp;searchuser=%s&amp;itemname=0&amp;log_submit=true">%s</a>',
                                    hsc(urlencode((string)$row['internalKey'])),
                                    hsc($value)
                                );
                            },
                        ],
                    ],
                    ['id' => 'mgrlog']
                );
                $nav = $pager->render($links, [
                    'label' => lang('pagination_label'),
                    'first' => lang('pagination_table_first'),
                    'last' => lang('pagination_table_last'),
                ]);
                echo $nav . $table->render($rows, $links) . $nav;
            }
            ?>
        </div>
    </div>
<?php
    global $action;
    $action = 1;
endif;

        function array_unique_multi($array, $checkKey)
        {
            if (!is_array(current($array)) || empty($checkKey)) {
                return array_unique($array);
            }
            $ret = [];
            $checkValues = [];
            foreach ($array as $key => $current) {
                if (in_array($current[$checkKey], $checkValues)) {
                    continue;
                }
                $checkValues[] = $current[$checkKey];
                $ret[$key] = $current;
            }
            return $ret;
        }

        function record_sort($array, $key)
        {
            $hash = [];
            foreach ($array as $k => $v) {
                $hash[$k] = $v[$key];
            }
            natsort($hash);
            $records = [];
            foreach ($hash as $k => $row) {
                $records[$k] = $array[$k];
            }
            return $records;
        }
