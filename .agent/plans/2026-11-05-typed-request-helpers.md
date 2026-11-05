# ExecPlan: リクエスト値を型ごとに取り出すヘルパーを用意し、空の値の扱いを GET と POST でそろえる

## Purpose / Big Picture

管理画面・同梱のスニペットとプラグイン・インストーラが受け取るリクエスト値（URL のクエリやフォームの送信値）を、型が保証された形で取り出すようにする。これで、パラメータを欠いた・空の・配列を送ったリクエストで、PHP 8.1〜8.5 の非推奨の警告や TypeError、想定外の SQL が出なくなる。あわせて、GET と POST で空の値の扱いが違う状態をなくし、`getv()` を `postv()` に書き換えても動きが変わらないようにする。

Issue: #1059

## Progress

- [x] (2026-11-05) M1: 型つきヘルパー 12 関数と `to_*()` 4 つを `manager/includes/helpers.php` に足し、決まりを `assets/docs/request-input.md` に書いた。`*_strvar` を security-lint の検出対象に入れた（PR は Artifacts and Notes）
- [x] (2026-11-05) M2: `getv()` の特例をやめ、全 221 行を読んで空の値で動きが変わる 9 か所（7 ファイル）を型つきに直した。エラー画面の操作の番号も型つきで読むようにした（PR は Artifacts and Notes）
- [x] (2026-11-05) M3: `manager/actions/` の置き換え（3 つに分ける。M3a: document・main・tool・report と直下、M3b: element、M3c: permission）
  - [x] (2026-11-05) M3a: 15 ファイルで型つきへ置き換え（`evo()->input_*()` を含む）。出力する値も `*_strvar()` で取り出すよう決まりを改めた（PR は Artifacts and Notes）
  - [x] (2026-11-05) M3b: `manager/actions/element/` の 13 ファイルで型つきへ置き換え。テンプレートとテンプレート変数の画面の、操作の番号による権限の判定をほかのエレメントの画面と同じ形にそろえた（PR は Artifacts and Notes）
  - [x] (2026-11-05) M3c: `manager/actions/permission/` の 14 ファイルで型つきへ置き換え。入口で操作の番号と id を 1 回だけ読む形にそろえ、フォームの差し戻しの値から変数を作る `extract()` を消した（PR は Artifacts and Notes）
- [ ] M4: `manager/processors/` の置き換え
  - [x] (2026-11-06) M4a: `manager/processors/permission/` の保存の処理で型つきへ置き換え。mode は型つきで読んで当てはまらない値を拒否し、形式違いのロール・空のユーザー名を拒否するようにした（PR は Artifacts and Notes）
  - [ ] M4b: エレメントの保存の処理（`module`・`tmplvars`・`plugin`・`snippet`・`chunk`・`template`）
  - [ ] M4c: `document`・`pm`・`db`・`backup` と `manager/processors/` 直下
- [ ] M5: `manager/includes/`・`manager/media/`・`manager/frames/` ほか `manager/` 直下の置き換え
- [ ] M6: `assets/`（スニペット・プラグイン・モジュール）と `install/` の置き換え
- [ ] M6b: `evo()->input_get()` / `input_post()` / `input_any()` / `input_cookie()` を DocumentParser から消す（M3〜M6 で本体の呼び出しを置き換えたあと）
- [ ] M7: 置き換え漏れを検出する検査を `security-lint.php` に足す
- [ ] M8: PHP 8.5 と PHP 7.4 での横断の確認と振り返り

## Surprises & Discoveries

