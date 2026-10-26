# ExecPlan: 管理画面の各画面が直書きした縦の余白をやめ、テーマの決まりで揃える（Issue #924）

## Purpose / Big Picture

管理画面の各画面（`manager/actions/` の PHP）が `style` 属性や埋め込みの `<style>` で書いている「ブロックの間の縦の余白」を消し、テーマの `style.css` の決まり（`assets/docs/theme-spacing.md`）で揃える。どのテーマでも、どの画面のどのタブでも、ブロックの間が決まりの値（`--space-3` = 12px など）になり、テーマ側だけで余白を直せるようになる。RevoStyle は今の見た目のまま変えない。

## Progress

- [x] (2026-10-27) ExecPlan を作った。直書きの棚卸し（Context and Orientation の表）まで済み
- [ ] M1: 修正前の計測（6 テーマ + RevoStyle × 対象画面 × 全タブ）と、棚卸しの仕分けの確定
- [ ] M2 の手順 0: 本人の確認（相談事項 1〜4）
- [ ] M2: 画面の HTML から直書きの余白を消し、6 テーマに決まりの規則を足す。RevoStyle には今の値を保つ規則を足す
- [ ] M3: 通しの計測と、`theme-spacing.md` の例外と書き方の仕上げ

## Surprises & Discoveries

計画を作るときの調査で分かったこと（2026-10-27、根拠は該当ファイルの行）。

- `manager/actions/document/document_data.static.php` の 206〜212 行の埋め込みの `<style>` は、`h3 { padding-bottom: 0; margin-bottom: 0 }` を範囲を絞らずに書いている。シェル化で管理画面は 1 つの文書（ツリーやメニューと同じ `document`）になったので、この規則は `a=3` を開いている間 `#mainPane` の中のすべての `h3` に効き、決まりの `.sectionBody h3`（下 `--space-3`）を打ち消す。
- エディタ型の 5 画面（`mutate_templates`・`mutate_snippet`・`mutate_htmlsnippet`・`mutate_module`・`mutate_plugin` の各 `.dynamic.php`。`mutate_tmplvars` は無い）は、コード欄の上の帯を同じ `style="padding:3px 8px; overflow:hidden;zoom:1; background-color:#eeeeee; border:1px solid #c3c3c3; border-bottom:none;margin-top:5px;"` で書いている。縦の余白（`margin-top:5px`）のほかに、色と枠も直書きで、テーマの色の変数（`assets/docs/theme-variables.md`）にも従っていない。
- `manager/actions/report/sysinfo.static.php` の 99 行は `.sectionBody` 自身に `style="padding:10px 20px;"` を書いている。`.sectionBody` の内側の余白はテーマが決めてよい値（`theme-spacing.md` の対象外の項）なのに、画面の HTML がテーマの値を上書きしている。
- `manager/actions/tool/import_site.static.php` の 33 行は `.sectionBody` に `style="margin:8px;"` を書いている（外側の余白。ブロックの間に当たる）。
- `manager/actions/element/resource_selector.static.php`（`a=84`）は、`window.close()` で閉じる別窓の画面で、`#mainPane` の中に入らない。決まりの対象外（ポップアップ）。
- `manager/actions/report/phpinfo.static.php`（`a=200`）の埋め込みの `<style>` は、PHP の `phpinfo()` が出す HTML を整えるもので、`.phpinfoReport` の中に閉じている。

## Decision Log

- 2026-10-27 / yamamoto（planner）: ExecPlan を作る。
  - 根拠: 約 28 ファイル・80 か所の `style` 属性と、20 ファイルの埋め込みの `<style>` があり、2 枚目以降のタブの計測も要る。1 回の PR の中でマイルストーンに分けて進める。
- 2026-10-27 / yamamoto（planner）: 対象は「`#mainPane` の中で縦に並ぶブロックの間」だけにする。表のセル（`td`・`th`）の `padding`、入力欄・ボタン・並べ替えリストの項目など部品の内側、横の余白、JavaScript が組み立てる引数の小さな表（`mutate_tmplvars`・`mutate_snippet`・`mutate_module`・`mutate_plugin` の `t = '<table …>'`）の余白は変えない。
  - 根拠: `theme-spacing.md` の「対象外」（部品の内側の余白、横の余白）に従う。引数の表は入力部品の一部として扱う。
  - 代替案: `style` 属性をすべて class に移す案。余白の決まりと関係ない書き換えが大半になり、差分の意味が閉じないので採らない（必要なら別の Issue にする）。
