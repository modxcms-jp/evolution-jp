# ExecPlan: 保存後の戻り先と再読み込みの引数を読める名前にする（Issue #1277）

## Purpose / Big Picture

管理画面で保存したあとに「どの画面へ戻るか」「ツリーやメニューを読み直すか」を決める引数（`stay`・`mode`・`r`・`dv`）を、数字の暗号ではなく、URL やフォームを見ただけで意味のわかる名前と値（`after_save=stay`、`mode=new`、`refresh=tree` など）にする。保存後の行き先のキーは、旧名の `stay`（管理ユーザーは `save_action`）から `after_save` に改める。あわせて、戻り先を決める処理を 1 つのクラスに集め、画面ごとに書き写された分岐（約 50 か所）をなくす。旧い名前と値の読み替えは残さない。

## Progress

- [ ] (2026-11-08) M0: 着手前の確認（#1278・#1285 のマージ状況、取りこぼしの grep、main での戻り先の記録）
- [ ] M1: 仕組みの導入。`EvoFormFlow` を作り、要素 6 種・Web ユーザー・ロールの processor と差し戻しを移す（送る値は変えない）
- [ ] M2: 保存後の行き先を `after_save=new|stay|close` にそろえる（旧名の `stay`・`save_action` を置き換える。要素・文書・下書き・管理ユーザー・Web ユーザー）
- [ ] M3: 新規か更新かの `mode` を `new` / `edit` / `profile` にする
- [ ] M4: `r` を `refresh` にし、`dv` を消す。再読み込みの受け口を `EvoShell.refresh()` 1 つにする
- [ ] M5: テーブルの最適化・中身の削除の戻り先 `mode` を `from` にする（#1278 のマージ後）
- [ ] M6: ドキュメント・リリースノート・最終確認

## Surprises & Discoveries

- 2026-11-08: 管理ユーザーの新規作成では、画面の選択欄が `save_action`（`next` / `stay` / `close`）を送るのに、`newUser()`（`manager/processors/permission/save_user.functions.php:566`）は `stay` を読んでいる。そのため、何を選んでも一覧（`a=75`）へ戻る。`stay=2` の経路も `a=11&id=…`（新規の画面に id を付けたもの）になっていて、編集の画面（`a=12`）ではない。どちらも、値をそろえると直る。
- 2026-11-08: 文書（`manager/actions/document/mutate_content/functions.php:823` の `get_strvar('stay') === '2'`）と管理ユーザー（`manager/actions/permission/mutate_user/tpl/javascript.php:76` の `get_intvar('stay') === 2`）の「タブを覚える」判定は、戻ってくる値（文書は `stay=stay`、ユーザーは `save_action=stay`）と合っておらず、「この画面」で保存しても今のタブを覚えない。要素の画面だけが効いている。
- 2026-11-08: `manager/index.php:185` は、POST の `stay` が `new` 以外なら何でもセッション `saveAfter` に記録する。これを読むのは文書の画面（`action_buttons.php:79`）だけ。今は要素の保存が `1`・`2` を書き込んでも文書側で無視されるが、要素の値を文書と同じ名前（この画面は `stay`）にそろえると、チャンクを「この画面」で保存しただけで文書の画面の既定の選択が変わる。
- 2026-11-08: プロフィール（`a=74`、`manager/actions/permission/mutate_user_pf.dynamic.php:154`）にも「更新 + 」の選択欄があるが、保存（`save_user.functions.php:742`）は選択を見ずに常にダッシュボードへ戻る。
- 2026-11-08: Web ユーザーの入力エラーの差し戻し（`save_web_user.processor.php:546` の `webAlert()`）は `stay` を引き継がない。要素の差し戻し（`ManagerAPI::redirectToElementForm()`）は引き継ぐ。
- 2026-11-08: `dv` は受け取り（`manager/actions/wait.static.php:10`）だけがあり、送る箇所が無い。
- 2026-11-08: `r` は 2 か所で受けている。フルページの読み込みでは `manager/actions/header.inc.php:132` の `doRefresh(r)` → `mainMenu.reloadPane(r)`（`manager/frames/menu.php:143`）、シェルの画面遷移では `manager/media/script/shell.js:410` の `handleRefreshParam()`。値の意味の表が 2 つあり、`r=1` と `r=3` はフルページでだけ動きが違う（1 は開閉状態を保存してから読み直し、3 はそのまま読み直し。シェル側はどちらも `EvoShell.reloadTree()`）。さらに `manager/actions/tool/bkmanager.static.php:138` も `doRefresh()` を呼ぶので、バックアップの画面ではフルページで 2 回読み直す。フルページの読み込みでは、ツリーとメニューはその応答で描き直したばかりなので、`doRefresh()` の読み直しは結果を変えない。
- 2026-11-08: 管理ユーザーの保存（`saveUserSettings()`、`save_user.functions.php:104`）は、POST の項目のうち除外の一覧（`stay`・`mode`・`save_action` など）に無いものをすべてユーザー設定として保存する。フォームの項目の名前を変えるときは、この一覧に載っている名前にすること。保存後の行き先のキーを `after_save` にしたので（Decision Log）、M2 でこの一覧に `after_save` を足す（Concrete Steps 14）。
- 2026-11-08: `manager/includes/migrations/00000000_baseline.php:356` は、除外の一覧に無かったころにユーザー設定として保存された `save_action` などの行を消している。`after_save` を除外の一覧に入れ忘れると同じことが起きるので、M2 で必ず足す。ベースラインのマイグレーションは過去の掃除なので変えない。
- 2026-11-08: 文書の画面は、テンプレートやエディタを切り替えると、同じ画面の番号（`a=4` など）へフォームをそのまま POST し直す（`manager/media/style/common/jscripts.tpl:155`）。選択欄の行き先（旧名 `stay`、M2 から `after_save`）は GET（保存後の戻り）と POST（この再表示）の両方から読む必要がある。
- 2026-11-08: 文書の新規の画面では「設定」タブの種類（`fieldType`）で文書とウェブリンクを切り替えられる。今は差し戻し先を送られた `mode`（4 か 72）で決めているので、`a=4` で種類をウェブリンクに変えて差し戻されると `a=4` に戻る。
- 2026-11-08（メイン）: 上の「管理ユーザーの新規作成」「タブを覚えない」「プロフィールの選択欄」「Web ユーザーの差し戻し」の 4 件は、利用者に見える不具合なので #1285 に切り出し、#1277 より先に直す（本人の判断）。プロフィールの選択欄は #1285 で外す。M1 の「戻り先が main と同じ」は、#1285 のマージ後の main を基準にする。
- 2026-11-08（メイン）: #1278（PR #1286）では、`optimize_table.processor.php` の戻り先の `&s=4` は受け取る側が無かったので `tab` に改名せず削除した。`t` / `u` は `table` / `truncate`。
- 2026-11-08: 入力ファイル（メインが書き出した 1277-input.txt）の Issue 本文の節は空だった。この計画は、呼び出し元が伝えた Issue の要点（戻り先を決める仕組みを 1 か所にまとめ、読める名前と値にする。旧名・旧値の読み替えは残さない）に基づく。M0 で Issue 本文と突き合わせる。

