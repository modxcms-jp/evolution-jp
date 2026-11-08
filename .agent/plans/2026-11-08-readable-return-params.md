# ExecPlan: 保存後の戻り先と再読み込みの引数を読める名前にする（Issue #1277）

## Purpose / Big Picture

管理画面で保存したあとに「どの画面へ戻るか」「ツリーやメニューを読み直すか」を決める引数（`stay`・`mode`・`r`・`dv`）を、数字の暗号ではなく、URL やフォームを見ただけで意味のわかる名前と値（`after_save=stay`、`mode=new`、`refresh=tree` など）にする。保存後の行き先のキーは、旧名の `stay`（管理ユーザーは `save_action`）から `after_save` に改める。あわせて、戻り先を決める処理を 1 つのクラスに集め、画面ごとに書き写された分岐（約 50 か所）をなくす。旧い名前と値の読み替えは残さない。

## Progress

- [x] (2026-11-09) M0: 着手前の確認。Issue 本文と範囲が一致。#1278・#1285 は main にマージ済み、#1279（PR #1293）をブランチに取り込み済み。取りこぼしの grep で `refresh_site.dynamic.php` の `doRefresh(1)` と `assets/docs/manager-shell.md` を足した。修正前の戻り先を 308 行記録した（基準 0f915fda1。要約は Artifacts and Notes）
- [x] (2026-11-09) M1: `EvoFormFlow` を作り、要素 6 種・Web ユーザー・ロールの processor と差し戻しを移した（2676fc4d2）。送る値は変えず、M1 の範囲 131 行が修正前の記録と完全一致、範囲外も差なし、PHP・JS のエラー 0。その後 main を取り込み（376cce09c）
- [x] (2026-11-09) M2: 保存後の行き先を `after_save=new|stay|close` にそろえる（旧名の `stay`・`save_action` を置き換える。要素・文書・下書き・管理ユーザー・Web ユーザー）。タブの記憶を設定に従わせる（bf25d6698、自己レビューの低の手当て ad1965086。修正前の記録と旧名→新名の置き換えで突き合わせて一致し、違いは計画どおりの変更だけ。tester の独立の確認 7 項目すべて PASS: 差し戻しの入力値と選択 12 件、選択欄 19 画面・テーマ 7 種・権限による出し分け、未保存の警告、タブの記憶 30 件、文書の既定の記憶、user_settings に行ができない、エラー 0。自己レビューは高・中なし）
- [x] (2026-11-09) M3: 新規か更新かの `mode` を `new` / `edit` / `profile` にする（d92153e03、自己レビューの低の手当て 2c3538fcc。M2 の記録と突き合わせ、保存後の Location と差し戻しの URL は文書-7（a=4 で種類をウェブリンクにして差し戻すと a=72）を除いて同じ、hidden の mode の違いは計画どおり。tester の確認 6 項目すべて PASS: 各画面の hidden の mode、文書-7 の入力の復元、文書・ウェブリンク・下書きの保存と採用の DB の値、プロフィールと管理ユーザー、旧い番号の拒否、エラー 0。自己レビューは高なし、中はリリースノートの文案の漏れで Interfaces に反映）
- [ ] M4: `r` を `refresh` にし、`dv` を消す。再読み込みの受け口を `EvoShell.refresh()` 1 つにする
- [ ] M5: テーブルの最適化・中身の削除の戻り先 `mode` を `from` にする（#1278 はマージ済み）
- [ ] M6: ドキュメント・リリースノート・最終確認

## Surprises & Discoveries

- 2026-11-08: 管理ユーザーの新規作成では、画面の選択欄が `save_action`（`next` / `stay` / `close`）を送るのに、`newUser()` は `stay` を読んでいた。そのため、何を選んでも一覧（`a=75`）へ戻った。`stay=2` の経路も `a=11&id=…`（新規の画面に id を付けたもの）になっていて、編集の画面（`a=12`）ではなかった。→ 2026-11-09 確認: #1285（PR #1292）で直った。今の `newUser()`（`manager/processors/permission/save_user.functions.php:569-580`）は `save_action` を読み、`updateUser()` と同じ戻り先（`a=11&r=3&save_action=next` / `a=12&id=…&r=3&save_action=stay` / `a=75&r=3`）になる。
- 2026-11-08: 文書と管理ユーザーの「タブを覚える」判定が、戻ってくる値と合っておらず、「この画面」で保存しても今のタブを覚えなかった。→ 2026-11-09 確認: #1285 で直った。今は文書が `remember_last_tab === '2' || (=== '1' && get_strvar('stay') === 'stay')`（`manager/actions/document/mutate_content/functions.php:823`）、管理ユーザーが同じ形で `get_strvar('save_action') === 'stay'`（`manager/actions/permission/mutate_user.dynamic.php:84`。`tpUser` の生成は `mutate_user/tpl/javascript.php` から、`form.php` を出力した後のここへ移った）。`tabpane.js` の第 2 引数は「開いたときに Cookie から復元するか」になり、タブの切り替えはいつも Cookie に書く（復元しないときは古い記録を消す）。
- 2026-11-08: `manager/index.php:185` は、POST の `stay` が `new` 以外なら何でもセッション `saveAfter` に記録する。これを読むのは文書の画面（`action_buttons.php:79`）だけ。要素の値を文書と同じ名前（この画面は `stay`）にそろえると、チャンクを「この画面」で保存しただけで文書の画面の既定の選択が変わる。→ 2026-11-09 修正前の記録: 今すでに変わっている。要素を「次の作成」（`stay=1`）か「この画面」（`stay=2`）で保存すると `saveAfter` が `1` / `2` に書き換わり、文書の画面は合う選択肢が無いので「閉じる」を選ぶ（文書を「この画面」で保存した後でも）。要素を「閉じる」（`stay` が空）で保存したときは変わらない。Decision Log の「`saveAfter` の記憶を文書の保存の processor に移す」で直るので、Validation の 文書-5 の期待値はその後の動き（変わらない）にした。
- 2026-11-08: プロフィール（`a=74`）にも「更新 + 」の選択欄があったが、保存は選択を見ずに常にダッシュボードへ戻っていた。→ 2026-11-09 確認: #1285 で選択欄を外した。プロフィールの画面（`mutate_user_pf.dynamic.php`）に `stay` は残っていない。タブの記憶は `remember_last_tab == 2` だけ（345 行）。
- 2026-11-08: Web ユーザーの入力エラーの差し戻し（`webAlert()`）は `stay` を引き継がなかった。→ 2026-11-09 確認: #1285 で直った（`save_web_user.processor.php:550-551`）。管理ユーザーの差し戻し（`save_user.functions.php:295-305` の `webAlert()`）も `save_action` を引き継ぐようになった。
- 2026-11-08: `dv` は受け取り（`manager/actions/wait.static.php:10`）だけがあり、送る箇所が無い。
- 2026-11-08: `r` は 2 か所で受けている。フルページの読み込みでは `manager/actions/header.inc.php:132` の `doRefresh(r)` → `mainMenu.reloadPane(r)`（`manager/frames/menu.php:143`）、シェルの画面遷移では `manager/media/script/shell.js:410` の `handleRefreshParam()`。値の意味の表が 2 つあり、`r=1` と `r=3` はフルページでだけ動きが違う（1 は開閉状態を保存してから読み直し、3 はそのまま読み直し。シェル側はどちらも `EvoShell.reloadTree()`）。さらに `manager/actions/tool/bkmanager.static.php:138` も `doRefresh()` を呼ぶので、バックアップの画面ではフルページで 2 回読み直す。フルページの読み込みでは、ツリーとメニューはその応答で描き直したばかりなので、`doRefresh()` の読み直しは結果を変えない。
- 2026-11-08: 管理ユーザーの保存（`saveUserSettings()`、`save_user.functions.php:104`）は、POST の項目のうち除外の一覧（`stay`・`mode`・`save_action` など）に無いものをすべてユーザー設定として保存する。フォームの項目の名前を変えるときは、この一覧に載っている名前にすること。保存後の行き先のキーを `after_save` にしたので（Decision Log）、M2 でこの一覧に `after_save` を足す（Concrete Steps 14）。
- 2026-11-08: `manager/includes/migrations/00000000_baseline.php:356` は、除外の一覧に無かったころにユーザー設定として保存された `save_action` などの行を消している。`after_save` を除外の一覧に入れ忘れると同じことが起きるので、M2 で必ず足す。ベースラインのマイグレーションは過去の掃除なので変えない。
- 2026-11-08: 文書の画面は、テンプレートやエディタを切り替えると、同じ画面の番号（`a=4` など）へフォームをそのまま POST し直す（`manager/media/style/common/jscripts.tpl:155`）。選択欄の行き先（旧名 `stay`、M2 から `after_save`）は GET（保存後の戻り）と POST（この再表示）の両方から読む必要がある。
- 2026-11-08: 文書の新規の画面では「設定」タブの種類（`fieldType`）で文書とウェブリンクを切り替えられる。今は差し戻し先を送られた `mode`（4 か 72）で決めているので、`a=4` で種類をウェブリンクに変えて差し戻されると `a=4` に戻る。
- 2026-11-08（メイン）: 上の「管理ユーザーの新規作成」「タブを覚えない」「プロフィールの選択欄」「Web ユーザーの差し戻し」の 4 件は、利用者に見える不具合なので #1285 に切り出し、#1277 より先に直す（本人の判断）。M1 の「戻り先が main と同じ」は、#1285 のマージ後の main を基準にする。→ 2026-11-09: 4 件とも #1285（PR #1292、main c6b2f8f75）で直った。
- 2026-11-08（メイン）: #1278（PR #1286）では、`optimize_table.processor.php` の戻り先の `&s=4` は受け取る側が無かったので `tab` に改名せず削除した。`t` / `u` は `table` / `truncate`（`request_strvar()` で読む）。戻り先の `mode` は残っている（M5 の対象）。
- 2026-11-08: 入力ファイル（メインが書き出した 1277-input.txt）の Issue 本文の節は空だった。→ 2026-11-09: Issue 本文を読んで突き合わせた。範囲（`r`・`dv`・`stay`・`mode`、仕組みを 1 か所にまとめる、旧名・旧値の読み替えを残さない、リリースノートに書く）は計画と一致。Issue の例（`stay=new` / `stay=edit`）とキーの名前・値が違うのは、本人の判断（Decision Log の `after_save`）による。
- 2026-11-09: #1285 の後も、要素の 5 画面（チャンク・モジュール・プラグイン・スニペット・テンプレート）と Web ユーザーのタブの記憶は `remember_last_tab == 2 || get_intvar('stay') === 2` のままで、設定が「いいえ」（0）でも「この画面」で戻るとタブを復元する。文書と管理ユーザーは設定が 1 のときだけ。TV（`mutate_tmplvars.dynamic.php:724`）は設定にかかわらず `false`（2016 年の 1ca60ecc9 から変わらず、理由の記録は無い）。
- 2026-11-09: タブの記憶の判定は、どの画面も GET だけを見ている（文書 `get_strvar`、要素 `get_intvar`、管理ユーザー `get_strvar`）。選択欄の既定は GET と POST の両方（`request_*` / `anyv`）。文書の再表示（POST）では、タブを復元せず先頭に戻る（Cookie も消える）のが今の動き。
- 2026-11-09: 管理ユーザーの保存は、`save_action` が無い・許可していない値のとき `next`（新規の画面 `a=11`）に戻る（`save_user.functions.php:569, 741` の既定値）。要素・Web ユーザーは「閉じる」。画面の選択欄はいつも値を送るので、違いが出るのは外から POST したときだけ。
- 2026-11-09: 取りこぼしの grep（M0 の手順 3）で、計画に無かった呼び出しが 2 つ見つかった。`manager/actions/main/refresh_site.dynamic.php:23` の `doRefresh(1);`（サイトの更新の画面がツリーを読み直す。シェルでは最初のページの `header.inc.php` が定義した `doRefresh` を使っている）と、`assets/docs/manager-shell.md:28` の `reloadPane` の記載。M4・M6 の対象に加えた。
- 2026-11-09: 文書の差し戻しで行き先（旧名 `stay`）を引き継ぐのは、エイリアスの重複（`save_resource.processor.php:405-411`）だけ。日付の誤り（`ex_docapi.php:545-548`）や権限のエラー（`save_resource.processor.php:51, 117, 458, 493, 532, 590`）は `formReturnUrl()` だけで、行き先を引き継がない。main と同じ動きを保つため、この Issue では広げない。
- 2026-11-09: `save_resource.processor.php` は、行き先を `validated('stay')`（DocAPI を通した POST の値。75・150 行）で `goNextAction()` に渡している。M6 の残りの grep の `v\('stay'` には当たらないので、パターンに `validated\('stay'\)` を足した。同じファイルには、すでに `mode()`（`postedMode() === 27 ? 'edit' : 'new'`、1012-1015 行）がある。
- 2026-11-09: #1279（PR #1293、未マージ）で、文書グループ・ユーザーグループの項目名が `document_groups[]` / `user_groups[]` などになった。差し戻した入力の復元は画面側の `ManagerAPI::restoreElementFormValues()` が行い、モジュールは 4 番目の引数に `['user_groups']`（`mutate_module.dynamic.php:61-66`）、プラグインは `['sysevents']` を渡している。この Issue は差し戻しの「送り出し」（`redirectToElementForm()`）だけを `EvoFormFlow` に移し、復元の関数とその引数は変えない。

