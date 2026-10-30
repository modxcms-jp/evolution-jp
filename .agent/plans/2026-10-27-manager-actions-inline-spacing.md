# ExecPlan: 管理画面の各画面が直書きした縦の余白をやめ、テーマの決まりで揃える（Issue #924）

## Purpose / Big Picture

管理画面の各画面（`manager/actions/` の PHP）が `style` 属性や埋め込みの `<style>` で書いている「ブロックの間の縦の余白」を消し、テーマの `style.css` の決まり（`assets/docs/theme-spacing.md`）で揃える。どのテーマでも、どの画面のどのタブでも、ブロックの間が決まりの値（`--space-3` = 12px など）になり、テーマ側だけで余白を直せるようになる。RevoStyle は今の見た目のまま変えない。

## Progress

- [x] (2026-10-27) ExecPlan を作った。直書きの棚卸し（Context and Orientation の表）まで済み
- [x] (2026-10-30) M1: 修正前の計測（7 テーマ × 17 画面 × 全タブ = 329 パネル）と、棚卸しの仕分けの確定。結果は `<work>/spacing/m1-base/`、消した場合の差は `<work>/spacing/probe/probe.json`
- [x] (2026-10-30) M2 の手順 0: 本人の確認（相談事項 1〜4。すべて推奨どおり。Decision Log を参照）
- [x] (2026-10-31) M2: 画面の HTML から直書きの余白を消し、6 テーマに決まりの規則を足す。RevoStyle には今の値を保つ規則を足す。修正後の計測は `<work>/spacing/m2-test/`（6 テーマは表の期待と一致、RevoStyle は `m1-base` と同じ）
- [ ] M3: 通しの計測と、`theme-spacing.md` の例外と書き方の仕上げ

## Surprises & Discoveries

計画を作るときの調査で分かったこと（2026-10-27、根拠は該当ファイルの行）。

- `manager/actions/document/document_data.static.php` の 206〜212 行の埋め込みの `<style>` は、`h3 { padding-bottom: 0; margin-bottom: 0 }` を範囲を絞らずに書いている。シェル化で管理画面は 1 つの文書（ツリーやメニューと同じ `document`）になったので、この規則は `a=3` を開いている間 `#mainPane` の中のすべての `h3` に効き、決まりの `.sectionBody h3`（下 `--space-3`）を打ち消す。
- エディタ型の 5 画面（`mutate_templates`・`mutate_snippet`・`mutate_htmlsnippet`・`mutate_module`・`mutate_plugin` の各 `.dynamic.php`。`mutate_tmplvars` は無い）は、コード欄の上の帯を同じ `style="padding:3px 8px; overflow:hidden;zoom:1; background-color:#eeeeee; border:1px solid #c3c3c3; border-bottom:none;margin-top:5px;"` で書いている。縦の余白（`margin-top:5px`）のほかに、色と枠も直書きで、テーマの色の変数（`assets/docs/theme-variables.md`）にも従っていない。
- `manager/actions/report/sysinfo.static.php` の 99 行は `.sectionBody` 自身に `style="padding:10px 20px;"` を書いている。`.sectionBody` の内側の余白はテーマが決めてよい値（`theme-spacing.md` の対象外の項）なのに、画面の HTML がテーマの値を上書きしている。
- `manager/actions/tool/import_site.static.php` の 33 行は `.sectionBody` に `style="margin:8px;"` を書いている（外側の余白。ブロックの間に当たる）。
- `manager/actions/element/resource_selector.static.php`（`a=84`）は、`window.close()` で閉じる別窓の画面で、`#mainPane` の中に入らない。決まりの対象外（ポップアップ）。
- `manager/actions/report/phpinfo.static.php`（`a=200`）の埋め込みの `<style>` は、PHP の `phpinfo()` が出す HTML を整えるもので、`.phpinfoReport` の中に閉じている。

M1 の計測（2026-10-30、main の 90 コミット後の作業ツリー。検証環境 `evo-validate-924`、127.0.0.24、mailpit だけ 18025 番）で分かったこと。

