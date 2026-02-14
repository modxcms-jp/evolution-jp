# ExecPlan: Headless公開Read API（Phase 2）

## Purpose / Big Picture

フロントエンドがCMSテンプレートを介さず利用できる公開Read APIを提供し、Evolution CMSをヘッドレスCMSとして運用可能にする。公開範囲を明示し、非公開データ漏えいを防ぎながら配信性能を確保する。

## Progress

- [ ] (2026-02-14) 公開対象フィールドと非公開フィールドを定義する
- [ ] (2026-02-14) resources一覧/詳細APIを実装する
- [ ] (2026-02-14) TVsとmedia metadata取得APIを実装する
- [ ] (2026-02-14) ページング/フィルタ/ソート/フィールド選択を実装する
- [ ] (2026-02-14) 非公開リソース遮断と境界値検証を完了する

## Surprises & Discoveries

- 既存リソース取得は管理画面用途と混在しているため、公開向け出力フィールドの絞り込みルールを先に決める必要がある。

## Decision Log

- 2026-02-14 / AI / `content` を含む完全本文返却は `fields` 指定がある場合のみ許可し、デフォルトは軽量メタ情報中心にする。代替案の常時全文返却は帯域負荷が高いため不採用。
- 2026-02-14 / AI / 公開APIは匿名アクセス可だが、Phase 1のレート制限を必須適用する。代替案の無制限公開は不採用。
- 2026-02-14 / AI / 非公開・権限制約ドキュメントは匿名APIから除外し、存在有無を過度に示さない応答方針を採用する。
- 2026-02-14 / AI / WordPress互換の運用感を優先し、デフォルト `per_page=10`、上限 `100`、合計件数ヘッダを返す。
- 2026-02-14 / AI / WordPressは公開GET運用の参考にするが、旧来仕様の互換は目的化せず、モダンなHTTP/認証設計を優先する。
- 2026-02-14 / AI / 本Phaseの本体read APIは汎用取得に限定し、Ditto相当の高度フィルタや独自パラメータ互換は拡張API（スニペット/プラグイン）で提供する。

## Outcomes & Retrospective

実装後に記載

## Context and Orientation

用語:

- read-only API: データ参照のみ可能で、更新系メソッドを受け付けないAPI。
- fields選択: クライアントが必要な項目のみ指定する仕組み。

対象ファイル:

- `manager/includes/rest/controllers/resources-controller.php`
- `manager/includes/rest/controllers/media-controller.php`
- `manager/includes/rest-routes.php`
- `manager/includes/rest-api.inc.php`（共通整形ロジック）

前提依存:

- `.agent/plans/2026-02-14-api-router-foundation.md` 完了済み
- `.agent/plans/2026-02-14-manager-url-routing-migration.md` 完了済み
- `.agent/plans/2026-02-14-rest-api-foundation-security.md` 完了済み

## Plan of Work

公開APIの返却仕様を固定し、最初に`resources`の一覧/詳細を実装する。次にTVとmedia metadataを追加し、共通のページングとフィルタ処理を適用する。全クエリはDBアクセス直前でエスケープし、返却前に公開許可判定を行う。管理向け内部情報（内部パス、権限状態、未公開フラグ詳細）はレスポンスに含めない。
URLは `/api/v1/...` を第一選択にし、rewrite不能環境向けに `/api.php?route=/evo/v1/...` を同等フォールバックとして提供する。
本体側は最小パラメータ（page/per_page/fields/sort/order/parents/depth）を維持し、Ditto互換の複雑条件は拡張名前空間（例: `/api/ditto/v1/...`）へ委譲する。

## Concrete Steps

1. 返却スキーマを定義する。  
   編集対象ファイル: `.agent/plans/2026-02-14-headless-read-api.md`  
   実行コマンド: `rg -n "Interfaces and Dependencies|Validation and Acceptance" .agent/plans/2026-02-14-headless-read-api.md`  
   期待される観測結果: 必須フィールド、任意フィールド、除外フィールドが明文化される。
2. resourcesコントローラを実装する。  
   編集対象ファイル: `manager/includes/rest/controllers/resources-controller.php`  
   実行コマンド: `php -l manager/includes/rest/controllers/resources-controller.php`  
   期待される観測結果: `GET /evo/v1/resources`, `GET /evo/v1/resources/<id>` が動作する。
3. mediaコントローラを実装する。  
   編集対象ファイル: `manager/includes/rest/controllers/media-controller.php`  
   実行コマンド: `php -l manager/includes/rest/controllers/media-controller.php`  
   期待される観測結果: `GET /evo/v1/media` が動作し、許可範囲のメタ情報のみ返す。
4. ルート登録を追加する。  
   編集対象ファイル: `manager/includes/rest-routes.php`  
   実行コマンド: `php -l manager/includes/rest-routes.php`  
   期待される観測結果: read-onlyルートが `GET` のみで登録される。
5. 境界値と情報漏えい防止を仕上げる。  
   編集対象ファイル: `manager/includes/rest/controllers/resources-controller.php`, `manager/includes/rest-api.inc.php`  
   実行コマンド: `rg -n "per_page|fields|404|403|sanitize" manager/includes/rest/controllers/resources-controller.php manager/includes/rest-api.inc.php`  
   期待される観測結果: 上限下限、不正パラメータ、非公開アクセスの扱いが実装される。

## Validation and Acceptance

1. `php -l manager/includes/rest/controllers/resources-controller.php manager/includes/rest/controllers/media-controller.php manager/includes/rest-routes.php` が成功すること。
2. 匿名で `GET /api/v1/resources?page=1&per_page=20`（またはフォールバックURL）が成功すること。
3. 匿名で非公開リソースIDへアクセスした際に内容が返らないこと。
4. `fields` 指定時に要求項目のみ返ること。
5. `per_page=0`、負数、過大値で下限/上限丸めが動作すること。
6. 連続アクセス時にPhase 1のレート制限が適用されること。
7. レスポンスヘッダに `X-EVO-Total` と `X-EVO-TotalPages` が含まれること。
8. `POST/PUT/PATCH/DELETE` を送ると405を返すこと。

## Idempotence and Recovery

コントローラ追加中心で既存管理機能への影響を分離する。APIルートはread-onlyに限定し、問題発生時はルート登録を外すだけで影響を局所化できる。

## Artifacts and Notes

- `assets/docs/template-system.md`
- `assets/docs/cache-mechanism.md`
- `.agent/plans/2026-02-14-rest-api-foundation-security.md`

## Interfaces and Dependencies

想定エンドポイント:

- `GET /api.php?route=/evo/v1/resources&page=<n>&per_page=<n>&fields=<csv>&q=<keyword>`
- `GET /api.php?route=/evo/v1/resources/<id>?fields=<csv>`
- `GET /api.php?route=/evo/v1/media&path=<dir>&page=<n>&per_page=<n>`
- `GET /api/v1/resources?page=<n>&per_page=<n>&fields=<csv>&q=<keyword>`
- `GET /api/v1/resources/<id>?fields=<csv>`
- `GET /api/v1/media?path=<dir>&page=<n>&per_page=<n>`

依存:

- Phase 1の認証/制限/レスポンス共通層
- Bearer opaque token認証（非公開readや将来拡張時）
- `db()->select()` と `db()->escape()`
- `evo()->logEvent()`（異常系）

既定ページング:

- `per_page` のデフォルトは `10`
- `per_page` の上限は `100`