修正前の戻り先の記録（2026-11-09、基準 0f915fda1。Artifacts and Notes）で分かったこと:

- 管理ユーザーのパスワードを画面に表示する保存（「通知」を「画面に表示」にしたとき）の「閉じる」のリンクは、新規では `r=3` が付き（`a=75&r=3` / `a=11&r=3&save_action=next` / `a=12&id=…&r=3&save_action=stay`）、更新では付かない（`a=75` / `a=11&save_action=next` / `a=12&id=…&save_action=stay`。`save_user.functions.php:765-777`）。自分自身を更新したときはログアウト（`a=8`）。メールで通知するときは新規・更新とも 302 で `r=3` 付き。→ Decision Log 2026-11-09（M4 でそろえる）。
- 差し戻しで「閉じる」を選んでいたときの引き継ぎが種類で違う。管理ユーザーは `save_action=close` まで付ける（`a=11&save_action=close` / `a=12&id=…&save_action=close`）。要素・Web ユーザーは何も付けない（`a=<新>` / `a=<編>&id=…`）。文書は `stay=close` まで付ける。→ Decision Log 2026-11-09（画面の既定と同じ値は付けない）。
- 文書の差し戻し（エイリアスの重複）は `a=4&newtemplate=<テンプレート>&stay=<選択>` / `a=27&id=…&newtemplate=…&stay=<選択>` の形で、「閉じる」「次の作成」でも `stay` が付く。`a=4` で種類をウェブリンクにしたときも `a=4` に戻り、入力（種類 `reference` を含む）は残る（文書-7 の main の動き）。
- 更新の差し戻しで「閉じる」を選んだときは `a=<編>&id=…` で、行き先は付かない（要素・Web ユーザー）。新規も同じく `a=<新>`。
- モジュールは、更新で名前が重複しても差し戻されずに保存される（新規だけが差し戻される。`error-dup-edit` が 302 で `a=106&r=2` など）。今の動きなので、この Issue では変えない。
- 文書の操作の戻り先は `r` の位置と引き継ぐ引数がそれぞれ違う。子の一覧からの公開・非公開・削除・削除の取り消しは `r=1&a=120&id=親` に、並べ替え・検索語（`q`・`page`・`orderby`・`orderdir`）を引き継ぐ（`ChildDocumentListState::returnUrl()`）。ルート直下の文書では `a=2&r=1`。複製は親があれば `r=1&a=120&id=親`（一覧の引数は引き継がない）、ルート直下なら `r=1&a=3&id=複製した id`。移動は移動先が親ありなら `a=120&id=移動前の親&r=1`、ルートへなら `a=2&r=1`。ゴミ箱を空にする（`a=64`）は `r=1&a=7`（待ち画面からダッシュボード）。
- Validation の表に無かった `r` 付きの戻り先: 下書きの保存（`a=128`。この画面 `a=131&id=…&stay=stay`、下書きを採用 `a=133&id=…&r=1`、閉じる `a=3&id=…&r=1`）、下書きの採用（`a=129`）・削除（`a=130`）は `a=3&id=…&r=1`、公開予約の取り消し（`a=134`）は `a=3&id=…&r=1`、一覧から（`back=publist`）は `a=70&r=1`、モジュールの複製（`a=111`）は `r=2&a=108&id=複製した id`、削除（`a=110`）は `a=106&r=2`、バックアップからの復元（`a=305`）は `r=9&a=93`。M4 の手順 24 と Validation に加えた。
- フルページで `a=93&r=9` を開くと、`header.inc.php` の `doRefresh(9)` と `bkmanager.static.php:138` の `doRefresh()` で、ツリーとメニューを 2 回読み直す（`f=nodes&savestateonly=1` と `f=menu` が 2 組）。シェルの中で `r=9` 付きの URL に移ったときも、`handleRefreshParam()` と、本文の script として実行される `bkmanager.static.php:138` の `doRefresh()` で 2 回になる（コードから。記録はフルページだけ）。M4 で `doRefresh()` を消すと、どちらも 1 回以内になる。`a=26`（サイトの更新）は、シェルでは 1 回、フルページでは描画した直後に `doRefresh(1)` でもう 1 回読み直す。M4 で `EvoShell.refresh('tree')` に替えてもフルページの 1 回は残る（描画したばかりのツリーを読み直すだけで、結果は変わらない）。
- QuickManager（クロームレス、`a=27&quickmanager=1`）の保存は、応答が 200 で `Location` が無く（保存後にサイトの画面へ移る）、ツリー・メニューの読み直しのリクエストも無い。Validation の 文書-13 に加えた。
- 文書の画面の「保存後」の既定は、文書を「次の作成」で保存しても変わらない（`new` は記憶しない。前に「この画面」なら「この画面」のまま）。文書-4 の main の動きと一致。
- テーブルの最適化のリンクは、テーブルに `Data_free` があるときだけ出る。Docker の検証環境（InnoDB）では 0 なのでリンクが出ない。修正前の記録は、リンクと同じ URL（`index.php?a=54&mode=53&table=modx_manager_log` など）への POST で取った。M5 の確認も同じ方法で行う。

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
- 2026-11-08 / Claude（planner）: `manager/processors/db/optimize_table.processor.php` の `mode`（戻り先の画面の番号 53 か 93）は、この Issue に含める（M5）。名前を `from`、値を `sysinfo` / `bkmanager` にし、processor の中で 53 / 93 に直す。2026-11-09 更新: #1278 はマージ済み。`t` / `u` は `table` / `truncate`（`request_strvar()`）になり、`s` は改名せず削除された。`from` も同じく `request_` 系の `request_choicevar('from', ['sysinfo', 'bkmanager'], '')` で読む（リンクは `data-evo-post` で開くので、GET の引数として来ても POST として来ても受ける）。
- 2026-11-08 / Claude（planner）: 値を読むヘルパー。processor（保存は POST だけ）は `post_choicevar()`。画面の選択欄は、保存後の戻り（GET）と文書の再表示（POST）の両方から来るので `request_choicevar()`（今も要素の画面は `request_intvar('stay')`）。許可していない値は、`after_save` なら `close`、`mode` なら空文字（processor が拒否する）。2026-11-09 更新: タブの記憶だけは GET の `get_choicevar()` で読む（次の項目）。
- 2026-11-08 / Claude（planner）: 選択欄の `<option>` に付いている `id="stay1"`〜`stay4` は付けない。`stay` の grep（1277-input.txt）で、PHP の出力以外に参照が無いことを確かめた。2026-11-09 の grep（M0 の手順 3 の 3 つ目）でも、`action_buttons.php` と要素・Web ユーザーの画面の PHP 以外に当たらない。
- 2026-11-08 / Claude（planner）: PR は 1 つにし、マイルストーンごとにコミットする（ブランチは `refactor/1277-readable-return-params`、`gh issue develop` で #1277 に紐づけ済み）。送る値の変更は、外から送るコードにとってどれも互換を壊すので、同じリリースにまとめて入れる。M1 は振る舞いを変えないコミットとして、差分を読むときの区切りになる。レビューが重くなったら、M4・M5 を、このブランチを base にした別の PR に分けてよい。2026-11-08 / 本人の判断: PR 1 つ・マイルストーンごとのコミットで確定。
- 2026-11-08 / 本人の判断: 管理ユーザーの「次の作成」は、今と同じく `new_user` の権限が無くても出す（要素は権限で出し分けているが、ここでは揃えない）。
- 2026-11-08 / 本人の判断: プロフィール（`a=74`）の「更新 + 」の選択欄は #1285 で外す。M2 ではプロフィールの選択欄を扱わない。（2026-11-09: #1285 で外れたことを確かめた）
- 2026-11-08 / Claude（planner）: 管理ユーザーの保存の除外の一覧（`saveUserSettings()`）は、旧名の `stay` と `save_action` を `after_save` に置き換える。どのフォームも旧名を送らなくなるので、旧名は一覧に残さない（旧名の読み替えを残さない方針と同じ）。選択欄の `id="stay"` は POST されないので一覧に要らない。
- 2026-11-09 / Claude（planner）: `EvoFormFlow::rememberTab()` は、`$setting = (int)config('remember_last_tab'); return $setting === 2 || ($setting === 1 && get_choicevar('after_save', ['stay'], '') === 'stay');` にする。
  理由: #1285 で文書と管理ユーザーはこの形（2 = いつも、1 = 「この画面」で戻ったときだけ、0 = 復元しない）にそろった。要素の 5 画面と Web ユーザーは、設定が 0 でも「この画面」で復元しており、設定に従っていない。比較は `(int)` にそろえる（文書の画面は条件によって `$modx->config['remember_last_tab'] = 0` と整数を入れる。`functions.php:537, 720`）。GET だけを見るのは、どの画面も今そうしているから（Surprises の 2026-11-09 の項。文書の再表示の POST では今と同じく復元しない）。
  影響: 設定が 0 のサイトで、要素の 5 画面と Web ユーザーの「この画面」の後にタブを復元しなくなる（意図した変更。Validation とリリースノートに書く）。
  不採用: 当初の計画の `=== 2 || requestedAfterSave() === 'stay'`（設定 0 を無視し、POST の再表示でも復元する）。
