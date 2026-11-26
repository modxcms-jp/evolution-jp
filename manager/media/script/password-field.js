/**
 * 管理画面のパスワードの欄（管理ユーザー・Web ユーザー・プロフィール・パスワードの変更で共通）。
 * HTML は manager/actions/permission/mutate_user/user_form_rows.php の userFormPasswordRow() が出し、
 * 決まり（文字数・文字集合）は EvoPasswordPolicy::clientConfig() から受ける。決まりと理由は assets/docs/password-policy.md
 *
 * - 欄は 1 つ。初めと自動生成の後は伏せ字。欄の右端に目のアイコン（ログイン画面と同じく aria-pressed で切り替える）、横にコピー（画面には出さない）
 * - 自動生成のボタンはオプションの枠の中。押したときだけ欄の値を書き換える
 * - 自動生成は Web Crypto（crypto.getRandomValues）だけを使う。使えない環境では自動生成のボタンを止め、ほかの方法に替えない
 * - 文字数のスライダー・文字種のチェックを変えただけでは、入力済みの値を書き換えない
 * - 入力中に、最小の文字数（8）未満はエラー、推奨の文字数（15）未満は警告を欄の下に出す。送信を止めるのはエラーのときだけ
 * - パスワードをストレージ・URL・コンソールに残さない
 */
