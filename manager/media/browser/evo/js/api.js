// api.php とのJSON通信。configとstateは呼び出し側から明示的に渡す(暗黙の状態共有をしない)。

export function apiUrl(config, params) {
    var query = Object.keys(params).map(function (key) {
        return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
    }).join('&');
    return config.apiUrl + '?' + query;
}

export function fetchJson(url, options) {
    return fetch(url, Object.assign({ credentials: 'same-origin' }, options))
        .then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) {
                    var message = (data && data.error && data.error.message) || ('HTTP ' + res.status);
                    throw new Error(message);
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