- 2026-11-05: 型つきの取り出し口は、ID 専用の `get_intvar()` / `post_intvar()` / `request_intvar()` / `to_intvar()` / `abort_if_invalid_id()` として既にある（`manager/includes/helpers.php`、呼び出し元は約 150 か所）。新しい仕組みは作らず、この一族を広げる。
- 2026-11-05: #1058 で、`getv()` の引数なし呼び出しによる null 添字、リクエスト値の既定値 `null` を `preg_match()` などへ渡す約 20 か所、モジュールの依存関係の削除で `count(null)` が TypeError になる不具合を直した。いずれもこの計画で防ぎたい形の実例。
- 2026-11-05: `getv()` の特例をやめると、既定値を省いた `getv()` も空の値で `null` ではなく `''` を返すようになる（PR #1060 のレビューで指摘）。たとえば `manager/frames/nodes.php:54` は `getv('offset') !== null` で分岐していて、`offset=&expandAll=1` は今は通常の展開に進むが、特例をやめるとページ送りの分岐に入り `expandAll` を無視する。そのため M2 の確認は既定値つきの呼び出しに限らず、`getv()` の全呼び出しを対象にする。
- 2026-11-05（M1 の自己レビュー）: `.github/scripts/security-lint.php` の sql-embed は `REQUEST_FUNCS`（`getv` / `anyv` / `postv` / `cookiev`）の名前で照合している。中身をそのまま返す `*_strvar()` に置き換えると SQL への埋め込みが検出から外れるため、M1 で `*_strvar` を `REQUEST_FUNCS` に足した。`*_intvar` / `*_boolvar` / `*_choicevar` / `*_idsvar` は返す値がコードで決まるので足さない。
- 2026-11-05（M1 の自己レビュー）: ID の一覧を読む既存の処理は動きが揃っていない。`manager/processors/document/move_document.processor.php` は 1 つでも不正な id があればエラーで止め、`manager/actions/element/mutate_module_resources.dynamic.php` は読めた id だけを使う。`*_idsvar()` は読めない要素を黙って捨てるので、全体を拒否したい処理は置き換えないか、要素数を比べる（`assets/docs/request-input.md` に記載）。
- 2026-11-05（M2）: `getv()` の約 205 行は、空の値でも結果が変わらない使い方だった（真偽判定、`(int)`・`hsc()`・`empty()`、`== ''` 比較、既定値が `''` のもの、全件取得）。`isset(getv())`・`is_null(getv())`・`??`・strict な `in_array`・配列の添字に使う形は 0 件。`install/` には `getv()` が無い。`evo()->input_get()` は `$_GET` を直接読むので、この変更の影響を受けない。
- 2026-11-05（M2 の自己レビュー）: エラー画面（`manager/includes/traits/document.parser.subparser.trait.php`）の操作の番号は、`a[]=1` のとき PHP 8 で TypeError になり、値をエスケープせずに出力していた（M2 の前から）。`get_intvar('a') ?: post_intvar('a')` で読むようにして、M2 と一緒に直した。
- 2026-11-05（M2 の検証）: `manager/frames/nodes.php` は直接開くと `$modx` が無く空の 200 で終わる。実際の呼び出しは `index.php?a=1&f=nodes&...`（tree.js）なので、検証はこの形で行う。
- 2026-11-05（M3a の検証）: 範囲外の 3 か所が、配列を送ると TypeError になる（修正前から）。`manager/includes/accesscontrol.inc.php:90` の `preg_match(..., anyv('id', 0))`（`id[]=1` を送るとどの画面でも出る）、`manager/includes/extenders/ex_maketable.php:351` の `getv('page')`、`manager/includes/traits/document.parser.subparser.trait.php` の `checkPermissions()`（`$docid` を文字列として扱う）。M5 で扱う。
- 2026-11-05（M3a の検証）: 管理画面の既定の設定は `display_errors=Off` なので、応答の本文だけでは PHP の警告を見逃す。検証では、コンテナの中だけで `display_errors=On` と `error_log` を stderr にする ini を置き、本文と stderr の両方で数える（作業ツリーには書かない）。
- 2026-11-05（M3b の自己レビュー）: `manager/index.php` は `(int)anyv('a')` で画面を振り分けるので、`a` の書き方（先頭の 0 など）が違っても同じ画面に入る。画面の側で `get_intvar('a') === 16` のように「当てはまるときだけ権限を確かめる」形にすると、当てはまらない値で権限の判定が働かない。テンプレートとテンプレート変数の画面を、ほかのエレメントの画面と同じく `switch (request_intvar('a'))` で当てはまらない値を拒否する形にそろえた。これで、main にあった POST だけで送る経路も閉じた。振り分け側（`manager/index.php`）の読み方は M5 で扱う。
- 2026-11-05（M3b の自己レビュー）: テンプレートの画面は、フォームのイベント（OnTempFormPrerender など）に `id()` の戻り値を渡していて、これは文字列（新規は `''`）。チャンク・スニペット・テンプレート変数の画面は int の `$id` を渡している。
- 2026-11-06（M3c の自己レビュー）: ユーザー・Web ユーザー・ロールの画面は、権限の判定は `request_intvar('a')` で、データの読み込み（所属グループ）と hidden は `get_intvar('a')` / `get_intvar('id')` で、受け取り元が食い違っていた（修正前から）。新規の権限だけを持つ利用者が、受け取り元を食い違わせて他人の所属グループを見られる経路があった。入口で `$action` と id を 1 回だけ読み、権限の判定・データの読み込み・テンプレートの分岐・hidden をすべてその値で作る形にそろえた（M3b のエレメントの画面と同じ）。新規の画面では id を 0 にする。
- 2026-11-06（M3c の検証）: 検証環境（公式の Docker イメージ。php.ini を持たない）では `request_order` が空で、`$_REQUEST` に cookie が入り、同じ名前があると cookie の値が優先される。`anyv()` を通るヘルパーはすべて影響を受ける。#1073 に分けた（本人の判断で保留）。
- 2026-11-06（M3c の自己レビュー）: Web ユーザーの画面とプロフィールの画面は、保存に失敗したときの差し戻しの値（セッションに保存したフォームの値）を `extract()` で変数にしていて、入口で決めた `$user`・`$action` やテーブル名の変数を上書きできた。入口の値を使う形にそろえた直後に、上書きされた値が SQL と hidden に入る経路ができていたので、`extract()`（とプロフィールの画面の `${$k} = $v`）を消し、値は `setting()` などの関数で読み、SQL に入れる id は直前で `(int)` にした。プロフィールの画面の `use_editor` は、個人の設定が無いときはシステム設定に戻す（修正前の動き）。
- 2026-11-06（M3c の検証）: ユーザーの画面のブロック中の判定は、PHP 8 で動きが変わっていた。保存に失敗したときの再表示で日時が空文字になると `'' != 0` が PHP 8 で真になり、ブロックしていないユーザーが「ブロック中」で出ていた。ユーザーの編集画面の `blockedmode()` には `??` の優先順位の誤りもあった。さらに、差し戻しの日時が入力欄の書式の文字列のまま残り、判定も入力欄の表示（1970 年）も、保存し直したときの値（年だけ）も壊れていた。共通の関数 `isUserBlocked()`（`manager/actions/permission/mutate_user/blocked.php`）にそろえて数値で比べ、差し戻しの日時は生年月日と同じく `ConvertDate()` でタイムスタンプに戻した。画面が読んでいた `failedlogins` は DB の列名（`failedlogincount`）と合わず働いていなかったので、判定から消した。
- 2026-11-06（M3c の検証）: ユーザーの編集画面で、ブロックの日時を入れると保存に失敗する（strict モード）・hidden の `blocked` が期限付きのブロックを恒久のブロックに上書きしうる、という保存側の問題が修正前からある。#1076 に分けた（M4 の範囲と重なるが、JS と保存の処理の見直しが要るので別に扱う）。
- 2026-11-06（M4a の検証）: 保存の処理の `mode` は緩い比較（`== 12` など）で、PHP 7 では `012`・`12.0`・前後に空白の付いた値が通っていた。権限の弱い利用者が `mode=011` で新規作成でき、パスワードの変更の権限だけの利用者が `mode=074` などで保存の経路に入れた。`post_choicevar()` で 1 回だけ読んで当てはまらない値を拒否する形にした（ユーザーは `saveUserMode()`、Web ユーザーは `saveWebUserMode()`）。パスワードの確認欄も緩い比較で、`1000000` と `1.0e6` が一致していた（4 つの保存の処理とパスワードの変更）。
- 2026-11-06（M4a の自己レビュー）: Web グループの削除は、`web_groups` を `webuser` の列で消していて、グループと同じ番号の Web ユーザーの所属が消えていた（修正前から）。`webgroup` の列で消すように直した。Web ユーザーの保存は、エスケープ済みの値をイベント（OnWebSaveUser など）とメールにも渡していたので、エスケープを DB に渡す直前に移した。
- 2026-11-06（M4a の自己レビュー）: `save_web_user.processor.php` には `compact()`（AGENTS.md で使わない決まり）が残っている（修正前から）。M4a では範囲を広げず、そのままにした。

