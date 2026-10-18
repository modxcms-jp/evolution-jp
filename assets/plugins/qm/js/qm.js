/**
 * QuickManager の公開側のスクリプト。jQuery には依存しない
 *
 * - a.colorbox のリンクを、<dialog> の中の <iframe> で開く（Colorbox の置き換え）。QM.init() の modal が true のときだけ
 * - ツールバーを閉じる・開く（Cookie の hideQM で覚える）
 * QM.close() で開いているモーダルを閉じる。jQuery があるときは、互換のために $j.colorbox.close() / jQuery.colorbox.close() も使える
 */
(function () {
    'use strict';

    const STYLE = ''
        + '#qmModal{position:fixed;inset:0;margin:auto;padding:0;border:0;border-radius:8px;overflow:hidden;'
        + 'max-width:100vw;max-height:100vh;background:#fff;box-shadow:0 12px 40px rgba(0,0,0,.35)}'
        + '#qmModal::backdrop{background:rgba(20,24,32,.5)}'
        + '#qmModal iframe{display:block;width:100%;height:100%;border:0}'
        + '#qmModalClose{position:absolute;top:6px;right:8px;z-index:1;width:32px;height:32px;border:0;border-radius:50%;'
        + 'background:rgba(30,34,42,.75);color:#fff;font:20px/32px sans-serif;cursor:pointer}'
        + '#qmModalClose:focus-visible{outline:2px solid #2f6fd0;outline-offset:2px}';

    let options = { modal: false, width: '90%', height: '100%' };
    let dialog = null;

    // 単位の無い数値は px として扱う（Colorbox と同じ）
    function size(value) {
        const v = String(value).trim();
        return /^\d+(\.\d+)?$/.test(v) ? v + 'px' : v;
    }

    // 外部のアドオンが呼ぶ parent.$j.colorbox.close() のための互換。jQuery がページの後ろで読まれることもあるので、開くたびに付ける
    function shimColorbox() {
        if (window.jQuery) {
            window.jQuery.colorbox = window.jQuery.colorbox || {};
            window.jQuery.colorbox.close = close;
        }
    }

    function open(url) {
        close();
        shimColorbox();
        if (!document.getElementById('qmModalStyle')) {
            const style = document.createElement('style');
            style.id = 'qmModalStyle';
            style.textContent = STYLE;
            document.head.appendChild(style);
        }
        dialog = document.createElement('dialog');
        dialog.id = 'qmModal';
        dialog.style.width = size(options.width);
        dialog.style.height = size(options.height);
        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.id = 'qmModalClose';
        closeButton.setAttribute('aria-label', 'close');
        closeButton.textContent = '×';
        closeButton.addEventListener('click', close);
        const iframe = document.createElement('iframe');
        iframe.src = url;
        iframe.title = 'QuickManager';
        dialog.append(closeButton, iframe);
        // Esc で閉じる。背景のクリックでは閉じない（Colorbox の overlayClose:false と同じ）
        dialog.addEventListener('cancel', function (e) {
            e.preventDefault();
            close();
        });
        document.body.appendChild(dialog);
        dialog.showModal();
    }

    function close() {
        if (!dialog) return;
        const d = dialog;
        dialog = null;
        if (d.open) d.close();
        d.remove();
    }

    function toggleToolbar(hide) {
        const editor = document.getElementById('qmEditor');
        const closed = document.getElementById('qmEditorClosed');
        if (editor) editor.style.display = hide ? 'none' : '';
        if (closed) closed.style.display = hide ? 'block' : 'none';
        document.cookie = 'hideQM=' + (hide ? 1 : 0) + '; path=/;';
    }

    function setup() {
        if (/(?:^|;\s*)hideQM=1(?:;|$)/.test(document.cookie)) {
            const editor = document.getElementById('qmEditor');
            const closed = document.getElementById('qmEditorClosed');
            if (editor) editor.style.display = 'none';
            if (closed) closed.style.display = 'block';
        }
        document.addEventListener('click', function (e) {
            if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            const target = e.target.closest ? e.target : null;
            if (!target) return;
            if (target.closest('.qmClose')) {
                e.preventDefault();
                toggleToolbar(true);
                return;
            }
            if (target.closest('#qmEditorClosed')) {
                toggleToolbar(false);
                return;
            }
            const link = target.closest('a.colorbox');
            if (link && options.modal) {
                e.preventDefault();
                open(link.href);
            }
        });
    }

    window.QM = {
        init: function (opts) {
            options = Object.assign(options, opts);
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', setup, { once: true });
            } else {
                setup();
            }
        },
        open: open,
        close: close
    };
})();
