# 管理画面の jQuery 置き換えの書き方

管理画面の jQuery を素の JS（ES6+）へ置き換えるときの書き方のメモ。#679（jQuery 廃止）とその子 Issue で使う。

## 共通部品

`manager/media/script/evo.js`（`header.inc.php` で読み込む）に次の 3 つがある。

| 部品 | 代わりにするもの | 補足 |
| --- | --- | --- |
| `Evo.ready(fn)` | `$j(function(){…})` / `$j(document).ready(…)` | DOM の準備ができていれば待たずに実行する。シェルが後から差し込んだインラインスクリプトでも動く |
| `Evo.fetch(url, options)` | `$j.ajax(…)` / `$j.get(…)` / `$j.getJSON(…)` | `X-Requested-With` を付け、GET・HEAD 以外では `X-CSRF-Token` を付ける。セッション切れならログイン画面へ移る |
| `Evo.post(url, data)` | `$j.post(url, data, …)` | `data` はオブジェクト・`FormData`・`URLSearchParams`・文字列（`a=1&b=2` の形）。配列の値は `key[]` で送る（入れ子のオブジェクトは扱わない） |

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
| `.closest(sel)` / `.parent()` | `el.closest(sel)` / `el.parentElement` |
| `.parents(sel)` | 一致する祖先をすべて返す（自身は含まない）。`el.parentElement` から `closest(sel)` をたどって集める。1 つだけ使っているなら `el.parentElement.closest(sel)` |
| `.siblings()` / `.next()` / `.prev()` | `Array.from(el.parentElement.children).filter(c => c !== el)` / `nextElementSibling` / `previousElementSibling` |
| `.each(function (i, el) { … this … })` | `document.querySelectorAll(sel).forEach(function (el, i) { … el … })`。引数の順が逆で、`this` は要素にならないので、コールバックの中も書き換える |
| `.click(fn)` / `.change(fn)` / `.on(ev, fn)` / `.bind` | `el.addEventListener(ev, fn)`。後から差し込まれる要素は `document.addEventListener` で受けて `e.target.closest(sel)` で絞る |
| `.click()` / `.submit()` / `.trigger('change')` | `el.click()` / `form.requestSubmit()` / `el.dispatchEvent(new Event('change', { bubbles: true }))` |
| `.focus()` | `el.focus()` |
| `.data(k)` | `el.dataset.k` |
| `.offset()` | 文書基準の座標。`const r = el.getBoundingClientRect();` に `r.top + window.scrollY` / `r.left + window.scrollX`（`getBoundingClientRect()` だけではビューポート基準） |
| `.outerWidth()` / `.outerHeight()` | `el.offsetWidth` / `el.offsetHeight`（padding と border を含む） |
| `.width()` / `.height()` | 内容領域の大きさ。`getComputedStyle(el)` の `width` / `height` を数値にする（`box-sizing: border-box` のときは padding と border を引く）。`offsetWidth` / `offsetHeight` とは違う |
| `$j.inArray(v, arr)` / `$j.extend` | `arr.indexOf(v)` / `Object.assign` |
| `$j.each(arr, function (i, v) { … })` | `arr.forEach(function (v, i) { … })`。引数の順が逆になる。オブジェクトは `Object.keys(obj).forEach(function (k) { … obj[k] … })` |

## 気をつけること

- `form.submit()` は `submit` イベントを発火しない。jQuery の `.submit()`（引数なし）は発火するため、置き換えでは `form.requestSubmit()` を使う（シェルの送信の横取りや、送信前の確認が動く）。
- 要素が無いとき、jQuery は何もしないが、素の JS では `null` への操作で例外になる。無いことがある要素は先に確かめる。

## 外部のアドオンの互換（jQuery を読み込まなくなったこと）

管理画面は既定で jQuery を読み込まない。`$j` も `jQuery` も定義されない（#823）。他の作者のプラグイン、自作の ManagerManager のウィジェット、`mm_rules` に手で書いた `$j(...)` などの JS は、そのままでは動かない（`$j is not defined` のエラーになる）。

### アドオンの作者へ

このファイルの「共通部品」と「よく使う呼び出しの置き換え」の表に沿って、素の JS に書き換える。ManagerManager のルールやウィジェットは「ManagerManager のウィジェット」の決まりに沿って書く。

### どうしても jQuery が要る場合（利用者向け）

jQuery の本体（`manager/media/script/jquery/jquery.min.js`）は当面同梱している。次のどちらかで読み込める。

1. `OnManagerMainFrameHeaderHTMLBlock` のプラグインで読み込む。このイベントの出力は `header.inc.php` の `<head>` の中、共通のスクリプト（`evo.js` など）と `$j` を定義するインラインスクリプトより前に出る。jQuery が読み込まれていれば `header.inc.php` も `$j` を定義するが、プラグインの側で定義しておくと確実。

   ```php
   // プラグイン（システムイベント OnManagerMainFrameHeaderHTMLBlock にチェック）
   event()->output(
       '<script src="media/script/jquery/jquery.min.js"></script>'
       . '<script>var $j = jQuery.noConflict();</script>'
   );
   ```

