# ExecPlan: 同梱テーマの縦の余白を決まりで揃える（Issue #917）

## Purpose / Big Picture

管理画面の同梱 7 テーマで、ページの見出し・操作ボタン・一覧の表・タブとパネルなどの「ブロック」の上下の間を、1 つの決まりで揃える。決まりは `assets/docs/` に書き、新しいテーマや外部のテーマも同じ決まりに従えるようにする。色や、余白以外の見た目は変えない。

## Progress

- [ ] (2026-10-25 計画) M1: 7 テーマ × 主な画面の縦の余白を計測し、余白の決まりの案と文書（`assets/docs/theme-spacing.md`）を作り、下書きの PR に載せる
- [ ] (2026-10-25 計画) M2: 決まりの案に本人の確認を得て（手順 0）、SakuraFlow・AromaHop（丸いタブの 2 テーマ）の `style.css` を決まりに揃える
- [ ] (2026-10-25 計画) M3: AuroraFlow・RevoClassic・RevoStyle・RubberWhite・SoftSlate の `style.css` を決まりに揃え、7 テーマを通しで確かめる

## Surprises & Discoveries

計画を作るときの調査で分かったこと（2026-10-25、根拠は該当ファイルの行）。

- RevoStyle の `--space-*` は、ほかのテーマと段の刻みが違う。`--space-0` から `--space-14` まであり、`--space-1: 2px`、`--space-2: 3px`、`--space-3: 4px`、`--space-4: 5px` … `--space-8: 10px` … `--space-14: 28px`（`manager/media/style/RevoStyle/style.css` の 48〜63 行）。共通の `manager/media/style/common/shell.css` は `--space-2` を 8px のつもりで読む（49 行の `#mainPane` の padding、148〜156 行の `#actions` の位置、215〜269 行のモーダル）。そのため RevoStyle では `#mainPane` の内側の余白が 3px になっている。RevoStyle は `--shell-main-padding` を定義していない。
- RubberWhite は数字の段を持たず、名前の段 `--space-xs: 4px`・`--space-sm: 8px`・`--space-md: 16px`・`--space-lg: 20px`・`--space-xl: 24px` を持つ（`manager/media/style/RubberWhite/style.css` の 159〜163 行。39 か所で参照）。shell.css は既定値（8px など）で表示される。
- 残りの 5 テーマ（AromaHop・AuroraFlow・RevoClassic・SakuraFlow・SoftSlate）は `--space-1: 4px` 〜 `--space-8: 32px`（4px 刻み）を持つ。
- ダッシュボード（`a=2`）の外枠に、テンプレートの `style` 属性で余白が直書きされている。`manager/media/style/common/welcome.tpl` の 2 行目は `<div style="margin:8px;">`（AuroraFlow・RevoClassic・RubberWhite・SoftSlate が使う）、`manager/media/style/RevoStyle/welcome.tpl` の 2 行目は `<div style="margin: 20px 12px;">`。SakuraFlow と AromaHop の `welcome.tpl` は `<div>` だけ。そのため、ダッシュボードの上端からタブまでが、テーマによって 8px・16px・23px（RevoStyle は 3px + 20px）と違うはず。インラインの `style` は、`style.css` からは `!important` なしでは上書きできない。
- `.dynamic-tab-pane-control .tab-page` の `position: relative; top: …` は、描く位置だけをずらし、レイアウト上の箱は元の場所に残る。下に続くブロックとの間は、ずらした分だけ広がる（または狭まる）。現在の値は、AromaHop `12px`（1967 行）、SakuraFlow `var(--space-2)`（1828 行、#912 で 12px から変更）、AuroraFlow `0`、SoftSlate `0`、RevoClassic `calc(-1 * var(--space-1))`（-4px、1484 行）、RevoStyle `-2px`（1417 行）、RubberWhite `-2px`（1413 行）。
- RubberWhite には `.dynamic-tab-pane-control .tab-page` の規則が 2 つある（1408 行と、後ろで見た目を上書きする 2049 行）。
- RevoClassic は、セクションの見出しの帯（`.sectionHeader`、下の枠なし）と本体（`.section .sectionBody`）を間 0 でつなげている（765〜798 行）。フォルダ型のタブとパネルと同じく、意図した「つながった組」。
- 操作ボタン群 `#actions` は、shell.css（146〜153 行）で `position: fixed` にされ、右上に浮く。縦に積むブロックの流れには入らない。SoftSlate は幅 1100px 以下で `#actions` を右下へ移し、`#mainPane` の下に 72px の余白を足している（`manager/media/style/SoftSlate/style.css` の 307〜318 行）。最後の項目がボタン群に隠れないための意図した値。
- SakuraFlow には、決まりの無い px の余白が残る。例: `.tab-page .sectionHeader { margin: 0 0 12px 0 }`（986 行）、`.dynamic-tab-pane-control.tab-pane { padding-bottom: 20px }`（1840 行）、`.actionButtons { margin-bottom: 15px }`（2051 行）、`ul.actionButtons { padding-bottom: 10px }`（2067 行）。AromaHop も同じ作り（1979 行、2190 行付近）。
- テーマの `style.css` は `manager/actions/header.inc.php` の 38 行で `media/style/<テーマ>/style.css?<modx_version>` として読み込まれる。

## Decision Log

