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
                onclick="documentDirty=false;document.location.href='index.php?a=2';"><img
                    alt="icons_cancel"
                    src="<?= style('icons_cancel') ?>" /> <?= lang('cancel') ?>
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
                                    value="<?= getv('message') ?>" />
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
                                            $row['internalKey'] == getv('searchuser') ? 'selected="selected"' : '',
                                            $row['username']
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
                                            $row['action'] == getv('action') ? 'selected="selected"' : '',
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
                                        $selectedtext = $row['itemid'] == getv('itemid') ? ' selected="selected"' : '';
                                        echo sprintf(
                                            '<option value="%s"%s>%s</option>',
                                            $row['itemid'],
                                            $selectedtext,
                                            $row['itemid']
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
                                            $row['itemname'],
                                            $row['itemname'] == getv('itemname') ? ' selected="selected"' : '',
                                            $row['itemname']
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
                                    value="<?= getv('datefrom', '') ?>" />
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
                                    value="<?= getv('dateto', '') ?>" />
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
                                    value="<?= getv('nrresults', config('number_of_logs')) ?>" />
                                <img src="<?= style('tx') ?>" border="0" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ul class="actionButtons" style="margin-top:1em;margin-left:5px;">
                <li><a
                        href="#" class="default"
                        onclick="documentDirty=false;document.logging.log_submit.click();">
                        <img src="<?= style('icons_save') ?>" />
                        <?= lang('search') ?>
                    </a>
                </li>
                <li>
                    <a href="index.php?a=2" onclick="documentDirty=false;">
                        <img src="<?= style('icons_cancel') ?>" />
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
            if (getv('log_submit')) {
                // get the selections the user made.
                $where = [];
                if (getv('searchuser')) {
                    $where[] = sprintf("internalKey='%d'", (int)getv('searchuser'));
                }
                if (getv('action')) {
                    $where[] = sprintf('action=%d', (int)getv('action'));
                }
                if (getv('itemid') || getv('itemid') == '-') {
                    $where[] = sprintf("itemid='%s'", getv('itemid'));
                }
                if (getv('itemname')) {
                    $where[] = sprintf("itemname='%s'", getv('itemname'));
                }
                if (getv('message')) {
                    $where[] = sprintf("message LIKE '%%%s%%'", getv('message'));
                }
                // date stuff
                if (getv('datefrom')) {
                    $where[] = sprintf('timestamp > %s', evo()->toTimeStamp(getv('datefrom')));
                }
                if (getv('dateto')) {
                    $where[] = sprintf('timestamp < %s', evo()->toTimeStamp(getv('dateto')));
                }

                // Number of result to display on the page, will be in the LIMIT of the sql query also
                $int_num_result = is_numeric(getv('nrresults')) ? getv('nrresults') : config('number_of_logs');

                // build the sql
                $total = db()->getValue(
                    db()->select(
                        'COUNT(id)',
                        '[+prefix+]manager_log',
                        implode(' AND ', $where)
                    )
                );
                $orderby = 'timestamp DESC, id DESC';
                $rs = db()->select(
                    '*',
                    '[+prefix+]manager_log',
                    implode(' AND ', $where),
                    $orderby,
                    sprintf('%s, %s', evo()->input_get('int_cur_position', 0), $int_num_result)
                );
                if ($total < 1) {
                    echo '<p>' . lang('mgrlog_emptysrch') . '</p>';
                } else {
                    echo '<p>' . lang('mgrlog_sortinst') . '</p>';

                    include_once(MODX_CORE_PATH . 'paginate.inc.php');
                    // New instance of the Paging class, you can modify the color and the width of the html table
                    $extargv = sprintf(
                        '&a=13&searchuser=%s&action=%s&itemid=%s&itemname=%s&message=%s&dateto=%s&datefrom=%s&nrresults=%s&log_submit=%s',
                        getv('searchuser'),
                        getv('action'),
                        getv('itemid'),
                        getv('itemname'),
                        getv('message'),
                        getv('dateto'),
                        getv('datefrom'),
                        $int_num_result,
                        getv('log_submit')
                    );
                    $p = new Paging($total, evo()->input_get('int_cur_position', 0), $int_num_result, $extargv);

                    // Load up the 2 array in order to display result
                    $array_paging = $p->getPagingArray();
                    $array_row_paging = $p->getPagingRowArray();
                    $current_row = (int)(evo()->input_get('int_cur_position', 0) / $int_num_result);

                    // Display the result as you like...
                    echo sprintf('<p>%s %s', lang('paging_showing'), $array_paging['lower']);
                    echo sprintf(' %s %s', lang('paging_to'), $array_paging['upper']);
                    echo sprintf(' (%s %s)<br />', $array_paging['total'], lang('paging_total'));
                    $paging = sprintf(
                        '%s%s%s',
                        $array_paging['first_link'],
                        lang('paging_first'),
                        isset($array_paging['first_link']) ? '</a> ' : ' '
                    );
                    $paging .= $array_paging['previous_link'] . lang('paging_prev') . (isset($array_paging['previous_link']) ? "</a> " : " ");
                    $pagesfound = sizeof($array_row_paging);
                    if ($pagesfound > 6) {
                        $start = max(0, min($current_row - 2, $pagesfound - 5));
                        $end = min($pagesfound - 1, $start + 4);
                        for ($i = $start; $i <= $end; $i++) {
                            $paging .= $array_row_paging[$i];
                        }
                    } else {
                        for ($i = 0; $i < $pagesfound; $i++) {
                            $paging .= $array_row_paging[$i] . "&nbsp;";
                        }
                    }
                    $paging .= sprintf(
                        '%s%s%s ',
                        $array_paging['next_link'],
                        lang('paging_next'),
                        isset($array_paging['next_link']) ? '</a> ' : ' '
                    );
                    $paging .= sprintf(
                        '%s%s%s</p>',
                        $array_paging['last_link'],
                        lang('paging_last'),
                        isset($array_paging['last_link']) ? '</a> ' : ' '
                    );
                    echo $paging;
            ?>
                    <script type="text/javascript" src="media/script/tablesort.js"></script>
                    <table class="sortabletable rowstyle-even" id="table-1">
                        <thead>
                            <tr>
                                <th class="sortable"><b><?= lang('mgrlog_time') ?></b></th>
                                <th class="sortable"><b><?= lang('mgrlog_action') ?></b></th>
                                <th class="sortable"><b><?= lang('mgrlog_itemid') ?></b></th>
                                <th class="sortable"><b><?= lang('mgrlog_username') ?></b></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // grab the entire log file...
                            $tpl = <<< EOT
<tr class="[+class+]">
	<td>[+datetime+]</td>
	<td>[[+action+]] [+message+]</td>
	<td>[+title+]</td>
	<td><a href="index.php?a=13&searchuser=[+internalKey+]&itemname=0&log_submit=true">[+username+]</a></td>
</tr>
EOT;
                            $logentries = [];
                            $i = 0;
                            while ($row = db()->getRow($rs)) {
                                $row['itemname'] = evo()->hsc($row['itemname']);
                                if (!preg_match('/^[1-9][0-9]*$/', $row['itemid'])) {
                                    $row['title'] = '<div style="text-align:center;">-</div>';
                                } elseif (in_array($row['action'], [3, 27, 5])) {
                                    $row['title'] = evo()->parseText(
                                        '<a href="index.php?a=3&amp;id=[+itemid+]">[[+itemid+]] [+itemname+]</a>',
                                        $row
                                    );
                                } else {
                                    $row['title'] = evo()->parseText('[[+itemid+]] [+itemname+]', $row);
                                }
                                $row['class'] = ($i % 2) ? 'even' : '';
                                $row['datetime'] = evo()->toDateFormat($row['timestamp'] + config('server_offset_time'));
                                echo evo()->parseText($tpl, $row);
                                $i++;
                            }
                            ?>
                        </tbody>
                    </table>
                <?php
                    echo $paging;
                }
                ?>
        </div>
    </div>
<?php
                global $action;
                $action = 1;
            } else {
                echo lang('mgrlog_noquery');
            }
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
