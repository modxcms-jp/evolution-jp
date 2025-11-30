(function(window, document) {
    'use strict';

    var dragItem = null;

    function trim(str) {
        return String(str).replace(/^\s+|\s+$/g, '');
    }

    function findSortableLists(root) {
        var context = root || document;
        return context.querySelectorAll('ul[data-sortable], ol[data-sortable]');
    }

    function clearDragging(el) {
        if (!el || !el.className) {
            return;
        }
        el.className = trim(el.className.replace(/\s*dragging\s*/g, ' ').replace(/\s{2,}/g, ' '));
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
        var children = list.children || list.childNodes;
        for (var i = 0; i < children.length; i++) {
            var child = children[i];
            if (!child || child.nodeType !== 1) {
                continue;
            }
            var id = child.id || '';
            if (id) {
                ids.push(id);
            }
        }
        field.value = ids.join(delimiter);
    }

    function resolveListItem(list, node) {
        while (node && node !== list && node.nodeType === 1 && node.tagName !== 'LI') {
            node = node.parentNode;
        }
        if (node && node !== list && node.tagName === 'LI') {
            return node;
        }
        return null;
    }

    function onDragStart(e) {
        dragItem = this;
        if (this.className.indexOf('dragging') === -1) {
            this.className = trim(this.className + ' dragging');
        }
        if (e && e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
            try {
                e.dataTransfer.setData('text/plain', this.id);
            } catch (err) {
                // Ignore unsupported data transfer errors
            }
        }
    }

    function onDragOver(e) {
        if (!dragItem) {
            return;
        }
        e.preventDefault();
        var list = this;
        var target = resolveListItem(list, e.target);
        if (!target || target === dragItem) {
            return;
        }
        var rect = target.getBoundingClientRect();
        var after = e.clientY - rect.top > rect.height / 2;
        list.insertBefore(dragItem, after ? target.nextSibling : target);
    }

    function onDrop(e) {
        e.preventDefault();
        updateHidden(this);
    }

    function onDragEnd() {
        clearDragging(this);
        var parent = this.parentNode;
        dragItem = null;
        if (parent) {
            updateHidden(parent);
        }
    }

    function prepareList(list) {
        if (!list || list.getAttribute('data-sortable-ready')) {
            return;
        }
        list.setAttribute('data-sortable-ready', '1');
        var items = list.getElementsByTagName('li');
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            item.setAttribute('draggable', 'true');
            item.ondragstart = onDragStart;
            item.ondragend = onDragEnd;
            if (item.style) {
                item.style.userSelect = 'none';
            }
        }
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
