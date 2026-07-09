/*
 * EvoShell - 管理画面シェルのSPAナビゲーション (frameset代替)
 *
 * コンテンツ領域(#mainPane)だけをfetchで差し替え、メニューとツリーを
 * 再描画せずに画面遷移する。URLはHistory API(pushState)で同期する。
 * サーバー側は X-Requested-With: XMLHttpRequest を検出すると
 * header/footer抜きの断片HTMLを X-Evo-Pane ヘッダー付きで返す(manager/index.php)。
 * Vanilla JS (ES6+)。jQueryには依存しない。
 */
(function () {
    'use strict';

    const loadedScriptSrcs = new Set();

    // 初期ロード時に読み込み済みのscript srcを記録し、断片側での二重読込を防ぐ
    function registerInitialScripts() {
        document.querySelectorAll('script[src]').forEach(function (el) {
            loadedScriptSrcs.add(resolveUrl(el.getAttribute('src')));
        });
    }

    function resolveUrl(url) {
        return new URL(url, window.location.href).href;
    }

    function isManagerUrl(url) {
        const resolved = new URL(url, window.location.href);
        if (resolved.origin !== window.location.origin) {
            return false;
        }
        const base = window.location.pathname.replace(/[^\/]*$/, '');
        return resolved.pathname === base + 'index.php' || resolved.pathname === base;
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function confirmIfDirty() {
        if (!window.documentDirty) {
            return true;
        }
        const ok = window.confirm(EvoShell.unsavedMessage);
        if (ok) {
            window.documentDirty = false;
        }
        return ok;
    }

    function startWork() {
        if (window.mainMenu && typeof window.mainMenu.work === 'function') {
            window.mainMenu.work();
        }
    }

    function stopWork() {
        if (window.mainMenu && typeof window.mainMenu.stopWork === 'function') {
            window.mainMenu.stopWork();
        }
    }

    // 差し替えたHTML内の<script>は自動実行されないため、生成し直して順次実行する
    function executeScripts(container, done) {
        const scripts = Array.from(container.querySelectorAll('script'));

        function runNext(index) {
            if (index >= scripts.length) {
                if (done) done();
                return;
            }
            const old = scripts[index];
            const src = old.getAttribute('src');

            if (src) {
                const resolved = resolveUrl(src);
                if (loadedScriptSrcs.has(resolved)) {
                    runNext(index + 1);
                    return;
                }
                loadedScriptSrcs.add(resolved);
                const el = document.createElement('script');
                Array.from(old.attributes).forEach(function (attr) {
                    if (attr.name === 'async' || attr.name === 'defer') {
                        return;
                    }
                    el.setAttribute(attr.name, attr.value);
                });
                el.async = false;
                el.onload = el.onerror = function () {
                    runNext(index + 1);
                };
                old.parentNode.replaceChild(el, old);
                return;
            }

            const el = document.createElement('script');
            Array.from(old.attributes).forEach(function (attr) {
                if (attr.name === 'async' || attr.name === 'defer') {
                    return;
                }
                el.setAttribute(attr.name, attr.value);
            });
            el.text = old.text;
            old.parentNode.replaceChild(el, old);
            runNext(index + 1);
        }

        runNext(0);
    }

    function fullReload(url) {
        window.location.href = url;
    }

    function isFullDocumentHtml(text) {
        return /<\s*html\b/i.test(text);
    }

    function paneTargetFromHash(pane, hash) {
        if (!pane || !hash || hash === '#') {
            return null;
        }
        const id = decodeURIComponent(hash.slice(1));
        if (!id) {
            return null;
        }
        const byId = pane.ownerDocument.getElementById(id);
        if (byId && pane.contains(byId)) {
            return byId;
        }
        return Array.from(pane.querySelectorAll('[name]')).find(function (element) {
            return element.getAttribute('name') === id;
        }) || null;
    }

    function scrollPaneToHash(url) {
        const pane = document.getElementById('mainPane');
        const hash = new URL(url, window.location.href).hash;
        const target = pane ? paneTargetFromHash(pane, hash) : null;
        if (!target) {
            return;
        }
        requestAnimationFrame(function () {
            const paneTop = pane.getBoundingClientRect().top;
            const targetTop = target.getBoundingClientRect().top;
            pane.scrollTop += targetTop - paneTop;
            window.scrollTo(0, 0);
        });
    }

    function responseUrlWithRequestHash(responseUrl, requestUrl) {
        const finalUrl = new URL(responseUrl, window.location.href);
        const requested = new URL(requestUrl, window.location.href);
        if (requested.hash) {
            finalUrl.hash = requested.hash;
        }
        return finalUrl.href;
    }

    // 断片内のスタイルシートを<head>へ移す。
    // 断片ごと消えると、body直下に残るUI部品(datepickerのカレンダー等)が
    // スタイルを失いシェルのグリッドを崩すため、headに恒久配置して重複は除去する
    function hoistStylesheets(container) {
        container.querySelectorAll('link[rel="stylesheet"]').forEach(function (link) {
            const href = resolveUrl(link.getAttribute('href'));
            const exists = Array.from(document.head.querySelectorAll('link[rel="stylesheet"]')).some(function (el) {
                return resolveUrl(el.getAttribute('href')) === href;
            });
            if (exists) {
                link.remove();
            } else {
                document.head.appendChild(link);
            }
        });
    }

    // 応答全体でドキュメントを書き換える(断片でない完全HTMLが返った場合の保険)
    function replaceDocument(html) {
        document.open();
        document.write(html);
        document.close();
    }

    // 前ページが残したグローバルなUI部品を破棄する(差し替え前のクリーンアップ)
    function teardownPane(pane) {
        document.dispatchEvent(new CustomEvent('evoshell:unload'));

        // TinyMCEはEditorManagerに旧インスタンスが残ると同じidで再初期化できない
        if (window.tinymce && typeof window.tinymce.remove === 'function') {
            window.tinymce.remove();
        }
    }

    function applyFragment(html, finalUrl, push) {
        const pane = document.getElementById('mainPane');
        if (!pane) {
            fullReload(finalUrl);
            return;
        }

        teardownPane(pane);
        window.documentDirty = false;
        pane.innerHTML = html;
        pane.scrollTop = 0;
        hoistStylesheets(pane);

        if (push) {
            window.history.pushState({ url: finalUrl }, '', finalUrl);
        }

        // 遷移のたびにツリーのクリック動作を既定(開く)へ戻す(旧header.inc.php相当)
        if (window.tree) {
            window.tree.ca = 'open';
        }

        executeScripts(pane, function () {
            // 断片内のjQuery ready相当は即時実行済み。共通後処理を行う
            if (window.jQuery) {
                window.jQuery('#preLoader').hide();
                window.jQuery('.tooltip').powerTip({ fadeInTime: '0', placement: 'e' });
            }
            // window.load / DOMContentLoaded 依存の既存ライブラリを再初期化する
            if (window.fdTableSort && typeof window.fdTableSort.init === 'function') {
                window.fdTableSort.init(false);
            }
            if (window.MODXSortable && typeof window.MODXSortable.initAll === 'function') {
                window.MODXSortable.initAll();
            }
            // 編集系アクションでは未保存変更の検知を再バインドする(header.inc.phpで定義)
            if (typeof window.evoBindDirtyTracking === 'function') {
                const action = new URL(finalUrl, window.location.href).searchParams.get('a');
                window.evoBindDirtyTracking(action);
            }
            handleRefreshParam(finalUrl);
            scrollPaneToHash(finalUrl);
            stopWork();
            document.dispatchEvent(new CustomEvent('evoshell:load', { detail: { url: finalUrl } }));
        });
    }

    // processorのリダイレクトURLに付くr=Nはツリー/メニュー再読込の合図
    function handleRefreshParam(url) {
        const r = new URL(url, window.location.href).searchParams.get('r');
        if (r && window.mainMenu && typeof window.mainMenu.reloadPane === 'function') {
            window.mainMenu.reloadPane(parseInt(r, 10));
        }
    }

    // Content-Disposition: attachment の応答をファイル保存として処理する
    function downloadResponse(response) {
        const disposition = response.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
        let filename = 'download';
        if (match) {
            try {
                filename = decodeURIComponent(match[1]);
            } catch (e) {
                // URLエンコードされていない/不正な%シーケンスはそのまま使う
                filename = match[1];
            }
        }
        return response.blob().then(function (blob) {
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(link.href);
        });
    }

    function getFormData(form, submitter) {
        const formData = new FormData(form);
        if (submitter && submitter.name && !submitter.disabled) {
            formData.append(submitter.name, submitter.value);
        }
        return formData;
    }

    function request(url, options, push) {
        startWork();
        const headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, options.headers || {});
        fetch(url, Object.assign({}, options, { headers: headers, credentials: 'same-origin' }))
            .then(function (response) {
                if (response.headers.get('X-Evo-Login') === 'required') {
                    // セッション切れ。ログイン画面へフルリロードする
                    fullReload('index.php');
                    return null;
                }
                if (/attachment/i.test(response.headers.get('Content-Disposition') || '')) {
                    return downloadResponse(response).then(stopWork);
                }
                return response.text().then(function (text) {
                    if (response.headers.get('X-Evo-Pane') === '1') {
                        applyFragment(text, responseUrlWithRequestHash(response.url, url), push);
                    } else if (options.method === 'POST' || isFullDocumentHtml(text)) {
                        // 断片で返せない応答(モジュール実行等)はドキュメントごと差し替える
                        replaceDocument(text);
                    } else {
                        fullReload(url);
                    }
                    return null;
                });
            })
            .catch(function () {
                stopWork();
                if (options.method === 'POST') {
                    // POST(保存等)の通信失敗を無反応のまま放置しない。
                    // フォームの再送信は避け、現在の画面を再取得して復旧させる
                    window.alert(EvoShell.networkErrorMessage);
                    fullReload(window.location.href);
                } else {
                    fullReload(url);
                }
            });
    }

    // ツリー/メニュー部品を再取得して差し替える(セッション切れ検出・失敗時の後始末込み)
    function reloadPartial(url, elementId) {
        const current = document.getElementById(elementId);
        if (!current) {
            return;
        }
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (response.headers.get('X-Evo-Login') === 'required') {
                    fullReload('index.php');
                    return null;
                }
                return response.text();
            })
            .then(function (html) {
                if (html === null) {
                    return;
                }
                const tpl = document.createElement('template');
                tpl.innerHTML = html;
                const fresh = tpl.content.getElementById(elementId);
                if (!fresh) {
                    return;
                }
                current.replaceWith(fresh);
                executeScripts(fresh);
            })
            .catch(function () {
                stopWork();
            });
    }

    const EvoShell = {
        // header.inc.phpが言語別メッセージで上書きする
        unsavedMessage: 'Your changes are not saved. Continue?',
        networkErrorMessage: 'A network error occurred. The page will be reloaded.',

        navigate: function (url, opts) {
            const push = !opts || opts.push !== false;
            if (!confirmIfDirty()) {
                return;
            }
            request(url, { method: 'GET' }, push);
        },

        submit: function (form, submitter) {
            const method = (form.getAttribute('method') || 'GET').toUpperCase();
            const action = form.getAttribute('action') || window.location.href;
            const formData = getFormData(form, submitter);

            if (method === 'GET') {
                const params = new URLSearchParams(formData);
                const url = action.split('?')[0] + '?' + params.toString();
                window.documentDirty = false;
                request(url, { method: 'GET' }, true);
                return;
            }

            window.documentDirty = false;
            request(action, {
                method: 'POST',
                body: formData,
                headers: { 'X-CSRF-Token': getCsrfToken() }
            }, true);
        },

        reloadTree: function () {
            reloadPartial('index.php?a=1&f=tree', 'treePane');
        },

        reloadMenu: function () {
            const bar = document.getElementById('topMenu');
            if (!bar || bar.tagName === 'BODY') {
                window.location.reload();
                return;
            }
            reloadPartial('index.php?a=1&f=menu', 'topMenu');
        }
    };

    window.EvoShell = EvoShell;

    // 既存コードのform.submit()直接呼び出し(submitイベント非発火)もシェル経由にする
    const nativeFormSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        const action = this.getAttribute('action') || window.location.href;
        if (
            document.body.classList.contains('evo-shell')
            && this.closest('#mainPane')
            && !this.hasAttribute('data-no-ajax')
            && !this.hasAttribute('target')
            && isManagerUrl(action)
        ) {
            EvoShell.submit(this);
            return;
        }
        nativeFormSubmit.call(this);
    };

    document.addEventListener('DOMContentLoaded', function () {
        registerInitialScripts();

        if (!document.body.classList.contains('evo-shell')) {
            return;
        }

        window.history.replaceState({ url: window.location.href }, '', window.location.href);

        // リンククリックの委譲。data-no-ajax / target付き / 外部URL / #アンカーは素通しする
        document.addEventListener('click', function (e) {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                return;
            }
            const link = e.target.closest('a[href]');
            if (!link || link.hasAttribute('data-no-ajax') || link.hasAttribute('target') || link.hasAttribute('download')) {
                return;
            }
            const href = link.getAttribute('href');
            if (!href || href.startsWith('javascript:')) {
                return;
            }
            if (href.startsWith('#')) {
                const url = new URL(window.location.href);
                url.hash = href;
                const pane = document.getElementById('mainPane');
                if (paneTargetFromHash(pane, url.hash)) {
                    e.preventDefault();
                    window.history.pushState({ url: url.href }, '', url.href);
                    scrollPaneToHash(url.href);
                }
                return;
            }
            if (!isManagerUrl(href)) {
                return;
            }
            const url = new URL(href, window.location.href);
            if (!url.searchParams.get('a')) {
                return;
            }
            e.preventDefault();
            EvoShell.navigate(url.href);
        });

        // コンテンツ領域のフォーム送信の委譲
        document.addEventListener('submit', function (e) {
            if (e.defaultPrevented) {
                return;
            }
            const form = e.target;
            if (form.tagName !== 'FORM' || !form.closest('#mainPane')) {
                return;
            }
            if (form.hasAttribute('data-no-ajax') || form.hasAttribute('target')) {
                return;
            }
            const action = form.getAttribute('action') || window.location.href;
            if (!isManagerUrl(action)) {
                return;
            }
            e.preventDefault();
            EvoShell.submit(form, e.submitter);
        });

        // ブラウザの戻る/進む
        window.addEventListener('popstate', function (e) {
            const url = (e.state && e.state.url) ? e.state.url : window.location.href;
            EvoShell.navigate(url, { push: false });
        });

        scrollPaneToHash(window.location.href);
    });
})();