## Decision Log

- 2026-11-05 / yamamoto・Claude: 取り出し口の形は、既存の `*_intvar()` を広げる（受け取り元の接頭辞 `get_` / `post_` / `request_` × 型）。代替案の `request()->query()->int('id')` のような 1 つの入口は Symfony に近いが、既存の約 150 か所も置き換えになり、今の作法から外れるため採らない。
- 2026-11-05 / yamamoto・Claude: 空の値は「生の値はそのまま、型つきは既定値」にする。`getv()` / `postv()` / `anyv()` はキーがあれば空文字をそのまま返し、既定値はキーが無いときだけ使う（Symfony の InputBag と同じ）。型つきの取り出し口は、空・形式違い・配列などの想定外の型を、すべて既定値にする。代替案の「すべて空なら既定値」は、保存で項目を空にしたい場合に既定値が入る恐れがあり、`postv()` の約 540 か所の確認が重いため採らない。「型つきも空はそのまま」は、型つきで空を受けたときの値が型ごとにばらつくため採らない。
- 2026-11-05 / Claude（M1 の自己レビュー）: `*_choicevar()` の `$default` は必須にする。省けると形式違いのときに `null` が返り、この計画が防ぎたい「null を文字列関数へ渡す」形がまた生まれるため。
- 2026-11-05 / Claude（M1 の自己レビュー）: `*_idsvar()` は、文字列で受けたときだけカンマで分けた各要素の前後の空白を除く（`'3, 4'` は `[3, 4]`）。配列の要素は除かない。既存の `move_document.processor.php` が各要素を `trim()` してから読んでいるのに合わせた。1 つの値を読む `*_intvar()` は今のまま空白を許さない。
- 2026-11-05 / Claude（M2）: `manager/actions/element/mutate_plugin.dynamic.php` の `["id" => getv('id')]`（プラグインのイベント OnPluginFormPrerender / OnPluginFormRender に渡す値）は直さない。`id=` を明示して開いたときだけ null から `''` に変わり、違いが出るのは受け取る側が `isset()` / `is_null()` で判定するときだけ（標準添付のプラグインに該当なし）。`get_intvar('id') ?: null` にすると、逆に `'abc'` などで値が変わる。
- 2026-11-05 / Claude（M2）: `manager/actions/header.inc.php` の `tab` は `get_strvar('tab', '1')` で読む。既定値が int の 1 から文字列の `'1'` に変わるが、`setcookie()` が文字列にして送るので、ブラウザに届く値は同じ。
- 2026-11-05 / Claude（M3a）: M3 は対象が約 300 か所あるので、`manager/actions/` のサブディレクトリで 3 つに分ける（M3a: document・main・tool・report と直下の約 110 か所、M3b: element の約 100 か所、M3c: permission の約 100 か所）。それぞれ 1 つの PR にする。
- 2026-11-05 / Claude（M3a の自己レビュー）: `evo()->input_get()` / `input_post()` / `input_any()` は、中身が `getv()` / `postv()` / `anyv()` と同じ生の値なので、置き換えの対象に入れる（受け取り元の対応は `input_get` → `get_`、`input_post` → `post_`、`input_any` → `request_`）。
- 2026-11-05 / yamamoto: `evo()->input_get()` / `input_post()` / `input_any()` / `input_cookie()`（2018 年に足した DocumentParser の公開メソッド）は、本体からの呼び出しを置き換えたあと、1.3 で消す（M6b）。代替案の「非推奨にして 1.4 で消す」「残す」は採らない。
- 2026-11-05 / Claude（M3a の検証）: `hsc(getv('x'))` のように出力するものは、配列が来ると Array to string の警告になる。「出力するものは生の値のまま残してよい」を改め、出力するものは `*_strvar()` で取り出す、とした（`assets/docs/request-input.md`）。あわせて、残してよい形に「送られたかどうか（真偽）だけを見るもの」を足し、配列だけを受ける項目（配列でないときは `[]`）と、文字列と配列のどちらも正しい値になる項目（テンプレート変数の値。生の値のまま受ける）を分けて書いた。
- 2026-11-05 / Claude（M3a）: 置き換えで、意味のない値のときに動きが変わるものは許容した。記録の件数（`logging.static.php` の `nrresults`）の 0・負数・小数は設定値になる、`wait.static.php` の `dv=1&id=0` は `a=3&id=0` ではなく `a=2` へ移る、`bkmanager.static.php` の `r=0` で `doRefresh(0)` を出さない（`reloadPane(0)` は何もしない）、`parent_picker.dynamic.php` の重複した id は 1 つになる。正常な値の動きは変えていない（PHP 8.5 で修正前後を 314 ケース比べて同一）。
- 2026-11-05 / Claude（M3a の自己レビュー）: `bkmanager.static.php` の `doRefresh` の行は、`header.inc.php` と同じ条件だが消さない。`header.inc.php` はシェルの断片の要求（`$isPaneRequest`）では読み込まれず、消すとその場合の再読み込みが失われるため。
- 2026-11-05 / Claude（M3b）: プラグインの画面がフォームのイベント（OnPluginFormPrerender / OnPluginFormRender）に渡す `id` を `get_intvar('id')`（新規は 0）にした。M2 では「直さない」としていたが、チャンク・スニペット・テンプレート変数の画面が int の `$id` を渡しているのにそろえるため改めた。違いが出るのは、受け取る側が `isset()` / `is_null()` / `=== null` で判定する場合だけ。
- 2026-11-05 / Claude（M3b）: 操作の番号（`a`）などで権限を分ける判定は、型つきで読み、当てはまらない値は拒否する（`switch` の `default` でエラー）。空で送ることに意味がある検索語（モジュール一覧の `search`）は生の値で受けて `is_string()` で確かめる。どちらも `assets/docs/request-input.md` に書いた。
- 2026-11-05 / yamamoto: GET と POST の両方を読むヘルパーの名前（生の値は `anyv()`、型つきは `request_*var()`）のずれは、型つきを `any_*var()` にそろえる。ただし、多くの呼び出しは実際には GET か POST の片方からしか呼ばれないので、受け取り元を入口に合わせて `get_` / `post_` に絞る作業とあわせて #1070 で行う。この計画では `request_*var()` の名前のまま置き換えを続ける。
- 2026-11-06 / yamamoto: `extract()` はできるだけ使わない。リクエスト由来のものは #1059 で消す（M3c で 2 画面、`send_message.processor.php` は M4）。設定値などをグローバル変数にするもの（`manager/index.php:54` など）は #1074 に分ける。スニペット・プラグイン・モジュールに引数を変数で渡すもの（MODX の作法）と、Ditto の request extender（許可の一覧に載ったものだけ）は残す。
- 2026-11-06 / yamamoto: プロフィールの画面（必ず自分自身）の削除ボタンは出さない。削除の処理が自分自身の削除を断るので、押しても必ず断られていた。管理画面の操作ボタンに非活性の作りは無いので、ユーザーの編集画面で自分を開いたときと同じく、出さない形にした。
- 2026-11-06 / Claude（M4a）: M4 は保存の処理なので 3 つに分ける（M4a: permission、M4b: エレメント、M4c: document・pm・db・backup と直下）。検証は、画面のフォームから保存し、DB の値を列ごとに修正前と比べる。
- 2026-11-06 / Claude（M4a）: 形式違いの値は、既定値にして保存するのではなく拒否する。ロール（`01`・`1.0`・`1abc`・配列）を 0 として保存すると、更新で対象のユーザーが使えなくなるため。空や空白だけのユーザー名も、新規（11・87）と Web ユーザーの更新（88）で拒否する（管理ユーザーの更新 12・74 は前から拒否していた）。修正前は、キーが無いときだけ `'New User'` で保存し、空のときは空の名前で保存していた。
- 2026-11-06 / Claude（M4a）: イベント（OnBeforeUserFormSave・OnUserFormSave・OnManagerSaveUser・OnManagerChangePassword）に渡す id・ロールは int にした。M3b でフォームのイベントに渡す id を int にそろえたのと同じ向き。同梱のプラグインに、これらのイベントを受け取るものは無い。
- 2026-11-06 / Claude（M4a）: 保存に失敗したあとの差し戻しは、`restorableFormValues()`（`manager/actions/permission/mutate_user/form_values.php`）で、文字列と、配列だけを受ける項目（`user_groups`・`allowed_days`）に絞る。再表示の所属グループのチェックは、差し戻しの値があればそれで決める（修正前は POST を見ていて、リダイレクトの後は空だった）。
- 2026-11-06 / Claude（M4a）: Web ユーザーの保存で、パスワードを変えない更新にも OnWebChangePassword が前のイベントの値で呼ばれる件は、イベントの呼び出しの仕様にかかわるので #1078 に分けた。