- 2026-10-25 / yamamoto（planner）: 余白の決まりは、新しいファイル `assets/docs/theme-spacing.md` に書く。`assets/docs/theme-variables.md` には、余白は `theme-spacing.md` を見る旨の 1 行だけを足し、`AGENTS.md` のドキュメントマップに 1 行足す。理由: `theme-variables.md` は「テーマの色の変数」で、テーマと共通部の色の約束を扱う。余白の決まりは、変数の約束（`--space-*`）に加えて「どのブロックの間をどの段にするか」というレイアウトの決まりと例外の一覧を持ち、主題が別。1 つの主題を 1 つの文書に置く（SSOT）。代替案の「`theme-variables.md` に節を足し、題を『テーマの変数』に改める」は、色の文書の構成（共通部での参照の表）に余白の決まりの表と例外が混ざって読みにくくなるので採らない。
- 2026-10-25 / yamamoto（planner）: 余白の段の約束として、すべてのテーマが `:root` に `--space-1` 〜 `--space-8`（4px × N、`--space-2` が 8px）を定義することを文書に書く。shell.css は既にこの意味で読んでいる。RevoStyle（刻みの違う段）と RubberWhite（名前の段）の扱いは、M2 の手順 0 の本人の確認（「M1 の確認」）で決める。計画としての推奨は次の案 A。
  - 案 A（推奨）: RubberWhite は `--space-1..8` を定義し、`--space-xs/sm/md/lg/xl` の参照を同じ値の数字の段（4px→`--space-1`、8px→`--space-2`、16px→`--space-4`、20px→`--space-5`、24px→`--space-6`）に置き換えて、名前の段を消す（見た目は変わらない）。RevoStyle は `--space-1..8` を 4px 刻みに定義し直し、今の 44 か所の参照は、縦のブロックの間なら決まりの段に、それ以外（ボタンの内側・横の余白など）は今と同じ px の値になるように書き換える（同じ値の段があればその段、無ければ px をそのまま書く）。RevoStyle の `--space-9` 以降は消す。結果として RevoStyle の `#mainPane` の内側の余白は 3px から 8px になる（上下左右とも。共通部との約束を直すための意図した変更として、文書と PR に書く）。
  - 案 B: RevoStyle に `--shell-main-padding: 3px` を足して今の見た目を保ち、段の意味のずれは残す。shell.css のモーダルなども `--space-*` を読むので、ずれが残り続ける。採らない。
  - 案 C: 共通部が `--space-*` を読むのをやめ、専用の変数（`--shell-*`）だけを読む。共通部とテーマの約束が増え、テーマ側の段と二重になる。採らない。
- 2026-10-25 / yamamoto（planner）: 決まりは `--space-N` の段で直接書く。「ブロックの間」専用の意味の変数（`--gap-block` など）は作らない（YAGNI。決まりの表で、どの間がどの段かを示せば足りる）。テーマごとに密度を変えたくなったら、そのとき意味の変数を検討する。
- 2026-10-25 / yamamoto（planner）: 修正前の状態は、ブランチを切り替えずに作る。Playwright で `**/manager/media/style/*/style.css*` へのリクエストを横取りし、`git -C <wt> show <base>:manager/media/style/<テーマ>/style.css` の中身を返す（`<base>` は `git -C <wt> merge-base origin/main HEAD`。計画作成時は e58e53e6b）。理由: runner のワークツリーを main に切り替えずに済み、DB と環境が同じまま、同じスクリプトで前後を比べられる。CSS だけの変更なら、これで main と同じ表示になる。`welcome.tpl` も変えると決まった場合、テンプレートは横取りできないので、ダッシュボードの修正前の値は M1 で未変更のワークツリーから取った計測を使う（失ったときの手順は Idempotence and Recovery）。
- 2026-10-25 / yamamoto（planner）: 対象は `#mainPane` の中で縦に積むブロックの、縦の間だけ。ツリー、上のメニュー、ログイン画面、右クリックメニュー、モーダル、ファイルブラウザ、部品の内側（ボタン・入力欄・表のセル）の余白、横の余白は対象外（ロードマップの「非対象」とも合わせる）。ブロックの内側の余白（`.sectionBody` や `.tab-page` の padding）の大きさはテーマのデザインとして残し、その中の最初と最後の子に外側の margin を足さないことだけを決める。
- 2026-10-25 / yamamoto（ユーザーと決定、Issue より）: 位置をずらす `top` と場当たりの px は使わない。ただし、フォルダ型のタブとパネルの枠をつなげる負の `top`（RevoClassic・RevoStyle・RubberWhite）は、意図した重ねとして残す。同じく、枠でつながる組（RevoClassic のセクションの見出しの帯と本体など）の間 0 と、SoftSlate の狭い画面の `padding-bottom: 72px` は、例外として文書に挙げて残す。
- 2026-10-25 / yamamoto（planner）: shell.css は変えない。ページの上端の余白は shell.css の `var(--shell-main-padding, var(--space-2, 8px))` のまま（8px）とし、決まりの基準にする。
- 2026-10-25 / yamamoto（planner）: ダッシュボードの外枠のインラインの余白（`common/welcome.tpl` の `margin:8px`、`RevoStyle/welcome.tpl` の `margin: 20px 12px`）を消すかは、M2 の手順 0 の本人の確認（「M1 の確認」）で決める。ユーザーと決めた書き換えの範囲は `style.css` だけで、`welcome.tpl` はその外のため。計画としては、消して SakuraFlow・AromaHop と同じ `<div>` にすることを推奨する（ダッシュボードは主な画面の 1 つで、インラインの `style` は `style.css` から揃えられない）。消すと決まったら、M3 の手順 2 で `common/welcome.tpl` と `RevoStyle/welcome.tpl` の外枠を `<div>` にする。
- 2026-10-25 / yamamoto（ユーザーと決定）: マイルストーンは ① 計測と決まりの案・文書（本人の確認まで）② SakuraFlow・AromaHop ③ 残り 5 テーマ、の 3 つ。runner（exec-plan-runner の `/run-plan`）でマイルストーンごとに同じ下書きの PR へ push する。
- 2026-10-25 / yamamoto（planner）: 本人の確認の関門は、M1 の最後ではなく M2 の手順 0 に置き、通過の条件を「Decision Log に『M1 の確認』の行があること」にする。runner は M1 を完了して push し、PR に案を載せてから止まれる。再開したときは記録の有無だけで判断でき、M1 をやり直すことも、確認済みなのにまた止まることも無い。代替案の「M1 の最後の手順で止まる」は、M1 が未完了のまま止まるので、再開時に runner が M1 を最初からやり直すおそれがあり採らない。
- 2026-10-25 / yamamoto（planner）: 計測は幅 1440px・高さ 900px で行う（`#actions` が右上に浮く通常の配置。SoftSlate の 1100px 以下の配置は例外として扱う）。位置は `getBoundingClientRect()` の値を 0.5px 単位に丸めて比べ、決まりとの差が ±0.5px 以内なら一致とみなす（`em` の高さなどで端数が出るため）。

