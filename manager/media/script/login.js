/**
 * 管理画面のログイン画面（テーマ 8 種の login.tpl）の送信処理。jQuery には依存しない
 *
 * - ログインボタン（#submitButton、Enter キーの送信も含む）で、processors/login.processor.php へ POST する
 * - 応答が "Location: …" なら、その URL へ移る。それ以外はエラーの文として jAlert（evo-dialog.js）で出し、
 *   キャプチャの画像を作り直す
 * - 「ログイン名を記憶する」（#rememberme）は、チェックしたときだけ 1 を送る
 * - 送っている間はボタンに .loading を付け、押せなくする
 */
(function () {
    'use strict';

    // 別のページのフレームの中で開かれたら、ログイン画面を画面全体で開き直す
    // 他のオリジンのフレームでは移せないことがある。そのときも送信処理は動かす
    try {
        if (window.top.frames.length !== 0) {
            window.top.location = window.self.document.location;
        }
    } catch (e) {
        // 何もしない
    }

    function value(selector) {
        const el = document.querySelector(selector);
        return el ? el.value : '';
    }

    function submit(button) {
        const remember = document.getElementById('rememberme');
        const params = new URLSearchParams({
            username: value('#username'),
            password: value('#password'),
            rememberme: remember && remember.checked ? '1' : '0',
            ajax: '1',
            captcha_code: value('input[name="captcha_code"]')
        });
        const done = function () {
            button.classList.remove('loading');
            button.disabled = false;
        };
        button.classList.add('loading');
        button.disabled = true;

        fetch('processors/login.processor.php', {
            method: 'POST',
            body: params,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                // 404・500 などのエラーページは出さず、ボタンを戻すだけにする（今までと同じ）
                return response.ok ? response.text() : null;
            })
            .then(function (response) {
                if (response === null) {
                    done();
                    return;
                }
                if (response.substr(0, 9).toLowerCase() === 'location:') {
                    window.top.location = response.substr(10);
                    return;
                }
                done();
                const captcha = document.getElementById('captcha_image');
                if (captcha) {
                    captcha.src = '../index.php?get=captcha';
                }
                jAlert(response);
            })
            .catch(done);
    }

    function setup() {
        const button = document.getElementById('submitButton');
        if (button) {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                if (!button.disabled) {
                    submit(button);
                }
            });
        }
        const username = document.getElementById('username');
        const password = document.getElementById('password');
        if (username && username.value !== '' && password) {
            password.focus();
        } else if (username) {
            username.focus();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup, { once: true });
    } else {
        setup();
    }
})();
