<?php
if (!isset($modx) || !evo()->isLoggedin()) {
    exit;
}
// シェルの部品（header.inc.php が描く。再読み込みは shell.js の reloadPartial）。単独のページとしては出さない
?>
        <script type="text/javascript">
            function initMenuState() {
                // シェルでのメニュー再読込時にタイマーが多重登録されないよう、一旦解除する
                if (window.__menuTimers) {
                    window.__menuTimers.forEach(clearInterval);
                }
                window.__menuTimers = [];
                window.__menuTimers.push(window.setInterval(keepMeAlive, 1000 * 60));

                if (top.__hideTree) {
                    hideTreeFrame();
                }

                markCurrentMenu(window.location.href);
            }

            Evo.ready(function () {
                initMenuState();
            });

            // シェル遷移ではメニューが再描画されないため、表示中の画面が変わるたびに付け直す。
            // リダイレクトしないPOST（サイトをリフレッシュ等）ではアドレスバーが元の画面のままなので、表示した画面のURLで判定する
            document.addEventListener('evoshell:load', function (e) {
                var detail = e.detail || {};
                markCurrentMenu(detail.screenUrl || detail.url || window.location.href);
            });

            // 表示中の画面に対応するサブメニュー項目に aria-current を付け、その親タブを開く。
            // リンクのクエリ（a、モジュールなら id も）がすべて一致する項目を対象にする。ダッシュボードは対象外
            function markCurrentMenu(url) {
                var nav = document.getElementById('nav');
                if (!nav) return;

                var current = new URL(url, window.location.href).searchParams;
                var action = current.get('a');
                var matched = null;
                nav.querySelectorAll('ul.subnav a[href]').forEach(function (link) {
                    link.removeAttribute('aria-current');
                    if (matched || !action || action === '2') return;
                    var href = link.getAttribute('href');
                    if (href.indexOf('javascript:') === 0) return;
                    var params = new URL(href, window.location.href).searchParams;
                    if (params.get('a') !== action) return;
                    for (var [key, value] of params) {
                        if (current.get(key) !== value) return;
                    }
                    matched = link;
                });
                if (!matched) return;

                matched.setAttribute('aria-current', 'page');
                var tab = matched.closest('ul.subnav').parentNode;
                Array.prototype.forEach.call(nav.children, function (li) {
                    li.classList.toggle('active', li === tab);
                });
            }

            // シェルのメニュー行の高さ(--shell-menu-height)を、表示中のサブメニュー帯の下端に合わせる。
            // 帯はテーマ側で絶対配置されているため、行の高さを固定値にすると帯の下が #mainPane に隠れる
            function fitMenuHeight() {
                var menu = document.getElementById('topMenu');
                if (!menu || menu.tagName === 'BODY') return;

                var subnav = document.querySelector('#nav > li.active > ul.subnav');
                var target = subnav && subnav.getClientRects().length ? subnav : document.getElementById('Navcontainer');
                if (!target) return;
                var height = Math.ceil(target.getBoundingClientRect().bottom - menu.getBoundingClientRect().top);
                if (height > 0) {
                    document.documentElement.style.setProperty('--shell-menu-height', height + 'px');
                }
            }

            // タブの切り替え・帯の折り返し・メニューの再読込に追随する。再読込で要素が替わるため、そのたびに付け直す
            function watchMenuHeight() {
                if (window.__menuHeightObserver) {
                    window.__menuHeightObserver.disconnect();
                }
                fitMenuHeight();
                if (!window.ResizeObserver) return;
                window.__menuHeightObserver = new ResizeObserver(fitMenuHeight);
                document.querySelectorAll('#nav, #nav > li > ul.subnav').forEach(function (el) {
                    window.__menuHeightObserver.observe(el);
                });
            }

            function keepMeAlive() {
                var tokInput = document.getElementById('sessTokenInput');
                var tok = tokInput ? tokInput.value : '';
                var o = Math.random();
                var params = new URLSearchParams({"tok": tok, "o": o});
                // 失敗時（HTTP エラー・JSON でない応答）は何もしない
                Evo.fetch('session_keepalive.php?' + params.toString())
                    .then(function (response) {
                        return response.ok ? response.json() : null;
                    })
                    .then(function (resp) {
                        if (resp && resp.status != 'ok') EvoCsrf.post('index.php?a=8', { fullPage: true });
                    })
                    .catch(function () {});
            }

            // ツリーを隠す・表示するときの短い動き。利用者の操作のとき（animate が true）だけ付け、ページを開いたときには付けない
            function animateTreePane() {
                document.body.classList.add('tree-animating');
                clearTimeout(window.__treeAnimationTimer);
                window.__treeAnimationTimer = setTimeout(function () {
                    document.body.classList.remove('tree-animating');
                }, 300);
            }

            function hideTreeFrame(animate) {
                if (animate === true) animateTreePane();
                document.body.classList.add('tree-hidden');
                top.__hideTree = true;
            }

            function defaultTreeFrame(animate) {
                if (animate === true) animateTreePane();
                document.body.classList.remove('tree-hidden');
                top.__hideTree = false;
            }

            // GENERAL FUNCTIONS - Refresh
            // These functions are used for refreshing the tree or menu
            function reloadtree() {
                var elm = document.getElementById('buildText');
                if (elm) {
                    elm.innerHTML = <?= js_literal("&nbsp;&nbsp;<img src='" . hsc(style('icons_loading_doc_tree')) . "' />&nbsp;" . hsc(stripcslashes(lang('loading_doc_tree')))) ?>;
                    elm.style.display = '';
                }
                top.tree.saveFolderState(); // save folder state
                setTimeout('top.tree.restoreTree()', 100);
            }

            function reloadmenu() {
                var elm = document.getElementById('buildText');
                if (elm) {
                    elm.innerHTML = <?= js_literal("&nbsp;&nbsp;<img src='" . hsc(style('icons_working')) . "' />&nbsp;" . hsc(stripcslashes(lang('loading_menu')))) ?>;
                    elm.style.display = '';
                }
                EvoShell.reloadMenu();
            }

            // GENERAL FUNCTIONS - Work
            // These functions are used for showing the user the system is working
            window.work = function() {
                var workText = document.getElementById('workText');
                if (workText) workText.innerHTML = <?= js_literal('&nbsp;<img src="' . hsc(style('icons_working')) . '" />&nbsp;' . hsc(stripcslashes(lang('working')))) ?>;
            }

            window.stopWork = function() {
                document.getElementById('workText').innerHTML = '';
            }

            // GENERAL FUNCTIONS - Remove locks
            // This function removes locks on documents, templates, parsers, and snippets
            function removeLocks() {
                if (confirm(<?= js_literal(stripcslashes(lang('confirm_remove_locks'))) ?>) == true) {
                    EvoCsrf.post("index.php?a=67");
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

            // 旧フレーム参照(top.mainMenu.X)の互換API。シェルではこのオブジェクトが窓口になる
            window.mainMenu = {
                keepMeAlive: keepMeAlive,
                hideTreeFrame: hideTreeFrame,
                defaultTreeFrame: defaultTreeFrame,
                reloadtree: reloadtree,
                reloadmenu: reloadmenu,
                removeLocks: removeLocks,
                showWin: showWin,
                initMenuState: initMenuState,
                work: function () { window.work(); },
                stopWork: function () { window.stopWork(); }
            };
        </script>
    <header id="topMenu" class="<?= $modx_textdir === 'rtl' ? 'rtl' : 'ltr' ?>">
        <script type="text/javascript">
            // シェルの部分再取得では#topMenu内のscriptだけが実行されるため、メニュー状態を再初期化する
            if (window.mainMenu && typeof window.mainMenu.initMenuState === 'function') {
                window.mainMenu.initMenuState();
            }
        </script>

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
                    $loginMarkup = sprintf('<a href="index.php?a=74">%s</a>', $loginName);
                } else {
                    $loginMarkup = $loginName;
                }
                $supplementalNavItems[] = sprintf('<span class="supplementalNav__item supplementalNav__item--user">%s</span>', $loginMarkup);

                if (evo()->hasPermission('help')) {
                    $helpLabel = htmlspecialchars(lang('help'), ENT_QUOTES, $charset);
                    $supplementalNavItems[] = sprintf('<span class="supplementalNav__item supplementalNav__item--help"><a href="index.php?a=9">%s</a></span>', $helpLabel);
                }

                $safeModeNavItem = manager_safe_mode_nav_item_html();
                if ($safeModeNavItem !== '') {
                    $supplementalNavItems[] = $safeModeNavItem;
                }

                $logoutLabel = htmlspecialchars(lang('logout'), ENT_QUOTES, $charset);
                $supplementalNavItems[] = sprintf('<span class="supplementalNav__item supplementalNav__item--logout"><a href="%s" data-evo-post="full">%s</a></span>', 'index.php?a=8', $logoutLabel);

                $versionLabelClass = 'supplementalNav__version-label';
                $versionLabel = htmlspecialchars($modx_version, ENT_QUOTES, $charset);
                $versionTitle = htmlspecialchars(config('site_name') . ' – ' . $modx_full_appname, ENT_QUOTES, $charset);
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
                    $item['home'] = item(lang('home'), 2);             // home
                    $item['preview'] = item(lang('view_site'), hsc(config('site_url')), 1,
                        'target="_blank"'); // preview
                    $item['refresh_site'] = item(lang('refresh_site'), 26,
                        evo()->hasPermission('empty_cache'), 'data-evo-post');    // clear-cache
                    $item['search'] = item(lang('search'), 71);          // search
                    $item['resource_list'] = item(lang('resources_list'), 120, evo()->hasPermission('view_document'));
                    $item['add_resource'] = item(lang('add_resource'), 4,
                        evo()->hasPermission('new_document')); // new-document
                    $item['add_weblink'] = item(lang('add_weblink'), 72,
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

                    $item['element_management'] = item(lang('element_management'), 76,
                        $perm_element_management);// Elements
                    $item['manage_files'] = item(lang('manage_files'), 31,
                        evo()->hasPermission('file_manager'));// Manage-Files

                    // Modules Menu Items
                    $perm_module_management = (evo()->hasPermission('new_module') || evo()->hasPermission('edit_module')) ? 1 : 0;
                    $item['modules'] = [];
                    $item['modules']['module_management'] = item(lang('module_management'), 106,
                        $perm_module_management);// manage-modules
                    if (evo()->hasPermission('exec_module')) {
                        // Each module
                        $uid = evo()->getLoginUserID();
                        if (sessionv('mgrRole') != 1) {
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
                            // 断片出力のモジュールはシェル内に表示される。
                            // 完全HTMLを出すモジュールはshell.jsが自動でフルページ表示へフォールバックする
                            $item['modules'][$content['name']] = item(hsc($content['name']),
                                "index.php?a=112&amp;id={$content['id']}");
                        }
                        $modulemenu = $item['modules'];
                    }

                    // Security menu items (users)
                    $perm_role_management = (evo()->hasPermission('new_role') || evo()->hasPermission('edit_role') || evo()->hasPermission('delete_role')) ? 1 : 0;
                    $perm_mgruser = (evo()->hasPermission('access_permissions') && config('use_udperms') == 1) ? 1 : 0;
                    $perm_webuser = (evo()->hasPermission('web_access_permissions') && config('use_udperms') == 1) ? 1 : 0;

                    $item['user_manage'] = item(lang('user_management_title'), 75,
                        evo()->hasPermission('edit_user'));// manager-users
                    $item['web_user_manage'] = item(lang('web_user_management_title'), 99,
                        evo()->hasPermission('edit_web_user'));// web-users
                    $item['role_manage'] = item(lang('role_management_title'), 86, $perm_role_management);// roles
                    $item['manager_permissions'] = item(lang('manager_permissions'), 40,
                        $perm_mgruser);// manager-perms
                    $item['web_permissions'] = item(lang('web_permissions'), 91, $perm_webuser);// web-user-perms
                    $item['remove_locks'] = item(lang('remove_locks'), 'javascript:removeLocks();',
                        evo()->hasPermission('remove_locks'), '');// unlock-pages

                    // Tools Menu
                    $item['bk_manager'] = item(lang('bk_manager'), 93,
                        evo()->hasPermission('bk_manager'));// backup-mgr
                    $item['import_site'] = item(lang('import_site'), 95,
                        evo()->hasPermission('import_static'));// import-html
                    $item['export_site'] = item(lang('export_site'), 83,
                        evo()->hasPermission('export_static'));// export-static-site
                    $item['edit_settings'] = item(lang('edit_settings'), 17,
                        evo()->hasPermission('settings'));// configuration

                    // Reports Menu
                    $item['site_schedule'] = item(lang('site_schedule'), 70,
                        evo()->hasPermission('view_schedule'));// site-sched
                    $item['eventlog_viewer'] = item(lang('eventlog_viewer'), 114,
                        evo()->hasPermission('view_eventlog'));// eventlog
                    $item['view_logging'] = item(lang('view_logging'), 13,
                        evo()->hasPermission('logs'));// manager-audit-trail
                    $item['view_sysinfo'] = item(lang('view_sysinfo'), 53,
                        evo()->hasPermission('logs'));// system-info

                    // User Profile Menu
                    $item['change_user_pf'] = item(lang('profile'), 74,
                        evo()->hasPermission('change_password'));// change password
                    $item['change_password'] = item(lang('change_password'), 28,
                        evo()->hasPermission('change_password'));// change password

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
                            ['id' => '1', 'name' => lang('site'), 'menuitem' => implode("\n", $sitemenu)]);
                    }
                    if (!empty($elementmenu)) {
                        echo $modx->parseText($tpl,
                            ['id' => '2', 'name' => lang('elements'), 'menuitem' => implode("\n", $elementmenu)]);
                    }
                    if (!empty($modulemenu)) {
                        echo $modx->parseText($tpl,
                            ['id' => '3', 'name' => lang('modules'), 'menuitem' => implode("\n", $modulemenu)]);
                    }
                    if (!empty($securitymenu)) {
                        echo $modx->parseText($tpl,
                            ['id' => '4', 'name' => lang('users'), 'menuitem' => implode("\n", $securitymenu)]);
                    }
                    if (!empty($usermenu)) {
                        echo $modx->parseText($tpl,
                            ['id' => '7', 'name' => lang('user'), 'menuitem' => implode("\n", $usermenu)]);
                    }
                    if (!empty($toolsmenu)) {
                        echo $modx->parseText($tpl,
                            ['id' => '5', 'name' => lang('tools'), 'menuitem' => implode("\n", $toolsmenu)]);
                    }
                    if (!empty($reportsmenu)) {
                        echo $modx->parseText($tpl,
                            ['id' => '6', 'name' => lang('reports'), 'menuitem' => implode("\n", $reportsmenu)]);
                    }
                    ?>
            </ul>
        </nav>
    </form>
    <script type="text/javascript">
        // メニューを組み立てた直後に測り、最初の描画から帯が隠れないようにする
        watchMenuHeight();
    </script>

    <div id="menuSplitter"></div>
    </header>

<?php
function item($name, $href, $display = 1, $attrib = '')
{
    if ($display == 0) {
        return false;
    }
    if (preg_match('@^[1-9][0-9]*$@', $href)) {
        $href = (int)$href === 2 ? './' : "index.php?a={$href}";
    }
    return sprintf('<li><a onclick="this.blur();" href="%s" %s>%s</a></li>', $href, $attrib, $name);
}

function buildMenu($target, $item)
{
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