## Decision Log

- 2026-11-08 / Claude（planner）: 戻り先を決める仕組みは、新しいクラス `EvoFormFlow`（`manager/includes/form_flow.class.inc.php`）に置く。`manager/includes/extenders/ex_managerapi.php` の先頭で `require_once` し、`manager()` が使える場所ならどこでも使えるようにする。
  理由: 「どの種類の編集画面が何番か」「保存後にどこへ戻るか」「どの値を受けるか」を 1 つの表と数個の関数にまとめたい。`ChildDocumentListState`（`manager/includes/controls/child_document_list_state.class.php`、一覧の画面と processor が同じ規則を使うための静的クラス）と、`EvoSettingsForm`（`manager/includes/settings_form.class.inc.php`）と同じ形。
  不採用: `ManagerAPI` のメソッドにする案は、`ManagerAPI` がすでに 1,100 行を超えて責務が混ざっているので採らない（既にある `redirectToElementForm()` / `requireElementName()` は残し、中身を `EvoFormFlow` に任せる）。`helpers.php` の関数にする案は、グローバルな名前を増やす（#560 で減らしている）ので採らない。`controls/` は表などの画面の部品の置き場なので、`includes/` の直下にする。
- 2026-11-08 / 本人の判断: 保存後の行き先のキーは `after_save` にし、値は `new`（続けて新規作成）・`stay`（この画面＝続けて編集）・`close`（閉じる）にする。文書の下書きの `save_draft` / `save_standby` は、文書だけが追加で受ける値として残す。旧名の `stay`（要素・Web ユーザー・文書）と管理ユーザーの `save_action`（`next` / `stay` / `close`）は `after_save` に一本化する。
  理由（本人の指摘）: 「とどまる」は行き先の値の 1 つで、キーの名前ではない。
  不採用: 当初の計画の「キーは旧名の `stay` を残し、値を `new` / `edit` / `close` にする」案。
  選択欄の `id="stay"` は、テーマの CSS（`select#stay`、7 テーマ）と未保存の検知の除外（`header.inc.php:120`）が使っているので変えない。そのため選択欄は `name="after_save" id="stay"` と、name と id が食い違う（id は見た目と検知のための目印で、送る値の名前ではない）。
- 2026-11-08 / Claude（planner）: 新規か更新かの `mode` は `new`・`edit`、プロフィールだけ `profile`。`r` は `refresh` にし、値は `tree`（旧 1 と 3）・`menu`（旧 2）・`all`（旧 9、ツリーとメニュー）・`page`（旧 10、管理画面ごと読み直す）。`dv` は送る箇所が無いので消す。2026-11-08 / 本人の判断: これらの値の名前と、最適化の戻り先の `from=sysinfo|bkmanager`（M5）は計画どおりで確定。
- 2026-11-08 / Claude（planner）: `EvoFormFlow` の行き先を扱うメソッドの名前は、キーに合わせて `AfterSave` を含める（`postedAfterSave()` / `requestedAfterSave()` / `afterSaveOptions()` / `documentAfterSaveDefault()` / `rememberDocumentAfterSave()`）。行き先の値の `stay` は、画面の表（`SCREENS`）では編集の画面（`edit`）に当たる。`mode` の `edit` と行き先の `stay` は別の語彙なので、`afterSaveUrl()` の中で対応づける。
- 2026-11-08 / Claude（planner）: 段取りは「内部の語彙を先にそろえ（M1）、送る値はあとで変える（M2〜M5）」。M1 では processor の中は行き先を `new` / `stay` / `close`、`mode` を `new` / `edit` で分岐し、URL とフォームに出すキーと値だけを今のもの（キーは旧名の `stay`、値は数字）に読み替える。M1 の終わりに、すべての経路の戻り先が main と同じであることを確かめられる。読み替えは M2・M3 で消すので、リリースには残らない。
- 2026-11-08 / Claude（planner）: 文書（新規・ウェブリンク・更新・下書き）の保存後の戻り先は、親や下書きの有無で変わるので `EvoFormFlow` の表には入れず、`save_resource.processor.php` の `goNextAction()` と `save_draft_content.processor.php` に残す。値の読み取り（`EvoFormFlow::postedAfterSave()`）と URL の組み立て（`EvoFormFlow::url()`）は `EvoFormFlow` を使う。
- 2026-11-08 / Claude（planner）: 文書の新規の差し戻し先（4 か 72）は、`mode=new` と送られた種類（`type` が `reference` なら 72）から決める。`a=4` で種類をウェブリンクに変えて差し戻されたときだけ、戻り先が 4 から 72 に変わる（送った内容に合った画面になる）。2026-11-08 / 本人の判断: 72 に戻るのでよい。要確認: 差し戻した入力が `a=72` の画面で正しく復元されること（Validation の 文書-7）。
- 2026-11-08 / Claude（planner）: 文書の「保存後」の選択の記憶（セッション `saveAfter`）は、`manager/index.php` から文書の保存の 2 つの processor（`save_resource` と `save_draft_content`）の先頭に移し、`EvoFormFlow::rememberDocumentAfterSave()` / `documentAfterSaveDefault()` で読み書きする。要素などの保存で文書の既定の選択が変わらないようにするため。`new` は今と同じく記憶しない。
- 2026-11-08 / Claude（planner）: 再読み込みの受け口は `shell.js` の `EvoShell.refresh(target)` 1 つにする。`handleRefreshParam()` はこれを呼ぶだけにし、`header.inc.php` の `doRefresh()`、`bkmanager.static.php:138` の重複呼び出し、`menu.php` の `reloadPane()` を消す。フルページの読み込みではツリーとメニューを同じ応答で描き直しているので、読み直しを省いても表示は変わらない。`refresh=page` だけは `wait.static.php` が自分で扱う（今の `r=10` と同じ）。2026-11-08 / 本人の判断: `top.mainMenu.reloadPane()` と `doRefresh()` は名前を残さずに消し、`EvoShell.refresh()` に一本化して、リリースノートに書く。
- 2026-11-08 / Claude（planner）: プラグインに渡すイベントの引数の `mode`（`'new'` / `'upd'`、`OnBeforeDocFormSave` など約 35 か所）は変えない。リクエストの引数ではなく、プラグインとの約束（公開 API）なので。`DocAPI::setValue($form_v, $mode)` の `$mode`（画面の番号 4・72・27）も、processor の中で番号に直して渡す内部の値なので変えない。
- 2026-11-08 / Claude（planner）: `manager/processors/db/optimize_table.processor.php` の `mode`（戻り先の画面の番号 53 か 93）は、この Issue に含める（M5）。名前を `from`、値を `sysinfo` / `bkmanager` にし、processor の中で 53 / 93 に直す。#1278 で同じファイルの `t` / `u` / `s` が `table` / `truncate` / `tab` に変わるので、#1278 のマージ後に main を取り込んでから行う。受け取りのヘルパー（`get_` / `request_`）は #1278 が `table` などに使ったものに合わせる。
- 2026-11-08 / Claude（planner）: 値を読むヘルパー。processor（保存は POST だけ）は `post_choicevar()`。画面の選択欄とタブの記憶は、保存後の戻り（GET）と文書の再表示（POST）の両方から来るので `request_choicevar()`（今も要素の画面は `request_intvar('stay')`）。許可していない値は、`after_save` なら `close`、`mode` なら空文字（processor が拒否する）。
- 2026-11-08 / Claude（planner）: 選択欄の `<option>` に付いている `id="stay1"`〜`stay4` は付けない。`stay` の grep（1277-input.txt）で、PHP の出力以外に参照が無いことを確かめた。
- 2026-11-08 / Claude（planner）: PR は 1 つにし、マイルストーンごとにコミットする（ブランチは `refactor/1277-readable-return-params`、`gh issue develop` で #1277 に紐づけ済み）。送る値の変更は、外から送るコードにとってどれも互換を壊すので、同じリリースにまとめて入れる。M1 は振る舞いを変えないコミットとして、差分を読むときの区切りになる。レビューが重くなったら、M4・M5 を、このブランチを base にした別の PR に分けてよい。2026-11-08 / 本人の判断: PR 1 つ・マイルストーンごとのコミットで確定。
- 2026-11-08 / 本人の判断: 管理ユーザーの「次の作成」は、今と同じく `new_user` の権限が無くても出す（要素は権限で出し分けているが、ここでは揃えない）。
- 2026-11-08 / 本人の判断: プロフィール（`a=74`）の「更新 + 」の選択欄は #1285 で外す。M2 ではプロフィールの選択欄を扱わない。
- 2026-11-08 / Claude（planner）: 管理ユーザーの保存の除外の一覧（`saveUserSettings()`）は、旧名の `stay` と `save_action` を `after_save` に置き換える。どのフォームも旧名を送らなくなるので、旧名は一覧に残さない（旧名の読み替えを残さない方針と同じ）。選択欄の `id="stay"` は POST されないので一覧に要らない。

