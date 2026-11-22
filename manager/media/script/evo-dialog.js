/**
 * 管理画面のダイアログ（jquery.alerts の置き換え）。<dialog> で作り、jQuery には依存しない
 *
 * jAlert(message, title, callback) / jConfirm(message, title, callback) / jPrompt(message, value, title, callback)
 * の名前と引数は jquery.alerts と同じ（外部のアドオンが呼んでいるため）。
 * - message は HTML として表示し、改行は <br> にする（呼び出し側で必要なエスケープをする前提。error.class.inc.php）
 * - title は文字列として表示する
 * - callback には、OK で true、キャンセルで false（jPrompt は入力値か null）を渡す。Esc は jAlert では OK、ほかはキャンセルと同じ
 * - 新しいダイアログを開くと、開いているダイアログは閉じる（コールバックは呼ばない）
 * - 見出しの帯の右に × を出す（Esc と同じ扱い）。title が空文字のときは帯ごと出さない
 * - 4 つ目の引数 options（jquery.alerts には無い）: { icon: 'warning' } でメッセージの左に警告アイコンを出す。
 *   okLabel で、そのダイアログだけ OK ボタンの文言を変える
 * ボタンの文言は EvoDialog.okLabel / EvoDialog.cancelLabel、× の読み上げは EvoDialog.closeLabel で変えられる（header.inc.php が言語に合わせる）
 * EvoDialog.form(message, fields, title, callback, options)（jquery.alerts には無い）: 入力欄を複数並べる。
 *   fields は [{name, label, type, value}]（type は text・password など。既定は text）。
 *   callback には、OK で {name: 値} のオブジェクト、キャンセルで null を渡す。Enter はどの欄でも OK（IME の確定の Enter は除く）
 */
