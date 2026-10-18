# ExecPlan: ManagerManager の jQuery を素の JS に置き換える（Issue #819）

## Purpose / Big Picture

ManagerManager（MM）のルールを使っているサイトで、リソース編集の画面の見た目と動き（項目を隠す・名前を変える・タブへ移す・既定値を入れる・タグを選ぶ など）を今のまま保ちつつ、MM が出力する JS から jQuery の呼び出しをなくす。これが終わると、管理画面の jQuery 本体を外す最後の作業（#823）に進める。

## Progress

- [x] (2026-10-18) M1: 本体と共通の部品（`mm.js`）。ルールの包みを `Evo.ready` に、`includeJs` / `includeCss` をルールの実行中は登録して `<script src>` / `<link>` で出す形に。セット A〜D を直接開く・シェルで移るの 2 通りで main と比べ、観測値が一致
- [ ] M2: 表示と文言のウィジェット（タブ・セクション・項目名・説明・タブの追加・テンプレートの選択肢）
- [ ] M3: 項目の操作と検証のウィジェット（隠す・タブへ移す・必須・同期・アクセス拒否）
- [ ] M4: 値を入れるウィジェット（mm_default・ddsetfieldvalue・mm_inherit・showimagetvs）
- [ ] M5: 同梱ライブラリを持つウィジェット（ddmaxlength・tags・googlemap）
- [ ] M6: ddmultiplefields の残りと仕上げ（全体の grep、`assets/docs/jquery-migration.md` への追記）

## Surprises & Discoveries

- 2026-10-18（M1）: main でもセット D（accessdenied）を開くと `Cannot set properties of null (setting 'innerHTML')` のページのエラーが出る（M1 の前から）。M3 で accessdenied を置き換えるときに見る。根拠: 検証環境で main とブランチの両方で同じエラー
- 2026-10-18（M1）: 登録先は `$GLOBALS['mm_includes']` ではなく `global $mm_includes` と `mm_registerInclude()` にした（MM のほかのグローバルに揃えた）

- 2026-10-18: `includeJs()` は `jQuery("head").append('<script src=…>')` を返す。jQuery 3（同梱は 3.6.0）は、append した `<script src>` を同期の XHR（`jQuery._evalUrl`、`async: false`）で読み込んでその場で実行する。そのため、tags の `includeJs(tags.js)` の直後の行で `new TagCompleter(…)` が動いている。素の JS で `<script>` 要素を足すだけでは非同期になり、この順番が崩れる（Plan of Work の「外部ファイルの読み込み」で扱う）。
- 2026-10-18: googlemap ウィジェットの見出しのコメントは「Google Maps API v2」だが、実際のコードは v3（`https://maps.googleapis.com/maps/api/js?v=3.51&key=…&callback=initMap`、`google.maps.Map`）で、API キーを渡す作り。キーがあれば動く見込みがある。#796（613c82192）で ddgmap を外したときのコミットのメッセージも「代わりに mm_widget_googlemap を使う」としている。
- 2026-10-18: `widgets/googlemap/googlemap.js` は先頭で `var $j = jQuery.noConflict();` をグローバルに書いている。#823 で jQuery を外すと、このファイルの読み込みだけで例外になるので、この Issue で必ず直す。
- 2026-10-18: `$mm_fields[…]['fieldname']` は、チェックボックス・複数選択・タグの TV で `tv5\\[\\]`（PHP の値として `\\[\\]`）を含む。これを JS の `"…"` の文字列に埋め込むと、値は `tv5\[\]` になり、CSS のセレクタのエスケープとして働く。セレクタを組み立てるときに `js_literal()` へ変えると、`\\[\\]` がそのまま残ってセレクタが壊れる。fieldname を含むセレクタは今の埋め込み方を保つか、PHP 側で `\\` を取り除いてから `js_literal()` と属性値の引用（`[name="…"]`）で組み立てる。
- 2026-10-18: jQuery 独自の擬似セレクタが使われている（`:first`・`:input`・`:has()`・`:last`）。`querySelector` では例外になるので書き換えが要る。`.parents("tr")` は一致する祖先を**すべて**返す（入れ子の表では複数の `tr` が隠れる）。`closest()` への単純な置き換えは動きが変わる。
- 2026-10-18: jQuery は要素が無いと何もしないが、素の JS では `null` への操作で例外になる。MM のルールは 1 つの `try` の中で順に動き、例外が出ると「ManagerManager: An error has occurred」の alert を出して残りのルールがすべて止まる。テンプレートに無い項目を指すルールはよくあるので、要素が無いときに黙って何もしない作りが必須。
- 2026-10-18: ddmultiplefields は jQuery の名前空間付きのイベント（`change.ddEvents`・`load.ddEvents`）を `trigger` で起こしている。jQuery の `trigger` は名前空間付きでも要素の `onchange` 属性のハンドラーを呼ぶ。素の `change` に置き換えると、#814 で `addEventListener('change')` にした未保存の検知が画面を開いた直後に反応し得る。独自の名前のイベント（例: `ddmultiple:change`）にする。

## Decision Log

- 2026-10-18 / メイン（AI）: **ルールで登録した CSS は MM の出力の位置ではなく head に足す**（M1 の自己レビューで分かった）。MM の出力は `#mutate` の中にあり、`mm_widget_accessdenied` がフォームごと消すと `<link>` も消えて見た目が外れるため。JS は実行の順番のために出力の位置の `<script src>` のまま。あわせて、前の画面の `mm_run` が残って動かないよう、定義の前に `window.mm_run = null` を出し、関数のときだけ `Evo.ready(mm_run)` を呼ぶ

- 2026-10-18 / planner（AI）: **共通の小さな部品を MM の中に 1 ファイル用意する（推奨）**。`assets/plugins/managermanager/mm.js` に、グローバルの `MM` として次だけを置く（目安 100 行以内）。
  `MM.all(sel)`（`querySelectorAll` を配列で）、`MM.parents(el, sel)`（jQuery の `.parents` と同じく一致する祖先をすべて）、`MM.hide(x)` / `MM.show(x)`（`style.display` を `none` / `''` にする）、`MM.setValue(x, v)`、`MM.setChecked(x, bool)`、`MM.html(x, s)`、`MM.fire(el, type)`（`bubbles: true` の素のイベント）、`MM.addStyle(cssText)`。`x` は要素・配列・NodeList・セレクタの文字列・`null` のどれでもよく、要素が無ければ何もしない。
  理由: (1) 20 近いウィジェットが PHP の文字列で同じ形（行を隠す・値を入れる・チェックを付ける）を繰り返し出しており、直接書くと出力が長くなり、要素が無いときの確認を毎回書くことになる。上の発見のとおり、確認の漏れ 1 つで残りのルールがすべて止まる。(2) 部品を 1 か所にすると、jQuery と違う点（`parents` の意味、`hide` の方法）を 1 か所で正しく実装できる。(3) ファイルにすると、ブラウザのキャッシュが効き、構文の確認もしやすい。
  代替案: ウィジェットごとに直接書く。部品のファイルは要らないが、上の (1)(2) の重複と漏れの危険が大きい。`Evo`（`manager/media/script/evo.js`）に足す案は、MM のためだけの部品を管理画面の全体に読み込ませることになるので採らない。
  部品は jQuery の代わりの汎用ライブラリにしない。MM の出力が実際に使う形だけを持たせ、`MM.all` で取った要素へは素の DOM の操作を書く。