## Outcomes & Retrospective

実装後に記載。

## Context and Orientation

用語:

- テーマ: 管理画面の見た目の一式。`manager/media/style/<テーマ名>/` にあり、`style.css` がその本体。どれを使うかはシステム設定 `manager_theme`（DB の `modx_system_settings` の行。既定は `RevoClassic`）で決まる。同梱は AromaHop・AuroraFlow・RevoClassic・RevoStyle・RubberWhite・SakuraFlow・SoftSlate の 7 つ
- シェル: 管理画面の外枠（上のメニュー `#topMenu`、左のツリー `#treePane`、右の作業領域 `#mainPane`）。各画面の中身は `#mainPane` に差し込まれる。見た目の土台は `manager/media/style/common/shell.css`
- ブロック: `#mainPane` の中で縦に積まれる塊。ページの見出し（`h1`）、セクション（`.section` とその見出し `.sectionHeader`・本体 `.sectionBody`）、ページの中の操作ボタン列（`ul.actionButtons`）、一覧の表、タブの組（`.tab-pane`。中にタブの列 `.tab-row` とパネル `.tab-page`）など
- タブとパネル: `manager/media/script/tabpane.js`（`WebFXTabPane`）が `.tab-pane` の中に `.dynamic-tab-pane-control`、`.tab-row`、`.tab-page` を作る。丸いタブ（SakuraFlow・AromaHop）はタブとパネルが離れて見え、フォルダ型（RevoClassic・RevoStyle・RubberWhite）はタブの下端とパネルの上端の枠を負の `top` で重ねてつなげる
- `--space-N`: テーマが `:root` で定義する余白の段の変数。5 テーマは `--space-1: 4px` 〜 `--space-8: 32px`（4px 刻み）。例外は Surprises & Discoveries を参照
- 縦の間: 縦に並んだ 2 つのブロックの、上のブロックの下端（枠を含む）から下のブロックの上端（枠を含む）までの距離。入れ物とその最初の子の間は、入れ物の上の枠の内側から子の上端まで（入れ物の上の内側の余白と子の上の margin の和）

関係するファイル（パスはリポジトリのルートから）:

- `manager/media/style/{AromaHop,AuroraFlow,RevoClassic,RevoStyle,RubberWhite,SakuraFlow,SoftSlate}/style.css`: 書き換えの対象
- `manager/media/style/common/shell.css`: `#mainPane` の内側の余白（49 行）と `#actions` の固定（146〜153 行）。変えない
- `manager/media/style/common/welcome.tpl`、`manager/media/style/{AromaHop,RevoStyle,SakuraFlow}/welcome.tpl`: ダッシュボードの中身。外枠のインラインの余白は Surprises を参照。変えるかは M1 の確認で決める
- `manager/media/style/common/block_section.tpl`・`block_tabpane.tpl`・`block_tabpage.tpl`: セクションとタブの組の HTML の雛形（構造を知るため。変えない）
- `manager/actions/document/resources_list.static.php`（160〜222 行）: サブリソース一覧（`a=120`）。`h1`、`#actions`、`.section > .sectionBody > ul.actionButtons`、パンくず、`form#mutate` の表の順に並ぶ
- `assets/docs/theme-variables.md`: #877 で作った色の決まり。文書の書き方の前例
- `assets/docs/site-url.md`: 80 番以外のポートで検証環境を立てるときの、サイトのURL（`fixed_site_url`）の扱い
- `.claude/skills/adaptive-validation-runner/`: Docker の検証環境と Playwright の共通ヘルパー（`scripts/lib.js` の `login()`・`sql()`・`appExec()`）

前提:

- #877（色の変数）と #912（SakuraFlow の未定義の変数と、タブとパネルの間を `var(--space-2)` に）は main に入っている。#912 では、タブを使う 27 画面で、SakuraFlow のタブの下端からパネルの上端までが 12px → 8px になったことを測っている。そのとき `a=40`・`a=107`・`a=108` は、タブの上の余白が 6px だった（変更前からの差。原因は M1 で調べる）
- DocumentParser（`manager/includes/document.parser.class.inc.php`）の `executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()` には影響しない。変えるのは管理画面の CSS（と、決まれば `welcome.tpl` の外枠の属性）と文書だけ
- キャッシュ: サーバー側のキャッシュには影響しない（`welcome.tpl` を変えた場合も、管理画面のテンプレートはサイトのキャッシュに入らない）。ブラウザは `style.css?<modx_version>` を版の文字列で覚えるので、版を上げるまで古い CSS が残ることがある（リリースで版が上がれば解消。この作業では扱わない）

