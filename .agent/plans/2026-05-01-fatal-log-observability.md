# ExecPlan: fatal/OOM 調査ログ拡張

## Purpose / Big Picture

fatal error、OOM、断続的な一過性障害が発生したときに、追加の再現作業なしで原因候補を狭められる system log を残せるようにする。既存の JSONLines ベースの system log を維持したまま、fatal 時の文脈、直近イベント、障害 fingerprint を追加し、AI と人間の双方が同種障害を追跡しやすい状態を作る。

## Progress

- [x] (2026-05-03) 現行ログ schema と fatal 捕捉経路を棚卸し完了。`collectCommonContext` で request/user/document を収集済み。memory・cache_status・recent_events・fingerprint が欠落していることを確認。
- [x] (2026-05-03) `Logger::pushEvent` / `flushRecentEvents` / `collectFatalContext` / `buildFatalFingerprint` を実装。critical 以上のログに `recent_events` と `fatal_fingerprint` を自動付与。
- [x] (2026-05-03) `DocumentParser` の `executeParser()` / `prepareResponse()` (cache.hit / cache.miss / cache.disabled) / `postProcess()` に `Logger::pushEvent` を追加。
- [x] (2026-05-03) `index.php` / `manager/index.php` の fatal / throwable ハンドラに `memory_limit` / `memory_usage` / `memory_peak_usage` / `cache_status` を追加。
- [x] (2026-05-03) viewer / CLI（system_log.viewer.inc.php, log-tail.php, log-search.php）が新フィールドを固定キーで参照しないことを確認。後方互換維持。
- [ ] (2026-05-03) 観測ベースで受け入れ確認（コンテナ内で実際のログを確認）

## Surprises & Discoveries

- 2026-05-01 時点で system log 本体は実装済みで、fatal / uncaught throwable の捕捉も `index.php` と `manager/index.php` に存在する。
- 既存の `Logger` は request、user、document、trace を収集できるが、fatal 調査に必要な memory 使用量、cache 状態、recent events、fingerprint はまだ持っていない。
- `assets/docs/architecture.md` に基づくと、recent events を埋める主戦場は `executeParser()`、`prepareResponse()`、`parseDocumentSource()`、`postProcess()` であり、フロントコントローラ単体では十分な時系列を作れない。
- 既存の `.agent/plans/2026-05-01-logger-context-architecture-refactor.md` は責務分離が主題であり、fatal 調査性の拡張とは完了条件が異なる。

## Decision Log

### 2026-05-01: 既存設計メモを ExecPlan 化して独立タスクとして扱う

- **決定**: ログ調査性に関する既存の詳細計画は ExecPlan に移し、実装の正本を `.agent/plans/2026-05-01-fatal-log-observability.md` に置く
- **理由**:
  - 実装順、検証、復旧手順を持つ実施計画としては `assets/docs/` より ExecPlan の方が適切
  - ログ改善メモのままだと進捗、発見、判断ログが更新されず、生きた計画にならない
  - system log 本体改修、logger 構造整理、fatal 調査性拡張を別タスクとして管理した方が依存関係が明確
- **代替案**: 既存ドキュメントを詳細化し続ける
- **不採用理由**: ロードマップ連携と進捗更新の導線が弱く、SSOT が docs と plans に分散する

### 2026-05-01: recent events は全量 trace ではなく少数リングバッファで扱う

- **決定**: リクエスト全体を逐次詳細ログ化せず、メモリ上に最後の少数イベントだけを保持し、fatal 時のみ出力する
- **理由**:
  - 常時詳細記録はログ量と CPU コストを増やし、通常運用のノイズも大きい
  - fatal 調査で必要なのは「直前に何が起きていたか」であり、全文履歴ではない
  - OOM 調査では書き込み前の追加メモリ消費も抑える必要がある
- **代替案**: すべてのイベントを system log に逐次書き込む
- **不採用理由**: ログ量が膨らみ、system log の用途が障害調査からトレース保管へずれる

### 2026-05-01: `DocumentParser` の処理段階を recent events の SSOT にする

- **決定**: recent events の主要な発火地点は `executeParser()`、`prepareResponse()`、`parseDocumentSource()`、`postProcess()` に集約する
- **理由**:
  - フロントのキャッシュ判定、文書解決、タグ解析、後処理という主要な状態遷移がこの4段階に収まる
  - `current_snippet` や `active_plugin` の既存文脈と合わせると、fatal 直前の行動系列を表現しやすい
  - 局所的な個別計測より、DocumentParser の段階名を軸にした方が schema と実装の整合が保ちやすい
