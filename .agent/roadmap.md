# Evolution CMS JP Edition ロードマップ (v1.3.0 – v1.5.0)

AI実装を前提とした長期計画の正本。ExecPlanと実装状況を同期して更新する。

最終更新: 2026-02-14

## 現在地（先に読む）

- [x] **実装済み（確認済み）**
    - [x] CLIエントリーポイント `evo` の整備
    - [x] コマンドルーティング（`foo:bar` 形式）
    - [x] CLIコマンド群の実装（DB/設定/キャッシュ/ログ/ヘルスチェック系）
- [ ] **未着手または実装未確認**
    - [ ] API Router基盤（`api.php`, `rest-*`）
    - [ ] `manager_prefix` 導入と管理画面URL移行
    - [ ] REST API基盤（認証/レート制限/監査ログ）
    - [ ] Headless Read API / Manager Write API
    - [ ] ファイルベースの新ログ基盤（system/manager）

## 実行順ロードマップ（依存順）

### 1. 基盤整備（v1.3.0 前半）

- [x] **CLI機能拡充**
    - [x] `evo` エントリーポイント
    - [x] コマンドルーティング
    - [x] 主要運用コマンド（`help`, `db:*`, `config:show`, `cache:clear`, `health:check`, `log:show`, `log:clear`）
- [ ] **システムログ機構の改修**（AI自走デバッグ）
    - [ ] PSR-3準拠ロガー (`manager/includes/logger.class.php`)
    - [ ] JSONLines保存 (`temp/logs/system/YYYY/MM/`)
    - [ ] ローテーション/圧縮/削除運用
    - [ ] 管理画面「システムログ」UI
    - [ ] CLI拡張（`log:tail system`, `log:search system`, `log:rotate system`, `log:compress system`, `log:clean system`）
    - [ ] `event_log` 依存の段階廃止
- [ ] **マイグレーション機構**
    - [ ] `up`/`down` を持つクラス設計
    - [ ] DBバージョン管理テーブル
- [ ] **オンラインアップデート機構**（基本設計）
- [ ] **管理操作ログ機構の改修**
    - [ ] JSONLines保存 (`temp/logs/manager/YYYY/MM/`)
    - [ ] 管理画面「管理操作ログ」UI
    - [ ] CLI拡張（`log:tail manager`, `log:search manager`）
    - [ ] `manager_log` 依存の段階廃止

### 2. ルーティング先行計画（v1.3.0 後半）

目標アーキテクチャ:

- フロント・管理画面・APIを単一フロントコントローラへ段階統合する
- 当面は `api.php` 先行導入で移行し、互換期間を経て統合する

- [ ] **Phase 0: API Router基盤**
    - [ ] `api.php` をフロントコントローラ化
    - [ ] ルート登録/ディスパッチャ/予約パス優先ルール
    - [ ] namespace省略解決（`/api/v1/...` -> `/api/evo/v1/...`）
    - [ ] ExecPlan: `.agent/plans/2026-02-14-api-router-foundation.md`
- [ ] **Phase 0.5: 管理画面URL変更機能**
    - [ ] `manager_prefix` 設定化（`.env`/設定ファイル）
    - [ ] 旧 `manager/` 導線の移行挙動定義
    - [ ] Router優先ルールとの衝突回避
    - [ ] ExecPlan: `.agent/plans/2026-02-14-manager-url-routing-migration.md`
- [ ] **Phase 0.8: `manager` 公開URL廃止（段階移行）**
    - [ ] 旧URL互換導線と停止条件定義
    - [ ] 旧URL利用の監視ログ導入
    - [ ] 公開URL廃止後の物理ディレクトリ整理
    - [ ] ExecPlan: `.agent/plans/2026-02-14-manager-public-endpoint-retirement.md`
- [ ] **Phase 1: REST API基盤とセキュリティ**
    - [ ] `/api/v1/...` 優先（`api.php` はフォールバック）
    - [ ] 統一JSONエラー/認証/レート制限/監査ログ
    - [ ] ExecPlan: `.agent/plans/2026-02-14-rest-api-foundation-security.md`
- [ ] **Phase 2: Headless公開Read API**
    - [ ] `resources` / `media` read-only API
    - [ ] ページング/フィルタ/fields
    - [ ] 非公開データ遮断
    - [ ] ExecPlan: `.agent/plans/2026-02-14-headless-read-api.md`
- [ ] **Phase 3: 管理操作Write API**
    - [ ] resource の create/update/publish/unpublish/delete
    - [ ] `hasPermission()` と同一ルール適用
    - [ ] 失敗時監査ログと回復導線
    - [ ] ExecPlan: `.agent/plans/2026-02-14-manager-write-api.md`

### 3. 大規模改修（v1.4.0 以降）

- [ ] **PDO移行（最高優先）**
    - [ ] `DBAPI` のPDOラッパー実装
    - [ ] 既存 `mysql_` 系互換レイヤー整理
- [ ] **frame要素廃止（最高優先）**
    - [ ] HTML5 + Ajax + Flexbox/Grid へ段階移行
    - [ ] ヘッダー/サイドバー/メインエリア移行
- [ ] **jQuery廃止（高優先）**
    - [ ] Vanilla JS (ES6+) へ段階移行
    - [ ] `querySelector` / `fetch` 活用