## Outcomes & Retrospective

## Context and Orientation

用語:

- **画面の番号（a）**: 管理画面は `index.php?a=番号` で画面を切り替える。例: チャンクの新規作成は 77、編集は 78、要素の一覧は 76。
- **processor**: 保存などの処理だけを行い、終わったら `header('Location: …')` で次の画面へ移す PHP（`manager/processors/`）。画面は `manager/actions/`。
- **「更新 + 」の選択欄**: 編集画面の保存ボタンの横にある選択欄（今は `<select id="stay" name="stay">`、M2 で name だけ `after_save` にする）。保存後に「次の作成」（続けて新規作成）・「この画面」（続けて編集）・「閉じる」（一覧へ）のどれにするかを選ぶ。
- **差し戻し**: 名前の重複などの入力エラーで、入力をセッションに残して（`manager()->saveFormValues(画面の番号)`）編集画面へ戻すこと。残した入力は、戻った画面が同じ番号で読み出す。
- **シェル**: 管理画面のメニュー・ツリー・本文を 1 ページにまとめた仕組み（`manager/media/script/shell.js`）。画面の移動は本文だけを fetch で差し替え、processor のリダイレクト先の URL を見て、ツリーやメニューを読み直す。

対象の箇所（行番号は origin/main ae7cc86cf 時点）。

旧名の `stay`（保存後の行き先。M2 で `after_save` にする）:

| 区分 | ファイル:行 | 今の値 |
| --- | --- | --- |
| 選択欄（文書） | `manager/actions/document/mutate_content/action_buttons.php:78-104` | `new` / `stay` / `close` ＋ 下書きの `save_draft` / `save_standby`。既定はセッション `saveAfter` |
| 選択欄（要素） | `manager/actions/element/mutate_htmlsnippet.dynamic.php:172-180`、`mutate_module.dynamic.php:175-183`、`mutate_plugin.dynamic.php:293-299`、`mutate_snippet.dynamic.php:124-130`、`mutate_templates.dynamic.php:84-88`、`mutate_tmplvars.dynamic.php:339-345` | `1` / `2` / 空 |
| 選択欄（ユーザー） | `manager/actions/permission/mutate_user/functions.php:112-175`（name は `save_action`）、`mutate_user_pf.dynamic.php:154-160`、`mutate_web_user.dynamic.php:262-270` | `next` / `stay` / `close`、`1` / `2` / 空 |
| タブの記憶 | `mutate_content/functions.php:823`、`mutate_htmlsnippet.dynamic.php:351`、`mutate_module.dynamic.php:496`、`mutate_plugin.dynamic.php:592`、`mutate_snippet.dynamic.php:336`、`mutate_templates.dynamic.php:312`、`mutate_user/tpl/javascript.php:76`、`mutate_user_pf.dynamic.php:354`、`mutate_web_user.dynamic.php:688` | `remember_last_tab` が 2 か `stay` が 2 |
| 受け取りと戻り先 | `manager/processors/chunk/save_htmlsnippet.processor.php:128-135, 238-245`、`snippet/save_snippet.processor.php:97-100, 142-145`、`template/save_template.processor.php:76-79, 123-126`、`tmplvars/save_tmplvars.processor.php:96-99, 194-197`、`plugin/save_plugin.processor.php:93-96, 156-159`、`module/save_module.processor.php:97-100, 153-158`、`permission/save_web_user.processor.php:40, 210-222, 439-443, 468-472`、`permission/save_user.functions.php:566-578, 740-776`（139 は設定の除外の一覧）、`document/save_resource.processor.php:75, 150, 408-411, 860-888`、`document/save_draft_content.processor.php:71-97` | 上の値 |
| 差し戻しで引き継ぐ | `manager/includes/extenders/ex_managerapi.php:106-110`（`redirectToElementForm()`） | `1` / `2` |
| 選択の記憶 | `manager/index.php:185-186` | `new` 以外を `saveAfter` へ |
| 変えないもの | `manager/actions/header.inc.php:120`（`#stay` を未保存の検知から外す）、各テーマの `style.css` の `select#stay`（7 か所）、言語ファイルの `stay` / `stay_new`（文言のキー） | — |

