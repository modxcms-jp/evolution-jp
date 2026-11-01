// api.php とのJSON通信。configとstateは呼び出し側から明示的に渡す(暗黙の状態共有をしない)。

import { t } from './i18n.js';

export function apiUrl(config, params) {
    var query = Object.keys(params).map(function (key) {
        return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
    }).join('&');
    return config.apiUrl + '?' + query;
}

// API が返した理由(JSON の error.message)を運ぶエラー。通信エラーや JSON でない応答とは区別する
export function apiError(message) {
    var err = new Error(message);
    err.fromApi = true;
    return err;
}

// 失敗の通知の文言を決める唯一の場所。API の文言はそのまま、それ以外(通信エラー等)は前置き(prefixKey)を付ける
export function failureMessage(err, prefixKey) {
    if (err && err.fromApi) {
        return err.message;
    }
    return t(prefixKey, (err && err.message) || t('fb_network_error'));
}

// API の JSON でない応答や通信の失敗は、すべて既存の fb_network_error の文言の Error にそろえる
// fetch の失敗(reject)を fb_network_error の Error にそろえる、生の fetch の唯一の入口
export function apiFetch(url, options) {
    return fetch(url, Object.assign({ credentials: 'same-origin' }, options))
        .catch(function () {
            throw new Error(t('fb_network_error'));
        });
}

export function fetchJson(url, options) {
    return apiFetch(url, options)
        .then(function (res) {
            var contentType = res.headers.get('Content-Type') || '';
            if (contentType.indexOf('application/json') !== 0) {
                throw new Error(t('fb_network_error'));
            }
            return res.json().catch(function () {
                throw new Error(t('fb_network_error'));
            }).then(function (data) {
                if (!res.ok) {
                    var message = data && data.error && data.error.message;
                    throw message ? apiError(message) : new Error(t('fb_network_error'));
                }
                return data;
            });
        });
}

export function postForm(config, state, action, fields, folderOverride) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('type', state.type);
    body.set('folder', folderOverride != null ? folderOverride : state.folder);
    body.set('csrf_token', config.csrfToken);
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
    return fetchJson(config.apiUrl, { method: 'POST', body: body });
}
