// 画像の簡易編集(トリミング・左右 90° の回転・数値でのリサイズ)。外部のライブラリは使わず、canvas の drawImage で書き出す。
// 書き出しの順は 回転 → トリミング → リサイズ。座標は「回転後の画像の画素」を基準にし、
// 画面の表示の倍率との換算は toImagePoint() と showCrop() の 2 か所だけで行う。
// 保存は API の saveImage へ FormData で送る。EXIF は canvas で書き出すと消える(画面にその旨を表示している)。

import { t } from './i18n.js';
import { fetchJson } from './api.js';
import { openOverlay } from './overlay.js';
import { withVersion } from './utils.js';

var JPEG_QUALITY = 0.92;
// 画面に表示する画像の高さの下限と上限(px)
var STAGE_MIN_HEIGHT = 140;
var STAGE_MAX_HEIGHT = 360;
// 画面の drag と見分けるための最小の移動量(表示の px)
var CLICK_SLOP = 3;

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

function extensionOf(name) {
    var dot = String(name).lastIndexOf('.');
    return dot === -1 ? '' : String(name).slice(dot + 1).toLowerCase();
}

// 拡張子に合わせた書き出しの形式。編集できる拡張子は jpg・jpeg・png だけ
function mimeFor(name) {
    return extensionOf(name) === 'png' ? 'image/png' : 'image/jpeg';
}

// 回転(右回り 90° の回数)を、元の画像を回転後の座標へ写す変換として ctx に掛ける
function applyRotation(g, rot, srcW, srcH) {
    if (rot === 1) {
        g.translate(srcH, 0);
        g.rotate(Math.PI / 2);
    } else if (rot === 2) {
        g.translate(srcW, srcH);
        g.rotate(Math.PI);
    } else if (rot === 3) {
        g.translate(0, srcW);
        g.rotate(Math.PI * 3 / 2);
    }
}

function makeCanvas(width, height, opaque) {
    var canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    var g = canvas.getContext('2d');
    g.imageSmoothingEnabled = true;
    g.imageSmoothingQuality = 'high';
    if (opaque) {
        // JPEG は透過を持てない。縁の半透明が黒くならないよう、白で塗っておく
        g.fillStyle = '#fff';
        g.fillRect(0, 0, width, height);
    }
    return canvas;
}

// 大きな canvas のメモリを、変数が消えるのを待たずに手放す
function releaseCanvas(canvas) {
    if (canvas) {
        canvas.width = 0;
        canvas.height = 0;
    }
}

// 大きく縮めるときは、1 回に半分までにして段階的に縮める(一度に縮めると粗くなる)
function nextDimension(current, target) {
    return current > target * 2 ? Math.ceil(current / 2) : target;
}

/**
 * @param {object} ctx
 * @param {HTMLElement} ctx.overlayEl - 編集のダイアログの外側の要素(中の要素は data-role で探す)
 * @param {object} ctx.state - type / folder
 * @param {object} ctx.config - apiUrl / csrfToken / editableExtensions / editMaxDimension / editMaxPixels / maxUploadSize
 * @param {(name: string) => void} ctx.onSaved - 保存できたとき。保存した名前を渡す
 * @returns {{open: (file: object) => void, canEdit: (file: object) => boolean}}
 */