`mode`（新規か更新か。値は画面の番号）:

| 区分 | ファイル:行 | 今の値 |
| --- | --- | --- |
| 送る（hidden） | `mutate_content/_template.tpl:14` と `mutate_content/functions.php:814`（`manager()->action`）、`mutate_htmlsnippet.dynamic.php:159`、`mutate_module.dynamic.php:162`、`mutate_plugin.dynamic.php:280`、`mutate_snippet.dynamic.php:113`、`mutate_templates.dynamic.php:73`、`mutate_tmplvars.dynamic.php:323`（いずれも `request_intvar('a')`）、`permission/mutate_role.dynamic.php:110`、`permission/mutate_user/tpl/form.php:13`、`permission/mutate_user_pf.dynamic.php:138`（固定の 74）、`permission/mutate_web_user.dynamic.php:250` | 画面の番号 |
| 受ける | `save_htmlsnippet:11-13, 62, 81-82, 137`（77/78）、`save_snippet:10-12, 61`（23/22）、`save_template:10-12, 35`（19/16）、`save_tmplvars:11-13, 35`（300/301）、`save_plugin:12-14, 36, 81, 144, 170-184`（101/102）、`save_module:13-15, 40`（107/108）、`save_role:12, 32-34, 55, 63`（38/35）、`save_user.functions.php:9, 15, 27, 298-300, 742, 749, 771` と `save_user.processor.php:44-76`（11/12/74）、`save_web_user:39, 66, 485, 549-551`（87/88）、`save_resource:14, 1000-1015`（4/72/27） | 画面の番号 |
| 番号を渡す呼び出し | 各要素の processor の `manager()->requireElementName(…, 新規の番号, 編集の番号, $id)` と `manager()->redirectToElementForm(…)`（チャンク・プラグイン・テンプレート・TV で確認済み。スニペット・モジュールは M0 で確認） | 画面の番号 |

`mode`（別の意味。対象外）: イベントの引数（`'mode' => 'new'` / `'upd'`、processor と `assets/plugins/qm/qm.inc.php`）、`manager/actions/document/revision.dynamic.php:20`（一覧の絞り込み）、`manager/actions/tool/bkmanager.static.php:15-20, 157, 290, 324, 346` と `manager/processors/backup/restore.processor.php:15-31`（バックアップの操作の種類）、`manager/media/browser/evo/api.php:959` と `js/imageEditor.js:433`（コピーか上書きか）、`mutate_htmlsnippet.dynamic.php:160` の `changeMode`、`save_settings.processor.php:296` の変数名、`evo()->doc->mode`（`normal` / `draft`）、DocumentParser・DBAPI・修飾子・Ditto・TinyMCE 7 などの関数の引数。

`mode`（戻り先の画面の番号。M5）: `manager/processors/db/optimize_table.processor.php:45`。送るのは `manager/actions/report/sysinfo.static.php:289`（53）と `manager/actions/tool/bkmanager.static.php:231, 246`（93）。

`r` と `dv`（再読み込み）:

| 区分 | ファイル:行 |
| --- | --- |
| 受ける | `manager/actions/header.inc.php:132-133`（`doRefresh()`、161-172 が本体）、`manager/frames/menu.php:143-160`（`reloadPane()`、206 で `mainMenu` に公開）、`manager/media/script/shell.js:409-428`（`handleRefreshParam()`）、`manager/actions/tool/bkmanager.static.php:138`、`manager/actions/wait.static.php:8-16`（`r=10` と `dv=1`） |
| `r=1`（ツリー） | `manager/includes/controls/child_document_list_state.class.php:78`、`document/delete_content:84`、`delete_draft_content:31`、`duplicate_content:54, 57`、`move_document:158, 165`、`publish_content:64`、`publish_draft_content:73`、`remove_content:78`、`save_draft_content:80, 92, 97`、`save_resource:866, 871, 877, 883, 887`、`undelete_content:72`、`unpublish_content:57`、`unpublish_draft_content:28, 30` |
| `r=2`（メニュー） | `module/delete_module:44`、`duplicate_module:59`、`save_module:100, 102, 156, 158` |
| `r=3`（ツリー） | `permission/save_user.functions.php:570, 575, 578, 743, 748, 757, 762` |
| `r=9`（両方） | `backup/restore.processor.php:48` |
| `r=10`（全体） | `save_settings.processor.php:30` |
| `dv=1` | 送る箇所なし |

DocumentParser の `executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()` には影響しない（管理画面の画面と processor だけの変更）。キャッシュの消し方（各 processor の `clearCache()`）も変えない。

前提: 検証は `compose.yml` の Docker 環境（`app` サービス）で行う。main の動作要件は PHP 7.4 以上なので、`match`・nullsafe・union 型などの PHP 8 の構文は使わない。

## Plan of Work

`EvoFormFlow` は、`final class` に静的メソッドと 1 つの表を持たせた小さなクラスにする。表 `SCREENS` は、編集画面の種類ごとに画面の番号を持つ。

    chunk    => new 77,  edit 78,  close 76
    snippet  => new 23,  edit 22,  close 76
    template => new 19,  edit 16,  close 76
    tmplvar  => new 300, edit 301, close 76
    plugin   => new 101, edit 102, close 76
    module   => new 107, edit 108, close 106
    role     => new 38,  edit 35,  close 86
    user     => new 11,  edit 12,  profile 74, close 75
    web_user => new 87,  edit 88,  close 99
    document => new 4,   edit 27
    weblink  => new 72,  edit 27

メソッド（どのマイルストーンで足すか）:

