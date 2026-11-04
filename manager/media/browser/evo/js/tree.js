// 左ペインのフォルダツリー。開閉状態(expandedPaths)はこのモジュール内に閉じる。
// グリッド側のフォルダ一覧(state.rawFolders)とは syncWithList() で
// 追加リクエストなしに同期する。

import { escapeHtml, joinPath, folderIcon, ICON_TREE_ARROW } from './utils.js';
import { t } from './i18n.js';
import { loadFailureMessage } from './api.js';
import { bindMoveDropTarget } from './dnd.js';

/**
 * @param {object} ctx
 * @param {HTMLElement} ctx.treeEl
 * @param {string} ctx.type - ルートノードのラベル(images/media/files)
 * @param {(path: string) => Promise<{folders}>} ctx.fetchFolders - foldersOnly=1でのlist取得
 * @param {() => string} ctx.getCurrentFolder
 * @param {(path: string) => void} ctx.onNavigate
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
        children.innerHTML = '<div class="evo-fb-tree-loading">' + escapeHtml(t('fb_loading')) + '</div>';
        return ctx.fetchFolders(path)
            .then(function (data) {
                renderTreeChildren(node, data.folders);
            })
            .catch(function (err) {
                children.innerHTML = '<div class="evo-fb-tree-loading evo-fb-error">' + escapeHtml(loadFailureMessage(err)) + '</div>';
            });
    }

    function collapseTreeNode(node) {
        expandedPaths.delete(node.dataset.path);
        setTreeToggleState(node, false);
        node.querySelector(':scope > .evo-fb-tree-children').hidden = true;
    }

    function renderTreeChildren(node, folders) {
        var path = node.dataset.path;
        var children = node.querySelector(':scope > .evo-fb-tree-children');
        node.dataset.loaded = '1';

        children.innerHTML = folders.map(function (folder) {
            return treeNodeHtml(folder.name, joinPath(path, folder.name), folder.hasChildren);
        }).join('');

        children.querySelectorAll(':scope > .evo-fb-tree-node').forEach(function (child) {
            bindTreeNode(child);
            // 再描画前に開いていた子は展開状態を復元する
            if (expandedPaths.has(child.dataset.path)) {
                expandTreeNode(child);
            }
        });
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
    function syncWithList(folder, folders) {
        var node = findTreeNode(folder);
        if (!node) {
            return;
        }
        renderTreeChildren(node, folders);
        if (folders.length > 0) {
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