- 2026-10-27 / yamamoto（planner）: 直書きを消した後の間は、既存の決まりの規則（`.sectionBody > :is(div, table, fieldset)`、`ul.actionButtons`、`:has(+ X)` の前の兄弟の `margin-bottom` など）で作る。既存の規則で足りない所だけ class を付け、6 テーマの `/* 縦の余白の決まり */` の並びに規則を足す。class の名前は #917 にならい、画面の部品を表す名前にする（例: `.resources-batch-actions`）。
  - 根拠: 余白の値をテーマの `style.css` だけに置く（SSOT）。新しい class は、既存の規則で当たらないときだけにする（YAGNI）。
- 2026-10-27 / yamamoto（planner）: エディタの帯の色と枠の直書きは、この作業では扱わない。`margin-top:5px` だけを消す。
  - 根拠: #924 は縦の余白の作業。色の直書きは `theme-variables.md` の作業で、5 画面 × 7 テーマの見た目の確認が別に要る。M3 の後に別の Issue を作る（本人の確認後）。
  - 代替案: 帯に class を付け、色と枠も `style.css` に移す案。設計としてはこちらが望ましいが、範囲が倍になるので分ける。相談事項 3 で本人に確かめる。
- 2026-10-27 / yamamoto（planner）: 修正前の状態は #917 と同じく、Playwright で `**/manager/media/style/*/style.css*` を横取りして `<base>` の中身を返す方法で作る。ただし今回は画面の PHP も変えるので、横取りだけでは修正前を再現できない。M1 の計測は、未変更の作業ツリー（このブランチの M1 の時点）で行い、結果を `<work>/spacing/m1-base/` に残して修正前の基準にする。
  - 根拠: #917 の学び（基準の版を報告に書かないと読み違える）。M2 以降の比較の基準は、常に `m1-base`（コミット `<base>` の表示）と書く。
- 2026-10-27 / yamamoto（planner）: 計測の条件は #917 と同じ（幅 1440px・高さ 900px、0.5px に丸め、決まりとの差が ±0.5px 以内なら一致）。2 枚目以降のタブは、タブの見出し（`.tab-row .tab`）を順にクリックして、パネルごとに測る。

## Outcomes & Retrospective

実装後に記載。

## Context and Orientation

用語（詳しくは `assets/docs/theme-spacing.md`）:

- テーマ: 管理画面の見た目の一式。`manager/media/style/<テーマ名>/style.css` が本体。システム設定 `manager_theme` で選ぶ。同梱は AromaHop・AuroraFlow・RevoClassic・RevoStyle・RubberWhite・SakuraFlow・SoftSlate の 7 つ。RevoStyle は廃止予定の凍結テーマで、決まりの対象外
- `#mainPane`: 管理画面の右の作業領域。各画面（`manager/actions/` の PHP の出力）はここに差し込まれる。ツリーやメニューと同じ 1 つの文書なので、画面の埋め込みの `<style>` は範囲を絞らないと画面の外にも効く
- ブロック: `#mainPane` の中で縦に積む塊。ページの見出し `h1`、`.section`（見出しの帯 `.sectionHeader` と本体 `.sectionBody`）、操作ボタン列 `ul.actionButtons`（`div.actionButtons` もある）、表、タブの組 `.tab-pane`（タブの列 `.tab-row` とパネル `.tab-page`）
- 縦の間: 上のブロックの下端（枠を含む）から下のブロックの上端（枠を含む）までの距離。決まりではブロックの間は `--space-3`（12px）
- 直書き: 画面の HTML の `style="…"` 属性や、画面が出す `<style>` の中で余白の px を書くこと。`style` 属性は `!important` 無しでは `style.css` から上書きできない
- 画面の番号: 管理画面の URL の `index.php?a=<番号>`。番号とファイルの対応は `manager/index.php` の `switch` の `case`

