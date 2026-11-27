/**
 * 管理画面の知らせ（トースト）。操作が済んだことを、操作を止めずに短く出す。jQuery には依存しない。
 * 決まり・置き場所・サーバー側から出す方法・ダイアログとの使い分けは assets/docs/manager-notice.md を正本とする。
 *
 * EvoNotice.show(message)
 * - message: 文字列。文字として出す（HTML にしない）
 * - 3 秒で消える。× で閉じられる。乗せている間・中にフォーカスがある間は消さない
 */
(function () {
    'use strict';

    const DURATION = 3000;
    // ボタンの並び（無ければ本文の欄の上端）との間
    const GAP = 8;

    let region = null;
    let observer = null;
    // 領域を作った直後のフレームの間は true（読み上げに空の領域を先に見せるため、中身はこの間を避けて入れる）
    let settling = false;

    function measureTop() {
        const pane = document.getElementById('mainPane');
        const actions = pane ? pane.querySelector('#actions') : null;
        if (actions) {
            const rect = actions.getBoundingClientRect();
            if (rect.height > 0 && getComputedStyle(actions).position === 'fixed') {
                return rect.bottom + GAP;
            }
        }
        if (pane) {
            return Math.max(pane.getBoundingClientRect().top, 0) + GAP;
        }
        return GAP * 2;
    }

    function place() {
        if (region) {
            region.style.setProperty('--evo-notice-top', Math.round(measureTop()) + 'px');
        }
    }

    function getRegion() {
        if (region && region.isConnected) {
            place();
            return region;
        }
        region = document.createElement('div');
        region.id = 'evoNotice';
        region.setAttribute('role', 'status');
        document.body.appendChild(region);
        settling = true;
        window.requestAnimationFrame(function () {
            settling = false;
        });
        place();
        const pane = document.getElementById('mainPane');
        if (pane && !observer) {
            observer = new MutationObserver(function () {
                if (region.childElementCount) {
                    place();
                }
            });
            observer.observe(pane, {childList: true});
        }
        window.addEventListener('resize', place);
        return region;
    }

    function close(item) {
        if (!item.isConnected) {
            return;
        }
        item.classList.add('is-leaving');
        // 消える動きを止めている環境（prefers-reduced-motion）でも消えるよう、animationend を待ちすぎない
        const remove = function () {
            item.remove();
        };
        item.addEventListener('animationend', remove, {once: true});
        window.setTimeout(remove, 400);
    }

    function append(item) {
        getRegion().appendChild(item);
        let timer = null;
        const start = function () {
            window.clearTimeout(timer);
            timer = window.setTimeout(function () {
                close(item);
            }, DURATION);
        };
        const stop = function () {
            window.clearTimeout(timer);
        };
        item.addEventListener('mouseenter', stop);
        item.addEventListener('mouseleave', start);
        item.addEventListener('focusin', stop);
        item.addEventListener('focusout', start);
        start();
    }

    function show(message) {
        const item = document.createElement('div');
        item.className = 'evo-notice';

        const text = document.createElement('p');
        text.className = 'evo-notice-message';
        text.textContent = String(message);
        item.appendChild(text);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'evo-notice-close';
        button.setAttribute('aria-label', EvoNotice.closeLabel);
        button.title = EvoNotice.closeLabel;
        button.textContent = '×';
        button.addEventListener('click', function () {
            close(item);
        });
        item.appendChild(button);

        getRegion();
        if (settling) {
            window.requestAnimationFrame(function () {
                append(item);
            });
        } else {
            append(item);
        }
        return item;
    }

    const EvoNotice = {
        // header.inc.php が言語に合わせて上書きする
        closeLabel: 'Close',
        show: show,
    };

    // 読み上げ（role="status"）は、空の領域が先にあるほうが中身の追加を拾う
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', getRegion);
    } else {
        getRegion();
    }

    window.EvoNotice = EvoNotice;
})();
