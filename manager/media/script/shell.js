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

    // 現在表示中の内容に対応するURL(popstateキャンセル時に履歴を戻すために追跡する)
    let currentUrl = window.location.href;
    // モーダルに表示中のコンテンツのURL。モーダル内フォームのaction=""(自己送信)は
    // ページのURLではなくこちらを基準に解決する(モーダルはhistoryに触らないため乖離する)
    let currentModalUrl = '';
    // モーダル内でPOST(保存)が行われたか。真なら閉じる際に背後の#mainPaneを再取得する
    let modalDirty = false;
    // openPicker/openFilePickerで開いたピッカーの選択結果を受け取るコールバック
    let pickerCallback = null;

    function resolveUrl(url) {
        return new URL(url, window.location.href).href;
    }

    function hasLiveScriptTag(resolvedUrl, exceptNode) {
        return Array.from(document.querySelectorAll('script[src]')).some(function (el) {
            if (el === exceptNode) {
                return false;
            }
            const src = el.getAttribute('src');
            return src && resolveUrl(src) === resolvedUrl;
        });
    }

    function isManagerUrl(url) {
        const resolved = new URL(url, window.location.href);
        if (resolved.origin !== window.location.origin) {
            return false;
        }
        const base = window.location.pathname.replace(/[^\/]*$/, '');
        return resolved.pathname === base + 'index.php' || resolved.pathname === base;
    }

    function normalizeManagerUrl(url) {
        const resolved = new URL(url, window.location.href);
        if (!isManagerUrl(resolved.href)) {
            return resolved.href;
        }
        if (resolved.searchParams.get('a') === '2') {
            resolved.searchParams.delete('a');
            resolved.pathname = window.location.pathname.replace(/(?:index\.php)?$/, '');
        }
        return resolved.href;
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
            // 先に動いたスクリプトが、まだ動かしていないスクリプトごと画面を消すことがある
            // （MM の accessdenied など）。文書から外れたスクリプトは、ブラウザと同じく動かさない
            if (!old.isConnected) {
                runNext(index + 1);
                return;
            }
            const src = old.getAttribute('src');

            if (src) {
                const resolved = resolveUrl(src);
                // 「過去に一度読んだ」だけで永久スキップすると、#mainPane差し替えで
                // 消えたページ専用script(TinyMCE初期化補助など)が再訪時に復活しない。
                // 現在のDOMに同一scriptタグが生きている場合だけ重複読込を避ける
                if (hasLiveScriptTag(resolved, old)) {
                    runNext(index + 1);
                    return;
                }
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
        window.location.href = normalizeManagerUrl(url);
    }

    // セッション切れのとき、ログイン画面を出すためにページ全体を読み直す。
    // # を付けたままだと、今と同じ URL（戻る・進むの移動先など）への代入はページ内の移動になり、読み直されない
    function reloadForLogin(url) {
        window.location.href = normalizeManagerUrl(url).split('#')[0];
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
        return normalizeManagerUrl(finalUrl.href);
    }

    // 断片内のスタイルシートを<head>へ移す。
    // 断片ごと消えると、body直下に残るUI部品(ライブラリのポップアップ等)が
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

    // 応答全体でドキュメントを書き換える(断片でない完全HTMLが返った場合の保険)。
    // document.writeはアドレスバーを更新しないため、応答先URLをhistoryへ反映する
    function replaceDocument(html, finalUrl) {
        if (finalUrl && finalUrl !== window.location.href) {
            window.history.replaceState({ url: finalUrl }, '', finalUrl);
        }
        document.open();
        document.write(html);
        document.close();
    }

    // 前ページが残したグローバルなUI部品を破棄する(差し替え前のクリーンアップ)
    function teardownPane(pane) {
        document.dispatchEvent(new CustomEvent('evoshell:unload'));

        // TinyMCEはEditorManagerに旧インスタンスが残ると同じidで再初期化できない。
        // ただし全インスタンスを無条件にremove()すると、モーダルを閉じただけで
        // 背後の#mainPane側のエディタまで消えてしまう(モーダル内にエディタを含まない
        // 操作でも発生する重大な副作用だった)。paneの外側にあるエディタは対象外にする
        if (window.tinymce && Array.isArray(window.tinymce.editors)) {
            window.tinymce.editors.slice().forEach(function (editor) {
                const el = typeof editor.getElement === 'function' ? editor.getElement() : null;
                if (el && pane.contains(el)) {
                    editor.remove();
                }
            });
        }
    }

    // finalUrl はアドレスバーと履歴に残すURL、screenUrl は表示する画面のURL（省略時は finalUrl）。
    // リダイレクトしないPOSTの結果では、アドレスバーは元の画面のまま、表示するのは操作の結果の画面になる
    function applyFragment(html, finalUrl, push, screenUrl) {
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

        currentUrl = finalUrl;
        if (push) {
            // shellNav: シェルの中で移ってきた履歴の目印（前の履歴も管理画面だと分かる。MM の accessdenied の「戻る」が使う）
            window.history.pushState({ url: finalUrl, shellNav: true }, '', finalUrl);
        }

        // 遷移のたびにツリーのクリック動作を既定(開く)へ戻す(旧header.inc.php相当)
        if (window.tree) {
            window.tree.ca = 'open';
        }

        executeScripts(pane, function () {
            // 断片内のready相当は即時実行済み。共通後処理を行う
            const preLoader = document.getElementById('preLoader');
            if (preLoader) {
                preLoader.style.display = 'none';
            }
            // window.load / DOMContentLoaded 依存の既存ライブラリを再初期化する
            if (window.MODXSortable && typeof window.MODXSortable.init === 'function') {
                window.MODXSortable.init();
            }
            // 編集系アクションでは未保存変更の検知を再バインドする(header.inc.phpで定義)
            if (typeof window.evoBindDirtyTracking === 'function') {
                const action = new URL(finalUrl, window.location.href).searchParams.get('a');
                window.evoBindDirtyTracking(action);
            }
            handleRefreshParam(finalUrl);
            scrollPaneToHash(finalUrl);
            stopWork();
            document.dispatchEvent(new CustomEvent('evoshell:load', { detail: { url: finalUrl, screenUrl: screenUrl || finalUrl } }));
        });
    }

    // 汎用モーダル(data-modalリンク・EvoShell.openModal用)のDOMを遅延生成する
    function getModalElements() {
        let overlay = document.getElementById('evoShellModalOverlay');
        if (overlay) {
            return { overlay: overlay, body: document.getElementById('evoShellModalBody') };
        }
        overlay = document.createElement('div');
        overlay.id = 'evoShellModalOverlay';
        overlay.className = 'hidden';
        overlay.innerHTML =
            '<div id="evoShellModal" role="dialog" aria-modal="true">' +
            '<div id="evoShellModalHeader">' +
            '<button type="button" class="evo-modal-close" aria-label="Close">&times;</button>' +
            '</div>' +
            '<div id="evoShellModalBody"></div>' +
            '<div id="evoShellModalFooter"></div>' +
            '</div>';
        document.body.appendChild(overlay);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                closeModal();
            }
        });
        overlay.querySelector('.evo-modal-close').addEventListener('click', function () {
            closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !overlay.classList.contains('hidden')) {
                closeModal();
            }
        });
        // TinyMCE等のダイアログはdocumentのfocusinを監視してフォーカスを奪還するため、
        // モーダル内のフォーカス移動を外へ伝播させない(入力欄が使えなくなるのを防ぐ)
        overlay.addEventListener('focusin', function (e) {
            e.stopPropagation();
        });

        return { overlay: overlay, body: document.getElementById('evoShellModalBody') };
    }

    // reloadMain=falseなら、モーダル内の保存(modalDirty)があっても背後の#mainPaneを再取得しない
    // (閉じた直後に別の画面へnavigateする場合、再取得と競合して古い画面が残るのを防ぐ)
    function closeModal(reloadMain) {
        const overlay = document.getElementById('evoShellModalOverlay');
        if (!overlay || overlay.classList.contains('hidden')) {
            return;
        }
        const body = document.getElementById('evoShellModalBody');
        if (body) {
            teardownPane(body);
            body.innerHTML = '';
        }
        currentModalUrl = '';
        pickerCallback = null;
        overlay.classList.add('hidden');
        if (modalDirty) {
            // モーダルで保存された変更を背後の画面へ反映する(#mainPaneを黙って再取得)
            modalDirty = false;
            if (reloadMain !== false) {
                request(currentUrl, { method: 'GET' }, false);
            }
        }
        document.dispatchEvent(new CustomEvent('evoshell:modalclose'));
    }

    function applyModalFragment(html, finalUrl) {
        const { overlay, body } = getModalElements();
        if (!body) {
            return;
        }
        overlay.querySelector('.evo-modal-close').setAttribute(
            'aria-label',
            window.EvoShell && typeof window.EvoShell.closeLabel === 'string' && window.EvoShell.closeLabel
                ? window.EvoShell.closeLabel
                : 'Close'
        );
        if (finalUrl) {
            currentModalUrl = finalUrl;
        }
        teardownPane(body);
        body.innerHTML = html;
        body.scrollTop = 0;
        hoistStylesheets(body);
        overlay.classList.remove('hidden');

        // #actionsはスクロールしないフッターへ、見出し(h1)は固定ヘッダーへ移す
        // (断片側は#mainPane前提のマークアップのため、移動先の存在はここでしか保証できない)
        const footer = document.getElementById('evoShellModalFooter');
        const actions = body.querySelector('#actions');
        if (footer) {
            footer.innerHTML = '';
            if (actions) {
                footer.appendChild(actions);
            }
        }

        const header = document.getElementById('evoShellModalHeader');
        const closeButton = header ? header.querySelector('.evo-modal-close') : null;
        const heading = body.querySelector('h1');
        if (header) {
            header.querySelectorAll('h1').forEach(function (el) {
                el.remove();
            });
            if (heading) {
                header.insertBefore(heading, closeButton);
            }
        }

        executeScripts(body, function () {
            if (window.MODXSortable && typeof window.MODXSortable.init === 'function') {
                window.MODXSortable.init(body);
            }
            stopWork();
            document.dispatchEvent(new CustomEvent('evoshell:modalload'));
        });
    }

    // processorのリダイレクトURLに付くrefreshは、ツリー・メニュー・管理画面の読み直しの合図（受け口はEvoShell.refresh）
    function handleRefreshParam(url) {
        EvoShell.refresh(new URL(url, window.location.href).searchParams.get('refresh'));
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

    // 応答待ちのフォーム。同じフォームの送信が重ならないよう、送信中は次の送信を無視する。
    // 値は送信中の印を付けた要素と押したボタン(フォームが画面から外れても、その要素から確実に外す)
    const submittingForms = new WeakMap();

    // 押したボタンだけに出すアニメーションの種類(テーマの --evo-submitting-style)
    const SUBMITTING_STYLES = ['spinner', 'stripes', 'dots'];
    const CLICK_TARGET_SELECTOR = '.actionButtons a, button[type="submit"], button:not([type]), input[type="submit"], input[type="image"]';

    // 直前にクリックされた見えるボタン。submitterの無い送信(form.submit())で押したボタンを知るために使う。
    // 同じクリックの処理の中で送信されたときだけ有効(クリックの直後のタスクで消す)。
    // 送信しないボタンのクリックが残り、後の別の送信(入力欄でEnterなど)の押したボタンと取り違えないため
    let lastClicked = null;

    function isVisible(el) {
        return el.getClientRects().length > 0;
    }

    // アニメーションを出すボタンを決める。決まらなければnull(今の半透明のまま)
    function resolveTrigger(submitter, targets) {
        const clicked = lastClicked;
        lastClicked = null;
        let trigger = null;
        if (submitter instanceof Element && isVisible(submitter)) {
            trigger = submitter;
        } else if (clicked && clicked.isConnected && targets.some(function (t) {
            return t.contains(clicked);
        })) {
            trigger = clicked;
        }
        if (!trigger) {
            return null;
        }
        const style = getComputedStyle(trigger).getPropertyValue('--evo-submitting-style').trim();
        if (SUBMITTING_STYLES.indexOf(style) === -1) {
            return null;
        }
        // inputは疑似要素を持てない(明滅のみ)ので、種類のクラスは付けない
        trigger.classList.add('evo-submitting-trigger');
        if (trigger.tagName !== 'INPUT') {
            trigger.classList.add('evo-submitting--' + style);
        }
        return { el: trigger, style: style };
    }

    // 送信中の見た目。送信ボタンと、同じ画面(#mainPane/モーダル)のactionButtonsを押せない見た目にする
    function setSubmitting(form, on, submitter) {
        if (on) {
            const container = form.closest('#mainPane, #evoShellModal');
            const targets = [form];
            if (container) {
                container.querySelectorAll('.actionButtons').forEach(function (el) {
                    targets.push(el);
                });
            }
            // 先に登録する。途中で例外が出ても、解除(clear)が付けた印を確実に外せる
            const state = { targets: targets, trigger: null };
            submittingForms.set(form, state);
            state.trigger = resolveTrigger(submitter, targets);
            targets.forEach(function (el) {
                el.classList.add('evo-submitting');
                el.setAttribute('aria-busy', 'true');
            });
            return;
        }
        const state = submittingForms.get(form);
        if (!state) {
            return;
        }
        state.targets.forEach(function (el) {
            el.classList.remove('evo-submitting');
            el.removeAttribute('aria-busy');
        });
        if (state.trigger) {
            state.trigger.el.classList.remove('evo-submitting-trigger', 'evo-submitting--' + state.trigger.style);
        }
        submittingForms.delete(form);
    }

    function getFormData(form, submitter) {
        const formData = new FormData(form);
        if (submitter && submitter.name && !submitter.disabled) {
            formData.append(submitter.name, submitter.value);
        }
        return formData;
    }

    // targetが'modal'の場合、断片はEvoShellモーダルへ差し込む(#mainPaneやhistoryは触らない)
    function request(url, options, push, target) {
        startWork();
        const isModalTarget = target === 'modal';
        const headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Evo-Shell': '1' }, options.headers || {});
        return fetch(url, Object.assign({}, options, { headers: headers, credentials: 'same-origin' }))
            .then(function (response) {
                if (response.headers.get('X-Evo-Login') === 'required') {
                    // セッション切れ。開こうとした画面（POST・モーダルなら今の画面）をフルリロードしてログイン画面を出す。
                    // ログイン画面はその URL のまま出て、ログイン後にその画面へ戻る
                    reloadForLogin(options.method === 'POST' || isModalTarget ? window.location.href : url);
                    return null;
                }
                if (/attachment/i.test(response.headers.get('Content-Disposition') || '')) {
                    return downloadResponse(response).then(stopWork);
                }
                // POSTがリダイレクトせずに結果を返したときは、アドレスバーと履歴を操作のURLにしない。
                // 状態を変える操作のURLはGETで開けない(405)ため、再読み込みや戻るで失敗しないよう、元の画面のURLのままにする
                const keepUrl = options.method === 'POST' && !response.redirected && !isModalTarget;
                const screenUrl = responseUrlWithRequestHash(response.url, url);
                const finalUrl = keepUrl ? currentUrl : screenUrl;
                return response.text().then(function (text) {
                    if (response.headers.get('X-Evo-Pane') === '1') {
                        if (isModalTarget) {
                            if (options.method === 'POST') {
                                modalDirty = true;
                            }
                            applyModalFragment(text, finalUrl);
                        } else {
                            applyFragment(text, finalUrl, push && !keepUrl, screenUrl);
                        }
                    } else if (options.method === 'POST' || isFullDocumentHtml(text)) {
                        // 断片で返せない応答(モジュール実行等)はドキュメントごと差し替える。
                        // document.writeはアドレスバーを更新しないため、応答先のURLで補正する
                        replaceDocument(text, finalUrl);
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
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Evo-Shell': '1' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (response.headers.get('X-Evo-Login') === 'required') {
                    reloadForLogin(window.location.href);
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

        // 未保存の変更があれば確認する。続けてよいなら true(EvoCsrf.post()も送る前に使う)
        confirmIfDirty: function () {
            return confirmIfDirty();
        },

        navigate: function (url, opts) {
            const push = !opts || opts.push !== false;
            if (!confirmIfDirty()) {
                return;
            }
            request(normalizeManagerUrl(url), { method: 'GET' }, push);
        },

        // 画面の JS から別の画面へ移る(キャンセル・ボタン・行クリック)ときの入口。
        // シェルの中なら #mainPane だけを入れ替え、シェルの外(別窓など)ならページ全体で移る。
        // opts.discard === true なら、未保存の確認を出さずに移る(キャンセル用)
        go: function (url, opts) {
            if (opts && opts.discard) {
                window.documentDirty = false;
            }
            if (document.body.classList.contains('evo-shell') && isManagerUrl(url)) {
                EvoShell.navigate(url);
            } else {
                window.location.href = url;
            }
        },

        submit: function (form, submitter) {
            if (submittingForms.has(form)) {
                return;
            }
            const method = (form.getAttribute('method') || 'GET').toUpperCase();
            const formData = getFormData(form, submitter);
            // モーダル内フォームはhistory/#mainPaneに触れず、モーダル自身を差し替える。
            // action未指定(自己送信)はページのURLではなくモーダルコンテンツのURLへ送る
            const isModalForm = !!form.closest('#evoShellModal');
            const target = isModalForm ? 'modal' : undefined;
            const push = !isModalForm;
            // 押したボタンの formaction があれば、フォームの action より優先する（ネイティブの送信と同じ）
            const action = (submitter && submitter.getAttribute && submitter.getAttribute('formaction'))
                || form.getAttribute('action')
                || (isModalForm && currentModalUrl)
                || window.location.href;

            if (method === 'GET') {
                const params = new URLSearchParams(formData);
                const url = action.split('?')[0] + '?' + params.toString();
                window.documentDirty = false;
                request(url, { method: 'GET' }, push, target);
                return;
            }

            window.documentDirty = false;
            // 応答・エラー・同期の例外のどれでも印を外す
            const clear = function () {
                setSubmitting(form, false);
            };
            try {
                setSubmitting(form, true, submitter);
                request(action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-CSRF-Token': getCsrfToken() }
                }, push, target).then(clear, clear);
            } catch (e) {
                clear();
                throw e;
            }
        },

        openModal: function (url) {
            request(url, { method: 'GET' }, false, 'modal');
        },

        // opts.reloadMain === false で、閉じた後の#mainPane再取得を行わない
        closeModal: function (opts) {
            closeModal(opts && opts.reloadMain === false ? false : undefined);
        },

        // 選択ダイアログ(親リソースの選択等)をモーダルで開く。断片側が EvoShell.pick(value) を呼ぶと、
        // モーダルが閉じてonPick(value)が実行される
        openPicker: function (url, onPick) {
            pickerCallback = typeof onPick === 'function' ? onPick : null;
            request(url, { method: 'GET' }, false, 'modal');
        },

        // closeModal()がpickerCallbackをクリアするため、先に退避してから閉じる
        pick: function (value) {
            const callback = pickerCallback;
            pickerCallback = null;
            closeModal();
            if (callback) {
                callback(value);
            }
        },

        // ファイルブラウザをモーダルで開く。断片側はグローバルフック
        // EvoFileBrowserPick(url) を呼ぶと、モーダルが閉じてonPick(url)が実行される
        openFilePicker: function (url, onPick) {
            const resolved = new URL(url, window.location.href);
            resolved.searchParams.set('modal', '1');
            EvoShell.openPicker(resolved.href, onPick);
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
        },

        // 再読み込みの受け口。target は tree / menu / all(ツリーとメニュー) / page(管理画面ごと)。
        // シェルの画面でなければ(QuickManager などのクロームレス)何もしない
        refresh: function (target) {
            if (!document.body.classList.contains('evo-shell')) {
                return;
            }
            if (target === 'tree' || target === 'all') {
                EvoShell.reloadTree();
            }
            if (target === 'menu' || target === 'all') {
                EvoShell.reloadMenu();
            }
            if (target === 'page') {
                window.location.href = 'index.php';
            }
        }
    };

    window.EvoShell = EvoShell;

    // モーダル内ファイルブラウザ(filebrowser.js)が選択確定時に呼ぶグローバルフック
    window.EvoFileBrowserPick = function (pickedUrl) {
        EvoShell.pick(pickedUrl);
    };

    // 既存コードのform.submit()直接呼び出し(submitイベント非発火)もシェル経由にする
    const nativeFormSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        const action = this.getAttribute('action') || window.location.href;
        if (
            document.body.classList.contains('evo-shell')
            && this.closest('#mainPane, #evoShellModal')
            && !this.hasAttribute('data-no-ajax')
            && !this.hasAttribute('target')
            && isManagerUrl(action)
        ) {
            EvoShell.submit(this);
            return;
        }
        nativeFormSubmit.call(this);
    };

    // ツリーと本文の境目のつまみ。ドラッグでツリーの幅（--shell-tree-width）を変え、幅をブラウザに覚えさせる。
    // ダブルクリックで既定の幅（header.inc.php の値）に戻す。つまみはツリーを読み込み直しても消えないよう body の直下に置く
    const TREE_WIDTH_KEY = 'evo.shell.treeWidth';
    const TREE_WIDTH_MIN = 160;

    function setTreeWidth(px) {
        document.documentElement.style.setProperty('--shell-tree-width', px + 'px');
    }

    // ツリーの上のボタンの並び（#treeMenu）が切れない幅。テーマでボタンの大きさが違うので、ドラッグを始めるときに測る
    function minTreeWidth() {
        const menu = document.getElementById('treeMenu');
        const pane = document.getElementById('treePane');
        if (!menu || !pane) {
            return TREE_WIDTH_MIN;
        }
        const outside = pane.getBoundingClientRect().width - menu.getBoundingClientRect().width;
        menu.style.width = 'min-content';
        const need = menu.getBoundingClientRect().width + outside;
        menu.style.width = '';
        return Math.max(TREE_WIDTH_MIN, Math.ceil(need));
    }

    function initTreeResizer() {
        try {
            const saved = parseInt(localStorage.getItem(TREE_WIDTH_KEY), 10);
            if (saved >= TREE_WIDTH_MIN) {
                setTreeWidth(saved);
            }
        } catch (e) {
            // 保存領域が使えないときは、既定の幅のまま
        }

        const handle = document.createElement('div');
        handle.id = 'evoTreeResizer';
        handle.setAttribute('role', 'separator');
        handle.setAttribute('aria-orientation', 'vertical');
        document.body.appendChild(handle);

        handle.addEventListener('pointerdown', function (e) {
            if (e.button !== 0) {
                return;
            }
            e.preventDefault();
            handle.setPointerCapture(e.pointerId);
            document.body.classList.add('is-resizing-tree');
            let width = 0;
            const min = minTreeWidth();
            const move = function (ev) {
                const max = Math.max(min, Math.round(window.innerWidth / 2));
                width = Math.min(max, Math.max(min, Math.round(ev.clientX)));
                setTreeWidth(width);
            };
            handle.addEventListener('pointermove', move);
            handle.addEventListener('lostpointercapture', function () {
                handle.removeEventListener('pointermove', move);
                document.body.classList.remove('is-resizing-tree');
                if (!width) {
                    return;
                }
                try {
                    localStorage.setItem(TREE_WIDTH_KEY, String(width));
                } catch (err) {
                    // 覚えられなくても、今の画面の幅は変わっている
                }
            }, { once: true });
        });

        handle.addEventListener('dblclick', function () {
            document.documentElement.style.removeProperty('--shell-tree-width');
            try {
                localStorage.removeItem(TREE_WIDTH_KEY);
            } catch (e) {
                // 保存領域が使えないときは、消すものも無い
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!document.body.classList.contains('evo-shell')) {
            return;
        }

        initTreeResizer();

        window.history.replaceState({ url: window.location.href }, '', window.location.href);

        // 直前に押したボタンを覚える。見えない要素(document.mutate.save.click()の合成クリック)は無視する
        document.addEventListener('click', function (e) {
            const el = e.target.closest ? e.target.closest(CLICK_TARGET_SELECTOR) : null;
            if (el && el.closest('#mainPane, #evoShellModal') && isVisible(el)) {
                lastClicked = el;
                setTimeout(function () {
                    if (lastClicked === el) {
                        lastClicked = null;
                    }
                }, 0);
            }
        }, true);

        // リンククリックの委譲。data-no-ajax / target付き / 外部URLは素通しする
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
                // href="#" はボタン用途のリンクでもブラウザの履歴を変更するため、
                // クリックハンドラからシェル遷移した後の二重取得を防ぐ
                if (href === '#') {
                    e.preventDefault();
                    return;
                }
                const url = new URL(window.location.href);
                url.hash = href;
                const pane = document.getElementById('mainPane');
                if (paneTargetFromHash(pane, url.hash)) {
                    e.preventDefault();
                    window.history.pushState({ url: url.href, shellNav: true }, '', url.href);
                    scrollPaneToHash(url.href);
                }
                return;
            }
            if (!isManagerUrl(href)) {
                return;
            }
            const url = new URL(href, window.location.href);
            if (!url.searchParams.get('a') && !(url.pathname === window.location.pathname.replace(/[^\/]*$/, '') && !url.search)) {
                return;
            }
            e.preventDefault();
            if (link.hasAttribute('data-modal')) {
                EvoShell.openModal(normalizeManagerUrl(url.href));
                return;
            }
            EvoShell.navigate(normalizeManagerUrl(url.href));
        });

        // コンテンツ領域のフォーム送信の委譲
        document.addEventListener('submit', function (e) {
            if (e.defaultPrevented) {
                return;
            }
            const form = e.target;
            if (form.tagName !== 'FORM' || !form.closest('#mainPane, #evoShellModal')) {
                return;
            }
            if (form.hasAttribute('data-no-ajax') || form.hasAttribute('target')) {
                return;
            }
            const action = (e.submitter && e.submitter.getAttribute('formaction')) || form.getAttribute('action') || window.location.href;
            if (!isManagerUrl(action)) {
                return;
            }
            e.preventDefault();
            EvoShell.submit(form, e.submitter);
        });

        // ブラウザの戻る/進む
        window.addEventListener('popstate', function (e) {
            const url = (e.state && e.state.url) ? e.state.url : window.location.href;
            if (!confirmIfDirty()) {
                // 履歴は既に移動済みのため、表示中の内容に対応するURLへ戻して整合させる
                window.history.pushState({ url: currentUrl }, '', currentUrl);
                return;
            }
            EvoShell.navigate(url, { push: false });
        });

        scrollPaneToHash(window.location.href);
    });
})();