## Outcomes & Retrospective

## Context and Orientation

用語:

- リクエスト値: ブラウザが送ってくる値。URL の `?id=5` のようなクエリ（GET）と、フォームの送信内容（POST）がある。PHP では `$_GET` / `$_POST` / `$_REQUEST` に入るが、このプロジェクトでは直接触らず、ヘルパーを通す（AGENTS.md の「スーパーグローバル変数の禁止」）。
- 生の値のヘルパー: `getv($key, $default)`（GET）、`postv()`（POST）、`anyv()`（GET と POST の両方）。値を変換せずに返す。キーを省略すると全件を配列で返す。中身は `array_get()`。
- 型つきのヘルパー: 決まった型の値だけを返し、それ以外は既定値を返すヘルパー。計画の開始時は ID 用の `*_intvar()` だけがあり、M1 で文字列・真偽値・選択肢・ID の配列を足した。

関係するファイル:

- `manager/includes/helpers.php`: `array_get()`（373 行付近、4 つ目の引数 `$validate` で値を確かめられる）、`*_intvar()` と `to_intvar()`、M1 で足した型つきヘルパー（420〜600 行付近）、`getv()` / `postv()` / `anyv()`（630〜680 行付近）。行番号は 2026-11-05 の M1 の時点のもの。
- `.github/scripts/security-lint.php`: processor の中でリクエスト値を無害化せずに SQL へ入れる書き方などを検出する。CI（`.github/workflows/security-lint.yml`）で動く。
- 文書: ヘルパーの決まりは今は AGENTS.md の「スーパーグローバル変数の禁止」にしか無い。

