# ExecPlan: 管理画面URL変更機能の前倒し実装（Router連動）

## Purpose / Big Picture

管理画面URL変更機能をRouter基盤の直後に前倒し実装し、将来の単一フロントコントローラ統合に向けたURL解決ルールを早期に確立する。API実装より先に管理導線の可変化を完了し、衝突回避と運用性を改善する。

## Progress

- [ ] (2026-02-14) `manager_prefix` 設定仕様（`.env`/設定ファイル）を確定する
- [ ] (2026-02-14) 予約パス優先ルールに `manager_prefix` を統合する
- [ ] (2026-02-14) 既存 `manager/` への互換リダイレクト方針を実装する
- [ ] (2026-02-14) ログイン/ログアウト/セッション維持導線の動作確認を完了する
- [ ] (2026-02-14) ドキュメントと運用手順を更新する

## Surprises & Discoveries

- 管理画面URL変更は単独機能に見えるが、実際は予約パス優先順位と衝突回避ルールに依存するためRouter連動が必須。

## Decision Log

- 2026-02-14 / AI / Router Phase直後に管理画面URL変更を実装し、APIより先にURL基盤を安定化させる。
- 2026-02-14 / AI / `manager_prefix` は設定可能にしつつ、旧 `manager/` は移行期間中に互換導線（301/302または明示エラー）を提供する。
- 2026-02-14 / AI / WordPressの運用知見は参考にするが、既存互換を優先しすぎず段階移行で統制する。

## Outcomes & Retrospective

実装後に記載

## Context and Orientation

用語:

- `manager_prefix`: 管理画面のURLプレフィックス（例: `/admin/`, `/backend/`）。
- 予約パス: コンテンツURLより優先解決されるシステムURL群（`/api/`, `/manager/` など）。

対象ファイル:

- `manager/index.php`
- `index.php`（必要に応じて入口判定）
- `manager/includes/initialize.inc.php` または設定ロード箇所
- `manager/includes/rest-api.inc.php`（優先順位ルール再利用時）

前提依存:

- `.agent/plans/2026-02-14-api-router-foundation.md` 完了済み

## Plan of Work

まず `manager_prefix` の設定読み込みと優先解決ルールを実装し、管理画面の入口判定を可変化する。次に旧URL互換導線を追加して運用影響を最小化する。最後に認証導線（login/logout/session keepalive）と各主要アクションの回帰確認を実施し、設定変更手順を文書化する。

## Concrete Steps

1. `manager_prefix` 設定仕様を確定する。  
   編集対象ファイル: `.agent/plans/2026-02-14-manager-url-routing-migration.md`  
   実行コマンド: `rg -n "manager_prefix|Validation and Acceptance|Idempotence" .agent/plans/2026-02-14-manager-url-routing-migration.md`  
   期待される観測結果: 設定値、デフォルト、移行方針が明記される。
2. 管理画面入口の可変化を実装する。  
   編集対象ファイル: `manager/index.php`（必要に応じて設定ロード箇所）  
   実行コマンド: `php -l manager/index.php`  
   期待される観測結果: `manager_prefix` 変更時に管理画面へ到達できる。
3. 旧URL互換導線を実装する。  
   編集対象ファイル: `index.php` または入口判定箇所  
   実行コマンド: `rg -n "manager_prefix|redirect|Location" index.php manager/index.php`  
   期待される観測結果: 旧 `manager/` アクセス時の挙動が定義どおりになる。
4. 認証導線の回帰確認を行う。  
   編集対象ファイル: （コード変更があれば）`manager/processors/login.processor.php`  
   実行コマンド: `php -l manager/processors/login.processor.php manager/index.php`  
   期待される観測結果: ログイン/ログアウト/遷移が壊れていない。
5. 運用ドキュメントを更新する。  
   編集対象ファイル: `.agent/roadmap.md`, `AGENTS.md`（必要時）  
   実行コマンド: `rg -n "manager_prefix|管理画面URL変更機能" .agent/roadmap.md AGENTS.md`  
   期待される観測結果: 設定方法と移行順序が参照可能になる。

## Validation and Acceptance

1. `php -l manager/index.php` が成功すること。
2. デフォルト設定で従来どおり `manager/` からログインできること。
3. `manager_prefix=/admin/` に変更後、`/admin/` から管理画面に到達できること。
4. 旧 `manager/` URLへのアクセスが定義した移行挙動（リダイレクトまたは明示エラー）になること。
5. ログイン後の主要画面遷移（ホーム、リソース編集、保存）が正常であること。
6. `api_prefix` と衝突しないこと。

## Idempotence and Recovery

設定導入と入口判定変更を分離して適用する。問題発生時は `manager_prefix` を既定値へ戻すだけで復旧可能にし、コード差分は入口判定周辺に限定する。

## Artifacts and Notes

- `.agent/roadmap.md`
- `.agent/plans/2026-02-14-api-router-foundation.md`
- `assets/docs/architecture.md`

## Interfaces and Dependencies

想定設定:

- `manager_prefix=/manager/`（既定）
- `manager_prefix=/admin/`（例）

依存:

- Routerの予約パス優先ルール
- セッション認証フロー（`manager/index.php`, `login.processor.php`）

後続依存:

- `.agent/plans/2026-02-14-rest-api-foundation-security.md`
- `.agent/plans/2026-02-14-headless-read-api.md`
- `.agent/plans/2026-02-14-manager-write-api.md`