- 2026-10-18 / planner（AI）: **ルールの包みは `$j(function(){…})` から `Evo.ready(function(){…})` に変える**。`Evo.ready` は DOM の準備ができていれば `setTimeout(fn, 0)` で実行し、jQuery 3 の ready と同じく、呼び出したスクリプトの残りと、シェルが続けて同期で実行するインラインスクリプト（`tpSettings = new WebFXTabPane(…)`）の後に動く。`mm_createTab` が `tpSettings` を使えるのはこの順番による。
- 2026-10-18 / planner（AI）: **外部ファイルの読み込みは「ルールを関数に包み、読み込みの `<script src>` を後ろに並べ、最後に実行する」形にする（推奨）**。OnDocFormRender の出力を次の 3 つに分ける。
  1 つ目の `<script>`: グローバル（`mm_lastTab` など）と `var mm_run = function(){ try { …ルール… } catch … finally … };`（定義だけ）。
  ルールの実行中に `includeJs()` / `includeCss()` で登録された URL の `<script src>` / `<link>`（同じ URL は 1 回）。
  2 つ目の `<script>`: `Evo.ready(mm_run);`。
  シェルの `executeScripts()`（`manager/media/script/shell.js`）は `<script src>` の `onload` を待って次へ進み、ページ全体の読み込みでも `<script src>` はパーサーを止めるので、`mm_run` は必ず読み込みの後に動く。`includeJs($url)`（既定の `'js'`）は URL を登録して空文字を返し、`'html'` の動きは変えない。
  代替案 A: `includeJs` が `<script>` 要素を足す JS を返す。非同期になり、tags などの「直後の行で使う」書き方が壊れる。代替案 B: `$e->_output` を退避して組み立て直し、`<script src>` を 1 つ目の `<script>` の前に出す。同じ結果になるが、イベントの内部のプロパティに触れるので採らない。
- 2026-10-18 / planner（AI）: **`includeJs` / `includeCss` の `'js'` の動きを変えるのは「要相談」**。利用者の自作ウィジェットが呼んでいる公開の関数で、返り値が JS のコードから空文字に変わる。OnDocFormRender のルールの中で呼ぶ限りは今より確実に読み込まれる（同期の XHR の非推奨の警告も消える）が、OnDocFormRender 以外のイベントで呼ぶ自作ウィジェットでは読み込まれなくなる。planner の推奨は「OnDocFormRender のルールの実行中だけ登録し、それ以外では素の JS で `<script>` / `<link>` を `head` に足すコードを返す」。動きが変わる点として PR に書き、リリース記事の候補にする。
- 2026-10-18 / planner（AI）: **`window.MM` を自作ウィジェットの作者に案内するかは #823 で決める**。この Issue では名前を `MM` に決め（`manager/`・`assets/` に同じ名前のグローバルが無いことを grep で確かめた）、`mm.js` の先頭のコメントに「MM の出力用。自作のウィジェットからも使える」とだけ書く。案内の文書は #823 の「アドオンの作者向けの案内」と一緒に作る。
- 2026-10-18 / planner（AI）: **`$j` を使う自作ウィジェットと、mm_rules の中で手書きした JS（`$modx->event->output('$j(…)')` など）は、この Issue では動き続ける**。jQuery 本体と `var $j = jQuery.noConflict()`（`manager/actions/header.inc.php`）は #823 まで残る。ルールの出力は今と同じ 1 つの関数の中で、同じ順に並ぶ。違いは包みが `Evo.ready` になることだけで、今の包み `$j(function(){` は引数を受けていないので、ルールの中で `$` などの別名に頼っていたコードは無い。jQuery を外したあとの扱い（互換の案 1・案 2）は #823 で決める。
- 2026-10-18 / planner（AI）: **googlemap は残して素の JS に置き換える（推奨。要相談ではない）**。理由: コードは v3 の API とキーで動く作りで、#796 で ddgmap の代わりとして案内している。置き換えは `$j` 12 か所と小さい。見出しの「v2」のコメントは直す。
  **外す場合は互換を壊すので要相談**。外すなら #796 と同じ扱いにする: `mm.inc.php` の `$removed_widgets` に `googlemap` を足し（アップグレードで残る旧ファイルを読み込まない）、`utilities.inc.php` の「Removed widgets」に何もしない `mm_widget_googlemap($fields, $googleApiKey = '', $default = '', $roles = '', $templates = '')` を置いて、ルールに残っていても Fatal error にしない。ウィジェットのディレクトリは消し、リリース記事の候補にする。
- 2026-10-18 / planner（AI）: **ddmaxlength の同梱ライブラリは、jQuery のプラグインの形をやめて素の JS の小さなファイルに書き直す**。`widgets/ddmaxlength/jquery.ddmaxlength-1.0.min.js` を消し、`widgets/ddmaxlength/ddmaxlength.js` を作る。アップグレードでは旧ファイルが残るが、読み込まれないので害はない（旧ファイルを消す仕組みは無い）。tags の `TagCompleter(tagEntryField, tagIndicatorList, delimiter)` は、自作のルールから直接呼ばれている可能性があるので、名前と引数を変えない。
- 2026-10-18 / planner（AI）: **マイルストーンは 6 つ、それぞれ 1 つの PR にする**。M1 の後は、jQuery が残ったままでも、置き換えたウィジェットと置き換えていないウィジェットが同じページで一緒に動く（包みが `Evo.ready` になっても `$j` はまだ使える）ので、どの順でマージしても画面は壊れない。M1 を最初にするのは、部品（`MM`）と読み込みの仕組みをほかのマイルストーンが使うため。中間の PR は `Refs #819`、最後の M6 だけ `Closes #819` にする。
  2 つ目以降のブランチは、前の PR のマージ後に main から `gh issue develop 819 --name refactor/819-mm-vanilla-m<番号> --base main` で作る（既存のブランチは後から Issue に紐づけられない）。
- 2026-10-18 / planner（AI）: 次の既存の問題は範囲を広げず、直さない。showimagetvs の `setInterval` はシェルの画面遷移のたびに増える（前の画面の要素は見つからないので害は小さい）。ddmaxlength の alert の文言がロシア語のまま。mm_requireFields の alert の文言が英語のまま。

