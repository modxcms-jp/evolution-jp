/**
 * 管理画面のダイアログ（jquery.alerts の置き換え）。<dialog> で作り、jQuery には依存しない
 *
 * jAlert(message, title, callback) / jConfirm(message, title, callback) / jPrompt(message, value, title, callback)
 * の名前と引数は jquery.alerts と同じ（外部のアドオンが呼んでいるため）。
 * - message は HTML として表示し、改行は <br> にする（呼び出し側で必要なエスケープをする前提。error.class.inc.php）
 * - title は文字列として表示する
 * - callback には、OK で true、キャンセルで false（jPrompt は入力値か null）を渡す。Esc は jAlert では OK と同じ
 * - 新しいダイアログを開くと、開いているダイアログは閉じる（コールバックは呼ばない）
 * ボタンの文言は EvoDialog.okLabel / EvoDialog.cancelLabel で変えられる（header.inc.php が言語に合わせる）
 */
(function () {
    'use strict';

    const STYLE = ''
        // テーマのリセット CSS（margin:0 など）で中央からずれないよう、配置も指定する
        + '.evo-dialog{position:fixed;inset:0;margin:auto;width:fit-content;height:fit-content;max-height:calc(100vh - 32px);'
        + 'border:0;border-radius:10px;padding:0;min-width:300px;max-width:min(600px,calc(100vw - 32px));'
        + 'box-shadow:0 12px 40px rgba(0,0,0,.25);color:#222;background:#fff;font:14px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}'
        + '.evo-dialog::backdrop{background:rgba(20,24,32,.45)}'
        + '.evo-dialog-title{margin:0;padding:14px 20px 0;font-size:15px;font-weight:600}'
        + '.evo-dialog-message{padding:10px 20px 4px;max-height:60vh;overflow:auto;word-break:break-word}'
        + '.evo-dialog-input{display:block;box-sizing:border-box;width:calc(100% - 40px);margin:6px 20px 0;padding:6px 8px;font:inherit}'
        + '.evo-dialog-buttons{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px 16px}'
        + '.evo-dialog-buttons button{min-width:80px;padding:6px 16px;border:1px solid #c5cad3;border-radius:6px;background:#f5f6f8;color:#222;font:inherit;cursor:pointer}'
        + '.evo-dialog-buttons button.evo-dialog-ok{border-color:#2f6fd0;background:#2f6fd0;color:#fff}'
        + '.evo-dialog-buttons button:focus-visible{outline:2px solid #2f6fd0;outline-offset:2px}';

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

    function show(type, message, title, value, callback) {
        if (!document.body) {
            document.addEventListener('DOMContentLoaded', function () {
                show(type, message, title, value, callback);
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
        const heading = document.createElement('h2');
        heading.className = 'evo-dialog-title';
        heading.textContent = title;
        heading.id = 'evo-dialog-title';
        dialog.setAttribute('aria-labelledby', heading.id);
        const body = document.createElement('div');
        body.className = 'evo-dialog-message';
        body.innerHTML = String(message === undefined || message === null ? '' : message).replace(/\n/g, '<br />');
        dialog.append(heading, body);

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
        const ok = button(EvoDialog.okLabel, 'evo-dialog-ok');
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
        // Esc。jAlert は OK と同じ扱いにする（jquery.alerts と同じ）
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
        alert: function (message, title, callback) {
            show('alert', message, title === undefined || title === null ? 'Alert' : title, null, callback);
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