棚卸し（2026-10-27。ブロックの間に当たるものだけ。行は計画作成時）:

| 画面 | ファイル | 直書き | 見込みの扱い |
| --- | --- | --- | --- |
| a=53 システム情報 | `manager/actions/report/sysinfo.static.php` | 99 行 `.sectionBody` の `padding:10px 20px`、129・135・148 行 `<table style="margin-bottom:20px;">`（2 枚目のタブ） | `padding` を消してテーマの値に任せる。表の下は `.sectionBody > :is(div, table, fieldset)` で 12px |
| a=93 バックアップ | `manager/actions/tool/bkmanager.static.php` | 294 行 `div.actionButtons` の `margin-top:10px`、325 行 `margin-top:10px;margin-bottom:10px` | 消す。前の兄弟の `margin-bottom` で作る |
| a=95 インポート | `manager/actions/tool/import_site.static.php` | 33 行 `.sectionBody` の `margin:8px` | 消す |
| a=40・a=91 アクセス権限 | `manager/actions/permission/access_permissions.dynamic.php`・`web_access_permissions.dynamic.php` | `form` の `margin: 0px`（5 か所ずつ）、`li` の中の `form` の `margin-top: 0.5em` | `margin: 0` はテーマの既定と同じなら消す。`0.5em` は M1 で測って決める |
| a=10 メッセージ | `manager/actions/permission/messages.static.php` | 354 行 `ul.actionButtons` の `margin-top:15px` | 消す |
| a=16・a=19 テンプレート | `manager/actions/element/mutate_templates.dynamic.php` | 105 行 `div` の `margin-bottom:10px`、151 行 帯の `margin-top:5px`、273 行 `ul.actionButtons` の `margin-top:15px` | 消す |
| a=22・a=23 スニペット、a=78・a=77 チャンク、a=108 モジュール、a=102 プラグイン | `mutate_snippet`・`mutate_htmlsnippet`・`mutate_module`・`mutate_plugin`（各 `manager/actions/element/*.dynamic.php`） | 帯の `margin-top:5px`。プラグインは 659・668 行 `.split` の `margin:10px 0` | 帯の `margin-top` を消す。`.split` は M1 で測って決める |
| a=301 テンプレート変数 | `manager/actions/element/mutate_tmplvars.dynamic.php` | 498 行 `div` の `padding-top:8px` | M1 で測って決める |
| a=13 ログ | `manager/actions/report/logging.static.php` | 188 行 `ul.actionButtons` の `margin-top:1em`（横の `margin-left:5px` は残す） | 縦だけ消す |
| a=31 ファイル管理 | `manager/actions/element/files.dynamic.php` | 375 行 `#uploader` の `margin-top:10px` | 消す |
| a=133 下書きの公開 | `manager/actions/document/publish_draft.dynamic.php` | 106 行 `div` の `margin-bottom:1em` | 消す |
| a=3 リソースの情報 | `manager/actions/document/document_data.static.php` | 206〜212 行 埋め込みの `h3 { padding-bottom:0; margin-bottom:0 }`（範囲を絞っていない） | 消す（Surprises & Discoveries） |
| a=83 エクスポート | `manager/actions/tool/export_site.static.php` | 埋め込みの `#exportNotice { margin: 1em 0 }`、`#exportResult dl { margin: 0.5em 0 }` | `#exportNotice` は消してブロックの間に。`dl` はリセット規則の対象なので消す |

対象外にするもの（理由）:

- 表のセルの `padding`（`mutate_settings/tab*.inc.php` の `td colspan="2" style="padding:0;"`、`th`・`td` の `padding-top:5px/10px`、`sysinfo` の `padding-right:30px`、`files.dynamic.php` の `padding-right`、各画面の `table.settings td { padding }`、`bkmanager` の `#mainPane table td { padding: 4px }`）: 部品の内側
- 入力欄・リンク・チェックボックスの余白（`textarea` の `padding:0`、`mutate_module_resources` の `a` の `margin-bottom`、`resource_selector` の `a` の `margin-left` など）: 部品の内側・横の余白
- `fieldset style="width:300px;padding:0;"`（ユーザーの編集）、`div style="padding-left:20px"`: 部品の内側・横の余白
- 並べ替えリスト（`mutate_template_tv_rank`・`mutate_plugin_priority` の `ul.sortableList li`）、親の選択のモーダル（`parent_picker`）、システムログのスクロールする箱の中の項目（`eventlog` の `.system-log-entry` など）: 部品の内側・モーダル。`theme-spacing.md` の例外に「スクロールする箱やリストの項目の間」として書く
- `resource_selector`（a=84、別窓）、`phpinfo`（a=200、`phpinfo()` の出力を整える `.phpinfoReport` の中）: 対象外の画面。`theme-spacing.md` の例外に書く
- `import_site` の `fieldset style="padding:10px;…"`、`files.dynamic.php` 438 行の `div` の `padding:10px 20px`: 入れ物の内側の余白（色も直書き）。この作業では扱わない

関係するファイル:

- 上の表の `manager/actions/` の各ファイル: 直書きを消す。必要なら class を付ける
- `manager/media/style/{AromaHop,AuroraFlow,RevoClassic,RubberWhite,SakuraFlow,SoftSlate}/style.css`: 末尾近くの `/* 縦の余白の決まり */` の並びに、必要な規則を足す
- `manager/media/style/RevoStyle/style.css`: 末尾の凍結の規則（1885 行から）に、消した値を同じ px で保つ規則を足す
- `assets/docs/theme-spacing.md`: 例外の表と「書き方」に、この作業で決めたことを足す
- `.claude/skills/adaptive-validation-runner/scripts/`（`lib.js` の `login()`・`sql()`・`appExec()`、`setup-site.js`、`smoke.js`、`compose.validation.yml`）: 検証環境と計測
- `.agent/plans/archive/2026-10-25-theme-vertical-spacing.md`: #917 の計画。計測スクリプトの作り（M1 の手順 3〜5）の手本

前提:

- #917（PR #922）が main に入っている。6 テーマの `style.css` は決まりに揃い、RevoStyle には凍結の規則がある
- DocumentParser（`manager/includes/document.parser.class.inc.php`）の `executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()` には影響しない。変えるのは管理画面の PHP の HTML 属性・埋め込みの CSS と、テーマの CSS と文書だけ
- キャッシュ: サーバー側のキャッシュには影響しない。ブラウザは `style.css?<modx_version>` を版で覚えるので、版が上がるまで古い CSS が残ることがある（リリースで解消。#917 と同じ）
- PHP の互換: HTML 属性と CSS の変更だけで、PHP の構文は変えない。`php -l` は検証用イメージで通す

## Plan of Work

M1 では、手を入れる前の 7 テーマで、上の表の画面を全タブまで測る。#917 の計測スクリプトはリポジトリの外に置いていたので、`.agent/plans/archive/2026-10-25-theme-vertical-spacing.md` の M1 の手順 3〜5 にならって作り直し、タブを順に開いて測る処理を足す。結果から、直書きの箇所ごとに「消すだけで決まりの値になる」「規則を足す」「対象外」を確定させ、表を更新する。`margin-top: 0.5em` や `.split` のように、見込みが決まっていない箇所もここで決める。

M2 の最初に本人の確認を取る（相談事項は Concrete Steps の M2 の手順 0）。確認の後、画面の HTML から直書きを消す。消すと決まりの値にならない箇所には、class を付け、6 テーマの `/* 縦の余白の決まり */` に規則を足す。px は書かず `var(--space-N)` で書く。RevoStyle には、消した値を同じ px で保つ規則を末尾の凍結の規則に足す（見た目を変えないため）。コミットは、画面の領域ごと（report/tool、element、permission・document）と、テーマの規則、RevoStyle の保持に分ける。

M3 では、7 テーマ × 対象画面 × 全タブを通しで測り、6 テーマは決まりとの差が無いこと、RevoStyle は `m1-base` と同じことを確かめる。縦の余白以外の計算済みの値（色・枠・文字・横の余白）が変わらないことも比べる。最後に `theme-spacing.md` の例外（スクロールする箱やリストの項目、別窓の画面、`phpinfo` の出力）と、「埋め込みの `<style>` は画面の部品の class に範囲を絞る」書き方を足す。

