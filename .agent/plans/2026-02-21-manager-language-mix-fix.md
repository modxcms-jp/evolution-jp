# ExecPlan: 管理画面設定で言語が混在表示される問題の修正

## Purpose / Big Picture

`manager/includes/lang/` に複数言語ファイルが存在すると、管理画面「グローバル設定」の文言が部分的に別言語へ混在する問題を解消する。設定画面の信頼性を回復し、言語ファイル追加時の予期しないUI崩れを防ぐ。

## Progress

- [x] (2026-02-21) 影響範囲と再現条件を固定し、回帰確認観点を確定する
- [x] (2026-02-21) `get_lang_keys()` の副作用（`$_lang` 汚染）を除去する
- [x] (2026-02-21) 言語一覧取得処理の不要な全件読み込みを最小化する
- [ ] (2026-02-21) 管理画面で混在表示が再発しないことを確認する

## Surprises & Discoveries

- 指定URL（`https://forum.modx.jp/viewtopic.php?f=23&t=2013`）の投稿は、`manager/includes/lang/` に海外言語ファイルを置いたときの混在表示を報告している。
- `manager/actions/tool/mutate_settings.dynamic.php` は設定画面描画時に `lang/*.inc.php` を全件走査している。
- `manager/actions/tool/mutate_settings/functions.inc.php` の `get_lang_keys()` は `global $_lang` を使って `include` するため、収集処理の副作用が表示言語へ漏れる。
- `get_lang_keys('english.inc.php')` を呼び出しても、既存の `$_lang['sentinel']` が保持されることをCLIで確認した。
- 言語キー判定を `include` からファイル内容のパターン検出へ変更し、設定画面ロード時の全言語 `include` を撤廃した。

## Decision Log

- 2026-02-21 / AI / まずは `$_lang` 汚染を止める修正を最優先に実施する。根拠: 表示混在の直接原因であり、差分最小で効果が大きい。
- 2026-02-21 / AI / 言語キー収集は「キー一覧の取得」と「表示言語ロード」を分離する。根拠: SSOT と責務分離を保ち、再発防止に有効。
- 2026-02-21 / AI / 追加最適化（キャッシュやメタデータ化）は副作用修正後に段階導入する。代替案: 一度に全面改修。見送り理由: 影響範囲が広く検証コストが高い。

## Outcomes & Retrospective

- `manager/actions/tool/mutate_settings/functions.inc.php` の `get_lang_keys()` をローカルスコープ化し、言語キー収集時に `$_lang` を汚染しないようにした。
- `manager/actions/tool/mutate_settings.dynamic.php` は言語ファイル名の列挙のみ行い、キー配列の全件構築をしない構成へ変更した。
- `manager/actions/tool/mutate_settings/functions.inc.php` は必要な言語だけ遅延評価し、`$lang_keys` にキャッシュする構成へ変更した。
- 構文検証: `mutate_settings.dynamic.php` / `functions.inc.php` / `tab3_user_settings.inc.php` / `tab4_manager_settings.inc.php` の `php -l` が成功した。
- 未完了: 実ブラウザでの混在再発確認。

## Context and Orientation

用語:

- 言語混在: 画面上のラベル群で、一部キーだけ別言語に置き換わる状態。
- `$_lang` 汚染: 本来の表示言語配列が、別用途の `include` 副作用で上書きされること。

対象ファイル:

- `manager/actions/tool/mutate_settings.dynamic.php`
- `manager/actions/tool/mutate_settings/functions.inc.php`
- （必要時）`manager/actions/tool/mutate_settings/tab4_manager_settings.inc.php`

関連ドキュメント:

- `assets/docs/architecture.md`
- `assets/docs/core-issues.md`
- `.agent/PLANS.md`

## Plan of Work

実装は二段階で進める。第一段階で `get_lang_keys()` の副作用を止める。具体的には、言語キー収集時に `$_lang` をローカルに隔離し、収集後に必ず元の `$_lang` を復元する。第二段階で、設定画面表示に不要な全件 `include` を減らす。最小案としては、言語一覧はファイル名から組み立て、キー一覧が必要な場面のみ限定的に読み込む。これにより、機能互換を保ちながら混在表示と無駄なI/Oを同時に抑える。

## Concrete Steps

1. 現状挙動を固定する。  
   編集対象ファイル: なし（調査のみ）  
   実行コマンド: `nl -ba manager/actions/tool/mutate_settings.dynamic.php | sed -n '33,45p'`  
   期待される観測結果: 言語ファイル全件走査と `get_lang_keys()` 呼び出し位置が確認できる。

2. `get_lang_keys()` で `$_lang` 汚染を防ぐ。  
   編集対象ファイル: `manager/actions/tool/mutate_settings/functions.inc.php`  
   実行コマンド: `php -l manager/actions/tool/mutate_settings/functions.inc.php`  
   期待される観測結果: 構文エラーなし、かつ `$_lang` を退避・復元する処理が存在する。

3. 言語一覧生成の責務を分離する。  
   編集対象ファイル: `manager/actions/tool/mutate_settings.dynamic.php`, `manager/actions/tool/mutate_settings/functions.inc.php`  
   実行コマンド: `rg -n "scandir\\(|get_lang_keys\\(|get_lang_options\\(" manager/actions/tool/mutate_settings* -S`  
   期待される観測結果: 言語一覧取得とキー収集の用途が分離され、不要な全件 `include` が減っている。

4. 設定画面表示の回帰確認を行う。  
   編集対象ファイル: なし（確認のみ）  
   実行コマンド: `php -l manager/actions/tool/mutate_settings.dynamic.php manager/actions/tool/mutate_settings/functions.inc.php manager/actions/tool/mutate_settings/tab4_manager_settings.inc.php`  
   期待される観測結果: 構文チェックが成功し、管理画面のラベルが単一言語で表示される。

5. 再発防止の記録を残す。  
   編集対象ファイル: `assets/docs/troubleshooting/solved-issues.md`（必要時）  
   実行コマンド: `rg -n "言語|global設定|Svenska|混在" assets/docs/troubleshooting/solved-issues.md`  
   期待される観測結果: 再現条件・原因・修正方針が追記され、将来の切り分けに使える。

## Validation and Acceptance

1. 管理画面「グローバル設定」を開いても、見出し・ラベル・説明文が設定言語以外に部分混在しないこと。
2. `manager/includes/lang/` に追加言語ファイルが存在しても、表示言語が安定して再現すること。
3. `php -l` による対象ファイルの構文チェックがすべて成功すること。
4. 設定画面の言語選択ドロップダウンが従来どおり表示されること。

## Idempotence and Recovery

修正は `mutate_settings` 周辺に限定し、他画面の表示ロジックへ影響を広げない。途中中断時は `functions.inc.php` の `get_lang_keys()` を優先的に元に戻せば影響を最小化できる。段階的コミットにより、副作用修正と最適化を分離してロールバック可能にする。

## Artifacts and Notes

- 事象報告: `https://forum.modx.jp/viewtopic.php?f=23&t=2013`
- 原因箇所:
  - `manager/actions/tool/mutate_settings.dynamic.php:35`
  - `manager/actions/tool/mutate_settings.dynamic.php:41`
  - `manager/actions/tool/mutate_settings/functions.inc.php:9`
  - `manager/actions/tool/mutate_settings/functions.inc.php:12`

## Interfaces and Dependencies

- UI依存: 管理画面「グローバル設定」（Action 17）
- 設定依存: `manager_language`
- 共有状態: グローバル配列 `$_lang`
- 外部依存はなし。PHP標準関数（`scandir`, `include`, `array_keys`）のみを利用する。
