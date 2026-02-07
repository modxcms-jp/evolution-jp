<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
if (!isset($modx->config['mail_check_timeperiod']) || empty($modx->config['mail_check_timeperiod'])) {
    $modx->config['mail_check_timeperiod'] = 0;
}
$mxla = $modx_lang_attribute ? $modx_lang_attribute : 'en';
?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
    <html <?php if ($modx_textdir === 'rtl') {
        echo 'dir="rtl"';
    } ?>lang="<?= $mxla ?>" xml:lang="<?= $mxla ?>">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=<?= $modx_manager_charset ?>"/>
        <title>nav</title>
        <link rel="stylesheet" type="text/css"
              href="media/style/<?= config('manager_theme') ?>/style.css?<?= $modx_version ?>"/>
        <?= config('manager_inline_style') ?>
        <script src="media/script/jquery/jquery.min.js" type="text/javascript"></script>
        <script type="text/javascript">
            // TREE FUNCTIONS - FRAME
            // These functions affect the tree frame and any items that may be pointing to the tree.
            var currentFrameState = 'open';
            var defaultFrameWidth = '<?= $modx_textdir === 'ltr' ? '260,*' : '*,260'?>';
            var userDefinedFrameWidth = '<?= $modx_textdir === 'ltr' ? '260,*' : '*,260'?>';
            var workText;
            var buildText;
            var msgcheck = <?= evo()->hasPermission('messages') ? 1 : 0;?>;

            var $j = jQuery.noConflict();
            jQuery(function () {
                window.setInterval('keepMeAlive()', 1000 * 60);
                if (msgcheck != 0) updateMail(true); // First run update
                var mailinterval = <?= config('mail_check_timeperiod');?>;
                if (mailinterval != '' && mailinterval != 0) {
                    if (msgcheck != 0) setInterval('updateMail(true)', mailinterval * 1000);
                }

                if (top.__hideTree) {
                    // display toc icon
                    jQuery('#tocText').html("<a href='#' onclick='defaultTreeFrame();'><img src='<?= $_style['show_tree']?>' alt='<?= $_lang['show_tree']?>' /></a>");
                }
            });

            function keepMeAlive() {
                var tok = jQuery('#sessTokenInput').val();
                var o = Math.random();
                jQuery.getJSON('session_keepalive.php', {"tok": tok, "o": o},
                    function (resp) {
                        if (resp.status != 'ok') window.location.href = 'index.php?a=8';
                    });
            }

            function updateMail(now) {
                try {
                    // if 'now' is set, runs immediate ajax request (avoids problem on initial loading where periodical waits for time period before making first request)
                    if (now && msgcheck != 0) {
                        jQuery.ajax({
                            type: 'POST',
                            url: 'index.php',
                            data: {"updateMsgCount": "true"},
                            success: function (request) {
                                showResponse(request);
                            }
                        });
                    }
                    return false;
                } catch (oException) {
                    // Delay first run until we're ready...
                    if (msgcheck != 0) setTimeout('updateMail(true)', 1000 * 60);
                }
            }

            function showResponse(request) {
                var counts = request.split(',');
                var elm = document.getElementById('msgCounter');
                if (elm) elm.innerHTML = '(' + counts[0] + ' / ' + counts[1] + ')';
                var elm = document.getElementById('newMail');
                if (elm) elm.style.display = counts[0] > 0 ? 'inline' : 'none';
            }

            function hideTreeFrame() {
                userDefinedFrameWidth = parent.document.getElementsByTagName("FRAMESET").item(1).cols;
                currentFrameState = 'closed';
                try {
                    jQuery('#tocText').html("<a href='#' onclick='defaultTreeFrame();'><img src='<?= $_style['show_tree']?>' alt='<?= $_lang['show_tree']?>' /></a>");
                    parent.document.getElementsByTagName("FRAMESET").item(1).cols = '<?php echo($modx_textdir === 'ltr' ? '0,*' : '*,0')?>';
                    top.__hideTree = true;
                } catch (oException) {
                    x = window.setTimeout('hideTreeFrame()', 100);
                }
            }

            function defaultTreeFrame() {
                userDefinedFrameWidth = defaultFrameWidth;
                currentFrameState = 'open';
                try {
                    var elm = document.getElementById('tocText');
                    if (elm) elm.innerHTML = "";
                    parent.document.getElementsByTagName("FRAMESET").item(1).cols = defaultFrameWidth;
                    top.__hideTree = false;
                } catch (oException) {
                    z = window.setTimeout('defaultTreeFrame()', 100);
                }
            }

            // TREE FUNCTIONS - Expand/ Collapse
            // These functions affect the expanded/collapsed state of the tree and any items that may be pointing to it
            function expandTree() {
                try {
                    parent.tree.d.openAll();  // dtree
                } catch (oException) {
                    zz = window.setTimeout('expandTree()', 100);
                }
            }

            function collapseTree() {
                try {
                    parent.tree.d.closeAll();  // dtree
                } catch (oException) {
                    yy = window.setTimeout('collapseTree()', 100);
                }
            }

            // GENERAL FUNCTIONS - Refresh
            // These functions are used for refreshing the tree or menu
            function reloadtree() {
                var elm = document.getElementById('buildText');
                if (elm) {
                    elm.innerHTML = "&nbsp;&nbsp;<img src='<?= $_style['icons_loading_doc_tree']?>' />&nbsp;<?= $_lang['loading_doc_tree']?>";
                    jQuery(elm).show();
                }
                top.tree.saveFolderState(); // save folder state
                setTimeout('top.tree.restoreTree()', 100);
            }

            function reloadmenu() {
                var elm = document.getElementById('buildText');
                if (elm) {
                    elm.innerHTML = "&nbsp;&nbsp;<img src='<?= $_style['icons_working']?>' />&nbsp;<?= $_lang['loading_menu']?>";
                    jQuery(elm).show();
                }
                parent.mainMenu.location.reload();
            }

            function reloadPane(rFrame) {
                if (rFrame == 1) {
                    x = window.setTimeout('reloadtree()', 100);
                }
                if (rFrame == 2) {
                    x = window.setTimeout('reloadmenu()', 100);
                }
                if (rFrame == 3) {
                    top.tree.location.href = 'index.php?a=1&f=tree';
                }
                if (rFrame == 9) {
                    x = window.setTimeout('reloadmenu()', 200);
                    y = window.setTimeout('reloadtree()', 100);
                }
                if (rFrame == 10) {
                    window.top.location.href = "../manager/";
                }
            }

            // GENERAL FUNCTIONS - Work
            // These functions are used for showing the user the system is working
            window.work = function() { // parent.mainMenu.document
                jQuery('#workText').html('&nbsp;<img src="<?= $_style['icons_working']?>" />&nbsp;<?= $_lang['working']?>');
            }

            window.stopWork = function() {
                document.getElementById('workText').innerHTML = '';
            }

            // GENERAL FUNCTIONS - Remove locks
            // This function removes locks on documents, templates, parsers, and snippets
            function removeLocks() {
                if (confirm("<?= $_lang['confirm_remove_locks']?>") == true) {
                    top.main.document.location.href = "index.php?a=67";
                }
            }

            function showWin() {
                window.open('../');
            }

            function NavToggle(element) {
                // This gives the active tab its look
                var navid = document.getElementById('nav');
                var navs = navid.getElementsByTagName('li');
                var navsCount = navs.length;
                for (j = 0; j < navsCount; j++) {
                    active = (navs[j].id == element.parentNode.id) ? "active" : "";
                    navs[j].className = active;
                }

                // remove focus from top nav
                if (element) element.blur();
            }
        </script>
    </head>

    <body id="topMenu" class="<?= $modx_textdir === 'rtl' ? 'rtl' : 'ltr' ?>">

    <div id="tocText"<?= $modx_textdir === 'rtl' ? ' class="tocTextRTL"' : '' ?>></div>
    <div id="topbar">
        <div id="topbar-container">
            <div id="statusbar">
                <span id="buildText"></span>
                <span id="workText"></span>
            </div>

            <div id="supplementalNav">
                <?php
                $charset = $modx_manager_charset;
                $supplementalNavItems = [];

                $loginName = htmlspecialchars($modx->getLoginUserName(), ENT_QUOTES, $charset);
                if (evo()->hasPermission('change_password')) {
                    $loginMarkup = sprintf('<a href="index.php?a=74" target="main">%s</a>', $loginName);
                } else {
                    $loginMarkup = $loginName;
                }
                $supplementalNavItems[] = sprintf('<span class="supplementalNav__item supplementalNav__item--user">%s</span>', $loginMarkup);

                if (evo()->hasPermission('messages')) {
                    $messagesLabel = htmlspecialchars($_lang['messages'], ENT_QUOTES, $charset);
                    $mailTooltip = htmlspecialchars($_lang['you_got_mail'], ENT_QUOTES, $charset);
                    $mailIcon = sprintf(
                        '<span id="newMail"><a href="index.php?a=10" title="%s" target="main"><img src="%s" alt="%s" /></a></span>',
                        $mailTooltip,
                        htmlspecialchars($_style['icons_mail'], ENT_QUOTES, $charset),
                        $messagesLabel
                    );
                    $messagesLink = sprintf(
                        '<a onclick="this.blur();" href="index.php?a=10" target="main">%s <span id="msgCounter">( ? / ? )</span></a>',
                        $messagesLabel
                    );
                    $supplementalNavItems[] = sprintf(
                        '<span class="supplementalNav__item supplementalNav__item--messages">%s%s</span>',
                        $mailIcon,
                        $messagesLink
                    );
                }

                if (evo()->hasPermission('help')) {
                    $helpLabel = htmlspecialchars($_lang['help'], ENT_QUOTES, $charset);
                    $supplementalNavItems[] = sprintf('<span class="supplementalNav__item supplementalNav__item--help"><a href="index.php?a=9" target="main">%s</a></span>', $helpLabel);
                }

                $logoutLabel = htmlspecialchars($_lang['logout'], ENT_QUOTES, $charset);
                $supplementalNavItems[] = sprintf('<span class="supplementalNav__item supplementalNav__item--logout"><a href="index.php?a=8" target="_top">%s</a></span>', $logoutLabel);

                $settings_version = db()->getValue(
                    'setting_value',
                    evo()->getFullTableName('system_settings'),
                    'setting_name="settings_version"'
                );
                if (empty($settings_version)) {
                    $settings_version = '0.0.0';
                }
                $versionLabelClass = 'supplementalNav__version-label';
                if ($settings_version != $modx_version) {
                    $versionLabelClass .= ' supplementalNav__version-label--mismatch';
                }
                $versionLabel = htmlspecialchars($settings_version, ENT_QUOTES, $charset);
                $versionTitle = htmlspecialchars($site_name . ' – ' . $modx_full_appname, ENT_QUOTES, $charset);
                $supplementalNavItems[] = sprintf(
                    '<span class="supplementalNav__item supplementalNav__item--version"><span class="%s" title="%s">%s</span></span>',
                    $versionLabelClass,
                    $versionTitle,
                    $versionLabel
                );
                ?>
                <?= implode("\n                    ", $supplementalNavItems) ?>
                <!-- close #supplementalNav --></div>
        </div>
    </div>

    <form name="menuForm" action="l4mnu.php" class="clear">
        <input name="sessToken" type="hidden" id="sessTokenInput" value="<?= md5(session_id()) ?>"/>
        <nav id="Navcontainer" class="top-nav">
            <ul id="nav">
                <?php
                // Concatenate menu items based on permissions

                // Site Menu
                    $item['home'] = item($_lang['home'], 2);             // home
                    $item['preview'] = item($_lang['view_site'], config("site_url"), 1,
                        'target="_blank"'); // preview
                    $item['refresh_site'] = item($_lang['refresh_site'], 26,
                        evo()->hasPermission('empty_cache'));    // clear-cache
                    $item['search'] = item($_lang['search'], 71);          // search
                    $item['resource_list'] = item($_lang['resources_list'], 120, evo()->hasPermission('view_document'));
                    $item['add_resource'] = item($_lang['add_resource'], 4,
                        evo()->hasPermission('new_document')); // new-document
                    $item['add_weblink'] = item($_lang['add_weblink'], 72,
                        evo()->hasPermission('new_document')); // new-weblink

                    // Resources Menu
                    if (evo()->hasPermission('new_template') || evo()->hasPermission('edit_template')
                        || evo()->hasPermission('new_snippet') || evo()->hasPermission('edit_snippet')
                        || evo()->hasPermission('new_chunk') || evo()->hasPermission('edit_chunk')
                        || evo()->hasPermission('new_plugin') || evo()->hasPermission('edit_plugin')) {
                        $perm_element_management = 1;
                    } else {
                        $perm_element_management = 0;
                    }

                    $item['element_management'] = item($_lang['element_management'], 76,
                        $perm_element_management);// Elements
                    $item['manage_files'] = item($_lang['manage_files'], 31,
                        evo()->hasPermission('file_manager'));// Manage-Files

                    // Modules Menu Items
                    $perm_module_management = (evo()->hasPermission('new_module') || evo()->hasPermission('edit_module')) ? 1 : 0;
                    $item['modules'] = [];
                    $item['modules']['module_management'] = item($_lang['module_management'], 106,
                        $perm_module_management);// manage-modules
                    if (evo()->hasPermission('exec_module')) {
                        // Each module
                        $uid = evo()->getLoginUserID();
                        if ($_SESSION['mgrRole'] != 1) {
                            // Display only those modules the user can execute
                            $field = 'sm.id, sm.name, mg.member';
                            $from = '[+prefix+]site_modules                 AS sm '
                                . 'LEFT JOIN [+prefix+]site_module_access AS sma ON sma.module = sm.id '
                                . 'LEFT JOIN [+prefix+]member_groups      AS mg  ON sma.usergroup = mg.user_group';
                            $where = "(mg.member IS NULL OR mg.member='{$uid}') AND sm.disabled != 1";
                            $orderby = 'sm.editedon DESC';
                            $rs = db()->select($field, $from, $where, $orderby);
                        } else {
                            // Admins get the entire list
                            $rs = db()->select('id,name', '[+prefix+]site_modules', 'disabled != 1',
                                'editedon DESC');
                        }

                        while ($content = db()->getRow($rs)) {
                            $item['modules'][$content['name']] = item($content['name'],
                                "index.php?a=112&amp;id={$content['id']}");
                        }
                        $modulemenu = $item['modules'];
                    }

                    // Security menu items (users)
                    $perm_role_management = (evo()->hasPermission('new_role') || evo()->hasPermission('edit_role') || evo()->hasPermission('delete_role')) ? 1 : 0;
                    $perm_mgruser = (evo()->hasPermission('access_permissions') && config('use_udperms') == 1) ? 1 : 0;
                    $perm_webuser = (evo()->hasPermission('web_access_permissions') && config('use_udperms') == 1) ? 1 : 0;

                    $item['user_manage'] = item($_lang['user_management_title'], 75,
                        evo()->hasPermission('edit_user'));// manager-users
                    $item['web_user_manage'] = item($_lang['web_user_management_title'], 99,
                        evo()->hasPermission('edit_web_user'));// web-users
                    $item['role_manage'] = item($_lang['role_management_title'], 86, $perm_role_management);// roles
                    $item['manager_permissions'] = item($_lang['manager_permissions'], 40,
                        $perm_mgruser);// manager-perms
                    $item['web_permissions'] = item($_lang['web_permissions'], 91, $perm_webuser);// web-user-perms
                    $item['remove_locks'] = item($_lang['remove_locks'], 'javascript:removeLocks();',
                        evo()->hasPermission('remove_locks'), '');// unlock-pages

                    // Tools Menu
                    $item['bk_manager'] = item($_lang['bk_manager'], 93,
                        evo()->hasPermission('bk_manager'));// backup-mgr
                    $item['import_site'] = item($_lang['import_site'], 95,
                        evo()->hasPermission('import_static'));// import-html
                    $item['export_site'] = item($_lang['export_site'], 83,
                        evo()->hasPermission('export_static'));// export-static-site
                    $item['edit_settings'] = item($_lang['edit_settings'], 17,
                        evo()->hasPermission('settings'));// configuration

                    // Reports Menu
                    $item['site_schedule'] = item($_lang['site_schedule'], 70,
                        evo()->hasPermission('view_schedule'));// site-sched
                    $item['eventlog_viewer'] = item($_lang['eventlog_viewer'], 114,
                        evo()->hasPermission('view_eventlog'));// eventlog
                    $item['view_logging'] = item($_lang['view_logging'], 13,
                        evo()->hasPermission('logs'));// manager-audit-trail
                    $item['view_sysinfo'] = item($_lang['view_sysinfo'], 53,
                        evo()->hasPermission('logs'));// system-info

                    // User Profile Menu
                    $item['change_user_pf'] = item($_lang['profile'], 74,
                        evo()->hasPermission('change_password'));// change password
                    $item['change_password'] = item($_lang['change_password'], 28,
                        evo()->hasPermission('change_password'));// change password
                    $item['messages'] = item($_lang['messages'], 10, evo()->hasPermission('messages'));// messages

                    $sitemenu = buildMenu('site', $item);
                    $elementmenu = buildMenu('element', $item);
                    //$modulemenu   = buildMenu('module',$item);//$item['modules']
                    $securitymenu = buildMenu('security', $item);
                    $toolsmenu = buildMenu('tools', $item);
                    $reportsmenu = buildMenu('reports', $item);
                    $usermenu = buildMenu('user', $item);

                    // Output Menus where there are items to show
                    $tpl = '<li id="limenu[+id+]"><a href="#menu[+id+]" onclick="new NavToggle(this); return false;">[+name+]</a><ul class="subnav" id="menu[+id+]">[+menuitem+]</ul></li>' . "\n";
                    $tplActive = str_replace(']"><a', ']" class="active"><a', $tpl);
                    if (!empty($sitemenu)) {
                        echo $modx->parseText($tplActive,
                            ['id' => '1', 'name' => $_lang['site'], 'menuitem' => implode("\n", $sitemenu)]);
                    }
                    if (!empty($elementmenu)) {
                        echo $modx->parseText($tpl,
                            ['id' => '2', 'name' => $_lang['elements'], 'menuitem' => implode("\n", $elementmenu)]);
                    }
                    if (!empty($modulemenu)) {
                        echo $modx->parseText($tpl,
                            ['id' => '3', 'name' => $_lang['modules'], 'menuitem' => implode("\n", $modulemenu)]);
                    }
                    if (!empty($securitymenu)) {
                        echo $modx->parseText($tpl,
                            ['id' => '4', 'name' => $_lang['users'], 'menuitem' => implode("\n", $securitymenu)]);
                    }
                    if (!empty($usermenu)) {
                        echo $modx->parseText($tpl,
                            ['id' => '7', 'name' => $_lang['user'], 'menuitem' => implode("\n", $usermenu)]);
                    }
                    if (!empty($toolsmenu)) {
                        echo $modx->parseText($tpl,
                            ['id' => '5', 'name' => $_lang['tools'], 'menuitem' => implode("\n", $toolsmenu)]);
                    }
                    if (!empty($reportsmenu)) {
                        echo $modx->parseText($tpl,
                            ['id' => '6', 'name' => $_lang['reports'], 'menuitem' => implode("\n", $reportsmenu)]);
                    }
                    ?>
            </ul>
        </nav>
    </form>

    <div id="menuSplitter"></div>
    </body>
    </html>

