# ExecPlan: 同梱テーマの縦の余白を決まりで揃える（Issue #917）

## Purpose / Big Picture

管理画面の同梱テーマで、ページの見出し・操作ボタン・一覧の表・タブとパネルなどの「ブロック」の上下の間を、1 つの決まりで揃える。対象は RevoStyle を除く 6 テーマ。決まりは `assets/docs/` に書き、新しいテーマや外部のテーマも同じ決まりに従えるようにする。色や、余白以外の見た目は変えない。RevoStyle は、今の見た目のまま変えない。

## Progress

- [x] (2026-10-25) ExecPlan を作った（planner）
- [x] (2026-10-25) ユーザーの判断を反映した。RevoStyle は対象外として凍結、RubberWhite は `--space-1..8` に名前を揃える、`common/welcome.tpl` の外枠の余白は消す（M3）、`AGENTS.md` のドキュメントマップへの追加は承認済み。M2 の手順 0 の関門は残す
- [x] (2026-10-25) M1: 計測と決まりの案・文書（578e7618e）。自己レビューの指摘に対応した（`--space-7` が無いテーマの訂正、関門の相談事項の追加）
- [ ] (2026-10-25 計画) M2: 決まりの案に本人の確認を得てから（手順 0）、SakuraFlow・AromaHop（丸いタブの 2 テーマ）の `style.css` を決まりに揃える
- [ ] (2026-10-25 計画) M3: RubberWhite の段の名前を揃え、AuroraFlow・RevoClassic・RubberWhite・SoftSlate の `style.css` と `common/welcome.tpl` を決まりに揃える。7 テーマを通しで確かめる（RevoStyle は前後で変わらないこと）

## Surprises & Discoveries

計画を作るときの調査で分かったこと（2026-10-25、根拠は該当ファイルの行）。

- RevoStyle の `--space-*` は、ほかのテーマと刻みが違う。`--space-0` から `--space-14` まであり、`--space-1: 2px`、`--space-2: 3px`、`--space-3: 4px`、`--space-4: 5px` … `--space-8: 10px` … `--space-14: 28px` となっている（`manager/media/style/RevoStyle/style.css` の 48〜63 行）。共通の `manager/media/style/common/shell.css` は、`--space-2` を 8px のつもりで読む（49 行の `#mainPane` の padding、148〜156 行の `#actions` の位置、215〜269 行のモーダル）。そのため RevoStyle では、`#mainPane` の内側の余白が 3px になっている。RevoStyle は `--shell-main-padding` を定義していない。RevoStyle は対象外にしたので（Decision Log）、この値はそのまま残る。
- RubberWhite は数字の段を持たず、名前の段 `--space-xs: 4px`・`--space-sm: 8px`・`--space-md: 16px`・`--space-lg: 20px`・`--space-xl: 24px` を持つ（`manager/media/style/RubberWhite/style.css` の 159〜163 行。39 か所で参照）。shell.css の余白は既定値（8px など）で表示されている。
- 4px 刻みの段を全部そろえて持つのは、RevoClassic と SoftSlate だけ（`--space-1: 4px` 〜 `--space-8: 32px`）。AromaHop・AuroraFlow・SakuraFlow は、`--space-7`（28px）を定義していない（`grep -cE "^\s*--space-[1-8]\s*:"` が 7 件）。RubberWhite は名前が違う（下の項）。（2026-10-25、M1 の自己レビューで訂正。計画作成時は「5 テーマが 1〜8 を持つ」と誤って書いていた）
- ダッシュボード（`a=2`）の外枠の余白が、テンプレートの `style` 属性に直書きされている。
  - `manager/media/style/common/welcome.tpl` の 2 行目は `<div style="margin:8px;">`。AuroraFlow・RevoClassic・RubberWhite・SoftSlate が使う
  - `manager/media/style/RevoStyle/welcome.tpl` の 2 行目は `<div style="margin: 20px 12px;">`
  - SakuraFlow と AromaHop の `welcome.tpl` は `<div>` だけ
  - そのため、ダッシュボードの上端からタブまでの距離が、テーマによって 8px・16px・23px（RevoStyle は 3px + 20px）と違うはず
  - インラインの `style` は、`!important` を使わない限り `style.css` から上書きできない
- `.dynamic-tab-pane-control .tab-page` の `position: relative; top: …` は、描く位置だけをずらし、レイアウト上の箱は元の場所に残す。そのため、下に続くブロックとの間は、ずらした分だけ広がる（または狭まる）。現在の値は次のとおり。
  - 正の値: AromaHop `12px`（1967 行）、SakuraFlow `var(--space-2)`（1828 行。#912 で 12px から変えた）
  - 0: AuroraFlow、SoftSlate
  - 負の値: RevoClassic `calc(-1 * var(--space-1))`（-4px、1484 行）、RevoStyle `-2px`（1417 行）、RubberWhite `-2px`（1413 行）
- RubberWhite には `.dynamic-tab-pane-control .tab-page` の規則が 2 つある（1408 行と、後ろで見た目を上書きする 2049 行）。
- RevoClassic は、セクションの見出しの帯（`.sectionHeader`。下の枠なし）と本体（`.section .sectionBody`）を、間 0 でつなげている（765〜798 行）。フォルダ型のタブとパネルと同じく、意図して「つなげた組」。
- 操作ボタン群 `#actions` は、shell.css（146〜153 行）で `position: fixed` になり、右上に浮く。縦に積むブロックの流れには入らない。SoftSlate は、幅 1100px 以下で `#actions` を右下へ移し、`#mainPane` の下に 72px の余白を足している（`manager/media/style/SoftSlate/style.css` の 307〜318 行）。最後の項目がボタン群に隠れないようにするための、意図した値。
- SakuraFlow には、決まりの無い px の余白が残っている。例: `.tab-page .sectionHeader { margin: 0 0 12px 0 }`（986 行）、`.dynamic-tab-pane-control.tab-pane { padding-bottom: 20px }`（1840 行）、`.actionButtons { margin-bottom: 15px }`（2051 行）、`ul.actionButtons { padding-bottom: 10px }`（2067 行）。AromaHop も同じ作り（1979 行、2190 行付近）。
- テーマの `style.css` は、`manager/actions/header.inc.php` の 38 行で `media/style/<テーマ>/style.css?<modx_version>` として読み込まれる。

M1 の計測と自己レビューで分かったこと（2026-10-25）。