## Outcomes & Retrospective

## Context and Orientation

### 用語

- **ManagerManager（MM）**: リソース編集の画面を、テンプレートやロールごとに作り替えるプラグイン。本体は `assets/plugins/managermanager/`。
- **ルール（mm_rules）**: `mm_hideFields('longtitle');` のような PHP の関数呼び出しを並べたもの。どこから読むかは `mm.inc.php` の `make_changes()` で決まる（下の「ルールの読み込み元」）。
- **ウィジェット**: ルールで呼ぶ関数の 1 つ 1 つ。`widgets/<名前>/<名前>.php` に 1 つずつある。名前の先頭が `.`・`_`・`!` のディレクトリと、`$removed_widgets`（今は `ddgmap`）は読み込まない。
- **シェル**: 管理画面の外枠（`manager/media/script/shell.js`）。画面の切り替えで、取得した HTML の断片を `#mainPane` に差し込み、中の `<script>` を作り直して順に実行する。
- **未保存の検知**: リソース編集の画面で入力が変わると `documentDirty = true` にする仕組み。#814 で `addEventListener('change')` になったので、jQuery の `.trigger('change')` では届かない。素の `dispatchEvent(new Event('change', { bubbles: true }))` が要る。

### MM の作り

1. 管理画面のリソース編集（`manager/actions/document/mutate_content.dynamic.php`）が OnDocFormPrerender と OnDocFormRender を呼ぶ。出力は `manager/actions/document/mutate_content/_template.tpl` の `[+OnDocFormPrerender+]`（フォームの先頭）と `[+OnDocFormRender+]`（タブの本体の直後、`tpSettings = new WebFXTabPane(…)` の `<script>` の前）に入る。
2. プラグインのコード（`mm.install_base.tpl` → `mm.inc.php` の `MANAGERMANAGER::run()`）がウィジェットを読み込み、`$mm_fields`（項目名 → `fieldtype`・`fieldname`・`tv` など）を組み立て、イベントごとに `libs/` のファイルを読む。
3. OnDocFormRender（`libs/OnDocFormRender.inc.php`）は、`<script>` と `$j(function(){ try {` を出し、`make_changes()` でルールを実行し、後片付け（空の TV 欄を隠すなど）と `catch`（alert）・`finally`（`#loadingmask` を隠す）で閉じる。
4. 各ウィジェットは、`event()->name === 'OnDocFormRender'` と `useThisRule($roles, $templates)` を確かめ、`$j(…)` / `jQuery(…)` の JS を文字列で組み立てて `event()->output()` で出す。ルールを呼んだ順にそのまま並ぶ。

本体のファイル:

- `mm.inc.php`: 読み込みと `$mm_fields`、`make_changes()`・`rule_exists()`。jQuery の呼び出しは無い。
- `libs/OnDocFormRender.inc.php`（9 か所）: 上の包みと後片付け。後片付けは `$j("div#tabGeneral table").prop("width","100%")`、`$j("div.tmplvars :input")` が無ければ `div.tmplvars` とその前の `div` を隠す、`#field_template optgroup` で見えている `option` が無い組を隠す、`$j("#loadingmask").hide()`。
- `libs/OnDocFormPrerender.inc.php`（1 か所）: `tvs_below_content` を `1` にし、`<div id="loadingmask">` と、その大きさを `$j("body").height()` に合わせる即時実行の `<script>` を出す。
- `libs/OnPluginFormRender.inc.php`（0 か所）: プラグインの編集画面で `mm_createTab()` を呼ぶ。ready で包まず即時に実行する。
- `utilities.inc.php`（2 か所）: `useThisRule()`・`makeArray()`・`jsSafe()`・`tplUseTvs()`・`includeJs()`（`jQuery("head").append(…)` を返す）・`includeCss()`（`$j("head").append(…)` を返す）・削除済みウィジェットの空の関数。
- `mm.install_base.tpl`（3 か所、コメントだけ）: 「ManagerManager requires jQuery 1.7+」など。

### jQuery を使うファイルと数

`grep -rcE '\$j|jQuery' assets/plugins/managermanager` で数えた行数（0 のファイルは省く）。合計 28 ファイル・254 行。

    libs/OnDocFormRender.inc.php            9
    libs/OnDocFormPrerender.inc.php         1
    utilities.inc.php                       2
    mm.install_base.tpl                     3（コメント）
    widgets/!template/!template.php         1（コメント。読み込まれない見本）
    widgets/accessdenied/accessdenied.php   3
    widgets/ddmaxlength/ddmaxlength.php     5
    widgets/ddmaxlength/jquery.ddmaxlength-1.0.min.js  3
    widgets/ddmultiplefields/ddmultiplefields.php     46
    widgets/ddsetfieldvalue/ddsetfieldvalue.php       32
    widgets/googlemap/googlemap.js         12
    widgets/mm_changefieldhelp/mm_changefieldhelp.php  2
    widgets/mm_createtab/mm_createtab.php   1
    widgets/mm_default/mm_default.php      37
    widgets/mm_hidefields/mm_hidefields.php 12
    widgets/mm_hidesections/mm_hidesections.php  6
    widgets/mm_hidetabs/mm_hidetabs.php     8
    widgets/mm_hidetemplates/mm_hidetemplates.php  2
    widgets/mm_inherit/mm_inherit.php       5
    widgets/mm_movefieldstotab/mm_movefieldstotab.php  13
    widgets/mm_renamefield/mm_renamefield.php  8
    widgets/mm_renamesection/mm_renamesection.php  3
    widgets/mm_renametab/mm_renametab.php   1
    widgets/mm_requirefields/mm_requirefields.php  9
    widgets/mm_synch_fields/mm_synch_fields.php  4
    widgets/showimagetvs/showimagetvs.php   9
    widgets/tags/tags.js                   16
    widgets/tags/tags.php                   1

jQuery を使わないもの: `widgets/colors`（#795 で `<input type="color">` に済み）、`widgets/ddautofolders`（保存時の PHP だけ）、`libs/OnPluginFormRender.inc.php`、`libs/OnBeforeDocFormSave.inc.php`（空）。

### 同梱ライブラリの使われ方

