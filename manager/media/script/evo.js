/**
 * 管理画面の共通部品（Evo.ready / Evo.fetch / Evo.post / Evo.fieldRow / Evo.fieldPart / Evo.hideEmptyFieldGroups）
 *
 * 置き換えの書き方は assets/docs/jquery-migration.md を参照。
 * - Evo.ready(fn): $j(function(){…}) の代わり。シェルが後から差し込んだインラインスクリプトでは
 *   DOMContentLoaded がすでに発火しているため、DOM の準備ができていれば待たずに実行する。
 *   jQuery 3 と同じく、呼び出したスクリプトの残りが終わってから非同期に実行する
 * - Evo.fetch(url, options): 管理画面の AJAX 用の fetch。X-Requested-With を付け、
 *   GET・HEAD 以外では X-CSRF-Token を付ける。セッションが切れていれば（X-Evo-Login: required）
 *   ログイン画面へ移り、Promise は解決しない（呼び出し側の後続処理やエラー表示を走らせない）
 * - Evo.post(url, data): $j.post の代わり。data はオブジェクト・FormData・URLSearchParams・文字列
 * - Evo.fieldRow(field, root): 編集画面の欄の行（data-field 属性の付いた要素）。field が名前なら
 *   root（省略時は document）の中でその名前の行を、要素ならその要素が入っている行（入れ子なら
 *   いちばん外側）を返す。無ければ null
 * - Evo.fieldPart(field, part): その行の中の部分（data-field-part 属性が part の要素）。無ければ null
 * - Evo.hideEmptyFieldGroups(root): 見えている入力欄が無い欄のまとまり（data-field-group 属性の付いた要素）を隠す
 *
 * fetch は HTTP のエラー（404・500 など）でも reject しない。必要なら response.ok を確かめること。
 */
(function () {
    'use strict';

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function isSameOrigin(url) {
        try {
            return new URL(url, window.location.href).origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function toBody(data) {
        if (data === undefined || data === null) {
            return undefined;
        }
        if (data instanceof FormData || data instanceof URLSearchParams) {
            return data;
        }
        // 文字列のまま送ると text/plain になり、PHP の $_POST に入らない
        if (typeof data === 'string') {
            return new URLSearchParams(data);
        }
        const params = new URLSearchParams();
        Object.keys(data).forEach(function (key) {
            const value = data[key];
            if (Array.isArray(value)) {
                value.forEach(function (item) {
                    params.append(key + '[]', item);
                });
                return;
            }
            params.append(key, value === undefined || value === null ? '' : value);
        });
        return params;
    }

    // data-field が空の行（フィールドではない行）は対象にしない
    const FIELD_ROW = '[data-field]:not([data-field=""])';

    function fieldRow(field, root) {
        if (!field) {
            return null;
        }
        if (field.nodeType !== 1) {
            return (root || document).querySelector('[data-field="' + CSS.escape(field) + '"]');
        }
        // 行の中に別の欄（data-field）が入れ子になっているときは、いちばん外側の行
        let row = field.closest(FIELD_ROW);
        while (row && row.parentElement && row.parentElement.closest(FIELD_ROW)) {
            row = row.parentElement.closest(FIELD_ROW);
        }
        return row;
    }

    // 行の中の部分。入れ子の欄の部分は、その欄のものなので除く
    function fieldPart(field, part) {
        const row = fieldRow(field);
        if (!row) {
            return null;
        }
        const parts = row.querySelectorAll('[data-field-part="' + CSS.escape(part) + '"]');
        return Array.prototype.find.call(parts, function (el) {
            return el.closest(FIELD_ROW) === row;
        }) || null;
    }

    // 見えている入力欄が 1 つも無い欄のまとまり（data-field-group）を隠す。
    // 欄を隠す・ほかのタブへ移す処理（ManagerManager・QuickManager）の後に呼ぶ。隠れているかは style の display で見る
    const FIELD_CONTROLS = 'input:not([type="hidden"]), select, textarea, button';

    function hideEmptyFieldGroups(root) {
        (root || document).querySelectorAll('[data-field-group]').forEach(function (group) {
            const shown = Array.prototype.some.call(group.querySelectorAll(FIELD_CONTROLS), function (el) {
                for (let p = el; p && p !== group; p = p.parentElement) {
                    if (p.style.display === 'none') {
                        return false;
                    }
                }
                return true;
            });
            if (!shown) {
                group.style.display = 'none';
            }
        });
    }

    window.Evo = {
        // HTML として出す文脈（EvoDialog の message など）へ、利用者や外から来る値を入れるときに使う
        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        ready: function (fn) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () {
                    fn();
                }, { once: true });
                return;
            }
            window.setTimeout(fn, 0);
        },

        fetch: function (url, options) {
            const opts = Object.assign({}, options);
            const method = String(opts.method || 'GET').toUpperCase();
            const headers = new Headers(opts.headers);
            if (isSameOrigin(url)) {
                // サーバーはこのヘッダーで、セッション切れを X-Evo-Login で知らせる。
                // X-Evo-Shell はシェルの画面遷移の印（応答が断片になる）なので付けない
                if (!headers.has('X-Requested-With')) {
                    headers.set('X-Requested-With', 'XMLHttpRequest');
                }
                const token = getCsrfToken();
                if (method !== 'GET' && method !== 'HEAD' && token && !headers.has('X-CSRF-Token')) {
                    headers.set('X-CSRF-Token', token);
                }
            }
            opts.method = method;
            opts.headers = headers;
            opts.credentials = opts.credentials || 'same-origin';

            return window.fetch(url, opts).then(function (response) {
                if (response.headers.get('X-Evo-Login') === 'required') {
                    // 今の画面を読み直してログイン画面を出す（ログイン画面はこの URL のまま出て、ログイン後にこの画面へ戻る）。
                    // # を付けたままだと、同じ URL への代入でページが読み直されない
                    window.location.href = window.location.href.split('#')[0];
                    return new Promise(function () {});
                }
                return response;
            });
        },

        post: function (url, data, options) {
            return window.Evo.fetch(url, Object.assign({}, options, { method: 'POST', body: toBody(data) }));
        },

        fieldRow: fieldRow,

        fieldPart: fieldPart,

        hideEmptyFieldGroups: hideEmptyFieldGroups
    };
})();