- `a=40`・`a=107`・`a=108` でタブの上が 6px になる原因は、リセット規則の `p` の margin。リセット規則は `h1, h2, h3, h4, h5, h6, p, pre, blockquote, ul, ol, dl, address { margin: .5em 0 }` で、7 テーマすべての `style.css` にある（SakuraFlow は 292 行、AromaHop は 307 行）。ブロックの間を決めるとき、この規則をどう扱うかを M2 の手順 0 で相談する。
- `a=120`（サブリソース一覧）の 10px は、`manager/actions/document/resources_list.static.php` の `style` 属性で直書きされている。パンくずは 552 行の `<div style="margin-bottom:10px;">`、「チェックしたものを移動」のボタンは 147 行の `<div style="margin-top:10px;">`。このファイルは、計画で決めた書き換えの範囲（6 テーマの `style.css` と `common/welcome.tpl`）の外にある。
- RubberWhite と SoftSlate は、ページの中の操作ボタン列の下が 22px ある。
- M1 の文書のコミットは 578e7618e（`docs(theme): 管理画面テーマの縦の余白の決まりを追加する`）。

## Decision Log

- 2026-10-25 / yamamoto（planner）: 余白の決まりは、新しいファイル `assets/docs/theme-spacing.md` に書く。`assets/docs/theme-variables.md` には「余白は `theme-spacing.md` を見る」旨の 1 行だけを足す。
  - 理由: `theme-variables.md` は「テーマの色の変数」の文書で、テーマと共通部の色の約束を扱う。余白の決まりは、変数の約束（`--space-*`）に加えて、「どのブロックの間をどの段にするか」というレイアウトの決まりと例外の一覧を持ち、主題が違う。1 つの主題は 1 つの文書に置く（SSOT）。
  - 代替案: `theme-variables.md` に節を足し、題を「テーマの変数」に改める案。色の文書の構成（共通部での参照の表）に、余白の決まりの表と例外が混ざって読みにくくなるので採らない。
- 2026-10-25 / ユーザー判断: `AGENTS.md` のドキュメントマップに `theme-spacing.md` の 1 行を足すことを承認した。
  - 根拠: 新しい文書をマップから辿れるようにするため。`theme-variables.md` も同じく載っている。
  - 代替案: マップに載せず `theme-variables.md` からのリンクだけにする案。AGENTS.md の「`assets/docs/` 配下を必ず参照する」の入口から外れるので採らない。
- 2026-10-25 / ユーザー判断: RevoStyle は #917 の対象外にし、今の見た目のまま凍結する。`manager/media/style/RevoStyle/style.css` も `manager/media/style/RevoStyle/welcome.tpl` も変えない。文書には「RevoStyle は旧デザインを保つためのテーマで、廃止予定のため決まりの対象外。新しいテーマの手本にしない」と書く。
  - 根拠: RevoStyle は、既存のデザインを変えられない事情がある利用者のためのテーマで、いずれ廃止する。shell.css は `--space-*` を既定値付きで読むので、RevoStyle の今の値（`--space-2` が 3px など）のまま見た目は変わらず、共通部も崩れない。
  - 代替案と、採らない理由:
    - RevoStyle の段を 4px 刻みに定義し直す（計画作成時の推奨だった案 A の RevoStyle 部分）: 見た目が変わり、凍結の方針に反する
    - `--shell-main-padding` だけを足して今の見た目を保つ: RevoStyle に手を入れることになり、凍結の方針に反する
  - 検証: RevoStyle は、計測の値・計算済みの値・スクリーンショットが前後で変わらないことだけを確かめる。
- 2026-10-25 / ユーザー判断: RubberWhite は、余白の段の名前を `--space-1..8`（4px 刻み）に揃える。見た目は変えない。RevoStyle を除くすべてのテーマが `:root` に `--space-1` 〜 `--space-8`（4px × N。`--space-2` が 8px）を持つことを、決まりにする。
  - 根拠: shell.css は、もともとこの名前と値の意味で読んでいる。テーマと共通部の約束を、色の変数と同じく文書にする。
  - RubberWhite の置き換え: `--space-1..8` を足し、`--space-xs/sm/md/lg/xl` の参照を同じ値の数字の段に置き換えて、名前の段を消す。4px は `--space-1`、8px は `--space-2`、16px は `--space-4`、20px は `--space-5`、24px は `--space-6`。
  - 代替案: 共通部が `--space-*` を読むのをやめ、専用の変数（`--shell-*`）だけを読む案。共通部とテーマの約束が増え、テーマ側の段と二重になるので採らない。
- 2026-10-25 / yamamoto（planner）: 決まりは `--space-N` の段で直接書く。「ブロックの間」専用の意味の変数（`--gap-block` など）は作らない。
  - 理由: 決まりの表で、どの間がどの段かを示せば足りる（YAGNI）。
  - 代替案: 意味の変数を作る案。テーマごとに密度を変えたくなったときに、改めて検討する。
- 2026-10-25 / yamamoto（planner）: 修正前の状態は、ブランチを切り替えずに作る。Playwright で `**/manager/media/style/*/style.css*` へのリクエストを横取りし、`git -C <wt> show <base>:manager/media/style/<テーマ>/style.css` の中身を返す。`<base>` は `git -C <wt> merge-base origin/main HEAD`（計画作成時は e58e53e6b）。
  - 理由: runner のワークツリーを main に切り替えずに済み、DB と環境が同じまま、同じスクリプトで前後を比べられる。CSS だけの変更なら、これで main と同じ表示になる。
  - 例外: `common/welcome.tpl` はテンプレートなので横取りできない。M3 で変えた後のダッシュボードの修正前の値には、M1 で未変更のワークツリーから取った計測を使う（失ったときの手順は Idempotence and Recovery）。
- 2026-10-25 / yamamoto（planner）: 対象は、`#mainPane` の中で縦に積むブロックの縦の間だけにする。
  - 対象外: ツリー、上のメニュー、ログイン画面、右クリックメニュー、モーダル、ファイルブラウザ、部品の内側（ボタン・入力欄・表のセル）の余白、横の余白（ロードマップの「非対象」とも合わせる）。
  - ブロックの内側の余白（`.sectionBody` や `.tab-page` の padding）の大きさは、テーマのデザインとして残す。決めるのは、その中の最初と最後の子に外側の margin を足さないことだけ。
- 2026-10-25 / ユーザー判断（Issue より）: 位置をずらす `top` と、場当たりの px は使わない。次のものは例外として文書に挙げ、残す。
  - フォルダ型のタブとパネルの枠をつなげる負の `top`（RevoClassic・RubberWhite。RevoStyle は対象外のまま）
  - 枠でつながる組の間 0（RevoClassic のセクションの見出しの帯と本体など）
  - SoftSlate の狭い画面の `padding-bottom: 72px`
