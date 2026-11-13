# ExecPlan: タブの重なりと並びを共通の CSS にまとめ、タブの中の余白を 1 つの変数にする（#1448 の 3 本目）

Issue: #1448（Refs）。前提: #1451（同じ規則の移動）、#1452（セクション）。このブランチは #1452 のブランチの上に積む

## Purpose / Big Picture

タブ（tabpane.js の部品）の規則は、7 テーマが書き写している。そのうち、7 テーマで値が同じ「重なりと並び」の性質を `manager/media/style/common/tabs.css` に移す。タブの中（`.tab-page`）の余白は、テーマの中の 2 つの規則が `!important` で張り合っていたので、1 つの変数・1 つの規則にして `!important` をやめる。タブの形（錠剤とフォルダの見出しの 2 種類）は、テーマのデザインなのでテーマに残す。

## Progress

- [x] (2026-11-14) 7 テーマのタブの規則を一覧にした（並走セッションの tab-values.md と、component.py）
- [x] (2026-11-14) 値が同じ性質を tabs.css に移し、`.tab-page` の余白を `--tab-page-padding` にした
- [x] (2026-11-14) 層に入れて勝ち負けが変わる組み合わせを洗い出した（テーマと層の外の共通の CSS が相手。0 件）
- [x] (2026-11-14) #1452 のブランチと比べて、計算後のスタイルを突き合わせた。差はダッシュボード（a=2）のタブの中の余白と、それに伴う中の要素の幅・高さだけ
- [ ] 下書きの PR を作る

## Surprises & Discoveries

- `.tab-page` の余白の `!important` は、外からの上書き（共通の CSS・tabpane.js・画面の `<style>`・インライン）と張り合うためではなく、テーマの中の `.dynamic-tab-pane-control .tab-page` と `.sectionBody .tab-page`（どちらも詳細度 (0,2,0)）が張り合っていただけだった（並走セッションの調べ）。後ろの `.sectionBody .tab-page` が勝つので、`.sectionBody` で包まれていないタブ（ダッシュボード）だけ余白が違っていた。
- ダッシュボードの「確認」のタブ（`#tabcheck`）には、テンプレート（welcome.tpl）にインラインの `padding-left:0; padding-right:0` がある。これまでは RevoClassic などの `!important` に打ち消されていた（AromaHop は `!important` が無く、もともと 0）。`!important` をやめたので、テンプレートの意図どおり左右 0 になり、7 テーマでそろった。インラインの指定そのものを見直すかは、別に判断する。
- タブの見出し（`.tab`）は `h2` に付く。層の洗い出しで、クラスだけの右端を `h2` とみなしても 0 件だった。

## Decision Log

- 2026-11-14: 変数にするのは `--tab-page-padding` だけ。形の性質（角・枠・地・余白・文字色）は、テーマごとに組み合わせがばらばらで、変数にすると数が増えて読みにくくなるので、テーマに残す（並走セッションと合意）。
- 2026-11-14: `--tab-page-padding` の値は、ほとんどの画面で効いていた `.sectionBody .tab-page` の値にする。ダッシュボードのタブの中の余白が、ほかの画面とそろう（見た目が変わるのはこの画面だけ）。
- 2026-11-14: `.tab-row` の `display: flex` は RevoStyle に無いので対象外（RevoStyle に上書きを足す形は避ける）。
- 2026-11-14: tabs.css は `@layer evo-components`。RevoStyle（凍結）は規則を残し、変数だけを足す。

## Outcomes & Retrospective

## Context and Orientation

- 新しいファイル: `manager/media/style/common/tabs.css`
- 読み込み: `manager_theme_stylesheets()`（forms.css → theme-base.css → sections.css → tabs.css → テーマ）
- 文書: `assets/docs/theme-variables.md` の「タブ」
- DocumentParser・キャッシュへの影響はない

## Plan of Work

varize.py（指定は spec-tabs.json。変数の無い固定値の性質だけ）で値が同じ性質をテーマから消し、tab_page_padding.py で余白の 2 つの規則から `padding` を消して `:root` に `--tab-page-padding` を足す。tabs.css は、線引きのコメント付きで手で書く。

## Concrete Steps

1. 層の洗い出し: `flip2.py spec-tabs.json`、`flip_common.py spec-tabs.json <共通の CSS>`。`.tab-page` に `padding` を書く層の外の規則が無いこと（`!important` をやめると詳細度に関係なく勝つため、全部を探す）
2. 計算後のスタイル: compstyle.js を、このブランチと #1452 のブランチで流して比べる。差はダッシュボード（a=2）のタブの中の余白だけであること

## Validation and Acceptance

- 7 テーマ × 34 画面で、計算後のスタイルの差が、ダッシュボードのタブの中の余白（と、その中の要素の位置・大きさ）だけ

## Idempotence and Recovery

テーマの CSS を #1452 のブランチの版に戻し、varize.py と tab_page_padding.py を流し直せば同じ結果になる。

## Artifacts and Notes

- 並走セッションの調べ: tab-values.md（タブの 7 テーマの値）、important.py（`!important` の集計）

## Interfaces and Dependencies

- テーマの新しい変数: `--tab-page-padding`