## Plan of Work

M1 では、手を入れる前の 7 テーマを同じ条件で計測し、現状の縦の間を「テーマ × 画面 × ブロックの組」の表にする。表から、テーマをまたいで多い値・意図した組（タブとパネル、見出しの帯と本体）・場当たりの値を仕分け、決まりの案を作る。案の出発点は次のとおりで、数値は計測の結果で確定させる。

- ページの端と最初のブロック: shell.css の `--space-2`（8px）。テーマは足さない（最初のブロックの上の margin は 0）
- ブロックとブロックの間（見出しと次のブロック、セクションどうし、セクションとタブの組、操作ボタン列と表など）: 1 つの段（候補は `--space-2` か `--space-3`）
- 丸いタブのタブとパネルの間: ページの端と同じ `--space-2`（#912 の値）。フォルダ型は負の `top` で重ねる（例外）
- 入れ物（`.sectionBody`・`.tab-page`）の中: 内側の余白（テーマのデザイン）だけで間を作り、最初の子の上・最後の子の下に外側の margin を足さない
- 間は下側の margin（`margin-bottom`）だけで作り、上側の margin と重ねない（margin の相殺で値が読めなくなるのを避ける）
- 例外: フォルダ型のタブとパネルの負の `top`、枠でつながる組の間 0、SoftSlate の狭い画面の `#actions` 用の余白

決まりは `assets/docs/theme-spacing.md` に、段の約束（`--space-1..8` の値と、共通部が読む変数）、決まりの表（どの間をどの段に）、例外の一覧、書き方（`top` でずらさない、px を直書きしない、横の余白や上下の内側の余白を変えるときは `padding-block` などで縦だけを書く）、新しいテーマを作るときの確かめ方、の順に書く。構成は `theme-variables.md` にならう。

M1 の最後に、計測の表・決まりの案・テーマごとの直す箇所の見込み・本人に決めてほしいこと（Decision Log の RevoStyle・RubberWhite の段の扱いと `welcome.tpl`、`a=40`・`a=107`・`a=108` の 6px の扱い）を下書きの PR に載せる。M2 の最初の手順を「本人の確認を得てから行う」関門にし、確認で決まったことを Decision Log と文書に反映してから、テーマの書き換えに入る。関門を M1 の最後ではなく M2 の頭に置くのは、runner が M1 を完了・push して PR に案を載せてから止まれるようにするためと、再開したときに確認の記録の有無だけで通過を判断できるようにするため。

M2 では、決まりを最初に丸いタブの 2 テーマへ当てる。SakuraFlow は #912 で一部を直しており、AromaHop は同じ作りに `top: 12px` が残るため、2 つを並べて直すと差分を見比べやすい。`.tab-page` の `position: relative; top` は消し（`position: relative` と `z-index` は重なりの順のために残す）、タブとパネルの間は `.tab-row` か `.tab-page` の margin で作る。ほかのブロックの間も、決まりの段の `margin-bottom` に書き換える。

M3 では、残りの 5 テーマに同じことをする。決まりで RevoStyle・RubberWhite の段を直すことになった場合は、それを最初のコミットに分ける（見た目を変えない置き換えと、余白を揃える変更を混ぜない）。最後に 7 テーマを通しで計測し、文書の例外と表を実際の値に合わせて仕上げる。

各マイルストーンの検証は、修正前（横取りで返す main の CSS）と修正後の計測とスクリーンショットの比較で行う。縦の間が決まりどおりになったこと、色・枠・角の丸み・影・文字・横の余白など、縦の余白以外の計算済みの値（computed style）が変わらないことを確かめる。

## Concrete Steps

記号: `<wt>` は作業ツリーのパス（runner のワークツリー。このブランチ）、`<work>` はリポジトリの外の作業用ディレクトリ（例: `/tmp/evo-917-spacing`）、`<base>` は `git -C <wt> merge-base origin/main HEAD`（計画作成時は e58e53e6b）。検証環境は名前 `evo-validate-917`、アドレス `127.0.0.17`、ポート 80 とする。docker compose と同梱スクリプトを実行するたびに、先頭に `EVO_VALIDATE_NAME=evo-validate-917 EVO_BIND_IP=127.0.0.17` を付ける（付け忘れると別のセッションの環境を作り直すおそれがある）。

### M1: 計測と決まりの案・文書

1. 検証環境を立てる（adaptive-validation-runner の `references/environment.md` に従う）。
   - 実行: 同じ名前の環境が無いことを `docker ps -a --filter label=com.docker.compose.project=evo-validate-917 --format '{{.Names}}\t{{.Label "com.docker.compose.project.working_dir"}}'` で確かめ、アドレスが空いていることを `ss -ltn | grep -E '(127\.0\.0\.17|0\.0\.0\.0|\*|\[::\]):(80|8025)\b'` で確かめる。`<wt>` で `EVO_VALIDATE_NAME=evo-validate-917 EVO_BIND_IP=127.0.0.17 docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml up -d --build`。Playwright を `<work>/pw` に入れ（`npm install playwright@1.48.2` と `npx playwright install chromium`）、`EVO_VALIDATE_NAME=evo-validate-917 EVO_BIND_IP=127.0.0.17 NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/setup-site.js --sample` の後、同じ前置きで `scripts/smoke.js` を流す。
   - 期待: smoke.js がすべて PASS。80 番が使えず別のポートにした場合は、`fixed_site_url` がポート込みの URL のままであること（空にするとログイン後に 80 番へ飛ぶ。`assets/docs/site-url.md`）。
