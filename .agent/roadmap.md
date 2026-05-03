# Evolution CMS JP Edition ロードマップ (v1.3.0 – v1.5.0)

AI実装を前提とした長期計画の正本。ExecPlanと実装状況を同期して更新する。

最終更新: 2026-05-03

## 現在地（先に読む）

着手順は下記「実行順ロードマップ（依存順）」を正とする。

## 記述フォーマット（固定）

ロードマップ項目は以下のテンプレートで記載する。実装手順の詳細はExecPlanへ集約する。
日付欄は未確定の場合に `未定` / `未完了` を使う。

```md
### <タスク名>
- Status: `NEXT | WIP | DONE | BLOCKED`
- 着手予定日: `YYYY-MM-DD` または `未定`
- 完了日: `YYYY-MM-DD` または `未完了`
- 目的:
- 背景/課題:
- 到達条件（Definition of Done）:
- 非対象（やらないこと）:
- 依存関係:
- ExecPlan: `.agent/plans/YYYY-MM-DD-task-name.md` または `なし`
- メモ/判断ログ:
```

## 実行順ロードマップ（依存順）

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

### バイパスキャッシュ経路の fatal/OOM 観測
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: フルページキャッシュバイパス経路（index.php 早期 exit）での fatal/OOM も system log に残せるようにする
- 背景/課題: index.php の早期 exit（29–74行）は `register_shutdown_function` と関数定義（100行以降）より前に抜けるため、バイパス経路での fatal/OOM はシャットダウンハンドラが未登録のまま落ちる。PR #423 のログ拡張はパーサ経路のみ対象で、最も典型的なキャッシュヒット経路が観測の空白になっている。
- 到達条件（Definition of Done）:
  - バイパス経路内での fatal/OOM を system log に記録できる
  - `Logger` autoload 前でも動作する最小限の shutdown ハンドラを早期登録できる
  - パーサ経路の既存ログと schema が揃っている
- 非対象（やらないこと）: バイパス経路のパフォーマンス計測、APM 相当の全経路トレース
- 依存関係: fatal/OOM 調査ログ拡張
- ExecPlan: `なし`
- メモ/判断ログ: 2026-05-03 に PR #423 レビューで指摘。早期 exit 前に Logger autoload が完了しているかどうかの確認が実装の前提条件になる。

### postProcess 経路の fatal/OOM 観測
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: `postProcess()` shutdown ハンドラ内（OnBeforeSaveWebPageCache / OnWebPageComplete）での fatal/OOM を system log に記録できるようにする
- 背景/課題: `frontend_log_shutdown_fatal` は index.php:241 で登録され、`postProcess` は `prepareResponse()` 内（line 629）で後から登録される。PHP は shutdown ハンドラを登録順に実行するため、postProcess 内で fatal が発生した時点では `frontend_log_shutdown_fatal` は実行済みで再実行されない。PR #423 の拡張はこの経路をカバーできていない。
- 到達条件（Definition of Done）:
  - postProcess 内での fatal/OOM を system log に記録できる
  - パーサ経路の既存ログと schema が揃っている
- 非対象（やらないこと）: shutdown ハンドラ全般の再設計
- 依存関係: fatal/OOM 調査ログ拡張
- ExecPlan: `なし`
- メモ/判断ログ: 2026-05-03 に PR #423 レビューで指摘。登録順序を逆転させるか、postProcess 自身が fatal を捕捉してログする方式が候補。

### 静的ページキャッシュ経路の cache_status 識別
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: `get_static_pages()` による静的ページキャッシュ経路を fatal ログ上でフルページキャッシュバイパスや通常キャッシュと区別できるようにする
- 背景/課題: `get_static_pages()` は `executeParser()` 内（line 311）で exit するため、`documentGenerated` が null のまま `cache_status: unknown` を返す。フルページキャッシュバイパス経路（index.php 29–74行）と同じ `unknown` になり、fatal 発生経路を特定できない。
- 到達条件（Definition of Done）:
  - 静的ページキャッシュ経路での fatal を他の `unknown` 経路と区別できる
- 非対象（やらないこと）: cache_status 全値の再設計
- 依存関係: fatal/OOM 調査ログ拡張
- ExecPlan: `なし`
- メモ/判断ログ: 2026-05-03 に PR #423 レビューで指摘。`Logger::pushEvent('static_cache.hit')` を exit 前に追加して recent_events で識別する方式が候補。

