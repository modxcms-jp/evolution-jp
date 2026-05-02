# ExecPlan: ロガー内部構造の整理

## Purpose / Big Picture

システムログ機構の継続改善に向けて、`Logger` と周辺コードの責務を整理する。warning での文脈欠落は解消済みだが、共通コンテキスト収集、詳細トレース収集、フロント側補助コンテキスト、ファイル出力が密結合しており、次の拡張で再び判断コストと影響範囲が増える状態にある。

## Progress

- [x] (2026-05-02) 現状のログコンテキスト収集経路と責務分担を棚卸しする
- [x] (2026-05-02) `Logger` の共通文脈収集と debug 文脈収集を分離する
- [x] (2026-05-02) `frontend_system_log_context()` と `Logger` の責務境界を整理する
- [x] (2026-05-02) `timestamp` と request 開始時刻の扱いを見直す
- [x] (2026-05-02) 構文確認とログ観測の回帰確認を行う

## Surprises & Discoveries

- 2026-05-01 時点では、warning で `document_identifier` が欠落した原因は `Logger::collectContext()` の early return にあったことが分かっている。
- フロントエンド入口の `index.php` には `frontend_system_log_context()` があり、`Logger` 側の共通文脈と一部重複している。
- `timestamp` は `request_time()` ベースのため、同一リクエストで複数件のログを書いても同じ時刻になる。
- 2026-05-02 に `timestamp` を実記録時刻へ変更し、旧 `request_time()` ベースの意味は `context.request_started_at` へ分離した。
- 2026-05-02 の回帰確認では `php -l` は通過したが、`php evo log:tail --lines=3` は `mysqli` 拡張未導入により起動前で停止した。
- 2026-05-02 に `docker compose up -d app db` 後、`docker compose exec -T app php evo log:tail --lines=3` で CLI 読取が通ることを確認した。
- 2026-05-02 に生ログ末尾も確認したが、観測できたのは既存エントリで、新規 schema の `request_started_at` を含む行はこの確認では採取できなかった。
- 2026-05-02 に `docker compose exec -T app` で `Logger` を直接呼び出して warning を1件生成し、raw log line に `request_started_at` が含まれることを確認した。

## Decision Log

### 2026-05-01: 本体改修と構造整理を分離する

- **決定**: システムログ機能追加本体とは別の ExecPlan に切り出して進める
- **理由**:
  - 既存 ExecPlan は機能追加、UI、CLI、fatal 捕捉、installer ログ対応まで含み、すでにスコープが広い
  - 今回の論点は新機能ではなく、責務分離と設計整理が中心
  - 本体タスクの完了判定と構造改善の継続検討を分けたほうが進捗管理しやすい
- **代替案**: 既存 ExecPlan の追加改善メモに積み続ける
- **不採用理由**: 実装済み事項と将来改善候補が混ざり、次回着手時の焦点がぼやける

## Outcomes & Retrospective

- `Logger` の文脈収集を `collectCommonContext()` と `collectDebugContext()` に分離し、warning/info と error 以上の責務境界を読み取りやすくした。
- フロントエンド入口の重複コンテキスト収集を削除し、document/plugin/snippet 情報は `Logger` の共通文脈へ一本化した。
- ログ schema は `timestamp` を実際の書き込み時刻、`context.request_started_at` をリクエスト開始時刻として扱う構成へ整理した。
- コンテナ内では `log:tail` の回帰確認と、新規 warning 行での `request_started_at` 出力確認が完了した。CLI と既存ログの読取互換は維持されている。

## Context and Orientation

対象コードは主に以下にある。

- `manager/includes/logger.class.php`
- `manager/includes/document.parser.class.inc.php`
- `manager/includes/traits/document.parser.subparser.trait.php`
- `index.php`
- `manager/index.php`

関係する観点は次の通り。

- 共通コンテキスト収集: request、user、document、plugin/snippet 情報
- 詳細デバッグコンテキスト: caller、trace、fatal/exception 補足情報
- フロント補助コンテキスト: `frontend_system_log_context()`
- 記録時刻: `timestamp` と request 開始時刻の関係
- 出力責務: パス正規化、ローテーション、書き込み

ここでいう「共通文脈」は、warning でも info でも常に持っていてよい request や document の情報を指す。「debug 文脈」は、error 以上でのみ必要な stack trace や caller を指す。

## Plan of Work