2. 計測に使う ID を決める。
   - 実行: `docker exec evo-validate-917-db mariadb -uuser -ppassword evodb -N -e "SELECT id FROM modx_site_content WHERE isfolder=1 AND deleted=0 ORDER BY id LIMIT 1; SELECT id FROM modx_site_modules ORDER BY id LIMIT 1; SELECT COUNT(*) FROM modx_user_settings WHERE setting_name='manager_theme'"`
   - 期待: 1 行目に子を持つリソースの ID（`<folder>`）、2 行目にモジュールの ID（`<module>`。無ければ `a=108` の代わりに `a=107` を測る）、3 行目が 0（利用者ごとのテーマの上書きが無い）。
3. 計測スクリプトを `<work>/spacing/measure.js` に書く（リポジトリには置かない。`scripts/lib.js` を `require` し、書き方は `scripts/smoke.js` にならう）。
   - 入力: 環境変数 `THEMES`（既定は 7 テーマすべて）、`STATE`（`base` か `work`）、`OUT`（出力先。`<work>/spacing/<STATE>/`）。
   - テーマごとに: `sql("REPLACE INTO modx_system_settings (setting_name,setting_value) VALUES ('manager_theme','<テーマ>')")` と `appExec(['php','evo','cache:clear'])` でテーマを切り替え、`login()` し、`page.setViewportSize({ width: 1440, height: 900 })` とする。`STATE=base` のときは `page.context().route('**/manager/media/style/*/style.css*', …)` で、`git -C <wt> show <base>:manager/media/style/<テーマ>/style.css` の中身を `text/css` で返す。
   - 画面ごとに `EvoShell.navigate('index.php?<クエリ>')` で開き（smoke.js と同じく、応答と `#mainPane` の更新を待つ）、次を JSON で `OUT/<テーマ>/<画面>.json` に、`#mainPane` のスクリーンショットを `OUT/<テーマ>/<画面>.png` に保存する。画面は `a=2`（ダッシュボード）、`a=120&id=<folder>`（サブリソース一覧）、`a=27&id=1`（リソースの編集）、`a=76`（エレメント管理）、`a=17`（システム設定）、`a=75`（ユーザー管理）、`a=12&id=1`（ユーザーの編集）、`a=99`（ウェブユーザー）、`a=86`（ロール）、`a=106`（モジュール）、`a=108&id=<module>`（モジュールの編集）、`a=40`（アクセス権限）、`a=53`（システム情報）、`a=93`（バックアップ）、`a=114`（システムログ）の 15 画面。タブのある画面は最初のタブのまま測る。
   - 記録する値: `#mainPane` から 5 階層までの、表示されていて高さが 0 でないブロックの要素（`display` が `block`・`flex`・`grid`・`table`・`list-item` のもの。`position` が `fixed`・`absolute` のもの、`#actions`、`script`・`style`、表のセルの中は除く）について、要素の道筋（`#mainPane > div.section:nth-of-type(1) > div.sectionBody` の形）、`getBoundingClientRect()` の `top`・`bottom`、計算済みの `margin-top/bottom`・`padding-top/bottom`・`border-top/bottom-width`・`position`・`top`。そこから、隣り合う兄弟の縦の間（下の `top` − 上の `bottom`）と、入れ物と最初の子・最後の子の間を出す。あわせて、縦の余白以外の値として `color`・`background-color`・`background-image`・`border-*-color`・`border-*-style`・`border-left/right-width`・`border-radius`・`box-shadow`・`font-size`・`font-weight`・`line-height`・`opacity`・`margin-left/right`・`padding-left/right` を、同じ要素の道筋ごとに記録する。
   - 最後に `manager_theme` を `RevoClassic` に戻し、キャッシュを作り直す。
4. 比べるスクリプトを `<work>/spacing/compare.js` に書く。
   - 2 つの出力先（例: `base` と `work`）を読み、(a) テーマ × 画面 × ブロックの組ごとの縦の間の表（Markdown）、(b) 縦の余白以外の値が違う要素と値の一覧、を出す。決まりの表（M1 で作る JSON。`<work>/spacing/rule.json`。ブロックの組の種類 → 期待する px）を渡したときは、(c) 決まりと ±0.5px を超えて違う箇所の一覧も出す。
5. 現状を計測し、決まりの案を作る。
   - 実行: `<wt>` で `EVO_VALIDATE_NAME=evo-validate-917 EVO_BIND_IP=127.0.0.17 STATE=work OUT=<work>/spacing/m1-work NODE_PATH=<work>/pw/node_modules node <work>/spacing/measure.js`（ワークツリーは未変更なので main と同じ）。同じく `STATE=base OUT=<work>/spacing/m1-base` でも流し、`node <work>/spacing/compare.js <work>/spacing/m1-base <work>/spacing/m1-work` で (b) が空であることを確かめる（横取りの仕組みが正しく main を返している確認）。
   - 期待: 7 テーマ × 15 画面の JSON と PNG がそろう。(b) が 0 件。現状の縦の間の表から、テーマをまたいで同じ種類の間（例: `h1` の下、`.section` どうし、操作ボタン列と表、タブとパネル、`.tab-pane` の下）の値のばらつきが読める。SakuraFlow のサブリソース一覧で、見出しの枠・操作ボタン列・表の間が不揃いなことが数値で出る。`a=40`・`a=107`・`a=108` のタブの上が 6px になる原因（どの要素の margin か）を特定する。
   - 決まりの案を `<work>/spacing/rule.json` と表にし、`node <work>/spacing/compare.js <work>/spacing/m1-base <work>/spacing/m1-work <work>/spacing/rule.json` の (c) で、テーマごとに直す箇所の見込み（件数と主なセレクタ）を出す。
