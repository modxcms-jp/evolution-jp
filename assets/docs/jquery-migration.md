# 管理画面の jQuery 置き換えの書き方

管理画面の jQuery を素の JS（ES6+）へ置き換えるときの書き方のメモ。#679（jQuery 廃止）とその子 Issue で使う。

## 共通部品

`manager/media/script/evo.js`（`header.inc.php` で読み込む）に次の 3 つがある。

| 部品 | 代わりにするもの | 補足 |
| --- | --- | --- |
| `Evo.ready(fn)` | `$j(function(){…})` / `$j(document).ready(…)` | DOM の準備ができていれば待たずに実行する。シェルが後から差し込んだインラインスクリプトでも動く |
| `Evo.fetch(url, options)` | `$j.ajax(…)` / `$j.get(…)` / `$j.getJSON(…)` | シェルと同じヘッダーを付け、GET・HEAD 以外では `X-CSRF-Token` を付ける。セッション切れならログイン画面へ移る |
| `Evo.post(url, data)` | `$j.post(url, data, …)` | `data` はオブジェクト・`FormData`・`URLSearchParams`・文字列。配列の値は `key[]` で送る（入れ子のオブジェクトは扱わない） |

- `DOMContentLoaded` へ単純に書き換えない。シェルの画面遷移で差し込まれたスクリプトでは、すでに発火していて実行されない。
- `fetch` は 404・500 などでも reject しない。`$j.post` の失敗時の処理にあたるものは `response.ok` を見て書く。
- `X-CSRF-Token` は `csrf.js` が XHR と `fetch` の全体にも付けている。`Evo.fetch` は読み込み順に頼らずに付ける。

```js
// 前
$j.post('index.php?a=118', { action: 'setsetting', key: k, value: v }, function (data) { … });

// 後
Evo.post('index.php?a=118', { action: 'setsetting', key: k, value: v })
    .then(function (response) { return response.text(); })
    .then(function (data) { … });
```

## よく使う呼び出しの置き換え

| jQuery | 素の JS |
| --- | --- |
| `$j('#id')` / `$j('.cls')` | `document.getElementById('id')` / `document.querySelectorAll('.cls')` |
| `.val()` / `.val(v)` | `el.value` / `el.value = v` |
| `.prop('checked')` / `.prop('disabled', true)` | `el.checked` / `el.disabled = true` |
| `.attr(n)` / `.removeAttr(n)` | `el.getAttribute(n)` / `el.removeAttribute(n)` |
| `.hide()` / `.show()` / `.toggle()` | `el.hidden = true` / `el.hidden = false` / `el.hidden = !el.hidden`（CSS の `display` で隠している要素は `el.style.display`） |
| `.fadeIn()` / `.fadeOut()` / `.slideUp()` | `el.hidden` の切り替え。動きが要るときは CSS の `transition` |
| `.addClass` / `.removeClass` / `.toggleClass` | `el.classList.add` / `remove` / `toggle` |
| `.css(n, v)` | `el.style.setProperty(n, v)` |
| `.html(s)` / `.text(s)` | `el.innerHTML = s` / `el.textContent = s`（利用者の入力を入れるときは `textContent`） |
| `.append(el)` / `.after(el)` / `.before(el)` / `.remove()` / `.empty()` | `append` / `after` / `before` / `remove` / `replaceChildren()` |
| `.parents(sel)` / `.closest(sel)` / `.parent()` | `el.closest(sel)` / `el.closest(sel)` / `el.parentElement` |
| `.siblings()` / `.next()` / `.prev()` | `Array.from(el.parentElement.children).filter(c => c !== el)` / `nextElementSibling` / `previousElementSibling` |
| `.each(fn)` | `document.querySelectorAll(sel).forEach(fn)` |
| `.click(fn)` / `.change(fn)` / `.on(ev, fn)` / `.bind` | `el.addEventListener(ev, fn)`。後から差し込まれる要素は `document.addEventListener` で受けて `e.target.closest(sel)` で絞る |
| `.click()` / `.submit()` / `.trigger('change')` | `el.click()` / `form.requestSubmit()` / `el.dispatchEvent(new Event('change', { bubbles: true }))` |
| `.focus()` | `el.focus()` |
| `.data(k)` | `el.dataset.k` |
| `.offset()` / `.outerWidth()` / `.height()` | `el.getBoundingClientRect()` / `el.offsetWidth` / `el.offsetHeight` |
| `$j.inArray(v, arr)` / `$j.each(arr, fn)` / `$j.extend` | `arr.indexOf(v)` / `arr.forEach(fn)` / `Object.assign` |

## 気をつけること

- `form.submit()` は `submit` イベントを発火しない。jQuery の `.submit()`（引数なし）は発火するため、置き換えでは `form.requestSubmit()` を使う（シェルの送信の横取りや、送信前の確認が動く）。
- 要素が無いとき、jQuery は何もしないが、素の JS では `null` への操作で例外になる。無いことがある要素は先に確かめる。
- 外部アドオンが `$j` や `jQuery` を使っていることがある。jQuery 本体を外す方針は #823 で決める。