- **代替案**: 呼び出し元ごとに任意イベントを好きな形式で追加する
- **不採用理由**: イベント名と payload が散乱し、検索・集計の再現性が落ちる

## Outcomes & Retrospective

未着手。完了時に、fatal/OOM 調査に必要だった情報が 1 レコードまたは少数の関連ログで揃うようになったか、ログ量とノイズが許容範囲に収まったかを振り返る。

## Context and Orientation

対象コードと既存責務は次の通り。

- `manager/includes/logger.class.php`
  - system log の schema 生成、共通文脈収集、trace 制御、ファイル出力を担う
- `index.php`
  - フロントエンドの uncaught throwable / shutdown fatal を system log へ流す
- `manager/index.php`
  - 管理画面の uncaught throwable / shutdown fatal を system log へ流す
- `manager/includes/document.parser.class.inc.php`
  - `executeParser()`、`prepareResponse()`、`parseDocumentSource()`、`postProcess()` がリクエスト主要段階を担う
- `manager/includes/traits/document.parser.subparser.trait.php`
  - `logEvent()` と parser 周辺補助処理を通じて既存ログとの互換経路を持つ
- `manager/includes/system_log.viewer.inc.php`
  - 管理画面と CLI が依存する system log 読み取り層

本タスクでいう recent events は、fatal が起きる前にメモリ上へ保持する少数の構造化イベント列を指す。fingerprint は、同種障害の集計用に error type、正規化済みメッセージ、file、line、snippet などから生成する安定識別子を指す。

`DocumentParser` への影響点は明示的に次の4箇所とする。

- `executeParser()`: リクエスト開始、キャッシュ短絡、入口判定などの初期イベント
- `prepareResponse()`: cache hit/miss、ドキュメント解決、権限・参照解決のイベント
- `parseDocumentSource()`: snippet / plugin / template 解析の入退場イベント
- `postProcess()`: キャッシュ書き込みや完了直前イベント

## Plan of Work

最初に、現在の fatal / exception ログに何があり、何が不足しているかを schema 観点で棚卸しする。その上で、常時保持してよい軽量フィールドと、fatal 時のみ追加する高価値フィールドを分ける。memory 情報と cache 状態は shutdown / exception ログの共通拡張として `Logger` または入口側補助関数で組み立てる。

recent events は Logger に全責務を寄せず、イベント収集 API だけを共通化し、発火地点は `DocumentParser` の主要段階に置く。これによりログ出力層と実行フローの責務を分離しつつ、fatal 時には最後の数件を一括で吐き出せるようにする。保持件数は 20 件を初期値とし、各イベントは `at_ms` と `event` を必須にする。

fingerprint は検索と集計のための安定キーとして導入するが、UI と CLI の既存互換を壊さない。まずは fatal / critical 向けのみ実装対象とし、warning への拡張は別判断に分ける。長い UA や trace 全文は常時保持せず、調査価値とサイズのバランスを優先する。

## Concrete Steps

1. 現行の fatal / exception ログ schema と収集経路を棚卸しする。  
   編集対象ファイル: `manager/includes/logger.class.php`, `index.php`, `manager/index.php`, `manager/includes/system_log.viewer.inc.php`  
   実行コマンド: `rg -n "fatal|exception|memory|getTraceId|collectContext|system log" manager/includes/logger.class.php index.php manager/index.php manager/includes/system_log.viewer.inc.php`  
   期待される観測結果: 既存の fatal フィールド、追加すべき memory / cache / fingerprint 項目、表示側の依存が一覧できる。

2. recent events の収集境界を `DocumentParser` の主要段階に割り当てる。  
   編集対象ファイル: `manager/includes/document.parser.class.inc.php`, `manager/includes/traits/document.parser.subparser.trait.php`, 必要に応じて `index.php`  
   実行コマンド: `rg -n "executeParser|prepareResponse|parseDocumentSource|postProcess|currentSnippet|activePlugin" manager/includes/document.parser.class.inc.php manager/includes/traits/document.parser.subparser.trait.php index.php`  
   期待される観測結果: `cache.hit`、`cache.miss`、`snippet.enter`、`snippet.leave`、必要なら `db.query.slow` をどこで積むかが明確になる。

3. recent events バッファの API と保存形式を設計する。  
   編集対象ファイル: `manager/includes/logger.class.php`, 必要に応じて新規補助クラスまたは `DocumentParser` 関連ファイル  
   実行コマンド: `php -l manager/includes/logger.class.php manager/includes/document.parser.class.inc.php manager/includes/traits/document.parser.subparser.trait.php`  
   期待される観測結果: fatal 時にだけ `recent_events` を含められる実装方針が定まり、通常ログには不要な肥大化が起きない。

