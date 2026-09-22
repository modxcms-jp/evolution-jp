/**
 * 管理画面のCSRFトークン自動付与
 *
 * トークンは header.inc.php が出力する <meta name="csrf-token"> から取得する。
 * 画面ごとの対応に頼らず、送信経路によらず同一オリジンへのPOSTに必ずトークンを付ける。
 * - フォーム: submitイベント(クリック/Enter/requestSubmit)と form.submit() 直接呼び出しの両方で、
 *   送信直前に hidden の csrf_token を補う。AJAX遷移で後から差し込まれたフォームや、
 *   target 付きでシェルを経由しないネイティブ送信も対象になる
 * - XMLHttpRequest(jQuery AJAX含む) / fetch: X-CSRF-Token ヘッダを付ける。
 *   呼び出し側が既に付けている場合は重複させない
 *
 * shell.js が HTMLFormElement.prototype.submit を退避して使うため、shell.js より先に読み込むこと。
 */
(function () {
    'use strict';

    var FIELD_NAME = 'csrf_token';
    var HEADER_NAME = 'X-CSRF-Token';

    function getToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function isSameOrigin(url) {
        try {
            return new URL(url, window.location.href).origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function isUnsafeMethod(method) {
        return ['GET', 'HEAD', 'OPTIONS'].indexOf(String(method || 'GET').toUpperCase()) === -1;
    }

    function ensureFormToken(form) {
        if (!form || String(form.getAttribute('method') || '').toLowerCase() !== 'post') {
            return;
        }
        // form.action は name="action" の入力要素に隠されるため属性から読む
        if (!isSameOrigin(form.getAttribute('action') || window.location.href)) {
            return;
        }
        var token = getToken();
        if (!token) {
            return;
        }
        var field = form.querySelector('input[name="' + FIELD_NAME + '"]');
        if (!field) {
            field = document.createElement('input');
            field.type = 'hidden';
            field.name = FIELD_NAME;
            form.insertBefore(field, form.firstChild);
        }
        if (!field.value) {
            field.value = token;
        }
    }

    // クリック/Enter/requestSubmit。shell.js等のバブリング段階のハンドラがFormDataを作る前に補う
    document.addEventListener('submit', function (e) {
        if (e.target && e.target.tagName === 'FORM') {
            ensureFormToken(e.target);
        }
    }, true);

    // form.submit() / jQuery('#form').submit() はsubmitイベントを発火しない
    var formSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        ensureFormToken(this);
        return formSubmit.call(this);
    };

    // XMLHttpRequest(jQuery AJAXもここを通る)。ajaxSetupのbeforeSendは
    // 呼び出し側のbeforeSend指定で上書きされるため、XHR自体で付与する
    var xhrOpen = XMLHttpRequest.prototype.open;
    var xhrSetRequestHeader = XMLHttpRequest.prototype.setRequestHeader;
    var xhrSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function (method, url) {
        this._evoCsrf = { method: method, url: url, hasHeader: false };
        return xhrOpen.apply(this, arguments);
    };

    XMLHttpRequest.prototype.setRequestHeader = function (name, value) {
        if (this._evoCsrf && String(name).toLowerCase() === HEADER_NAME.toLowerCase()) {
            this._evoCsrf.hasHeader = true;
        }
        return xhrSetRequestHeader.apply(this, arguments);
    };

    XMLHttpRequest.prototype.send = function () {
        var info = this._evoCsrf;
        if (info && !info.hasHeader && isUnsafeMethod(info.method) && isSameOrigin(info.url)) {
            var token = getToken();
            if (token) {
                xhrSetRequestHeader.call(this, HEADER_NAME, token);
            }
        }
        return xhrSend.apply(this, arguments);
    };

    if (typeof window.fetch === 'function') {
        var nativeFetch = window.fetch;
        window.fetch = function (input, init) {
            var isRequest = typeof Request !== 'undefined' && input instanceof Request;
            var method = (init && init.method) || (isRequest ? input.method : 'GET');
            var url = isRequest ? input.url : String(input);
            if (isUnsafeMethod(method) && isSameOrigin(url)) {
                var headers = new Headers((init && init.headers) || (isRequest ? input.headers : undefined));
                var token = getToken();
                if (token && !headers.has(HEADER_NAME)) {
                    headers.set(HEADER_NAME, token);
                    init = Object.assign({}, init, { headers: headers });
                }
            }
            return nativeFetch.call(window, input, init);
        };
    }
})();