- `screen(string $kind, string $mode): int`（M1）: 種類と `mode` の画面の番号。`saveFormValues()` の鍵と差し戻し先に使う。`mode` が空なら `close` の番号。
- `url(array $query): string`（M1）: `'index.php?' . http_build_query($query, '', '&')`。エスケープ前の URL を返す（HTML に出すときは呼び出し側で `hsc()`）。
- `postedMode(string $kind): string`（M1）: POST の `mode` を、その種類の表にある値（`close` 以外）だけ受け、それ以外は空文字。M1 では画面の番号で受けて名前に直し、M3 で名前をそのまま受ける。
- `mode(string $kind): string`（M3）: 画面側。今の `manager()->action` が表のどの値か（`new` / `edit` / `profile`）。どれでもなければ空文字。
- `postedAfterSave(array $extra = []): string`（M1）: 保存後の行き先。POST の `after_save` を `new` / `stay` / `close` と `$extra`（文書の下書きの `save_draft` / `save_standby`）だけ受け、それ以外は `close`。M1 では旧名のキー `stay` の `1` / `2` で受けて `new` / `stay` に直し（M1 で使うのは要素と Web ユーザーだけ。文書と管理ユーザーは M2 から使う）、M2 でキー `after_save` の値をそのまま受ける。
- `afterSaveUrl(string $kind, int $id, string $afterSave, array $extra = []): string`（M1）: 保存後の URL。`new` は新規の画面（`after_save=new` 付き）、`stay` は編集の画面（`id` と `after_save=stay` 付き）、それ以外は `close` の画面。`$extra`（`refresh` など）はどの場合にも足す。M1 では旧名のキー `stay` と `1` / `2` に直して出し、M2 で `after_save` と値の名前のまま出す。
- `formUrl(string $kind, string $mode, int $id = 0): string`（M1）: 差し戻しの URL。`mode` が `edit` なら `id` を付け、`postedAfterSave()` が `close` でなければ行き先を引き継ぐ（M2 からは `after_save`）。
- `requestedAfterSave(array $extra = []): string`（M2）: 画面側。GET か POST の `after_save`。許可していない値は空文字。
- `rememberTab(): bool`（M2）: `(int)config('remember_last_tab') === 2 || requestedAfterSave() === 'stay'`。
- `afterSaveOptions(string $selected, bool $withNew, array $extra = []): string`（M2）: 選択欄の `<option>` の HTML（エスケープ済み）。値は `new`（「次の作成」、`$withNew` のときだけ）・`stay`（「この画面」）・`$extra`（値 => 文言）・`close`（「閉じる」）の順。`$selected` が出した選択肢に無ければ「閉じる」を選ぶ。
- `documentAfterSaveDefault(): string` / `rememberDocumentAfterSave(string $afterSave): void`（M2）: 文書の選択の既定（セッション `saveAfter`、無ければ `close`）の読み書き。`new` は記憶しない。

`ManagerAPI::requireElementName($name, $typeLabel, string $kind, $id = 0)` と `redirectToElementForm($message, string $kind, $id = 0)` は、画面の番号 2 つの代わりに種類を受け、中で `EvoFormFlow::screen()` と `formUrl()` を使う。

各マイルストーンは、送る側と受ける側を同じコミットで変え、コミットごとに管理画面が動く状態を保つ。

## Concrete Steps

作業ディレクトリはリポジトリのルート。git はワークツリーの隔離のため 1 コマンドずつ実行する（`cd` や `&&` でつながない）。

M0 着手前の確認

1. `gh issue view 1277 --comments` で Issue 本文を読み、この計画の範囲（Surprises の最後の項目）と食い違いが無いか確かめる。
2. `git log --oneline origin/main -20` で #1278 がマージ済みか確かめる。マージ済みなら `git merge origin/main` で取り込む（M5 はマージ後でないと始めない）。
3. 取りこぼしを探す。
       git grep -nE "requireElementName|redirectToElementForm" -- manager
       git grep -nE "reloadPane|doRefresh" -- manager assets install
       git grep -nE "\.mode\.value|\[name=.?mode|elements\[.mode.\]|\bstay[0-9]\b" -- manager assets install
   期待結果: 1 つ目は ex_managerapi.php の定義と 6 種の要素の processor。2 つ目は header.inc.php・menu.php・bkmanager.static.php・wait.static.php だけ（ほかにあれば M4 の置き換えの対象に加える）。3 つ目は `bkmanager.static.php:133`（バックアップの `mode`、対象外）と、この計画の表の PHP だけ。
4. 修正前の戻り先を記録する。main のコンテナで、Validation and Acceptance の表の操作を一通り行い、保存の応答の `Location`（ブラウザの開発者ツールのネットワーク）を控える。コンテナの起動と確認の手順は `adaptive-validation-runner` スキルに従う（管理画面へ curl するときは `Accept-Language` を付ける）。

M1 仕組みの導入（送る値は変えない）

5. `manager/includes/form_flow.class.inc.php` を作り、Plan of Work の表と、M1 のメソッド（`screen` / `url` / `postedMode` / `postedAfterSave` / `afterSaveUrl` / `formUrl`）を書く。クラスの docblock に、引数の名前と値の一覧（`after_save`・`mode`・`refresh`）を書き、ここを正とする（M1 の時点では、出すキーがまだ旧名の `stay` であることも書き、M2 で消す）。
6. `manager/includes/extenders/ex_managerapi.php` の先頭で `require_once MODX_CORE_PATH . 'form_flow.class.inc.php';` を読み、`requireElementName()` / `redirectToElementForm()` を種類で受ける形に直す（中の旧名の `postv('stay')` の判定は `EvoFormFlow::formUrl()` に移る）。
7. 要素の processor を、`$mode = EvoFormFlow::postedMode('chunk')` のように読み、`switch` の `case` を `'new'` / `'edit'` にし、保存後を `header('Location: ' . EvoFormFlow::afterSaveUrl('chunk', $id, EvoFormFlow::postedAfterSave()))` にする。モジュールは `$extra` に `['r' => 2]`（M4 で `refresh` にする）。`requireElementName()` / `redirectToElementForm()` の呼び出しは種類を渡す。
   編集対象: `manager/processors/chunk/save_htmlsnippet.processor.php`、`snippet/save_snippet.processor.php`、`template/save_template.processor.php`、`tmplvars/save_tmplvars.processor.php`、`plugin/save_plugin.processor.php`（`saveEventListeners()` の `$mode === 101` も `'new'` に）、`module/save_module.processor.php`。
8. `manager/processors/permission/save_web_user.processor.php` を同じ形にする（`saveWebUserMode()` は `EvoFormFlow::postedMode('web_user')`、`webAlert()` は `screen()` と `formUrl()` を使う。#1285 で行き先を引き継ぐようにした形を `formUrl()` に置き換える）。パスワードを表示する画面の「閉じる」のリンクも `afterSaveUrl()` を使う。
9. `manager/processors/permission/save_role.processor.php` の `mode` を `postedMode('role')` で読み、`'new'` / `'edit'` で分岐する。戻り先は今のまま `a=86`。
10. 構文を確かめる。
        docker compose exec app php -l manager/includes/form_flow.class.inc.php
        docker run --rm -v "$PWD":/app -w /app php:7.4-cli php -l manager/includes/form_flow.class.inc.php
    変更したファイルごとに同じことを行う。期待結果: `No syntax errors detected`。
11. Validation の「要素」「Web ユーザー」「ロール」の行を行い、`Location` のキーと値の組が M0 の記録と同じであることを確かめる（引数の並びは変わってよい）。
    コミット案: `refactor(manager): 保存後の戻り先を EvoFormFlow に集める`

M2 保存後の行き先を `after_save=new|stay|close` にそろえる

