# ExecPlan: API Router基盤の先行構築（Phase 0）

## Purpose / Big Picture

REST API実装の前提として、`api.php` に集約されるルーター基盤を先行実装し、後続の認証・read・write実装を安全に分離して進められる状態を作る。これにより、機能追加時の分岐肥大化を防ぎ、API全体の拡張性と保守性を確保する。
中長期的には、フロント・管理画面・APIを単一フロントコントローラへ統合するための足場として扱う。

## Progress

- [ ] (2026-02-14) ルート定義フォーマットを確定する
- [ ] (2026-02-14) ルート登録レジストリを実装する
- [ ] (2026-02-14) `method + path` ディスパッチャを実装する
- [ ] (2026-02-14) namespace省略解決（`/api/v1/...` -> `/api/evo/v1/...`）を実装する
- [ ] (2026-02-14) 404/405/OPTIONSの標準応答を実装する

## Surprises & Discoveries

- 既存CMSにはルーティングの明示レイヤーがなく、実装箇所の分散が起きやすい。

## Decision Log

- 2026-02-14 / AI / Routerは独立Phaseで先行し、後続Phaseの共通土台とする。
- 2026-02-14 / AI / `GET /api/v1/...` は本体API省略形として解決する。
- 2026-02-14 / AI / WordPressは構造設計の参考にするが、互換目的の制約は導入しない。
- 2026-02-14 / AI / 本RouterはAPI専用に閉じず、将来の「管理画面URL変更機能」で再利用できる設計にする。
- 2026-02-14 / AI / 当面は `api.php` 先行導入で段階移行し、最終的に単一エンドポイント運用へ寄せる。

## Outcomes & Retrospective

実装後に記載

## Context and Orientation

用語:

- ルートレジストリ: ルート定義（namespace/version/path/methods/callback）を保持する仕組み。
- ディスパッチャ: 受信リクエストを一致ルートへ振り分ける処理。

対象ファイル:

- `api.php`
- `manager/includes/rest-api.inc.php`
- `manager/includes/rest-routes.php`

## Plan of Work

まずルート定義・登録・解決の責務を固定し、認証やビジネスロジックとは分離する。Router層は純粋に経路解決と標準HTTP応答に集中し、認証・レート制限は後続Phaseでミドルウェアとして接続する。これにより段階実装時のリスクを抑える。
あわせて予約パス（`/api/`, `/manager/`）の優先順位ルールを定義し、将来の管理画面URL変更時に同じ解決ルールを使えるようにする。
移行期は既存エンドポイントとの互換を維持し、運用が安定した段階で単一フロントコントローラへ統合する。

## Concrete Steps

1. ルート定義APIを作る。  
   編集対象ファイル: `manager/includes/rest-api.inc.php`  
   実行コマンド: `php -l manager/includes/rest-api.inc.php`  
   期待される観測結果: `register_rest_route()` 相当が利用できる。
2. ディスパッチャを作る。  
   編集対象ファイル: `manager/includes/rest-api.inc.php`  
   実行コマンド: `rg -n "dispatch|405|404|OPTIONS" manager/includes/rest-api.inc.php`  
   期待される観測結果: ルート未一致404、メソッド不一致405、OPTIONS応答が実装される。
3. `api.php` をフロントコントローラ化する。  
   編集対象ファイル: `api.php`  
   実行コマンド: `php -l api.php`  
   期待される観測結果: ルーティング処理が単一入口に集約される。
4. 省略namespace解決を追加する。  
   編集対象ファイル: `manager/includes/rest-api.inc.php`  
   実行コマンド: `rg -n "/api/v1|/api/evo/v1|namespace" manager/includes/rest-api.inc.php`  
   期待される観測結果: `/api/v1/...` が `/api/evo/v1/...` と同等に解決される。
5. 予約パス優先順位ルールを定義する。  
   編集対象ファイル: `manager/includes/rest-api.inc.php`（必要に応じて設定読み込み）  
   実行コマンド: `rg -n "api_prefix|manager_prefix|reserved|priority" manager/includes/rest-api.inc.php`  
   期待される観測結果: `api_prefix` と将来の `manager_prefix` を前提にした衝突回避ルールが確認できる。

## Validation and Acceptance

1. `php -l api.php manager/includes/rest-api.inc.php manager/includes/rest-routes.php` が成功すること。
2. `GET /api/v1/` で本体APIディスカバリが返ること。
3. `GET /api/evo/v1/` が `GET /api/v1/` と同等応答になること。
4. 存在しないルートで404が返ること。
5. 許可外メソッドで405が返ること。
6. 予約パスがコンテンツURLより優先解決されること。

## Idempotence and Recovery

Router層の追加に限定し、認証や業務ロジックを混ぜない。問題発生時は`api.php`と`rest-api.inc.php`差分のみで切り戻し可能にする。

## Artifacts and Notes

- `.agent/plans/2026-02-14-rest-api-foundation-security.md`
- `.agent/plans/2026-02-14-headless-read-api.md`
- `.agent/plans/2026-02-14-manager-write-api.md`

## Interfaces and Dependencies

想定エンドポイント（Router検証用）:

- `GET /api/v1/`
- `GET /api/evo/v1/`
- `GET /api.php?route=/evo/v1/`

後続依存:

- 認証・制限: `.agent/plans/2026-02-14-rest-api-foundation-security.md`
- read API: `.agent/plans/2026-02-14-headless-read-api.md`
- write API: `.agent/plans/2026-02-14-manager-write-api.md`