- 2026-10-25 / yamamoto（planner）: shell.css は変えない。ページの上端の余白は、shell.css の `var(--shell-main-padding, var(--space-2, 8px))` のまま（8px）とし、決まりの基準にする。
- 2026-10-25 / ユーザー判断: `manager/media/style/common/welcome.tpl` の外枠の `style="margin:8px;"` を消して `<div>` にし、ダッシュボードの余白を `style.css` の決まりで揃える（M3）。`RevoStyle/welcome.tpl` は触らない。
  - 根拠: ダッシュボードは主な画面の 1 つで、インラインの `style` は `style.css` から揃えられない。SakuraFlow・AromaHop の `welcome.tpl` は、もう外枠に余白を持たない。
  - 代替案と、採らない理由:
    - `style.css` で `!important` を使って上書きする: テーマごとに打ち消しが要り、直書きが残る
    - テンプレートは変えない: ダッシュボードが揃わない
  - 結果: AuroraFlow・RevoClassic・RubberWhite・SoftSlate のダッシュボードでは、外枠の左右の余白（8px）も無くなる。意図した変更として、PR に書く。
- 2026-10-25 / ユーザー判断: M1 の決まりの案への本人の確認（M2 の手順 0 の関門）は残す。`a=40`・`a=107`・`a=108` のタブの上が 6px になる件は、M1 で原因を調べ、関門で相談する。
  - 根拠: 数値の決まりは計測を見てから決めたい。
  - 代替案: 計画の段階で数値まで決めてしまう案。現状の値を見ずに決めることになるので採らない。
- 2026-10-25 / ユーザー判断: マイルストーンは次の 3 つ。runner（exec-plan-runner の `/run-plan`）で、マイルストーンごとに同じ下書きの PR へ push する。
  - ① 計測と決まりの案・文書
  - ② SakuraFlow・AromaHop
  - ③ 残りの 4 テーマ（AuroraFlow・RevoClassic・RubberWhite・SoftSlate）
- 2026-10-25 / yamamoto（planner）: 本人の確認の関門は、M1 の最後ではなく M2 の手順 0 に置く。通る条件は、Decision Log に「M1 の確認」の行があること。
  - 理由: runner は M1 を完了・push して、PR に案を載せてから止まれる。再開したときは記録の有無だけで判断できるので、M1 をやり直すことも、確認済みなのにまた止まることも無い。
  - 代替案: M1 の最後の手順で止まる案。M1 が未完了のまま止まるので、再開したときに runner が M1 を最初からやり直すおそれがあり、採らない。
- 2026-10-25 / yamamoto（planner）: 計測は、幅 1440px・高さ 900px で行う。`#actions` が右上に浮く通常の配置で、SoftSlate の 1100px 以下の配置は例外として扱う。位置は `getBoundingClientRect()` の値を 0.5px 単位に丸めて比べ、決まりとの差が ±0.5px 以内なら一致とみなす（`em` で決まる高さなどで端数が出るため）。

## Outcomes & Retrospective

実装後に記載。

## Context and Orientation

用語:

- テーマ: 管理画面の見た目の一式。`manager/media/style/<テーマ名>/` にあり、`style.css` が本体。どのテーマを使うかは、システム設定 `manager_theme`（DB の `modx_system_settings` の行。既定は `RevoClassic`）で決まる。同梱は AromaHop・AuroraFlow・RevoClassic・RevoStyle・RubberWhite・SakuraFlow・SoftSlate の 7 つ。このうち RevoStyle は、旧デザインを保つための廃止予定のテーマで、この作業の対象外
- シェル: 管理画面の外枠。上のメニュー `#topMenu`、左のツリー `#treePane`、右の作業領域 `#mainPane` から成る。各画面の中身は `#mainPane` に差し込まれる。見た目の土台は `manager/media/style/common/shell.css`
- ブロック: `#mainPane` の中で縦に積まれる塊。ページの見出し（`h1`）、セクション（`.section`。見出しは `.sectionHeader`、本体は `.sectionBody`）、ページの中の操作ボタン列（`ul.actionButtons`）、一覧の表、タブの組（`.tab-pane`。中にタブの列 `.tab-row` とパネル `.tab-page`）など
- タブとパネル: `manager/media/script/tabpane.js`（`WebFXTabPane`）が、`.tab-pane` の中に `.dynamic-tab-pane-control`・`.tab-row`・`.tab-page` を作る。丸いタブ（SakuraFlow・AromaHop）は、タブとパネルが離れて見える。フォルダ型（RevoClassic・RubberWhite。対象外の RevoStyle も同じ作り）は、タブの下端とパネルの上端の枠を負の `top` で重ねてつなげる
- `--space-N`: テーマが `:root` で定義する、余白の段の変数。この作業で、RevoStyle を除く全テーマが `--space-1: 4px` 〜 `--space-8: 32px`（4px 刻み）を持つようにする（今は RubberWhite が名前の段で、AromaHop・AuroraFlow・SakuraFlow は `--space-7` が無い。Surprises & Discoveries を参照）
- 縦の間: 縦に並んだ 2 つのブロックのうち、上のブロックの下端（枠を含む）から、下のブロックの上端（枠を含む）までの距離。入れ物とその最初の子の間は、入れ物の上の枠の内側から子の上端までの距離（入れ物の上の内側の余白と、子の上の margin の和）

関係するファイル（パスはリポジトリのルートから）:

- `manager/media/style/{AromaHop,AuroraFlow,RevoClassic,RubberWhite,SakuraFlow,SoftSlate}/style.css`: 書き換えの対象
- `manager/media/style/common/welcome.tpl`: ダッシュボードの中身（AuroraFlow・RevoClassic・RubberWhite・SoftSlate が使う）。M3 で外枠の `style="margin:8px;"` を消す
- `manager/media/style/RevoStyle/style.css`・`manager/media/style/RevoStyle/welcome.tpl`: 対象外。変えない（計測では、前後で変わらないことだけを確かめる）
- `manager/media/style/{AromaHop,SakuraFlow}/welcome.tpl`: 自前のダッシュボード。外枠の余白は既に無い。変えない
- `manager/media/style/common/shell.css`: `#mainPane` の内側の余白（49 行）と、`#actions` の固定（146〜153 行）。変えない
- `manager/media/style/common/block_section.tpl`・`block_tabpane.tpl`・`block_tabpage.tpl`: セクションとタブの組の HTML の雛形。構造を知るために読む。変えない
- `manager/actions/document/resources_list.static.php`（160〜222 行）: サブリソース一覧（`a=120`）。`h1`、`#actions`、`.section > .sectionBody > ul.actionButtons`、パンくず、`form#mutate` の表の順に並ぶ
- `assets/docs/theme-variables.md`: #877 で作った色の決まり。文書の書き方の前例
- `assets/docs/site-url.md`: 80 番以外のポートで検証環境を立てるときの、サイトのURL（`fixed_site_url`）の扱い
- `.claude/skills/adaptive-validation-runner/`: Docker の検証環境と、Playwright の共通ヘルパー（`scripts/lib.js` の `login()`・`sql()`・`appExec()`）