12. `EvoFormFlow` の `postedAfterSave()` / `afterSaveUrl()` / `formUrl()` から、旧名のキー `stay` と `1` / `2` の読み替えを消し、キー `after_save` で値の名前をそのまま受け・出す形にする。`requestedAfterSave()` / `rememberTab()` / `afterSaveOptions()` / `documentAfterSaveDefault()` / `rememberDocumentAfterSave()` を足す。docblock から旧名の注記を消す。
13. 要素・Web ユーザーの画面の選択欄を `<select id="stay" name="after_save">` にし、中身を `<?= EvoFormFlow::afterSaveOptions(EvoFormFlow::requestedAfterSave(), evo()->hasPermission('new_chunk')) ?>` の形にする（権限は画面ごとに今と同じもの）。id は `stay` のまま（テーマの CSS と未保存の検知の除外が使う。name と id が食い違うのは Decision Log のとおり）。タブの記憶を `EvoFormFlow::rememberTab() ? 'true' : 'false'` にする。プロフィール（`mutate_user_pf.dynamic.php`）は #1285 で選択欄が外れているので選択欄は扱わず、タブの記憶の判定が残っていれば `rememberTab()` にする（M0 で #1285 後の形を確かめる）。
    編集対象: `mutate_htmlsnippet` / `mutate_module` / `mutate_plugin` / `mutate_snippet` / `mutate_templates` / `mutate_tmplvars` / `mutate_web_user` / `mutate_user_pf`（いずれも `.dynamic.php`）。
14. 管理ユーザー: `manager/actions/permission/mutate_user/functions.php` の `saveOptions()` を `afterSaveOptions(requestedAfterSave(), true)` にし、`<select>` を `id="stay" name="after_save"` にする（旧名の `save_action` をやめる。「次の作成」は今と同じく権限で出し分けない）。`mutate_user/tpl/javascript.php:76` を `rememberTab()` に。`manager/processors/permission/save_user.functions.php` の `newUser()` と `updateUser()` を `postedAfterSave()` と `afterSaveUrl('user', …, ['r' => 3])` にする。同じファイルの `saveUserSettings()` の除外の一覧（`$ignore`、104 行付近）で、旧名の `stay` と `save_action` を `after_save` に置き換える（足さないと `after_save` がユーザー設定として保存される。Surprises の管理ユーザーの保存の項）。プロフィール（`saveUserMode()` が profile）の保存後は今と同じくダッシュボード（`a=2&r=3`）。
15. 文書: `action_buttons.php` の `ab_save()` を、選択欄を `<select id="stay" name="after_save">` にし、中身を `afterSaveOptions(requestedAfterSave(['save_draft', 'save_standby']) ?: documentAfterSaveDefault(), 新規の権限, 下書きの選択肢)` にする（下書きの文言は今のまま）。`mutate_content/functions.php:823` を `rememberTab()` に。`save_resource.processor.php` は先頭（権限の確認の直後）で `rememberDocumentAfterSave(postedAfterSave())` を呼び、`goNextAction()` の行き先を `postedAfterSave()` から取り（値は今と同じ `new` / `stay` / `close`。変わるのはキーだけ）、URL を `EvoFormFlow::url()` で組む。408-411 の差し戻しも `postedAfterSave()` を使う。`save_draft_content.processor.php` は `postedAfterSave(['save_draft', 'save_standby'])` にし、先頭で `rememberDocumentAfterSave()` を呼ぶ。`manager/index.php:185-186`（旧名の POST の `stay` を `saveAfter` へ記録する処理）を消す。
16. 構文確認（手順 10 と同じ）と、Validation の「要素」「ユーザー」「文書」の行。
    コミット案: `refactor(manager): 保存後の行き先を after_save=new・stay・close にそろえる`

M3 `mode` の値をそろえる

17. `postedMode()` から番号の読み替えを消し、`mode()` を足す。
18. 画面の hidden の `mode` を `EvoFormFlow::mode('chunk')` などにする（Context の「送る（hidden）」の行のすべて）。文書は `functions.php:814` を `EvoFormFlow::mode((int)manager()->action === 72 ? 'weblink' : 'document')` にする（下書きの画面では空になり、下書きの保存は `mode` を読まないので影響しない）。プロフィールは `profile`。
19. 文書の processor: `save_resource.processor.php` の `postedMode()` を、`EvoFormFlow::postedMode('document')` と、それを画面の番号に直す関数（`edit` は 27、`new` は送られた `type` が `reference` なら 72、それ以外は 4）に分ける。`saveFormValues()`・`formReturnUrl()`・`DocAPI::setValue()` には番号を渡す。管理ユーザーは `saveUserMode()` と `save_user.processor.php` の比較（11・12・74）を名前に。
20. 構文確認と、Validation の全行。
    コミット案: `refactor(manager): 新規か更新かの mode を new・edit にする`

M4 `r` を `refresh` にし、`dv` を消す

21. `shell.js` の `EvoShell` に `refresh(target)` を足す（`tree` は `reloadTree()`、`menu` は `reloadMenu()`、`all` は両方、`page` は `window.location.href = 'index.php'`、それ以外は何もしない）。`handleRefreshParam()` は `searchParams.get('refresh')` を渡すだけにする。
22. `header.inc.php` の 132-135 行と `doRefresh()`（161-172 行）、`bkmanager.static.php:138`、`menu.php` の `reloadPane()` と `mainMenu.reloadPane` を消す（M0 の手順 3 で見つけたほかの呼び出しは `EvoShell.refresh()` に置き換える）。
23. `wait.static.php` は `get_choicevar('refresh', ['page'], '')` が `page` なら `EvoShell.refresh('page');`、それ以外は今の `EvoShell.navigate("index.php?a=2");` にし、`dv` の分岐を消す。
24. 送る側の `r=` をすべて `refresh=` に置き換える（Context の表の `r=1`〜`r=10` の行。`r=1` と `r=3` は `tree`）。`child_document_list_state.class.php:78` は `'refresh' => 'tree'`。M1・M2 で `$extra` に入れた `['r' => 2]` / `['r' => 3]` も直す。
25. 構文確認と、Validation の「再読み込み」の行。
    コミット案: `refactor(manager): 再読み込みの引数を refresh=tree などにし、dv を消す`

M5 テーブルの最適化の戻り先（#1278 のマージ後）

26. `optimize_table.processor.php:45` を、`from` を `sysinfo` / `bkmanager` の選択肢で読み、53 / 93 に直して戻す形にする（選択肢に無ければダッシュボード `a=2`）。`sysinfo.static.php:289` と `bkmanager.static.php:231, 246` のリンクの `mode=' . $action` を `from=sysinfo` / `from=bkmanager` にする。リンクの組み立ては #1278 が直した形（引数名 `table` / `truncate`）に合わせる。
    コミット案: `refactor(manager): テーブルの最適化の戻り先を from で受ける`

