/**
 * 管理画面のツールチップ（jquery.powertip の置き換え）。jQuery には依存しない
 *
 * class="tooltip" の要素にマウスを乗せるかフォーカスすると、title の内容を右横（入らなければ左横）に出す。
 * - 内容は HTML として表示する（説明文の <br> など。powertip と同じ）。data-powertip があればそちらを優先する
 *   （ManagerManager の mm_changefieldhelp がエスケープした説明文を入れる）
 * - ブラウザ標準の title の表示と重ならないよう、最初に出すときに title を data-evo-tooltip へ移す
 * - document でイベントを受けるので、シェルが画面を差し替えた後の要素も初期化なしで動く
 */
(function () {
    'use strict';

    const STYLE = ''
        + '#evoTooltip{position:fixed;z-index:2147483647;max-width:min(520px,calc(100vw - 32px));padding:8px 12px;border-radius:6px;'
        + 'background:rgba(30,34,42,.94);color:#fff;font:13px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif;'
        + 'box-shadow:0 4px 16px rgba(0,0,0,.2);pointer-events:none;word-break:break-word}'
        + '#evoTooltip[hidden]{display:none}'
        + '#evoTooltip::before{content:"";position:absolute;top:50%;margin-top:-6px;border:6px solid transparent}'
        + '#evoTooltip.evo-tooltip-e::before{left:-12px;border-right-color:rgba(30,34,42,.94)}'
        + '#evoTooltip.evo-tooltip-w::before{right:-12px;border-left-color:rgba(30,34,42,.94)}';
    const GAP = 10;

    let tip = null;
    let current = null;

    function getTip() {
        if (tip) {
            return tip;
        }
        const style = document.createElement('style');
        style.textContent = STYLE;
        document.head.appendChild(style);
        tip = document.createElement('div');
        tip.id = 'evoTooltip';
        tip.setAttribute('role', 'tooltip');
        tip.hidden = true;
        document.body.appendChild(tip);
        return tip;
    }

    function content(el) {
        if (el.hasAttribute('title')) {
            el.setAttribute('data-evo-tooltip', el.getAttribute('title'));
            el.removeAttribute('title');
        }
        if (el.hasAttribute('data-powertip')) {
            return el.getAttribute('data-powertip');
        }
        return el.getAttribute('data-evo-tooltip') || '';
    }

    function show(el) {
        const html = content(el);
        if (!html) {
            return;
        }
        const t = getTip();
        current = el;
        t.innerHTML = html;
        t.hidden = false;
        el.setAttribute('aria-describedby', 'evoTooltip');

        const r = el.getBoundingClientRect();
        const w = t.offsetWidth;
        const h = t.offsetHeight;
        const east = r.right + GAP + w <= window.innerWidth || r.left - GAP - w < 0;
        t.className = east ? 'evo-tooltip-e' : 'evo-tooltip-w';
        t.style.left = (east ? r.right + GAP : r.left - GAP - w) + 'px';
        const top = r.top + r.height / 2 - h / 2;
        t.style.top = Math.max(4, Math.min(top, window.innerHeight - h - 4)) + 'px';
    }

    function hide() {
        if (current) {
            current.removeAttribute('aria-describedby');
            current = null;
        }
        if (tip) {
            tip.hidden = true;
        }
    }

    function target(node) {
        return node && node.closest ? node.closest('.tooltip') : null;
    }

    document.addEventListener('mouseover', function (e) {
        const el = target(e.target);
        if (el && el !== current) {
            show(el);
        }
    });
    document.addEventListener('mouseout', function (e) {
        const el = target(e.target);
        if (el && el === current && !el.contains(e.relatedTarget)) {
            hide();
        }
    });
    document.addEventListener('focusin', function (e) {
        const el = target(e.target);
        if (el) {
            show(el);
        }
    });
    document.addEventListener('focusout', function (e) {
        if (target(e.target) === current) {
            hide();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && current) {
            hide();
        }
    });
    // スクロールや画面の差し替えで要素が動く・消えるので閉じる
    window.addEventListener('scroll', hide, true);
    document.addEventListener('evoshell:unload', hide);
    document.addEventListener('evoshell:modalclose', hide);

    window.EvoTooltip = { hide: hide };
})();