- `widgets/ddmaxlength/jquery.ddmaxlength-1.0.min.js`: jQuery のプラグイン `$.fn.ddMaxLength({max, containerSelector, warningClass})`。`ddmaxlength.php` が `includeJs` で読み、`#tv<ID>` の親に `<div class="ddMaxLengthCount"><span></span></div>` を足して残りの文字数を表示し、`keyup` で数え直す。残りが `max` の 7% 未満で `errorClass`、14% 未満で `warningClass` を付ける。保存時（`#mutate` の submit）に残りが負の欄があれば alert を出して送信を止める。
- `widgets/tags/tags.js`: グローバルの関数 `TagCompleter(tagEntryField, tagIndicatorList, delimiter)`。`tags.php` が `includeJs` で読み、入力欄の後ろにタグの一覧 `<ul class="mmTagList" id="<fieldname>_tagList">` を足してから、`var <fieldname>_tags = new TagCompleter(…)` を出す。一覧の `li` のクリックでタグを足し外しし、入力欄に素の `change` を起こす（#814 で修正済み）。入力欄の `change` で選択中の印（`tagSelected`）を付け直す。
- `widgets/googlemap/googlemap.js`: グローバルの `mm_gmap`（`init`・`draw`・`addMarker`・`geoSearch`）。`googlemap.php` が `includeJs` で読み、Google Maps の API を `callback=initMap` で非同期に読み、`initMap` で TV ごとに `mm_gmap.init(fieldname, default)` を呼ぶ。地図の容器・住所の検索欄を TV の後ろに足し、ダブルクリック・ドラッグで TV の値を「緯度,経度」にする。

### 管理画面の共通部品とすでに素の JS になった部分

- `manager/media/script/evo.js`（#813）: `Evo.ready(fn)`・`Evo.fetch(url, options)`・`Evo.post(url, data)`。`manager/actions/header.inc.php` で、jQuery の後・`shell.js` の前に読む。書き方の一覧は `assets/docs/jquery-migration.md`。
- リソース編集の画面（#814）: 未保存の検知は `addEventListener('change')`。保存ボタンは `form.requestSubmit()`（`manager/media/style/common/jscripts.tpl`）で、`#mutate` に `novalidate` を付けてある。シェルの `submit` の横取り（`shell.js` の `document.addEventListener('submit', …)`）は、先に `e.defaultPrevented` を見るので、フォームの `submit` のリスナーで `preventDefault()` すれば送信が止まる。
- 並べ替えは `manager/media/script/dragdrop-sort.js`（#784、`window.MODXSortable`、完了で `modxsortable:end` の CustomEvent）。日付は自作のカレンダー（#788、`DatePicker` のクラス）。ツールチップは自作（#786）。

### 出力の実行の時機と順番

リソース編集の画面では、次の順に `<script>` が並ぶ。

1. `[+JScripts+]`（`jscripts.tpl`）。
2. OnDocFormPrerender の出力: `#loadingmask` と、その大きさを合わせる `<script>`（ready の外、その場で実行）。
3. タブの本体（`[+content+]`）。
4. OnDocFormRender の出力。MM の分は、プラグインの `$e->output()` の分が先、プラグインの中の `echo` の分が後（`DocumentParser::invokeEvent()` が `_output` の後に `evalPlugin()` の返り値を足す）。
   - MM の `<script>`: グローバル `mm_lastTab = "tabGeneral"`・`mm_sync_field_count = 0`・`synch_field = []` を置き、`$j(function(){ try { … } … })` を登録する。ready の中には、ルールを呼んだ順に各ウィジェットの出力が並び、その後に `make_changes()` が返すコメント、後片付け、`catch` の alert、`finally` の `#loadingmask` を隠す処理が続く。
   - `includeJs` の位置では、jQuery が同期の XHR でファイルを読んで実行する（上の発見）。
   - `mm_default('template', …)` で今のテンプレートと違う値を指定したときだけ、別の `<script>jQuery(function(){ documentDirty=false; … $('#mutate').submit(); })</script>` を `echo` で出す（MM の `<script>` の後）。
5. ほかのプラグインの OnDocFormRender の出力。
6. `<script>tpSettings = new WebFXTabPane(…)</script>`（ready の外、その場で実行）。
7. OnRichTextEditorInit の出力（TinyMCE など）。

ready の中の処理がいつ動くか:

- ページ全体の読み込み（`index.php?a=27&id=…` を直接開く）: `DOMContentLoaded` の後。上の 1〜7 がすべて実行された後。
- シェルの画面遷移（断片の差し込み）: jQuery の ready はすでに済んでいるので、`$j(fn)` は `setTimeout(fn)` で後回しになる。シェルはインラインスクリプトを同期で続けて実行し、`<script src>` に当たるとその読み込みを待つので、ready の中の処理は「MM の `<script>` の後で、次の `<script src>` より前のインラインスクリプトをすべて実行した後」に動く。6 の `tpSettings` は必ず先に作られる（`mm_createTab` の `tpSettings.addTabPage` が動くのはこのため）。
- ready の中で `$j(function(){…})` をもう一度呼んでいるのは ddmultiplefields だけ（`SetUrl` の差し替えと `#mutate` の submit）。これは外側の処理（後片付けと `finally` まで）がすべて終わった後に動く。

ready の中で動くウィジェット（ルールの呼び出し順に並ぶ）: accessdenied・ddmaxlength・ddmultiplefields・ddsetfieldvalue・googlemap（API の読み込みの後は `initMap` から非同期）・mm_changefieldhelp・mm_createtab・mm_default・mm_hidefields・mm_hidesections・mm_hidetabs・mm_hidetemplates・mm_inherit・mm_movefieldstotab・mm_renamefield・mm_renamesection・mm_renametab・mm_requirefields・mm_synch_fields・showimagetvs（`setInterval(checkImageTVupdates, 250)` も ready の中で登録）・tags。
ready の外で動くもの: OnDocFormPrerender の `#loadingmask`、プラグインの編集画面での `mm_createTab`（OnPluginFormRender、即時）、`mm_default('template')` の自動送信（別の ready）。

`Evo.ready` も「DOM の準備ができていれば `setTimeout(fn, 0)`」なので、包みを置き換えても上の順番は変わらない。

### ルールの読み込み元

`mm.inc.php` の `make_changes($config_chunk)`:

1. `$config_chunk` が `@FILE` / `@INCLUDE` で始まれば、そのファイルを読む。
2. `$config_chunk` が空でなく、その名前のチャンクの中身が空でなければ、その中身を `eval()` する。
3. それ以外は、`MODX_BASE_PATH . $config_chunk` のファイル、`assets/plugins/managermanager/mm_rules.inc.php` の順に、あって中身が空でない最初のものを `include` する。

プラグインの設定 `&config_chunk` の既定は `mm_rules`（`mm.install_base.tpl`）。インストールでは同じ名前のチャンク `mm_rules`（`assets/chunks/mm_rules.install_base.tpl`、中身は `mm_widget_showimagetvs();` と `track_visitors` が `0` のときの `mm_hideFields('log');`）が入る。したがって、標準のインストールでは**チャンク `mm_rules` が使われ、`mm_rules.inc.php` は読まれない**。検証ではチャンク `mm_rules` の中身を書き換える（PHP の開始タグは付けない）。

