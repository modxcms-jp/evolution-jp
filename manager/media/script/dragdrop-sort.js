(function(window, document) {
    'use strict';

    var dragItem = null;
    var dragList = null;

    // 並べ替えの対象にする要素の属性
    //   data-sortable        : ul / ol / table / tbody に付ける
    //   data-sortable-items  : 並べ替える行を絞り込むセレクタ（見出し行を除くときなど）
    //   data-sortable-handle : つかむ位置のセレクタ。指定時はそこからだけドラッグを始める
    //   data-target / data-delimiter : 並び順(id)を書き込む hidden の id と区切り文字
    // 並べ替えが終わると、リストで modxsortable:end（detail.item に動かした要素）を発火する
    var SELECTOR = 'ul[data-sortable], ol[data-sortable], table[data-sortable], tbody[data-sortable]';

    function trim(str) {
        return String(str).replace(/^\s+|\s+$/g, '');
    }

    function findSortableLists(root) {
        var context = root || document;
        return context.querySelectorAll(SELECTOR);
    }

    function clearDragging(el) {
        if (!el || !el.className) {
            return;
        }
        el.className = trim(el.className.replace(/\s*dragging\s*/g, ' ').replace(/\s{2,}/g, ' '));
    }

    function itemTag(list) {
        return list.tagName === 'UL' || list.tagName === 'OL' ? 'LI' : 'TR';
    }

    // table に付けた場合は、直下の tr と tbody 内の tr の両方を行として扱う
    function isItem(list, node) {
        if (!node || node.nodeType !== 1 || node.tagName !== itemTag(list)) {
            return false;
        }
        var parent = node.parentNode;
        if (parent !== list && !(list.tagName === 'TABLE' && parent && parent.parentNode === list)) {
            return false;
        }
        var filter = list.getAttribute('data-sortable-items');
        return !filter || node.matches(filter);
    }

    function getItems(list) {
        var nodes = list.getElementsByTagName(itemTag(list));
        var items = [];
        for (var i = 0; i < nodes.length; i++) {
            if (isItem(list, nodes[i])) {
                items.push(nodes[i]);
            }
        }
        return items;
    }

    function resolveListItem(list, node) {
        while (node && node !== list && node.nodeType === 1) {
            if (isItem(list, node)) {
                return node;
            }
            node = node.parentNode;
        }
        return null;
    }

    function updateHidden(list) {
        if (!list) {
            return;
        }
        var targetId = list.getAttribute('data-target');
        if (!targetId) {
            return;
        }
        var field = document.getElementById(targetId);
        if (!field) {
            return;
        }
        var delimiter = list.getAttribute('data-delimiter') || ',';
        var ids = [];
        var items = getItems(list);
        for (var i = 0; i < items.length; i++) {
            if (items[i].id) {
                ids.push(items[i].id);
            }
        }
        field.value = ids.join(delimiter);
    }

    function prepareItem(item) {
        item.setAttribute('draggable', 'true');
        if (item.style) {
            item.style.userSelect = 'none';
        }
    }

    function notifyEnd(list, item) {
        var event;
        if (typeof window.CustomEvent === 'function') {
            event = new CustomEvent('modxsortable:end', {bubbles: true, detail: {item: item}});
        } else {
            event = document.createEvent('CustomEvent');
            event.initCustomEvent('modxsortable:end', true, false, {item: item});
        }
        list.dispatchEvent(event);
    }

    // ドラッグ開始前に draggable を付ける。後から追加した行もここで拾う。
    // ハンドル指定時は、入力欄の文字選択を妨げないよう押している間だけ付ける
    function onMouseDown(e) {
        var list = this;
        var item = resolveListItem(list, e.target);
        if (!item) {
            return;
        }
        var handle = list.getAttribute('data-sortable-handle');
        if (!handle) {
            prepareItem(item);
            return;
        }
        var node = e.target.nodeType === 1 ? e.target : e.target.parentNode;
        var grip = node && node.closest ? node.closest(handle) : null;
        if (!grip || !item.contains(grip)) {
            return;
        }
        item.setAttribute('draggable', 'true');
        var release = function() {
            document.removeEventListener('mouseup', release, false);
            if (dragItem !== item) {
                item.removeAttribute('draggable');
            }
        };
        document.addEventListener('mouseup', release, false);
    }

    function onDragStart(e) {
        var item = resolveListItem(this, e.target);
        if (!item || item.getAttribute('draggable') !== 'true') {
            return;
        }
        dragItem = item;
        dragList = this;
        if (item.className.indexOf('dragging') === -1) {
            item.className = trim(item.className + ' dragging');
        }
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            try {
                e.dataTransfer.setData('text/plain', item.id);
            } catch (err) {
                // Ignore unsupported data transfer errors
            }
        }
    }

    // dragenter専用。preventDefaultのみ行い、要素の移動(insertBefore)はしない。
    // dragoverと同じ処理を割り当てると、insertBeforeによるDOM変更が新たな
    // dragenter/dragleaveを誘発し、ブラウザによってはドラッグが混乱する
    function onDragEnter(e) {
        if (!dragItem || dragList !== this) {
            return;
        }
        e.preventDefault();
    }

    function onDragOver(e) {
        if (!dragItem || dragList !== this) {
            return;
        }
        e.preventDefault();
        var target = resolveListItem(this, e.target);
        if (!target || target === dragItem) {
            return;
        }
        var rect = target.getBoundingClientRect();
        var after = e.clientY - rect.top > rect.height / 2;
        target.parentNode.insertBefore(dragItem, after ? target.nextSibling : target);
    }

    function onDrop(e) {
        if (dragList !== this) {
            return;
        }
        e.preventDefault();
        updateHidden(this);
    }

    function onDragEnd(e) {
        var item = dragItem;
        if (!item || dragList !== this) {
            return;
        }
        var list = this;
        clearDragging(item);
        if (list.getAttribute('data-sortable-handle')) {
            item.removeAttribute('draggable');
        }
        dragItem = null;
        dragList = null;
        updateHidden(list);
        notifyEnd(list, item);
    }

    function prepareList(list) {
        if (!list || list.getAttribute('data-sortable-ready')) {
            return;
        }
        list.setAttribute('data-sortable-ready', '1');
        if (!list.getAttribute('data-sortable-handle')) {
            var items = getItems(list);
            for (var i = 0; i < items.length; i++) {
                prepareItem(items[i]);
            }
        }
        list.addEventListener('mousedown', onMouseDown, false);
        list.addEventListener('dragstart', onDragStart, false);
        list.addEventListener('dragend', onDragEnd, false);
        // dragoverだけでなくdragenterでもpreventDefault()しないと、
        // ブラウザによってはドロップ先として認識されずdragoverが発火しない
        list.addEventListener('dragenter', onDragEnter, false);
        list.addEventListener('dragover', onDragOver, false);
        list.addEventListener('drop', onDrop, false);
        updateHidden(list);
    }

    function initAll(root) {
        var lists = findSortableLists(root);
        for (var i = 0; i < lists.length; i++) {
            prepareList(lists[i]);
        }
    }

    function updateAll(root) {
        var lists = findSortableLists(root);
        for (var i = 0; i < lists.length; i++) {
            updateHidden(lists[i]);
        }
    }

    var api = {
        init: function(root) {
            initAll(root);
        },
        refresh: function(root) {
            dragItem = null;
            dragList = null;
            initAll(root);
        },
        updateAll: function(root) {
            updateAll(root);
        },
        updateList: updateHidden,
        prepareList: prepareList
    };

    window.MODXSortable = api;

    document.addEventListener('DOMContentLoaded', function() {
        initAll();
    }, false);
})(window, document);
