# Evolution CMS JP Edition ロードマップ アーカイブ

完了済みタスクの記録。参照用途のみ（再着手・依存参照は roadmap.md を正とする）。

---

## 0. 最優先（即時対応）

### Codex レビュー観点制約の追加

- Status: `DONE`
- 着手予定日: `2026-05-04`
- 完了日: `2026-05-05`
- 目的: Codex レビューボットが agent/skill 定義ファイルの変更に対してエッジケース網羅性を要求しないよう、レビュー観点を制約する
- 背景/課題: PR #433 で 40 コミット超のレビューラリーが発生。Codex が手順書ドキュメントを「実装コードの仕様書」として扱い、エッジケース指摘を無限に生成し続けた。`.github/codex-pr-rules.md` にレビュー観点制約を追加することで改善を図る
- 到達条件（Definition of Done）:
  - `.github/codex-pr-rules.md` に `## Review scope for agent and skill definitions` セクションが追加されている
  - 次の agent/skill 定義 PR で Codex のレビュー指摘がエッジケース指摘から設計整合性確認に絞られていることを観察できる
- 非対象（やらないこと）: Codex 側の設定ファイル（`.codex/` 配下）の変更（効果がない場合の次手として残す）
- 依存関係: なし
- ExecPlan: `.agent/plans/archive/2026-05-05-codex-review-scope-limit.md`
- メモ/判断ログ: PR #433 のレビューラリー分析から、`.github/codex-pr-rules.md` が Codex のレビュー参照ファイルであることを確認済み

## 1. 基盤整備（v1.3.0 前半）

### CLI機能拡充

- Status: `DONE`
- 着手予定日: `2026-02-07`
- 完了日: `2026-02-11`
- 目的: 運用・調査・保守をブラウザ依存なしでCLI実行可能にする
- 背景/課題: 管理画面操作前提では自動化と障害切り分けが遅く、AI実装の反復速度が低下する
- 到達条件（Definition of Done）:
  - `evo` エントリーポイントとコマンドルーティングが稼働する
  - 主要運用コマンド（`help`, `db:*`, `config:show`, `cache:clear`, `health:check`, `log:show`, `log:clear`）が実行可能
  - ローカル開発でCLI中心の運用フローが再現できる
- 非対象（やらないこと）: API Router統合、管理画面UI刷新
- 依存関係: なし
- ExecPlan: `.agent/plans/archive/2026-02-11-evo-cli-self-bootstrap.md`
- メモ/判断ログ: 実装手順・検証詳細はExecPlanを参照

### システムログ機構の改修

- Status: `DONE`
- 着手予定日: `2026-04-26`
- 完了日: `2026-05-01`
- 目的: AIが機械可読なシステムログを解析し、調査と修正を自走できる基盤を整える
- 背景/課題: 既存ログはHTML形式でDB保存（管理画面表示前提）され、構造化不足により検索・分析・自動化が難しい
- 到達条件（Definition of Done）:
  - 対象をシステムログのみに限定して改修方針を実装
  - PSR-3準拠ロガーを導入し、JSONLinesで `temp/logs/system/YYYY/MM/` に保存
  - ローテーション/圧縮/削除の運用を整備
  - 管理画面「システムログ」UIとCLI（`log:tail/search system`）を提供
  - issue-resolverスキル更新、多言語対応（「イベントログ」→「システムログ」）、Sentry拡張ポイント設計、`event_log`依存の段階廃止を反映
- 非対象（やらないこと）: 管理操作ログ（`manager_log`）の改修
- 依存関係: CLI機能拡充
- ExecPlan: `.agent/plans/archive/2026-05-01-logging-system-refactor.md`
- メモ/判断ログ: 2026-04-26に実装着手。実装前にExecPlanの日付とDB書き込み廃止方針を整理済み。運用上の気づきとして、fatal 判定は warning を含めず、warning は system log に残すが trace/caller は省略する方針へ整理した。installer ログは system log と統合せず `temp/logs/install/` 配下で分離維持する。2026-05-01 時点で module 実行時の `E_DEPRECATED` / `E_USER_DEPRECATED` も system log へ残す方針を追加し、CLI・構文・画面操作を含む統合テスト完了を確認した。