- 棚卸しの表は今の main でも合っている（行番号だけ動いた: `files.dynamic.php` の `#uploader` は 375→404 行、`div` の `padding:10px 20px` は 438→467 行）。増えたのは、対象外に当たる `publish_draft.dynamic.php` 61 行と `mutate_content/fields.php` の `td`/`span` の `margin:0`（文字の小さな表示）、`resource_selector` 160 行の `margin:1em`（別窓）、`mutate_templates` 392 行の `$divstyle`（枠・色・`padding:1em` の入れ物）、`mutate_plugin` 147 行の引数の表（`margin-top:20px;margin-bottom:20px`、JS の表）。埋め込みの `<style>` は 20 ファイルのまま
- `document_data.static.php`（a=3）の埋め込みの `h3` の規則は、今は何にも当たらない。a=3 の `#mainPane` の中に `h3` が 1 つも無い（id=1・2・5 で確認）。消しても見た目は変わらない（先々 `h3` を足したときに決まりを打ち消す芽を摘む作業）。計画の「`.sectionBody h3` を打ち消す」は、今は起きていない
- 計測のための環境の整備が要った。`use_udperms` が 0 だと a=91 は権限エラー（a=40 も中身が空）。管理ユーザーが 1 人だけだと `messages` 権限が 0 にされ、a=10 は権限エラーの警告になる（`ex_managerapi.php` 522 行）。検証環境に、`use_udperms=1`、文書グループ・ユーザーグループ・ウェブグループを各 1 つ、2 人目の管理ユーザー `second` を SQL で足した（リポジトリの外。環境を作り直したら同じ手順が要る）
- a=133（下書きの公開）は、下書きが無くても画面が出る（`id=1`）。下書きを作らずに測った
- 直書きを消した場合の差は、画面ごとに JS で外して測った（`probe.js`。コードは変えていない）。主な結果は Context and Orientation の表の「M1 の結果」。分かったこと:
  - 消すだけで見た目が変わらない: a=40・a=91 の `form` の `margin: 0px`、a=31 の `#uploader` の `margin-top:10px`（前のブロックの下の 12px に相殺される）、a=133 の `div` の `margin-bottom:1em`（12px = 決まりと同じ）、a=3 の `<style>`
  - 消すと間が 0 になり、規則が要る: a=93 の 2 か所、`mutate_templates` の `div` と帯、4 つのエディタの帯（表の下の 5px が 0 になる）、a=13 の `ul`（12px が 0 になる）、a=16 の `ul.actionButtons`（15px が 0 になる）、a=83 の `#exportNotice`（上下の 12px が 0 になる）
  - 表のセルの中: a=10 の `ul.actionButtons`（textarea の下の 15px が 0 になり、ボタンが欄に付く）、a=301 の `padding-top:8px`（ウィジェット設定の行は、種類によって出る。隠れた行）、`mutate_plugin` の `.split`（AromaHop・SakuraFlow では `display:none`。そのほかの 4 テーマで 10px ずつ）
  - `import_site` の `margin:8px` は縦の間を変えない（相殺される）が、横は変わる。AromaHop・SakuraFlow は `.sectionBody` の外側の横が 8px → 0（幅 1148 → 1164）、RevoStyle は 8px → 15px。RevoClassic・AuroraFlow・RubberWhite・SoftSlate は変わらない（テーマの値が 8px）
  - `sysinfo` の `.sectionBody` の `padding:10px 20px` を消すと、テーマの `.sectionBody` の値に変わる: AromaHop・SakuraFlow は 20px（縦 +10px ずつ、横は同じ）、AuroraFlow・RevoClassic は 16px（縦 +6px ずつ、横 -4px ずつ）、RubberWhite・SoftSlate・RevoStyle は 15px（縦 +5px ずつ、横 -5px ずつ）
  - `sysinfo` の表の `margin-bottom:20px` を消すと、次の見出し（`h4`）までが 20px → 12px（RevoStyle は 6px になるので 20px を保つ規則が要る）
  - `:has(+ X)` の規則は、`bkmanager` のスナップショットのタブで当たらない: `form` と次の `.sectionHeader` の間に `<style>` があるため（`theme-spacing.md` に書いてある限界）。`<style>` を動かすか、class を付ける

M2（2026-10-31）で分かったこと。