- 2026-11-09 / Claude（planner）: 管理ユーザーの保存で `after_save` が無い・許可していない値のときは、ほかの画面と同じく「閉じる」（`a=75`）にする。今の既定の `next` はやめる。画面の選択欄はいつも値を送るので、画面からの保存の戻り先は変わらない（違いが出るのは外から POST したときだけ）。行き先の既定を `EvoFormFlow::postedAfterSave()` 1 つにするため。
- 2026-11-09 / Claude（planner）: `EvoShell.refresh(target)` は、シェルの画面（`document.body.classList.contains('evo-shell')`）でなければ何もしない。今の `doRefresh()` が `top.mainMenu` の無いとき（QuickManager などのクロームレス）に何もしないのと同じにするため。`EvoShell.reloadMenu()` は `#topMenu` が無いとページごと読み直すので、クロームレスで `refresh=menu` を受けると読み直しが起きてしまう。
- 2026-11-09 / Claude（planner）: `ManagerAPI::restoreElementFormValues()`（画面側の復元）は変えない。M1 で `redirectToElementForm()` / `requireElementName()` の中身を `EvoFormFlow` に任せるとき、モジュールの画面の `restoreElementFormValues(…, ['user_groups'])`（#1279 で決めた項目名）とプラグインの `['sysevents']` の 4 番目の引数はそのまま残す。差し戻しで保存する入力（`saveFormValues()` に渡す POST 全体）も変えない。
- 2026-11-09 / Claude（planner）: #1279（PR #1293）は未マージで、この Issue と同じファイル（`mutate_module.dynamic.php`・`mutate_tmplvars.dynamic.php`・`save_module`・`save_tmplvars`・`save_resource`・`ex_managerapi.php`・`mutate_content/functions.php`）を触るので、ブランチに取り込み、#1277 の PR は `refactor/1279-group-id-field-names` を base にして作る。#1279 のマージ後に base を `main` に切り替える。2026-11-09 更新: #1279 は main にマージ済み（ccf2509be）で、このブランチに取り込んだ（376cce09c）。PR の base は `main` にする。
- 2026-11-09 / Claude（planner）: `EvoFormFlow` は、表 `SCREENS` に無い種類（kind）を受けたら `InvalidArgumentException` を投げる（`screen()`・`postedMode()`・`afterSaveUrl()`・`formUrl()` の全部。表を引く private の関数 1 つで確かめる）。`screen()` の `mode` は、空文字と `close` のときだけ `close` の番号に倒し、表に無い値（`chunk` に `profile` など）と、`close` の無い種類（文書・ウェブリンク）で `close` を求めたときも同じ例外にする。M2 の最初に入れる（手順 12）。
  理由: kind・mode は processor がリテラルで渡す値で、リクエストからは来ない（`postedMode()` は表にある値か空文字しか返さない）。表に無い値は書き間違いなので、黙って `a=0` などへ移すより、その場で止めて開発中に気づけるほうがよい。前例は `manager/includes/migration_helper.class.inc.php:426` の `throw new InvalidArgumentException("Unknown index kind: {$kind}")`。
  不採用: `evo()->logEvent()` で記録して `close` に倒す案。記録は残るが、画面は間違った戻り先へ移り続け、気づきにくい。
- 2026-11-09 / Claude（planner）: M2 で `formUrl()` の行き先を引数で受ける形にする: `formUrl(string $kind, string $mode, int $id, string $afterSave): string`。POST を読むのは呼び出し側（`EvoFormFlow::postedAfterSave()`）にそろえる。
  理由: M1 では `formUrl()` だけが中で POST を読んでおり、`afterSaveUrl()` と形が違った。引数で受ければ、どちらも入力から URL を作るだけの関数になり、M2 で読み替えを外すときに同じ形で直せる。呼び出しは 3 か所（`ManagerAPI::redirectToElementForm()`、`save_web_user.processor.php` の `webAlert()`、M2 で移す `save_user.functions.php` の `webAlert()`）で、どれも `postedAfterSave()` を渡すだけ。
- 2026-11-09 / Claude（planner）: 差し戻しで行き先を引き継ぐときは、「戻った画面の選択欄の既定と同じ値は付けない」にそろえる。要素・Web ユーザー・管理ユーザーの選択欄は、値が無いと「閉じる」を選ぶので、`close` は付けない（`formUrl()` の今の形）。管理ユーザーは今の `save_action=close` が付かなくなるが、戻った画面の選択は「閉じる」のままで変わらない（URL だけの違い）。文書は既定がセッションの記憶（`documentAfterSaveDefault()`）で「閉じる」とは限らないので、今と同じく `close` も含めて必ず付ける（`save_resource.processor.php` の差し戻しは `formUrl()` を使わず、`formReturnUrl()` に `after_save` を足す）。
  不採用: どの種類も `close` まで付ける案（要素の URL が変わるだけで得るものが無い）。
- 2026-11-09 / Claude（planner）: `postedAfterSave(array $extra = [])` は M2 で `post_choicevar('after_save', array_merge(['new', 'stay', 'close'], $extra), 'close')` にする。`$extra` を渡すのは下書きの保存（`save_draft_content.processor.php`、`['save_draft', 'save_standby']`）だけ。文書の保存（`save_resource`）の画面は下書きの値を出さないので渡さない（今の差し戻しの `request_choicevar('stay', […, 'save_draft', 'save_standby'])` から下書きの値が外れるが、画面からは送られない）。
- 2026-11-09 / Claude（planner）: `documentAfterSaveDefault()` は、セッション `saveAfter` が `stay` か `close` のときだけその値を返し、それ以外（下書きの `save_draft` / `save_standby`、旧い要素の `1` / `2`、無いとき）は `close` を返す。`rememberDocumentAfterSave()` は今と同じく `new` 以外を記録する。今の画面（`action_buttons.php:79-90`）が `new` / `stay` / `close` 以外を「閉じる」として扱うのと同じにするため。文書の再表示（テンプレートの切り替えの POST）で下書きの値を選んでいたときは、`requestedAfterSave(['save_draft', 'save_standby'])` がその値を返し、選択が残るようになる（今は「閉じる」に戻る。文書-8 の「選択が残る」にそろう小さな変更）。
- 2026-11-09 / Claude（planner）: 管理ユーザーのパスワードを画面に表示する保存の「閉じる」は、M4 で更新にも `refresh=tree` を付け、新規・更新・メールで通知のどの経路も `afterSaveUrl('user', …, ['refresh' => 'tree'])` 1 つで組む。M2 では送る値を変えない段取りを守り、更新のパスワード表示だけ `$extra` を空にして今の URL（`r` なし）を保つ。自分自身の更新のログアウト（`a=8`）は変えない。
  理由: 新規とメールで通知では付き、更新のパスワード表示だけ付かない理由の記録が無く、分岐が 1 つ増えるだけ。付けて変わるのは、閉じたあとにツリーを 1 回読み直すことだけ。
  不採用: 今の動きを保つため、更新のパスワード表示だけ `refresh` を付けない案。

## Outcomes & Retrospective

## Context and Orientation

用語:

- **画面の番号（a）**: 管理画面は `index.php?a=番号` で画面を切り替える。例: チャンクの新規作成は 77、編集は 78、要素の一覧は 76。
- **processor**: 保存などの処理だけを行い、終わったら `header('Location: …')` で次の画面へ移す PHP（`manager/processors/`）。画面は `manager/actions/`。
- **「更新 + 」の選択欄**: 編集画面の保存ボタンの横にある選択欄（今は `<select id="stay" name="stay">`、管理ユーザーは `name="save_action"`。M2 で name だけ `after_save` にする）。保存後に「次の作成」（続けて新規作成）・「この画面」（続けて編集）・「閉じる」（一覧へ）のどれにするかを選ぶ。
- **差し戻し**: 名前の重複などの入力エラーで、入力をセッションに残して（`manager()->saveFormValues(画面の番号)`）編集画面へ戻すこと。残した入力は、戻った画面が同じ番号で読み出す。
- **シェル**: 管理画面のメニュー・ツリー・本文を 1 ページにまとめた仕組み（`manager/media/script/shell.js`）。画面の移動は本文だけを fetch で差し替え、processor のリダイレクト先の URL を見て、ツリーやメニューを読み直す。
- **タブの記憶**: 編集画面のタブ（`WebFXTabPane`、`manager/media/script/tabpane.js`）は、切り替えるといつも Cookie（`webfxtab_<id>`）に書き、開いたときに復元するかを第 2 引数で決める。グローバル設定 `remember_last_tab` は 0（復元しない）・1（「この画面」で戻ったときだけ）・2（いつも）。

対象の箇所（行番号はブランチ refactor/1277-readable-return-params の df5a08619 時点。origin/main c6b2f8f75 に #1279 のブランチを取り込んだもの）。M1（2676fc4d2）で、要素 6 種・Web ユーザー・ロールの processor と `redirectToElementForm()` / `requireElementName()` は `EvoFormFlow` を通すようになり、下の表のそれらの行番号は古い。M2 で触る箇所と行番号は Concrete Steps の M2 を、`r` を送る箇所は手順 24 を正とする（どちらも 376cce09c 時点）。

旧名の `stay` と管理ユーザーの `save_action`（保存後の行き先。M2 で `after_save` にする）:

| 区分 | ファイル:行 | 今の値 |
| --- | --- | --- |
| 選択欄（文書） | `manager/actions/document/mutate_content/action_buttons.php:73-110`（`ab_save()`。78-104 が選択欄） | `new` / `stay` / `close` ＋ 下書きの `save_draft` / `save_standby`。既定はセッション `saveAfter` |
| 選択欄（要素） | `manager/actions/element/mutate_htmlsnippet.dynamic.php:172-180`、`mutate_module.dynamic.php:175-183`、`mutate_plugin.dynamic.php:293-299`、`mutate_snippet.dynamic.php:124-130`、`mutate_templates.dynamic.php:84-88`（`get_intvar`）、`mutate_tmplvars.dynamic.php:339-345` | `1` / `2` / 空 |
| 選択欄（ユーザー） | `manager/actions/permission/mutate_user/functions.php:112-175`（`saveOptions()` と `aButtonSave()`、name は `save_action`）、`mutate_web_user.dynamic.php:262-270` | `next` / `stay` / `close`、`1` / `2` / 空 |
| タブの記憶 | `mutate_content/functions.php:823`（設定 1 で `stay=stay`）、`mutate_user.dynamic.php:84`（設定 1 で `save_action=stay`）、`mutate_htmlsnippet.dynamic.php:351-354`、`mutate_module.dynamic.php:496`、`mutate_plugin.dynamic.php:592`、`mutate_snippet.dynamic.php:336`、`mutate_templates.dynamic.php:312`、`mutate_web_user.dynamic.php:688`（この 6 画面は設定 2 か `stay=2`）、`mutate_user_pf.dynamic.php:345`（設定 2 だけ）、`mutate_tmplvars.dynamic.php:724`（いつも `false`） | 上のとおり |
| 受け取りと戻り先 | `manager/processors/chunk/save_htmlsnippet.processor.php:128-135, 238-245`、`snippet/save_snippet.processor.php:97-100, 142-145`、`template/save_template.processor.php:76-79, 123-126`、`tmplvars/save_tmplvars.processor.php:96-99, 194-197`、`plugin/save_plugin.processor.php:93-96, 156-159`、`module/save_module.processor.php:97-100, 153-158`、`permission/save_web_user.processor.php:40, 210-222, 439-443, 468-472, 550-551`、`permission/save_user.functions.php:295-305, 569-580, 741-777`（順に `webAlert()`・`newUser()`・`updateUser()`）、`document/save_resource.processor.php:75, 150, 405-411, 860-888`（75・150 は `validated('stay')`）、`document/save_draft_content.processor.php:71-97` | 上の値 |
| 設定の除外の一覧 | `save_user.functions.php:104-147`（`saveUserSettings()` の `$ignore`。`stay` は 139、`save_action` は 144） | — |
| 差し戻しで引き継ぐ | `manager/includes/extenders/ex_managerapi.php:101-113`（`redirectToElementForm()`。106-110 が `stay`） | `1` / `2` |
| 選択の記憶 | `manager/index.php:185-187` | `new` 以外を `saveAfter` へ |
| 変えないもの | `manager/actions/header.inc.php:120`（`#stay` を未保存の検知から外す）、各テーマの `style.css` の `select#stay`（7 か所）、言語ファイルの `stay` / `stay_new`（文言のキー）、`manager/includes/migrations/00000000_baseline.php:356` | — |

