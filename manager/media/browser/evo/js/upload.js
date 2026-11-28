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
 * @param {(names: string[]) => boolean} ctx.confirmOverwrite - サーバーが code: exists と返したファイルの、保存される名前(targetName)を渡して呼ぶ。
 *        true ならそのファイルだけを overwrite=1 で送り直し、false なら保存しない
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

    function canUpload() {
        return ctx.state.writable && ctx.config.canUpload;
    }

    function uploadFiles(fileList) {
        // 書き込めないフォルダや、アップロードの権限が無いときは送らない(ドラッグ＆ドロップはここで止める)
        if (!canUpload()) {
            return;
        }
        var files = Array.prototype.slice.call(fileList);
        if (files.length === 0) {
            return;
        }
        sendFiles(files, ctx.state.folder, false);
    }

    // 1 回の要求には 1 ファイルだけ載せる。まとめて送ると合計が post_max_size や max_file_uploads を超え、
    // 1 枚ずつは上限内でも全部が失敗する。並べて送るのは UPLOAD_CONCURRENCY 件まで
    var UPLOAD_CONCURRENCY = 3;

    // folder は送る時点のフォルダ(送り直しの間に移っても同じ所へ送る)。overwrite が true なら同じ名前の上書きまで許す
    function sendFiles(files, folder, overwrite) {
        var jobs = files.map(function (file) {
            return { file: file, row: addUploadRow(file.name) };
        });
        var conflicts = [];
        var next = 0;
        var running = 0;

        function finishAll() {
            if (conflicts.length > 0 && ctx.confirmOverwrite(conflicts.map(function (c) { return c.targetName; }))) {
                conflicts.forEach(function (c) { c.row.dismiss(); });
                sendFiles(conflicts.map(function (c) { return c.file; }), folder, true);
            } else {
                conflicts.forEach(function (c) { c.row.done(false, c.message); });
            }
            ctx.onDone();
        }

        function pump() {
            while (running < UPLOAD_CONCURRENCY && next < jobs.length) {
                running++;
                sendOne(jobs[next++], folder, overwrite, function (conflict) {
                    if (conflict) {
                        conflicts.push(conflict);
                    }
                    running--;
                    if (next < jobs.length) {
                        pump();
                    } else if (running === 0) {
                        finishAll();
                    }
                });
            }
        }

        pump();
    }

    // 1 ファイルを送り、行を仕上げる。同じ名前で上書きの確認が要るときだけ、行を残したまま conflict を渡す
    function sendOne(job, folder, overwrite, callback) {
        var file = job.file;
        var row = job.row;

        // maxUploadSize が null のときは、本体では制限しない(サーバーの PHP の上限だけが効く)
        if (ctx.config.maxUploadSize != null && file.size > ctx.config.maxUploadSize) {
            row.done(false, t('fb_err_too_large'));
            callback(null);
            return;
        }

        var formData = withApiParams(ctx.config, new FormData());
        formData.set('action', 'upload');
        formData.set('folder', folder);
        if (overwrite) {
            formData.set('overwrite', '1');
        }
        formData.append('files[]', file, file.name);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', postUrl(ctx.config), true);
        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                row.setProgress(Math.round((e.loaded / e.total) * 100));
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

            if (!ok || !result) {
                // API の理由があればそれを、JSON でない応答は通信エラーとして出す
                row.done(false, responseError(result).message);
                callback(null);
                return;
            }
            var err = (result.errors || [])[0];
            if (err && err.code === 'exists' && !overwrite) {
                callback({ file: file, targetName: err.targetName || file.name, row: row, message: err.message });
                return;
            }
            row.done(!err, err && err.message);
            callback(null);
        });
        xhr.addEventListener('error', function () {
            row.done(false, t('fb_network_error'));
            callback(null);
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
        // 書き込めないフォルダでは、ドロップを受けない(ブラウザがファイルを開かないよう、ドラッグ自体は止める)
        ctx.dropzoneEl.addEventListener('dragenter', function (e) {
            if (!isOsFileDrag(e)) {
                return;
            }
            e.preventDefault();
            dragCounter++;
            if (canUpload()) {
                ctx.dropzoneEl.classList.add('is-dragover');
            }
        });
        ctx.dropzoneEl.addEventListener('dragover', function (e) {
            if (!isOsFileDrag(e)) {
                return;
            }
            e.preventDefault();
            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = canUpload() ? 'copy' : 'none';
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