### ロガー内部構造の整理

- Status: `DONE`
- 着手予定日: `未定`
- 完了日: `2026-05-02`
- 目的: システムログ機構の拡張と保守を継続しやすいように、`Logger` と周辺の責務を整理する
- 背景/課題: 現在の `Logger` は共通コンテキスト収集、詳細トレース収集、パス正規化、ローテーション、書き込みまで一括で担っており、warning での文脈欠落やフロント側との責務重複を招いた
- 到達条件（Definition of Done）:
  - 共通文脈収集と debug 文脈収集の責務を分離する
  - フロント側の `frontend_system_log_context()` と `Logger` の責務境界を整理する
  - `timestamp` と request 開始時刻の扱いを再設計し、必要なら別フィールドを追加する
  - 将来拡張時に影響範囲を局所化できる構造へ整理する
- 非対象（やらないこと）: 管理操作ログの改修、外部ログサービス実装
- 依存関係: システムログ機構の改修
- ExecPlan: `.agent/plans/archive/2026-05-02-logger-context-architecture-refactor.md`
- メモ/判断ログ: 2026-05-01 時点で、warning から `document_identifier` が欠落した不具合は解消済み。ただし構造上の責務重複と `Logger` 集約過多は残っているため、後続タスクとして独立計画化した。2026-05-02 に共通文脈収集と debug 文脈収集を分離し、frontend 側の重複コンテキスト収集を削除、`timestamp` と `request_started_at` の役割分離、およびコンテナ内での raw log 検証まで完了した。

### スキルエージェント自己成長運用基盤

- Status: `DONE`
- 着手予定日: `2026-05-02`
- 完了日: `2026-05-02`
- 目的: スキルエージェントの学びを ExecPlan 単位で収集・整理し、肥大化を抑えながら改善 proposal を継続生成できる運用基盤を整える
- 背景/課題: 実行ごとの都度内省はトークンコストが高く、改善を単純追記で続けると `SKILL.md` と references が肥大化して発火精度と保守性が落ちる
- 到達条件（Definition of Done）:
  - 実行中の軽量痕跡を `trace.jsonl` として保存するスキーマと保存先を定義する
  - ExecPlan 完了時に `learning.json`、`pruning.json`、`proposal.json` を生成する流れを定義する
  - 改善提案を `add / move / merge / retire` の4種に制限し、proposal-first で運用する
  - `SKILL.md` の肥大化を防ぐ予算ルールと `stale` 判定ルールを定義する
  - まずは対象 skill を1つに限定した検証手順を持つ
- 非対象（やらないこと）: スキル本体への自動反映、全文チャット常時保存、全 skill 一括適用
- 依存関係: CLI機能拡充, システムログ機構の改修
- ExecPlan: `.agent/plans/archive/2026-05-02-agent-skill-growth-loop.md`
- メモ/判断ログ: 2026-05-02 に、毎回 structured review を回すのではなく ExecPlan 完了時にまとめて学びを生成し、実行中は軽量トレースだけ残す方針を採用した。改善は追記だけでなく pruning を必須工程として扱う。2026-05-02 に Phase 1 の CLI 導線整備と検証シナリオ確定まで完了した。

### fatal/OOM 調査ログ拡張

- Status: `DONE`
- 着手予定日: `2026-05-03`
- 完了日: `2026-05-03`
- 目的: fatal error や OOM 発生時に、再現作業なしで原因候補を狭められる system log を残せるようにする
- 背景/課題: 現在の system log は request・document・trace の基本文脈は持つが、memory 状況、直前イベント列、障害 fingerprint がなく、同種障害の集計と断続障害の切り分けに時間がかかる
- 到達条件（Definition of Done）:
  - fatal / uncaught throwable ログに `memory_limit` / `memory_usage` / `memory_peak_usage` / `cache_status` を記録できる
  - `DocumentParser` の主要段階を起点にした `recent_events` を fatal 時のみ出力できる
  - 同種障害の追跡に使える `fatal_fingerprint` を導入する
  - 管理画面と CLI が旧ログと新ログを継続して読める