`mode`（新規か更新か。値は画面の番号）:

| 区分 | ファイル:行 | 今の値 |
| --- | --- | --- |
| 送る（hidden） | `mutate_content/_template.tpl:14` と `mutate_content/functions.php:814`（`manager()->action`）、`mutate_htmlsnippet.dynamic.php:159`、`mutate_module.dynamic.php:162`、`mutate_plugin.dynamic.php:280`、`mutate_snippet.dynamic.php:113`、`mutate_templates.dynamic.php:73`、`mutate_tmplvars.dynamic.php:323`（いずれも `request_intvar('a')`）、`permission/mutate_role.dynamic.php:110`、`permission/mutate_user/tpl/form.php:13`、`permission/mutate_user_pf.dynamic.php:138`（固定の 74）、`permission/mutate_web_user.dynamic.php:250` | 画面の番号 |
| 受ける | `save_htmlsnippet:11-13, 62, 82, 137`（77/78）、`save_snippet:10-12, 62, 107`（23/22）、`save_template:10-12, 36, 85`（19/16）、`save_tmplvars:11-13, 36, 105`（300/301）、`save_plugin:12-14, 37, 102, 170-188`（101/102、`saveEventListeners()`）、`save_module:13-15, 41, 109`（107/108）、`save_role:12, 32-34, 55, 64, 67`（38/35）、`save_user.functions.php:7-10, 15, 27, 202, 298-303, 743, 750, 772` と `save_user.processor.php:44-76`（11/12/74）、`save_web_user:39, 66-67, 249, 483-486, 549-552`（87/88）、`save_resource:14, 1001-1015`（4/72/27。`mode()` は 1012-1015） | 画面の番号 |
| 番号を渡す呼び出し | `manager()->requireElementName(…, 新規の番号, 編集の番号, $id)`: `save_htmlsnippet:77`、`save_snippet:26`、`save_template:24`、`save_tmplvars:26`、`save_plugin:27`、`save_module:28`。`manager()->redirectToElementForm(…)`: `save_htmlsnippet:62, 96, 147`、`save_snippet:53`、`save_template:49, 98`、`save_tmplvars:44, 52, 113, 121`、`save_plugin:51, 116`、`save_module:53`（6 種すべてで確認済み） | 画面の番号 |

`mode`（別の意味。対象外）: イベントの引数（`'mode' => 'new'` / `'upd'`、processor と `assets/plugins/qm/qm.inc.php`）、`manager/actions/document/revision.dynamic.php:20`（一覧の絞り込み）、`manager/actions/tool/bkmanager.static.php:15-20, 112, 133, 157, 290, 324, 346` と `manager/processors/backup/restore.processor.php:15-31`（バックアップの操作の種類）、`manager/media/browser/evo/api.php:959` と `js/imageEditor.js:433`（コピーか上書きか）、`mutate_htmlsnippet.dynamic.php:160` の `changeMode`、`save_settings.processor.php:296-311` の変数名、`evo()->doc->mode`（`normal` / `draft`）、`DocAPI::setValue()` の `$mode`（`ex_docapi.php:489-558`）、DocumentParser・DBAPI・修飾子・Ditto・TinyMCE 7 などの関数の引数。

`mode`（戻り先の画面の番号。M5）: `manager/processors/db/optimize_table.processor.php:45`（`request_intvar('mode')`）。送るのは `manager/actions/report/sysinfo.static.php:289`（53）と `manager/actions/tool/bkmanager.static.php:231, 246`（93）。#1278 の後、リンクは `index.php?a=54&mode=…&table=…` / `&truncate=…`（`data-evo-post`）。

`r` と `dv`（再読み込み）:

| 区分 | ファイル:行 |
| --- | --- |
| 受ける | `manager/actions/header.inc.php:132-135`（`doRefresh()` の呼び出し、161-172 が本体）、`manager/frames/menu.php:143-160`（`reloadPane()`、206 で `mainMenu` に公開）、`manager/media/script/shell.js:285`（呼び出し）と `409-428`（`handleRefreshParam()`）、`manager/actions/tool/bkmanager.static.php:138`、`manager/actions/wait.static.php:8-16`（`r=10` と `dv=1`） |
| `doRefresh()` を直接呼ぶ | `manager/actions/main/refresh_site.dynamic.php:23`（`doRefresh(1);`） |
| `r=1`〜`r=10` を送る | 手順 24 の一覧（376cce09c 時点。`r=1` は 13 ファイル 22 か所、`r=2` は 3 ファイル 4 か所、`r=3` は 1 ファイル 7 か所、`r=9`・`r=10` は各 1 か所） |
| `dv=1` | 送る箇所なし |
| ドキュメント | `assets/docs/manager-shell.md:16, 28, 41`（`reloadPane` の記載と、`EvoShell` の読み直しの API） |

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

- `screen(string $kind, string $mode): int`（M1）: 種類と `mode` の画面の番号。`saveFormValues()` の鍵と差し戻し先に使う。`mode` が空か `close` なら `close` の番号。M2 から、表に無い種類・表に無い `mode`・`close` の無い種類の `close` は `InvalidArgumentException`（Decision Log 2026-11-09。ほかのメソッドも種類は同じ確かめ方）。
- `url(array $query): string`（M1）: `'index.php?' . http_build_query($query, '', '&')`。エスケープ前の URL を返す（HTML に出すときは呼び出し側で `hsc()`）。
- `postedMode(string $kind): string`（M1）: POST の `mode` を、その種類の表にある値（`close` 以外）だけ受け、それ以外は空文字。M1 では画面の番号で受けて名前に直し、M3 で名前をそのまま受ける。
- `mode(string $kind): string`（M3）: 画面側。今の `manager()->action` が表のどの値か（`new` / `edit` / `profile`）。どれでもなければ空文字。
- `postedAfterSave(array $extra = []): string`（M1）: 保存後の行き先。POST の `after_save` を `new` / `stay` / `close` と `$extra`（文書の下書きの `save_draft` / `save_standby`）だけ受け、それ以外は `close`。M1 では旧名のキー `stay` の `1` / `2` で受けて `new` / `stay` に直し（M1 で使うのは要素と Web ユーザーだけ。文書と管理ユーザーは M2 から使う。`$extra` は M1 では未使用）、M2 でキー `after_save` の値をそのまま受ける（`post_choicevar('after_save', array_merge(['new', 'stay', 'close'], $extra), 'close')`）。
- `afterSaveUrl(string $kind, int $id, string $afterSave, array $extra = []): string`（M1）: 保存後の URL。`new` は新規の画面（`after_save=new` 付き）、`stay` は編集の画面（`id` と `after_save=stay` 付き）、それ以外は `close` の画面。`$extra`（`refresh` など）はどの場合にも足す。M1 では旧名のキー `stay` と `1` / `2` に直して出し、M2 で `after_save` と値の名前のまま出す。
- `formUrl(string $kind, string $mode, int $id = 0): string`（M1）→ M2 で `formUrl(string $kind, string $mode, int $id, string $afterSave): string`: 差し戻しの URL。`mode` が `edit` なら `id` を付け、行き先が `close` でなければ引き継ぐ（M2 からは `after_save`。`close` を付けないのは、戻った画面の既定が「閉じる」だから。Decision Log 2026-11-09）。`mode` が空（`close` の画面へ戻す）でも引き継ぐのは、今の Web ユーザー・管理ユーザーの `webAlert()` と同じ。M1 では中で `postedAfterSave()` を読み、M2 から呼び出し側が渡す。
- `requestedAfterSave(array $extra = []): string`（M2）: 画面の選択欄の既定。GET か POST の `after_save`。許可していない値は空文字。
- `rememberTab(): bool`（M2）: `$setting = (int)config('remember_last_tab'); return $setting === 2 || ($setting === 1 && get_choicevar('after_save', ['stay'], '') === 'stay');`（Decision Log 2026-11-09）。
- `afterSaveOptions(string $selected, bool $withNew, array $extra = []): string`（M2）: 選択欄の `<option>` の HTML（エスケープ済み）。値は `new`（「次の作成」、`$withNew` のときだけ）・`stay`（「この画面」）・`$extra`（値 => 文言）・`close`（「閉じる」）の順。`$selected` が出した選択肢に無ければ「閉じる」を選ぶ。
- `documentAfterSaveDefault(): string` / `rememberDocumentAfterSave(string $afterSave): void`（M2）: 文書の選択の既定の読み書き。記録は `new` 以外。読み出しはセッション `saveAfter` が `stay` か `close` のときだけその値、それ以外は `close`（Decision Log 2026-11-09）。

`ManagerAPI::requireElementName($name, $typeLabel, string $kind, $id = 0)` と `redirectToElementForm($message, string $kind, $id = 0)` は、画面の番号 2 つの代わりに種類を受け、中で `EvoFormFlow::screen()` と `formUrl()` を使う。画面側の `restoreElementFormValues()` は変えない。

各マイルストーンは、送る側と受ける側を同じコミットで変え、コミットごとに管理画面が動く状態を保つ。

## Concrete Steps

作業ディレクトリはリポジトリのルート。git はワークツリーの隔離のため 1 コマンドずつ実行する（`cd` や `&&` でつながない）。

M0 着手前の確認（2026-11-09 完了）

1. `gh issue view 1277 -R modxcms-jp/evolution-jp` で Issue 本文を読み、計画の範囲と突き合わせた（結果は Surprises の 2026-11-09 の項。食い違いなし）。
2. `git log --oneline origin/main -12` で #1278（PR #1286）と #1285（PR #1292）がマージ済みであることを確かめた。ブランチは origin/main c6b2f8f75 と #1279 のブランチ（PR #1293）を取り込み済み（df5a08619）。M5 の前提（#1278 のマージ）は満たした。
3. 取りこぼしを探した（`assets/plugins/*/tinymce/` は対象外）。
       git grep -nE "requireElementName|redirectToElementForm" -- manager
       git grep -nE "reloadPane|doRefresh" -- manager assets install
       git grep -nE "\.mode\.value|\[name=.?mode|elements\[.mode.\]|\bstay[0-9]\b" -- manager assets install
   結果: 1 つ目は `ex_managerapi.php` の定義と 6 種の要素の processor（Context の「番号を渡す呼び出し」）。2 つ目は `header.inc.php`・`menu.php`・`bkmanager.static.php`・`wait.static.php` に加えて、`manager/actions/main/refresh_site.dynamic.php:23` と `assets/docs/manager-shell.md:28`（手順 22・28 に加えた）。3 つ目は `bkmanager.static.php:133`（バックアップの `mode`、対象外）と、`action_buttons.php` と要素・Web ユーザーの画面の `<option id="stayN">` だけ。