前提:

- #877（色の変数）と #912（SakuraFlow の未定義の変数と、タブとパネルの間を `var(--space-2)` にした修正）は、main に入っている。
  - #912 では、タブを使う 27 画面で、SakuraFlow のタブの下端からパネルの上端までが 12px から 8px になったことを測った。
  - そのとき `a=40`・`a=107`・`a=108` は、タブの上の余白が 6px だった（変更前からの差。原因は M1 で調べる）。
- DocumentParser（`manager/includes/document.parser.class.inc.php`）の `executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()` には影響しない。変えるのは、管理画面の CSS、`common/welcome.tpl` の外枠の属性、文書だけ。
- キャッシュ:
  - サーバー側のキャッシュには影響しない（管理画面のテンプレートは、サイトのキャッシュに入らない）。
  - ブラウザは `style.css?<modx_version>` を版の文字列で覚えるので、版が上がるまで古い CSS が残ることがある。リリースで版が上がれば解消するので、この作業では扱わない。

## Plan of Work

M1 では、手を入れる前の 7 テーマを同じ条件で計測し、現状の縦の間を「テーマ × 画面 × ブロックの組」の表にする。RevoStyle も測るが、これは修正後と比べて変わらないことを確かめるための基準で、決まりの案の材料には使わない。残りの 6 テーマの表から、テーマをまたいで多い値・意図した組（タブとパネル、見出しの帯と本体）・場当たりの値を仕分け、決まりの案を作る。案の出発点は次のとおりで、数値は計測の結果を見て決める。

- ページの端と最初のブロック: shell.css の `--space-2`（8px）。テーマは余白を足さない（最初のブロックの上の margin は 0）
- ブロックとブロックの間（見出しと次のブロック、セクションどうし、セクションとタブの組、操作ボタン列と表など）: 1 つの段に揃える（候補は `--space-2` か `--space-3`）
- 丸いタブのタブとパネルの間: ページの端と同じ `--space-2`（#912 の値）。フォルダ型は負の `top` で重ねる（例外）
- 入れ物（`.sectionBody`・`.tab-page`）の中: 内側の余白（テーマのデザイン）だけで間を作り、最初の子の上・最後の子の下に外側の margin を足さない
- 間は下側の margin（`margin-bottom`）だけで作り、上側の margin と重ねない（margin の相殺で値が読めなくなるのを避けるため）
- 例外: フォルダ型のタブとパネルの負の `top`、枠でつながる組の間 0、SoftSlate の狭い画面の `#actions` 用の余白

文書 `assets/docs/theme-spacing.md` には、次の順に書く。構成は `theme-variables.md` にならう。

1. 段の約束: `--space-1..8` の値と、共通部が読む変数
2. 決まりの表: どの間をどの段にするか
3. 例外の一覧
4. 対象外のテーマ: RevoStyle（旧デザインを保つための廃止予定のテーマで、新しいテーマの手本にしない）
5. 書き方: `top` でずらさない、px を直書きしない、余白を変えるときは `padding-block` などで縦だけを書く
6. 新しいテーマを作るときの確かめ方

M1 の最後に、次の内容を下書きの PR に載せる。

- 計測の表
- 決まりの案
- テーマごとに直す箇所の見込み
- `a=40`・`a=107`・`a=108` の 6px の原因と、扱いの案

M2 の最初の手順を「本人の確認を得てから行う」関門にする。確認で決まったことを Decision Log と文書に反映してから、テーマの書き換えに入る。

M2 では、決まりを最初に丸いタブの 2 テーマへ当てる。SakuraFlow は #912 で一部を直してあり、AromaHop は同じ作りに `top: 12px` が残っているので、2 つを並べて直すと差分を見比べやすい。

- `.tab-page` の `top` は消す。`position: relative` と `z-index` は、重なりの順のために残す
- タブとパネルの間は、`.tab-row` か `.tab-page` の margin で作る
- ほかのブロックの間も、決まりの段の `margin-bottom` に書き換える

M3 では、次の順に進める。

1. RubberWhite の段の名前を `--space-1..8` に揃える。見た目を変えない置き換えなので、余白を揃える変更とは別のコミットにする
2. AuroraFlow・RevoClassic・RubberWhite・SoftSlate を、M2 と同じ方針で揃える
3. `common/welcome.tpl` の外枠の余白を消す
4. 7 テーマを通しで計測し、文書の例外と表を実際の値に合わせて仕上げる。RevoStyle は、前後で変わらないことを確かめる

各マイルストーンの検証では、修正前（横取りで返す main の CSS）と修正後の計測とスクリーンショットを比べる。縦の間が決まりどおりになったことと、縦の余白以外の計算済みの値（computed style）が変わらないことを確かめる。縦の余白以外の値とは、色・枠・角の丸み・影・文字・横の余白など。

## Concrete Steps

記号:

- `<wt>`: 作業ツリーのパス（runner のワークツリー。このブランチ）
- `<work>`: リポジトリの外の作業用ディレクトリ（例: `/tmp/evo-917-spacing`）
- `<base>`: `git -C <wt> merge-base origin/main HEAD`（計画作成時は e58e53e6b）

検証環境は、名前 `evo-validate-917`、アドレス `127.0.0.17`、ポート 80 とする。docker compose と同梱スクリプトを実行するたびに、先頭に `EVO_VALIDATE_NAME=evo-validate-917 EVO_BIND_IP=127.0.0.17` を付ける。付け忘れると、別のセッションの環境を作り直してしまうおそれがある。

### M1: 計測と決まりの案・文書

1. 検証環境を立てる（adaptive-validation-runner の `references/environment.md` に従う）。
   - 実行:
     - 同じ名前の環境が無いことを確かめる: `docker ps -a --filter label=com.docker.compose.project=evo-validate-917 --format '{{.Names}}\t{{.Label "com.docker.compose.project.working_dir"}}'`
     - アドレスが空いていることを確かめる: `ss -ltn | grep -E '(127\.0\.0\.17|0\.0\.0\.0|\*|\[::\]):(80|8025)\b'`
     - `<wt>` で起動する: `EVO_VALIDATE_NAME=evo-validate-917 EVO_BIND_IP=127.0.0.17 docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml up -d --build`
     - Playwright を `<work>/pw` に入れる: `npm install playwright@1.48.2` と `npx playwright install chromium`
     - サイトを入れる: `EVO_VALIDATE_NAME=evo-validate-917 EVO_BIND_IP=127.0.0.17 NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/setup-site.js --sample`
     - 同じ前置きで `scripts/smoke.js` を流す
   - 期待:
     - smoke.js がすべて PASS する
     - 80 番が使えず別のポートにした場合は、`fixed_site_url` がポート込みの URL のままであること（空にすると、ログイン後に 80 番へ飛ぶ。`assets/docs/site-url.md`）
