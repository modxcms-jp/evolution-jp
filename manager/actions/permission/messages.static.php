<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
global $_style;
if (!evo()->hasPermission('messages')) {
    alert()->setError(3);
    alert()->dumpError();
}
$icons_path = manager_style_image_path('icons');
if (isset($_REQUEST['id'])) {
    $msgid = intval($_REQUEST['id']);
}
$uid = evo()->getLoginUserID();
?>
    <h1><?= $_lang['messages_title'] ?></h1>
<?php
$location = getv('id') ? '10' : '2';
?>
    <div id="actions">
        <ul class="actionButtons">
            <li id="Button5" class="mutate"><a href="#"
                                               onclick="documentDirty=false;document.location.href='index.php?a=<?= $location ?>';"><img
                        alt="icons_cancel"
                        src="<?= $_style["icons_cancel"] ?>"/> <?= $_lang['cancel'] ?></a></li>
        </ul>
    </div>

<?php if (isset($msgid) && $_REQUEST['m'] == 'r') { ?>
    <div class="section">
        <div class="sectionHeader"><?= $_lang['messages_read_message'] ?></div>
        <div class="sectionBody" id="lyr3">
            <?php
            $rs = db()->select('*', '[+prefix+]user_messages', "id='{$msgid}'");
            $limit = db()->count($rs);
            if ($limit != 1) {
                echo "Wrong number of messages returned!";
            } else {
                $message = db()->getRow($rs);
                $message['subject'] = decrypt($message['subject']);
                $message['message'] = decrypt($message['message']);
                if ($message['recipient'] != $uid) {
                    echo $_lang['messages_not_allowed_to_read'];
                } else {
                    // output message!
                    // get the name of the sender
                    $sender = $message['sender'];
                    if ($sender == 0) {
                        $sendername = $_lang['messages_system_user'];
                    } else {
                        $rs2 = db()->select('username', '[+prefix+]manager_users', "id={$sender}");
                        $row2 = db()->getRow($rs2);
                        $sendername = $row2['username'];
                    }
                    ?>
                    <table width="600" border="0" cellspacing="0" cellpadding="0">
                        <tr>
                            <td colspan="2">
                                <ul class="actionButtons">
                                    <li id="btn_reply"><a
                                            href="index.php?a=10&t=c&m=rp&id=<?= $message['id'] ?>"><img
                                                src="<?= $_style["icons_message_reply"] ?>"/> <?= $_lang['messages_reply'] ?>
                                        </a></li>
                                    <li><a href="index.php?a=10&t=c&m=f&id=<?= $message['id'] ?>"><img
                                                src="<?= $_style["icons_message_forward"] ?>"/> <?= $_lang['messages_forward'] ?>
                                        </a></li>
                                    <li><a href="index.php?a=65&id=<?= $message['id'] ?>"><img
                                                src="<?= $_style["icons_delete_document"] ?>"/> <?= $_lang['delete'] ?>
                                        </a></li>
                                    <?php if ($message['sender'] == 0) { ?>
                                        <script
                                            type="text/javascript">document.getElementById("btn_reply").className = 'disabled';</script>
                                    <?php } ?>
                                </ul>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">&nbsp;</td>
                        </tr>
                        <tr>
                            <td style="width: 120px;"><b><?= $_lang['messages_from'] ?>:</b></td>
                            <td style="width: 480px;"><?= $sendername ?></td>
                        </tr>
                        <tr>
                            <td><b><?= $_lang['messages_sent'] ?>:</b></td>
                            <td><?= $modx->toDateFormat($message['postdate'] + $modx->config['server_offset_time']) ?></td>
                        </tr>
                        <tr>
                            <td><b><?= $_lang['messages_subject'] ?>:</b></td>
                            <td><?= $message['subject'] ?></td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <?php
                                // format the message :)
                                $message = str_replace("\n", "<br />", $message['message']);
                                $dashcount = substr_count($message, "-----");
                                $message = str_replace("-----", "<i class='message-quote'>", $message);
                                for ($i = 0; $i < $dashcount; $i++) {
                                    $message .= "</i>";
                                }
                                echo $message;
                                ?>
                            </td>
                        </tr>
                    </table>
                    <?php
                    // mark the message as read
                    $rs = db()->update('messageread=1', '[+prefix+]user_messages', "id='{$msgid}'");
                }
            }
            ?>
        </div>
    </div>
<?php } ?>


    <div class="section">
        <div class="sectionHeader"><?= $_lang['messages_inbox'] ?></div>
        <div class="sectionBody">
            <?php

            // Get  number of rows
            $rs = db()->select('count(id)', '[+prefix+]user_messages', "recipient='{$uid}'");
            $num_rows = db()->getValue($rs);

            // ==============================================================
            // Exemple Usage
            // Note: I make 2 query to the database for this exemple, it
            // could (and should) be made with only one query...
            // ==============================================================

            // If current position is not set, set it to zero
            if (!isset($_REQUEST['int_cur_position']) || $_REQUEST['int_cur_position'] == 0) {
                $int_cur_position = 0;
            } else {
                $int_cur_position = $_REQUEST['int_cur_position'];
            }

            // Number of result to display on the page, will be in the LIMIT of the sql query also
            $int_num_result = $modx->config['number_of_messages'];


            $extargv = "&a=10"; // extra argv here (could be anything depending on your page)

            include_once(MODX_CORE_PATH . 'paginate.inc.php');
            // New instance of the Paging class, you can modify the color and the width of the html table
            $p = new Paging($num_rows, $int_cur_position, $int_num_result, $extargv);

            // Load up the 2 array in order to display result
            $array_paging = $p->getPagingArray();
            $array_row_paging = $p->getPagingRowArray();

            // Display the result as you like...
            $pager .= $_lang['showing'] . " " . $array_paging['lower'];
            $pager .= " " . $_lang['to'] . " " . $array_paging['upper'];
            $pager .= " (" . $array_paging['total'] . " " . $_lang['total'] . ")";
            $pager .= "<br />" . $array_paging['previous_link'] . '&lt;&lt;' . (isset($array_paging['previous_link']) ? "</a> " : " ");
            foreach ($array_row_paging as $v) {
                $pager .= $v . '&nbsp;';
            }
            $pager .= $array_paging['next_link'] . "&gt;&gt;" . (isset($array_paging['next_link']) ? "</a>" : "");

            $rs = db()->select(
                '*',
                '[+prefix+]user_messages',
                sprintf("recipient='%s'", $uid),
                'postdate DESC',
                sprintf('%d, %s', $int_cur_position, $int_num_result)
            );
            $limit = db()->count($rs);
            if ($limit < 1):
                echo $_lang['messages_no_messages'];
            else:
            echo $pager;
            $dotablestuff = 1;
            ?>
            <script type="text/javascript" src="media/script/tablesort.js"></script>
            <table border=0 cellpadding=0 cellspacing=0 class="sortabletable sortable-onload-5 rowstyle-even"
                   id="table-1" width='100%'>
                <thead>
                <tr bgcolor='#CCCCCC'>
                    <th width="12"></th>
                    <th width="60%" class="sortable"><b><?= $_lang['messages_subject'] ?></b></th>
                    <th class="sortable"><b><?= $_lang['messages_from'] ?></b></th>
                    <th class="sortable"><b><?= $_lang['messages_private'] ?></b></th>
                    <th width="20%" class="sortable"><b><?= $_lang['messages_sent'] ?></b></th>
                </tr>
                </thead>
                <tbody>
                <?php
                while ($message = db()->getRow($rs)) :
                    $message['subject'] = decrypt($message['subject']);
                    $message['message'] = decrypt($message['message']);
                    $sender = $message['sender'];
                    if ($sender == 0):
                        $sendername = "[System]";
                    else:
                        $rs2 = db()->select('username', '[+prefix+]manager_users', "id='{$sender}'");
                        $row2 = db()->getRow($rs2);
                        $sendername = $row2['username'];
                    endif;
                    $messagestyle = $message['messageread'] == 0 ? "messageUnread" : "messageRead";
                    ?>
                    <tr>
                        <td><?php if (($message['messageread'] == 0)) {
                                echo sprintf('<img src="%snew1-09.gif">',
                                    $icons_path);
                            } ?></td>
                        <td class="<?= $messagestyle ?>" style="cursor: pointer; text-decoration: underline;"
                            onclick="document.location.href='index.php?a=10&id=<?= $message['id'] ?>&m=r';"><?= $message['subject'] ?></td>
                        <td><?= $sendername ?></td>
                        <td><?= $message['private'] == 0 ? $_lang['no'] : $_lang['yes'] ?></td>
                        <td><?= $modx->toDateFormat($message['postdate'] + $modx->config['server_offset_time']) ?></td>
                    </tr>
                <?php
                endwhile;
                endif;

                if ($dotablestuff == 1) { ?>
                </tbody>
            </table>
        <?php } ?>
        </div>
    </div>
    <div class="section">
        <div class="sectionHeader"><?= $_lang['messages_compose'] ?></div>
        <div class="sectionBody">
            <?php
            if (($_REQUEST['m'] === 'rp' || $_REQUEST['m'] === 'f') && isset($msgid)) {
                $rs = db()->select('*', '[+prefix+]user_messages', "id='{$msgid}'");
                $limit = db()->count($rs);
                if ($limit != 1) {
                    echo "Wrong number of messages returned!";
                } else {
                    $message = db()->getRow($rs);
                    $message['subject'] = decrypt($message['subject']);
                    $message['message'] = decrypt($message['message']);
                    if ($message['recipient'] != $uid) {
                        echo $_lang['messages_not_allowed_to_read'];
                    } else {
                        // output message!
                        // get the name of the sender
                        $sender = $message['sender'];
                        if ($sender == 0) {
                            $sendername = "[System]";
                        } else {
                            $rs2 = db()->select('username', '[+prefix+]manager_users', "id={$sender}");
                            $row2 = db()->getRow($rs2);
                            $sendername = $row2['username'];
                        }
                        $subjecttext = $_REQUEST['m'] == 'rp' ? "Re: " : "Fwd: ";
                        $subjecttext .= $message['subject'];
                        $messagetext = "\n\n\n-----\n" . $_lang['messages_from'] . ": $sendername\n" . $_lang['messages_sent'] . ": " . $modx->toDateFormat($message['postdate'] + $modx->config['server_offset_time']) . "\n" . $_lang['messages_subject'] . ": " . $message['subject'] . "\n\n" . $message['message'];
                        if ($_REQUEST['m'] == 'rp') {
                            $recipientindex = $message['sender'];
                        }
                    }
                }
            }
            ?>

            <script type="text/javascript">
                function hideSpans(showSpan) {
                    document.getElementById("userspan").style.display = "none";
                    document.getElementById("groupspan").style.display = "none";
                    document.getElementById("allspan").style.display = "none";
                    if (showSpan == 1) {
                        document.getElementById("userspan").style.display = "block";
                    }
                    if (showSpan == 2) {
                        document.getElementById("groupspan").style.display = "block";
                    }
                    if (showSpan == 3) {
                        document.getElementById("allspan").style.display = "block";
                    }
                }
            </script>
            <form action="index.php?a=66" method="post" name="messagefrm" enctype="multipart/form-data">
                <fieldset style="width: 600px;background-color:#fff;border:1px solid #ddd;">
                    <legend><b><?= $_lang['messages_send_to'] ?>:</b></legend>
                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                        <tr>
                            <td>
                                <label><input type=radio name="sendto" VALUE="u" checked
                                              onClick='hideSpans(1);'><?= $_lang['messages_user'] ?></label>
                                <label><input type=radio name="sendto" VALUE="g"
                                              onClick='hideSpans(2);'><?= $_lang['messages_group'] ?></label>
                                <label><input type=radio name="sendto" VALUE="a"
                                              onClick='hideSpans(3);'><?= $_lang['messages_all'] ?></label><br/>
                                <span id='userspan'
                                      style="display:block;"> <?= $_lang['messages_select_user'] ?>:&nbsp;
    <?php
    // get all usernames
    $rs = db()->select('mu.username,mu.id',
        '[+prefix+]manager_users mu INNER JOIN [+prefix+]user_attributes mua ON mua.internalkey=mu.id',
        "mua.blocked='0'");
    ?>
    <select name="user" class="inputBox" style="width:150px">
    <?php
    while ($row = db()->getRow($rs)) {
        ?>
        <option value="<?= $row['id'] ?>"><?= $row['username'] ?></option>
        <?php
    }
    ?>
    </select>