## Concrete Steps

記号:

- `<wt>`: 作業ツリー（このブランチ）
- `<work>`: リポジトリの外の作業用ディレクトリ（例: `$CLAUDE_JOB_DIR/tmp/evo-924-spacing`。無ければ `/tmp/evo-924-spacing`）
- `<base>`: `git -C <wt> merge-base origin/main HEAD`（計画作成時は 8c703cb38）

検証環境は、名前 `evo-validate-924`、アドレス `127.0.0.24`、ポート 80。docker compose と同梱スクリプトの実行には、毎回先頭に `EVO_VALIDATE_NAME=evo-validate-924 EVO_BIND_IP=127.0.0.24` を付ける（付け忘れると別のセッションの環境を作り直すおそれがある）。

### M1: 修正前の計測と仕分けの確定

1. 検証環境を立てる（`.claude/skills/adaptive-validation-runner/references/environment.md` に従う）。
   - 実行:
     - `docker ps -a --filter label=com.docker.compose.project=evo-validate-924 --format '{{.Names}}'` が空であること
     - `ss -ltn | grep -E '(127\.0\.0\.24|0\.0\.0\.0|\*|\[::\]):(80|8025)\b'` が空であること
     - `<wt>` で `EVO_VALIDATE_NAME=evo-validate-924 EVO_BIND_IP=127.0.0.24 docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml up -d --build`
     - `<work>/pw` に `npm install playwright@1.48.2` と `npx playwright install chromium`
     - 同じ前置きと `NODE_PATH=<work>/pw/node_modules` で `node .claude/skills/adaptive-validation-runner/scripts/setup-site.js --sample`、続けて `scripts/smoke.js`
   - 期待: smoke.js がすべて PASS する
2. 計測に使う ID を決める。
   - 実行: `docker exec evo-validate-924-db mariadb -uuser -ppassword evodb -N -e "SELECT MIN(id) FROM modx_site_templates; SELECT MIN(id) FROM modx_site_snippets; SELECT MIN(id) FROM modx_site_htmlsnippets; SELECT MIN(id) FROM modx_site_plugins; SELECT MIN(id) FROM modx_site_modules; SELECT MIN(id) FROM modx_site_tmplvars; SELECT COUNT(*) FROM modx_user_settings WHERE setting_name='manager_theme'"`
   - 期待: 各エレメントの ID（`<tpl>`・`<snip>`・`<chunk>`・`<plugin>`・`<module>`・`<tv>`）が出る。無いものは、管理画面で 1 件作ってから測る。最後の行は 0
3. 計測スクリプト `<work>/spacing/measure.js` を作る。リポジトリには置かない。
   - #917 の計画の M1 の手順 3（テーマの切り替え、ログイン、`EvoShell.navigate()`、測る要素と記録する値）をそのまま写す
   - 足すこと: 画面を開いた後、`#mainPane .tab-row .tab` を順にクリックし、表示されたパネルごとに `OUT/<テーマ>/<画面>--tab<N>.json` と `.png` を保存する
   - 測る画面: `a=53`、`a=93`、`a=95`、`a=40`、`a=91`、`a=10`、`a=16&id=<tpl>`、`a=22&id=<snip>`、`a=78&id=<chunk>`、`a=108&id=<module>`、`a=102&id=<plugin>`、`a=301&id=<tv>`、`a=13`、`a=31`、`a=3&id=1`、`a=83`。`a=133` は下書きのあるリソースが要るので、`a=27&id=1` で下書きを作ってから測る（作れなければ理由を書いて外す）
   - 期待: `node <work>/spacing/measure.js` が、7 テーマ × 画面 × タブの JSON と PNG を出し、エラーが無い
4. 修正前を測る。
   - 実行: `STATE=base OUT=<work>/spacing/m1-base node <work>/spacing/measure.js`（作業ツリーは未変更のまま。横取りはしない）
   - 期待: `m1-base` に全テーマ・全画面・全タブの結果がそろう