6. 文書を書く。
   - 編集: `assets/docs/theme-spacing.md` を新しく作る（題「テーマの縦の余白」。Plan of Work の構成。決まりの表は「間の種類 | 段 | 例（セレクタ）」、例外は「例外 | テーマ | 理由」の表）。`assets/docs/theme-variables.md` の冒頭の説明の後に「余白の変数と決まりは `theme-spacing.md` を参照」の 1 行を足す。`AGENTS.md` のドキュメントマップの `theme-variables.md` の行の下に `| \`theme-spacing.md\` | テーマの縦の余白 |` を足す。
   - 実行: doc-maintainer の `/doc-audit` を変更した 3 ファイルに対して行い、指摘を直す（runner の実行中は確認せずに直してコミットする）。
   - 期待: `/doc-audit` の指摘が 0 件。`grep -n "theme-spacing" <wt>/AGENTS.md <wt>/assets/docs/theme-variables.md` が 1 件ずつ。
   - コミット: `docs(theme): 管理画面テーマの縦の余白の決まりを追加する`
7. 本人に見せる内容を用意する（M1 の push のときに、メインが下書きの PR の本文に書く）。
   - 現状の縦の間の表（テーマ × 主な間の種類。全画面の表は長いので、ばらつきの大きい組を中心に）、決まりの案、テーマごとの直す箇所の見込み、SakuraFlow のサブリソース一覧の修正前のスクリーンショットの所見、決めてほしいこと（RevoStyle・RubberWhite の段を案 A にするか、`welcome.tpl` の外枠の余白を消すか、`a=40`・`a=107`・`a=108` の 6px を決まりに含めるか）。
   - M1 はここで完了にしてよい。確認の関門は M2 の手順 0 に置く。

### M2: SakuraFlow・AromaHop

0. 決まりの案について、本人の確認を得てから行う（M2 以降のすべての手順の前提）。
   - Decision Log に「M1 の確認」の行（本人が決まりの案を了承した日付と、決めてほしいことへの答え）があれば、確認は済んでいるので手順 1 へ進む。
   - 無ければ、runner はここで止まり、M1 の手順 7 の内容（PR の番号と、決めてほしいこと）を「止まった理由: ExecPlan に『本人の確認を得てから行う』と書かれた手順に来た」として返す。
   - 確認を得たら、メインが Decision Log に「M1 の確認」の行を書き、文書を答えに合わせて直す（コミット: `docs(theme): 余白の決まりを確認の結果に合わせて直す`。直すものが無ければ不要）。`<work>/spacing/rule.json` も同じ値に直す。`welcome.tpl` を変えると決まったら、M3 の手順 2 の編集対象に `manager/media/style/common/welcome.tpl` と `manager/media/style/RevoStyle/welcome.tpl` が入る（`common/welcome.tpl` は AuroraFlow・RevoClassic・RubberWhite・SoftSlate が使うので M3 に置く。M2 では、SakuraFlow・AromaHop の自前の `welcome.tpl` が既に外枠の余白を持たないことだけ確かめる）。
1. SakuraFlow を決まりに揃える。
   - 編集: `manager/media/style/SakuraFlow/style.css`。M1 の (c) に挙がった箇所を、決まりの段の `margin-bottom`（入れ物の内側は `padding-block`）に書き換える。主な見込み: `h1` の `margin`、`.tab-page .sectionHeader` の `12px`、`.section .sectionBody` と `.tab-page .sectionBody` の `margin`、`.actionButtons` の `margin-bottom: 15px`、`ul.actionButtons` の `padding-bottom: 10px`、`.dynamic-tab-pane-control.tab-pane` の `padding-bottom: 20px`、`.dynamic-tab-pane-control .tab-page` の `top: var(--space-2)`（消して margin で間を作る。`position: relative` と `z-index` は残す）。横の余白は変えない（`margin: 0 0 X` のような一括の書き方を変えるときは、左右の値を保つ）。
   - コミット: `fix(theme): SakuraFlow の縦の余白を決まりに揃える`
2. AromaHop を決まりに揃える。
   - 編集: `manager/media/style/AromaHop/style.css`。手順 1 と同じ。`.dynamic-tab-pane-control .tab-page` の `top: 12px`（1967 行）を消し、タブとパネルの間を決まりの段（`--space-2` の見込み）にする。
   - コミット: `fix(theme): AromaHop の縦の余白を決まりに揃える`
3. 確かめる。
   - 実行: 環境が無ければ M1 の手順 1〜2 で立てる。`THEMES=SakuraFlow,AromaHop` で `STATE=base OUT=<work>/spacing/m2-base` と `STATE=work OUT=<work>/spacing/m2-work` を流し、`node <work>/spacing/compare.js <work>/spacing/m2-base <work>/spacing/m2-work <work>/spacing/rule.json`。
   - 期待: (c) が 0 件（2 テーマ × 15 画面の縦の間が決まりどおり。文書の例外を除く）。(b) が 0 件（色・枠・角・影・文字・横の余白が前後で同じ）。`grep -n -A14 "^\.dynamic-tab-pane-control \.tab-page {" <wt>/manager/media/style/SakuraFlow/style.css <wt>/manager/media/style/AromaHop/style.css | grep -E "^\S+-\s+top:"` が何も出さない。SakuraFlow のサブリソース一覧で、見出しの枠・操作ボタン列・表の間が同じ値になる（`m2-base` と `m2-work` の PNG を並べて見る）。
   - 必要なら、M2 で分かった例外を文書に足す（コミット: `docs(theme): 余白の決まりに丸いタブの例外を足す` など。足すものが無ければ不要）。

