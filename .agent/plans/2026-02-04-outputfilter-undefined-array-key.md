# ExecPlan: outputfilter の未定義配列キー警告修正（発生源対処版）

## Purpose / Big Picture
PHP 8.0+ で TV の outputfilter 実行時に発生する `Undefined array key` 警告を解消する。警告を局所的に握り潰すのではなく、`$params` の生成元を正規化して全 outputfilter の入力契約を安定化させる。

## Progress
- [x] (2026-02-04) 初版計画を作成し、影響ファイルを棚卸し
- [x] (2026-02-04) 対症療法（各 filter 内の `?? ''`）で一時的に警告を回避
- [x] (2026-02-14) ExecPlan をテンプレート準拠に再構成
- [x] (2026-02-14) 「発生源修正」方針に基づく改修案へ更新
- [ ] (2026-02-14) 実装・検証結果を本 Plan に追記

## Surprises & Discoveries
`manager/includes/document.parser.class.inc.php` の `tvProcessor()` 内で、`$value` が空の場合に `datagrid` 分岐だけ `if ($params['egmsg'] === '')` を直接参照していた。ここが warning の発火点になり得る。また outputfilter 側が未定義キーを前提にしており、呼び出し契約が曖昧だった。

## Decision Log
2026-02-14 / AI / 対症療法（各 filter で未定義キーを空文字へ変換）を最終解としない。理由: エラー隠蔽になり、入力契約の不整合が残るため。代替案は「`tvProcessor()` で format ごとの既定値を明示し、`$params` を正規化してから filter を呼び出す」。
2026-02-14 / AI / 既存の outputfilter インターフェース（`$value`, `$params`）は維持し、互換性を優先する。理由: 呼び出し側の一元修正で影響範囲を閉じられるため。

## Outcomes & Retrospective
実装完了後に記載する。

## Context and Orientation
対象は TV 表示処理の中心である `manager/includes/document.parser.class.inc.php` の `tvProcessor()`。ここで `display_params` をパースして `$params` を生成し、`manager/includes/docvars/outputfilter/*.inc.php` に引き渡している。  
warning は「filter 側でキー未定義」だけでなく「生成元が filter ごとの必須キーを保証していない」ことが本質的な原因。

用語:
outputfilter は TV 値を表示向けに整形する小さな変換モジュール。  
入力契約は「どのキーが常に存在し、どの型で渡るか」の取り決め。  
発生源修正は warning 発生箇所ではなく、異常データを生む上流を直すこと。

## Plan of Work
`tvProcessor()` に format ごとのパラメータスキーマ（既定値マップ）を追加し、`display_params` のパース結果をそのスキーマで正規化してから outputfilter を呼ぶ。これにより filter 側は「定義済みキーが渡る」契約に依存できる。  
同時に、`$value` 空判定の `datagrid` 早期 return 条件を未定義キー参照しない実装に変更し、warning 発火点を除去する。

## Concrete Steps
1. format ごとの既定値マップを `tvProcessor()` に追加する。  
   編集対象ファイル: `manager/includes/document.parser.class.inc.php`  
   実行コマンド: `rg -n "function tvProcessor|datagrid|display_params" manager/includes/document.parser.class.inc.php`  
   期待される観測結果: 既定値マップの配置場所と `tvProcessor()` の入力処理位置が特定できる。
2. `$params` 生成直後に正規化処理（`array_replace($defaults, $params)` 相当）を挿入する。  
   編集対象ファイル: `manager/includes/document.parser.class.inc.php`  
   実行コマンド: `php -l manager/includes/document.parser.class.inc.php`  
   期待される観測結果: 構文エラーがなく、`$params` 参照前に必ず既定キーが存在する状態になる。
3. `datagrid` の早期 return 判定で未定義キー参照を発生させない。  
   編集対象ファイル: `manager/includes/document.parser.class.inc.php`  
   実行コマンド: `rg -n "egmsg|datagrid" manager/includes/document.parser.class.inc.php`  
   期待される観測結果: `egmsg` 参照が正規化後の `$params` に対してのみ行われ、未定義キー warning の経路が消える。
4. 主要 outputfilter の呼び出し契約が維持されることを点検する。  
   編集対象ファイル: `manager/includes/docvars/outputfilter/image.inc.php`, `manager/includes/docvars/outputfilter/hyperlink.inc.php`, `manager/includes/docvars/outputfilter/htmltag.inc.php`, `manager/includes/docvars/outputfilter/datagrid.inc.php`, `manager/includes/docvars/outputfilter/date.inc.php`, `manager/includes/docvars/outputfilter/delim.inc.php`, `manager/includes/docvars/outputfilter/string.inc.php`, `manager/includes/docvars/outputfilter/richtext.inc.php`  
   実行コマンド: `php -l manager/includes/docvars/outputfilter/{image,hyperlink,htmltag,datagrid,date,delim,string,richtext}.inc.php`  
   期待される観測結果: 対象 filter で構文エラーがなく、互換インターフェース（`$value`, `$params`）を維持している。
5. 実測結果を Plan に反映する。  
   編集対象ファイル: `.agent/plans/2026-02-04-outputfilter-undefined-array-key.md`  
   実行コマンド: `git diff -- .agent/plans/2026-02-04-outputfilter-undefined-array-key.md`  
   期待される観測結果: Progress / Surprises / Decision Log / Outcomes が実装結果に追従して更新される。

## Validation and Acceptance
1. `php -l manager/includes/document.parser.class.inc.php` を実行し、`No syntax errors detected` が出ること。
2. 主要 outputfilter 8ファイルに対する `php -l` で `No syntax errors detected` が出ること。
3. 管理画面で `display_params` を省略した TV を各 outputfilter 形式で表示し、PHP warning（`Undefined array key`）が出ないこと。
4. `datagrid` で `egmsg` 未指定の TV を表示し、warning なしで従来どおりレンダリングされること。
5. `display_params` 指定あり TV でも表示 HTML が改修前と同等であること（回帰なし）。
6. 上記検証結果（実行コマンドと観測結果）を本 Plan に追記し、過去チャットを見ずに再現できる状態にすること。

## Idempotence and Recovery
変更は `document.parser.class.inc.php` と必要最小限の outputfilter のみ。差分は `git diff` で確認し、想定外があれば対象コミットを revert して復旧できる。  
中断時は `Progress` の未完了項目を次回の再開点として扱う。

## Artifacts and Notes
関連ファイル:
`manager/includes/document.parser.class.inc.php`  
`manager/includes/docvars/outputfilter/image.inc.php`  
`manager/includes/docvars/outputfilter/hyperlink.inc.php`  
`manager/includes/docvars/outputfilter/htmltag.inc.php`  
`manager/includes/docvars/outputfilter/datagrid.inc.php`  
`manager/includes/docvars/outputfilter/date.inc.php`  
`manager/includes/docvars/outputfilter/delim.inc.php`  
`manager/includes/docvars/outputfilter/string.inc.php`  
`manager/includes/docvars/outputfilter/richtext.inc.php`

## Interfaces and Dependencies
外部依存は追加しない。`tvProcessor()` と outputfilter の既存インターフェースを維持するため、管理画面・フロント双方への影響を最小化できる。  
関連ドキュメントは `AGENTS.md`, `.agent/PLANS.md`, `assets/docs/architecture.md`, `assets/docs/template-system.md`。
