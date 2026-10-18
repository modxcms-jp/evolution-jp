/**
 * 管理画面のタブ（WebFX Tab Pane 1.02 の呼び方に合わせて書き直したもの）
 *
 * <div class="tab-pane" id="…">
 *   <div class="tab-page" id="…"><h2 class="tab">見出し</h2> 中身 </div>
 * </div>
 * new WebFXTabPane(el, bUseCookie) / tp.addTabPage(el, callback) / setSelectedIndex() / getSelectedIndex()、
 * setupAllTabs() / disposeAllTabs()、要素の tabPane / tabPage プロパティは今までと同じ。
 * テーマの CSS が使うクラス（dynamic-tab-pane-control / .tab-row / .tab / .selected）と、
 * 開いたタブを覚える Cookie（webfxtab_<id>）も変えない。
 * - 覚えた番号のタブが無い・隠れている（ManagerManager の mm_hideTabs など）ときは、表示されている最初のタブを開く
 * - 見出しは role="tab"。Tab キーで選ばれたタブへ移り、左右・Home・End で切り替える。Enter・Space でも開く
 * - 見出しの中身は <span> で包む（テーマの CSS と mm_renameTab が前提にしている）
 */

// クラスは var への代入で定義する。管理画面のシェルが document.write で画面全体を
// 差し替えると同じ window でこのファイルが再評価され、class 宣言だと二重宣言エラーになるため
var WebFXTabPane = class WebFXTabPane {
    constructor(el, bUseCookie) {
        if (el == null) return;

        this.element = el;
        el.tabPane = this;
        this.pages = [];
        this.useCookie = bUseCookie != null ? bUseCookie : true;
        // 選ぶつもりのタブの番号（覚えた番号）。表示しているタブは shownIndex
        this.selectedIndex = 0;
        this.shownIndex = null;
        this.settled = false;

        el.classList.add('dynamic-tab-pane-control');
        this.tabRow = document.createElement('div');
        this.tabRow.className = 'tab-row';
        this.tabRow.setAttribute('role', 'tablist');
        el.insertBefore(this.tabRow, el.firstChild);

        if (this.useCookie) {
            const n = Number(WebFXTabPane.getCookie('webfxtab_' + el.id));
            this.selectedIndex = Number.isInteger(n) && n >= 0 ? n : 0;
        }

        this._onKeyDown = (e) => this.keyDown(e);
        this.tabRow.addEventListener('keydown', this._onKeyDown);
        // 見出しが後から隠されたら、表示されているタブへ移す
        this.observer = typeof MutationObserver === 'function'
            ? new MutationObserver(() => this.ensureVisible())
            : null;

        Array.from(el.children).forEach((child) => {
            if (child.classList.contains('tab-page')) {
                this.addTabPage(child);
            }
        });

        // 後に続く addTabPage と、画面のスクリプト（MM など）が終わってから確かめる
        const settle = () => {
            this.settled = true;
            this.ensureVisible();
        };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => setTimeout(settle, 0), { once: true });
        } else {
            setTimeout(settle, 0);
        }
    }

    static setCookie(sName, sValue, nDays) {
        let expires = '';
        if (nDays) {
            const d = new Date();
            d.setTime(d.getTime() + nDays * 24 * 60 * 60 * 1000);
            expires = '; expires=' + d.toUTCString();
        }
        // 公開側（Ditto のデバッグ画面など）では baseurl が無い
        const path = typeof window.baseurl === 'string' && window.baseurl ? window.baseurl : '/';
        document.cookie = sName + '=' + encodeURIComponent(sValue) + expires + '; path=' + path + '; SameSite=Lax';
    }

    static getCookie(sName) {
        for (const cookie of document.cookie.split('; ')) {
            const i = cookie.indexOf('=');
            if (cookie.slice(0, i) === sName) {
                return decodeURIComponent(cookie.slice(i + 1));
            }
        }
        return null;
    }

    static removeCookie(name) {
        WebFXTabPane.setCookie(name, '', -1);
    }

    static isHidden(page) {
        return !page || page.tab.hidden || getComputedStyle(page.tab).display === 'none';
    }

    // n のタブを開いて、開くタブとして覚える
    setSelectedIndex(n) {
        n = Number(n);
        if (!this.pages[n]) return;
        this.selectedIndex = n;
        this.show(n);
        if (this.useCookie) {
            WebFXTabPane.setCookie('webfxtab_' + this.element.id, n); // session cookie
        }
    }

    getSelectedIndex() {
        return this.selectedIndex;
    }

    show(n) {
        if (this.shownIndex === n) return;
        this.pages.forEach((page, i) => (i === n ? page.show() : page.hide()));
        this.shownIndex = n;
    }

    // 覚えた番号のタブが無い・隠れているときは、表示されている最初のタブを開く（覚えた番号は変えない）
    ensureVisible() {
        if (!this.pages) return;
        const wanted = this.pages[this.selectedIndex];
        if (wanted && !WebFXTabPane.isHidden(wanted)) {
            this.show(this.selectedIndex);
            return;
        }
        if (!this.settled && !wanted) return; // まだ addTabPage が続く
        const first = this.pages.findIndex((page) => !WebFXTabPane.isHidden(page));
        if (first !== -1) {
            this.show(first);
        }
    }

    addTabPage(oElement, callBackFnc) {
        if (oElement.tabPage && oElement.tabPage.tabPane === this) // already added
            return oElement.tabPage;

        const n = this.pages.length;
        const tp = new WebFXTabPage(oElement, this, n, callBackFnc);
        this.pages[n] = tp;
        this.tabRow.appendChild(tp.tab);
        tp.hide();
        if (this.observer) {
            this.observer.observe(tp.tab, { attributes: true, attributeFilter: ['style', 'class', 'hidden'] });
        }
        if (n === this.selectedIndex || this.settled) {
            this.ensureVisible();
        }
        return tp;
    }

    keyDown(e) {
        const tab = e.target.closest ? e.target.closest('.tab') : null;
        if (!tab || !tab.tabPage) return;
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            tab.tabPage.select();
            return;
        }
        const visible = this.pages.filter((page) => !WebFXTabPane.isHidden(page));
        let i = visible.indexOf(tab.tabPage);
        if (e.key === 'ArrowRight') i = (i + 1) % visible.length;
        else if (e.key === 'ArrowLeft') i = (i - 1 + visible.length) % visible.length;
        else if (e.key === 'Home') i = 0;
        else if (e.key === 'End') i = visible.length - 1;
        else return;
        e.preventDefault();
        visible[i].select();
        visible[i].tab.focus();
    }

    dispose() {
        if (this.observer) this.observer.disconnect();
        this.tabRow.removeEventListener('keydown', this._onKeyDown);
        this.pages.forEach((page) => page.dispose());
        this.element.tabPane = null;
        this.element = null;
        this.tabRow = null;
        this.observer = null;
        this.pages = null;
    }

    get classNameTag() {
        return 'dynamic-tab-pane-control';
    }
};