### M3: 残り 5 テーマ（M2 の手順 0 の確認が前提。改めて止まらない）

1. 段の約束を直す（M1 の確認で案 A に決まった場合だけ）。
   - 編集: `manager/media/style/RubberWhite/style.css`（`--space-1..8` を足し、`--space-xs..xl` の参照を同じ値の数字の段へ置き換え、名前の段を消す）。コミット: `refactor(theme): RubberWhite の余白の変数を共通の段の名前にする`。
   - 編集: `manager/media/style/RevoStyle/style.css`（`--space-1..8` を 4px 刻みに定義し直し、`--space-9` 以降を消す。今の参照は、縦のブロックの間を除き、今と同じ px になるように書き換える）。コミット: `fix(theme): RevoStyle の余白の変数を共通の段に揃える`。
   - 期待: RubberWhite は、この時点で `STATE=base` と `STATE=work` の計測が縦の間も含めて同じ（見た目が変わらない置き換え）。RevoStyle は、`#mainPane` の内側の余白が 3px → 8px になる以外、この時点で縦の間と (b) が変わらない。`grep -nE "\-\-space-(xs|sm|md|lg|xl|9|1[0-4])\b" <wt>/manager/media/style/RubberWhite/style.css <wt>/manager/media/style/RevoStyle/style.css` が何も出さない。
2. 5 テーマを決まりに揃える。
   - 編集: `manager/media/style/{AuroraFlow,RevoClassic,RevoStyle,RubberWhite,SoftSlate}/style.css`。M2 の手順 1 と同じ方針。フォルダ型のタブの負の `top`（RevoClassic・RevoStyle・RubberWhite）、RevoClassic の見出しの帯と本体の間 0、SoftSlate の 1100px 以下の `padding-bottom: 72px` は残す。RubberWhite は `.tab-page` の規則が 2 つ（1408 行・2049 行）あるので、両方を見る。
   - コミット: テーマごとに 1 つ。`fix(theme): AuroraFlow の縦の余白を決まりに揃える` のように、テーマ名だけを変える。
   - `welcome.tpl` を変えると決まった場合: `manager/media/style/common/welcome.tpl` と `manager/media/style/RevoStyle/welcome.tpl` の外枠を `<div>` にする。コミット: `fix(theme): ダッシュボードの外枠の余白を決まりに揃える`。
3. 7 テーマを通しで確かめる。
   - 実行: すべてのテーマで `STATE=base OUT=<work>/spacing/m3-base` と `STATE=work OUT=<work>/spacing/m3-work` を流し、`compare.js` に `rule.json` を渡す。`welcome.tpl` を変えた場合、ダッシュボード（`a=2`）の修正前は `<work>/spacing/m1-work` の値と比べる。
   - 期待: (c) が 0 件（7 テーマ × 15 画面）。(b) は、案 A にした場合の RevoStyle の `#mainPane` の `padding-left/right`（3px → 8px）だけ。`grep -n -A14 "^\.dynamic-tab-pane-control \.tab-page {" <wt>/manager/media/style/*/style.css | grep -E "^\S+-\s+top:"` が、RevoClassic・RevoStyle・RubberWhite の負の値だけを出す。
   - 文書の決まりの表と例外を、実際の値に合わせて仕上げる（必要なときだけ。コミット: `docs(theme): 余白の決まりを 7 テーマの結果に合わせて仕上げる`）。
4. 後片付け。`manager_theme` を `RevoClassic` に戻し、本人が目視で確かめたいと言っていなければ、`EVO_VALIDATE_NAME=evo-validate-917 EVO_BIND_IP=127.0.0.17 docker compose -f compose.yml -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml down -v --rmi local` で消す。検証用に作られた `manager/includes/config.inc.php` も消す。`git -C <wt> status --short` が空であることを確かめる。

## Validation and Acceptance

次をすべて満たしたら完了とする。値は M3 の手順 3 の計測で示し、PR の本文に「条件 × main × このブランチ」の表で書く。

1. `assets/docs/theme-spacing.md` があり、段の約束（`--space-1..8`）、どの間をどの段にするかの表、例外の一覧、書き方が書かれている。`AGENTS.md` のドキュメントマップと `theme-variables.md` から辿れる。
2. 7 テーマ × 15 画面（M1 の手順 3 の一覧）で、ブロックの縦の間が決まりの表どおり（±0.5px）。例外は文書に挙げたものだけ。修正前（main）は、SakuraFlow のサブリソース一覧の見出しの枠・操作ボタン列・表の間や、AromaHop のタブとパネルの間（12px）などで決まりと違う値が出る。
3. 7 テーマの `style.css` で、`.dynamic-tab-pane-control .tab-page` の `top` は、RevoClassic・RevoStyle・RubberWhite の負の値（タブとパネルの枠をつなげる重ね）だけ。ブロックの縦の間を作る宣言は `--space-N` で書かれている（例外を除き、px の直書きが無い）。
4. 色・枠・角の丸み・影・文字・横の余白など、縦の余白以外の計算済みの値が、修正前と同じ。違いがあるのは、Decision Log で決めた意図した変更（案 A にした場合の RevoStyle の `#mainPane` の左右の余白など）だけ。
5. 修正前と修正後のスクリーンショットを並べて、縦の余白以外の見た目が変わっていない（目視。本人の確認に使う）。