- 非対象（やらないこと）: 全リクエストの逐次トレース保存、APM 相当の性能計測、管理操作ログ改修
- 依存関係: システムログ機構の改修, ロガー内部構造の整理
- ExecPlan: `.agent/plans/archive/2026-05-03-fatal-log-observability.md`
- メモ/判断ログ: 2026-05-01 にログ調査性に関する設計メモを ExecPlan へ移行し、fatal/OOM 調査性の拡張を独立タスクとして分離した。recent events は全量逐次記録ではなく少数リングバッファ前提で検討する。

### postProcess 経路の fatal/OOM 観測

- Status: `DONE`
- 着手予定日: `2026-05-03`
- 完了日: `2026-05-03`
- 目的: `postProcess()` 内での Throwable 例外を system log に記録できるようにする
- 背景/課題: `frontend_log_shutdown_fatal` は index.php:241 で登録され、`postProcess` は `prepareResponse()` 内（line 633）で後から登録される。PHP は shutdown ハンドラを登録順に実行するため、postProcess 内で例外が発生しても `frontend_log_shutdown_fatal` は既に実行済みで postProcess 内の Throwable を拾えない。postProcess 内の Throwable を直接ログする設計へ転換。
- 到達条件（Definition of Done）:
  - postProcess 内で発生した Throwable を system log に記録できる
  - trace はセキュリティ対応としてargs を除外して正規化する
  - メモリ・キャッシュ情報を付与する
  - existing log schema と整合させる
- 非対象（やらないこと）: E_ERROR/E_PARSE などの Throwable以外の fatal error（別タスク）、shutdown ハンドラ全般の再設計
- 依存関係: fatal/OOM 調査ログ拡張
- ExecPlan: `なし`
- メモ/判断ログ: 2026-05-03 に実装。当初は nested shutdown で fatal 捕捉を試みたが、shutdown フェーズで追加登録したハンドラは同じ shutdown 内での fatal では実行されない PHP 仕様に引っかかったため、try/catch(Throwable) で即時ログ化する方式へ修正。PR レビューで trace セキュリティ指摘を受け、helper 未定義時でも args を除外する normalizeTraceFrames() を追加して対応。PR #425 でマージ完了。

### 静的ページキャッシュ経路の cache_status 識別

- Status: `DONE`
- 着手予定日: `2026-05-04`
- 完了日: `2026-05-04`
- 目的: `get_static_pages()` による静的ページキャッシュ経路を fatal ログ上でフルページキャッシュバイパスや通常キャッシュと区別できるようにする
- 背景/課題: `get_static_pages()` は `executeParser()` 内（line 311）で exit するため、`documentGenerated` が null のまま `cache_status: unknown` を返す。フルページキャッシュバイパス経路（index.php 29–74行）と同じ `unknown` になり、fatal 発生経路を特定できない。
- 到達条件（Definition of Done）:
  - 静的ページキャッシュ経路での fatal を他の `unknown` 経路と区別できる
- 非対象（やらないこと）: cache_status 全値の再設計
- 依存関係: fatal/OOM 調査ログ拡張
- ExecPlan: `なし`
- メモ/判断ログ: 2026-05-03 に PR #423 レビューで指摘。`Logger::pushEvent('static_cache.hit')` を exit 前に追加して recent_events で識別する方式が候補。

### リリースパッケージビルドの SSOT 整備

- Status: `DONE`
- 着手予定日: `2026-05-06`
- 完了日: `2026-05-06`
- 目的: リリース除外パスの定義を `.gitattributes` に一元化し、`release.yml` をシンプル化する
- 背景/課題: `.gitattributes` の `export-ignore`（3 エントリ）と `release.yml` の rsync `--exclude`（15 以上）が二重管理になっており、一方を更新した際にもう一方を更新し忘れるリスクがある
- 到達条件（Definition of Done）:
  - `.gitattributes` に `export-ignore` エントリが網羅されている
  - `release.yml` が `git archive` ベースになり rsync 除外リストが不要になっている
- 非対象（やらないこと）: リリースフロー全体の再設計
- 依存関係: なし
- ExecPlan: `.agent/plans/2026-05-06-gitattributes-export-ignore-and-release-refactor.md`
- メモ/判断ログ: `git archive` は `.gitattributes` の `export-ignore` を自動的に尊重するため SSOT が実現できる