5. 仕分けを確定する。
   - `rule.json`（#917 の M1 の手順 5 の形。無ければ `theme-spacing.md` の決まりの表から作る）と比べ、直書きの箇所ごとに「消すだけで決まりの値になる」「class と規則を足す」「対象外（例外に書く）」を決める
   - Context and Orientation の棚卸しの表の「見込みの扱い」を、決めた扱いに書き換える。見込みが「M1 で決める」の行（`0.5em`、`.split`、`padding-top:8px`）は必ず決める
   - 期待: 表のすべての行に、決まった扱いと、修正後に期待する間の値（px）が書かれている
   - コミット: `docs(plan): #924 の修正前の計測と仕分けを記録する`

### M2: 直書きを消し、テーマで揃える

0. 本人の確認を得てから行う（M2 以降のすべての手順の前提）。
   - Decision Log に「M1 の確認」の行があれば、確認は済んでいる。無ければ runner はここで止まり、「止まった理由: ExecPlan に『本人の確認を得てから行う』と書かれた手順に来た」として、下の相談事項と M1 の表を返す
   - 相談事項:
     1. `sysinfo` の `.sectionBody` の `padding:10px 20px` を消し、テーマの内側の余白に任せてよいか（見た目が変わる。RevoStyle は保持の規則で今の値を保つ）
     2. 対象外の仕分け（Context and Orientation の「対象外にするもの」）でよいか
     3. エディタの帯の色と枠の直書きを、別の Issue に分けてよいか
     4. 新しく付ける class の名前（M1 で決めた一覧）
1. report・tool の画面（`sysinfo`・`bkmanager`・`import_site`・`logging`・`export_site`）の直書きを消す。
   - 編集: 表の該当行。`style` 属性に余白以外の値（`margin-left` など）があれば、その値だけを残す
   - 実行: `EVO_VALIDATE_NAME=evo-validate-924 EVO_BIND_IP=127.0.0.24 docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml exec app php -l <ファイル>` を変えたファイルごとに
   - 期待: `No syntax errors detected`
   - コミット: `refactor(manager): システム情報・バックアップなどの画面の直書きの縦の余白を消す`
2. element の画面（テンプレート・スニペット・チャンク・モジュール・プラグイン・テンプレート変数・ファイル管理）の直書きを消す。
   - 期待・確認: 手順 1 と同じ
   - コミット: `refactor(manager): エレメントの編集画面の直書きの縦の余白を消す`
3. permission・document の画面（アクセス権限 2 画面、メッセージ、下書きの公開、リソースの情報）の直書きを消す。`document_data` の埋め込みの `h3` の規則は消す。
   - 期待・確認: 手順 1 と同じ
   - コミット: `refactor(manager): 権限とリソースの画面の直書きの縦の余白を消す`
4. 6 テーマの `style.css` に、M1 で決めた規則を足す。
   - 編集: `manager/media/style/{AromaHop,AuroraFlow,RevoClassic,RubberWhite,SakuraFlow,SoftSlate}/style.css` の `/* 縦の余白の決まり */` の並び。`var(--space-3)` など段で書く。`:has()` を使う規則は別の規則に分ける（`theme-spacing.md` の書き方）
   - 実行: `grep -nE "^\s*margin(-top|-bottom)?:\s*[0-9.]+px" <足した行の範囲>` で px の直書きが無いこと
   - コミット: `style(theme): 画面から消した縦の余白を 6 テーマの決まりで揃える`
5. RevoStyle に、消した値を同じ px で保つ規則を足す。
   - 編集: `manager/media/style/RevoStyle/style.css` の末尾の凍結の規則（コメントに、どの画面から消した値かを足す）
   - コミット: `style(theme): RevoStyle で画面から消した縦の余白を同じ値で保つ`
6. 修正後を測って比べる。
   - 実行: `STATE=work OUT=<work>/spacing/m2-work node <work>/spacing/measure.js` と、#917 と同じ形の比較 `node <work>/spacing/compare.js <work>/spacing/m1-base <work>/spacing/m2-work <work>/spacing/rule.json`
   - 期待: 6 テーマで、M1 の表の「修正後に期待する間」と一致する。RevoStyle は `m1-base` と同じ。縦の余白以外の計算済みの値の差が無い

### M3: 通しの確認と文書