今の状態（2026-11-05、grep による数）:

- 呼び出し元は `getv` 238・`postv` 544・`anyv` 235 か所。ディレクトリごとでは `manager/actions/` 360、`manager/processors/` 363、`manager/includes/` 66、`manager/media/` 32、`manager/frames/` 14、`assets/snippets/` 37、`assets/plugins/` 46、`assets/modules/` 40、`install/` 44。
- 取り出した後に自分で確かめる書き方が散らばっている: ID の形式チェック `preg_match('@^[1-9][0-9]*$@', getv('id'))` が 16 か所、`(int)` / `intval()` の後付けが 55 か所、`is_numeric()` / `ctype_digit()` が 4 か所。
- `getv()` だけが、空の値（`?k=`）を「無い」とみなして既定値（既定値を省いたときは `null`）を返す。この特例に頼っている形は 2 つある。1 つは既定値を省いて `null` と比べるもので、直接比べている箇所は `manager/frames/nodes.php:54` の `getv('offset') !== null` と `manager/actions/document/mutate_content/functions.php:504` の `getv('pid') === null`（変数に入れてから比べる形は M2 で追う）。もう 1 つは既定値つきの `getv()`（35 か所）で、空のときに既定値へ戻ることを期待しているもの（例: `manager/actions/header.inc.php` の `getv('tab', 1)` と `getv('f', 'mainpane')`、`manager/actions/report/logging.static.php` の `getv('nrresults', config('number_of_logs'))`、`manager/actions/report/eventlog.dynamic.php` の `getv('period', 'latest')`、`manager/media/browser/evo/browser.php` の `getv('type', getv('Type', 'images'))`）。