</span>
                                <span id='groupspan'
                                      style="display:none;"> <?= $_lang['messages_select_group'] ?>:&nbsp;
    <?php
    // get all usernames
    $rs = db()->select('name, id', '[+prefix+]user_roles');
    ?>
    <select name="group" class="inputBox" style="width:150px">
    <?php
    while ($row = db()->getRow($rs)) {
        ?>
        <option value="<?= $row['id'] ?>"><?= $row['name'] ?></option>
        <?php
    }
    ?>
</select>
</span>
                                <span id='allspan' style="display:none;">
</span>
                            </td>
                        </tr>
                    </table>
                </fieldset>

                <p>

                <fieldset style="width: 600px;background-color:#fff;border:1px solid #ddd;">
                    <legend><b><?= $_lang['messages_message'] ?>:</b></legend>

                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                        <tr>
                            <td><?= $_lang['messages_subject'] ?>:</td>
                            <td><input name="messagesubject" type=text class="inputBox" style="width: 500px;"
                                       maxlength="60" value="<?= $subjecttext ?>"></td>
                        </tr>
                        <tr>
                            <td valign="top"><?= $_lang['messages_message'] ?>:</td>
                            <td><textarea name="messagebody" style="width:500px; height: 200px;" onLoad="this.focus()"
                                          class="inputBox"><?= $messagetext ?></textarea></td>
                        </tr>
                        <tr>
                            <td></td>
                            <td>
                                <ul class="actionButtons" style="margin-top:15px;">
                                    <li><a href="#" class="primary"
                                           onclick="documentDirty=false; document.messagefrm.submit();"><img
                                                src="<?= $_style["icons_save"] ?>"/> <?= $_lang['messages_send'] ?>
                                        </a></li>
                                    <li><a href="index.php?a=10&t=c"><img
                                                src="<?= $_style["icons_cancel"] ?>"/> <?= $_lang['cancel'] ?>
                                        </a></li>
                                </ul>
                            </td>
                        </tr>
                    </table>

                </fieldset>
            </form>
        </div>
    </div>