2. 計測に使う ID を決める。
   - 実行: `docker exec evo-validate-917-db mariadb -uuser -ppassword evodb -N -e "SELECT id FROM modx_site_content WHERE isfolder=1 AND deleted=0 ORDER BY id LIMIT 1; SELECT id FROM modx_site_modules ORDER BY id LIMIT 1; SELECT COUNT(*) FROM modx_user_settings WHERE setting_name='manager_theme'"`
   - 期待:
     - 1 行目: 子を持つリソースの ID（`<folder>`）
     - 2 行目: モジュールの ID（`<module>`）。無ければ、`a=108` の代わりに `a=107` を測る
     - 3 行目: 0（利用者ごとのテーマの上書きが無い）
3. 計測スクリプトを `<work>/spacing/measure.js` に書く。リポジトリには置かない。`scripts/lib.js` を `require` し、書き方は `scripts/smoke.js` にならう。
   - 入力（環境変数）:
     - `THEMES`: 既定は 7 テーマすべて
     - `STATE`: `base` か `work`
     - `OUT`: 出力先（`<work>/spacing/<STATE>/`）
   - テーマごとの準備:
     - `sql("REPLACE INTO modx_system_settings (setting_name,setting_value) VALUES ('manager_theme','<テーマ>')")` と `appExec(['php','evo','cache:clear'])` で、テーマを切り替える
     - `login()` し、`page.setViewportSize({ width: 1440, height: 900 })` とする
     - `STATE=base` のときは、`page.context().route('**/manager/media/style/*/style.css*', …)` で、`git -C <wt> show <base>:manager/media/style/<テーマ>/style.css` の中身を `text/css` で返す
   - 画面ごとの記録:
     - `EvoShell.navigate('index.php?<クエリ>')` で開く（smoke.js と同じく、応答と `#mainPane` の更新を待つ）
     - 計測の値を JSON で `OUT/<テーマ>/<画面>.json` に、`#mainPane` のスクリーンショットを `OUT/<テーマ>/<画面>.png` に保存する
     - タブのある画面は、最初のタブのまま測る
   - 測る 15 画面:
     - `a=2`（ダッシュボード）
     - `a=120&id=<folder>`（サブリソース一覧）
     - `a=27&id=1`（リソースの編集）
     - `a=76`（エレメント管理）
     - `a=17`（システム設定）
     - `a=75`（ユーザー管理）
     - `a=12&id=1`（ユーザーの編集）
     - `a=99`（ウェブユーザー）
     - `a=86`（ロール）
     - `a=106`（モジュール）
     - `a=108&id=<module>`（モジュールの編集）
     - `a=40`（アクセス権限）
     - `a=53`（システム情報）
     - `a=93`（バックアップ）
     - `a=114`（システムログ）
   - 測る要素: `#mainPane` から 5 階層までの、表示されていて高さが 0 でないブロックの要素。
     - 含める: `display` が `block`・`flex`・`grid`・`table`・`list-item` のもの
     - 除く: `position` が `fixed`・`absolute` のもの、`#actions`、`script`・`style`、表のセルの中
   - 要素ごとに記録する値:
     - 要素の道筋（`#mainPane > div.section:nth-of-type(1) > div.sectionBody` の形）
     - `getBoundingClientRect()` の `top`・`bottom`
     - 計算済みの `margin-top/bottom`・`padding-top/bottom`・`border-top/bottom-width`・`position`・`top`
   - そこから出す値: 隣り合う兄弟の縦の間（下の `top` − 上の `bottom`）と、入れ物と最初の子・最後の子の間。
   - 縦の余白以外の値も、同じ要素の道筋ごとに記録する: `color`・`background-color`・`background-image`・`border-*-color`・`border-*-style`・`border-left/right-width`・`border-radius`・`box-shadow`・`font-size`・`font-weight`・`line-height`・`opacity`・`margin-left/right`・`padding-left/right`。
   - 最後に `manager_theme` を `RevoClassic` に戻し、キャッシュを作り直す。
4. 比べるスクリプトを `<work>/spacing/compare.js` に書く。
   - 2 つの出力先（例: `base` と `work`）を読み、次を出す。
     - (a) テーマ × 画面 × ブロックの組ごとの縦の間の表（Markdown）
     - (b) 縦の余白以外の値が違う要素と、その値の一覧
   - 決まりの表を JSON で渡したときは、次も出す。
     - (c) 決まりと ±0.5px を超えて違う箇所の一覧
   - 決まりの表のファイルは `<work>/spacing/rule.json`。中身は、ブロックの組の種類から期待する px への対応。M1 で作る。RevoStyle は表に含めない（(c) の対象外）。
5. 現状を計測し、決まりの案を作る。
   - 実行:
     - `<wt>` で `EVO_VALIDATE_NAME=evo-validate-917 EVO_BIND_IP=127.0.0.17 STATE=work OUT=<work>/spacing/m1-work NODE_PATH=<work>/pw/node_modules node <work>/spacing/measure.js` を流す。ワークツリーは未変更なので、main と同じ
     - 同じく `STATE=base OUT=<work>/spacing/m1-base` でも流す
     - `node <work>/spacing/compare.js <work>/spacing/m1-base <work>/spacing/m1-work` で、(b) が空であることを確かめる。横取りの仕組みが、正しく main の CSS を返していることの確認になる
   - 期待:
     - 7 テーマ × 15 画面の JSON と PNG がそろう
     - (b) が 0 件
     - 現状の縦の間の表から、テーマをまたいで同じ種類の間（例: `h1` の下、`.section` どうし、操作ボタン列と表、タブとパネル、`.tab-pane` の下）の値のばらつきが読める
     - SakuraFlow のサブリソース一覧で、見出しの枠・操作ボタン列・表の間が不揃いなことが、数値で分かる
     - `a=40`・`a=107`・`a=108` のタブの上が 6px になる原因（どの要素の margin か）が分かる
   - 6 テーマ（RevoStyle を除く）の表から、決まりの案を `<work>/spacing/rule.json` と表にまとめる。`node <work>/spacing/compare.js <work>/spacing/m1-base <work>/spacing/m1-work <work>/spacing/rule.json` の (c) から、テーマごとに直す箇所の見込み（件数と主なセレクタ）を出す。
