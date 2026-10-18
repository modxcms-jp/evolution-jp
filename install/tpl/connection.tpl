<form id="install" action="[+site_url+]install/" method="POST">
    <input type="hidden" name="action" value="options"/>
    <input type="hidden" name="prev_action" value="connection"/>
    <h2>[+connection_screen_database_info+]</h2>
    <h3>[+connection_screen_server_connection_information+]</h3>
    <p>[+connection_screen_server_connection_note+]</p>

    <p class="labelHolder"><label for="database_server">[+connection_screen_database_host+]</label>
        <input id="database_server" type="text" value="[+database_server+]" name="database_server"/>
    </p>
    <p class="labelHolder"><label for="database_user">[+connection_screen_database_login+]</label>
        <input id="database_user" type="text" name="database_user" value="[+database_user+]"/>
    </p>
    <p class="labelHolder"><label for="database_password">[+connection_screen_database_pass+]</label>
        <input id="database_password" type="password" name="database_password" value="[+database_password+]"/>
    </p>

    <!-- connection test action/status message -->
    <div class="clickHere">
        &rarr; <a id="servertest" href="javascript:void(0)">[+connection_screen_server_test_connection+]</a>
    </div>
    <div class="status" id="serverstatus" style="display:none;"></div>
    <!-- end connection test action/status message -->


    <div id="setCollation">
        <div id="collationMask">
            <h3>[+connection_screen_database_connection_information+]</h3>
            <p>[+connection_screen_database_connection_note+]</p>
            <p class="labelHolder"><label for="dbase">[+connection_screen_database_name+]</label>
                <input id="dbase" type="text" value="[+dbase+]" name="dbase"/>
            </p>
            <p class="labelHolder"><label for="dbase">[+connection_screen_collation+]</label>
                <select id="collation" name="database_collation"/>
                </select>
            </p>
            <p class="labelHolder"><label for="table_prefix">[+connection_screen_table_prefix+]</label>
                <input id="table_prefix" type="text" value="[+table_prefix+]" name="table_prefix"/>
            </p>
            <p class="labelHolder">
            <div id="connection_method" name="connection_method">
                <input type="hidden" value="SET CHARACTER SET" id="database_connection_method"
                       name="database_connection_method"/>
            </div>
            </p>
            <div class="clickHere">
                &rarr; <a id="databasetest" href="#footer">[+connection_screen_database_test_connection+]</a>
            </div>
            <div class="status" id="databasestatus" style="display:none;">&nbsp;</div>
        </div>
    </div>

    <script type="text/javascript">
        // 確認の結果が見えるよう、ページの下までスクロールする
        ['servertest', 'databasetest'].forEach(function (id) {
            document.getElementById(id).addEventListener('click', function () {
                var footer = document.getElementById('footer');
                window.scrollTo({
                    top: footer.getBoundingClientRect().top + window.scrollY,
                    behavior: 'smooth'
                });
            });
        });
    </script>
    <div id="AUH" style="margin-top:1.5em;display:[+auh_display+];">
        <div id="AUHMask">
            <h2>[+connection_screen_defaults+]</h2>
            <h3>[+connection_screen_default_admin_user+]</h3>
            <p>[+connection_screen_default_admin_note+]</p>
            <p class="labelHolder"><label for="adminname">[+connection_screen_default_admin_login+]</label>
                <input id="adminname" type="text" value="[+adminname+]" name="adminname"/>
            </p>
            <p class="labelHolder"><label for="adminemail">[+connection_screen_default_admin_email+]</label>
                <input id="adminemail" type="email" value="[+adminemail+]" name="adminemail" style="width:300px;" required/>
                <span class="warning">[+connection_error+]</span>
            </p>
            <p class="labelHolder"><label for="adminpass">[+connection_screen_default_admin_password+]</label>
                <input id="adminpass" type="password" name="adminpass" value="[+adminpass+]"/>
            </p>
            <p class="labelHolder"><label
                        for="adminpassconfirm">[+connection_screen_default_admin_password_confirm+]</label>
                <input id="adminpassconfirm" type="password" name="adminpassconfirm" value="[+adminpassconfirm+]"/>
            </p>
        </div>
    </div>
    <p class="buttonlinks">
        <a href="javascript:void(0);" class="prev" title="[+btnback_value+]"><span>[+btnback_value+]</span></a>
        <a href="javascript:void(0);" class="next" title="[+btnnext_value+]"
           style="display:none;"><span>[+btnnext_value+]</span></a>
    </p>