M6 ドキュメントと最終確認

27. 旧い名前と値が残っていないことを確かめる。
        git grep -nE "[?&]r=[0-9]|'r' *=> *[0-9]|intvar\('r'\)|intvar\('dv'\)|searchParams\.get\('r'\)|reloadPane|doRefresh" -- manager assets install
        git grep -nE "v\('stay'|[?&]stay=|name=\"stay\"|save_action" -- manager assets install ':!manager/includes/migrations'
        git grep -nE "choicevar\('mode', \[[0-9]|name=\"mode\" value=\"<\?= *(\(int\) *)?(\\\$action|request_intvar)|name=\"mode\" value=\"74\"" -- manager
    期待結果: どれも出力なし。2 つ目は旧名のキー `stay` と `save_action` を探す（値としての `'stay'` や、言語ファイルの `$_lang['stay']`、`id="stay"` には当たらない形にしてある）。マイグレーションを外すのは、`manager/includes/migrations/00000000_baseline.php:356` が、過去にユーザー設定へ紛れ込んだ `save_action` の行を消すために名前を持っているから（過去の掃除なので変えない）。2026-11-08 の origin/main ae7cc86cf では 44 行当たる。
28. `assets/docs/architecture.md` の管理画面の処理の流れの節（無ければ近い節）に、「保存後の戻り先・新規か更新か・再読み込みの値は `EvoFormFlow`（`manager/includes/form_flow.class.inc.php`）と `EvoShell.refresh()` を正とする」と 2〜3 行で書く。`/doc-audit` を通す。
29. PR の本文に Interfaces and Dependencies の「リリースノートに書くこと」を載せる。

## Validation and Acceptance

Docker の検証環境（`adaptive-validation-runner` スキルの手順で起動）で、次の操作の戻り先（保存の応答の `Location`、または最後に表示された画面の URL）と画面の様子を確かめる。「main」の欄は M0 で記録した値と同じであること、「変更後」の欄のとおりになることを確かめる。`<新>` `<編>` `<閉>` は Plan of Work の表の番号。

要素（チャンク・スニペット・テンプレート・TV・プラグイン・モジュールのそれぞれ）:

| 操作 | main | 変更後 |
| --- | --- | --- |
| 新規で保存 + 閉じる | `a=<閉>` | 同じ |
| 新規で保存 + 次の作成 | `a=<新>&stay=1`、選択欄は「次の作成」 | `a=<新>&after_save=new`、選択欄は「次の作成」 |
| 新規で保存 + この画面 | `a=<編>&id=新しい id&stay=2`、タブを覚える | `a=<編>&id=…&after_save=stay`、タブを覚える |
| 更新で保存（3 種） | 上と同じ形（id は今の id） | 上と同じ形 |
| 名前の重複で差し戻し（この画面を選んで） | `a=<編>&id=…&stay=2`、入力が残る | `…&after_save=stay`、入力が残る |
| モジュールの保存・複製・削除 | 上に `r=2`、メニューのモジュールの一覧が更新される | `refresh=menu`、同じ |
| 画面の hidden の `mode`（開発者ツール） | 画面の番号 | `new` か `edit` |
| 選択欄（開発者ツール） | `<select id="stay" name="stay">`、値は `1` / `2` / 空 | `<select id="stay" name="after_save">`、値は `new` / `stay` / `close`。テーマの見た目（`select#stay`）が変わらず、選択を変えても未保存の警告が出ない |

ユーザー:

| 操作 | main | 変更後 |
| --- | --- | --- |
| 管理ユーザーを新規で保存 + 次の作成 / この画面 / 閉じる | 選択にかかわらず `a=75&r=3`（不具合。#1285 で直す） | `a=11&after_save=new` / `a=12&id=…&after_save=stay` / `a=75`、どれも `refresh=tree` |
| 管理ユーザーを更新で保存（3 種） | `a=11&r=3&save_action=next` / `a=12&id=…&r=3&save_action=stay` / `a=75&r=3` | `a=11&after_save=new` / `a=12&id=…&after_save=stay` / `a=75`、どれも `refresh=tree`。「この画面」でタブを覚える |
| 管理ユーザーを保存したあと、ユーザー設定の表（`user_settings`）を見る | `save_action` の行が無い | `after_save` の行も無い。`docker compose exec db mariadb -uuser -ppassword evodb -e "SELECT setting_name FROM modx_user_settings WHERE setting_name IN ('after_save','stay','save_action')"` が 0 行（接続情報と接頭辞は `compose.yml` の既定値。`.env` で変えていればそれに合わせる） |
| パスワードを画面に表示する保存（新規・更新） | 表示画面の「閉じる」が上の戻り先 | 同じ戻り先（新しい値） |
| プロフィールを保存 | `a=2&r=3` | ダッシュボード、`refresh=tree` |
| Web ユーザー（新規・更新 × 3 種、パスワード表示） | 要素と同じ形（87 / 88 / 99） | 要素と同じ形 |
| Web ユーザーのメールアドレスの誤りで差し戻し（この画面） | `a=88&id=…`（行き先なし。#1285 の後は旧名の `stay=2` 付き） | `a=88&id=…&after_save=stay` |
| ロールの新規・更新 | `a=86` | 同じ。`mode` に他の値を送ると保存しない |

文書:

| 操作 | main | 変更後 |
| --- | --- | --- |
| 文書-1 新規（親あり）で保存 + 閉じる / この画面 / 次の作成 | `a=120&id=親&r=1` / `a=27&id=…&r=1&stay=stay` / `a=4&pid=親&r=1&stay=new` | `r=1` を `refresh=tree`、旧名のキー `stay` を `after_save` にした同じ形（値の `stay` / `new` は今と同じ）。「この画面」でタブを覚える |
| 文書-2 ルート直下の文書を閉じるで保存 | `a=3&id=…&r=1` | `a=3&id=…&refresh=tree` |
| 文書-3 ウェブリンクを新規で保存 + 次の作成 | `a=72&pid=親&r=1&stay=new` | `a=72&pid=親&refresh=tree&after_save=new` |
| 文書-4 「この画面」で保存したあと、別の文書の新規の画面を開く | 選択欄が「この画面」 | 同じ |
| 文書-5 チャンクを「この画面」で保存したあと、文書の新規の画面を開く | 選択欄は前の文書の選択のまま | 同じ（チャンクの保存で変わらない） |
| 文書-6 エイリアスの重複で差し戻し（この画面） | `a=27&id=…&stay=stay` | `…&after_save=stay` |
| 文書-7 `a=4` で種類をウェブリンクに変え、エイリアスの重複で差し戻し | `a=4` に戻り、入力が残る | `a=72` に戻り、入力（種類はウェブリンク）が残る |
| 文書-8 テンプレートを切り替える（再表示） | 選択欄の選択が残る | 同じ |
| 文書-9 下書きを この画面 / 下書きを採用 / 閉じる で保存 | `a=131&id=…&stay=stay` / `a=133&id=…&r=1` / `a=3&id=…&r=1` | `after_save=stay` / `refresh=tree` にした同じ形 |
| 文書-10 子の一覧から公開・非公開・削除・削除の取り消し | `a=120&id=…&r=1&page=…`（並べ替え・検索語を保つ） | `refresh=tree` にした同じ形、ツリーが更新される |
| 文書-11 複製・移動・ゴミ箱を空にする | 複製は `a=3&id=新しい id&r=1`、移動は `a=120&id=…&r=1` か `a=2&r=1`、ゴミ箱は `a=7&r=1` からダッシュボード | `refresh=tree` にした同じ形 |