- 足した `:is(.sectionBody, .tab-page, form) > :has(+ :is(ul, div).actionButtons)` は、表に無い画面の「ブロック → ボタン列」の間も 12px にする。計測で確かめたのは a=83（`table.settings` → `ul.actionButtons`、0→12）と a=95（`form` の中の表 → `ul.actionButtons`、0→12）。自己レビューでは、ほかに a=76 の全タブ（`resources.static.php`）、a=75・a=99（ユーザー管理）、a=86（ロール管理）、ユーザー編集のアクセスタブ（グループが無いとき）、docmanager の並べ替えタブでも、前のブロックの下が 0 または約 6px から 12px になる見込みと分かった（M3 で測る）
- RevoStyle の a=13 の保持の規則は、ボタン列が `form[name="logging"]` の中ではなく、タブの組（`#logPane`）の直下にあるので、`#logPane > ul.actionButtons` に直した（63a030805）
- a=95 の結果画面（インポートの実行後）には、`:has()` で見分けられる要素が無い。RevoStyle で今の `margin:8px` を保つため、`.sectionBody` に `id="importSite"` を付けた（3a5926e6c）
- `bkmanager.static.php` のスナップショットのタブは、`<div class="actionButtons">` が `</form>` の前で閉じておらず、HTML のパーサの補正でスナップショットの一覧が `#tabSnapshot` の中に入る。今回の `<style>` の移動と `.tab-page > :has(+ .sectionHeader)` は補正後の DOM が前提。閉じタグの欠けは元からのもので、直していない

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
- 2026-10-30 / yamamoto（worker、M1。本人の確認は M2 の手順 0 で取る）: M1 の仕分けの案を Context and Orientation の表のとおりにする。
  - 消すだけ: a=3 の `<style>`、a=40・a=91 の `form` の `margin: 0px`、a=31 の `#uploader`、a=133 の `div`、`sysinfo` の表、a=83 の `dl`
  - 規則を足す: 既存の `:has(+ X)` の並びを広げ、class は `.code-editor` だけを新しく付ける見込み。a=13 は `.tab-pane > ul.actionButtons` の `margin-top`
  - 対象外に足す: a=301 の `padding-top:8px`、`mutate_plugin` の `.split`。a=40・a=91 の `li` の中の `form` の `margin-top: 0.5em` と、a=10 の `ul.actionButtons` は相談事項 2 に回す
  - 根拠: M1 の計測（`<work>/spacing/probe/probe.json`）。相談事項の材料は Surprises & Discoveries と表
  - 代替案: `.tab-pane > ul.actionButtons` を前の兄弟の `margin-bottom` で作る案は、最後の `.tab-page` が非表示のとき当たらないので採らない
- 2026-10-30 / yamamoto（本人）: M1 の確認。相談事項 1〜4 はすべて推奨どおりにする。
  1. `sysinfo` の `.sectionBody` の `padding:10px 20px` は消し、テーマの内側の余白に任せる。a=95 の `margin:8px` も消す。RevoStyle は今の値を保つ
  2. 対象外の仕分けは表のとおり（a=40・a=91 の `li` の中の `form` の `margin-top: 0.5em`、a=301 の `padding-top:8px`、`mutate_plugin` の `.split`、小さな `margin:0` 類）。a=10 の返信欄の `ul.actionButtons` は class `.messages-compose-actions` を付け、`margin-top: var(--space-3)` にする
  3. エディタの帯の色と枠は別の Issue に分ける。この PR は帯の上の余白だけを扱う
  4. 新しく付ける class は `.code-editor` と `.messages-compose-actions` だけにし、ほかは既存の `:has(+ X)` の並びを広げて作る。TV が無いときの文字は `<p>` で包む
- 2026-10-31 / yamamoto（runner、M2 の自己レビュー）: 足した `:has(+ :is(ul, div).actionButtons)` の規則の範囲は絞らない。表に無い画面の間も決まり（12px）に近づくが、決まりを横断して当てる方針（AGENTS.md の「局所的な新方式導入は禁止」）に合う。代わりに M3 の通しの計測に、その画面（a=76 の全タブ、a=75、a=99、a=86、ユーザー編集のアクセスタブ、docmanager の並べ替えタブ）を足し、PR に「表に無い変化」として書く
  - 代替案: 規則の親を画面の class に限る。画面ごとに例外が増え、決まりが散らばるので採らない
- 2026-10-31 / yamamoto（runner）: a=95 の RevoStyle の保持のため、`.sectionBody` に `id="importSite"` を足した。M1 の確認の決定 4（新しい class は 2 つだけ）の例外ではなく、class は増やしていない。結果画面に目印が無いため。M3 の報告で本人に伝える

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

棚卸し（2026-10-27 に作り、2026-10-30 の M1 で今の main に合わせて仕分けを確定した。ブロックの間に当たるものだけ。行番号は 2026-10-30）。