### マイグレーション機構
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: DB変更を再現可能な手順として管理し、リリース時の差分適用を安全化する
- 背景/課題: スキーマ変更の適用履歴が一元管理されず、環境差分が発生しやすい
- 到達条件（Definition of Done）:
  - `up`/`down` を持つマイグレーションクラス設計を確定
  - DBバージョン管理テーブルを定義・運用可能にする
- 非対象（やらないこと）: 個別機能のスキーマ最適化
- 依存関係: CLI機能拡充
- ExecPlan: `なし`
- メモ/判断ログ: 実装前にExecPlan作成が必要

### オンラインアップデート機構（基本設計）
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: 将来の自動更新に向けた安全な更新フローの基本設計を確立する
- 背景/課題: 更新運用が手作業依存で、手順の再現性とロールバック設計が不足している
- 到達条件（Definition of Done）:
  - 更新対象・配布単位・検証・ロールバックを含む基本設計を定義
  - 既存運用との互換性と段階導入方針を明文化
- 非対象（やらないこと）: 本番運用向けの完全自動更新実装
- 依存関係: マイグレーション機構
- ExecPlan: `なし`
- メモ/判断ログ: 基本設計タスクとして扱う

### 管理操作ログ機構の改修
- Status: `BLOCKED`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: 監査要件に対応できる管理操作ログ基盤へ移行する
- 背景/課題: `manager_log` は構造化と長期運用に課題があり、監査観点で改善余地が大きい
- 到達条件（Definition of Done）:
  - JSONLinesで `temp/logs/manager/YYYY/MM/` 保存が可能
  - 管理画面「管理操作ログ」UIを提供
  - CLI拡張（`log:tail manager`, `log:search manager`）を提供
  - `manager_log`依存の段階廃止方針を実装
- 非対象（やらないこと）: システムログの再改修
- 依存関係: システムログ機構の改修
- ExecPlan: `.agent/plans/2026-02-07-manager-log-refactor.md`
- メモ/判断ログ: 現在は方針検討中。システムログ改修完了後に着手

## 2. ルーティング先行計画（v1.3.0 後半）

目標アーキテクチャ:

- フロント・管理画面・APIを単一フロントコントローラへ段階統合する
- 当面は `api.php` 先行導入で移行し、互換期間を経て統合する

### Phase 0: API Router基盤
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: APIと後続ルーティング統合の共通土台を先行整備する
- 背景/課題: 現在はルーティング責務が分散し、後続フェーズの実装効率が低い
- 到達条件（Definition of Done）:
  - `api.php` をフロントコントローラとして機能させる
  - ルート登録/ディスパッチャ/予約パス優先ルールを実装
  - namespace省略解決（`/api/v1/...` -> `/api/evo/v1/...`）を実装
- 非対象（やらないこと）: 認証・レート制限などセキュリティ層の本実装
- 依存関係: CLI機能拡充
- ExecPlan: `.agent/plans/2026-02-14-api-router-foundation.md`
- メモ/判断ログ: ルーティング先行戦略で段階統合

### Phase 0.5: 管理画面URL変更機能
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: 管理画面URLを設定可能にし、将来の単一入口化へ備える
- 背景/課題: 固定URL前提では衝突回避と運用柔軟性が不足する
- 到達条件（Definition of Done）:
  - `manager_prefix` を設定化（`.env`/設定ファイル）
  - 旧 `manager/` 導線の移行挙動を定義
  - Router優先ルールとの衝突を回避
- 非対象（やらないこと）: `manager` 公開URLの完全廃止
- 依存関係: Phase 0
- ExecPlan: `.agent/plans/2026-02-14-manager-url-routing-migration.md`
- メモ/判断ログ: Router連動で前倒し実装する

### Phase 0.8: `manager` 公開URL廃止（段階移行）
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: `manager` の公開URL依存を解消し、単一入口方針へ移行する
- 背景/課題: 直接公開URLは運用・監視・統合方針と衝突しやすい
- 到達条件（Definition of Done）:
  - 旧URL互換導線と停止条件を定義
  - 旧URL利用の監視ログを導入
  - 公開URL廃止後の物理ディレクトリ整理計画を確立
