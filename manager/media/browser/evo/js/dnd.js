// ファイル移動のD&Dプリミティブ。ブラウザ内移動(DND_TYPE)とOSからのファイル
// ドラッグ(アップロード用)をdataTransfer.typesで判別し、DOM操作のみを担う。
// 何を移動するか・移動後どうするかは呼び出し側のコールバックに委ねる。

export const DND_TYPE = 'application/x-evo-fb-files';

export function isInternalDrag(e) {
    var types = e.dataTransfer && e.dataTransfer.types;
    return !!types && Array.prototype.indexOf.call(types, DND_TYPE) !== -1;
}

export function isOsFileDrag(e) {
    var types = e.dataTransfer && e.dataTransfer.types;
    return !!types
        && Array.prototype.indexOf.call(types, 'Files') !== -1
        && !isInternalDrag(e);
}

/**
 * elを移動先のドロップターゲットにする。ドロップ時にonDrop(dest, names, sourceFolder)を呼ぶ。
 */
export function bindMoveDropTarget(el, getDest, onDrop) {
    el.addEventListener('dragover', function (e) {
        if (!isInternalDrag(e)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        e.dataTransfer.dropEffect = 'move';
        el.classList.add('is-dropover');
    });
    el.addEventListener('dragleave', function () {
        el.classList.remove('is-dropover');
    });
    el.addEventListener('drop', function (e) {
        if (!isInternalDrag(e)) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        el.classList.remove('is-dropover');
        var payload;
        try {
            payload = JSON.parse(e.dataTransfer.getData(DND_TYPE));
        } catch (err) {
            return;
        }
        if (!payload || !Array.isArray(payload.names)) {
            return;
        }
        onDrop(getDest(), payload.names, payload.folder);
    });
}

/**
 * elを移動元のドラッグソースにする。getNames()が移動対象のファイル名配列を返す。
 */
export function bindFileDragSource(el, getNames, getFolder) {
    el.addEventListener('dragstart', function (e) {
        var names = getNames();
        e.dataTransfer.setData(DND_TYPE, JSON.stringify({ names: names, folder: getFolder() }));
        e.dataTransfer.setData('text/plain', names.join('\n'));
        e.dataTransfer.effectAllowed = 'move';
        el.classList.add('is-dragging');
    });
    el.addEventListener('dragend', function () {
        el.classList.remove('is-dragging');
    });
}
