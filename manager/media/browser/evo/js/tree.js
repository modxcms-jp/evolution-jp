// 左ペインのフォルダツリー。開閉状態(expandedPaths)はこのモジュール内に閉じる。
// グリッド側のファイル一覧(state.rawFolders/rawFiles)とは syncWithList() で
// 追加リクエストなしに同期する。

import { escapeHtml, joinPath, folderIcon, fileIcon, ICON_TREE_ARROW } from './utils.js';
import { bindMoveDropTarget, bindFileDragSource } from './dnd.js';

/**
 * @param {object} ctx
 * @param {HTMLElement} ctx.treeEl
 * @param {string} ctx.type - ルートノードのラベル(images/media/files)
 * @param {(path: string) => Promise<{folders, files}>} ctx.fetchLight - light=1でのlist取得
 * @param {() => string} ctx.getCurrentFolder
 * @param {(path: string, selectName?: string) => void} ctx.onNavigate
 * @param {(name: string) => void} ctx.onSelectInPlace - 現在フォルダ内のファイルをクリックしたとき
 * @param {(path: string) => void} ctx.onPickFile - ファイルをダブルクリックしたとき
 * @param {(dest: string, names: string[], sourceFolder: string) => void} ctx.onDropMove
 */
export function createTree(ctx) {
    var treeEl = ctx.treeEl;
    var expandedPaths = new Set(['']);

    function treeNodeHtml(name, path, hasChildren) {
        var toggle = hasChildren
            ? '<button type="button" class="evo-fb-tree-toggle" data-role="tree-toggle" aria-expanded="false">' + ICON_TREE_ARROW + '</button>'
            : '<span class="evo-fb-tree-toggle evo-fb-tree-toggle-none"></span>';

        return '' +
            '<div class="evo-fb-tree-node" data-path="' + escapeHtml(path) + '">' +
            '  <div class="evo-fb-tree-row" data-path="' + escapeHtml(path) + '">' +
            toggle +
            '    <button type="button" class="evo-fb-tree-label">' +
            folderIcon(14) +
            '      <span class="evo-fb-tree-name">' + escapeHtml(name) + '</span>' +
            '    </button>' +
            '  </div>' +
            '  <div class="evo-fb-tree-children" hidden></div>' +
            '</div>';
    }

    function treeFileHtml(file, folderPath) {
        return '' +
            '<div class="evo-fb-tree-row evo-fb-tree-file" draggable="true"' +
            ' data-name="' + escapeHtml(file.name) + '"' +
            ' data-file-path="' + escapeHtml(file.path) + '"' +
            ' data-folder="' + escapeHtml(folderPath) + '">' +
            '  <span class="evo-fb-tree-toggle evo-fb-tree-toggle-none"></span>' +
            '  <button type="button" class="evo-fb-tree-label">' +
            fileIcon(14) +
            '    <span class="evo-fb-tree-name">' + escapeHtml(file.name) + '</span>' +
            '  </button>' +
            '</div>';
    }

    function bindTreeFile(row) {
        var name = row.dataset.name;
        var folderPath = row.dataset.folder;
        var label = row.querySelector('.evo-fb-tree-label');

        label.addEventListener('click', function () {
            if (ctx.getCurrentFolder() === folderPath) {
                ctx.onSelectInPlace(name);
            } else {
                ctx.onNavigate(folderPath, name);
            }
        });
        label.addEventListener('dblclick', function () {
            ctx.onPickFile(row.dataset.filePath);
        });
        bindFileDragSource(row, function () { return [name]; }, function () { return folderPath; });
    }

    function findTreeNode(path) {
        var nodes = treeEl.querySelectorAll('.evo-fb-tree-node');
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].dataset.path === path) {
                return nodes[i];
            }
        }
        return null;
    }

    function bindTreeNode(node) {
        var path = node.dataset.path;
        var row = node.querySelector('.evo-fb-tree-row');
        var toggle = row.querySelector('[data-role="tree-toggle"]');
        var label = row.querySelector('.evo-fb-tree-label');

        if (toggle) {
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                if (expandedPaths.has(path)) {
                    collapseTreeNode(node);
                } else {
                    expandTreeNode(node);
                }
            });
        }
        label.addEventListener('click', function () {
            ctx.onNavigate(path);
        });
        bindMoveDropTarget(row, function () { return path; }, ctx.onDropMove);
    }

    function setTreeToggleState(node, expanded) {
        var toggle = node.querySelector(':scope > .evo-fb-tree-row [data-role="tree-toggle"]');
        if (toggle) {
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.classList.toggle('is-open', expanded);
        }
    }

    function expandTreeNode(node) {
        var path = node.dataset.path;
        expandedPaths.add(path);
        setTreeToggleState(node, true);
        var children = node.querySelector(':scope > .evo-fb-tree-children');
        children.hidden = false;

        if (node.dataset.loaded) {
            return Promise.resolve();
        }
        children.innerHTML = '<div class="evo-fb-tree-loading">読み込み中...</div>';
        return ctx.fetchLight(path)
            .then(function (data) {
                renderTreeChildren(node, data.folders, data.files);
            })
            .catch(function () {
                children.innerHTML = '';
            });
    }

    function collapseTreeNode(node) {
        expandedPaths.delete(node.dataset.path);
        setTreeToggleState(node, false);
        node.querySelector(':scope > .evo-fb-tree-children').hidden = true;
    }

    function renderTreeChildren(node, folders, files) {
        var path = node.dataset.path;
        var children = node.querySelector(':scope > .evo-fb-tree-children');
        node.dataset.loaded = '1';

        children.innerHTML = folders.map(function (folder) {
            return treeNodeHtml(folder.name, joinPath(path, folder.name), folder.hasChildren);
        }).join('') + (files || []).map(function (file) {
            return treeFileHtml(file, path);
        }).join('');

        children.querySelectorAll(':scope > .evo-fb-tree-node').forEach(function (child) {
            bindTreeNode(child);
            // 再描画前に開いていた子は展開状態を復元する
            if (expandedPaths.has(child.dataset.path)) {
                expandTreeNode(child);
            }
        });
        children.querySelectorAll(':scope > .evo-fb-tree-file').forEach(bindTreeFile);
        setActiveTreePath(ctx.getCurrentFolder());
    }

    function setActiveTreePath(path) {
        treeEl.querySelectorAll('.evo-fb-tree-row.is-active').forEach(function (el) {
            el.classList.remove('is-active');
        });
        var node = findTreeNode(path);
        if (node) {
            node.querySelector(':scope > .evo-fb-tree-row').classList.add('is-active');
        }
    }

    // 現在フォルダまでの祖先を順に展開して、ツリー上で見える状態にする
    function revealPath(path) {
        var segments = path ? path.split('/').filter(Boolean) : [];
        var chain = Promise.resolve();
        var acc = '';
        segments.forEach(function (segment) {
            var parent = acc;
            chain = chain.then(function () {
                var node = findTreeNode(parent);
                return node ? expandTreeNode(node) : null;
            });
            acc = acc ? acc + '/' + segment : segment;
        });
        chain.then(function () {
            setActiveTreePath(path);
        });
    }

    // グリッドのlist応答からツリーの現在ノードを同期する(追加リクエスト不要)
    function syncWithList(folder, folders, files) {
        var node = findTreeNode(folder);
        if (!node) {
            return;
        }
        renderTreeChildren(node, folders, files);
        if (folders.length > 0 || (files && files.length > 0)) {
            expandedPaths.add(folder);
            setTreeToggleState(node, true);
            node.querySelector(':scope > .evo-fb-tree-children').hidden = false;
        }
    }

    function renderRoot() {
        treeEl.innerHTML = treeNodeHtml(ctx.type, '', true);
        var rootNode = treeEl.querySelector('.evo-fb-tree-node');
        bindTreeNode(rootNode);
        setTreeToggleState(rootNode, true);
        rootNode.querySelector(':scope > .evo-fb-tree-children').hidden = false;
    }

    return {
        renderRoot: renderRoot,
        syncWithList: syncWithList,
        revealPath: revealPath
    };
}
