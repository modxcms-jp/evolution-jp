(function(window, document) {
    'use strict';

    var dragItem = null;

    function trim(str) {
        return String(str).replace(/^\s+|\s+$/g, '');
    }

    function hasSortableAttribute(node) {
        if (!node || node.nodeType !== 1) {
            return false;
        }
        if (node.getAttribute) {
            return !!node.getAttribute('data-sortable');
        }
        return false;
    }

    function findSortableLists(root) {
        var context = root || document;
        if (context.querySelectorAll) {
            return context.querySelectorAll('ul[data-sortable], ol[data-sortable]');
        }

        var lists = [];
        var tags = ['ul', 'ol'];
        for (var t = 0; t < tags.length; t++) {
            var elements = context.getElementsByTagName(tags[t]);
            for (var i = 0; i < elements.length; i++) {
                if (hasSortableAttribute(elements[i])) {
                    lists.push(elements[i]);
                }
            }
        }
        return lists;
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
            e.dataTransfer.setData('text/plain', this.id);
        }
    }

    function onDragOver(e) {
        if (!dragItem) {
            return;
        }
        if (e && e.preventDefault) {
            e.preventDefault();
        }
        var list = this;
        var target = resolveListItem(list, (e && (e.target || e.srcElement)) || null);
        if (!target || target === dragItem) {
            return;
        }
        var rect = target.getBoundingClientRect ? target.getBoundingClientRect() : null;
        var after = false;
        if (rect) {
            var clientY = (e && (e.clientY || e.pageY)) || 0;
            after = clientY - rect.top > rect.height / 2;
        }
        list.insertBefore(dragItem, after ? target.nextSibling : target);
    }

    function onDrop(e) {
        if (e && e.preventDefault) {
            e.preventDefault();
        }
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
        if (list.addEventListener) {
            list.addEventListener('dragover', onDragOver, false);
            list.addEventListener('drop', onDrop, false);
        } else if (list.attachEvent) {
            list.attachEvent('ondragover', onDragOver);
            list.attachEvent('ondrop', onDrop);
        }
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

    if (document.addEventListener) {
        document.addEventListener('DOMContentLoaded', function() {
            initAll();
        }, false);
    } else if (window.attachEvent) {
        window.attachEvent('onload', function() {
            initAll();
        });
    }
})(window, document);
