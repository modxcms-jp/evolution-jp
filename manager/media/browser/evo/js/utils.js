// 状態を持たない純粋なヘルパー群(HTMLエスケープ・表示整形・パス結合・アイコン)。

export function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
}

export function formatSize(bytes) {
    if (bytes < 1024) {
        return bytes + ' B';
    }
    var units = ['KB', 'MB', 'GB'];
    var value = bytes;
    var i = -1;
    do {
        value /= 1024;
        i++;
    } while (value >= 1024 && i < units.length - 1);
    return value.toFixed(1) + ' ' + units[i];
}

export function formatDate(unixTime) {
    var d = new Date(unixTime * 1000);
    function pad(n) { return String(n).padStart(2, '0'); }
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
}

export function joinPath(base, name) {
    return base ? base + '/' + name : name;
}

export function renderBreadcrumb(el, escapedType, folder, onNavigate) {
    var segments = folder ? folder.split('/').filter(Boolean) : [];
    var html = '<button type="button" class="evo-fb-crumb" data-path="">' + escapedType + '</button>';
    var acc = '';
    segments.forEach(function (segment) {
        acc = acc ? acc + '/' + segment : segment;
        html += '<span class="evo-fb-crumb-sep">/</span>';
        html += '<button type="button" class="evo-fb-crumb" data-path="' + escapeHtml(acc) + '">' + escapeHtml(segment) + '</button>';
    });
    el.innerHTML = html;
    el.querySelectorAll('.evo-fb-crumb').forEach(function (btn) {
        btn.addEventListener('click', function () {
            onNavigate(btn.getAttribute('data-path'));
        });
    });
}

export function folderIcon(size) {
    return '<svg viewBox="0 0 24 24" width="' + size + '" height="' + size + '" aria-hidden="true"><path fill="currentColor" d="M10 4H2v16h20V6H12z"/></svg>';
}

export function fileIcon(size) {
    return '<svg viewBox="0 0 24 24" width="' + size + '" height="' + size + '" aria-hidden="true"><path fill="currentColor" d="M6 2h9l5 5v15H6zm8 1.5V8h4.5z"/></svg>';
}

export const ICON_TREE_ARROW = '<svg viewBox="0 0 24 24" width="12" height="12"><path fill="currentColor" d="M9 5l8 7-8 7z"/></svg>';
export const ICON_RENAME = '<svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75zm17.71-10.04a1 1 0 000-1.41l-2.51-2.51a1 1 0 00-1.41 0l-1.83 1.83 3.75 3.75z"/></svg>';
export const ICON_DELETE = '<svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M6 7h12l-1 14H7zm3-3h6l1 2H8zM4 6h16v2H4z"/></svg>';
export const ICON_PREVIEW = '<svg viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19zm-6 0A4.5 4.5 0 1114 9.5 4.5 4.5 0 019.5 14z"/></svg>';