6. 文書を書く。
   - 編集:
     - `assets/docs/theme-spacing.md` を新しく作る。題は「テーマの縦の余白」。構成は Plan of Work のとおり
       - 決まりの表の列: 間の種類 | 段 | 例（セレクタ）
       - 例外の表の列: 例外 | テーマ | 理由
       - 「対象外のテーマ」の節に、「RevoStyle は旧デザインを保つためのテーマで、廃止予定のため決まりの対象外。新しいテーマの手本にしない」と書く。あわせて、RevoStyle の `--space-*` は刻みが違い、shell.css の余白も今の値（3px など）のままであることを書く
     - `assets/docs/theme-variables.md`: 冒頭の説明の後に「余白の変数と決まりは `theme-spacing.md` を参照」の 1 行を足す
     - `AGENTS.md`: ドキュメントマップの `theme-variables.md` の行の下に、`| \`theme-spacing.md\` | テーマの縦の余白 |` を足す（承認済み）
   - 実行: doc-maintainer の `/doc-audit` を、変更した 3 ファイルに対して行い、指摘を直す。runner の実行中は、確認せずに直してコミットする。
   - 期待:
     - `/doc-audit` の指摘が 0 件
     - `grep -n "theme-spacing" <wt>/AGENTS.md <wt>/assets/docs/theme-variables.md` が 1 件ずつ出る
   - コミット: `docs(theme): 管理画面テーマの縦の余白の決まりを追加する`
7. 本人に見せる内容を用意する。M1 の push のときに、メインが下書きの PR の本文に書く。
   - 現状の縦の間の表（テーマ × 主な間の種類）。全画面の表は長いので、ばらつきの大きい組を中心にする
   - 決まりの案と、テーマごとに直す箇所の見込み
   - SakuraFlow のサブリソース一覧の、修正前のスクリーンショットの所見
   - 相談したいこと: M2 の手順 0 の相談事項 1〜4
   - M1 はここで完了にしてよい。確認の関門は M2 の手順 0 に置く。

### M2: SakuraFlow・AromaHop

0. 決まりの案について、本人の確認を得てから行う（M2 以降のすべての手順の前提）。
   - Decision Log に「M1 の確認」の行（本人が決まりの案を了承した日付と、相談への答え）があれば、確認は済んでいる。手順 1 へ進む。
   - 無ければ、runner はここで止まる。M1 の手順 7 の内容（PR の番号と、下の相談事項）を、「止まった理由: ExecPlan に『本人の確認を得てから行う』と書かれた手順に来た」として返す。
   - 相談事項（M1 の計測と自己レビューから）:
     1. ブロックとブロックの間を `--space-3`（12px）にするか、`--space-2`（8px）にするか
     2. `a=40`・`a=107`・`a=108` でタブの上が 6px になる件。原因はリセット規則の `p` の margin（Surprises）。決まりに含めるか、例外にするか
     3. リセット規則 `h1, h2, h3, h4, h5, h6, p, pre, blockquote, ul, ol, dl, address { margin: .5em 0 }`（7 テーマすべてにある）をどう扱うか
        - A: 対象外にし、決まりはブロックの要素（`.section`・`.sectionBody`・`.tab-pane`・操作ボタン列など）にだけ当てる
        - B: `margin-bottom` だけにし、入れ物の中の `:first-child` の上と `:last-child` の下を 0 にする
     4. SakuraFlow・AromaHop の `.section` で、文字だけの見出し（`.sectionHeader`）から本体（`.sectionBody`）までを、0 にするか `--space-3` にするか
   - 確認を得たら、メインが次を行う。
     - Decision Log に「M1 の確認」の行を書く
     - 文書を答えに合わせて直す。`assets/docs/theme-spacing.md` の冒頭にある「状態: 案」の行は、ここで消す。コミットは `docs(theme): 余白の決まりを確認の結果に合わせて直す`
     - `<work>/spacing/rule.json` も同じ値に直す
   - SakuraFlow・AromaHop の自前の `welcome.tpl` が、外枠の余白を持たないことも確かめる。
1. SakuraFlow を決まりに揃える。
   - 編集: `manager/media/style/SakuraFlow/style.css`。`:root` の `--space-6` の次に `--space-7: 28px;` を足す（段の約束をそろえる）。M1 の (c) に挙がった箇所を、決まりの段の `margin-bottom`（入れ物の内側は `padding-block`）に書き換える。
   - 直す見込みの箇所:
     - `h1` の `margin`
     - `.tab-page .sectionHeader` の `12px`
     - `.section .sectionBody` と `.tab-page .sectionBody` の `margin`
     - `.actionButtons` の `margin-bottom: 15px`
     - `ul.actionButtons` の `padding-bottom: 10px`
     - `.dynamic-tab-pane-control.tab-pane` の `padding-bottom: 20px`
     - `.dynamic-tab-pane-control .tab-page` の `top: var(--space-2)`。消して、間は margin で作る。`position: relative` と `z-index` は残す
   - 横の余白は変えない。`margin: 0 0 X` のような一括の書き方を変えるときは、左右の値を保つ。
   - コミット: `fix(theme): SakuraFlow の縦の余白を決まりに揃える`
2. AromaHop を決まりに揃える。
   - 編集: `manager/media/style/AromaHop/style.css`。方針は手順 1 と同じで、`:root` に `--space-7: 28px;` を足す。`.dynamic-tab-pane-control .tab-page` の `top: 12px`（1967 行）を消し、タブとパネルの間を決まりの段（`--space-2` の見込み）にする。
   - コミット: `fix(theme): AromaHop の縦の余白を決まりに揃える`
3. 確かめる。
   - 実行:
     - 環境が無ければ、M1 の手順 1〜2 で立てる
     - `THEMES=SakuraFlow,AromaHop` で、`STATE=base OUT=<work>/spacing/m2-base` と `STATE=work OUT=<work>/spacing/m2-work` を流す
     - `node <work>/spacing/compare.js <work>/spacing/m2-base <work>/spacing/m2-work <work>/spacing/rule.json` を流す
   - 期待:
     - (c) が 0 件（2 テーマ × 15 画面の縦の間が、文書の例外を除いて決まりどおり）
     - (b) が 0 件（色・枠・角・影・文字・横の余白が、前後で同じ）
     - `grep -n -A14 "^\.dynamic-tab-pane-control \.tab-page {" <wt>/manager/media/style/SakuraFlow/style.css <wt>/manager/media/style/AromaHop/style.css | grep -E "^\S+-\s+top:"` が何も出さない
     - `grep -cE "^\s*--space-[1-8]\s*:" <wt>/manager/media/style/SakuraFlow/style.css <wt>/manager/media/style/AromaHop/style.css` が、どちらも 8 を出す
     - SakuraFlow のサブリソース一覧で、見出しの枠・操作ボタン列・表の間が同じ値になる（`m2-base` と `m2-work` の PNG を並べて見る）
   - M2 で分かった例外があれば、文書に足す。コミットは `docs(theme): 余白の決まりに丸いタブの例外を足す` など（足すものが無ければ不要）。