1. `assets/docs/theme-spacing.md` を仕上げる。
   - 例外の表に足す: スクロールする箱やリストの項目の間（システムログの項目、並べ替えリスト）、別窓の画面（`a=84`）、`phpinfo()` の出力（`a=200`）
   - 「書き方」に足す: 画面が埋め込む `<style>` は、画面の部品の class か ID に範囲を絞る（`h3 { … }` のように要素だけで書かない。1 つの文書なので画面の外にも効くため）
   - 「対象外のテーマ」の RevoStyle の段落に、今回保った値を足す
   - 実行: doc-maintainer の `/doc-audit` を `theme-spacing.md` と、この ExecPlan に行う
   - 期待: 指摘 0 件
   - コミット: `docs(theme): 画面の直書きの余白をやめた後の例外と書き方を足す`
2. 通しで確かめる。
   - 実行: `scripts/smoke.js` と、M2 の手順 6 の計測と比較をもう一度
   - 期待: smoke.js が PASS。比較の結果は M2 の手順 6 と同じ
3. 残った直書きが対象外だけであることを確かめる。
   - 実行: `grep -rnE 'style="[^"]*(margin(-top|-bottom)?|padding(-top|-bottom)?)\s*:' manager/actions` の出力を、Context and Orientation の「対象外にするもの」と照らす
   - 期待: 出た行がすべて対象外の項に当てはまる

## Validation and Acceptance

- 6 テーマ（RevoStyle を除く）で、棚卸しの表の画面を全タブまで開いたとき、ブロックの間が `theme-spacing.md` の決まりの値になっている（計測の比較で差が ±0.5px 以内）。例: システム情報（`a=53`）の 2 枚目のタブで、表と次の小見出しの間が 12px。バックアップ（`a=93`）の復元のタブで、ファイル欄とボタンの間が決まりの値
- RevoStyle では、同じ画面の計測とスクリーンショットが修正前（`m1-base`）と変わらない
- 縦の余白以外の計算済みの値（色・枠・文字・横の余白）が、7 テーマとも修正前と変わらない
- `grep` で残る `style` 属性の余白が、すべて対象外の項に当てはまる
- `smoke.js` が PASS し、変えた PHP がすべて `php -l` を通る

## Idempotence and Recovery

- 計測はリポジトリの外（`<work>`）に出力し、同じコマンドで上書きして取り直せる
- `m1-base` を失ったときは、`git -C <wt> worktree add <work>/base <base>` で未変更の作業ツリーを作り、そこで検証環境を立て直して手順 4 をやり直す（PHP も変えるので、CSS の横取りだけでは修正前を再現できない）
- 検証環境は `EVO_VALIDATE_NAME=evo-validate-924 EVO_BIND_IP=127.0.0.24 docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml down -v` で消して立て直せる
- 各コミットは画面の領域ごとに閉じているので、途中で止まっても、次の手順から再開できる

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/924
- 関連: #917（PR #922）、`.agent/plans/archive/2026-10-25-theme-vertical-spacing.md`
- 想定コミット（Concrete Steps の各手順）:
  - `docs(plan): #924 の修正前の計測と仕分けを記録する`
  - `refactor(manager): システム情報・バックアップなどの画面の直書きの縦の余白を消す`
  - `refactor(manager): エレメントの編集画面の直書きの縦の余白を消す`
  - `refactor(manager): 権限とリソースの画面の直書きの縦の余白を消す`
  - `style(theme): 画面から消した縦の余白を 6 テーマの決まりで揃える`
  - `style(theme): RevoStyle で画面から消した縦の余白を同じ値で保つ`
  - `docs(theme): 画面の直書きの余白をやめた後の例外と書き方を足す`

## Interfaces and Dependencies

- テーマと画面の約束: 画面の HTML は余白を書かず、class だけを持つ。余白の値はテーマの `style.css` が持つ。外部のテーマも、新しく足す class に対して `theme-spacing.md` の決まりで規則を書く必要がある（M3 で文書に足す）
- `manager/media/script/tabpane.js`（`WebFXTabPane`）: タブの組の HTML を作る。計測でタブを切り替えるときのクリック先（`.tab-row .tab`）
- Docker の検証環境と Playwright 1.48.2（adaptive-validation-runner）