最初に、どのフィールドが常時必要で、どのフィールドが高コストまたは高ノイズなのかを整理する。その上で `Logger` 内部を「常時収集する文脈」と「error 以上で追加する文脈」に分け、フロント入口側で重複して持っている情報があれば `Logger` に寄せる。

設計の基本方針は、機能追加ではなく責務整理である。見た目の出力を変えるより、同じ出力をより明確な経路で得られるようにする。大きな抽象化は避け、今後の変更単位を分けるのに必要な最小限の分割に留める。

`timestamp` については、既存フィールドの意味を壊さずに扱う。必要なら `request_started_at` のような補助フィールドを追加するが、既存 UI と CLI が読めなくなる変更はしない。

## Concrete Steps

1. 現状の責務を棚卸しする。  
   編集対象ファイル: `manager/includes/logger.class.php`, `index.php`, `manager/index.php`  
   実行コマンド: `rg -n "collectContext|frontend_system_log_context|trace_id|timestamp|request_time|caller|trace" manager/includes/logger.class.php index.php manager/index.php`  
   期待される観測結果: 共通文脈、debug 文脈、入口側補助文脈、時刻生成の担当箇所が一覧できる。

2. `Logger` の文脈収集を分離する。  
   編集対象ファイル: `manager/includes/logger.class.php`  
   実行コマンド: `php -l manager/includes/logger.class.php`  
   期待される観測結果: 共通文脈と debug 文脈の責務が読み分けやすくなり、warning でも必要文脈が欠けない。

3. フロント側補助コンテキストの責務境界を整理する。  
   編集対象ファイル: `index.php`, 必要に応じて `manager/includes/logger.class.php`  
   実行コマンド: `php -l index.php manager/includes/logger.class.php`  
   期待される観測結果: `frontend_system_log_context()` の重複が減り、frontend と manager のログ schema がより揃う。

4. 記録時刻の扱いを見直す。  
   編集対象ファイル: `manager/includes/logger.class.php`, `manager/includes/system_log.viewer.inc.php`, 必要に応じて `manager/actions/report/eventlog.dynamic.php`  
   実行コマンド: `php -l manager/includes/logger.class.php manager/includes/system_log.viewer.inc.php manager/actions/report/eventlog.dynamic.php`  
   期待される観測結果: リクエスト開始時刻とログ記録時刻の意味が明確になり、必要なら両方を観測できる。

5. 回帰確認を行う。  
   編集対象ファイル: なし  
   実行コマンド: `php evo log:tail --lines=5`, `php evo log:search "warning" --limit=5`, `php -l index.php manager/index.php manager/includes/logger.class.php`  
   期待される観測結果: CLI と画面が既存ログを読み続けられ、warning / error / fatal の記録内容に意図しない欠落がない。

## Validation and Acceptance

受け入れ条件は、内部構造が変わっても利用者から観測できるログ品質が下がらないことである。

- warning を発生させたとき、`document_identifier` などの共通文脈が欠落しない
- error 以上では caller と trace が従来どおり観測できる
- フロントと管理画面で共通フィールドの並びと意味が大きくずれない
- CLI `log:tail` / `log:search` と管理画面「システムログ」で既存ログを問題なく読める
- 記録時刻の意味がログ schema と実装で一致している

## Idempotence and Recovery

途中で中断した場合は、まず `git diff -- manager/includes/logger.class.php index.php manager/index.php manager/includes/system_log.viewer.inc.php manager/actions/report/eventlog.dynamic.php` で差分を確認する。構造整理の途中段階で warning の共通文脈や error の trace が欠けていないかを優先して確認する。

復帰時は `php -l` を対象ファイルすべてに対して実行し、次に CLI で最新ログを確認する。ログ schema を変更した場合は、古いログファイルも画面と CLI で読めるかを確認してから次の変更に進む。

## Artifacts and Notes

- 既存本体 ExecPlan: `.agent/plans/2026-04-26-logging-system-refactor.md`
- ロードマップ: `.agent/roadmap.md`
- 関連ドキュメント: `assets/docs/architecture.md`, `assets/docs/core-issues.md`

## Interfaces and Dependencies

- `Logger` は `DocumentParser::logEvent()` と管理画面 / フロントエンド入口の fatal / exception 捕捉から利用される
- ログ表示は `manager/includes/system_log.viewer.inc.php` と `manager/actions/report/eventlog.dynamic.php` に依存する
- CLI は `manager/includes/cli/commands/log-tail.php` と `manager/includes/cli/commands/log-search.php` から同じログ schema を読む
- 今回は外部送信や管理操作ログには触れない