「消した場合」は、6 テーマ（RevoStyle を除く）で直書きを JS で外して測った差（`<work>/spacing/probe/probe.json`）。「期待する間」は、M2 の後に 6 テーマで測って一致を見る値（px、±0.5px）。RevoStyle は「保つ」に書いた値を、RevoStyle の `style.css` の凍結の規則で保つ。

| 画面 | ファイル（行） | 直書き | 消した場合（M1 の結果） | 決めた扱い | 期待する間 | RevoStyle で保つ値 |
| --- | --- | --- | --- | --- | --- | --- |
| a=53 システム情報 | `report/sysinfo.static.php`（99 行） | `.sectionBody` の `padding:10px 20px` | テーマの値に変わる。AromaHop・SakuraFlow は 20px（縦 +10 ずつ、横は同じ）、AuroraFlow・RevoClassic は 16px（縦 +6、横 -4）、RubberWhite・SoftSlate は 15px（縦 +5、横 -5） | 消す（相談事項 1） | 内側の余白はテーマの値（上の値のとおり） | `padding:10px 20px` |
| 同（2 枚目のタブ） | 同（129・135・148 行） | `<table style="margin-bottom:20px;">` ×3 | 表の下が 20px → 12px（次の `h4` まで） | 消すだけ | 表の下 12 | 20px（消すと 6px になる） |
| a=93 バックアップ（復元のタブ） | `tool/bkmanager.static.php`（294 行） | `div.actionButtons` の `margin-top:10px` | ファイル欄の `div` とボタンの間が 10 → 0 | 規則を足す。前の兄弟の下に `form > :has(+ div.actionButtons)`（既存の `form > :has(+ …)` の並びに足す） | 12 | `margin-top:10px` |
| 同（スナップショットのタブ） | 同（325 行） | `margin-top:10px;margin-bottom:10px` | 表とボタンの間 10 → 0、ボタンと次の `.sectionHeader` の間 10 → 0 | 規則を足す。上は上と同じ規則。下は `form` と `.sectionHeader` の間に `<style>` が挟まって `.tab-page > :has(+ .sectionHeader)` が当たらないので、`<style>` を `form` の前へ移す（class は増やさない） | 上 12、下 12 | `margin-top:10px;margin-bottom:10px` |
| a=95 インポート | `tool/import_site.static.php`（33 行） | `.sectionBody` の `margin:8px` | 縦の間は変わらない。横が変わる（AromaHop・SakuraFlow は 8px → 0、幅 1148 → 1164。RevoClassic・AuroraFlow・RubberWhite・SoftSlate はテーマの値が同じ 8px で変わらない） | 消す（相談事項 1 に並べる。横の外側の余白が AromaHop・SakuraFlow で変わる） | 縦は変わらない | `margin:8px` |
| a=40・a=91 アクセス権限 | `permission/access_permissions.dynamic.php`・`web_access_permissions.dynamic.php`（5 か所ずつ） | `form` の `margin: 0px` | 差なし（テーマの `form` は 0） | 消すだけ | 変わらない | 不要 |
| 同 | 同（93・96 行） | `li` の中の `form` の `margin-top: 0.5em`（6px） | 項目の間が 8〜9px → 2〜3px | 対象外（リストの項目の間。相談事項 2）。消すなら項目の間の規則が要る | 変えない（6px のまま） | 不要 |
| a=10 メッセージ | `permission/messages.static.php`（354 行） | 返信の表のセルの中の `ul.actionButtons` の `margin-top:15px` | textarea の行とボタンの間が 15 → 0（ボタンが欄に付く） | 相談事項 2: セルの中として対象外にするか、class `.messages-compose-actions` を付けて `margin-top: var(--space-3)`（15 → 12）にする。推奨は後者 | 12 | `margin-top:15px` |
| a=16・a=19 テンプレート | `element/mutate_templates.dynamic.php`（105 行） | `div` の `margin-bottom:10px` | 帯の `margin-top:5px` も消すと、名前の `div` とコード欄の間が 10 → 0（片方だけ消すと 5 になる） | 規則を足す（下の `.code-editor`） | 12 | 10px |
| 同（151 行） | 同 | 帯の `margin-top:5px` | 同上 | 同上 | 同上 | 5px |
| 同（273 行） | 同 | `ul.actionButtons` の `margin-top:15px`（タブ 3。TV の一覧の下） | 一覧の `ul` の下の 6px だけになる。TV が無いときは文字の直後で 0 | 規則を足す。TV が無いときの文字を `<p>` で包み、`:is(.sectionBody, .tab-page, form) > :has(+ ul.actionButtons)` の `margin-bottom` で作る | 12 | 15px |
| a=22・a=23 スニペット、a=78・a=77 チャンク、a=108・a=107 モジュール、a=102 プラグイン | `mutate_snippet`（336 行）・`mutate_htmlsnippet`（241 行）・`mutate_module`（387 行）・`mutate_plugin`（539 行） | 帯の `margin-top:5px` | 表の下が 5 → 0 | 規則を足す。コード欄の入れ物（帯とテキスト欄を入れる `div`）に class `.code-editor` を付け、前の兄弟の `margin-bottom`（`:has(+ .code-editor)`）で作る | 12 | 5px |
| 同 | `mutate_plugin`（659・668 行） | `.split` の `margin:10px 0` | AromaHop・SakuraFlow は `display:none`（差なし）。ほかの 4 テーマは 10px ずつ → 0 | 対象外（表の行の中の区切り線。部品の内側） | 変えない | 不要 |
| a=301 テンプレート変数 | `mutate_tmplvars.dynamic.php`（498 行） | ラベルのセルの中の `div` の `padding-top:8px` | ウィジェットの設定の行が出たときだけ。行の中の位置が 8px ずれる | 対象外（表のセルの中。部品の内側） | 変えない | 不要 |
| a=13 ログ | `report/logging.static.php`（188 行） | `ul.actionButtons` の `margin-top:1em;margin-left:5px` | 縦 12 → 0（タブのパネルとボタンの間。RevoClassic は 16 → 4、RubberWhite は 14 → 2） | 縦だけ消す。`.tab-pane > ul.actionButtons` に `margin-top: var(--space-3)` を足す（最後の `.tab-page` が非表示のことがあり、`:has(+ ul)` が当たらないため。上の兄弟が無い作りなので相殺は起きない） | 12（RevoClassic 16・RubberWhite 14 は負の `top` の分で、今と同じ） | `margin-top:12px` |
| a=31 ファイル管理 | `element/files.dynamic.php`（404 行） | `#uploader` の `margin-top:10px` | 差なし（前のブロックの下の 12px に相殺されていた） | 消すだけ | 12 | 10px |
| a=133 下書きの公開 | `document/publish_draft.dynamic.php`（106 行） | `div` の `margin-bottom:1em` | 差なし（12px。決まりと同じ） | 消すだけ | 12 | 12px（消すと 0 になる） |
| a=3 リソースの情報 | `document/document_data.static.php`（206〜212 行） | 埋め込みの `h3 { padding-bottom:0; margin-bottom:0 }` | 差なし（a=3 の中に `h3` が無い。今は何にも当たらない） | 消す | 変わらない | 不要 |
| a=83 エクスポート | `tool/export_site.static.php`（83・144 行） | 埋め込みの `#exportNotice { margin: 1em 0 }`、`#exportResult dl { margin: 0.5em 0 }` | `#exportNotice` は上下 12 → 0。`dl` は上 6 → 0（下は 6 のまま） | `#exportNotice` は消して、下の間を `:has(+ ul.actionButtons)` で作る。`dl` は消すだけ（リセット規則の `margin-bottom` が残る） | notice の下 12、`dl` どうし 6 | `#exportNotice` の `margin: 1em 0` |

