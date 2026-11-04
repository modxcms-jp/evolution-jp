// api.php とのJSON通信。configとstateは呼び出し側から明示的に渡す(暗黙の状態共有をしない)。

import { t } from './i18n.js';

// config.apiParams(サーバーが作る。ピッカーは { type }、管理モードは { scope: 'manage' })は、すべての要求にそのまま付ける。
// 範囲(ピッカー・管理モード)を見て分けるのはサーバーだけで、ここでは中身を見ない
export function apiUrl(config, params) {
    var all = Object.assign({}, config.apiParams, params);
    var query = Object.keys(all).map(function (key) {
        return encodeURIComponent(key) + '=' + encodeURIComponent(all[key]);
    }).join('&');
    return config.apiUrl + '?' + query;
}

// POST の送り先。本文が post_max_size を超えると PHP は本文(scope など)を捨てるので、apiParams は URL にも付ける
// (付けないと、管理モードの要求がピッカーとして認可される)
export function postUrl(config) {
    return apiUrl(config, {});
}

// POST の本体(URLSearchParams か FormData)に apiParams と CSRF トークンを付けて返す
export function withApiParams(config, body) {
    Object.keys(config.apiParams || {}).forEach(function (key) {
        body.set(key, config.apiParams[key]);
    });
    body.set('csrf_token', config.csrfToken);
    return body;
}

// API が返した理由(JSON の error.message)を運ぶエラー。通信エラーや JSON でない応答とは区別する
export function apiError(message) {
    var err = new Error(message);
    err.fromApi = true;
    return err;
}

// 応答の本文(パース済みの JSON。JSON でなければ null)から Error を作る。
// API の error.message があれば apiError、無ければ(JSON でない応答など)通信エラー
export function responseError(data) {
    var message = data && data.error && data.error.message;
    return message ? apiError(message) : new Error(t('fb_network_error'));
}

// 書き込み系の操作の失敗の文言を決める場所。API の文言はそのまま、それ以外(通信エラー等)は前置き(prefixKey)を付ける
export function failureMessage(err, prefixKey) {
    if (err && err.fromApi) {
        return err.message;
    }
    return t(prefixKey, (err && err.message) || t('fb_network_error'));
}

// 読み込み系の失敗の文言。API の文言にも前置き(fb_load_failed)を付ける
export function loadFailureMessage(err) {
    return t('fb_load_failed', (err && err.message) || t('fb_network_error'));
}

// fetch の失敗(reject)を fb_network_error の Error にそろえる、生の fetch の唯一の入口
export function apiFetch(url, options) {
    return fetch(url, Object.assign({ credentials: 'same-origin' }, options))
        .catch(function () {
            throw new Error(t('fb_network_error'));
        });
}

// API の JSON でない応答や通信の失敗は、すべて既存の fb_network_error の文言の Error にそろえる
export function fetchJson(url, options) {
    return apiFetch(url, options)
        .then(function (res) {
            var contentType = res.headers.get('Content-Type') || '';
            var parsing = contentType.indexOf('application/json') === 0 ? res.json() : Promise.resolve(null);
            return parsing.catch(function () {
                return null;
            }).then(function (data) {
                if (!res.ok || data === null) {
                    throw responseError(data);
                }
                return data;
            });
        });
}

export function postForm(config, state, action, fields, folderOverride) {
    var body = withApiParams(config, new URLSearchParams());
    body.set('action', action);
    body.set('folder', folderOverride != null ? folderOverride : state.folder);
    Object.keys(fields || {}).forEach(function (key) {
        var val = fields[key];
        if (Array.isArray(val)) {
            val.forEach(function (v) {
                body.append(key, v);
            });
        } else {
            body.set(key, val);
        }
    });
    return fetchJson(postUrl(config), { method: 'POST', body: body });
}