- 非対象（やらないこと）: 初期段階での物理ディレクトリ即時削除
- 依存関係: Phase 0.5
- ExecPlan: `.agent/plans/2026-02-14-manager-public-endpoint-retirement.md`
- メモ/判断ログ: URL廃止と物理整理は段階分離する

### Phase 1: REST API基盤とセキュリティ
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: 公開APIと管理APIに共通するセキュアな基盤を確立する
- 背景/課題: 統一エラー・認証・制限・監査の共通実装が未整備
- 到達条件（Definition of Done）:
  - `/api/v1/...` 優先運用（`api.php` フォールバック）を実装
  - 統一JSONエラー、認証、レート制限、監査ログを実装
- 非対象（やらないこと）: read/write API個別機能の完結
- 依存関係: Phase 0
- ExecPlan: `.agent/plans/2026-02-14-rest-api-foundation-security.md`
- メモ/判断ログ: セキュリティ層を初期段階で固定する

### Phase 2: Headless公開Read API
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: Headless運用に必要な公開read APIを提供する
- 背景/課題: 既存取得処理は公開向け契約が曖昧で、非公開情報の遮断設計が必要
- 到達条件（Definition of Done）:
  - `resources` / `media` のread-only APIを提供
  - ページング/フィルタ/fields選択を提供
  - 非公開データを遮断し、境界値検証を完了
- 非対象（やらないこと）: 更新系API（write）
- 依存関係: Phase 1
- ExecPlan: `.agent/plans/2026-02-14-headless-read-api.md`
- メモ/判断ログ: 軽量レスポンスと公開境界を重視

### Phase 3: 管理操作Write API
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: 管理操作をAPI経由で安全に実行可能にする
- 背景/課題: 既存管理画面依存の更新フローを外部連携可能な形へ整理する必要がある
- 到達条件（Definition of Done）:
  - resourceのcreate/update/publish/unpublish/delete APIを提供
  - `hasPermission()` と同一権限ルールを適用
  - 失敗時監査ログと回復導線を実装
- 非対象（やらないこと）: 業務特化ロジックの本体組み込み
- 依存関係: Phase 1, Phase 2
- ExecPlan: `.agent/plans/2026-02-14-manager-write-api.md`
- メモ/判断ログ: 基本CRUDに責務を限定し拡張は分離する

## 3. 大規模改修（v1.4.0 以降）

### PDO移行（最高優先）
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: DBアクセス層をPDOへ移行し、互換性と保守性を向上させる
- 背景/課題: 既存 `mysql_` 系互換レイヤーは将来的な保守負担が高い
- 到達条件（Definition of Done）:
  - `DBAPI` のPDOラッパーを実装
  - 既存 `mysql_` 系互換レイヤーを整理
- 非対象（やらないこと）: 全機能一括置換
- 依存関係: マイグレーション機構
- ExecPlan: `なし`
- メモ/判断ログ: 影響範囲が大きいため段階移行を前提

### frame要素廃止（最高優先）
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: 管理画面をモダン構成へ移行し、保守性とUXを改善する
- 背景/課題: frame依存構造は拡張性・互換性・開発効率に制約が大きい
- 到達条件（Definition of Done）:
  - HTML5 + Ajax + Flexbox/Gridへの段階移行計画を実装
  - ヘッダー/サイドバー/メインエリアの移行を完了
- 非対象（やらないこと）: デザイン全面刷新
- 依存関係: API Router基盤
- ExecPlan: `なし`
- メモ/判断ログ: URL/ルーティング方針と合わせて段階実施

### jQuery廃止（高優先）
- Status: `NEXT`
- 着手予定日: `未定`
- 完了日: `未完了`
- 目的: フロント実装をVanilla JSへ統一し、依存削減と可読性向上を図る
- 背景/課題: jQuery依存はモダン実装との混在コストが高い
- 到達条件（Definition of Done）:
  - Vanilla JS (ES6+) への段階移行を進める
  - `querySelector` / `fetch` ベースへ置換する
- 非対象（やらないこと）: UIコンポーネント刷新
- 依存関係: frame要素廃止
- ExecPlan: `なし`
- メモ/判断ログ: 移行時は既存挙動の互換維持を優先
