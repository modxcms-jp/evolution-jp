# ExecPlan: 表の行の交互の色を CSS の :nth-child にし、行に交互のクラスを付ける仕組みをやめる

Issue: #1448（Refs）。前提: #1460（表・一覧の共通の CSS）。このブランチは #1460 のブランチの上に積む

## Purpose / Big Picture

表の行を 1 行おきに色分けするのに、PHP が行ごとに `.gridItem`・`.gridAltItem` のクラスや `bgcolor` の属性を付けていた。CSS の `:nth-child` でできるので、`.grid` の表は CSS だけで縞にし、行に交互のクラスを付ける仕組み（`DataTable` の `rowClasses`、`getHTMLGrid()` の `itemClass`・`altItemClass`、datagrid の `&itmc`・`&aitmc`）をやめる（利用者の提案と判断）。

## Progress

- [x] (2026-11-14) 行に交互のクラスを付ける箇所と、縞の無い `.grid` の表を洗い出した
- [x] (2026-11-14) tables.css を `:nth-child` にし、PHP の指定と仕組みを消した
- [x] (2026-11-14) バックアップと sysinfo の表を `.grid` にそろえ、合計の行を `tfoot` に移した
- [x] (2026-11-14) #1460 のブランチと比べて、計算後のスタイルを突き合わせ、画面を撮って見比べた
- [x] (2026-11-14) PR を作る

## Surprises & Discoveries

- 縞の無かった `.grid` の表（検索の書き方の表、オンラインのユーザー、公開の予定、ManagerManager の補助の表）は、どれもデータの一覧だった。すべて縞になる。
- 合計の行は `tbody` の中にあり、`bgcolor` で色を付けていた。層に入れた CSS の縞の色が `bgcolor` に勝つので、`tfoot` に移して `.grid > tfoot > tr` で色を付けた。
- sysinfo の合計は `$total = …` と代入していて、最後のテーブルの値しか出ていなかった（バックアップの画面は `+=`）。足し上げるように直した。
- 見出しの `th` は共通の CSS で左寄せなので、`align="right"` の属性では右に寄らない。右寄せの列のクラス `.is-end` を、`.is-center` の並びに足した。

## Decision Log

- 2026-11-14: 公開サイトの表（datagrid・`getHTMLGrid()`）の `itmc`・`aitmc`（`itemClass`・`altItemClass`）もやめる（利用者の判断）。既存のサイトでは行のクラスが付かなくなる。縞はサイトの CSS で `tbody > tr:nth-child(even)` と書く（data-table.md の「サイトの CSS に影響する変化」）。保存済みの TV の設定に `&itmc=` が残っていても、無視されるだけ。
- 2026-11-14: `rowAttributes` の `class` は残す（行ごとに意味のあるクラスを付ける用途）。

## Outcomes & Retrospective

59 行足して 100 行ほど消し、差し引きで減った。6 テーマの一覧の見た目は同じ。縞の色がテーマの値に変わったのは、sysinfo の更新履歴の表（これまで全テーマで #eee と #fff に固定）と、新しく `.grid` にしたバックアップの表。

## Context and Orientation

- `manager/includes/controls/data_table.class.php`（`DataTable`・`DataTableSettings`）、`manager/includes/extenders/dbapi/mysqli.inc.php`（`getHTMLGrid()`）、`manager/includes/docvars/outputfilter/datagrid.inc.php`
- `manager/media/style/common/tables.css`、`common/theme-base.css`（`.is-end`）
- 文書: `assets/docs/data-table.md`、`assets/docs/theme-variables.md`
- 公開サイトの出力（datagrid の TV）が変わる。キャッシュの決まりへの影響はない（行のクラスが付かなくなるだけ）

## Plan of Work

drop_row_classes.py で、仕組みと呼び出しの指定を消す。tables.css の `.gridItem`・`.gridAltItem` を `.grid > tbody > tr` と `:nth-child(even)` にし、AromaHop・RubberWhite・SakuraFlow の行の規則も書き換える。バックアップと sysinfo の表は手で直す。

## Concrete Steps

1. `grep -rn "rowClasses\|itemClass\|altItemClass\|itmc\|gridItem\|gridAltItem"` で、残りが無いこと
2. compstyle.js をこのブランチと #1460 のブランチで流して比べる
3. バックアップ（a=93）と sysinfo（a=53）の表を撮り、合計の行（tfoot）の地と中身を確かめる

## Validation and Acceptance

- 7 テーマ × 34 画面で、計算後のスタイルの差が、上の「Outcomes」に書いた表だけ
- 合計の行が見出しと同じ地の色で、sysinfo とバックアップの合計が同じ値

## Idempotence and Recovery

#1460 のブランチの版に戻し、drop_row_classes.py を流し直して、手で直した箇所を当て直せば同じ結果になる。

## Artifacts and Notes

- 道具: drop_row_classes.py、compstyle.js、compdiff.py、stripe_shots.js、tfoot_check.js（検証用の一時領域）

## Interfaces and Dependencies

- 消した選択肢: `DataTable` の `rowClasses`、`DataTableSettings` の `itemClass`・`altItemClass`、`getHTMLGrid()` の `itemClass`・`altItemClass`、datagrid の `&itmc`・`&aitmc`
- 足したクラス: `.grid .is-end`（右寄せの列）
