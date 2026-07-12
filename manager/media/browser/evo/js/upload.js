// アップロードボタン・D&Dドロップ受付・進捗バー付き行のUI。
// アップロードのドロップ受付はOSからのファイルドラッグのみで、ブラウザ内の
// ファイル移動ドラッグ(dnd.jsのDND_TYPE)とはisOsFileDrag()で区別して共存する。

import { escapeHtml } from './utils.js';
import { isOsFileDrag } from './dnd.js';

/**
 * @param {object} ctx
 * @param {HTMLElement} ctx.uploadsEl
 * @param {HTMLElement} ctx.fileInputEl
 * @param {HTMLElement} ctx.uploadBtn
 * @param {HTMLElement} ctx.dropzoneEl
 * @param {object} ctx.state
 * @param {object} ctx.config
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
        var files = Array.prototype.slice.call(fileList);
        if (files.length === 0) {
            return;
        }

        var formData = new FormData();
        formData.set('action', 'upload');
        formData.set('type', ctx.state.type);
        formData.set('folder', ctx.state.folder);
        formData.set('csrf_token', ctx.config.csrfToken);
        files.forEach(function (file) {
            formData.append('files[]', file, file.name);
        });

        var rows = files.map(function (file) {
            return addUploadRow(file.name);
        });

        var xhr = new XMLHttpRequest();
        xhr.open('POST', ctx.config.apiUrl, true);
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
                    errorByName[err.name] = err.message;
                });
                files.forEach(function (file, i) {
                    var failed = errorByName[file.name];
                    rows[i].done(!failed, failed);
                });
            } else {
                rows.forEach(function (row) {
                    row.done(false, 'アップロードに失敗しました');
                });
            }
            ctx.onDone();
        });
        xhr.addEventListener('error', function () {
            rows.forEach(function (row) {
                row.done(false, '通信エラー');
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
        ctx.dropzoneEl.addEventListener('dragenter', function (e) {
            if (!isOsFileDrag(e)) {
                return;
            }
            e.preventDefault();
            dragCounter++;
            ctx.dropzoneEl.classList.add('is-dragover');
        });
        ctx.dropzoneEl.addEventListener('dragover', function (e) {
            if (!isOsFileDrag(e)) {
                return;
            }
            e.preventDefault();
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
