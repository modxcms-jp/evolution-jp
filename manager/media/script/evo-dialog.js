/**
 * 管理画面のダイアログ（jquery.alerts の置き換え）。<dialog> で作り、jQuery には依存しない
 *
 * jAlert(message, title, callback) / jConfirm(message, title, callback) / jPrompt(message, value, title, callback)
 * の名前と引数は jquery.alerts と同じ（外部のアドオンが呼んでいるため）。
 * - message は HTML として表示し、改行は <br> にする（呼び出し側で必要なエスケープをする前提。error.class.inc.php）
 * - title は文字列として表示する
 * - callback には、OK で true、キャンセルで false（jPrompt は入力値か null）を渡す。Esc は jAlert では OK、ほかはキャンセルと同じ
 * - 新しいダイアログを開くと、開いているダイアログは閉じる（コールバックは呼ばない）
 * - title が空文字のときは見出しを出さない
 * - 4 つ目の引数 options（jquery.alerts には無い）: { icon: 'warning' } でメッセージの左に警告アイコンを出す。
 *   okLabel で、そのダイアログだけ OK ボタンの文言を変える
 * ボタンの文言は EvoDialog.okLabel / EvoDialog.cancelLabel で変えられる（header.inc.php が言語に合わせる）
 */
(function () {
    'use strict';

    const STYLE = ''
        // テーマのリセット CSS（margin:0 など）で中央からずれないよう、配置も指定する
        + '.evo-dialog{position:fixed;inset:0;margin:auto;width:fit-content;height:fit-content;max-height:calc(100vh - 32px);'
        + 'border:1px solid #d5d9e0;border-radius:5px;padding:0;min-width:min(300px,calc(100vw - 32px));max-width:min(480px,calc(100vw - 32px));'
        + 'box-sizing:border-box;display:flex;flex-direction:column;'
        // 文字は管理画面（テーマ）の書体を引き継ぐ
        + 'box-shadow:0 4px 16px rgba(0,0,0,.15);color:#222;background:#fff;font-family:inherit;font-size:13px;line-height:1.6}'
        + '.evo-dialog:not([open]){display:none}'
        + '.evo-dialog::backdrop{background:rgba(0,0,0,.25)}'
        + '.evo-dialog-title{flex:none;margin:0;padding:14px 20px 0;font-size:15px;font-weight:600}'
        // 長いメッセージは、ボタンを残したままこの中でスクロールする
        + '.evo-dialog-message{padding:10px 20px 4px;min-height:0;overflow:auto;word-break:break-word}'
        + '.evo-dialog-untitled .evo-dialog-message{padding-top:18px}'
        + '.evo-dialog-body{display:flex;align-items:flex-start;gap:12px}'
        + '.evo-dialog-icon{flex:none;width:24px;height:24px;margin-top:1px}'
        + '.evo-dialog-text{flex:1;min-width:0}'
        + '.evo-dialog-input{flex:none;display:block;box-sizing:border-box;width:calc(100% - 40px);margin:6px 20px 0;padding:6px 8px;font:inherit}'
        + '.evo-dialog-buttons{flex:none;display:flex;justify-content:flex-end;gap:8px;padding:16px 20px}'
        // ボタンが 1 つ（jAlert）のときは下の真ん中。jConfirm / jPrompt は右寄せのまま
        + '.evo-dialog-alert .evo-dialog-buttons{justify-content:center}'
        // テーマの button の右の余白（margin）が残ると、真ん中からずれる
        + '.evo-dialog-buttons button{margin:0;min-width:80px;padding:6px 16px;border:1px solid #c5cad3;border-radius:2px;background:#f5f6f8;color:#222;font:inherit;cursor:pointer}'
        + '.evo-dialog-buttons button.evo-dialog-ok{border-color:#2f6fd0;background:#2f6fd0;color:#fff}'
        // 開いたときにフォーカスを当てるので、そのままだとブラウザが枠を出すことがある。
        // 枠は、ダイアログの中で Tab を押してボタンを移り始めたとき（evo-dialog-keyboard）だけ出す
        + '.evo-dialog-buttons button:focus{outline:none}'
        + '.evo-dialog-keyboard .evo-dialog-buttons button:focus-visible{outline:2px solid #2f6fd0;outline-offset:2px}';

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
        addStyle();

        const dialog = document.createElement('dialog');
        dialog.className = 'evo-dialog evo-dialog-' + type;
        if (title === '') {
            dialog.classList.add('evo-dialog-untitled');
        } else {
            const heading = document.createElement('h2');
            heading.className = 'evo-dialog-title';
            heading.textContent = title;
            heading.id = 'evo-dialog-title';
            dialog.setAttribute('aria-labelledby', heading.id);
            dialog.append(heading);
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
        if (type === 'prompt') {
            input = document.createElement('input');
            input.type = 'text';
            input.className = 'evo-dialog-input';
            input.value = value || '';
            dialog.append(input);
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
            return type === 'prompt' ? input.value : true;
        };
        const cancelResult = type === 'prompt' ? null : false;
        ok.addEventListener('click', function () {
            close(okResult());
        });
        if (cancel) {
            cancel.addEventListener('click', function () {
                close(cancelResult);
            });
        }
        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    close(okResult());
                }
            });
        }
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
        dialog.addEventListener('cancel', function (e) {
            e.preventDefault();
            close(type === 'alert' ? true : cancelResult);
        });

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
        alert: function (message, title, callback, options) {
            show('alert', message, title === undefined || title === null ? 'Alert' : title, null, callback, options);
        },
        confirm: function (message, title, callback) {
            show('confirm', message, title === undefined || title === null ? 'Confirm' : title, null, callback);
        },
        prompt: function (message, value, title, callback) {
            show('prompt', message, title === undefined || title === null ? 'Prompt' : title, value, callback);
        }
    };

    window.EvoDialog = EvoDialog;
    window.jAlert = EvoDialog.alert;
    window.jConfirm = EvoDialog.confirm;
    window.jPrompt = EvoDialog.prompt;
})();