## Plan of Work

### 共通の方針

- 出力する JS は ES6 まで（`const`・`let`・アロー関数・テンプレート文字列は使ってよい）。管理画面の他の素の JS に揃える。
- 要素を取るときは `MM.all()` などの配列を返す部品を使い、無ければ何もしない形にする。1 つしか無いはずの要素（`#mutate` など）も、`if (el)` で確かめる。
- jQuery の `.hide()` は `style.display = 'none'`、`.show()` は `style.display = ''` にする（表の行などは CSS の `display` で出し分けているため `hidden` 属性にしない）。`MM.hide` / `MM.show` で揃える。
- `.parents(sel)` は `MM.parents(el, sel)`（一致する祖先すべて）、`.parents(sel + ":first")` / `.closest(sel)` は `el.parentElement.closest(sel)` / `el.closest(sel)`、`.parent(sel)` は「親が `sel` に一致するときだけ親」に置き換える。jQuery の擬似セレクタ（`:first`・`:last`・`:input`・`:has()`）は使わない。
- 値を入れるだけの処理（`mm_default`・`ddsetfieldvalue`・`mm_inherit`）は、今と同じく `change` を起こさない（起こすと画面を開いた直後に未保存の扱いになる）。利用者の操作の代わりに値を変える処理（タグのクリック、画像の選択）は、素の `change` を起こす（`MM.fire(el, 'change')`）。
- jQuery の `.submit(function(){ … return false; })` は、`form.addEventListener('submit', e => { … e.preventDefault(); })` にする。`mm_default('template')` の `$('#mutate').submit()` は `form.requestSubmit()` にする（`form.submit()` は submit イベントを起こさず、シェルの横取りを通らない）。
- 文字列の埋め込みは、`assets/docs/jquery-migration.md` と AGENTS.md の規則（`<script>` 内の値は `js_literal()`）に従う。今 `jsSafe()` で HTML エスケープしてから `.html()` に入れている箇所（`mm_renamefield` など）は、同じ値を `innerHTML` に入れる形にして、表示を変えない。fieldname を含むセレクタは発見のとおり扱う。

### M1: 本体と共通の部品

対象: `assets/plugins/managermanager/mm.js`（新規）、`libs/OnDocFormRender.inc.php`、`libs/OnDocFormPrerender.inc.php`、`utilities.inc.php`、`mm.install_base.tpl`（コメント）、`widgets/!template/!template.php`（コメントと見本）。

- `mm.js` に Decision Log の部品を書く。読み込みは OnDocFormRender の 1 つ目の `<script>` の前に `<script src="…/mm.js?<filemtime>">`（`MODX_BASE_URL` から組み立てる）。
- `OnDocFormRender.inc.php` を Decision Log の 3 つの部分（定義の `<script>`・登録された `<script src>` / `<link>`・`Evo.ready(mm_run)`）に組み直す。後片付けを素の JS にする（`div.tmplvars` の中に `input, select, textarea, button` が 1 つも無ければ `div.tmplvars` と、その直前の兄弟が `div` ならそれも隠す。`#field_template optgroup` は、中の `option` で `getComputedStyle(o).display !== 'none'` のものが無ければ隠す）。`finally` の `#loadingmask` は `MM.hide`。
- `OnDocFormPrerender.inc.php` の即時の `<script>` は、`#loadingmask` の `style` に幅 100%・高さ `document.body.offsetHeight + 'px'`・`position: absolute`・`z-index: 1000`・白の背景を入れる。ここでは `MM` はまだ読まれていないので素の DOM で書く。
- `utilities.inc.php` の `includeJs()` / `includeCss()` を、Decision Log（要相談の項）で決めた形にする。登録先はグローバルの配列（例: `$GLOBALS['mm_includes'] = ['js' => [], 'css' => []]`）で、`OnDocFormRender.inc.php` が `make_changes()` の前に空にし、後で読む。
- `mm.install_base.tpl` と `!template.php` の「jQuery が要る」というコメントを、今の作りに合わせて直す。`!template.php` の見本の JS も `MM` を使う形にする。

### M2: 表示と文言のウィジェット

対象: `widgets/mm_hidetabs/mm_hidetabs.php`、`widgets/mm_hidesections/mm_hidesections.php`、`widgets/mm_renametab/mm_renametab.php`、`widgets/mm_renamesection/mm_renamesection.php`、`widgets/mm_renamefield/mm_renamefield.php`、`widgets/mm_changefieldhelp/mm_changefieldhelp.php`、`widgets/mm_createtab/mm_createtab.php`、`widgets/mm_hidetemplates/mm_hidetemplates.php`（8 ファイル・31 行）。

- `mm_hidetabs` / `mm_hidesections`: `MM.hide('#tabSettingsHeader')` などの形にする。
- `mm_renametab`: 見出しの中の最初の子の `span` があればその中身を、無ければ見出しの中身を、新しい名前に置き換える（今の `empty().prepend()`）。
- `mm_renamesection` / `mm_renamefield`: `.siblings(sel)` は親の子のうち自分以外で `sel` に一致するもの、`.prev(sel)` は直前の兄弟が一致するときだけ、`.parents("td:first").prev("td").children("span.mutate-field-title")` は「最も近い祖先の `td` の直前の兄弟が `td` なら、その子の `span.mutate-field-title`」。`.html(s)` は `innerHTML = s`。
- `mm_changefieldhelp`: TV は上と同じたどり方で `span.comment` の `innerHTML`。項目は兄弟の `img.tooltip` に `id`・`title`・`alt`・`data-powertip` を入れる（`.prop` は DOM のプロパティ、`.attr` は属性。今の値の渡し方を変えない）。
- `mm_createtab`: `document.getElementById(mm_lastTab)` の後ろに `insertAdjacentHTML('afterend', html)`。OnPluginFormRender でも同じ出力が即時に動くので、`MM` に頼らず素の DOM で書く。
- `mm_hidetemplates`: `#field_template` の値が対象と違えば、その `option[value="…"]` を `remove()`。

### M3: 項目の操作と検証のウィジェット

対象: `widgets/mm_hidefields/mm_hidefields.php`、`widgets/mm_movefieldstotab/mm_movefieldstotab.php`、`widgets/mm_requirefields/mm_requirefields.php`、`widgets/mm_synch_fields/mm_synch_fields.php`、`widgets/accessdenied/accessdenied.php`（5 ファイル・41 行）。

