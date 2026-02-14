# ExecPlan: REST API基盤とセキュリティ層（Phase 1）

## Purpose / Big Picture

`/api/v1/...` を基本URL（rewriteなし環境は `/api.php?route=...`）としたREST API基盤を構築し、headless配信APIと管理操作APIの共通土台を固定する。攻撃耐性を初期段階で組み込み、機能追加時の再設計を防ぐ。

## Progress

- [ ] (2026-02-14) `/api/v1/...` ルーティング（`api.php` フォールバック付き）と統一JSONレスポンスを実装する
- [ ] (2026-02-14) 認証方式を二層化（公開GETは匿名、writeは認証必須）する
- [ ] (2026-02-14) Bearer opaque token + セッションnonce認証を実装する
- [ ] (2026-02-14) timestamp + nonce のリプレイ対策を実装する
- [ ] (2026-02-14) IP/APIキー単位のレート制限と429応答を実装する
- [ ] (2026-02-14) 監査ログと標準エラー規約を実装する

## Surprises & Discoveries

- 現状は `MODX_API_MODE` の利用が点在しており、共通のディスパッチャとエラー規約が未整備。

## Decision Log

- 2026-02-14 / AI / URLは `/api/v1/...` を第一選択とし、rewrite不能環境のみ `/api.php?route=...` をフォールバックにする。
- 2026-02-14 / AI / 認証はWordPress寄りに分離する。公開GETは匿名許可、write系と非公開参照は認証必須にする。
- 2026-02-14 / AI / 外部連携の標準は Bearer opaque token（DB照合）を採用し、`X-EVO-*` 署名は上級向けオプションとして提供する。
- 2026-02-14 / AI / HTTPメソッドは厳格運用し、`POST /{id}` 互換更新は実装しない。更新は `PUT/PATCH /{id}` のみ許可する。
- 2026-02-14 / AI / レート制限はIP単位とAPIキー単位の二重判定を採用。代替の単一判定は回避されやすいため不採用。
- 2026-02-14 / AI / WordPressはユーザー層と運用知見の参照先として採用するが、後方互換由来の古い制約には縛られずモダンAPI原則を優先する。
- 2026-02-14 / AI / 本体は「共通基盤（ルーティング・認証・認可・制限・標準リソース）」のみを責務とし、Ditto相当の高度検索は拡張（スニペット/プラグイン）側へ分離する。

## Outcomes & Retrospective

実装後に記載

## Context and Orientation

用語:

- dispatcher: ルートとHTTPメソッドを照合し、対応処理へ委譲する層。
- nonce: 一度だけ使うランダム値。再利用を拒否して再送攻撃を防ぐ。
- opaque token: ランダム値を発行し、ハッシュ化してDB照合する失効可能トークン。

対象ファイル:

- `api.php`
- `manager/includes/rest-api.inc.php`
- `manager/includes/rest-auth.inc.php`
- `manager/includes/rest-rate-limit.inc.php`
- `manager/includes/rest-routes.php`
- `manager/includes/helpers.php`（必要に応じて補助関数追加）

前提依存:

- `.agent/plans/2026-02-14-api-router-foundation.md` 完了済み
- `.agent/plans/2026-02-14-manager-url-routing-migration.md` 完了済み

## Plan of Work

まずルーティングとレスポンス規約を固定し、その上に認証・認可・制限を積む。公開GETは匿名で利用可能とし、write系・非公開参照は認証必須にする。全エラーは `{"ok":false,"data":null,"error":{...}}` で統一し、HTTPステータスと内部エラーコードを常に返す。セキュリティイベントは `evo()->logEvent()` に記録し、IP・route・statusを監査可能にする。
本Phaseは基盤責務に限定し、機能特化API（Ditto互換パラメータ群など）は実装しない。

## Concrete Steps

1. APIフロントコントローラとルート解決を追加する。
   編集対象ファイル: `api.php`
   実行コマンド: `php -l api.php`
   期待される観測結果: `/api/v1/...` を受け、rewrite不能時は `route` クエリで同等に処理できる。
2. ルート登録とディスパッチャを実装する。
   編集対象ファイル: `manager/includes/rest-api.inc.php`, `manager/includes/rest-routes.php`
   実行コマンド: `php -l manager/includes/rest-api.inc.php manager/includes/rest-routes.php`
   期待される観測結果: `register_rest_route()` 相当関数とメソッド判定（405）が動作し、`POST /{id}` は不許可になる。
3. 標準認証（Bearer opaque token + セッションnonce）とリプレイ防止を実装する。
   編集対象ファイル: `manager/includes/rest-auth.inc.php`
   実行コマンド: `php -l manager/includes/rest-auth.inc.php`
   期待される観測結果: 公開GETは匿名通過し、write系は認証必須。認証不備やnonce再利用時に401/403を返す。
4. レート制限を実装する。
   編集対象ファイル: `manager/includes/rest-rate-limit.inc.php`
   実行コマンド: `php -l manager/includes/rest-rate-limit.inc.php`
   期待される観測結果: 制限超過で429と`Retry-After`を返す。
5. 監査ログ統合を実装する。
   編集対象ファイル: `manager/includes/rest-api.inc.php`
   実行コマンド: `rg -n "logEvent|http_response_code|Retry-After" manager/includes/rest-api.inc.php`
   期待される観測結果: 認証失敗・制限超過・例外時のログ記録が確認できる。

## Validation and Acceptance

1. `php -l api.php manager/includes/rest-api.inc.php manager/includes/rest-auth.inc.php manager/includes/rest-rate-limit.inc.php manager/includes/rest-routes.php` が成功すること。
2. `GET /api/v1/ping`（または `GET /api.php?route=/evo/v1/ping`）で200 JSONが返ること。
3. 公開GETが匿名で利用できること。
4. write系を認証なしで呼ぶと401/403を返すこと。
5. nonce再利用・timestamp期限切れで401/403を返すこと。
6. 制限超過アクセスで429と`Retry-After`を返すこと。
7. 失敗アクセスがイベントログに構造化記録されること。
8. `POST /api/v1/.../{id}` に更新要求を送ると405を返すこと。

## Idempotence and Recovery

新規ファイル中心の実装とし、既存コードへの影響を最小化する。段階ごとに `php -l` と疎通確認を行い、問題時は追加ファイル単位で切り戻せる構成にする。

## Artifacts and Notes

- `assets/docs/architecture.md`
- `assets/docs/events-and-plugins.md`
- `manager/session_keepalive.php`

## Interfaces and Dependencies

標準認証（write系）:

- `Authorization: Bearer <token>`（opaque tokenをハッシュ照合）
- 同一オリジン向けはセッション + nonce

オプション認証（上級者向け）:

- `X-EVO-Key`
- `X-EVO-Timestamp`
- `X-EVO-Nonce`
- `X-EVO-Signature`

共通依存:

- `evo()` / `db()` / `anyv()` / `getv()` / `postv()`
- `evo()->logEvent()`

後続Plan依存:

- `.agent/plans/2026-02-14-api-router-foundation.md`
- `.agent/plans/2026-02-14-headless-read-api.md`
- `.agent/plans/2026-02-14-manager-write-api.md`