4. 修正前の戻り先を記録した（2026-11-09、基準 0f915fda1。M1 以降の比較の基準。要約と置き場所は Artifacts and Notes）。
   取り方（取り直すときもこのとおり）: `adaptive-validation-runner` スキルの手順で検証環境を立て（`setup-site.js --sample --editor`、`smoke.js` が全 PASS）、Playwright で Validation and Acceptance の表の「main」の欄の操作を行う。保存は画面の選択欄を選んでボタンを押し、`page.on('response')` で processor（`index.php` への POST）の応答の `status` と `Location` を控える。パスワードを表示する画面のように応答がリダイレクトでないものは、「閉じる」のリンクの `href` を控える。差し戻しは `page.on('dialog')` の文言と、ダイアログの後に開いた URL を控える。再読み込みは `page.on('request')`（document・fetch・xhr）の並びを控える。タブの記憶は、設定 0・1・2 のそれぞれで、2 つ目のタブを開いて「この画面」で保存した後と、開き直した後に、選ばれているタブを控える。curl で確かめるときは、ログインの Cookie と CSRF トークンを付け、`Accept-Language` を必ず付ける（`curl -s -o /dev/null -D - …` の `Location:` 行）。
   記録の形: 1 行 1 操作の TSV で、列は「画面・操作・選択・結果」（結果は `302 <POST 先> -> <Location> | landed=<開いた URL>`、差し戻しは `200 <POST 先> | dialog=[…] | after=<開いた URL>`、画面の部品は `dom` の行に select と hidden の `mode` を JSON で）。

M1 仕組みの導入（送る値は変えない。2026-11-09 完了、2676fc4d2）

5. `manager/includes/form_flow.class.inc.php` を作り、Plan of Work の表と、M1 のメソッド（`screen` / `url` / `postedMode` / `postedAfterSave` / `afterSaveUrl` / `formUrl`）を書く。クラスの docblock に、引数の名前と値の一覧（`after_save`・`mode`・`refresh`）を書き、ここを正とする（M1 の時点では、出すキーがまだ旧名の `stay` であることも書き、M2 で消す）。
6. `manager/includes/extenders/ex_managerapi.php` の先頭で `require_once MODX_CORE_PATH . 'form_flow.class.inc.php';` を読み、`requireElementName()`（74-88 行）/ `redirectToElementForm()`（101-113 行）を種類で受ける形に直す（中の旧名の `postv('stay')` の判定は `EvoFormFlow::formUrl()` に移る）。`restoreElementFormValues()`（190-213 行）とその呼び出し（モジュールの `['user_groups']`、プラグインの `['sysevents']`）は変えない。
7. 要素の processor を、`$mode = EvoFormFlow::postedMode('chunk')` のように読み、`switch` の `case` を `'new'` / `'edit'` にし、保存後を `header('Location: ' . EvoFormFlow::afterSaveUrl('chunk', $id, EvoFormFlow::postedAfterSave()))` にする。モジュールは `$extra` に `['r' => 2]`（M4 で `refresh` にする）。`requireElementName()` / `redirectToElementForm()` の呼び出しは種類を渡す（Context の「番号を渡す呼び出し」の 6 + 13 か所）。
   編集対象: `manager/processors/chunk/save_htmlsnippet.processor.php`（`resolvePublishFields()` の 62 行の `$mode === 78` も `'edit'` に）、`snippet/save_snippet.processor.php`、`template/save_template.processor.php`、`tmplvars/save_tmplvars.processor.php`、`plugin/save_plugin.processor.php`（`saveEventListeners()` の 177・184 行の `$mode === 101` も `'new'` に）、`module/save_module.processor.php`。
8. `manager/processors/permission/save_web_user.processor.php` を同じ形にする（`saveWebUserMode()`（483-486 行）は `EvoFormFlow::postedMode('web_user')`、`switch`（66 行）の `case` は `'new'` / `'edit'`、`webAlert()`（546-554 行）は `screen()` と `formUrl()` を使う。#1285 で行き先を引き継ぐようにした形を `formUrl()` に置き換える）。保存後の 4 か所（210-215・218-222・439-443・468-472 行）は `afterSaveUrl('web_user', …)`。パスワードを表示する画面の「閉じる」のリンクも同じ。
9. `manager/processors/permission/save_role.processor.php` の `mode`（12 行）を `postedMode('role')` で読み、`'new'` / `'edit'` で分岐する（32-34・55・64・67 行）。戻り先は今のまま `a=86`。
10. 構文を確かめる。
        docker compose exec app php -l manager/includes/form_flow.class.inc.php
        docker run --rm -v "$PWD":/app -w /app php:7.4-cli php -l manager/includes/form_flow.class.inc.php
    変更したファイルごとに同じことを行う。期待結果: `No syntax errors detected`。
11. Validation の「要素」「Web ユーザー」「ロール」の行を行い、`Location` のキーと値の組が手順 4 の記録と同じであることを確かめる（引数の並びは変わってよい）。
    結果（2026-11-09）: 手順 4 と同じスクリプトで全体を取り直して突き合わせ、M1 の範囲（要素 6 種 × 18 = 108、Web ユーザー 21、ロール 2）の 131 行が完全一致。範囲外（文書・管理ユーザー・再読み込みなど）も、データに由来する違い（差し戻しのダイアログに出る文書の id、記録したリクエストの履歴の長さ）のほかに差が無い。PHP・JS のエラー 0。
    コミット: 2676fc4d2 `refactor(manager): 保存後の戻り先を EvoFormFlow に集める`

M2 保存後の行き先を `after_save=new|stay|close` にそろえ、タブの記憶を設定に従わせる

M2 の書き込み範囲（worker に渡す一覧。376cce09c で `git grep -nE "v\('stay'|validated\('stay'\)|[?&]stay=|name=\"stay\"|save_action|intvar\('stay'\)|strvar\('stay'\)|saveAfter|remember_last_tab|EvoFormFlow::formUrl"` を引いて確かめた。行番号は同じ時点）:

    manager/includes/form_flow.class.inc.php
    manager/includes/extenders/ex_managerapi.php                    redirectToElementForm()（101-107 行）
    manager/actions/element/mutate_htmlsnippet.dynamic.php          選択欄 172-180、タブ 351-352
    manager/actions/element/mutate_module.dynamic.php               選択欄 175-183、タブ 496
    manager/actions/element/mutate_plugin.dynamic.php               選択欄 293-299、タブ 592
    manager/actions/element/mutate_snippet.dynamic.php              選択欄 124-130、タブ 336
    manager/actions/element/mutate_templates.dynamic.php            選択欄 84-88、タブ 312
    manager/actions/element/mutate_tmplvars.dynamic.php             選択欄 339-345、タブ 724
    manager/actions/permission/mutate_web_user.dynamic.php          選択欄 262-270、タブ 688
    manager/actions/permission/mutate_user_pf.dynamic.php           タブ 345
    manager/actions/permission/mutate_user.dynamic.php              タブ 84
    manager/actions/permission/mutate_user/functions.php            saveOptions() 112-140、aButtonSave() 142-175
    manager/actions/document/mutate_content/action_buttons.php      ab_save() 73-108
    manager/actions/document/mutate_content/functions.php           タブ 823
    manager/processors/permission/save_web_user.processor.php       webAlert() 518-524
    manager/processors/permission/save_user.functions.php           $ignore 139・144、webAlert() 296-305、newUser() 569-580、updateUser() 741-777
    manager/processors/document/save_resource.processor.php         先頭 5-17、75、150、差し戻し 405-411、goNextAction() 861-888
    manager/processors/document/save_draft_content.processor.php    71-97
    manager/index.php                                               185-187

この一覧に無いもの: 要素 6 種の processor とロールは、`postedAfterSave()` / `afterSaveUrl()` の呼び方が変わらないので触らない（モジュールの `['r' => 2]` は M4 まで残す）。`manager/actions/header.inc.php:120`（`#stay` の除外）、テーマの `select#stay`、言語ファイルの `stay` / `stay_new` は変えない。`assets/docs/` に `stay` / `save_action` / `saveAfter` の記載は無い。

12. `EvoFormFlow` を M2 の形にする。最初に M1 の自己レビューの 3 件を直す。
    - 表を引く private の関数を 1 つ作り、表に無い種類（kind）は `InvalidArgumentException` にする（`screen()`・`postedMode()`・`afterSaveUrl()`・`formUrl()` がこれを通す）。`screen()` は `mode` が空文字か `close` のときだけ `close` の番号を返し、表に無い `mode` と、`close` の無い種類の `close` は同じ例外にする（Decision Log 2026-11-09）。`save_web_user.processor.php:523` の `$mode ? screen(…) : 0` はそのままでよい（空の `mode` を `screen()` に渡していない）。
    - `postedAfterSave(array $extra = [])` を `post_choicevar('after_save', array_merge(['new', 'stay', 'close'], $extra), 'close')` にし、`$extra` を使う形にする（手順 15 の下書きの保存が渡す）。
    - `formUrl()` を `formUrl(string $kind, string $mode, int $id, string $afterSave)` にし、中で POST を読まない。呼び出し側は `ManagerAPI::redirectToElementForm()`（`EvoFormFlow::postedAfterSave()` を渡す）、`save_web_user.processor.php` の `webAlert()`（同じ）、手順 14 の `save_user.functions.php` の `webAlert()`。
    そのうえで、`postedAfterSave()` / `afterSaveUrl()` / `formUrl()` から旧名のキー `stay` と `1` / `2` の読み替え（`LEGACY_STAY`）を消し、キー `after_save` で値の名前をそのまま受け・出す形にする。`requestedAfterSave()` / `rememberTab()` / `afterSaveOptions()` / `documentAfterSaveDefault()` / `rememberDocumentAfterSave()` を足す（Plan of Work）。docblock から移行中の注記を消す。
    確かめ方: `docker compose exec app php -r` で `require 'manager/includes/form_flow.class.inc.php';` の後に `EvoFormFlow::screen('nope', 'new')` と `EvoFormFlow::screen('document', '')` を呼び、どちらも `InvalidArgumentException` になること、`EvoFormFlow::screen('chunk', '')` が 76、`EvoFormFlow::formUrl('chunk', 'edit', 7, 'close')` が `index.php?a=78&id=7`、`formUrl('chunk', 'new', 0, 'stay')` が `index.php?a=77&after_save=stay` を返すこと（`post_choicevar()` を使う関数はここでは呼ばない）。