4. memory / cache / fingerprint を fatal コンテキストへ追加する。  
   編集対象ファイル: `manager/includes/logger.class.php`, `index.php`, `manager/index.php`  
   実行コマンド: `php -l manager/includes/logger.class.php index.php manager/index.php`  
   期待される観測結果: fatal / uncaught throwable ログから `memory_limit`、`memory_usage`、`memory_peak_usage`、`cache_status`、`fatal_fingerprint` を観測できる。

5. viewer と CLI の後方互換を確認する。  
   編集対象ファイル: `manager/includes/system_log.viewer.inc.php`, `manager/actions/report/eventlog.dynamic.php`, `manager/includes/cli/commands/log-tail.php`, `manager/includes/cli/commands/log-search.php`  
   実行コマンド: `php -l manager/includes/system_log.viewer.inc.php manager/actions/report/eventlog.dynamic.php manager/includes/cli/commands/log-tail.php manager/includes/cli/commands/log-search.php`  
   期待される観測結果: 新フィールド追加後も既存ログと新ログの双方が表示・検索できる。

6. 観測ベースで受け入れ確認を行う。  
   編集対象ファイル: なし  
   実行コマンド: `php evo log:tail --lines=5`, `php evo log:search "Allowed memory size" --limit=5`, `php -l index.php manager/index.php manager/includes/logger.class.php manager/includes/document.parser.class.inc.php manager/includes/traits/document.parser.subparser.trait.php`  
   期待される観測結果: fatal/OOM ログの1件から URL、document、snippet、memory、cache 状態、recent events、fingerprint を読み取れる。

## Validation and Acceptance

受け入れ条件は、fatal/OOM 調査に必要な文脈が増えつつ、通常ログ運用と既存 reader が壊れないことである。

- fatal または uncaught throwable を発生させたとき、system log に `memory_limit`、`memory_usage`、`memory_peak_usage` が入る
- フロントエンド fatal では `document_identifier`、`current_snippet`、`cache_status`、`recent_events` を合わせて確認できる
- `recent_events` は時系列配列で、各要素に最低限 `at_ms` と `event` がある
- `fatal_fingerprint` により、同種エラーを `log:search` や画面検索で追跡しやすい
- 既存の system log viewer と CLI が旧ログと新ログを継続して読める
- 通常の warning / info ログに recent events や重い trace が常時ぶら下がらない

## Idempotence and Recovery

途中で中断した場合は、まず `git diff -- manager/includes/logger.class.php index.php manager/index.php manager/includes/document.parser.class.inc.php manager/includes/traits/document.parser.subparser.trait.php manager/includes/system_log.viewer.inc.php manager/actions/report/eventlog.dynamic.php manager/includes/cli/commands/log-tail.php manager/includes/cli/commands/log-search.php` で差分を確認する。recent events の導入途中で schema と reader がずれやすいため、復帰時は reader 側互換と fatal 入口側の両方を優先確認する。

復帰時は `php -l` を対象ファイルへ順に実行し、その後 `php evo log:tail --lines=3` で最新ログが読めることを確認する。途中で導入した event 名や fingerprint ルールを変更する場合は、ExecPlan の Decision Log に理由を追記してから再開する。

## Artifacts and Notes

- 設計メモの移行元: 2026-05-01 時点で廃止済みの旧ログ調査メモ
- 既存本体 ExecPlan: `.agent/plans/2026-04-26-logging-system-refactor.md`
- 関連 ExecPlan: `.agent/plans/2026-05-01-logger-context-architecture-refactor.md`
- ロードマップ: `.agent/roadmap.md`
- 関連ドキュメント: `assets/docs/architecture.md`, `assets/docs/cache-mechanism.md`, `assets/docs/template-system.md`, `assets/docs/events-and-plugins.md`

## Interfaces and Dependencies

- 前提依存: system log 本体改修が完了していること
- 推奨依存: `Logger` の責務分離を扱う `.agent/plans/2026-05-01-logger-context-architecture-refactor.md` の判断と整合すること
- `Logger` は fatal / exception の schema 拡張、fingerprint 生成、recent events の受け渡し口になる
- `DocumentParser` は recent events の発火源になるため、`executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()` の既存責務を壊さずに最小追加で実装する
- 管理画面 viewer と CLI reader は未知フィールドを許容する必要がある
- 今回は外部送信、Sentry 実装、管理操作ログ改修、常時詳細トレース化は対象外とする