// タブの 1 枚。WebFXTabPane#addTabPage から作る
var WebFXTabPage = class WebFXTabPage {
    constructor(el, tabPane, nIndex, callBackFnc) {
        if (el == null) return;

        this.element = el;
        el.tabPage = this;
        this.tabPane = tabPane;
        this.index = nIndex;
        this.callBack = callBackFnc;
        this.tab = Array.from(el.children).find((child) => child.classList.contains('tab'));
        this.tab.tabPage = this;

        // 見出しの中身を <span> で包む（テーマの CSS と mm_renameTab が前提にしている）
        this.aElement = document.createElement('span');
        while (this.tab.firstChild) this.aElement.appendChild(this.tab.firstChild);
        this.tab.appendChild(this.aElement);

        const uid = el.id || 'tabpage-' + Math.random().toString(36).slice(2, 10);
        if (!el.id) el.id = uid;
        if (!this.tab.id) this.tab.id = uid + '-tab';
        this.tab.setAttribute('role', 'tab');
        this.tab.setAttribute('aria-controls', el.id);
        el.setAttribute('role', 'tabpanel');
        el.setAttribute('aria-labelledby', this.tab.id);

        this._onClick = (e) => {
            e.preventDefault();
            this.select();
        };
        this.tab.addEventListener('click', this._onClick);
    }

    show() {
        this.tab.classList.add('selected');
        this.tab.setAttribute('aria-selected', 'true');
        this.tab.tabIndex = 0;
        this.element.style.display = 'block';
    }

    hide() {
        this.tab.classList.remove('selected');
        this.tab.setAttribute('aria-selected', 'false');
        this.tab.tabIndex = -1;
        this.element.style.display = 'none';
    }

    select() {
        this.tabPane.setSelectedIndex(this.index);
        if (this.callBack) this.callBack();
        return false;
    }

    dispose() {
        this.tab.removeEventListener('click', this._onClick);
        this.tab.tabPage = null;
        this.element.tabPage = null;
        this.aElement = null;
        this.tab = null;
        this.tabPane = null;
        this.element = null;
    }
};

// 初期化していない .tab-pane と .tab-page を初期化する
function setupAllTabs() {
    document.querySelectorAll('.tab-pane').forEach((el) => {
        if (!el.tabPane) {
            new WebFXTabPane(el);
        }
    });
    document.querySelectorAll('.tab-page').forEach((el) => {
        if (!el.tabPage && el.parentNode.classList.contains('tab-pane')) {
            el.parentNode.tabPane.addTabPage(el);
        }
    });
}

function disposeAllTabs() {
    document.querySelectorAll('.tab-pane').forEach((el) => {
        if (el.tabPane) {
            el.tabPane.dispose();
        }
    });
}

document.addEventListener('DOMContentLoaded', setupAllTabs);
