<?php
// 入力エラーで再表示したときは、最初の送信で変えた項目を引き継ぐ。
// 再表示の値は変更後の値なので、初期値と比べるだけでは変えたことにならない
$carriedChanges = json_decode((string)(($form_v ?? [])['changed_settings'] ?? ''), true);
$carriedChanges = is_array($carriedChanges) ? array_values(array_filter($carriedChanges, 'is_string')) : [];
?>
<script type="text/javascript">
    // 画面で変えた項目名を changed_settings として送る。
    // 保存側は、変えていない項目を既定値のまま保存せず、保存済みの値を残す
    (function () {
        var form = document.userform;
        if (!form) {
            return;
        }

        function fieldValues() {
            var values = {};
            Array.prototype.forEach.call(form.elements, function (el) {
                if (!el.name || el.type === 'file' || el.type === 'submit' || el.type === 'button') {
                    return;
                }
                var name = el.name.replace(/\[\]$/, '');
                if (!values[name]) {
                    values[name] = [];
                }
                if (el.type === 'checkbox' || el.type === 'radio') {
                    if (el.checked) {
                        values[name].push(el.value);
                    }
                    return;
                }
                if (el.tagName === 'SELECT' && el.multiple) {
                    Array.prototype.forEach.call(el.options, function (option) {
                        if (option.selected) {
                            values[name].push(option.value);
                        }
                    });
                    return;
                }
                values[name].push(el.value);
            });
            return values;
        }

        var carried = JSON.parse(<?= js_literal(json_encode($carriedChanges)) ?>);
        var initial = null;

        function remember() {
            initial = fieldValues();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', remember);
        } else {
            remember();
        }

        form.addEventListener('submit', function () {
            if (!initial) {
                return;
            }
            var current = fieldValues();
            var changed = Object.keys(current).filter(function (name) {
                return carried.indexOf(name) !== -1
                    || JSON.stringify(current[name]) !== JSON.stringify(initial[name] || []);
            });
            var input = form.querySelector('input[name="changed_settings"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'changed_settings';
                form.appendChild(input);
            }
            input.value = JSON.stringify(changed);
        });
    })();
</script>
