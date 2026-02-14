# Evolution CMS JP Edition ロードマップ (v1.3.0 – v1.5.0)

AI実装を前提とした長期計画の正本。ExecPlanと実装状況を同期して更新する。

最終更新: 2026-02-14

## 現在地（先に読む）

CLIは実装済み。着手順は下記「実行順ロードマップ（依存順）」を正とする（次工程をAPI Routerに固定しない）。

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

### 1. 基盤整備（v1.3.0 前半）

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
- ExecPlan: `.agent/plans/2026-02-07-evo-cli-self-bootstrap.md`
- メモ/判断ログ: 実装手順・検証詳細はExecPlanを参照

### システムログ機構の改修
- Status: `NEXT`
- 目的: AIが機械可読なシステムログを解析し、調査と修正を自走できる基盤を整える
- 背景/課題: 既存ログはHTML形式でDB保存（管理画面表示前提）され、構造化不足により検索・分析・自動化が難しい
- 到達条件（Definition of Done）:
  - 対象をシステムログのみに限定して改修方針を実装
  - PSR-3準拠ロガーを導入し、JSONLinesで `temp/logs/system/YYYY/MM/` に保存
  - ローテーション/圧縮/削除の運用を整備
  - 管理画面「システムログ」UIとCLI（`log:tail/search/compress/clean system`）を提供
  - issue-resolverスキル更新、多言語対応（「イベントログ」→「システムログ」）、Sentry拡張ポイント設計、`event_log`依存の段階廃止を反映
- 非対象（やらないこと）: 管理操作ログ（`manager_log`）の改修
- 依存関係: CLI機能拡充
- ExecPlan: `.agent/plans/2026-02-07-logging-system-refactor.md`

### マイグレーション機構
- Status: `NEXT`
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

### 2. ルーティング先行計画（v1.3.0 後半）

目標アーキテクチャ:

- フロント・管理画面・APIを単一フロントコントローラへ段階統合する
- 当面は `api.php` 先行導入で移行し、互換期間を経て統合する

### Phase 0: API Router基盤
- Status: `NEXT`
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

### 3. 大規模改修（v1.4.0 以降）

### PDO移行（最高優先）
- Status: `NEXT`
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
- 目的: フロント実装をVanilla JSへ統一し、依存削減と可読性向上を図る
- 背景/課題: jQuery依存はモダン実装との混在コストが高い
- 到達条件（Definition of Done）:
  - Vanilla JS (ES6+) への段階移行を進める
  - `querySelector` / `fetch` ベースへ置換する
- 非対象（やらないこと）: UIコンポーネント刷新
- 依存関係: frame要素廃止
- ExecPlan: `なし`
- メモ/判断ログ: 移行時は既存挙動の互換維持を優先
