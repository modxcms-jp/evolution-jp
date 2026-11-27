/**
 * 管理画面の知らせ（トースト）。操作を止めずに、本文の欄の右上（保存などのボタンの並び #actions のすぐ下）に
 * 短い知らせを出す。jQuery には依存しない。
 * 決まりと使い分け（ダイアログとの違い）は assets/docs/manager-notice.md を正本とする。
 *
 * EvoNotice.show(message, options)
 * - message: 文字列。文字として出す（HTML にしない）
 * - options.type: 'success'（既定）・'info'・'warning'。色と読み上げの強さが変わる（warning は role="alert"）
 * - options.sticky: true なら、閉じるまで残す。false（既定）なら DURATION ミリ秒で消える。
 *   乗せている間・中にフォーカスがある間は消さない
 * - options.links: [{href, label}]。知らせの中にリンクを並べる。管理画面の URL はシェルの中で開く（EvoShell.go）
 *
 * サーバー側から出すときは manager()->notice()（manager/includes/extenders/ex_managerapi.php）でセッションに積む。
 * 次に描く画面（ページ全体でも、シェルの部分の応答でも）の後処理 evoRenderPaneFooterExtras() が、この関数の呼び出しを出す。
 *
 * 置き場所は body の直下の #evoNotice（シェルが #mainPane を差し替えても消えないため）。
 * shell.css の body 直下の規則の例外に入れてある（assets/docs/manager-shell.md）。見た目は common/notice.css。
 * 縦の位置は、ボタンの並びの下端を測って --evo-notice-top に入れる（並びの高さはテーマで違い、無い画面もある）。
 * シェルが #mainPane の中身を差し替えたときも測り直し、残っている知らせが移った先のボタンに重ならないようにする。
 */
(function () {
    'use strict';

    const DURATION = 3000;
    // ボタンの並び（無ければ本文の欄の上端）との間
    const GAP = 8;

    let region = null;
    let observer = null;

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
        document.body.appendChild(region);
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

    function openLink(e) {
        const href = this.getAttribute('href');
        if (window.EvoShell && typeof window.EvoShell.go === 'function') {
            e.preventDefault();
            window.EvoShell.go(href);
        }
        close(this.closest('.evo-notice'));
    }

    function show(message, options) {
        const opts = options || {};
        const type = ['success', 'info', 'warning'].indexOf(opts.type) >= 0 ? opts.type : 'success';
        const item = document.createElement('div');
        item.className = 'evo-notice is-' + type;
        // 1 件ずつ読み上げさせる（領域ごと live にすると、残っている知らせまで読み直すことがある）
        item.setAttribute('role', type === 'warning' ? 'alert' : 'status');

        const body = document.createElement('div');
        body.className = 'evo-notice-body';
        const text = document.createElement('p');
        text.className = 'evo-notice-message';
        text.textContent = String(message);
        body.appendChild(text);

        const links = Array.isArray(opts.links) ? opts.links : [];
        if (links.length) {
            const list = document.createElement('p');
            list.className = 'evo-notice-links';
            links.forEach(function (link) {
                if (!link || !link.href) {
                    return;
                }
                const a = document.createElement('a');
                a.href = link.href;
                a.textContent = link.label || link.href;
                a.addEventListener('click', openLink);
                list.appendChild(a);
            });
            body.appendChild(list);
        }
        item.appendChild(body);

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

        getRegion().appendChild(item);

        if (!opts.sticky) {
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
        return item;
    }

    const EvoNotice = {
        // header.inc.php が言語に合わせて上書きする
        closeLabel: 'Close',
        show: show,
    };

    window.EvoNotice = EvoNotice;
})();