- `mm_hidefields`: 既定の形 `.parents("tr").hide().next("tr").find("td[colspan=2]").parent("tr").hide()` は、「祖先の `tr` をすべて隠し、それぞれの次の兄弟の `tr` の中に `td[colspan="2"]` があれば、その `td` の親の `tr` を隠す」。`MM.parents` を使い、意味を変えない。例外の項目（`hidemenu`・`menuindex`・`which_editor`・`content`・`pub_date`・`unpub_date`・`weblink`）も 1 つずつ同じように置き換える。
- `mm_movefieldstotab`: `$j('#tab…').length > 0` の確認、`select[id$=_prefix]` の祖先の最初の `tr` に `urltv` を付ける処理、行の移動（`appendTo("#tab…>table:first")` は「`#tab…` の直下の最初の `table`」、`:scope > table` で取る）、区切りの行（`ruleHtml`）を `insertAdjacentHTML('afterend', …)` で足す処理、`td[width]` の `width` を空にする処理を置き換える。`parents("tr:not(.urltv)")` は一致する祖先すべてで、`appendTo` は最後の 1 つ以外を移動ではなく複製するので、今の結果（どの行がどこに入るか）を Playwright で main と比べて確かめる。
- `mm_requirefields`: スタイルは `MM.addStyle()`、必須の印の追加、`#mutate` の `submit` のリスナー（空の欄があれば alert を出して `preventDefault()`。`focus` で `mmRequired` を外す）。`pub_date` / `unpub_date` の `this.type = "text"` はそのまま。
- `mm_synch_fields`: `synch_field[mm_sync_field_count]` に要素の配列を入れ、各要素の `keyup` で他の要素の `value` を揃える。
- `accessdenied`: `input, div, form[name=mutate]` をすべて `remove()`、`body` の先頭に `#aback` を足し、高さを `document.body.offsetHeight` に合わせる。

### M4: 値を入れるウィジェット

対象: `widgets/mm_default/mm_default.php`、`widgets/ddsetfieldvalue/ddsetfieldvalue.php`、`widgets/mm_inherit/mm_inherit.php`、`widgets/showimagetvs/showimagetvs.php`（4 ファイル・83 行）。

- `mm_default` / `ddsetfieldvalue` / `mm_inherit`: `MM.setValue`・`MM.setChecked` に揃える。`.removeAttr("checked")` は `checked = false`（今は属性を消しているが、表示上の結果は同じ）。`is_richtext` の `#which_editor:first` は `document.getElementById('which_editor')`、`changeRTE()` はそのまま呼ぶ。`option` 型の TV の `.val([v])`（ラジオ）は、`name` が一致するラジオのうち `value` が一致するものに `checked = true`。
- `mm_default('template')` の自動送信は、`Evo.ready` の中で `documentDirty = false`、`#mutate` の `a` を `4`・`newtemplate` を値にして `form.requestSubmit()`。
- `showimagetvs`: `#tv<ID>` に `imageField` を付け、プレビューを作り直す関数を `change` のリスナーと初回の呼び出しに使う（今の `bind("change load")…trigger("load")`）。前回の値は `el.dataset.lastvalue`。`checkImageTVupdates` は `MM.all('.imageField')` で回し、値が変わっていれば `MM.fire(el, 'change')`。プレビューの画像のクリックで `BrowseServer("tv<ID>")`。

### M5: 同梱ライブラリを持つウィジェット

対象: `widgets/ddmaxlength/ddmaxlength.php`、`widgets/ddmaxlength/jquery.ddmaxlength-1.0.min.js`（削除）、`widgets/ddmaxlength/ddmaxlength.js`（新規）、`widgets/tags/tags.php`、`widgets/tags/tags.js`、`widgets/googlemap/googlemap.php`、`widgets/googlemap/googlemap.js`（7 ファイル）。

- ddmaxlength: `ddmaxlength.js` にグローバルの関数 1 つ（例: `ddMaxLength(field, options)`）を置き、上の「同梱ライブラリの使われ方」の動きを素の JS で書く。PHP 側は `includeJs` の URL を新しいファイルにし、`#mutate` の submit のリスナーを素の JS にする。
- tags: `TagCompleter` の名前・引数・`typeof` による二重定義の防止を保ち、中を素の JS にする（`jQuery.inArray` は `indexOf`、`jQuery.each` で配列を回しながら `splice` している箇所は `filter` にする）。`tags.php` の `jQuery('#…').after(html)` は `insertAdjacentHTML('afterend', …)`。fieldname に `\[\]` を含む TV（タグの TV）では `getElementById` に `\` を除いた ID を渡す。
- googlemap: `var $j = jQuery.noConflict();` を消し、`.data()` は `dataset`、`.after(html)` は `insertAdjacentHTML`、`.val()` は `value` にする。値を変えたら `MM.fire(el, 'change')` を起こすかは、今が起こしていない（未保存の扱いにならない）ので変えない。見出しのコメントの「v2」を直す。

### M6: ddmultiplefields の残りと仕上げ

対象: `widgets/ddmultiplefields/ddmultiplefields.php`（46 行）、`assets/docs/jquery-migration.md`。

- `change.ddEvents` / `load.ddEvents` は、発見のとおり独自の名前のイベント（`ddmultiple:change` など、`bubbles: true`）と関数の直接の呼び出しに置き換える。元の欄（`#tv<ID>`）が画像の選択で受け取る `change` は素の `change` のまま受ける。
- `$par.fadeOut(300, fn)` は、すぐに `remove()` してから `fn` の処理をする（動きは消える）。`jQuery.parseJSON` は `JSON.parse`、`.on("modxsortable:end")` は `addEventListener`。
- `if (!window.MODXSortable){ includeJs(dragdrop-sort.js) }` は、M1 の `includeJs` が `<script src>` を出す形になると条件の中に置けないので、条件を外して常に登録する（同じページで 2 回読まないことは `includeJs` の重複の除外で担保）。
- 入れ子の `jQuery(function(){…})`（`SetUrl` の差し替えと submit）は `Evo.ready(…)` にして、外側の処理の後に動く順番を保つ。
- 仕上げ: `grep -rnE '\$j|jQuery' assets/plugins/managermanager` が、意図して残したもの（無ければ 0 件）だけになることを確かめる。`assets/docs/jquery-migration.md` に「MM のウィジェット」の節を足し、`MM` の部品・`includeJs` の動き・fieldname のエスケープの注意を書く。

## Concrete Steps

各マイルストーンで次を繰り返す。コマンドは作業ツリーのルート（`/home/yamamoto/ghq/github.com/modxcms-jp/evolution-jp/.claude/worktrees/issue-819` など）で、1 コマンドに 1 つの操作で実行する。

1. 対象ファイルを Plan of Work のとおりに編集する。
2. 構文を確かめる（検証用イメージの PHP を使う）。

        docker exec -w /var/www/html <名前>-app php -l assets/plugins/managermanager/widgets/mm_hidetabs/mm_hidetabs.php

   期待: `No syntax errors detected`。

