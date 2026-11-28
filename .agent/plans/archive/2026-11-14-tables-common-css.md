# ExecPlan: 表・一覧とページ送り、コード欄の帯を共通の CSS にまとめる（#1448 の 4 本目）

Issue: #1448（Refs）。前提: #1451〜#1453。このブランチは #1453 のブランチの上に積む

## Purpose / Big Picture

一覧の表（`.grid`）、ページ送り（`.pagination`）、コード欄の上の帯（`.code-editor-header`）の規則は、7 テーマが書き写している。7 テーマすべてが書いている「セレクタと性質」の組を `manager/media/style/common/tables.css`（`@layer evo-components`）に移し、テーマで値が違うものは `:root` の変数で決める。見た目は変えない（RevoStyle は、見れば意図が分かる程度の違いを許す）。

## Progress

- [x] (2026-11-14) 7 テーマの規則を一覧にした（component.py）
- [x] (2026-11-14) tables.css を書き、テーマから移した宣言を消し、値の違うテーマに変数を足した（grid_move.py・grid_move2.py）
- [x] (2026-11-14) #1453 のブランチと比べて、7 テーマ × 34 画面の計算後のスタイルを突き合わせた
- [x] (2026-11-14) 下書きの PR を作る

## Surprises & Discoveries

- `.gridItem`・`.gridAltItem` は表の行（`tr`）に付く（`data_table.class.php` の `rowClasses`）。tr の `padding` は表の配置で使われないので、その宣言は消した。計算後のスタイルでは tr の padding と内側の幅・高さが変わって見えるが、箱の大きさは同じ。
- ページ送りの普段の地（AromaHop・AuroraFlow・SakuraFlow の `background`）をテーマに残すと、層の外の規則が勝って、層に入れた「今のページ」の地が出なくなる。普段の地も `--pagination-bg` にして、いっしょに移した。
- RevoStyle は `--space-*` の値が違う（`--space-2` が 3px）。tables.css の余白は値で書いた。
- RevoStyle には `a, a:link …` に下線を付ける規則があり、層に入れた `.grid a { text-decoration: none }` が負ける。すべての一覧に下線が付くので、RevoStyle には `.grid a` の規則を残した。
- 測定の中で、ファイルブラウザ（a=31）に 1 件だけ違いが出たが、前の画面の順をそろえて測り直すと消えた（マウスが乗った状態の揺れ）。

## Decision Log

- 2026-11-14: 7 テーマすべてが書いている組だけを移す。一部のテーマにしかない性質（表の角・影・`border-collapse`、見出しの行の下の枠など）はテーマに残す。
- 2026-11-14: 並べ替えの矢印（`.grid th[aria-sort] a::after`）は RevoStyle にだけ無かったが、表の部品なので tables.css に移した。RevoStyle にも矢印が付く（利用者の判断: RevoStyle は見れば意図が分かる程度の違いなら変わってよい）。
- 2026-11-14: RevoStyle に残していたページ送りの余白とコード欄の帯の上書きは消した。違いは余白の数 px と帯の色・枠の色だけ。
- 2026-11-14: `:has()` で前の要素の余白を作る規則（`:has(+ .pagination)`・`:has(+ .code-editor)`）は、縦の余白の決まり（theme-spacing.md）の仲間なので、ここでは移さない。
- 2026-11-14: `.gridItem`・`.gridAltItem` のクラスで交互の色にするのをやめ、`:nth-child` にするのは別の PR にする（利用者の提案。クラスの無い `.grid` の表が縞になるか、絞り込みで隠す行が数えられるかを確かめる必要がある）。

## Outcomes & Retrospective

テーマの CSS で 570 行ほど減り、tables.css が 120 行ほど増えた。6 テーマは計算後のスタイルが変わらず、RevoStyle だけページ送りの余白・コード欄の帯・並べ替えの矢印が少し変わった。

## Context and Orientation

- 新しいファイル: `manager/media/style/common/tables.css`
- 読み込み: `manager_theme_stylesheets()`（forms.css → theme-base.css → sections.css → tabs.css → tables.css → テーマ）
- 文書: `assets/docs/theme-variables.md` の「表・一覧」
- DocumentParser・キャッシュへの影響はない

## Plan of Work

grid_move.py で、移す組の宣言をテーマの規則から消し（空になった規則は消す）、値の違うテーマの `:root` に変数を足す。grid_move2.py で、6 テーマの並べ替えの矢印の規則と、RevoStyle の上書きを消す。tables.css は手で書く。

## Concrete Steps

1. `component.py manager/media/style '<部品のセレクタ>'` で、7 テーマの値を並べる
2. grid_move.py・grid_move2.py を流す
3. compstyle.js を、このブランチと #1453 のブランチで流し、compdiff.py で比べる

## Validation and Acceptance

- 7 テーマ × 34 画面で、計算後のスタイルの差が、tr の padding（当たっていない）と RevoStyle の小さな違いだけ
- RevoStyle の一覧とコード欄を撮って、前と並べて見比べる

## Idempotence and Recovery

テーマの CSS を #1453 のブランチの版に戻し、grid_move.py・grid_move2.py を流し直せば同じ結果になる。

## Artifacts and Notes

- 道具: component.py、grid_move.py、grid_move2.py、compstyle.js、compdiff.py（検証用の一時領域）

## Interfaces and Dependencies

- テーマの新しい変数: `--grid-bg`・`--grid-border`・`--grid-cell-padding`・`--color-grid-header-text`・`--grid-header-weight`・`--grid-header-bg`・`--grid-alt-bg`・`--grid-hover-bg`・`--pagination-bg`・`--color-pagination-text`・`--color-pagination-border`・`--color-pagination-disabled`・`--color-pagination-active-text`・`--pagination-active-bg`・`--color-pagination-active-border`
