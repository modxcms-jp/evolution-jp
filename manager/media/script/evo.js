/**
 * 管理画面の jQuery 置き換え用の共通部品（Evo.ready / Evo.fetch / Evo.post）
 *
 * 置き換えの書き方は assets/docs/jquery-migration.md を参照。
 * - Evo.ready(fn): $j(function(){…}) の代わり。シェルが後から差し込んだインラインスクリプトでは
 *   DOMContentLoaded がすでに発火しているため、DOM の準備ができていれば待たずに実行する。
 *   jQuery 3 と同じく、呼び出したスクリプトの残りが終わってから非同期に実行する
 * - Evo.fetch(url, options): 管理画面の AJAX 用の fetch。シェルと同じヘッダーを付け、
 *   GET・HEAD 以外では X-CSRF-Token を付ける。セッションが切れていれば（X-Evo-Login: required）
 *   ログイン画面へ移り、Promise は解決しない（呼び出し側の後続処理やエラー表示を走らせない）
 * - Evo.post(url, data): $j.post の代わり。data はオブジェクト・FormData・URLSearchParams・文字列
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
        if (typeof data === 'string' || data instanceof FormData || data instanceof URLSearchParams) {
            return data;
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

    window.Evo = {
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
                if (!headers.has('X-Requested-With')) {
                    headers.set('X-Requested-With', 'XMLHttpRequest');
                }
                // サーバーはこのヘッダーがあるときだけ、セッション切れを X-Evo-Login で知らせる
                if (!headers.has('X-Evo-Shell')) {
                    headers.set('X-Evo-Shell', '1');
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
                    window.location.href = 'index.php';
                    return new Promise(function () {});
                }
                return response;
            });
        },

        post: function (url, data, options) {
            return window.Evo.fetch(url, Object.assign({}, options, { method: 'POST', body: toBody(data) }));
        }
    };
})();