M2 で足す規則の見込み（新しい class は `.code-editor` と、相談事項 2 で決まれば `.messages-compose-actions` だけ）:

- `:is(.sectionBody, .tab-page, form) > :has(+ :is(ul, div).actionButtons)`、`:has(+ .code-editor)` の `margin-bottom: var(--space-3)`（`:has` は別の規則に分ける）
- `.tab-pane > ul.actionButtons` の `margin-top: var(--space-3)`（a=13）
- 6 テーマに同じ規則を足す。RevoStyle は上の「RevoStyle で保つ値」の px を、画面ごとの class・要素の規則で書く

対象外にするもの（理由）:

- 表のセルの `padding`（`mutate_settings/tab*.inc.php` の `td colspan="2" style="padding:0;"`、`th`・`td` の `padding-top:5px/10px`、`sysinfo` の `padding-right:30px`、`files.dynamic.php` の `padding-right`、各画面の `table.settings td { padding }`、`bkmanager` の `#mainPane table td { padding: 4px }`）: 部品の内側
- 入力欄・リンク・チェックボックスの余白（`textarea` の `padding:0`、`mutate_module_resources` の `a` の `margin-bottom`、`resource_selector` の `a` の `margin-left` など）: 部品の内側・横の余白
- `fieldset style="width:300px;padding:0;"`（ユーザーの編集）、`div style="padding-left:20px"`: 部品の内側・横の余白
- 並べ替えリスト（`mutate_template_tv_rank`・`mutate_plugin_priority` の `ul.sortableList li`）、親の選択のモーダル（`parent_picker`）、システムログのスクロールする箱の中の項目（`eventlog` の `.system-log-entry` など）: 部品の内側・モーダル。`theme-spacing.md` の例外に「スクロールする箱やリストの項目の間」として書く
- `resource_selector`（a=84、別窓）、`phpinfo`（a=200、`phpinfo()` の出力を整える `.phpinfoReport` の中）: 対象外の画面。`theme-spacing.md` の例外に書く
- `import_site` の `fieldset style="padding:10px;…"`、`files.dynamic.php` 467 行の `div` の `padding:10px 20px`、`mutate_templates` 392 行の `$divstyle`（`padding:1em`・枠・背景色）: 入れ物の内側の余白（色も直書き）。この作業では扱わない
- `mutate_snippet`・`mutate_module`・`mutate_tmplvars`・`mutate_plugin` の JS が組み立てる引数の表（`margin-bottom:3px`、`mutate_plugin` 147 行の `margin-top:20px;margin-bottom:20px` など）、`mutate_plugin` 219 行の `padding-bottom:10px`: 入力部品の一部（M1 で追加）
- `mutate_plugin` の `.split` の `margin:10px 0`、`mutate_tmplvars` 498 行の `padding-top:8px`: 表のセルの中。M1 の計測で、消すと行の中の位置が動くだけで、ブロックの間ではないと確認した（M1 で追加）
- `publish_draft` 61 行・`mutate_content/fields.php` の `margin:0`（小さな文字の表示）、`resource_selector` 160 行の `margin:1em`（別窓）: 縦のブロックの間ではない（M1 で追加）
- エディタ型 5 画面の帯の `padding:3px 8px`（`mutate_templates`・`mutate_plugin`・`mutate_module`・`mutate_htmlsnippet`・`mutate_snippet`）: 帯の内側の余白。色と枠の直書きと一緒に別の Issue で扱う（M1 の確認の決定 3。M3 の照合で追加）

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
   - 「書き方」の「ブロックは margin-top を持たない」に、例外の 2 つ（`.tab-pane > ul.actionButtons`: 最後のタブが非表示でも当たるように、`.messages-compose-actions`: 表のセルの中で前の兄弟が無い）を書く
   - 新しいテーマを作るときの「写す規則の並び」に、M2 で足した 4 つの規則を入れる。`.code-editor` と `.messages-compose-actions` を、テーマと画面の約束として書く。新しい規則の親が `:is(.sectionBody, .tab-page, form)` である理由も書く
   - `:has()` に対応しないブラウザでは、コード欄の前とボタン列の前の間が 0 になることを、限界の項に書くか決める
   - 実行: doc-maintainer の `/doc-audit` を `theme-spacing.md` と、この ExecPlan に行う
   - 期待: 指摘 0 件
   - コミット: `docs(theme): 画面の直書きの余白をやめた後の例外と書き方を足す`
2. 通しで確かめる。
   - 実行: `scripts/smoke.js` と、M2 の手順 6 の計測と比較をもう一度
   - 期待: smoke.js が PASS。比較の結果は M2 の手順 6 と同じ
   - 足して測る画面（M2 の自己レビューで分かった、表に無い変化）: a=76 の全タブ、a=75、a=99、a=86、ユーザー編集（a=12）のアクセスタブ、docmanager の並べ替えタブ。6 テーマで前のブロックとボタン列の間が 12px であり、崩れ（重なり・はみ出し）が無いこと。RevoStyle は変わらないこと
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
- ブランチ: `refactor/924-inline-spacing`（ワークツリー `.claude/worktrees/issue-924`。計画を作ったブランチ `worktree-issue-924-theme-spacing` は Issue に紐づいていないため、計画のコミットをこのブランチへ移した）
- PR: #971（下書き）
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
