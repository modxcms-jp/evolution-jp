/**
 * 要素（テンプレート・チャンク・スニペット・プラグイン・モジュール・テンプレート変数）の編集画面で、
 * 「ロック」のチェック（input[name=locked]）に合わせて、フォームの中の入力欄を読み取り専用にする。
 *
 * - テキスト欄・テキストエリア（textarea, input[type=text]）は readOnly にする
 * - 選択（select）は readOnly が効かないので disabled にし、readonly の class を付ける
 * - 効く範囲は渡したフォームの中だけ。操作ボタンの領域（.actionButtons。「保存後の動作」の選択など）は除く。
 *   この JS がロックで変えた欄だけを戻すので、もともと読み取り専用・無効の欄は、ロックを外しても変えない
 * - あとから作られる欄（パラメーターの表など）には効かない
 * - 無効の select は送信されないので、送信の直前に有効へ戻し、送信のあとロックの状態に戻す
 * - 開いたときにロックされていれば、ロックを外すまで保存ボタンを隠す。
 *   開いたあとにロックしたときは隠さない（ロックしたことを保存できるようにするため）
 *
 * 使い方: EvoElementLock.init(document.forms['mutate'], {locked: true, saveButton: '#Button1'});
 *   locked:     開いたときのロックの状態（差し戻しの再表示では、送信したチェックの状態）
 *   saveButton: 保存ボタンのセレクタ（無ければ省く）。フォームの中から探す。
 *               シェルでは画面の外の部品も同じ document にあり、ID が重なっても取り違えないようにするため
 */
// var への代入で定義する。シェルの画面遷移で再評価されるため（assets/docs/jquery-migration.md を参照）
var EvoElementLock = (function () {
    'use strict';

    const TEXT_FIELDS = 'textarea, input[type="text"]';
    const MARK_READONLY = 'evoLockReadonly';
    const MARK_DISABLED = 'evoLockDisabled';

    function setReadOnly(el, locked) {
        if (locked) {
            if (el.readOnly) return;
            el.readOnly = true;
            el.dataset[MARK_READONLY] = '1';
            return;
        }
        if (!el.dataset[MARK_READONLY]) return;
        el.readOnly = false;
        delete el.dataset[MARK_READONLY];
    }

    function setDisabled(el, locked) {
        if (locked) {
            if (el.disabled) return;
            el.disabled = true;
            el.classList.add('readonly');
            el.dataset[MARK_DISABLED] = '1';
            return;
        }
        if (!el.dataset[MARK_DISABLED]) return;
        el.disabled = false;
        el.classList.remove('readonly');
        delete el.dataset[MARK_DISABLED];
    }

    function targets(form, selector) {
        return Array.from(form.querySelectorAll(selector)).filter(function (el) {
            return !el.closest('.actionButtons');
        });
    }

    function applySelects(form, locked) {
        targets(form, 'select').forEach(function (el) {
            setDisabled(el, locked);
        });
    }

    function apply(form, locked) {
        targets(form, TEXT_FIELDS).forEach(function (el) {
            setReadOnly(el, locked);
        });
        applySelects(form, locked);
    }

    function init(form, options) {
        if (!form || !form.querySelector) return;
        const opts = options || {};
        const savedLocked = !!opts.locked;
        const saveButton = opts.saveButton ? form.querySelector(opts.saveButton) : null;
        const lockInput = form.querySelector('input[name="locked"]');
        const isLocked = function () {
            return lockInput ? lockInput.checked : savedLocked;
        };
        const sync = function () {
            const locked = isLocked();
            apply(form, locked);
            if (saveButton) saveButton.style.display = (savedLocked && locked) ? 'none' : '';
        };

        if (lockInput) lockInput.addEventListener('change', sync);
        form.addEventListener('submit', function () {
            applySelects(form, false);
            // 送信の値はこの submit の中で集められるので、そのあとで選択をロックの状態に戻す。
            // 保存ボタンは送信中に隠されていることがあるので触らない
            setTimeout(function () {
                applySelects(form, isLocked());
            }, 0);
        });
        sync();
    }

    return {init: init};
})();