(function () {
    'use strict';

    if (window.EvoPasswordField) {
        return;
    }

    function canGenerate() {
        return !!(window.crypto && typeof window.crypto.getRandomValues === 'function');
    }

    // 0 以上 n 未満の一様な整数。剰余の偏りが出ないよう、n の倍数に収まらない値は捨てて引き直す
    function randomIndex(n) {
        const limit = Math.floor(0x100000000 / n) * n;
        const buf = new Uint32Array(1);
        do {
            window.crypto.getRandomValues(buf);
        } while (buf[0] >= limit);
        return buf[0] % n;
    }

    /**
     * パスワードを作る。sets は使う文字種の文字列の配列（小文字を含めて渡す）。
     * すべての文字種の和から 1 文字ずつ選び、選んだ文字種のどれかが欠けていたら全体を作り直す。
     * 位置を決め打ちしないので、条件を満たす文字列の中で一様になる
     */
    function generate(length, sets) {
        if (!canGenerate()) {
            throw new Error('crypto.getRandomValues is not available');
        }
        const groups = sets.filter(Boolean).map(function (set) {
            return Array.from(set);
        });
        if (!groups.length || length < groups.length) {
            throw new Error('invalid options');
        }
        const chars = [].concat.apply([], groups);
        for (;;) {
            const picked = [];
            for (let i = 0; i < length; i++) {
                picked.push(chars[randomIndex(chars.length)]);
            }
            const coversAll = groups.every(function (group) {
                return picked.some(function (c) {
                    return group.indexOf(c) !== -1;
                });
            });
            if (coversAll) {
                return picked.join('');
            }
        }
    }

    // サーバーと同じく、入力のまま（正規化しない）コードポイントの数・UTF-8 のバイト数を数える
    function charCount(value) {
        return Array.from(value).length;
    }

    function byteCount(value) {
        return new TextEncoder().encode(value).length;
    }

    // 伏せたまま写す。Clipboard API が使えない（HTTP のサイトなど）ときは、部品の中に一時的に置いた見えない textarea から写す
    // （body の直下には足さない。manager-shell.md）
    function copyText(text, container) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve, reject) {
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.setAttribute('aria-hidden', 'true');
            area.style.cssText = 'position:fixed;top:0;left:-9999px;opacity:0;';
            container.appendChild(area);
            area.select();
            let ok = false;
            try {
                ok = document.execCommand('copy');
            } catch (e) {
                ok = false;
            }
            area.value = '';
            area.remove();
            if (ok) {
                resolve();
            } else {
                reject(new Error('copy failed'));
            }
        });
    }

    function format(template, values) {
        return String(template).replace(/%(\w+)%/g, function (m, key) {
            return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : m;
        });
    }

    /**
     * 文字数の案内。8 未満は error（送信しない）、15 未満は warning、ほかは空。長すぎる値も error。
     * 強さの評価ではない（15 文字以上でも「強い」とは言わない）
     */
    function lengthNotice(value, config, messages) {
        if (value === '') {
            return {kind: '', text: ''};
        }
        const count = charCount(value);
        if (count < config.minLength) {
            return {kind: 'error', text: format(messages.tooShort, {min: config.minLength, count: count})};
        }
        if (byteCount(value) > config.maxBytes) {
            return {kind: 'error', text: format(messages.tooLong, {max: config.maxBytes})};
        }
        if (count < config.recommendedLength) {
            return {kind: 'warning', text: format(messages.belowRecommended, {recommended: config.recommendedLength, count: count})};
        }
        return {kind: '', text: ''};
    }

    function init(root, config, messages) {
        if (!root || root.dataset.passwordReady === '1') {
            return;
        }
        const input = root.querySelector('[data-password-input]');
        const panel = root.querySelector('[data-password-panel]');
        if (!input || !panel) {
            return;
        }
        root.dataset.passwordReady = '1';

        const q = function (selector) {
            return root.querySelector(selector);
        };
        const flag = q('[data-password-flag]');
        const current = q('[data-password-current]');
        const status = q('[data-password-status]');
        const note = q('[data-password-note]');
        const fullWidth = q('[data-password-fullwidth]');
        const toggle = q('[data-password-action="toggle"]');
        const copy = q('[data-password-action="copy"]');
        const regenerate = q('[data-password-action="generate"]');
        const open = q('[data-password-action="open"]');
        const cancel = q('[data-password-action="cancel"]');
        const range = q('[data-password-length]');
        const shorter = q('[data-password-action="shorter"]');
        const longer = q('[data-password-action="longer"]');
        const lengthValue = q('[data-password-length-value]');
        const form = input.form;

        function dirty() {
            if (typeof window.documentDirty !== 'undefined') {
                window.documentDirty = true;
            }
        }

        function say(message, kind) {
            if (!status) {
                return;
            }
            status.textContent = message || '';
            status.className = 'password-setting-status' + (kind ? ' is-' + kind : '');
        }

        function mask() {
            input.type = 'password';
            if (toggle) {
                toggle.setAttribute('aria-pressed', 'false');
            }
        }

        function updateNote() {
            const notice = lengthNotice(input.value, config, messages);
            if (note) {
                note.textContent = notice.text;
                note.className = 'password-setting-note' + (notice.kind ? ' is-' + notice.kind : '');
                note.hidden = notice.text === '';
            }
            if (fullWidth) {
                fullWidth.hidden = !/[！-～　]/.test(input.value);
            }
            input.setAttribute('aria-invalid', notice.kind === 'error' ? 'true' : 'false');
            if (copy) {
                copy.disabled = input.disabled || input.value === '';
            }
            return notice;
        }

        function syncLength() {
            if (!range) {
                return;
            }
            const value = parseInt(range.value, 10);
            if (lengthValue) {
                lengthValue.textContent = format(messages.lengthValue, {count: value});
            }
            range.setAttribute('aria-valuetext', format(messages.lengthValue, {count: value}));
            if (shorter) {
                shorter.disabled = value <= parseInt(range.min, 10);
            }
            if (longer) {
                longer.disabled = value >= parseInt(range.max, 10);
            }
        }

        function step(delta) {
            const next = Math.min(parseInt(range.max, 10), Math.max(parseInt(range.min, 10), parseInt(range.value, 10) + delta));
            range.value = String(next);
            syncLength();
        }

        function sets() {
            const result = [config.lower];
            root.querySelectorAll('[data-password-class]').forEach(function (check) {
                if (check.checked && config[check.dataset.passwordClass]) {
                    result.push(config[check.dataset.passwordClass]);
                }
            });
            return result;
        }

        // markDirty が false のときは未保存の印を立てない（新規の画面を開いたときの初めの生成）
        function fill(markDirty) {
            const length = range ? parseInt(range.value, 10) : config.generateLength;
            input.value = generate(length, sets());
            mask();
            updateNote();
            if (markDirty !== false) {
                dirty();
            }
        }

        // 自動生成のオプションを初めの値（20 文字・大文字と数字）に戻す
        function resetOptions() {
            if (range) {
                range.value = range.defaultValue;
                syncLength();
            }
            root.querySelectorAll('[data-password-class]').forEach(function (check) {
                check.checked = check.defaultChecked;
            });
        }

        function setOpen(isOpen) {
            panel.hidden = !isOpen;
            if (flag) {
                flag.value = isOpen ? '1' : '0';
            }
            if (current) {
                current.hidden = isOpen;
            }
            // 閉じているときは送らない（変更しない保存で、空の値も送らない）
            input.disabled = !isOpen;
            if (!isOpen) {
                input.value = '';
                mask();
                resetOptions();
                say('');
            }
            updateNote();
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            });
        }

        if (copy) {
            copy.addEventListener('click', function () {
                if (input.value === '') {
                    return;
                }
                copyText(input.value, root).then(function () {
                    say(messages.copied, 'success');
                }, function () {
                    // 失敗しても欄を表示に切り替えない（表示は利用者が表示のボタンで選ぶ）
                    say(messages.copyFailed, 'error');
                });
            });
        }

        if (regenerate) {
            regenerate.addEventListener('click', function () {
                try {
                    fill();
                    say(messages.generated, 'success');
                } catch (e) {
                    say(messages.generateUnavailable, 'error');
                }
            });
        }

        if (range) {
            range.addEventListener('input', syncLength);
            if (shorter) {
                shorter.addEventListener('click', function () {
                    step(-1);
                });
            }
            if (longer) {
                longer.addEventListener('click', function () {
                    step(1);
                });
            }
            syncLength();
        }

        if (open) {
            // 欄にはフォーカスしない（ブラウザの入力候補の一覧が開いて欄を隠すため）。
            // 押したボタンは隠れるので、キーボードの位置はオプションの枠の自動生成のボタンへ移す
            open.addEventListener('click', function () {
                setOpen(true);
                dirty();
                if (regenerate && !regenerate.disabled) {
                    regenerate.focus();
                }
            });
        }

        if (cancel) {
            cancel.addEventListener('click', function () {
                setOpen(false);
                if (open) {
                    open.focus();
                }
            });
        }

        input.addEventListener('input', function () {
            updateNote();
            say('');
            dirty();
        });

        // 空・8 文字未満・長すぎる値は送らずに知らせる（空の欄は、送るまではエラーにしない）（サーバーでも同じ決まりで確かめる）。警告（15 文字未満）では止めない
        if (form) {
            form.addEventListener('submit', function (event) {
                if (input.disabled || panel.hidden) {
                    return;
                }
                const notice = input.value === ''
                    ? {kind: 'error', text: messages.required}
                    : updateNote();
                if (notice.kind !== 'error') {
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                // 保存のボタンは送る前に未保存の印を下ろすので、送らなかったときは立て直す
                dirty();
                say(notice.text, 'error');
                input.focus();
            }, true);
        }

        setOpen(root.dataset.passwordOpen === '1');
        const notices = [];
        if (root.dataset.passwordRestored === '1') {
            notices.push(messages.restored);
        }
        if (!canGenerate()) {
            if (regenerate) {
                regenerate.disabled = true;
            }
            notices.push(messages.generateUnavailable);
        } else if (!notices.length && root.dataset.passwordGenerate === '1') {
            fill(false);
        }
        if (notices.length) {
            say(notices.join(' '), 'warning');
        }
    }

    window.EvoPasswordField = {init: init, generate: generate, lengthNotice: lengthNotice};
})();
