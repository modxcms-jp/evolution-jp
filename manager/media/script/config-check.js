// ようこそ画面の「設定の確認」の警告を隠す・対処する操作（welcome.static.php が読む）。
// シェルは画面を開き直すたびにスクリプトを動かし直すので、登録は 1 回だけにする。
if (!window.EvoConfigCheck) {
    window.EvoConfigCheck = {};
    (function () {
        // 警告の入った fieldset を隠す
        function hideFieldset(wrapper) {
            const fieldset = wrapper ? wrapper.closest('fieldset') : null;
            if (fieldset) {
                fieldset.style.display = 'none';
            }
        }

        // mutate_settings.ajax.php（a=118）へ送る。処理できたときだけ本文が true になる
        function post(params) {
            return Evo.post('index.php', Object.assign({a: '118'}, params)).then(function (response) {
                return response.ok ? response.text() : '';
            }).then(function (body) {
                return body.trim() === 'true';
            }).catch(function () {
                return false;
            });
        }

        // 言語ファイルの configcheck_hide_warning のリンク（data-config-check-hide="<key>"）。
        // 警告の本文は #<key>_warning_wrapper の中にある
        function hideWarning(key) {
            post({action: 'setsetting', key: '_hide_configcheck_' + key, value: '1'}).then(function (ok) {
                if (ok) {
                    hideFieldset(document.getElementById(key + '_warning_wrapper'));
                }
            });
        }

        // TemplateSwitcher の警告の「無効にする」「削除」（data-config-check-plugin="disable|delete"）
        document.addEventListener('click', function (e) {
            const hide = e.target.closest('[data-config-check-hide]');
            if (hide) {
                e.preventDefault();
                hideWarning(hide.dataset.configCheckHide);
                return;
            }
            const el = e.target.closest('[data-config-check-plugin]');
            if (!el) {
                return;
            }
            e.preventDefault();
            const id = el.dataset.pluginId;
            if (!el.dataset.confirm) {
                return;
            }
            const action = el.dataset.configCheckPlugin === 'delete' ? 'deleteplugin' : 'disableplugin';
            EvoDialog.confirm(el.dataset.confirm, '', function (confirmed) {
                if (confirmed) {
                    removePlugin(action, id);
                }
            });
        });

        function removePlugin(action, id) {
            post({action: action, id: id}).then(function (ok) {
                if (!ok) {
                    return;
                }
                const line = document.getElementById('templateswitcher_plugin_' + id);
                const wrapper = line ? line.closest('#templateswitcher_present_warning_wrapper') : null;
                if (line) {
                    line.remove();
                }
                // 対象が残っていなければ、警告の入った fieldset を隠す
                if (wrapper && !wrapper.querySelector('.templateswitcher-plugin')) {
                    hideFieldset(wrapper);
                }
            });
        }
    })();
}