## Plan of Work

M1 で型つきのヘルパー（`*_strvar` / `*_boolvar` / `*_choicevar` / `*_idsvar`）を足し、決まりを文書にする（M1 で完了）。それぞれ `get_` / `post_` / `request_` の 3 つの受け取り元を用意し（ID 用の `*_intvar()` と同じ並び）、変換の本体は受け取り元に依らない `to_*()` に置く。各ヘルパーの戻り値の規則、空白と大文字小文字の扱い、0 や負の数が意味を持つ値の扱い（`*_numvar()` は置き換えの途中で要るとわかったら足すかを決め、Decision Log に書く）は、`assets/docs/request-input.md` を正本とする。

M2 で `getv()` の空の値の特例をやめる。動きが変わるのは、空の値が送られたときに、戻り値が既定値（省いたときは `null`）から `''` に変わる呼び出し元である。既定値の有無に関わらず `getv()` の全呼び出し（2026-11-05 時点で 238 か所・221 行）を確かめ、空の値と無い値で動きが分かれるもの（既定値に戻ることを期待する、`null` と比べる、戻り値を入れた変数を `null` や `isset()` で判定する）を型つきへ置き換えるか、`=== null` の判定を「空か無いか」の判定に直す。特例をやめる変更と置き換えは同じ PR にする（途中の版で動きが変わらないように）。

M3〜M6 でディレクトリごとに置き換える。置き換えの規則（置き換えの表、生の値のまま残してよい形、`*_boolvar()` や `*_idsvar()` に置き換える前に確かめること）は `assets/docs/request-input.md` を正本とし、ここには写さない。

M6b で、本体からの呼び出しが無くなった `evo()->input_get()` / `input_post()` / `input_any()` / `input_cookie()` を `manager/includes/document.parser.class.inc.php` から消す（Decision Log の 2026-11-05 の決定）。消す前に `grep -rnE "input_(get|post|any|cookie)\(" --include=*.php manager assets install` が 0 件であることを確かめ、`assets/docs/request-input.md` の該当の 1 行も消す。サイト側のスニペットやプラグインで使っていると Fatal error になるので、PR に `仕様変更` を付け、代わりの書き方（`getv()` や型つきヘルパー）を本文に書く。

M7 で、置き換え漏れを検出する規則を `security-lint.php` に足す。対象は、生の値のヘルパー（`getv()` / `postv()` / `anyv()`）の戻り値を、既定値なしのまま文字列関数（`preg_match`・`trim`・`strpos`・`strtolower`・`explode`・`is_file` など）や `count()`、出力の関数（`hsc`・`htmlspecialchars`・`urlencode`・`rawurlencode`）に直接渡す書き方。走査範囲は `manager/`・`assets/`（`assets/plugins/*/tinymce*/` を除く）・`install/`。M3〜M6 が終わった時点で違反 0 件にし、CI で守る。

M8 で、PHP 8.5 と PHP 7.4 の検証環境で横断の確認をする。

SQL に入れる値の無害化は PDO ベースの新しい DB API（`.agent/plans/2026-07-08-pdo-migration-dbapi-refactor.md`）が受け持つ。この計画は「型が保証された値を渡す」までにし、`db()->escape()` を外すなどの変更はしない。`sessionv()` / `serverv()` / `cookiev()` はリクエストの値ではないので対象外にする（`cookiev()` は 5 か所だけなので、M5 で必要なら型つきに寄せる）。

## Concrete Steps

M1:

- 編集: `manager/includes/helpers.php` に `to_strvar()` / `to_boolvar()` / `to_choicevar()` / `to_idsvar()` と、それぞれの `get_` / `post_` / `request_` を足す。`*_intvar()` の直前のコメントを、一族全体の説明に書き直す。
- 新規: `assets/docs/request-input.md`（生の値と型つきの使い分け、空の値の扱い、置き換えの表）。AGENTS.md のドキュメントマップに足し、「スーパーグローバル変数の禁止」の項から参照する。
- 確認: 検証用イメージの PHP で、各 `to_*()` に `null`・`''`・`'0'`・`'abc'`・配列・正常な値を渡し、期待どおりの値を返すことを確かめる。PHP 7.4 は `php:7.4-cli` で同じスクリプトを流す。

    docker run --rm -v "$PWD":/app -w /app php:7.4-cli php <作業用ディレクトリ>/check-helpers.php
    期待: すべての行が PASS

M2:

- 編集: `getv()` から空の値を消す処理を取り除き、`postv()` と同じく `array_get($_GET, $key, $default)` だけにする。
- 確認: `grep -rnE "\bgetv\(" --include=*.php manager assets install`（tinymce を除く 238 か所・221 行）を 1 つずつ読み、空の値と無い値で動きが分かれるものを Plan of Work の規則で直す。直接 `null` と比べる箇所は `grep -rnE "getv\('[^']+'\) *(!==|===) *null|is_null\(getv\(" --include=*.php manager assets install` で拾えるが、変数に入れてから比べる形はこれで拾えないので、全呼び出しを読む。直した箇所と、そのままにした理由を PR 本文に表で書く。
- 確認: 検証環境で、`manager/frames/nodes.php` を `offset=&expandAll=1` で呼び、修正前と同じくツリーをすべて展開した結果が返ることを確かめる。
- 確認: 検証環境で、置き換えた画面を空の値で開き（例: `a=114&period=`、`a=13&nrresults=`）、修正前と同じ表示になることを確かめる。

M3〜M6（各マイルストーンで同じ手順）:

- 編集: 対象ディレクトリの呼び出し元を、`assets/docs/request-input.md` の置き換えの表に沿って置き換える。1 つの PR は 1 つのマイルストーン（大きければサブディレクトリ単位）にする。
- 確認: 下の Validation の巡回スクリプトを、対象の画面に絞って修正前と修正後で流し、警告が増えていないこと、パラメータを欠いた・空の・配列を送ったリクエストで警告が 0 件であることを確かめる。保存を伴う画面は、`.claude/skills/adaptive-validation-runner/scripts/smoke.js` と、その画面の保存を 1 回ずつ確かめる。

M7:

- 編集: `.github/scripts/security-lint.php` に規則（例: `raw-input`）を足す。
- 確認: `php .github/scripts/security-lint.php` が `0 violations` で終わる。わざと `trim(getv('x'))` を書いた一時ファイルで違反が出ることも確かめ、確かめた後に消す。

## Validation and Acceptance

検証環境は `.claude/skills/adaptive-validation-runner/` の手順で立てる。PHP 8.5 は `.claude/skills/adaptive-validation-runner/references/environment.md` の「ほかの PHP の版で検証するとき」の方法で `FROM php:8.5-apache` にしたイメージを使う。一覧のページ送りを通すため、管理ユーザー・Web ユーザー・リソースを各 120 件ほど足し、`php evo cache:clear` でキャッシュを作り直す（MariaDB の `seq_1_to_120` を使う。`<名前>` は検証環境の名前）。

    docker exec <名前>-db mariadb -uuser -ppassword evodb -e "
      INSERT INTO modx_manager_users (username,password) SELECT CONCAT('u',seq),'x' FROM seq_1_to_120;
      INSERT INTO modx_user_attributes (internalKey,fullname,role,email) SELECT id,CONCAT('User ',id),1,CONCAT(username,'@example.com') FROM modx_manager_users WHERE username LIKE 'u%';
      INSERT INTO modx_web_users (username,password) SELECT CONCAT('w',seq),'x' FROM seq_1_to_120;
      INSERT INTO modx_web_user_attributes (internalKey,fullname,email) SELECT id,CONCAT('Web ',id),CONCAT(username,'@example.com') FROM modx_web_users WHERE username LIKE 'w%';
      INSERT INTO modx_site_content (pagetitle,alias,parent,published,template,content,createdon,editedon) SELECT CONCAT('Doc ',seq),CONCAT('doc',seq),0,1,1,'x',UNIX_TIMESTAMP(),UNIX_TIMESTAMP() FROM seq_1_to_120;"

巡回は Playwright でログインし、画面ごとに GET した本文から `Deprecated` / `Warning` / `Notice` / `Fatal error` / `Uncaught` の行を集める（`.claude/skills/adaptive-validation-runner/scripts/lib.js` の `login()` を使う。書き方は `.claude/skills/adaptive-validation-runner/scripts/smoke.js` が見本）。巡回の後にシステムログの `level` が `error` 以上の行も確かめる。

完了とみなす観察結果:

- 管理画面の画面（`manager/index.php` の振り分けのうち `manager/actions/` を読むもの、約 70 画面）と公開側のサンプルサイトの各ページを巡回し、応答の本文とシステムログ（`temp/logs/system/`）に Deprecated / Warning / Notice / Fatal error が 0 件。
- 同じ巡回を、各画面の主なパラメータを「欠いた」「空にした」「配列にした（`id[]=1`）」の 3 通りでも流し、0 件。
- `.claude/skills/adaptive-validation-runner/scripts/smoke.js` が PHP 8.5 と標準の PHP 8.4 で全 PASS。変更したファイルが `php:7.4-cli` で構文チェックを通る。
- `php .github/scripts/security-lint.php` が `0 violations`。
- `assets/docs/request-input.md` に決まりが書かれ、AGENTS.md から参照されている。

## Idempotence and Recovery

