# ExecPlan: 管理操作Write API（Phase 3）

## Purpose / Big Picture

WordPress REST APIに近い管理操作（resourceの作成/更新/公開制御/削除）をAPI経由で実行可能にし、外部管理ツールや自動化パイプラインから安全に運用できるようにする。既存管理画面の権限モデルをAPIへ正確に反映する。

## Progress

- [ ] (2026-02-14) write対象操作と必要権限を確定する
- [ ] (2026-02-14) manager-resourcesコントローラを実装する
- [ ] (2026-02-14) 作成/更新時の入力検証・サニタイズを実装する
- [ ] (2026-02-14) publish/unpublish/deleteを既存ルールに合わせて実装する
- [ ] (2026-02-14) 監査ログと失敗時の回復方針を実装する

## Surprises & Discoveries

- 既存のresource更新処理は管理画面フローに依存した前提があるため、API向けに最小限の共通処理抽出が必要。

## Decision Log

- 2026-02-14 / AI / write APIは全て認証必須 + permission callback必須で実装する。標準認証はBearer opaque token（外部）とセッション+nonce（同一オリジン）を採用し、`X-EVO-*` 署名はオプションとする。
- 2026-02-14 / AI / 更新は `PUT/PATCH /resources/{id}` のみを許可し、`POST /resources/{id}` 互換更新は実装しない。
- 2026-02-14 / AI / deleteは論理削除フロー（既存動作準拠）を優先し、物理削除APIは初版対象外とする。
- 2026-02-14 / AI / 大きい更新処理は可能な限り既存処理を呼び出して差分を小さくし、独自実装の重複を避ける。
- 2026-02-14 / AI / WordPressは権限モデルの参考にするが、互換維持目的の古い制約には追従せず、メソッド厳格運用とスコープ設計を優先する。
- 2026-02-14 / AI / 本体write APIはリソースの基本CRUDと公開制御に限定し、業務特化の更新ロジックは拡張（スニペット/プラグイン）APIへ分離する。

## Outcomes & Retrospective

実装後に記載

## Context and Orientation

用語:

- write API: 状態変更を伴う `POST` / `PUT` / `DELETE` エンドポイント。
- permission callback: 実行前に `evo()->hasPermission()` で可否判定する処理。

対象ファイル:

- `manager/includes/rest/controllers/manager-resources-controller.php`
- `manager/includes/rest-routes.php`
- `manager/actions/document/mutate_content/functions.php`（参照）
- `manager/processors/document/save_resource.processor.php`（参照）

前提依存:

- `.agent/plans/2026-02-14-api-router-foundation.md`
- `.agent/plans/2026-02-14-manager-url-routing-migration.md`
- `.agent/plans/2026-02-14-rest-api-foundation-security.md`
- `.agent/plans/2026-02-14-headless-read-api.md`

## Plan of Work

まず操作ごとに必要権限を明確化し、route登録時にpermission callbackを必須化する。次にcreate/update/publish/unpublish/deleteの順で実装し、入力検証とエラーレスポンスを共通化する。更新系の失敗時は副作用を最小化し、監査ログに操作内容と失敗理由を残す。既存管理画面と同じ業務ルールを使うことで挙動差異を抑える。外向けURLは `/api/v1/...` を基本にし、`/api.php?route=/evo/v1/...` をフォールバックとして併設する。
本Phaseでは本体責務を維持し、Ditto派生や独自業務フローは拡張名前空間APIで追加する前提にする。

## Concrete Steps

1. write API仕様を固定する。  
   編集対象ファイル: `.agent/plans/2026-02-14-manager-write-api.md`  
   実行コマンド: `rg -n "Interfaces and Dependencies|Validation and Acceptance|Decision Log" .agent/plans/2026-02-14-manager-write-api.md`  
   期待される観測結果: 操作一覧、HTTPメソッド、必要権限、失敗時挙動が明記される。
