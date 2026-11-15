// 画像の簡易編集(トリミング・左右 90° の回転・数値でのリサイズ)。外部のライブラリは使わず、canvas の drawImage で書き出す。
// 編集の途中はサーバーのファイルに触れず、操作の結果はその場でプレビューに出す。保存のときに、まとめて 1 回だけ書き出す。
// 書き出しの順は 回転 → トリミング → リサイズ。座標は「回転後の画像の画素」を基準にし、
// 画面の表示の倍率との換算は toImagePoint() と showCrop() の 2 か所だけで行う。枠の計算は js/cropBox.js にまとめている。
// 保存は API の saveImage へ FormData で送る。EXIF は canvas で書き出すと消える(画面にその旨を表示している)。

import { t } from './i18n.js';
import { failureMessage, fetchJson, postUrl, withApiParams } from './api.js';
import { openOverlay } from './overlay.js';
import { withVersion } from './utils.js';
import {
    fitRatioInside,
    matchesRatio,
    moveRect,
    normalizeRect,
    resizeCorner,
    resizeEdge,
    resizeFromAnchor,
    rotateRect,
    swapOrientation,
    wholeRect
} from './cropBox.js';

var JPEG_QUALITY = 0.92;
// 小さな画像を拡大して表示する倍率の上限(細かい範囲を選べるようにするため。保存する画像は拡大しない)
var STAGE_MAX_ZOOM = 4;
// ドラッグとして扱い始める移動量(表示の px)。クリックやダブルクリックのわずかなぶれで範囲が動かないようにする
var CLICK_SLOP = 3;
// トリミングの枠の幅・高さの最小(表示の px)。つまみが重なって操作できなくならないようにする
var MIN_CROP_DISPLAY = 16;
// 縦横比の選択肢(幅:高さ)。ボタンの data-ratio と同じ
var RATIOS = { '1:1': [1, 1], '4:3': [4, 3], '3:2': [3, 2], '16:9': [16, 9] };

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
 * @param {object} ctx.state - folder
 * @param {object} ctx.config - apiUrl / apiParams / csrfToken / editableExtensions / editMaxDimension / editMaxPixels / maxUploadSize
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
    var holeEl = el('edit-shade-hole');
    var nameEl = el('edit-name');
    var statusEl = el('edit-status');
    var errorEl = el('edit-error');
    var summaryEl = el('edit-summary');
    var cancelBtn = el('edit-cancel');
    var saveBtn = el('edit-save');
    var keepRatioEl = el('edit-keep-ratio');
    var ratioBtns = Array.from(overlayEl.querySelectorAll('[data-role="edit-ratio"]'));
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
    // トリミングの範囲 { x, y, w, h }(回転後の画像の画素)。最初は画像全体
    var crop = null;
    // 縦横比のボタンで選んだ比率。ratioKey は RATIOS のキー(null は選んでいない)、ratioPortrait は縦長に向けているか(3:4 など)。
    // 縦横比は固定しない。ボタンを選択中と表示するのは、範囲が実際にこの比率のときだけ(ratioShown())。
    // 選んだ比率を手放すのは、範囲がこの比率から外れて操作を終えたとき(reconcileRatio())。ドラッグの途中で外れても、戻れば表示も戻る
    var ratioKey = null;
    var ratioPortrait = false;
    // 範囲内のダブルクリックで画像全体にしたときの、元の範囲と縦横比の選択。もう一度ダブルクリックすると戻す。
    // 範囲を別に変えたときと回転したときは手放す(往復は、途中で別の編集をしない間だけ)
    var restore = null;
    // リサイズの指定 { w, h }。null は「トリミングの範囲を、長い辺の上限に収めた大きさ」。
    // outKey は最後に入力した辺。縦横比を保つときは、範囲が変わってもこの辺の値を保ち、もう一方を範囲の比率で決める
    var outSize = null;
    var outKey = 'w';
    var keepRatio = true;
    var busy = false;
    var drag = null;
    var resizeObserver = null;
    var renderFrame = 0;

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
    function bounds() {
        return rot % 2 === 1 ? { w: srcH, h: srcW } : { w: srcW, h: srcH };
    }

    function activeRatio() {
        var pair = RATIOS[ratioKey];
        if (!pair) {
            return null;
        }
        return ratioPortrait ? pair[1] / pair[0] : pair[0] / pair[1];
    }

    // 範囲が、選んだ比率になっているか(画素の丸めの差は同じとみなす)
    function ratioShown() {
        var ratio = activeRatio();
        return !!ratio && matchesRatio(crop, ratio);
    }

    // 表示の 1 画素あたりの、画像の画素の数
    function displayScale() {
        return canvasEl.clientWidth ? bounds().w / canvasEl.clientWidth : 1;
    }

    function minCropSize() {
        var b = bounds();
        return Math.min(b.w, b.h, Math.max(1, Math.ceil(MIN_CROP_DISPLAY * displayScale())));
    }

    // 元より大きくせず、長い辺を上限に収める倍率(1 以下)
    function fitScale(w, h) {
        return Math.min(1, maxDimension / Math.max(w, h));
    }

    // リサイズ後の大きさ。keep は縦横比を保つか(切り替えの直前の値で求めるときに渡す)
    function resolveOut(c, keep) {
        var f = fitScale(c.w, c.h);
        var keptW = Math.max(1, Math.floor(c.w * f));
        var keptH = Math.max(1, Math.floor(c.h * f));
        if (!outSize) {
            return { w: keptW, h: keptH };
        }
        if (!keep) {
            return {
                w: clamp(outSize.w, 1, Math.min(c.w, maxDimension)),
                h: clamp(outSize.h, 1, Math.min(c.h, maxDimension))
            };
        }
        if (outKey === 'w') {
            var w = clamp(outSize.w, 1, keptW);
            return { w: w, h: clamp(Math.round(w * c.h / c.w), 1, keptH) };
        }
        var h = clamp(outSize.h, 1, keptH);
        return { w: clamp(Math.round(h * c.w / c.h), 1, keptW), h: h };
    }

    function effectiveOut() {
        return resolveOut(crop, keepRatio);
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

    // 回転後の画像を、表示の領域に収まる大きさで canvas に描く。ダイアログの大きさが変わったときも描き直す
    function renderStage() {
        renderFrame = 0;
        if (!img) {
            return;
        }
        var b = bounds();
        var style = window.getComputedStyle(stageEl);
        var maxW = stageEl.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
        var maxH = stageEl.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom);
        if (maxW < 1 || maxH < 1) {
            return;
        }
        var scale = Math.min(STAGE_MAX_ZOOM, maxW / b.w, maxH / b.h);
        var dispW = Math.max(1, Math.floor(b.w * scale));
        var dispH = Math.max(1, Math.floor(b.h * scale));
        // 高精細の画面でもぼやけないよう、canvas の画素は表示の大きさ × devicePixelRatio にする(元の画素数は超えない)
        var dpr = window.devicePixelRatio || 1;
        var pixW = Math.max(1, Math.min(b.w, Math.round(dispW * dpr)));
        var pixH = Math.max(1, Math.min(b.h, Math.round(dispH * dpr)));
        canvasEl.width = pixW;
        canvasEl.height = pixH;
        canvasEl.style.width = dispW + 'px';
        canvasEl.style.height = dispH + 'px';
        var g = canvasEl.getContext('2d');
        g.imageSmoothingEnabled = true;
        g.imageSmoothingQuality = 'high';
        g.scale(pixW / b.w, pixH / b.h);
        applyRotation(g, rot, srcW, srcH);
        g.drawImage(img, 0, 0);
        updateControls();
    }

    function scheduleRender() {
        if (!renderFrame) {
            renderFrame = window.requestAnimationFrame(renderStage);
        }
    }

    // 画面の座標(clientX/Y)を、回転後の画像の画素へ換算する。clampToImage のときは範囲の外を端に寄せる
    function toImagePoint(e, clampToImage) {
        var rect = canvasEl.getBoundingClientRect();
        var b = bounds();
        var x = (e.clientX - rect.left) * b.w / rect.width;
        var y = (e.clientY - rect.top) * b.h / rect.height;
        return clampToImage ? { x: clamp(x, 0, b.w), y: clamp(y, 0, b.h) } : { x: x, y: y };
    }

    // トリミングの範囲(回転後の画像の画素)を、表示の位置に直して枠と外側の暗い部分を出す
    function showCrop() {
        if (!img || !canvasEl.clientWidth) {
            cropEl.hidden = true;
            holeEl.hidden = true;
            return;
        }
        var k = 1 / displayScale();
        [cropEl, holeEl].forEach(function (box) {
            box.style.left = (crop.x * k) + 'px';
            box.style.top = (crop.y * k) + 'px';
            box.style.width = (crop.w * k) + 'px';
            box.style.height = (crop.h * k) + 'px';
            box.hidden = false;
        });
    }

    function updateControls() {
        if (!img) {
            return;
        }
        var c = crop;
        var o = effectiveOut();
        var b = bounds();
        cropInputs.x.value = c.x;
        cropInputs.y.value = c.y;
        cropInputs.w.value = c.w;
        cropInputs.h.value = c.h;
        cropInputs.x.max = b.w - c.w;
        cropInputs.y.max = b.h - c.h;
        cropInputs.w.max = b.w;
        cropInputs.h.max = b.h;
        outInputs.w.value = o.w;
        outInputs.h.value = o.h;
        outInputs.w.max = Math.min(c.w, maxDimension);
        outInputs.h.max = Math.min(c.h, maxDimension);
        summaryEl.textContent = t('fb_edit_summary', c.w, c.h, o.w, o.h);
        var shown = ratioShown();
        ratioBtns.forEach(function (btn) {
            var pair = RATIOS[btn.dataset.ratio];
            var active = shown && btn.dataset.ratio === ratioKey;
            // 縦向きで合わせているボタンは「3:4」のように表示する(押すたびに 4:3 と 3:4 が入れ替わる)
            btn.textContent = active && ratioPortrait ? pair[1] + ':' + pair[0] : pair[0] + ':' + pair[1];
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        showCrop();
    }

    // 範囲が選んだ比率から外れたら、選んだ比率を手放す。ドラッグの途中は手放さず、ドラッグを終えたとき(endDrag())に決める
    function reconcileRatio() {
        if (!drag && ratioKey && !ratioShown()) {
            ratioKey = null;
        }
    }

    function setCrop(next, ratio) {
        var prev = crop;
        crop = normalizeRect(next, ratio, bounds());
        if (restore && (crop.x !== prev.x || crop.y !== prev.y || crop.w !== prev.w || crop.h !== prev.h)) {
            restore = null;
        }
        reconcileRatio();
        updateControls();
    }

    // 範囲内のダブルクリック: 画像全体と、その前の範囲とを切り替える。回転と保存の大きさの指定は変えない
    // (保存の大きさは resolveOut() が新しい範囲に収める)
    function toggleWhole() {
        if (!img || busy) {
            return;
        }
        if (restore) {
            crop = restore.crop;
            ratioKey = restore.ratioKey;
            ratioPortrait = restore.ratioPortrait;
            restore = null;
        } else {
            restore = { crop: crop, ratioKey: ratioKey, ratioPortrait: ratioPortrait };
            crop = wholeRect(bounds());
            // 画像全体が選んだ比率でなければ選択の表示は消えるが、選んだ比率は戻すときのために restore に持っている
            ratioKey = ratioShown() ? ratioKey : null;
        }
        updateControls();
    }

    // 縦横比のボタン。範囲を中心を保ったままその比率に合わせる(固定はしない)。
    // 選んでいるボタンをもう一度押すと縦横を入れ替える(1:1 は変わらない)。別のボタンは通常の向き(4:3 など)で合わせる
    function selectRatio(key) {
        if (!img || busy || !RATIOS[key]) {
            return;
        }
        if (key === ratioKey) {
            if (key !== '1:1') {
                ratioPortrait = !ratioPortrait;
                setCrop(swapOrientation(crop, bounds()), activeRatio());
            }
            return;
        }
        ratioKey = key;
        ratioPortrait = false;
        setCrop(fitRatioInside(crop, activeRatio()), activeRatio());
    }

    // 範囲・比率の向き・リサイズの指定も一緒に回し、回転と他の操作のどちらを先にしても同じ結果になるようにする
    function rotate(direction) {
        if (!img || busy) {
            return;
        }
        var before = bounds();
        crop = rotateRect(crop, direction, before);
        rot = (rot + direction + 4) % 4;
        // 範囲は縦横が入れ替わるので、合わせた比率も向きを入れ替える(4:3 は 3:4 になる)
        ratioPortrait = !ratioPortrait;
        restore = null;
        reconcileRatio();
        if (outSize) {
            outSize = { w: outSize.h, h: outSize.w };
            outKey = outKey === 'w' ? 'h' : 'w';
        }
        renderStage();
    }

    function onCropInput(key) {
        var raw = parseInt(cropInputs[key].value, 10);
        if (!img || !isFinite(raw)) {
            updateControls();
            return;
        }
        var b = bounds();
        var c = Object.assign({}, crop);
        // X・Y は大きさを保って位置だけを変える。幅・高さは位置を保ち、画像の外へ出ない大きさまでにする(縦横比は固定しない)
        if (key === 'x') {
            c.x = clamp(raw, 0, b.w - c.w);
        } else if (key === 'y') {
            c.y = clamp(raw, 0, b.h - c.h);
        } else if (key === 'w') {
            c.w = clamp(raw, 1, b.w - c.x);
        } else {
            c.h = clamp(raw, 1, b.h - c.y);
        }
        setCrop(c, null);
    }

    // key: 'w' か 'h'。縦横比を保つときは、変えなかった側を計算で合わせる。元より大きくはできず、長い辺は上限まで
    function onOutInput(key) {
        var raw = parseInt(outInputs[key].value, 10);
        if (!img || !isFinite(raw) || raw < 1) {
            updateControls();
            return;
        }
        var cur = effectiveOut();
        outKey = key;
        outSize = key === 'w' ? { w: raw, h: cur.h } : { w: cur.w, h: raw };
        // 上限に収めた値を、次からの指定として持つ
        outSize = effectiveOut();
        updateControls();
    }

    function onKeepRatioChange() {
        if (img && outSize) {
            // 切り替える前の見えている大きさを、そのまま指定として引き継ぐ
            outSize = resolveOut(crop, keepRatio);
        }
        keepRatio = keepRatioEl.checked;
        updateControls();
    }

    // --- トリミングの枠のドラッグ ---
    // つまみ: その辺・角だけを動かす / 枠の内側: 位置を動かす / 枠の外: 押した点から描き直す。
    // 縦横比は固定しない。Shift を押している間だけ、ドラッグを始めたときの範囲の縦横比を保つ
    // (ボタンの比率になっているときは、丸めた範囲から割り出さず、その比率そのものを使う)。
    // 範囲は毎回ドラッグの起点(drag.rect と drag.start)から計算し直す。途中で Shift を離したときは、その時点の範囲を起点にし直す
    // (rebaseDrag())ので、押しても離しても範囲が飛んだり崩れたりしない。押してから CLICK_SLOP だけ動くまでは何もしない

    function onPointerDown(e) {
        if (!img || busy || drag || e.button !== 0) {
            return;
        }
        e.preventDefault();
        var handleEl = e.target.closest('[data-handle]');
        var mode = handleEl ? handleEl.dataset.handle : (cropEl.contains(e.target) ? 'move' : 'draw');
        lastDownMode = mode;
        drag = {
            id: e.pointerId,
            mode: mode,
            ratio: ratioShown() ? activeRatio() : crop.w / crop.h,
            start: toImagePoint(e, mode === 'draw'),
            rect: Object.assign({}, crop),
            x: e.clientX,
            y: e.clientY,
            last: { clientX: e.clientX, clientY: e.clientY },
            shift: e.shiftKey,
            moved: false
        };
        wrapEl.setPointerCapture(e.pointerId);
        wrapEl.classList.add('is-dragging');
        window.addEventListener('keydown', onDragKey, true);
        window.addEventListener('keyup', onDragKey, true);
    }

    function onPointerMove(e) {
        if (!drag || e.pointerId !== drag.id) {
            return;
        }
        if (!drag.moved && Math.abs(e.clientX - drag.x) < CLICK_SLOP && Math.abs(e.clientY - drag.y) < CLICK_SLOP) {
            return;
        }
        drag.moved = true;
        drag.last = { clientX: e.clientX, clientY: e.clientY };
        drag.shift = e.shiftKey;
        applyDrag();
    }

    // ドラッグ中に Shift を押した・離したときは、ポインターが止まっていても、その場で範囲を計算し直す
    function onDragKey(e) {
        if (!drag || e.key !== 'Shift' || drag.shift === e.shiftKey) {
            return;
        }
        drag.shift = e.shiftKey;
        if (!drag.moved) {
            return;
        }
        if (drag.shift) {
            // 押したときは、ドラッグを始めたときの比率にその場で揃える(向かいの角・辺は動かない)
            applyDrag();
        } else {
            // 離したときは範囲を動かさず、今の範囲と位置から自由な変形を続ける(離した瞬間に形が飛ばないように)
            rebaseDrag();
        }
    }

    // 今の範囲とポインターの位置を、ドラッグの起点にし直す。枠の外からの描き直しは、ポインターの向きの角のつまみとして続ける
    function rebaseDrag() {
        if (drag.mode === 'draw') {
            var p = toImagePoint(drag.last, true);
            drag.mode = (p.y < drag.start.y ? 'n' : 's') + (p.x < drag.start.x ? 'w' : 'e');
        }
        drag.rect = Object.assign({}, crop);
        drag.start = toImagePoint(drag.last, false);
    }

    function applyDrag() {
        var b = bounds();
        var ratio = drag.shift ? drag.ratio : null;
        var min = minCropSize();
        var p = toImagePoint(drag.last, drag.mode === 'draw');
        var dx = p.x - drag.start.x;
        var dy = p.y - drag.start.y;
        var next;
        if (drag.mode === 'move') {
            next = moveRect(drag.rect, dx, dy, b);
        } else if (drag.mode === 'draw') {
            next = resizeFromAnchor(drag.start, p, ratio, b, min);
        } else if (drag.mode.length === 2) {
            next = resizeCorner(drag.rect, drag.mode, dx, dy, ratio, b, min);
        } else {
            next = resizeEdge(drag.rect, drag.mode, dx, dy, ratio, b, min);
        }
        setCrop(next, ratio);
    }

    function endDrag() {
        if (!drag) {
            return;
        }
        if (wrapEl.hasPointerCapture(drag.id)) {
            wrapEl.releasePointerCapture(drag.id);
        }
        var moved = drag.moved;
        drag = null;
        if (moved && img) {
            reconcileRatio();
            updateControls();
        }
        wrapEl.classList.remove('is-dragging');
        window.removeEventListener('keydown', onDragKey, true);
        window.removeEventListener('keyup', onDragKey, true);
    }

    function onPointerEnd(e) {
        if (drag && e.pointerId === drag.id) {
            endDrag();
        }
    }

    // ポインターを取り込んでいるので、dblclick の宛先は wrapEl になる。範囲の内側かどうかは、直前の pointerdown で見分ける
    var lastDownMode = null;
    wrapEl.addEventListener('dblclick', function () {
        if (lastDownMode === 'move') {
            toggleWhole();
        }
    });
    wrapEl.addEventListener('pointerdown', onPointerDown);
    wrapEl.addEventListener('pointermove', onPointerMove);
    wrapEl.addEventListener('pointerup', onPointerEnd);
    wrapEl.addEventListener('pointercancel', onPointerEnd);
    wrapEl.addEventListener('lostpointercapture', onPointerEnd);

    // --- 書き出し ---

    // 回転 → トリミング → リサイズ の順に canvas へ描き、指定の形式の Blob にする
    function exportBlob(mime) {
        return new Promise(function (resolve, reject) {
            var c = crop;
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
        endDrag();
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
                // maxUploadSize が null のときは、本体では制限しない(サーバーの PHP の上限だけが効く)
                if (ctx.config.maxUploadSize != null && blob.size > ctx.config.maxUploadSize) {
                    throw new Error(t('fb_err_too_large'));
                }
                var body = withApiParams(ctx.config, new FormData());
                body.set('action', 'saveImage');
                body.set('folder', savedFolder);
                body.set('mode', mode);
                body.set('target', savedTarget);
                body.set('image', blob, savedTarget);
                return fetchJson(postUrl(ctx.config), { method: 'POST', body: body });
            })
            .then(function (result) {
                setBusy(false);
                close(true);
                ctx.onSaved(result.name);
            })
            .catch(function (err) {
                setBusy(false);
                showError(failureMessage(err, 'fb_edit_save_failed'));
                saveBtn.focus({ preventScroll: true });
            });
    }

    // --- 開く・閉じる ---

    function release() {
        endDrag();
        if (resizeObserver) {
            resizeObserver.disconnect();
            resizeObserver = null;
        }
        if (renderFrame) {
            window.cancelAnimationFrame(renderFrame);
            renderFrame = 0;
        }
        if (loadingImg) {
            loadingImg.onload = null;
            loadingImg.onerror = null;
            loadingImg.removeAttribute('src');
        }
        loadingImg = null;
        img = null;
        crop = null;
        restore = null;
        outSize = null;
        // 表示用の canvas の画素も手放す
        canvasEl.width = 0;
        canvasEl.height = 0;
        canvasEl.style.width = '';
        canvasEl.style.height = '';
        cropEl.hidden = true;
        holeEl.hidden = true;
        summaryEl.textContent = '';
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
        ratioKey = null;
        ratioPortrait = false;
        restore = null;
        outSize = null;
        outKey = 'w';
        keepRatio = true;
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
            crop = wholeRect(bounds());
            saveBtn.disabled = false;
            renderStage();
            if (typeof ResizeObserver === 'function') {
                resizeObserver = new ResizeObserver(scheduleRender);
                resizeObserver.observe(stageEl);
            }
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
    ratioBtns.forEach(function (btn) {
        btn.addEventListener('click', function () { selectRatio(btn.dataset.ratio); });
    });
    Object.keys(cropInputs).forEach(function (key) {
        cropInputs[key].addEventListener('change', function () { onCropInput(key); });
    });
    Object.keys(outInputs).forEach(function (key) {
        outInputs[key].addEventListener('change', function () { onOutInput(key); });
    });
    keepRatioEl.addEventListener('change', onKeepRatioChange);
    cancelBtn.addEventListener('click', cancel);
    saveBtn.addEventListener('click', save);

    return { open: open, canEdit: canEdit };
}