3. 対象ファイルに jQuery が残っていないことを確かめる。

        grep -rnE '\$j|jQuery' assets/plugins/managermanager/widgets/mm_hidetabs

   期待: 何も出ない。

4. Validation and Acceptance の手順で、そのマイルストーンのルールのセットを main とブランチで流し、結果を比べる。
5. コミットし、PR を作る。コミットの例:
   - M1: `refactor(mm): ルールの包みと外部ファイルの読み込みを素の JS にする`
   - M2: `refactor(mm): タブ・セクション・項目名のウィジェットの jQuery を素の JS に置き換える`
   - M3: `refactor(mm): 項目を隠す・移す・必須にするウィジェットの jQuery を素の JS に置き換える`
   - M4: `refactor(mm): 値を入れるウィジェットと画像のプレビューの jQuery を素の JS に置き換える`
   - M5: `refactor(mm): ddmaxlength・tags・googlemap の同梱ライブラリを素の JS に書き直す`
   - M6: `refactor(mm): ddMultipleFields の jQuery を素の JS に置き換え、MM の置き換えを終える`
   本文の最後に、M1〜M5 は `Refs #819`、M6 は `Closes #819`。動きが変わる点（`includeJs` の返り値、ddmultiplefields の行の削除の動き）は PR に書く。

## Validation and Acceptance

### 完了の条件（利用者が見てわかること）

- 下のルールのセットを入れたリソース編集の画面で、main とブランチの観測値（隠れた要素・項目名・タブの位置・入力欄の値・チェック・必須の印・タグの一覧・プレビュー・文字数の表示）が一致する。違いがあるなら、PR に理由を書いた意図した違いだけ。
- 画面を開いた直後に `window.documentDirty` が main と同じ（ふつうは `false`）。タグのクリックと画像の選択の後は `true`。
- ブラウザのコンソールに MM 由来のエラーが無く、「ManagerManager: An error has occurred」の alert が出ない。ルールが指す項目がテンプレートに無いときも同じ。
- ページを直接開いたときと、シェルの画面遷移（ツリーやメニューから開く）の両方で同じ結果になる。
- 必須の欄が空のまま保存すると alert が出て保存されず、埋めると保存できる（DB で確かめる）。
- M6 の後、`grep -rnE '\$j|jQuery' assets/plugins/managermanager` が 0 件。

### 検証環境の準備

`.claude/skills/adaptive-validation-runner/SKILL.md` と `references/environment.md` に従う。並走を避けるため、名前とアドレスを決めて毎回のコマンドの先頭に付ける（例: `EVO_VALIDATE_NAME=evo-validate-819 EVO_BIND_IP=127.0.0.19`）。

    EVO_VALIDATE_NAME=evo-validate-819 EVO_BIND_IP=127.0.0.19 docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml up -d --build
    EVO_VALIDATE_NAME=evo-validate-819 EVO_BIND_IP=127.0.0.19 NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/setup-site.js --sample
    EVO_VALIDATE_NAME=evo-validate-819 EVO_BIND_IP=127.0.0.19 NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/smoke.js

期待: smoke が全 PASS。

### 検証用の TV を作る

サンプルサイトには画像・テキストエリアなどの TV がそろっていないので、SQL で作る（接頭辞は既定の `modx_`）。まず、確かめに使うリソース（例: ID 1）のテンプレートの ID を調べる。

    docker exec evo-validate-819-db mariadb -uuser -ppassword evodb -N -e "SELECT template FROM modx_site_content WHERE id=1"

その値を `<TPL>` として、TV を作ってテンプレートに割り当てる。

    docker exec evo-validate-819-db mariadb -uuser -ppassword evodb -e "INSERT INTO modx_site_tmplvars (type,name,caption,description,elements,display_params,default_text) VALUES ('image','mmImage','MM画像','','','',''),('text','mmText','MMテキスト','','','',''),('textarea','mmArea','MMテキストエリア','','','',''),('text','mmTags','MMタグ','','','',''),('text','mmMulti','MM複数','','','',''),('image','mmMultiImage','MM複数画像','','','',''),('text','mmGeo','MM地図','','','','')"
    docker exec evo-validate-819-db mariadb -uuser -ppassword evodb -e "INSERT INTO modx_site_tmplvar_templates (tmplvarid,templateid,rank) SELECT id,<TPL>,0 FROM modx_site_tmplvars WHERE name LIKE 'mm%'"
    docker exec evo-validate-819-db mariadb -uuser -ppassword evodb -e "INSERT INTO modx_site_tmplvar_contentvalues (tmplvarid,contentid,value) SELECT id,1,'a,b' FROM modx_site_tmplvars WHERE name='mmTags'"

`mmImage` の値は、Playwright で `assets/images/` の実在する画像のパスを入れて確かめる（サンプルの画像は `ls assets/images` で探す）。

### ルールを入れる

チャンク `mm_rules` の中身を書き換える（PHP の開始タグは付けない）。書き換えたらキャッシュを作り直す。

    docker exec evo-validate-819-db mariadb -uuser -ppassword evodb -e "UPDATE modx_site_htmlsnippets SET snippet='<ルール>' WHERE name='mm_rules'"
    docker exec -w /var/www/html evo-validate-819-app php evo cache:clear

ルールの中の `'` は SQL の文字列のために `''` にするか、Playwright のスクリプトの中で `lib.js` の `sql()` に渡す。ウィジェット同士が干渉しない（例: タブを隠すと移した項目が見えない、accessdenied は画面をすべて消す）ように、次のセットに分けて 1 つずつ入れる。どのセットも、各ウィジェットを 1 回以上使う。

セット A（M2・M3、既存のリソース `a=27&id=1`）:

    mm_hideTabs('access');
    mm_hideSections('tvs');
    mm_renameTab('settings', '公開の設定');
    mm_renameSection('content', '本文の欄');
    mm_renameField('longtitle', '見出し', '', '', '見出しの説明');
    mm_changeFieldHelp('alias', 'URL に使う名前');
    mm_changeFieldHelp('mmText', 'テキストの説明');
    mm_createTab('追加のタブ', 'Extra');
    mm_moveFieldsToTab('mmText,pub_date', 'Extra');
    mm_hideTemplates('<今のテンプレート以外で、サンプルにあるテンプレートの ID>');
    mm_hideFields('link_attributes,menuindex,which_editor');
    mm_requireFields('pagetitle,mmArea');
    mm_synch_fields('pagetitle,menutitle');