</form>

<script type="text/javascript">
    var showBlock = function (id) {
        document.getElementById(id).style.display = 'block';
    };
    var hide = function (id) {
        document.getElementById(id).style.display = 'none';
    };
    // HTML を差し込み、応答に含まれる script も実行する（jQuery の .html() と同じ）
    var setHtml = function (el, html) {
        el.innerHTML = html;
        el.querySelectorAll('script').forEach(function (old) {
            var script = document.createElement('script');
            script.textContent = old.textContent;
            old.replaceWith(script);
        });
    };
    var postForm = function (url, data) {
        return fetch(url, {method: 'POST', body: new URLSearchParams(data)})
            .then(function (response) {
                if (!response.ok) {
                    throw new Error(response.status + ' ' + response.statusText);
                }
                return response.text();
            });
    };

    document.getElementById('servertest').addEventListener('click', function () {
        hide('serverstatus');
        hide('setCollation');
        hide('AUH');
        var url = 'connection.servertest.php';
        var pars = {
            q: url,
            host: document.getElementById('database_server').value,
            uid: document.getElementById('database_user').value,
            pwd: document.getElementById('database_password').value,
            language: language
        };
        postForm(url, pars).then(function (data) {
            setHtml(document.getElementById('serverstatus'), data);
            showBlock('serverstatus');
            if (0 < data.indexOf('server_pass'))
                showBlock('setCollation');
        }).catch(function () {
            // 失敗時は何もしない（従来どおり）
        });
    });

    // database test
    document.getElementById('databasetest').addEventListener('click', function () {
        hide('databasetest');
        hide('AUH');
        var status = document.getElementById('databasestatus');
        status.innerHTML = '';
        status.style.display = 'none';
        var url = 'connection.databasetest.php';
        var pars = {
            'q': url,
            'host': document.getElementById('database_server').value,
            'uid': document.getElementById('database_user').value,
            'pwd': document.getElementById('database_password').value,
            'dbase': document.getElementById('dbase').value,
            'table_prefix': document.getElementById('table_prefix').value,
            'database_collation': document.getElementById('collation').value,
            'database_connection_method': 'SET CHARACTER SET',
            'language': language,
            'is_upgradeable': is_upgradeable
        };
        postForm(url, pars).then(function (data) {
            setHtml(status, data);
            status.style.display = 'block';
            if (0 < data.indexOf('database_pass')) {
                showBlock('AUH');
            } else {
                document.getElementById('databasetest').style.display = '';
            }
        }).catch(function () {
            status.innerHTML = '<span id="database_fail" style="background: #ffe6eb;padding:8px;border-radius:5px;color:#FF0000;">[+alert_database_test_connection_failed+]</span>';
            status.style.display = 'block';
            document.getElementById('databasetest').style.display = '';
        });
    });

    var showNextButton = function () {
        document.querySelectorAll('a.next').forEach(function (el) {
            el.style.display = 'block';
        });
    };
    if (document.getElementById('adminpassconfirm').value != '') {
        showNextButton();
    }

    document.getElementById('adminpassconfirm').addEventListener('focus', showNextButton);

    document.querySelectorAll('a.prev').forEach(function (el) {
        el.addEventListener('click', function () {
            var form = document.getElementById('install');
            form.querySelector('input[name=action]').value = 'mode';
            form.submit();
        });
    });
    document.querySelectorAll('a.next').forEach(function (el) {
        el.addEventListener('click', function () {
            if (is_upgradeable != 1 && document.getElementById('adminemail').value.trim() === '')
                alert("[+alert_enter_adminemail+]");
            else if (document.getElementById('adminpass').value !== document.getElementById('adminpassconfirm').value)
                alert("[+alert_enter_adminpassword+]");
            else
                document.getElementById('install').submit();
        });
    });
    var language = '[+install_language+]';
    var is_upgradeable = '[+is_upgradeable+]';
</script>