2. manager-resourcesコントローラを実装する。  
   編集対象ファイル: `manager/includes/rest/controllers/manager-resources-controller.php`  
   実行コマンド: `php -l manager/includes/rest/controllers/manager-resources-controller.php`  
   期待される観測結果: create/update/publish/unpublish/deleteハンドラが定義される。
3. writeルートを登録する。  
   編集対象ファイル: `manager/includes/rest-routes.php`  
   実行コマンド: `php -l manager/includes/rest-routes.php`  
   期待される観測結果: write系ルートにpermission callbackが設定される。
4. 既存ルール整合を取る。  
   編集対象ファイル: `manager/includes/rest/controllers/manager-resources-controller.php`  
   実行コマンド: `rg -n "save_document|publish_document|delete_document|db\\(\\)->(insert|update|query)" manager/includes/rest/controllers/manager-resources-controller.php`  
   期待される観測結果: 既存権限とDB更新パターンに準拠している。
5. 監査ログと回復導線を実装する。  
   編集対象ファイル: `manager/includes/rest/controllers/manager-resources-controller.php`  
   実行コマンド: `rg -n "logEvent|try|catch|rollback|error" manager/includes/rest/controllers/manager-resources-controller.php`  
   期待される観測結果: 成功/失敗操作の監査ログが残り、失敗時応答が一貫する。

## Validation and Acceptance

1. `php -l manager/includes/rest/controllers/manager-resources-controller.php manager/includes/rest-routes.php` が成功すること。
2. 有効認証 + 権限ありで `POST /api/v1/manager/resources`（またはフォールバックURL）が成功すること。
3. 権限なしユーザーで同操作を行い403が返ること。
4. `PUT /api.php?route=/evo/v1/manager/resources/<id>` で更新反映されること。
5. `POST /api.php?route=/evo/v1/manager/resources/<id>/publish` / `unpublish` が正しく反映されること。
6. `DELETE /api.php?route=/evo/v1/manager/resources/<id>` が既存ルールに沿って実行されること。
7. 不正payloadで422を返し、監査ログに失敗理由が記録されること。
8. 既存管理画面で同対象resourceを開いて整合が取れていること。
9. `POST /api/v1/manager/resources/<id>` は405を返すこと。

## Idempotence and Recovery

操作単位で実装と検証を分離し、create/update/publish/deleteを順次有効化する。障害時は対象ルートのみ一時停止できる設計とし、データ変更系は実行前後のログで追跡可能にする。

## Artifacts and Notes

- `assets/docs/events-and-plugins.md`
- `assets/docs/core-issues.md`
- `.agent/plans/2026-02-14-rest-api-foundation-security.md`
- `.agent/plans/2026-02-14-headless-read-api.md`

## Interfaces and Dependencies

想定エンドポイント:

- `POST /api/v1/manager/resources`
- `PUT /api/v1/manager/resources/<id>`
- `PATCH /api/v1/manager/resources/<id>`
- `POST /api/v1/manager/resources/<id>/publish`
- `POST /api/v1/manager/resources/<id>/unpublish`
- `DELETE /api/v1/manager/resources/<id>`
- `POST /api.php?route=/evo/v1/manager/resources`
- `PUT /api.php?route=/evo/v1/manager/resources/<id>`
- `PATCH /api.php?route=/evo/v1/manager/resources/<id>`
- `POST /api.php?route=/evo/v1/manager/resources/<id>/publish`
- `POST /api.php?route=/evo/v1/manager/resources/<id>/unpublish`
- `DELETE /api.php?route=/evo/v1/manager/resources/<id>`

必須要件:

- write系は認証必須（Bearer opaque token または セッション+nonce）
- `evo()->hasPermission()` による操作別認可
- `evo()->logEvent()` による監査

オプション要件:

- `X-EVO-*` 署名ヘッダ（上級向け強化）

依存:

- Phase 1の共通認証/制限/レスポンス
- Phase 2のread系共通整形（必要時）