### M3: 残り 4 テーマ（M2 の手順 0 の確認が前提。改めて止まらない）

1. RubberWhite の段の名前を揃える。
   - 編集: `manager/media/style/RubberWhite/style.css`。
     - `:root` に `--space-1: 4px` 〜 `--space-8: 32px` を足す
     - `--space-xs/sm/md/lg/xl` の参照を、同じ値の数字の段に置き換える
     - 名前の段の定義を消す
   - 実行: `THEMES=RubberWhite` で、`STATE=base` と `STATE=work` を流して比べる。
   - 期待:
     - 縦の間の表も (b) も、前後で同じ（見た目が変わらない置き換え）
     - `grep -nE "\-\-space-(xs|sm|md|lg|xl)\b" <wt>/manager/media/style/RubberWhite/style.css` が何も出さない
   - コミット: `refactor(theme): RubberWhite の余白の変数を共通の段の名前にする`
2. 4 テーマを決まりに揃える。
   - 編集: `manager/media/style/{AuroraFlow,RevoClassic,RubberWhite,SoftSlate}/style.css`。方針は M2 の手順 1 と同じ。AuroraFlow は `:root` に `--space-7: 28px;` を足す（RevoClassic・SoftSlate は既に持ち、RubberWhite は手順 1 で足す）。
   - 残すもの:
     - フォルダ型のタブの負の `top`（RevoClassic・RubberWhite）
     - RevoClassic の、見出しの帯と本体の間 0
     - SoftSlate の、1100px 以下の `padding-bottom: 72px`
   - RubberWhite は、`.tab-page` の規則が 2 つ（1408 行・2049 行）あるので、両方を見る。
   - `manager/media/style/RevoStyle/` の下は変えない。
   - コミット: テーマごとに 1 つ。`fix(theme): AuroraFlow の縦の余白を決まりに揃える` のように、テーマ名だけを変える。
3. ダッシュボードの外枠を揃える。
   - 編集: `manager/media/style/common/welcome.tpl` の 2 行目の `<div style="margin:8px;">` を `<div>` にする。ダッシュボードのタブの組とページの端の間は、手順 2 の `style.css` の決まり（ページの端は shell.css の `--space-2`、タブの組の上の margin は 0）で決まる。足りない間があれば、4 テーマの `style.css` に決まりの段で書く。
   - `manager/media/style/RevoStyle/welcome.tpl` は触らない。
   - 期待: AuroraFlow・RevoClassic・RubberWhite・SoftSlate のダッシュボード（`a=2`）で、ページの端からタブまでの距離が、SakuraFlow・AromaHop と同じ値になる。
   - コミット: `fix(theme): ダッシュボードの外枠の余白を決まりに揃える`
4. 7 テーマを通しで確かめる。
   - 実行:
     - すべてのテーマで、`STATE=base OUT=<work>/spacing/m3-base` と `STATE=work OUT=<work>/spacing/m3-work` を流す
     - `compare.js` に `rule.json` を渡す
     - ダッシュボード（`a=2`）の修正前は、`common/welcome.tpl` を横取りできないので、`<work>/spacing/m1-work` の値と比べる
   - 期待:
     - (c) が 0 件（RevoStyle を除く 6 テーマ × 15 画面）
     - (b) で違いが出るのは、AuroraFlow・RevoClassic・RubberWhite・SoftSlate のダッシュボードの外枠の `margin-left/right`（8px から 0）だけ
     - RevoStyle は、15 画面すべてで縦の間・(b)・PNG が前後で同じ
     - `git -C <wt> diff --stat <base> -- manager/media/style/RevoStyle` が何も出さない
     - `grep -cE "^\s*--space-[1-8]\s*:" <wt>/manager/media/style/{AromaHop,AuroraFlow,RevoClassic,RubberWhite,SakuraFlow,SoftSlate}/style.css` が、どのファイルも 8 を出す
     - `grep -n -A14 "^\.dynamic-tab-pane-control \.tab-page {" <wt>/manager/media/style/*/style.css | grep -E "^\S+-\s+top:"` が、RevoClassic・RevoStyle・RubberWhite の負の値だけを出す
   - 文書の決まりの表と例外を、実際の値に合わせて仕上げる。必要なときだけ行い、コミットは `docs(theme): 余白の決まりを 6 テーマの結果に合わせて仕上げる`。
5. 後片付け。
   - `manager_theme` を `RevoClassic` に戻す。
   - 本人が目視で確かめたいと言っていなければ、`EVO_VALIDATE_NAME=evo-validate-917 EVO_BIND_IP=127.0.0.17 docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml down -v --rmi local` で環境を消す。
   - 検証用に作られた `manager/includes/config.inc.php` も消す。
   - `git -C <wt> status --short` が空であることを確かめる。

## Validation and Acceptance

次をすべて満たしたら完了とする。値は M3 の手順 4 の計測で示し、PR の本文に「条件 × main × このブランチ」の表で書く。

1. `assets/docs/theme-spacing.md` があり、次が書かれている。`AGENTS.md` のドキュメントマップと `theme-variables.md` から辿れる。
   - 段の約束（RevoStyle を除く全テーマが `--space-1..8` を持つ）
   - どの間をどの段にするかの表
   - 例外の一覧
   - RevoStyle が対象外であること（旧デザインを保つための廃止予定のテーマで、手本にしない）
   - 書き方
2. RevoStyle を除く 6 テーマ × 15 画面（M1 の手順 3 の一覧）で、ブロックの縦の間が決まりの表どおり（±0.5px）になっている。
   - 例外は、文書に挙げたものだけ
   - 修正前（main）は、決まりと違う値が出る。例: SakuraFlow のサブリソース一覧の見出しの枠・操作ボタン列・表の間、AromaHop のタブとパネルの間（12px）、`common/welcome.tpl` を使う 4 テーマのダッシュボードの上端（16px）
