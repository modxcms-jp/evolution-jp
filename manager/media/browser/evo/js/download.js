// 選んだファイルのダウンロード。POST(CSRF トークン付き)で受け、blob を a.download で保存させる。
// fetch で受けるので、エラー(JSON)を画面に出せる。2 件以上は API が zip にまとめて返す。
// ポップアップ(単独のページ)でも動くよう、管理画面の shell.js には頼らない。

import { escapeHtml } from './utils.js';
import { t } from './i18n.js';

// Content-Disposition から保存名を取り出す。filename*(RFC 5987)を先に見る
function parseFilename(disposition) {
    if (!disposition) {
        return '';
    }
    var star = /filename\*\s*=\s*UTF-8''([^;]+)/i.exec(disposition);
    if (star) {
        try {
            return decodeURIComponent(star[1].trim());
        } catch (e) {
            // 次の形式へ
        }
    }
    var plain = /filename\s*=\s*"([^"]*)"/i.exec(disposition) || /filename\s*=\s*([^;]+)/i.exec(disposition);
    return plain ? plain[1].trim() : '';
}

/**
 * @param {object} ctx
 * @param {HTMLElement} ctx.uploadsEl - 「準備中」の行を出す場所(アップロードの進み具合と同じ所)
 * @param {object} ctx.state
 * @param {object} ctx.config
 * @returns {{download: (names: string[]) => void}}
 */
export function createDownload(ctx) {
    function addRow(text) {
        var row = document.createElement('div');
        row.className = 'evo-fb-upload-row';
        row.innerHTML = '<span class="evo-fb-upload-name">' + escapeHtml(text) + '</span>';
        ctx.uploadsEl.appendChild(row);
        return {
            remove: function () {
                row.remove();
            },
            fail: function (message) {
                row.classList.add('is-error');
                row.querySelector('.evo-fb-upload-name').textContent = message;
                setTimeout(function () {
                    row.remove();
                }, 6000);
            }
        };
    }

    function save(blob, filename) {
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.hidden = true;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 10000);
    }

    function download(names) {
        if (!names || names.length === 0) {
            return;
        }
        var body = new URLSearchParams();
        body.set('action', 'download');
        body.set('type', ctx.state.type);
        body.set('folder', ctx.state.folder);
        body.set('csrf_token', ctx.config.csrfToken);
        names.forEach(function (name) {
            body.append('files[]', name);
        });

        var row = addRow(t('fb_download_preparing'));
        fetch(ctx.config.apiUrl, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) {
                var contentType = res.headers.get('Content-Type') || '';
                if (contentType.indexOf('application/json') === 0 || !res.ok) {
                    // エラーは JSON。CSRF トークンの不一致などは JSON でないので、状態コードを出す
                    return res.text().then(function (text) {
                        var message = 'HTTP ' + res.status;
                        try {
                            var data = JSON.parse(text);
                            message = (data && data.error && data.error.message) || message;
                        } catch (e) {
                            // JSON でなければ状態コードのまま
                        }
                        throw new Error(message);
                    });
                }
                var fallback = names.length === 1 ? names[0] : 'download.zip';
                var filename = parseFilename(res.headers.get('Content-Disposition')) || fallback;
                return res.blob().then(function (blob) {
                    save(blob, filename);
                    row.remove();
                });
            })
            .catch(function (err) {
                var message = err && err.message === 'Failed to fetch' ? t('fb_network_error') : (err && err.message) || '';
                row.fail(t('fb_download_failed', message));
            });
    }

    return { download: download };
}