<?php
// count messages again, as any action on the messages page may have altered the message count
$rs = db()->select('count(*)', '[+prefix+]user_messages', "recipient='{$uid}' AND messageread=0");
$_SESSION['nrnewmessages'] = db()->getValue($rs);
$rs = db()->select('count(*)', '[+prefix+]user_messages', "recipient='{$uid}'");
$_SESSION['nrtotalmessages'] = db()->getValue($rs);
$messagesallowed = evo()->hasPermission('messages');
?>
    <script type="text/javascript">
        function msgCountAgain() {
            try {
                top.mainMenu.startmsgcount(<?= sessionv('nrnewmessages') ?>,<?= sessionv('nrtotalmessages') ?>,<?= $messagesallowed ? 1 : 0 ?>);
            } catch (oException) {
                vv = window.setTimeout('msgCountAgain()', 1500);
            }
        }

        v = setTimeout('msgCountAgain()', 1500); // do this with a slight delay so it overwrites msgCount()

    </script>

<?php

// http://d.hatena.ne.jp/hoge-maru/20120715/1342371992
function decrypt($encryptedText, $key = 'modx')
{
    $enc = base64_decode($encryptedText);
    $plaintext = '';
    $len = strlen($enc);
    for ($i = 0; $i < $len; $i++) {
        $asciin = ord($enc[$i]);
        $plaintext .= chr($asciin ^ ord($key[$i]));
    }
    return $plaintext;
}