各マイルストーンは独立した PR にし、マージ済みのマイルストーンは Progress に 1 行で記録する。途中で中断したときは、Progress の未完了のマイルストーンから再開する。M2 だけは、`getv()` の変更と呼び出し元の置き換えを同じ PR に入れる（分けると、間の版で空の値の扱いが変わる）。置き換えで動きが変わった画面が見つかったら、その PR を戻せば元に戻る（ヘルパーの追加は互換を壊さない）。

## Artifacts and Notes

- Issue: #1059
- 前段の修正: PR #1058（PHP 8.5 の非推奨の警告とモジュールの依存関係の不具合）
- 参考にした作法: Laravel の `$request->integer()` / `boolean()` / `string()` / `enum()` と `has()` / `filled()`、Symfony の InputBag の `getInt()` / `getDigits()` / `getBoolean()` / `getString()` / `getEnum()`
- 段階ごとのブランチ（`origin/main` から `git switch -c` で作り、Issue には紐づけない。後の段階は前の段階のブランチの上に積み、PR の base も前の段階のブランチにする。本人の指示、2026-11-05）: M1 は `feat/1059-m1-typed-helpers`（PR #1062）、M2 は `feat/1059-m2-getv-empty`（PR #1064、base は M1 のブランチ）、M3a は `refactor/1059-m3a-actions`（PR #1068、base は M2 のブランチ）、M3b は `refactor/1059-m3b-element`（PR #1071、base は M3a のブランチ）、M3c は `refactor/1059-m3c-permission`（PR #1077、base は M3b のブランチ）、M4a は `refactor/1059-m4a-permission-processors`（base は M3c のブランチ）。
- M1 の確認ケース（`to_*()` を直接呼び、php:7.4-cli・8.4-cli・8.5-cli で ALL PASS、警告 0。`error_reporting(E_ALL)` で警告を FAIL にし、`require 'manager/includes/helpers.php'` だけで読み込める）。M2 以降で境界を確かめ直すときは、同じ表で作り直す。
    to_strvar:   null→'' / ''→'' / ('', 'x')→'x' / 'abc'→'abc' / ['a']→既定 / 5→既定 / '0'→'0' / ' '→' '
    to_boolvar:  null→false / ('', true)→true / true→true / 1→true / (2, 'd')→'d' / '1'→true / ' On '→true / 'OFF'→false
                 'yes'→true / 'no'→false / ('abc', 'd')→'d' / (['1'], 'd')→'d' / (1.0, 'd')→'d'
    to_choicevar: ('a', ['a','b'], 'd')→'a' / ('z', …)→'d' / ('', …)→'d' / ('2', [1,2,3], 0)→2（元の型）/ (3, ['1','3'], null)→'3'
                 (['a'], ['a'], 'd')→'d' / ('a', [0], 'd')→'d' / ('01', [1,2], 0)→0 / 引数 2 つ→ArgumentCountError
    to_idsvar:   null→[] / ''→[] / '1,2,3'→[1,2,3] / '3, 4'→[3,4] / ' 3 , x, 4'→[3,4] / '3'→[3] / ' 3 '→[3]
                 '3,2,abc,0,3,-1,,5'→[3,2,5] / ['4',5,'x','4',['1']]→[4,5] / [' 3']→[] / 5→[] / ['0','7']→[7]
    受け取り元:  12 関数が存在する。$_GET / $_POST / $_REQUEST に値を入れて get_ / post_ / request_ の各型が上と同じ規則で返す
- 想定のコミット単位: M1 は「feat(helpers): リクエスト値を型ごとに取り出すヘルパーを足す」と「docs: リクエスト値の取り出し方の決まりを書く」。M2 は「refactor(helpers): getv() の空の値の特例をやめ、GET と POST の扱いをそろえる」。M3〜M6 は「refactor(<範囲>): リクエスト値を型つきのヘルパーで取り出す」をサブディレクトリ単位で。M7 は「ci(security-lint): リクエスト値を確かめずに文字列関数へ渡す書き方を検出する」。

## Interfaces and Dependencies

- 追加する関数（`manager/includes/helpers.php`）: `to_strvar` / `to_boolvar` / `to_choicevar` / `to_idsvar` と、`get_` / `post_` / `request_` を付けた `strvar` / `boolvar` / `choicevar` / `idsvar`。既存の `*_intvar()` / `to_intvar()` / `abort_if_invalid_id()` の動きは変えない。
- 動きが変わる関数: `getv()`（空の値をそのまま返すようになる）。同梱外のスニペットやプラグインが `getv()` の空の値の特例に頼っている場合は動きが変わるため、リリースノートに書く（Issue #1059 に `仕様変更` のラベルを付けてある）。
- 依存: なし。PDO 移行（`.agent/plans/2026-07-08-pdo-migration-dbapi-refactor.md`）とは独立に進められるが、同じ processor を触るので、並行するときはマイルストーンの範囲が重ならないようにする。