13. 要素・Web ユーザーの画面の選択欄を `<select id="stay" name="after_save">` にし、中身を `<?= EvoFormFlow::afterSaveOptions(EvoFormFlow::requestedAfterSave(), evo()->hasPermission('new_chunk')) ?>` の形にする（権限は画面ごとに今と同じもの）。id は `stay` のまま（テーマの CSS と未保存の検知の除外が使う。name と id が食い違うのは Decision Log のとおり）。タブの記憶を `EvoFormFlow::rememberTab() ? 'true' : 'false'` にする（要素 5 画面・Web ユーザー・プロフィール。TV の 724 行の `false` 固定も、未決事項の 2026-11-09 の決定どおり `rememberTab()` にそろえる）。プロフィールは選択欄が無いので、`rememberTab()` は設定 2 のときだけ真になり、今と同じ。
14. 管理ユーザー: `mutate_user/functions.php` の `saveOptions()` を `afterSaveOptions(requestedAfterSave(), true)` にし、`aButtonSave()` の `<select>` を `id="stay" name="after_save"` にする（旧名の `save_action` をやめる。「次の作成」は今と同じく権限で出し分けない）。`mutate_user.dynamic.php:84` のタブの記憶を `rememberTab()` に。`save_user.functions.php` の `newUser()` と `updateUser()` を `postedAfterSave()` と `afterSaveUrl('user', …, ['r' => 3])` にする（行き先が無いときの既定は「閉じる」。Decision Log 2026-11-09）。ただし更新のパスワード表示の「閉じる」（765-777 行）は `$extra` を空にして今の URL（`r` なし）を保ち、自分自身の更新のログアウト（`a=8`）はそのまま（`refresh` は M4 で付ける。Decision Log 2026-11-09）。`webAlert()` は `screen('user', $mode)` と `formUrl('user', $mode, post_intvar('userid'), EvoFormFlow::postedAfterSave())` にする（M3 までは `saveUserMode()` の番号を名前に直して渡す）。これで「閉じる」の差し戻しから `save_action=close` が消える（Decision Log 2026-11-09）。同じファイルの `saveUserSettings()` の除外の一覧（`$ignore`）で、旧名の `stay`（139 行）と `save_action`（144 行）を `after_save` に置き換える（足さないと `after_save` がユーザー設定として保存される。Surprises の管理ユーザーの保存の項）。プロフィール（`saveUserMode()` が 74）の保存後は今と同じくダッシュボード（`r=3&a=2`、744 行）。
15. 文書: `action_buttons.php` の `ab_save()` を、選択欄を `<select id="stay" name="after_save">` にし、中身を `afterSaveOptions(requestedAfterSave(['save_draft', 'save_standby']) ?: documentAfterSaveDefault(), 新規の権限, 下書きの選択肢)` にする（下書きの文言は今のまま。新規の選択肢は今と同じく下書きの画面では出さない）。`mutate_content/functions.php:823` を `rememberTab()` に。`save_resource.processor.php` は先頭（権限と `mode` の確かめの直後）で `rememberDocumentAfterSave(postedAfterSave())` を呼び、`goNextAction()` の行き先を、75・150 行の `validated('stay')` に代えて `postedAfterSave()` から取り（値は今と同じ `new` / `stay` / `close`。変わるのはキーだけ）、URL を `EvoFormFlow::url()` で組む。405-411 の差し戻しは `formReturnUrl()` に `&after_save=` と `postedAfterSave()` を足し、`close` も含めて必ず付ける（Decision Log 2026-11-09。ほかの差し戻しは今と同じく行き先を引き継がない。Surprises の 2026-11-09 の項）。`save_draft_content.processor.php` は `postedAfterSave(['save_draft', 'save_standby'])` にし、先頭で `rememberDocumentAfterSave()` を呼ぶ（行き先が無いときは今の `''` の分岐と同じく `a=3&id=…&r=1`）。`manager/index.php:185-187`（旧名の POST の `stay` を `saveAfter` へ記録する処理）を消す。
16. 構文確認（手順 10 と同じ）と、Validation の「要素」「ユーザー」「文書」の行。手順 4 のスクリプトで全体を取り直し、M2 で変わる行（キーが `after_save` になる行と、Validation の「変更後」の欄に書いた行）以外が手順 4 の記録と同じであることを確かめる。比較は `cmp.js` の正規化（作った要素・文書の id と名前の乱数を伏せる）で、旧名から新名への置き換え（`stay=1` → `after_save=new`、`stay=2` → `after_save=stay`、文書の `stay=` → `after_save=`、`save_action=next` → `after_save=new`、`save_action=` → `after_save=`）を足してから突き合わせる。
    コミット案: `refactor(manager): 保存後の行き先を after_save=new・stay・close にそろえる`

M3 `mode` の値をそろえる

17. `postedMode()` から番号の読み替えを消し、`mode()` を足す。
18. 画面の hidden の `mode` を `EvoFormFlow::mode('chunk')` などにする（Context の「送る（hidden）」の行のすべて）。文書は `functions.php:814` を `EvoFormFlow::mode((int)manager()->action === 72 ? 'weblink' : 'document')` にする（下書きの画面では空になり、下書きの保存は `mode` を読まないので影響しない）。プロフィールは `profile`。
19. 文書の processor: `save_resource.processor.php` の `postedMode()`（1001-1004 行）を、`EvoFormFlow::postedMode('document')` と、それを画面の番号に直す関数（`edit` は 27、`new` は送られた `type` が `reference` なら 72、それ以外は 4）に分ける。今の `mode()`（1012-1015 行）は `EvoFormFlow::postedMode('document')` の値そのものになるので消す。`saveFormValues()`・`formReturnUrl()`・`DocAPI::setValue()` には番号を渡す。管理ユーザーは `saveUserMode()` と、その比較（`save_user.functions.php:15, 27, 202, 301, 743`、`save_user.processor.php:46, 76`）を名前に。
20. 構文確認と、Validation の全行。
    コミット案: `refactor(manager): 新規か更新かの mode を new・edit にする`

M4 `r` を `refresh` にし、`dv` を消す

21. `shell.js` の `EvoShell` に `refresh(target)` を足す（`tree` は `reloadTree()`、`menu` は `reloadMenu()`、`all` は両方、`page` は `window.location.href = 'index.php'`、それ以外は何もしない。シェルの画面でなければ何もしない。Decision Log 2026-11-09）。`handleRefreshParam()`（409-428 行）は `searchParams.get('refresh')` を渡すだけにする。
22. `header.inc.php` の 132-135 行と `doRefresh()`（161-172 行）、`bkmanager.static.php:138`、`menu.php` の `reloadPane()`（143-160 行）と `mainMenu.reloadPane`（206 行）を消す。`manager/actions/main/refresh_site.dynamic.php:23` の `doRefresh(1);` は `EvoShell.refresh('tree');` にする。
23. `wait.static.php` は `get_choicevar('refresh', ['page'], '')` が `page` なら `EvoShell.refresh('page');`、それ以外は今の `EvoShell.navigate("index.php?a=2");` にし、`dv` の分岐を消す。
24. 送る側の `r=` をすべて `refresh=` に置き換える（`r=1` と `r=3` は `tree`、`r=2` は `menu`、`r=9` は `all`、`r=10` は `page`）。M4 に入る前に `git grep -nE "[?&]r=[0-9]|\br=[0-9]+&|'r' *=> *[0-9]" -- manager assets install` を引き直し、下の一覧と合うことを確かめる（376cce09c 時点。M2・M3 で行番号がずれる）。
        r=1  manager/includes/controls/child_document_list_state.class.php:78（'r' => 1。子の一覧からの公開・非公開・削除・削除の取り消しが通る）
        r=1  manager/processors/document/delete_content.processor.php:38（a=6、ルート直下）
        r=1  manager/processors/document/undelete_content.processor.php:72（a=63、ルート直下）
        r=1  manager/processors/document/publish_content.processor.php:47（a=61、ルート直下）
        r=1  manager/processors/document/unpublish_content.processor.php:45（a=62、ルート直下）
        r=1  manager/processors/document/duplicate_content.processor.php:54, 57（a=94）
        r=1  manager/processors/document/move_document.processor.php:158, 165（a=52）
        r=1  manager/processors/document/remove_content.processor.php:78（a=64、ゴミ箱を空にする）
        r=1  manager/processors/document/save_resource.processor.php:866, 871, 877, 883, 887（goNextAction()）
        r=1  manager/processors/document/save_draft_content.processor.php:80, 92, 97（a=128）
        r=1  manager/processors/document/publish_draft_content.processor.php:73（a=129）
        r=1  manager/processors/document/delete_draft_content.processor.php:31（a=130）
        r=1  manager/processors/document/unpublish_draft_content.processor.php:28, 30（a=134、28 は back=publist）
        r=2  manager/processors/module/save_module.processor.php:96, 146（$extra の ['r' => 2]）
        r=2  manager/processors/module/duplicate_module.processor.php:59（a=111）
        r=2  manager/processors/module/delete_module.processor.php:44（a=110）
        r=3  manager/processors/permission/save_user.functions.php:572, 577, 579, 744, 749, 758, 763（M2 の後は $extra の ['r' => 3]）
        r=9  manager/processors/backup/restore.processor.php:48（a=305）
        r=10 manager/processors/save_settings.processor.php:30
    `child_document_list_state.class.php:78` は `'refresh' => 'tree'`。`r` を先頭に置いている URL（`r=1&a=120…`、`r=2&a=108…`、`r=9&a=93`、`r=1&a=7`、`r=3&a=2`）は、`a` を先頭にした並びにそろえてよい（シェルは `searchParams` で読むので並びは効かない）。管理ユーザーの更新のパスワード表示の「閉じる」（M2 で `$extra` を空にした所）にも `['refresh' => 'tree']` を付ける（Decision Log 2026-11-09）。
25. 構文確認と、Validation の「文書」（文書-9〜13）・「モジュールの保存・複製・削除」・「再読み込みとその他」の行。手順 4 のスクリプトで、各操作の後の `page.on('request')` の並びを取り、読み直し（`f=nodes&savestateonly=1`・`f=nodes`・`f=menu`）の回数を数える。
    コミット案: `refactor(manager): 再読み込みの引数を refresh=tree などにし、dv を消す`

M5 テーブルの最適化の戻り先

26. `optimize_table.processor.php:45` を、`request_choicevar('from', ['sysinfo', 'bkmanager'], '')` で読み、53 / 93 に直して戻す形にする（選択肢に無ければダッシュボード `a=2`）。`sysinfo.static.php:289` と `bkmanager.static.php:231, 246` のリンクの `mode=' . $action` を `from=sysinfo` / `from=bkmanager` にする。リンクの残りの組み立て（`table` / `truncate`、`hsc()`、`data-evo-post`）は #1278 の形のまま。確かめ方: Docker の検証環境（InnoDB）では `Data_free` が 0 でリンクが出ないので、リンクと同じ URL（`index.php?a=54&from=sysinfo&table=modx_manager_log` など）へ POST して `Location` を見る（Surprises の 2026-11-09 の項）。リンクの `href` の形は、`sysinfo.static.php` と `bkmanager.static.php` の出力のコードを読んで確かめる。
    コミット案: `refactor(manager): テーブルの最適化の戻り先を from で受ける`

M6 ドキュメントと最終確認

27. 旧い名前と値が残っていないことを確かめる。
        git grep -nE "[?&]r=[0-9]|'r' *=> *[0-9]|intvar\('r'\)|intvar\('dv'\)|searchParams\.get\('r'\)|reloadPane|doRefresh" -- manager assets install
        git grep -nE "v\('stay'|validated\('stay'\)|[?&]stay=|name=\"stay\"|save_action" -- manager assets install ':!manager/includes/migrations'
        git grep -nE "choicevar\('mode', \[[0-9]|intvar\('mode'\)|'mode' => manager\(\)->action|name=\"mode\" value=\"<\?= *(\(int\) *)?(\\\$action|request_intvar)|name=\"mode\" value=\"74\"" -- manager
    期待結果: どれも出力なし。2 つ目は旧名のキー `stay` と `save_action` を探す（値としての `'stay'` や、言語ファイルの `$_lang['stay']`、`id="stay"` には当たらない形にしてある）。マイグレーションを外すのは、`manager/includes/migrations/00000000_baseline.php:356` が、過去にユーザー設定へ紛れ込んだ `save_action` の行を消すために名前を持っているから（過去の掃除なので変えない）。df5a08619 の時点では、1 つ目が 51 行、2 つ目が 50 行（うち `validated('stay')` が 2 行）、3 つ目が 22 行当たる。
28. `assets/docs/architecture.md` の管理画面の処理の流れの節（無ければ近い節）に、「保存後の戻り先・新規か更新か・再読み込みの値は `EvoFormFlow`（`manager/includes/form_flow.class.inc.php`）と `EvoShell.refresh()` を正とする」と 2〜3 行で書く。`assets/docs/manager-shell.md` の 16・28・41 行から `reloadPane` を消し、読み直しの API に `refresh`（`tree` / `menu` / `all` / `page`）を足す。`/doc-audit` を通す。
29. PR の本文に Interfaces and Dependencies の「リリースノートに書くこと」を載せる。

## Validation and Acceptance