3. 6 テーマの `style.css` について:
   - `.dynamic-tab-pane-control .tab-page` の `top` は、RevoClassic・RubberWhite の負の値（タブとパネルの枠をつなげる重ね）だけ
   - ブロックの縦の間を作る宣言は `--space-N` で書かれていて、例外を除き、px の直書きが無い
   - RubberWhite に `--space-xs/sm/md/lg/xl` が残っていない
   - 6 テーマすべてが `--space-1` 〜 `--space-8` をそろえて持つ。`grep -cE "^\s*--space-[1-8]\s*:" <wt>/manager/media/style/{AromaHop,AuroraFlow,RevoClassic,RubberWhite,SakuraFlow,SoftSlate}/style.css` が、どのファイルも 8 を出す（main では AromaHop・AuroraFlow・SakuraFlow が 7、RubberWhite が 0）
4. 縦の余白以外の計算済みの値（色・枠・角の丸み・影・文字・横の余白など）が、修正前と同じ。違うのは、`common/welcome.tpl` の外枠の `margin-left/right`（4 テーマのダッシュボード）だけ。
5. RevoStyle は、ファイルの差分が無く、15 画面の計測とスクリーンショットが前後で同じ。
6. 修正前と修正後のスクリーンショットを並べて見て、縦の余白以外の見た目が変わっていない（目視。本人の確認に使う）。

## Idempotence and Recovery

- 計測のスクリプトは、何度流しても同じ出力先を上書きするだけ。テーマの切り替えは `REPLACE INTO` なので、繰り返してよい。途中で止まったら、最後に `manager_theme` を `RevoClassic` に戻し、`docker exec -w /var/www/html evo-validate-917-app php evo cache:clear` を流す。
- 修正前の値は、横取りの仕組み（`STATE=base`）で、いつでも作り直せる。
- `common/welcome.tpl` を変えた後で、ダッシュボードの修正前の値（`<work>/spacing/m1-work`）を失ったときは、次の手順で測り直す。
  1. `git -C <wt> worktree add <work>/base <base>` で、修正前の作業ツリーを `<work>` の下に作る
  2. 別の名前とアドレス（例: `evo-validate-917base`、`127.0.0.18`）で検証環境を立てる
  3. `STATE=work` で計測し直す
  4. 終わったら、その環境を `down -v --rmi local` で消し、`git -C <wt> worktree remove <work>/base` で作業ツリーを外す
- 検証環境は、マイルストーンをまたいで使い回してよい。無くなっていたら、M1 の手順 1〜2 からやり直す。`config.inc.php` だけが残っていたら、`setup-site.js --reinstall` を使う。
- テーマの書き換えはテーマごとのコミットに分けるので、1 つのテーマで問題が出たら、そのコミットだけを `git -C <wt> revert <コミット>` で戻せる。
- M2 の手順 0 で止まった後は、本人の答えを Decision Log の「M1 の確認」の行に書いてから、`/run-plan .agent/plans/2026-10-25-theme-vertical-spacing.md` を流し直す。Progress の最初の未完了である M2 から再開し、手順 0 は記録があるので通る。答えを書かずに流し直すと、手順 0 でまた止まる。

## Artifacts and Notes

- 対象の Issue: #917（関連: #912、#877）
- ブランチ: `fix/917-theme-vertical-spacing`。計画作成時の HEAD は e58e53e6b で、PR はまだ無い。runner が M1 で下書きの PR を作って `Refs #917` を挙げ、最後のマイルストーンで `Closes #917` に改める
- 前例:
  - `assets/docs/theme-variables.md`（#877 の色の決まり）
  - PR #918（#912。SakuraFlow のタブとパネルの間を 27 画面で計測した方法）
- 計測のスクリプトと出力（`<work>/spacing/`）は、リポジトリの外に置き、PR には入れない。結果の表は PR の本文に書く
- 想定のコミット単位（Conventional Commits、日本語）:
  - M1: `docs(theme): 管理画面テーマの縦の余白の決まりを追加する`（`assets/docs/theme-spacing.md`、`assets/docs/theme-variables.md`、`AGENTS.md`）
  - M2:
    - 確認の後に、必要なら `docs(theme): 余白の決まりを確認の結果に合わせて直す`
    - `fix(theme): SakuraFlow の縦の余白を決まりに揃える`
    - `fix(theme): AromaHop の縦の余白を決まりに揃える`
  - M3:
    - `refactor(theme): RubberWhite の余白の変数を共通の段の名前にする`
    - テーマごとに `fix(theme): <テーマ> の縦の余白を決まりに揃える`（AuroraFlow・RevoClassic・RubberWhite・SoftSlate）
    - `fix(theme): ダッシュボードの外枠の余白を決まりに揃える`（`manager/media/style/common/welcome.tpl`）
    - 必要なら `docs(theme): 余白の決まりを 6 テーマの結果に合わせて仕上げる`
  - ExecPlan の記録（Progress・Surprises・Decision Log）は、メインがマイルストーンごとにコミットする

## Interfaces and Dependencies

- 共通部とテーマの約束:
  - shell.css は、`--shell-main-padding`（どのテーマも未定義）と、`--space-2`・`--space-3`・`--space-4` を既定値付きで読む
  - この作業で、RevoStyle を除く全テーマが `--space-1..8` を 4px 刻みで持つことを、文書の約束にする
  - shell.css 自体は変えない
  - RevoStyle は、今の段（`--space-2` が 3px など）のまま、shell.css の余白も今の値で表示され続ける
- タブ: `manager/media/script/tabpane.js`（`WebFXTabPane`）が作る `.dynamic-tab-pane-control`・`.tab-row`・`.tab-page` の構造に依存する。JS は変えない
- 画面の HTML: `manager/actions/` の各画面と `manager/media/style/common/block_*.tpl` が作る、`h1`・`.section`・`.sectionHeader`・`.sectionBody`・`ul.actionButtons`・`#actions` の構造に依存する。HTML は変えない。例外は、`common/welcome.tpl` の外枠の `style` 属性を消すことだけ
- 外部のテーマ:
  - 新しい文書の約束に従えば、共通部と同じ余白になる
  - 既存の外部のテーマは、今と同じく shell.css の既定値で表示され、互換は崩れない
  - `common/welcome.tpl` を使う外部のテーマでは、ダッシュボードの外枠の余白（8px）が無くなる。PR の備考に書く
- 検証:
  - Docker: `compose.yml` と `.claude/skills/adaptive-validation-runner/scripts/compose.validation.yml`（php:8.4-apache と mariadb:11）
  - Playwright 1.48.2（ヘッドレスの Chromium）と `scripts/lib.js`
  - ホストに Chromium の動作に要る OS のライブラリが無いときは、`references/environment.md` の「既知の落とし穴」に従い、本人に `sudo env "PATH=$PATH" npx playwright install-deps chromium` を頼む
- PHP の版や DB の版に関わる変更は無い（CSS・テンプレートの属性・文書だけ）