<?php
function item($name, $href, $display = 1, $attrib = 'target="main"')
{
    if ($display == 0) {
        return false;
    }
    if (preg_match('@^[1-9][0-9]*$@', $href)) {
        $href = "index.php?a={$href}";
    }
    return sprintf('<li><a onclick="this.blur();" href="%s" %s>%s</a></li>', $href, $attrib, $name);
}

function buildMenu($target, $item)
{
    global $modx;

    if (!isset($modx->config['topmenu_site'])) {
        include(MODX_CORE_PATH . 'default.config.php');
        $modx->config = $default_config;
    }
    $menu = [
        'site' => config('topmenu_site', ''),
        'element' => config('topmenu_element', ''),
        'module' => 'modules',
        'security' => config('topmenu_security', ''),
        'user' => config('topmenu_user', ''),
        'tools' => config('topmenu_tools', ''),
        'reports' => config('topmenu_reports', ''),
    ];

    if (empty($menu[$target])) {
        return false;
    }

    $result = [];
    $a = explode(',', $menu[$target]);
    foreach ($a as $v) {
        $v = trim($v);
        $v = trim($v, '* ');
        if (isset($item[$v]) && !empty($item[$v])) {
            $result[] = $item[$v];
        } elseif (isset($item['modules'][$v])) {
            $result[] = $item['modules'][$v];
            unset($item['modules'][$v]);
        }
    }

    return $result;
}