再読み込みとその他:

| 操作 | main | 変更後 |
| --- | --- | --- |
| グローバル設定を保存 | `a=7&r=10`、管理画面ごと読み直してダッシュボード | `a=7&refresh=page`、同じ |
| バックアップからの復元 | `a=93&r=9`、ツリーとメニューが更新される | `a=93&refresh=all`、同じ |
| `refresh=tree` 付きの URL をブラウザで再読み込み（フルページ） | ツリーが表示される | 同じ。コンソールにエラーが出ない |
| システム情報・バックアップの画面からテーブルを最適化 | `a=53&s=4` / `a=93&s=4`（#1278 後は `tab=4`） | `from=` で同じ画面のタブ 4 に戻る |

静的な確認: M6 の手順 27 の grep が出力なし。変更したすべての PHP ファイルで、`docker compose exec app php -l` と `php:7.4-cli` の `php -l` が `No syntax errors detected`。保存の操作のあと、`docker compose exec app php evo log:tail --lines=20` に PHP のエラーが無い。

## Idempotence and Recovery

コードの変更だけで、DB・設定・ファイルの置き場所は変えない。マイルストーンごとに 1 コミットにし、各コミットで送る側と受ける側をそろえるので、途中で止めても、どのコミットの時点でも管理画面は動く。問題が出たら、そのマイルストーンのコミットだけを revert する（M2〜M5 は前のマイルストーンに依存するので、戻すときは後ろから順に）。M1 は戻り先を変えないので、M1 だけを残して止めてもよい。セッションに残った古い `saveAfter` は、文書の値（`new` / `stay` / `close`）なら値が変わらないのでそのまま効き、要素が書き込んだ `1` / `2` は許可していない値として「閉じる」に読まれるだけで、エラーにならない。

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/1277
- ブランチ: `refactor/1277-readable-return-params`（`gh issue develop` で #1277 に紐づけ済み）。PR は未作成。
- 関連: #1278（`optimize_table.processor.php` の `t` / `u` / `s` などを読める名前にする。別の PR で進行中。`mode` は #1277 に残した）、#1070（`request_*var()` を `any_*var()` に改名する予定。`EvoFormFlow::requestedAfterSave()` もその対象になる）、#560（グローバルな名前を増やさない）。
- 想定コミット単位: M1〜M5 の各コミット案のとおり。M6 は `docs: 保存後の戻り先の決まりを書く`。
- 洗い出しの件数（2026-11-08、origin/main ae7cc86cf）: `stay` は 24 ファイル・約 45 か所（選択欄 10、タブの記憶 9、受け取りと戻り先の組み立て約 25。変えないもの 10 か所は別）。`mode`（新規か更新か）は送る 12 か所（11 ファイル）と受ける 12 ファイル。`mode`（戻り先）は受ける 1・送る 3。`r` は送る 37 か所（19 ファイル）と受ける 5 か所。`dv` は受ける 1・送る 0。

## Interfaces and Dependencies

リリースノートに書くこと（互換を壊す変更）:

- 編集画面から保存の processor へ送る保存後の行き先のキーが `after_save` になり、値は `new`（続けて新規作成）・`stay`（この画面）・`close`（閉じる）になる（文書の下書きは、ほかに `save_draft` / `save_standby`）。旧名のキー `stay`（要素・Web ユーザーの `1` / `2`、文書の `new` / `stay` / `close`）と、管理ユーザーの `save_action`（`next` / `stay` / `close`）は受けない。保存後の URL の行き先も `after_save=…` になる。選択欄の `id="stay"` は変わらないので、テーマの CSS の `select#stay` はそのまま効く。
- 新規か更新かを表す `mode` の値が、画面の番号（77 / 78 など）から `new` / `edit`（プロフィールは `profile`）になる。保存の processor へ直接 POST するコードは直す必要がある。`custom_tpl_dir` に置いた文書の画面のテンプレートは `[+mode+]` のままでよい。
- 保存後の URL の再読み込みの引数 `r`（1・2・3・9・10）が `refresh`（`tree` / `menu` / `all` / `page`）になる。`dv` は無くなる。古い URL をブックマークしていても、読み直しが起きないだけでエラーにはならない。
- JS の `top.mainMenu.reloadPane(n)` と、画面の `doRefresh(n)` が無くなる。ツリーやメニューを読み直すモジュールは `EvoShell.refresh('tree')` などを使う。
- テーブルの最適化・中身の削除（`a=54`）の戻り先の引数 `mode` が `from`（`sysinfo` / `bkmanager`）になる。
- `ManagerAPI::requireElementName()` / `redirectToElementForm()` の引数が、画面の番号 2 つから種類の名前（`'chunk'` など）に変わる。
- あわせて直る動き: 管理ユーザーの新規作成で「次の作成」「この画面」が効くようになる。文書と管理ユーザーで「この画面」のときにタブを覚える。要素などの保存で、文書の画面の「保存後」の既定の選択が変わらなくなる。Web ユーザーの差し戻しで選択が残る。
- 変えないもの: プラグインのイベントに渡す `mode`（`new` / `upd`）、選択欄の `id="stay"`、言語ファイルのキー。

依存:

- #1278 のマージ後に M5 を行う（同じファイルを触るため）。
- #1285 のマージ後に M1 を始める（同じ processor と画面を触り、M1 の比較の基準になるため）。「あわせて直る動き」のうち #1285 で直したものは、リリースノートでは #1285 側に書く。
- 要素・ユーザーの画面は `manager()` が読み込まれた状態で動く前提（`EvoFormFlow` は `ex_managerapi.php` が読み込む）。

## 未決事項

なし（2026-11-08 に本人がすべて決めた。Decision Log の「本人の判断」を参照）。