Docker の検証環境（`adaptive-validation-runner` スキルの手順で起動）で、次の操作の戻り先（保存の応答の `Location`、または最後に表示された画面の URL）と画面の様子を確かめる。「main」の欄は M0 の手順 4 で記録した値と同じであること、「変更後」の欄のとおりになることを確かめる。`<新>` `<編>` `<閉>` は Plan of Work の表の番号。タブの記憶は、特に書いていなければ設定 `remember_last_tab` を既定の 1 にして確かめる。

要素（チャンク・スニペット・テンプレート・TV・プラグイン・モジュールのそれぞれ）:

| 操作 | main | 変更後 |
| --- | --- | --- |
| 新規で保存 + 閉じる | `a=<閉>` | 同じ |
| 新規で保存 + 次の作成 | `a=<新>&stay=1`、選択欄は「次の作成」 | `a=<新>&after_save=new`、選択欄は「次の作成」 |
| 新規で保存 + この画面 | `a=<編>&id=新しい id&stay=2`、タブを覚える（TV は覚えない） | `a=<編>&id=…&after_save=stay`、タブを覚える（TV も覚える。未決事項の 2026-11-09 の決定） |
| 更新で保存（3 種） | 上と同じ形（id は今の id） | 上と同じ形 |
| 名前の重複・名前が空で差し戻し（新規・更新 × 次の作成 / この画面） | `a=<新>&stay=1`・`a=<新>&stay=2` / `a=<編>&id=…&stay=1`・`…&stay=2`、入力が残る（モジュールのユーザーグループ、プラグインのイベントの選択も） | `after_save=new` / `after_save=stay` にした同じ形、入力が残る |
| 同じ差し戻しで「閉じる」を選んでいたとき | `a=<新>` / `a=<編>&id=…`（行き先は付かない）、選択欄は「閉じる」 | 同じ |
| モジュールを更新で、ほかと同じ名前にして保存 | 差し戻されずに保存される（`a=106&r=2` など、保存と同じ戻り先） | 同じ（`refresh=menu`） |
| モジュールの保存・複製・削除 | 保存は上の形に `r=2`（`a=106&r=2` / `a=107&r=2&stay=1` / `a=108&id=…&r=2&stay=2`）、複製（`a=111`）は `r=2&a=108&id=複製した id`、削除（`a=110`）は `a=106&r=2`。メニューのモジュールの一覧が更新される | `r=2` を `refresh=menu` にした同じ形、同じ |
| タブの記憶の設定を 0 / 1 / 2 にして、2 つ目のタブで「この画面」で保存 | 0・1・2 のどれでも TV 以外は覚え、TV は覚えない。設定 2 で開き直すと TV 以外は覚えている | 0: どれも覚えない / 1・2: TV を含めて覚える（M2 からの意図した変更。設定 0 は文書・管理ユーザーと同じ動きになる） |
| 画面の hidden の `mode`（開発者ツール） | 画面の番号 | `new` か `edit` |
| 選択欄（開発者ツール） | `<select id="stay" name="stay">`、値は `1` / `2` / 空 | `<select id="stay" name="after_save">`、値は `new` / `stay` / `close`。テーマの見た目（`select#stay`）が変わらず、選択を変えても未保存の警告が出ない |

ユーザー:

| 操作 | main | 変更後 |
| --- | --- | --- |
| 管理ユーザーを新規で保存 + 次の作成 / この画面 / 閉じる | `a=11&r=3&save_action=next` / `a=12&id=新しい id&r=3&save_action=stay` / `a=75&r=3` | `a=11&after_save=new` / `a=12&id=…&after_save=stay` / `a=75`、どれも `refresh=tree` |
| 管理ユーザーを更新で保存（3 種） | `a=11&r=3&save_action=next` / `a=12&id=…&r=3&save_action=stay` / `a=75&r=3`。「この画面」でタブを覚える | `a=11&after_save=new` / `a=12&id=…&after_save=stay` / `a=75`、どれも `refresh=tree`。「この画面」でタブを覚える |
| 管理ユーザーの差し戻し（新規でユーザー名の重複・更新でメールアドレスの誤り × 3 種） | `a=11&save_action=<選択>` / `a=12&id=…&save_action=<選択>`（「閉じる」でも `save_action=close` が付く）、入力が残る | 次の作成・この画面は `after_save=new` / `after_save=stay`。閉じるは `a=11` / `a=12&id=…` で行き先が付かず、選択欄は「閉じる」（Decision Log 2026-11-09）。入力が残る |
| 管理ユーザーを保存したあと、ユーザー設定の表（`user_settings`）を見る | `save_action` の行が無い | `after_save` の行も無い。`docker compose exec db mariadb -uuser -ppassword evodb -e "SELECT setting_name FROM modx_user_settings WHERE setting_name IN ('after_save','stay','save_action')"` が 0 行（接続情報と接頭辞は `compose.yml` の既定値。`.env` や検証環境のスクリプトで変えていればそれに合わせる） |
| パスワードを画面に表示する保存（新規・更新 × 3 種） | 表示画面の「閉じる」のリンクが、新規は `a=75&r=3` / `a=11&r=3&save_action=next` / `a=12&id=…&r=3&save_action=stay`、更新は `a=75` / `a=11&save_action=next` / `a=12&id=…&save_action=stay`（`r` なし）。自分自身の更新はログアウト（`a=8`） | M2: キーを `after_save`（値の `next` は `new`）にした同じ形（更新は `r` なしのまま）。M4 から: 新規・更新とも `refresh=tree` 付き（Decision Log 2026-11-09）。自分自身は同じ |
| プロフィールを保存 | `r=3&a=2` | ダッシュボード、`refresh=tree` |
| Web ユーザー（新規・更新 × 3 種、パスワード表示） | 要素と同じ形（87 / 88 / 99） | 要素と同じ形 |
| Web ユーザーのメールアドレスの誤りで差し戻し（この画面） | `a=88&id=…&stay=2` | `a=88&id=…&after_save=stay` |
| ロールの新規・更新 | `a=86` | 同じ。`mode` に他の値を送ると保存しない |

文書:

| 操作 | main | 変更後 |
| --- | --- | --- |
| 文書-1 新規（親あり）で保存 + 閉じる / この画面 / 次の作成 | `a=120&id=親&r=1` / `a=27&id=…&r=1&stay=stay` / `a=4&pid=親&r=1&stay=new` | `r=1` を `refresh=tree`、旧名のキー `stay` を `after_save` にした同じ形（値の `stay` / `new` は今と同じ）。「この画面」でタブを覚える |
| 文書-2 ルート直下の文書を閉じるで保存 | `a=3&id=…&r=1` | `a=3&id=…&refresh=tree` |
| 文書-3 ウェブリンクを新規で保存 + 次の作成 | `a=72&pid=親&r=1&stay=new` | `a=72&pid=親&refresh=tree&after_save=new` |
| 文書-4 「この画面」で保存したあと、別の文書の新規の画面を開く | 選択欄が「この画面」 | 同じ |
| 文書-5 文書を「この画面」で保存し、要素（チャンクなど）を「次の作成」か「この画面」で保存したあと、文書の新規の画面を開く | 選択欄が「閉じる」に変わる（`index.php:185` が `saveAfter` を `1` / `2` で上書きする）。要素を「閉じる」で保存したときは「この画面」のまま | 「この画面」のまま（要素の保存で変わらない。Decision Log の `saveAfter` の記憶を processor に移す判断による意図した変更） |
| 文書-6 エイリアスの重複で差し戻し（新規・更新 × 3 種） | `a=4&newtemplate=<テンプレート>&stay=<選択>` / `a=27&id=…&newtemplate=…&stay=<選択>`（「閉じる」「次の作成」でも付く） | `stay=` を `after_save=` にした同じ形（`close` も付く。Decision Log 2026-11-09） |
| 文書-7 `a=4` で種類をウェブリンクに変え、エイリアスの重複で差し戻し | `a=4&newtemplate=…&stay=<選択>` に戻り、入力（種類 `reference` を含む）が残る | `a=72&newtemplate=…&after_save=<選択>` に戻り、入力（種類はウェブリンク）が残る |
| 文書-8 テンプレートを切り替える（再表示） | 選択欄の選択が残る（`new` を選んでいたら `new`）。タブは先頭に戻る | 同じ。下書きの画面で下書きの値を選んでいたときも残る（今は「閉じる」に戻る。Decision Log 2026-11-09） |
| 文書-9 下書きの保存（`a=128`）で この画面 / 下書きを採用 / 閉じる | `a=131&id=…&stay=stay` / `a=133&id=…&r=1` / `a=3&id=…&r=1` | `after_save=stay` / `refresh=tree` にした同じ形 |
| 文書-10 子の一覧から公開・非公開・削除・削除の取り消し（`a=61`・`62`・`6`・`63`） | `r=1&a=120&id=親`。一覧の引数があれば `&q=…&page=…&orderby=…&orderdir=…` を引き継ぐ。ルート直下の文書は `a=2&r=1` | `r=1` を `refresh=tree` にした同じ形（引数の並びは変わってよい）、ツリーが更新される |
| 文書-11 複製（`a=94`）・移動（`a=52`）・ゴミ箱を空にする（`a=64`） | 複製は親があれば `r=1&a=120&id=親`（一覧の引数は引き継がない）、ルート直下なら `r=1&a=3&id=複製した id`。移動は `a=120&id=移動前の親&r=1`、ルートへは `a=2&r=1`。ゴミ箱は `r=1&a=7` からダッシュボード | `refresh=tree` にした同じ形 |
| 文書-12 下書きの採用（`a=129`）・削除（`a=130`）・公開予約の取り消し（`a=134`） | どれも `a=3&id=…&r=1`。公開予約の一覧から取り消したとき（`a=134&back=publist`）は `a=70&r=1` | `refresh=tree` にした同じ形 |
| 文書-13 QuickManager（クロームレス、`a=27&quickmanager=1`）で保存 | 応答は 200 で `Location` が無く、サイトの画面へ移る。ツリー・メニューの読み直しのリクエストは無い | 同じ（M2 で選択欄の name が変わっても保存できる。M4 でページごとの読み直しが起きない） |

再読み込みとその他:

| 操作 | main | 変更後 |
| --- | --- | --- |
| グローバル設定を保存 | `a=7&r=10`、管理画面ごと読み直してダッシュボード | `a=7&refresh=page`、同じ |
| バックアップからの復元（`a=305`） | `r=9&a=93`、ツリーとメニューが更新される。`a=93&r=9` をフルページで開くと、描画のあとツリーとメニューを 2 回読み直す（`doRefresh()` が 2 か所） | `a=93&refresh=all`、ツリーとメニューが更新される。フルページでは描画のあとの読み直しが無く、シェルでは 1 回 |
| サイトの更新（`a=26`） | シェルではツリーを 1 回読み直す。フルページでは描画のあと `doRefresh(1)` で 1 回読み直す | シェルでは 1 回（`EvoShell.refresh('tree')`）。フルページでは main 以下（1 回以内）。コンソールにエラーが出ない |
| `r` / `refresh` 付きの URL をブラウザで再読み込み（フルページ。`a=2&r=1`・`a=2&r=2`・`a=2&r=3`・`a=3&id=1&r=1`） | 描画のあと `doRefresh()` でツリーかメニューをもう 1 回読み直す | `refresh=tree` / `menu` で開き、描画のあとの読み直しが無い。ツリー・メニューが表示され、コンソールにエラーが出ない |
| システム情報・バックアップの画面からテーブルを最適化・中身を削除 | `a=53` / `a=93`（#1278 で `&s=4` は削除済み）。Docker の検証環境ではリンクが出ないので、同じ URL（`a=54&mode=53&table=…` など）への POST で確かめた | `from=sysinfo` / `from=bkmanager` で同じ画面に戻る（同じく POST で確かめる） |