2. 設定 `mgr_jquery_path` に、読み込む jQuery の URL（管理画面からの相対パス。例: `media/script/jquery/jquery.min.js`）を入れる。設定の画面には項目が無いので、DB の `system_settings` に直接入れる（`{prefix}` は実際のテーブルの接頭辞。例: `modx_`）。入れた後は、管理画面の「サイトのリフレッシュ」か `php evo cache:clear` でキャッシュを消す。

   ```sql
   REPLACE INTO `{prefix}system_settings` (setting_name, setting_value)
   VALUES ('mgr_jquery_path', 'media/script/jquery/jquery.min.js');
   ```

どちらも、シェルの画面遷移で後から差し込まれた画面のインラインスクリプトからも `$j` を使える（`<head>` は最初の読み込みのときだけ出る）。読み込みは約 87KB 増える。

## ManagerManager のウィジェット

ManagerManager（`assets/plugins/managermanager/`）が出力する JS は、#819 で jQuery を使わない形になった。ルール（`mm_rules`）に手で書く JS や自作のウィジェットも、次の決まりで書く。

### ルールの動く時機

- OnDocFormRender のルールの出力は `mm_run` という関数に包まれ、`Evo.ready(mm_run)` で動く。ルールの中で `$j(function(){…})` のように包み直さなくてよい。
- 1 つのルールで例外が出ると、MM が alert を出して残りのルールがすべて止まる。ルールが指す項目がテンプレートに無いことはよくあるので、要素が無いときは黙って何もしない形にする。

### 部品 `MM`

`assets/plugins/managermanager/mm.js` がグローバルの `MM` を置く。`x` には要素・配列・NodeList・セレクタの文字列・`null` のどれでも渡せ、要素が無ければ何もしない。

| 部品 | 中身 |
| --- | --- |
| `MM.all(x)` | 要素の配列（`querySelectorAll` の結果を配列にしたもの） |
| `MM.parents(el, sel)` | `sel` に一致する祖先をすべて（自身は含まない）。1 つだけでよければ `el.parentElement.closest(sel)` |
| `MM.hide(x)` / `MM.show(x)` | `style.display` を `none` / `''` にする（表の行も扱うので `hidden` 属性は使わない） |
| `MM.setValue(x, v)` / `MM.setChecked(x, bool)` / `MM.html(x, s)` | `value` / `checked` / `innerHTML` を入れる |
| `MM.fire(x, type)` | `bubbles: true` の素のイベントを起こす |
| `MM.addStyle(cssText)` | `head` に `<style>` を足す |

汎用のライブラリではないので、取った要素への操作は素の DOM で書く。

### 外部ファイルの読み込み（`includeJs` / `includeCss`）

- OnDocFormRender のルールの中で `includeJs($url)` / `includeCss($url)` を呼ぶと、URL を登録して空文字を返す。登録した JS は `mm_run` の前に `<script src>` で読まれるので、ルールの中で読み込んだ関数をすぐ使える。CSS は `head` に足す。1 つの画面の MM の出力の中では、同じ URL は 1 回だけ読む（シェルで開き直すと読み直す）。
- 返り値が空文字なので、JS の条件の中（`if (!window.X){ … }` など）に置いても条件は効かない。常に登録する。
- OnDocFormRender のルールの外で呼ぶと、`head` に要素を足す JS を返す（読み込みは非同期になる）。`includeJs($url, 'html')` / `includeCss($url, 'html')` の返り値は変わらない。

### change を起こすかどうか

- 管理画面の未保存の検知（`documentDirty`）は、画面を開いたとき（シェルで移ったときも）に `#mainPane` の input・textarea・select それぞれに付けた素の `change` のリスナーで動く（`header.inc.php` の `evoBindDirtyTracking`）。jQuery の `.trigger('change')` では届かない。後から足した要素には付かない
- 利用者の操作の代わりに値を変える処理（タグのクリック、画像の選択など）は `MM.fire(el, 'change')` を起こす。
- 値を入れるだけの処理（既定値・継承など、画面を開いたときに動くもの）は `change` を起こさない。起こすと、開いた直後に未保存の扱いになる。
- ウィジェットの中だけで知らせ合う変更は、独自の名前のイベントにする（例: ddmultiplefields の `ddmultiple:change`）。

### 送信の前の処理

- `$j('#mutate').submit(function(){ … return false; })` は、`form.addEventListener('submit', function (e) { … e.preventDefault(); })` にする。シェルの送信の横取りは `e.defaultPrevented` を見るので、これで送信が止まる。

### 項目名（fieldname）を含むセレクタ

- `$mm_fields[…]['fieldname']` は、チェックボックス・複数選択などの TV で `tv5\\[\\]` を含む（CSS のセレクタのエスケープ）。`"…"` の文字列に埋め込むとセレクタとして働くが、`js_literal()` に通すと `\\` が残って壊れる。
- `getElementById` に渡すときは `\` を外す（`str_replace('\\', '', $fieldname)`）。セレクタを組み立てるときは今の埋め込み方を保つか、`\` を外した値を属性の引用（`[name="…"]`）で使う。

```php
// 前
$output .= '$j("#tv' . $tv['id'] . '").hide();';

// 後
$output .= 'MM.hide(document.getElementById("tv' . $tv['id'] . '"));';
```
