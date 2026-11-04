// アップロードボタン・D&Dドロップ受付・進捗バー付き行のUI。
// アップロードのドロップ受付はOSからのファイルドラッグのみで、ブラウザ内の
// ファイル移動ドラッグ(dnd.jsのDND_TYPE)とはisOsFileDrag()で区別して共存する。

import { escapeHtml } from './utils.js';
import { t } from './i18n.js';
import { isOsFileDrag } from './dnd.js';
import { postUrl, responseError, withApiParams } from './api.js';

/**
 * @param {object} ctx
 * @param {HTMLElement} ctx.uploadsEl
 * @param {HTMLElement} ctx.fileInputEl
 * @param {HTMLElement} ctx.uploadBtn
 * @param {HTMLElement} ctx.dropzoneEl
 * @param {object} ctx.state
 * @param {object} ctx.config
 * @param {(names: string[]) => boolean} ctx.confirmOverwrite - サーバーが「同じ名前がある」(code: exists)と返したファイルについて、
 *        サーバーが保存する名前(targetName)の一覧を渡して呼ぶ。true なら、そのファイルだけを overwrite=1 で送り直す。
 *        false なら、そのファイルは保存しない(別名で保存する範囲のサーバーは、この code を返さない)
 * @param {() => void} ctx.onDone - アップロード完了後の再読み込み
 */
export function createUpload(ctx) {
    function addUploadRow(name) {
        var row = document.createElement('div');
        row.className = 'evo-fb-upload-row';
        row.innerHTML = '' +
            '<span class="evo-fb-upload-name">' + escapeHtml(name) + '</span>' +
            '<progress class="evo-fb-upload-progress" value="0" max="100"></progress>';
        ctx.uploadsEl.appendChild(row);
        return {
            setProgress: function (percent) {
                row.querySelector('progress').value = percent;
            },
            // 結果を出さずに消す(同じ名前のファイルを上書きして送り直すとき、新しい行に置き換わる)
            dismiss: function () {
                row.remove();
            },
            done: function (ok, message) {
                row.classList.add(ok ? 'is-done' : 'is-error');
                if (!ok && message) {
                    row.querySelector('.evo-fb-upload-name').textContent = name + ' — ' + message;
                }
                setTimeout(function () {
                    row.remove();
                }, ok ? 1500 : 4000);
            }
        };
    }

    function uploadFiles(fileList) {
        // 書き込めないフォルダへは送らない(ボタンは使えなくしてあるが、ドラッグ＆ドロップはここで止める)
        if (!ctx.state.writable) {
            return;
        }
        var files = Array.prototype.slice.call(fileList);
        if (files.length === 0) {
            return;
        }
        sendFiles(files, ctx.state.folder, false);
    }

    // folder は送る時点のフォルダ(送り直しの間にフォルダを移っても、同じ所へ送る)。
    // overwrite が true のときは、同じ名前のファイルの上書きまで許して送る
    function sendFiles(files, folder, overwrite) {
        var formData = withApiParams(ctx.config, new FormData());
        formData.set('action', 'upload');
        formData.set('folder', folder);
        if (overwrite) {
            formData.set('overwrite', '1');
        }
        files.forEach(function (file) {
            formData.append('files[]', file, file.name);
        });

        var rows = files.map(function (file) {
            return addUploadRow(file.name);
        });

        var xhr = new XMLHttpRequest();
        xhr.open('POST', postUrl(ctx.config), true);
        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                var percent = Math.round((e.loaded / e.total) * 100);
                rows.forEach(function (row) {
                    row.setProgress(percent);
                });
            }
        });
        xhr.addEventListener('load', function () {
            var ok = xhr.status >= 200 && xhr.status < 300;
            var result = null;
            try {
                result = JSON.parse(xhr.responseText);
            } catch (e) {
                result = null;
            }

            if (ok && result) {
                var errorByName = {};
                (result.errors || []).forEach(function (err) {
                    errorByName[err.name] = err;
                });
                // 同じ名前があって保存しなかったファイル。上書きしてよいかを、保存される名前を並べて尋ねる
                var conflicts = [];
                files.forEach(function (file, i) {
                    var err = errorByName[file.name];
                    if (err && err.code === 'exists' && !overwrite) {
                        conflicts.push({ file: file, targetName: err.targetName || file.name, row: rows[i], message: err.message });
                        return;
                    }
                    rows[i].done(!err, err && err.message);
                });
                if (conflicts.length > 0 && ctx.confirmOverwrite(conflicts.map(function (c) { return c.targetName; }))) {
                    conflicts.forEach(function (c) { c.row.dismiss(); });
                    // 送り直しの完了で一覧を読み直す
                    sendFiles(conflicts.map(function (c) { return c.file; }), folder, true);
                    ctx.onDone();
                    return;
                }
                conflicts.forEach(function (c) { c.row.done(false, c.message); });
            } else {
                // API の理由があればそれを、JSON でない応答は通信エラーとして出す
                var reason = responseError(result).message;
                rows.forEach(function (row) {
                    row.done(false, reason);
                });
            }
            ctx.onDone();
        });
        xhr.addEventListener('error', function () {
            rows.forEach(function (row) {
                row.done(false, t('fb_network_error'));
            });
        });
        xhr.send(formData);
    }

    if (ctx.uploadBtn && ctx.fileInputEl) {
        ctx.uploadBtn.addEventListener('click', function () {
            ctx.fileInputEl.click();
        });
        ctx.fileInputEl.addEventListener('change', function () {
            uploadFiles(ctx.fileInputEl.files);
            ctx.fileInputEl.value = '';
        });
    }

    if (ctx.dropzoneEl) {
        var dragCounter = 0;
        // 書き込めないフォルダでは、ドロップを受けない。ブラウザがファイルを開いてしまわないよう、ドラッグ自体は止める
        ctx.dropzoneEl.addEventListener('dragenter', function (e) {
            if (!isOsFileDrag(e)) {
                return;
            }
            e.preventDefault();
            dragCounter++;
            if (ctx.state.writable) {
                ctx.dropzoneEl.classList.add('is-dragover');
            }
        });
        ctx.dropzoneEl.addEventListener('dragover', function (e) {
            if (!isOsFileDrag(e)) {
                return;
            }
            e.preventDefault();
            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = ctx.state.writable ? 'copy' : 'none';
            }
        });
        ctx.dropzoneEl.addEventListener('dragleave', function () {
            dragCounter = Math.max(0, dragCounter - 1);
            if (dragCounter === 0) {
                ctx.dropzoneEl.classList.remove('is-dragover');
            }
        });
        ctx.dropzoneEl.addEventListener('drop', function (e) {
            if (!isOsFileDrag(e)) {
                dragCounter = 0;
                ctx.dropzoneEl.classList.remove('is-dragover');
                return;
            }
            e.preventDefault();
            dragCounter = 0;
            ctx.dropzoneEl.classList.remove('is-dragover');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                uploadFiles(e.dataTransfer.files);
            }
        });
    }
}