セット B（M4、新規作成 `a=4&pid=1`。`mm_default` と `mm_inherit` は新規のときだけ動く）:

    mm_default('pub_date');
    mm_default('published', '0');
    mm_default('searchable', '0');
    mm_default('mmText', '既定の値');
    mm_inherit('mmArea');
    mm_ddSetFieldValue('cacheable', '0');
    mm_ddSetFieldValue('menutitle', 'メニューの名前');
    mm_widget_showimagetvs('mmImage');

   `mm_inherit('mmArea')` を確かめるため、親（ID 1）の `mmArea` に値を入れておく（`modx_site_tmplvar_contentvalues` に `contentid=1` の行を足す）。`mm_default('template', '<別のテンプレートの ID>')` は自動で送信されて画面が変わるので、別に 1 回だけ流し、送信後の URL と `newtemplate` の値を比べる。

セット C（M5・M6、既存のリソース `a=27&id=1`）:

    mm_ddMaxLength('mmArea', '', '', 20);
    mm_widget_tags('mmTags');
    mm_widget_googlemap('mmGeo', '', '35.68,139.76');
    mm_ddMultipleFields('mmMulti', '', '', 'field,text', '値,説明');
    mm_ddMultipleFields('mmMultiImage', '', '', 'field', '画像');

セット D（accessdenied。ID 2 を開く）:

    mm_widget_accessdenied('2', '<span>見られません</span>このリソースは開けません。');

### Playwright での比べ方

作業用ディレクトリ（リポジトリの外）に `observe.js` を置き、`lib.js` の `login()` と `newPage()` を使う。セットごとに対象の画面を開き、`#loadingmask` が隠れるのを待ってから、次を JSON に書き出す。

- 要素ごとの見える・見えない（`getComputedStyle(el).display` と `offsetParent`）: 各タブの見出しと本体、`#tv_header` / `#tv_body`、隠した項目の行。
- 文言: タブの見出し、セクションの見出し、項目名（`span.mutate-field-title`）、説明（`img.tooltip` の `title`・`data-powertip`、`span.comment`）。
- 項目がどのタブにあるか（`closest('.tab-page').id`）。
- 入力欄の値とチェック（`pub_date`・`published`・`publishedcheck`・`searchablecheck`・`cacheablecheck`・`menutitle`・`#tv<ID>`）。
- テンプレートの選択肢（`#field_template option` の値の一覧）。
- 必須の印（`.requiredIcon` の数）、文字数の表示（`.ddMaxLengthCount span` の文字と class）、タグの一覧（`#tv<ID>_tagList li` の文字と `tagSelected`）、画像のプレビュー（`#tv<ID>PreviewContainer img` の `src`）、ddmultiplefields の行数と各欄の値、地図の容器（`#map_canvas_tv<ID>`）と検索欄の有無。
- `window.documentDirty`、コンソールのエラー、出た alert の文言。

操作も加える: セット A で `pagetitle` に入力して `menutitle` が揃うこと、`pagetitle` を空にして保存して alert が出て保存されないこと。セット C でタグの `li` をクリックして入力欄の値と `documentDirty` が変わること、`mmArea` に 25 文字入れて残りが `-5` になり保存が止まること、ddmultiplefields で行を足して並べ替え、保存後の DB の値（`modx_site_tmplvar_contentvalues`）が main と同じ形（`値::説明||…`）になること。セット D は `#aback` だけが残ること。

同じスクリプトを、ページを直接開く場合と、シェルで別の画面から移る場合（`EvoShell` の画面遷移、`lib.js` と `smoke.js` の書き方を見本にする）の 2 通りで流す。

main とブランチの切り替えは、作業ツリーで 1 つずつ実行する。

    git switch --detach main
    git switch refactor/819-mm-vanilla

切り替えたら数秒待ってから流す（OPcache の `revalidate_freq=2`）。結果の JSON を `diff` で比べ、「セット × 開き方 × main × ブランチ」の表を PR の確認方法に書く。

googlemap は API キーが無いと地図そのものは出ない。容器と検索欄ができること、`initMap` が呼ばれること（`mm_gmap.map` に TV の ID が入る、または Google の API がキーの誤りを報告するところまで進む）、MM 由来の例外が出ないことを、main とブランチで比べる。

## Idempotence and Recovery

- 各マイルストーンは独立した PR で、どの段階でも jQuery 本体は残っているため、途中で止めても画面は動く。止めたマイルストーンは Progress に「途中」と書き、どのファイルまで置き換えたかを残す。
- 検証環境は何度でも作り直せる。TV とルールの SQL は、作り直した DB に対して同じ順で流す。TV を作り直すときは先に `DELETE FROM modx_site_tmplvars WHERE name LIKE 'mm%'` と、`modx_site_tmplvar_templates`・`modx_site_tmplvar_contentvalues` の該当の行を消す。
- ルールのチャンクは、確かめが終わったら元の中身（`assets/chunks/mm_rules.install_base.tpl` の本文）に戻すか、環境ごと片付ける（`references/environment.md` の「後片付け」）。
- main に切り替えたまま止まった場合は、`git switch refactor/819-mm-vanilla`（または作業中のマイルストーンのブランチ）で戻す。

## Artifacts and Notes

- Issue: #819（親 #679、後続 #823）
- ブランチ: `refactor/819-mm-vanilla`（M1）。M2 以降は `refactor/819-mm-vanilla-m<番号>` を main から作る
- 関連: #813（`Evo` の部品）、#814（リソース編集の未保存の検知）、#784（並べ替え）、#786（ツールチップ）、#788（日付）、#795（colors）、#796（ddgmap の削除、コミット 613c82192）
- 書き方の一覧: `assets/docs/jquery-migration.md`

## Interfaces and Dependencies

- 依存: `manager/media/script/evo.js` の `Evo.ready`（`header.inc.php` で読む）。`manager/media/script/shell.js` の `executeScripts()` が `<script src>` の読み込みを待つこと。`manager/media/script/dragdrop-sort.js` の `MODXSortable`。`manager/media/style/common/jscripts.tpl` の `BrowseServer`・`BrowseFileServer`・`SetUrl`・`changeRTE`・`requestSubmit()` による保存。
- 公開の関数（変えないもの）: すべてのウィジェットの関数名と引数、`useThisRule()`・`makeArray()`・`jsSafe()`・`tplUseTvs()`・`makeSqlList()` の動き、`includeJs($url, 'html')`・`includeCss($url, 'html')` の返り値、`$mm_fields` の中身、グローバルの JS の `mm_lastTab`・`synch_field`・`mm_sync_field_count`・`TagCompleter`・`mm_gmap`・`ddMultiple`。
- 変えるもの: `includeJs($url)` / `includeCss($url)`（既定の `'js'`）の返り値と読み込みの時機（Decision Log、要相談）。新しいグローバル `MM`（`assets/plugins/managermanager/mm.js`）。
- 範囲外: jQuery 本体と `$j` の定義（`manager/actions/header.inc.php`）を外すこと、自作のウィジェットや他の作者のプラグインの書き換え（#823）。