静的な確認: M6 の手順 27 の grep が出力なし。変更したすべての PHP ファイルで、`docker compose exec app php -l` と `php:7.4-cli` の `php -l` が `No syntax errors detected`。保存の操作のあと、`docker compose exec app php evo log:tail --lines=20` に PHP のエラーが無い。

## Idempotence and Recovery

コードの変更だけで、DB・設定・ファイルの置き場所は変えない。マイルストーンごとに 1 コミットにし、各コミットで送る側と受ける側をそろえるので、途中で止めても、どのコミットの時点でも管理画面は動く。問題が出たら、そのマイルストーンのコミットだけを revert する（M2〜M5 は前のマイルストーンに依存するので、戻すときは後ろから順に）。M1 は戻り先を変えないので、M1 だけを残して止めてもよい。セッションに残った古い `saveAfter` は、文書の値（`new` / `stay` / `close`）なら値が変わらないのでそのまま効き、要素が書き込んだ `1` / `2` は許可していない値として「閉じる」に読まれるだけで、エラーにならない。M0 の手順 4 の記録とスクリプトは git の管理外の一時領域にあり、消えていたら手順 4 の取り方で基準のコミット（0f915fda1）を立てて取り直す（Artifacts and Notes）。

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/1277
- ブランチ: `refactor/1277-readable-return-params`（`gh issue develop` で #1277 に紐づけ済み）。PR: #1296（下書き、base は `main`。2026-11-09 に M1 まで入れて作成。最後のマイルストーンで下書きを解除する）。
- base: `main`。#1279（PR #1293）は main にマージ済み（ccf2509be）で、このブランチに取り込んだ（376cce09c）。
- 関連: #1278（PR #1286、マージ済み。`optimize_table.processor.php` の `t` / `u` を `table` / `truncate` にし、`s` を削除。`mode` は #1277 に残した）、#1285（PR #1292、マージ済み。管理ユーザーの保存後の行き先・タブの記憶・差し戻しの引き継ぎ・プロフィールの選択欄）、#1279（PR #1293、マージ済み。グループの項目名）、#1070（`request_*var()` を `any_*var()` に改名する予定。`EvoFormFlow::requestedAfterSave()` もその対象になる）、#560（グローバルな名前を増やさない）。
- 修正前の戻り先の記録（2026-11-09、基準 0f915fda1＝M1 の直前）: 308 行。内訳は要素 6 種 × 18（新規・更新 × 3 種の選択と、名前の重複・名前が空 × 新規・更新 × 3 種の選択）、管理ユーザー 21 と `user_settings` の確認 1・プロフィール 1、Web ユーザー 21、ロール 2、文書 64（文書-1〜13 に当たる操作）、モジュールの複製・削除 4、最適化・中身の削除（POST）4、管理ログを空に 1、グローバル設定 2、QuickManager 1、サイトの更新 2、`r` 付きのフルページの読み込み 6、タブの記憶 54（9 画面 × 設定 0・1・2 × 保存後・開き直し）、差し戻しの入力の復元 2（モジュール・プラグイン）、バックアップ 2、画面の部品 12（`dom` の行。select の name・id・値と hidden の `mode`）。想定と違ったものは Surprises の「修正前の記録で分かったこと」に書いた。
  置き場所（ジョブの一時領域。git の管理外で、消えることがある）: `/home/yamamoto/.claude/jobs/62dfc916/tmp/1277-m0-locations.tsv`。記録したスクリプトは同じ所の `m0lib.js`（ログイン・応答とダイアログとリクエストの記録・TSV への追記）と `m0-elements.js`・`m0-users.js`・`m0-users2.js`・`m0-docs.js`・`m0-docs2.js`・`m0-doc8.js`・`m0-misc.js`・`m0-keep.js`・`m0-opt.js`、環境の起動は `m1-up.sh`（スクリプトを `m1/` に写して出力先を `1277-m1-locations.tsv` に替え、`compose.validation.yml` で立てて `setup-site.js --sample --editor` と `smoke.js`）と `m1-env.sh`（`EVO_VALIDATE_NAME=evo-validate-1277`・`EVO_BIND_IP`・`NODE_PATH`）、実行は `m1-run.sh`、比較は `m1/cmp.js`（先頭 3 列で行を対応づけ、作った要素・文書の id と名前の乱数を伏せて突き合わせる）。
  M2 以降も、このスクリプトを写して出力先を替え、同じ手順で取り直して `cmp.js` で突き合わせる（M2 は手順 16 の置き換えを足す）。一時領域が消えていたら、手順 4 の取り方と上の内訳で書き直し、基準のコミット 0f915fda1 を立てて記録から取り直す。
- M1 の検証（2026-11-09）: M1 の範囲 131 行（要素 108・Web ユーザー 21・ロール 2）が修正前と完全一致、範囲外も差なし、PHP・JS のエラー 0（手順 11）。
- 想定コミット単位: M1 は 2676fc4d2。M2〜M5 は各コミット案のとおり。M6 は `docs: 保存後の戻り先の決まりを書く`。
- 洗い出しの件数（2026-11-09、df5a08619）: `stay` と `save_action` は 24 ファイル（変えるもの 23、変えない `header.inc.php` 1）。内訳は選択欄 9（文書 1・要素 6・管理ユーザー 1・Web ユーザー 1）、タブの記憶 10（`stay` / `save_action` を見るもの 8、設定だけのプロフィール 1、いつも `false` の TV 1）、受け取りと戻り先の組み立て約 50 行（`save_user.functions.php` 10、`save_resource` 7、`save_web_user` 7 など）。`mode`（新規か更新か）は送る 12 か所（12 ファイル）と受ける 11 ファイル、番号を渡す呼び出しは 19 か所（6 ファイル）。`mode`（戻り先）は受ける 1・送る 3。`r` は送る 37 か所（19 ファイル）、受ける 5 ファイル、`doRefresh()` を直接呼ぶ 1 か所、ドキュメント 1 ファイル。`dv` は受ける 1・送る 0。

## Interfaces and Dependencies

リリースノートに書くこと（互換を壊す変更）:

- 編集画面から保存の processor へ送る保存後の行き先のキーが `after_save` になり、値は `new`（続けて新規作成）・`stay`（この画面）・`close`（閉じる）になる（文書の下書きは、ほかに `save_draft` / `save_standby`）。旧名のキー `stay`（要素・Web ユーザーの `1` / `2`、文書の `new` / `stay` / `close`）と、管理ユーザーの `save_action`（`next` / `stay` / `close`）は受けない。保存後の URL の行き先も `after_save=…` になる。選択欄の `id="stay"` は変わらないので、テーマの CSS の `select#stay` はそのまま効く。行き先を送らないときは、どの画面も「閉じる」になる（管理ユーザーは今まで新規の画面だった）。
- 新規か更新かを表す `mode` の値が、画面の番号（77 / 78 など）から `new` / `edit`（プロフィールは `profile`）になる。保存の processor へ直接 POST するコードは直す必要がある。プラグインやサイトの JS で POST の `mode`（画面の番号。`$_POST['mode'] == 27` や `document.mutate.mode.value` など）を見ていた場合も、エラーにならずに別の分岐へ入るので直す必要がある。新規か更新かはイベントの引数 `mode`（`new` / `upd`）か POST の `mode`（`new` / `edit`）で、文書かウェブリンクかは `type` で判断する。下書きの画面の `mode` は空になる。旧い番号で送ると保存しない。`custom_tpl_dir` に置いた文書の画面のテンプレートは `[+mode+]` のままでよい。
- 保存後の URL の再読み込みの引数 `r`（1・2・3・9・10）が `refresh`（`tree` / `menu` / `all` / `page`）になる。`dv` は無くなる。古い URL をブックマークしていても、読み直しが起きないだけでエラーにはならない。
- JS の `top.mainMenu.reloadPane(n)` と、画面の `doRefresh(n)` が無くなる。ツリーやメニューを読み直すモジュールは `EvoShell.refresh('tree')` などを使う。
- テーブルの最適化・中身の削除（`a=54`）の戻り先の引数 `mode` が `from`（`sysinfo` / `bkmanager`）になる。
- `ManagerAPI::requireElementName()` / `redirectToElementForm()` の引数が、画面の番号 2 つから種類の名前（`'chunk'` など）に変わる。
- あわせて変わる動き: 要素の画面と Web ユーザーの画面で、設定「最後に開いたタブを記憶」が「いいえ」のとき、「この画面」で保存してもタブを復元しなくなる（文書・管理ユーザーと同じ）。TV の画面も、設定が 2 のときと、1 で「この画面」で保存したときにタブを復元するようになる（今はいつも先頭）。要素などを「次の作成」「この画面」で保存しても、文書の画面の「保存後」の既定の選択が「閉じる」に変わらなくなる。管理ユーザーの入力エラーの差し戻しで、「閉じる」を選んでいたときは URL に行き先が付かなくなる（画面の選択は同じ）。管理ユーザーの更新でパスワードを画面に表示したあとの「閉じる」も、ツリーを読み直す（新規と同じ）。選択欄の `<option>` の `id="stay1"`〜`stay4` が無くなる（サイトの CSS・JS・ManagerManager の規則で `#stay2` などを指していたら効かなくなる）。文書の画面でテンプレートやエディタを切り替えた（同じ画面への POST）だけでは、「保存後」の選択を覚えなくなる（保存したときだけ覚える）。下書きの選択肢（`save_draft` / `save_standby`）を選んだまま画面を切り替えても、選択が残る。（M2 の自己レビューより）更新の前に開いていた管理ユーザーの編集画面から更新の後に保存すると、旧名の `save_action` がユーザー設定に 1 行できる（読む側は無く害は無い。画面を開き直せば起きない）。
- 変えないもの: プラグインのイベントに渡す `mode`（`new` / `upd`）、選択欄の `id="stay"`、言語ファイルのキー、`ManagerAPI::restoreElementFormValues()`。

依存:

- #1278（PR #1286）と #1285（PR #1292）は main にマージ済み（2026-11-09 確認）。M1 の比較の基準は #1285 の後の動き。#1285 で直した動き（管理ユーザーの新規作成の行き先、文書と管理ユーザーのタブの記憶、Web ユーザーの差し戻し、プロフィールの選択欄）は、リリースノートでは #1285 側に書く。
- #1279（PR #1293）は main にマージ済み（ccf2509be、2026-11-09 確認）で、このブランチに取り込み済み。
- 要素・ユーザーの画面は `manager()` が読み込まれた状態で動く前提（`EvoFormFlow` は `ex_managerapi.php` が読み込む）。

## 未決事項

なし（2026-11-09 の M1 後の更新で足した判断は、すべて Decision Log の 2026-11-09 の項に書いた）。

- 2026-11-09（メイン）: TV の画面（`mutate_tmplvars.dynamic.php:724`）の `false` 固定は、理由の記録が無い取り残しと判断し、M2 でほかの要素と同じ `rememberTab()` にそろえる。設定 2 と「この画面」（設定 1）で TV のタブを復元するようになる点を、リリースノートの「あわせて変わる動き」に書く。
- 2026-11-09（メイン）: 管理ユーザーで行き先が無い・不正なときの既定を `next` から「閉じる」に変える planner の判断（Decision Log）を採る。画面の選択欄はいつも値を送るので、違いが出るのは外から POST したときだけ。