## Idempotence and Recovery

- 計測のスクリプトは、何度流しても同じ出力先を上書きするだけ。テーマの切り替えは `REPLACE INTO` なので繰り返してよい。途中で止まったら、最後に `manager_theme` を `RevoClassic` に戻し、`php evo cache:clear` を流す（`docker exec -w /var/www/html evo-validate-917-app php evo cache:clear`）。
- 修正前の値は、横取りの仕組み（`STATE=base`）でいつでも作り直せる。`welcome.tpl` を変えた後でダッシュボードの修正前の値（`<work>/spacing/m1-work`）を失ったときは、`<work>` の下に `git -C <wt> worktree add <work>/base <base>` で修正前の作業ツリーを作り、別の名前とアドレス（例: `evo-validate-917base`、`127.0.0.18`）で検証環境を立てて、`STATE=work` で計測し直す。終わったら、その環境を `down -v --rmi local` で消し、`git -C <wt> worktree remove <work>/base` で作業ツリーを外す。
- 検証環境は、マイルストーンをまたいで使い回してよい。無くなっていたら M1 の手順 1〜2 からやり直す（`config.inc.php` だけが残っていたら `setup-site.js --reinstall`）。
- テーマの書き換えはテーマごとのコミットに分けるので、1 つのテーマで問題が出たら、そのコミットだけを `git -C <wt> revert <コミット>` で戻せる。
- M2 の手順 0 で止まった後は、本人の答えを Decision Log の「M1 の確認」の行に書いてから `/run-plan .agent/plans/2026-10-25-theme-vertical-spacing.md` を流し直す。Progress の最初の未完了の M2 から再開し、手順 0 は記録があるので通過する。答えを書かずに流し直すと、手順 0 でまた止まる。

## Artifacts and Notes

- 対象の Issue: #917（関連: #912、#877）
- ブランチ: `fix/917-theme-vertical-spacing`（計画作成時の HEAD は e58e53e6b。PR はまだ無い。runner が M1 で下書きの PR を作り、`Refs #917` を挙げ、最後のマイルストーンで `Closes #917` に改める）
- 前例: `assets/docs/theme-variables.md`（#877 の色の決まり）、PR #918（#912。SakuraFlow のタブとパネルの間を 27 画面で計測した方法）
- 計測のスクリプトと出力（`<work>/spacing/`）はリポジトリの外に置き、PR には入れない。結果の表は PR の本文に書く
- 想定コミット単位（Conventional Commits、日本語）:
  - M1: `docs(theme): 管理画面テーマの縦の余白の決まりを追加する`（`assets/docs/theme-spacing.md`、`assets/docs/theme-variables.md`、`AGENTS.md`）。確認の後に `docs(theme): 余白の決まりを確認の結果に合わせて直す`
  - M2: `fix(theme): SakuraFlow の縦の余白を決まりに揃える`、`fix(theme): AromaHop の縦の余白を決まりに揃える`
  - M3: （案 A のとき）`refactor(theme): RubberWhite の余白の変数を共通の段の名前にする`、`fix(theme): RevoStyle の余白の変数を共通の段に揃える`。テーマごとに `fix(theme): <テーマ> の縦の余白を決まりに揃える`（AuroraFlow・RevoClassic・RevoStyle・RubberWhite・SoftSlate）。（`welcome.tpl` を変えるとき）`fix(theme): ダッシュボードの外枠の余白を決まりに揃える`。必要なら `docs(theme): 余白の決まりを 7 テーマの結果に合わせて仕上げる`
  - ExecPlan の記録（Progress・Surprises・Decision Log）はメインがマイルストーンごとにコミットする

## Interfaces and Dependencies

- 共通部とテーマの約束: shell.css は `--shell-main-padding`（どのテーマも未定義）と `--space-2`・`--space-3`・`--space-4` を既定値付きで読む。この作業で、`--space-1..8` が 4px 刻みであることを文書の約束にする。shell.css 自体は変えない
- タブ: `manager/media/script/tabpane.js`（`WebFXTabPane`）が作る `.dynamic-tab-pane-control`・`.tab-row`・`.tab-page` の構造に依存する。JS は変えない
- 画面の HTML: `manager/actions/` の各画面と `manager/media/style/common/block_*.tpl` が作る `h1`・`.section`・`.sectionHeader`・`.sectionBody`・`ul.actionButtons`・`#actions` の構造に依存する。HTML は変えない（`welcome.tpl` の外枠だけは M1 の確認しだい）
- 外部のテーマ: 新しい文書の約束に従えば、共通部と同じ余白になる。既存の外部のテーマは、今と同じく shell.css の既定値で表示される（互換は崩れない）
- 検証: Docker（`compose.yml` と `.claude/skills/adaptive-validation-runner/scripts/compose.validation.yml`。php:8.4-apache と mariadb:11）、Playwright 1.48.2（ヘッドレスの Chromium）、`scripts/lib.js`。ホストに Chromium の OS のライブラリが無いときは、`references/environment.md` の「既知の落とし穴」に従い、本人に `sudo env "PATH=$PATH" npx playwright install-deps chromium` を頼む
- PHP の版や DB の版に関わる変更は無い（CSS・テンプレートの属性・文書だけ）