(function () {
    'use strict';

    const STYLE = ''
        // テーマのリセット CSS（margin:0 など）で中央からずれないよう、配置も指定する
        + '.evo-dialog{position:fixed;inset:0;margin:auto;width:fit-content;height:fit-content;max-height:calc(100vh - 32px);'
        + 'border:1px solid #d5d9e0;border-radius:5px;padding:0;overflow:hidden;min-width:min(300px,calc(100vw - 32px));max-width:min(480px,calc(100vw - 32px));'
        + 'box-sizing:border-box;display:flex;flex-direction:column;'
        // 文字は管理画面（テーマ）の書体を引き継ぐ
        + 'box-shadow:0 4px 16px rgba(0,0,0,.15);color:#222;background:#fff;font-family:inherit;font-size:13px;line-height:1.6}'
        + '.evo-dialog:not([open]){display:none}'
        + '.evo-dialog::backdrop{background:rgba(0,0,0,.25)}'
        // 見出しの帯と閉じるボタン。シェルのモーダル（shell.js）とファイルブラウザのダイアログ（overlay.js）も、このクラスで同じ見た目にする。
        // 地はテーマの主色。明るい主色でも白い文字が読めるよう、暗い色を重ねる。見出しはテーマの h1・h2 の地や枠を打ち消す
        + '.evo-titlebar{flex:none;cursor:move;user-select:none;touch-action:none;display:flex;align-items:center;justify-content:space-between;gap:8px;min-height:44px;padding:0 8px 0 16px;box-sizing:border-box;'
        + 'background-image:linear-gradient(rgba(0,0,0,.15),rgba(0,0,0,.15)),linear-gradient(var(--color-primary-start,var(--color-primary,#375665)),var(--color-primary-end,var(--color-primary,#375665)));'
        + 'color:var(--color-text-inverse,#fff)}'
        + '.evo-titlebar-title{min-width:0;margin:0;padding:8px 0;border:0;background:none;box-shadow:none;color:inherit;font-size:1.1em;font-weight:bold;overflow-wrap:anywhere;text-shadow:0 1px 2px rgba(0,0,0,.4)}'
        + '.evo-titlebar-close{flex:none;width:28px;height:28px;margin:0;padding:0;border:0;background:transparent;color:inherit;font-size:20px;line-height:26px;cursor:pointer}'
        + '.evo-titlebar-close:hover,.evo-titlebar-close:focus-visible{background:rgba(255,255,255,.2)}'
        + '.evo-titlebar-close:focus-visible{outline:2px solid currentColor;outline-offset:2px}'
        // 長いメッセージは、ボタンを残したままこの中でスクロールする
        + '.evo-dialog-message{padding:16px 20px 4px;min-height:0;overflow:auto;word-break:break-word}'
        + '.evo-dialog-body{display:flex;align-items:flex-start;gap:12px}'
        + '.evo-dialog-icon{flex:none;width:24px;height:24px;margin-top:1px}'
        + '.evo-dialog-text{flex:1;min-width:0}'
        + '.evo-dialog-input{flex:none;display:block;box-sizing:border-box;width:calc(100% - 40px);margin:6px 20px 0;padding:6px 8px;font:inherit}'
        + '.evo-dialog-label{flex:none;display:block;margin:10px 20px 0;font-weight:600}'
        + '.evo-dialog-label + .evo-dialog-input{margin-top:4px}'
        + '.evo-dialog-buttons{flex:none;display:flex;justify-content:flex-end;gap:8px;padding:16px 20px}'
        // ボタンが 1 つ（jAlert）のときは下の真ん中。jConfirm / jPrompt は右寄せのまま
        + '.evo-dialog-alert .evo-dialog-buttons{justify-content:center}'
        // テーマの button の右の余白（margin）が残ると、真ん中からずれる
        + '.evo-dialog-buttons button{margin:0;min-width:80px;padding:6px 16px;border:1px solid #c5cad3;border-radius:2px;background:#f5f6f8;color:#222;font:inherit;cursor:pointer}'
        // OK は主ボタンの地の色（--color-primary）と、その上の文字の色（--color-text-inverse）。テーマに無いときは既定値。変数の契約は assets/docs/theme-variables.md
        + '.evo-dialog-buttons button.evo-dialog-ok{border-color:var(--color-primary,#375665);background:var(--color-primary,#375665);color:var(--color-text-inverse,#fff)}'
        // 開いたときにフォーカスを当てるので、そのままだとブラウザが枠を出すことがある。
        // 枠は、ダイアログの中で Tab を押してボタンを移り始めたとき（evo-dialog-keyboard）だけ出す
        + '.evo-dialog-buttons button:focus{outline:none}'
        + '.evo-dialog-keyboard .evo-dialog-buttons button:focus-visible{outline:2px solid var(--color-primary,#375665);outline-offset:2px}';

    let current = null;

    function addStyle() {
        if (document.getElementById('evo-dialog-style')) {
            return;
        }
        const style = document.createElement('style');
        style.id = 'evo-dialog-style';
        style.textContent = STYLE;
        document.head.appendChild(style);
    }

    function button(label, className) {
        const el = document.createElement('button');
        el.type = 'button';
        el.className = className;
        el.textContent = label;
        return el;
    }

    // 警告アイコン（三角に !）。画像ファイルに頼らず、どのテーマでも同じに出す
    const WARNING_ICON = '<svg class="evo-dialog-icon" viewBox="0 0 24 24" aria-hidden="true">'
        + '<path d="M12 2.5 1.5 21h21z" fill="#f5b400" stroke="#d99a00" stroke-width="1" stroke-linejoin="round"/>'
        + '<path d="M12 9v6" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>'
        + '<circle cx="12" cy="18" r="1.3" fill="#fff"/></svg>';

    function show(type, message, title, value, callback, options) {
        options = options || {};
        if (!document.body) {
            document.addEventListener('DOMContentLoaded', function () {
                show(type, message, title, value, callback, options);
            }, { once: true });
            return;
        }
        if (current) {
            current.remove();
            current = null;
        }

        const dialog = document.createElement('dialog');
        dialog.className = 'evo-dialog evo-dialog-' + type;
        let closeButton = null;
        if (title !== '') {
            const bar = document.createElement('div');
            bar.className = 'evo-titlebar';
            const heading = document.createElement('h2');
            heading.className = 'evo-titlebar-title';
            heading.textContent = title;
            heading.id = 'evo-dialog-title';
            closeButton = button('\u00d7', 'evo-titlebar-close');
            closeButton.setAttribute('aria-label', EvoDialog.closeLabel);
            bar.append(heading, closeButton);
            dialog.setAttribute('aria-labelledby', heading.id);
            dialog.append(bar);
        }
        const body = document.createElement('div');
        body.className = 'evo-dialog-message';
        body.id = 'evo-dialog-message';
        dialog.setAttribute('aria-describedby', body.id);
        const html = String(message === undefined || message === null ? '' : message).replace(/\n/g, '<br />');
        if (options.icon === 'warning') {
            body.innerHTML = '<div class="evo-dialog-body">' + WARNING_ICON + '<div class="evo-dialog-text"></div></div>';
            body.querySelector('.evo-dialog-text').innerHTML = html;
        } else {
            body.innerHTML = html;
        }
        dialog.append(body);

        let input = null;
        const inputs = [];
        if (type === 'prompt') {
            input = document.createElement('input');
            input.type = 'text';
            input.className = 'evo-dialog-input';
            input.value = value || '';
            dialog.append(input);
            inputs.push(input);
        }
        if (type === 'form') {
            (Array.isArray(value) ? value : []).forEach(function (field, i) {
                const el = document.createElement('input');
                el.type = field.type || 'text';
                el.name = field.name || ('field' + i);
                el.id = 'evo-dialog-field-' + i;
                el.className = 'evo-dialog-input';
                el.value = field.value || '';
                // 入力値をブラウザに保存させない。パスワード欄の 'off' は主なブラウザが無視するので new-password にする（保存の提案を完全には止められない）
                el.autocomplete = el.type === 'password' ? 'new-password' : 'off';
                if (field.label) {
                    const label = document.createElement('label');
                    label.className = 'evo-dialog-label';
                    label.htmlFor = el.id;
                    label.textContent = field.label;
                    dialog.append(label);
                }
                dialog.append(el);
                inputs.push(el);
            });
            input = inputs[0] || null;
        }

        const buttons = document.createElement('div');
        buttons.className = 'evo-dialog-buttons';
        const ok = button(options.okLabel || EvoDialog.okLabel, 'evo-dialog-ok');
        buttons.append(ok);
        let cancel = null;
        if (type !== 'alert') {
            cancel = button(EvoDialog.cancelLabel, 'evo-dialog-cancel');
            buttons.append(cancel);
        }
        dialog.append(buttons);

        function close(result) {
            if (current !== dialog) {
                return;
            }
            current = null;
            dialog.close();
            dialog.remove();
            if (typeof callback === 'function') {
                callback(result);
            }
        }

        const okResult = function () {
            if (type === 'form') {
                const values = {};
                inputs.forEach(function (el) {
                    values[el.name] = el.value;
                });
                return values;
            }
            return type === 'prompt' ? input.value : true;
        };
        const cancelResult = type === 'prompt' || type === 'form' ? null : false;
        ok.addEventListener('click', function () {
            close(okResult());
        });
        if (cancel) {
            cancel.addEventListener('click', function () {
                close(cancelResult);
            });
        }
        inputs.forEach(function (el) {
            el.addEventListener('keydown', function (e) {
                // IME の変換を確定する Enter では閉じない
                if (e.key === 'Enter' && !e.isComposing) {
                    e.preventDefault();
                    close(okResult());
                }
            });
        });
        // Esc。jAlert は OK と同じ扱いにする。
        // 背後のシェルのモーダル（shell.js が document で Esc を受ける）まで閉じないよう、ここで止める
        dialog.addEventListener('keydown', function (e) {
            if (e.key === 'Tab') {
                dialog.classList.add('evo-dialog-keyboard');
            }
            if (e.key === 'Escape') {
                e.stopPropagation();
            }
        });
        // 見出しの × は Esc と同じ
        const dismiss = function () {
            close(type === 'alert' ? true : cancelResult);
        };
        dialog.addEventListener('cancel', function (e) {
            e.preventDefault();
            dismiss();
        });
        if (closeButton) {
            closeButton.addEventListener('click', dismiss);
        }

        current = dialog;
        document.body.appendChild(dialog);
        dialog.showModal();
        if (input) {
            input.focus();
            input.select();
        } else {
            ok.focus();
        }
    }

    const EvoDialog = {
        okLabel: 'OK',
        cancelLabel: 'Cancel',
        closeLabel: 'Close',
        alert: function (message, title, callback, options) {
            show('alert', message, title === undefined || title === null ? 'Alert' : title, null, callback, options);
        },
        confirm: function (message, title, callback) {
            show('confirm', message, title === undefined || title === null ? 'Confirm' : title, null, callback);
        },
        prompt: function (message, value, title, callback) {
            show('prompt', message, title === undefined || title === null ? 'Prompt' : title, value, callback);
        },
        form: function (message, fields, title, callback, options) {
            show('form', message, title === undefined || title === null ? 'Prompt' : title, fields, callback, options);
        }
    };

    // 見出しの帯はダイアログを開く前からシェルのモーダルなどが使うので、読み込んだときに足す
    addStyle();

    // 帯をつかんで、帯の親要素（ダイアログの本体）を動かす。動かせるのは、<dialog> なら画面の中、ほかは入れ物の中まで。
    // 位置は CSS の translate に持つので、同じ要素を使い回すダイアログは、開くときに style.translate を空に戻す
    document.addEventListener('pointerdown', function (e) {
        const bar = e.button === 0 && !e.target.closest('button') ? e.target.closest('.evo-titlebar') : null;
        const box = bar && bar.parentElement;
        if (!box) {
            return;
        }
        const rect = box.getBoundingClientRect();
        const area = box.matches('dialog') ? { left: 0, top: 0, right: window.innerWidth, bottom: window.innerHeight } : box.parentElement.getBoundingClientRect();
        const [x0, y0] = (box.style.translate || '0px 0px').split(' ').map(parseFloat);
        const startX = e.clientX;
        const startY = e.clientY;
        const clamp = (v, min, max) => Math.max(min, Math.min(v, max));
        const move = function (ev) {
            const dx = clamp(ev.clientX - startX, area.left - rect.left, area.right - rect.right);
            const dy = clamp(ev.clientY - startY, area.top - rect.top, area.bottom - rect.bottom);
            box.style.translate = (x0 + dx) + 'px ' + (y0 + dy) + 'px';
        };
        bar.setPointerCapture(e.pointerId);
        bar.addEventListener('pointermove', move);
        bar.addEventListener('lostpointercapture', function () {
            bar.removeEventListener('pointermove', move);
        }, { once: true });
        e.preventDefault();
    });
    window.EvoDialog = EvoDialog;
    window.jAlert = EvoDialog.alert;
    window.jConfirm = EvoDialog.confirm;
    window.jPrompt = EvoDialog.prompt;
})();