export function createImageEditor(ctx) {
    var overlayEl = ctx.overlayEl;
    function el(role) {
        return overlayEl.querySelector('[data-role="' + role + '"]');
    }
    var panelEl = el('edit-panel');
    var stageEl = el('edit-stage');
    var wrapEl = el('edit-wrap');
    var canvasEl = el('edit-canvas');
    var cropEl = el('edit-crop');
    var nameEl = el('edit-name');
    var statusEl = el('edit-status');
    var errorEl = el('edit-error');
    var cancelBtn = el('edit-cancel');
    var saveBtn = el('edit-save');
    var keepRatioEl = el('edit-keep-ratio');
    var cropInputs = { x: el('edit-crop-x'), y: el('edit-crop-y'), w: el('edit-crop-w'), h: el('edit-crop-h') };
    var outInputs = { w: el('edit-out-w'), h: el('edit-out-h') };
    var maxDimension = ctx.config.editMaxDimension || 4096;

    // 開いている間の状態。閉じたら release() ですべて手放す
    var closeOverlay = null;
    // 読み込み中の画像。読み込めたら img になる(古い読み込みの完了を無視するため)
    var loadingImg = null;
    var img = null;
    var target = '';
    var folder = '';
    var srcW = 0;
    var srcH = 0;
    var rot = 0;
    // トリミングの範囲 { x, y, w, h }(回転後の画像の画素)。null は画像全体
    var crop = null;
    // リサイズ後の大きさ { w, h }。null はトリミングの範囲を、長い辺の上限に収めた大きさ
    var outSize = null;
    var busy = false;
    var drag = null;

    function canEdit(file) {
        if (!file || !file.url || (ctx.config.editableExtensions || []).indexOf(extensionOf(file.name)) === -1) {
            return false;
        }
        // 画素数が多すぎる画像は、ブラウザが展開するときのメモリが足りなくなるので編集しない(寸法が分からないものは出す)
        if (file.width && file.height && file.width * file.height > (ctx.config.editMaxPixels || Infinity)) {
            return false;
        }
        // 別のオリジンの画像は、canvas が汚染されて書き出せない(toBlob が例外になる)ので編集できない
        try {
            return new URL(file.url, window.location.href).origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    // --- 寸法 ---

    // 回転後の画像の大きさ
    function rotatedWidth() {
        return rot % 2 === 1 ? srcH : srcW;
    }

    function rotatedHeight() {
        return rot % 2 === 1 ? srcW : srcH;
    }

    function cropRect() {
        return crop || { x: 0, y: 0, w: rotatedWidth(), h: rotatedHeight() };
    }

    // 元より大きくせず、長い辺を上限に収める倍率(1 以下)
    function fitScale(w, h) {
        return Math.min(1, maxDimension / Math.max(w, h));
    }

    function defaultOut(c) {
        var f = fitScale(c.w, c.h);
        return { w: Math.max(1, Math.floor(c.w * f)), h: Math.max(1, Math.floor(c.h * f)) };
    }

    function effectiveOut() {
        return outSize || defaultOut(cropRect());
    }

    // --- 画面 ---

    function showError(message) {
        errorEl.textContent = message || '';
        errorEl.hidden = !message;
    }

    function setBusy(value) {
        busy = value;
        statusEl.textContent = value ? t('fb_edit_saving') : '';
        panelEl.querySelectorAll('button, input').forEach(function (control) {
            control.disabled = value;
        });
        if (!value) {
            updateControls();
        }
    }

    // 回転後の画像を、表示の大きさに縮めて canvas に描く
    function renderStage() {
        var rw = rotatedWidth();
        var rh = rotatedHeight();
        var maxW = Math.max(120, stageEl.clientWidth - 16);
        // ダイアログ(ファイルブラウザの中に重なる)に操作欄も収まるよう、ファイルブラウザの高さに応じて決める
        var maxH = clamp(Math.round(overlayEl.clientHeight * 0.36), STAGE_MIN_HEIGHT, STAGE_MAX_HEIGHT);
        var scale = Math.min(1, maxW / rw, maxH / rh);
        var dispW = Math.max(1, Math.round(rw * scale));
        var dispH = Math.max(1, Math.round(rh * scale));
        canvasEl.width = dispW;
        canvasEl.height = dispH;
        canvasEl.style.width = dispW + 'px';
        canvasEl.style.height = dispH + 'px';
        var g = canvasEl.getContext('2d');
        g.imageSmoothingEnabled = true;
        g.imageSmoothingQuality = 'high';
        g.scale(dispW / rw, dispH / rh);
        applyRotation(g, rot, srcW, srcH);
        g.drawImage(img, 0, 0);
        updateControls();
    }

    // 画面の座標(clientX/Y)を、回転後の画像の画素へ換算する(範囲の外は端に寄せる)
    function toImagePoint(e) {
        var rect = canvasEl.getBoundingClientRect();
        var rw = rotatedWidth();
        var rh = rotatedHeight();
        return {
            x: clamp((e.clientX - rect.left) * rw / rect.width, 0, rw),
            y: clamp((e.clientY - rect.top) * rh / rect.height, 0, rh)
        };
    }

    // トリミングの範囲(回転後の画像の画素)を、表示の位置に直して枠を出す
    function showCrop() {
        if (!crop) {
            cropEl.hidden = true;
            return;
        }
        var kx = canvasEl.clientWidth / rotatedWidth();
        var ky = canvasEl.clientHeight / rotatedHeight();
        cropEl.style.left = (crop.x * kx) + 'px';
        cropEl.style.top = (crop.y * ky) + 'px';
        cropEl.style.width = (crop.w * kx) + 'px';
        cropEl.style.height = (crop.h * ky) + 'px';
        cropEl.hidden = false;
    }

    function updateControls() {
        var c = cropRect();
        var o = effectiveOut();
        var rw = rotatedWidth();
        var rh = rotatedHeight();
        cropInputs.x.value = c.x;
        cropInputs.y.value = c.y;
        cropInputs.w.value = c.w;
        cropInputs.h.value = c.h;
        cropInputs.x.max = rw - 1;
        cropInputs.y.max = rh - 1;
        cropInputs.w.max = rw;
        cropInputs.h.max = rh;
        outInputs.w.value = o.w;
        outInputs.h.value = o.h;
        outInputs.w.max = Math.min(c.w, maxDimension);
        outInputs.h.max = Math.min(c.h, maxDimension);
        showCrop();
    }

    // トリミングの範囲が変わったら、リサイズは範囲に合わせた大きさに戻す
    function setCrop(next) {
        var rw = rotatedWidth();
        var rh = rotatedHeight();
        var whole = next && next.x === 0 && next.y === 0 && next.w === rw && next.h === rh;
        crop = next && !whole && next.w >= 1 && next.h >= 1 ? next : null;
        outSize = null;
        updateControls();
    }

    function rotate(direction) {
        if (!img || busy) {
            return;
        }
        rot = (rot + direction + 4) % 4;
        crop = null;
        outSize = null;
        renderStage();
    }

    function onCropInput(key) {
        var raw = parseInt(cropInputs[key].value, 10);
        if (!img || !isFinite(raw)) {
            updateControls();
            return;
        }
        var rw = rotatedWidth();
        var rh = rotatedHeight();
        var c = Object.assign({}, cropRect());
        // x・y を変えて範囲が画像の外へ出るときは、入力した位置を保ち、幅・高さを縮める
        if (key === 'w') {
            c.w = clamp(raw, 1, rw - c.x);
        } else if (key === 'h') {
            c.h = clamp(raw, 1, rh - c.y);
        } else if (key === 'x') {
            c.x = clamp(raw, 0, rw - 1);
            c.w = Math.min(c.w, rw - c.x);
        } else {
            c.y = clamp(raw, 0, rh - 1);
            c.h = Math.min(c.h, rh - c.y);
        }
        setCrop(c);
    }

    // key: 'w' か 'h'。縦横比を保つときは、変えなかった側を計算で合わせる。元より大きくはできず、長い辺は上限まで
    function onOutInput(key) {
        var raw = parseInt(outInputs[key].value, 10);
        if (!img || !isFinite(raw) || raw < 1) {
            updateControls();
            return;
        }
        var c = cropRect();
        var cur = effectiveOut();
        var keep = keepRatioEl.checked;
        var f = fitScale(c.w, c.h);
        var maxW = keep ? Math.max(1, Math.floor(c.w * f)) : Math.min(c.w, maxDimension);
        var maxH = keep ? Math.max(1, Math.floor(c.h * f)) : Math.min(c.h, maxDimension);
        var w = cur.w;
        var h = cur.h;
        if (key === 'w') {
            w = clamp(raw, 1, maxW);
            h = keep ? clamp(Math.round(w * c.h / c.w), 1, maxH) : cur.h;
        } else {
            h = clamp(raw, 1, maxH);
            w = keep ? clamp(Math.round(h * c.w / c.h), 1, maxW) : cur.w;
        }
        outSize = { w: w, h: h };
        updateControls();
    }

    // --- トリミングの範囲のドラッグ ---

    function onPointerDown(e) {
        if (!img || busy || e.button !== 0) {
            return;
        }
        e.preventDefault();
        drag = { id: e.pointerId, start: toImagePoint(e), x: e.clientX, y: e.clientY, moved: false };
        wrapEl.setPointerCapture(e.pointerId);
    }

    function onPointerMove(e) {
        if (!drag || e.pointerId !== drag.id) {
            return;
        }
        if (!drag.moved && Math.abs(e.clientX - drag.x) < CLICK_SLOP && Math.abs(e.clientY - drag.y) < CLICK_SLOP) {
            return;
        }
        drag.moved = true;
        var p = toImagePoint(e);
        var x0 = Math.round(Math.min(drag.start.x, p.x));
        var y0 = Math.round(Math.min(drag.start.y, p.y));
        var x1 = Math.round(Math.max(drag.start.x, p.x));
        var y1 = Math.round(Math.max(drag.start.y, p.y));
        setCrop({ x: x0, y: y0, w: x1 - x0, h: y1 - y0 });
    }

    function onPointerEnd(e) {
        if (!drag || e.pointerId !== drag.id) {
            return;
        }
        // 動かさずに離した(クリック)ときは、選択を解除する
        if (!drag.moved) {
            setCrop(null);
        }
        drag = null;
    }

    wrapEl.addEventListener('pointerdown', onPointerDown);
    wrapEl.addEventListener('pointermove', onPointerMove);
    wrapEl.addEventListener('pointerup', onPointerEnd);
    wrapEl.addEventListener('pointercancel', onPointerEnd);

    // --- 書き出し ---

    // 回転 → トリミング → リサイズ の順に canvas へ描き、指定の形式の Blob にする
    function exportBlob(mime) {
        return new Promise(function (resolve, reject) {
            var c = cropRect();
            var o = effectiveOut();
            var opaque = mime === 'image/jpeg';
            var cur = null;
            try {
                // 元の画像から、回転・トリミング・最初の縮小を 1 回の描画で行い、大きな作業用の canvas を作らない
                var w = nextDimension(c.w, o.w);
                var h = nextDimension(c.h, o.h);
                cur = makeCanvas(w, h, opaque);
                var g = cur.getContext('2d');
                g.scale(w / c.w, h / c.h);
                g.translate(-c.x, -c.y);
                applyRotation(g, rot, srcW, srcH);
                g.drawImage(img, 0, 0);
                while (w !== o.w || h !== o.h) {
                    var nw = nextDimension(w, o.w);
                    var nh = nextDimension(h, o.h);
                    var next = makeCanvas(nw, nh, opaque);
                    next.getContext('2d').drawImage(cur, 0, 0, w, h, 0, 0, nw, nh);
                    releaseCanvas(cur);
                    cur = next;
                    w = nw;
                    h = nh;
                }
            } catch (err) {
                releaseCanvas(cur);
                reject(err);
                return;
            }
            var done = cur;
            done.toBlob(function (blob) {
                releaseCanvas(done);
                // ブラウザが求めた形式で書き出せないと、別の形式(PNG など)になる。拡張子と中身が食い違うので保存しない
                if (!blob || blob.type !== mime) {
                    reject(new Error(t('fb_edit_encode_failed')));
                    return;
                }
                resolve(blob);
            }, mime, opaque ? JPEG_QUALITY : undefined);
        });
    }

    function save() {
        if (!img || busy) {
            return;
        }
        var checked = overlayEl.querySelector('[data-role="edit-mode"]:checked');
        var mode = checked && checked.value === 'overwrite' ? 'overwrite' : 'copy';
        if (mode === 'overwrite' && !window.confirm(t('fb_edit_overwrite_confirm', target))) {
            return;
        }
        showError('');
        setBusy(true);
        var savedFolder = folder;
        var savedTarget = target;
        exportBlob(mimeFor(savedTarget))
            .then(function (blob) {
                if (blob.size > ctx.config.maxUploadSize) {
                    throw new Error(t('fb_err_too_large'));
                }
                var body = new FormData();
                body.set('action', 'saveImage');
                body.set('type', ctx.state.type);
                body.set('folder', savedFolder);
                body.set('csrf_token', ctx.config.csrfToken);
                body.set('mode', mode);
                body.set('target', savedTarget);
                body.set('image', blob, savedTarget);
                return fetchJson(ctx.config.apiUrl, { method: 'POST', body: body });
            })
            .then(function (result) {
                setBusy(false);
                close(true);
                ctx.onSaved(result.name);
            })
            .catch(function (err) {
                setBusy(false);
                var message = err && err.message === 'Failed to fetch' ? t('fb_network_error') : (err && err.message) || '';
                showError(t('fb_edit_save_failed', message));
                saveBtn.focus({ preventScroll: true });
            });
    }

    // --- 開く・閉じる ---

    function release() {
        if (loadingImg) {
            loadingImg.onload = null;
            loadingImg.onerror = null;
            loadingImg.removeAttribute('src');
        }
        loadingImg = null;
        img = null;
        crop = null;
        outSize = null;
        drag = null;
        // 表示用の canvas の画素も手放す
        canvasEl.width = 0;
        canvasEl.height = 0;
        cropEl.hidden = true;
    }

    function close(restoreFocus) {
        if (!closeOverlay) {
            return;
        }
        var fn = closeOverlay;
        closeOverlay = null;
        fn(restoreFocus);
        release();
    }

    // 保存の通信中は、閉じない(閉じても保存は進み、結果を受ける先が無くなるため)
    function cancel() {
        if (!busy) {
            close(true);
        }
    }

    function open(file) {
        if (closeOverlay || !canEdit(file)) {
            return;
        }
        target = file.name;
        folder = ctx.state.folder;
        rot = 0;
        crop = null;
        outSize = null;
        keepRatioEl.checked = true;
        overlayEl.querySelector('[data-role="edit-mode"][value="copy"]').checked = true;
        nameEl.textContent = file.name;
        showError('');
        statusEl.textContent = '';
        saveBtn.disabled = true;
        closeOverlay = openOverlay(overlayEl, panelEl, null, { onEscape: cancel });

        var loading = new Image();
        loadingImg = loading;
        loading.onload = function () {
            if (loadingImg !== loading) {
                return;
            }
            srcW = loading.naturalWidth;
            srcH = loading.naturalHeight;
            if (!srcW || !srcH) {
                showError(t('fb_edit_load_failed'));
                return;
            }
            img = loading;
            saveBtn.disabled = false;
            renderStage();
        };
        loading.onerror = function () {
            if (loadingImg === loading) {
                showError(t('fb_edit_load_failed'));
            }
        };
        // ブラウザのキャッシュに古い画像が残らないよう、更新時刻を付けて読む
        loading.src = withVersion(file.url, file.mtime);
    }

    el('edit-rotate-left').addEventListener('click', function () { rotate(-1); });
    el('edit-rotate-right').addEventListener('click', function () { rotate(1); });
    el('edit-crop-clear').addEventListener('click', function () {
        if (img && !busy) {
            setCrop(null);
        }
    });
    Object.keys(cropInputs).forEach(function (key) {
        cropInputs[key].addEventListener('change', function () { onCropInput(key); });
    });
    Object.keys(outInputs).forEach(function (key) {
        outInputs[key].addEventListener('change', function () { onOutInput(key); });
    });
    keepRatioEl.addEventListener('change', function () {
        if (keepRatioEl.checked && img) {
            onOutInput('w');
        }
    });
    cancelBtn.addEventListener('click', cancel);
    saveBtn.addEventListener('click', save);

    return { open: open, canEdit: canEdit };
}
