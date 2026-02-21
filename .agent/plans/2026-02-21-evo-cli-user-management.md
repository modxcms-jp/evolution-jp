# ExecPlan: EVO CLI ユーザー管理コマンド追加

## Purpose / Big Picture

開発時に管理画面へ入れない状況でも、CLIだけで管理ユーザーの CRUD（作成・参照・更新・削除）を実行できるようにする。AIの自動実行（非対話）と人間の手動実行（対話）の両方に対応し、直接SQL更新の運用リスクを下げる。

## Progress

- [ ] (2026-02-21) CLIの既存構造とユーザー認証ハッシュ仕様を確認する
- [ ] (2026-02-21) `user:create` / `user:update` の対話/非対話インターフェースを確定する
- [ ] (2026-02-21) `user:list` / `user:show` コマンドを実装する
- [ ] (2026-02-21) `user:create` コマンドを実装する
- [ ] (2026-02-21) `user:update` コマンドを実装する
- [ ] (2026-02-21) `user:set-password` コマンドを実装する
- [ ] (2026-02-21) `user:unlock` コマンドを実装する
- [ ] (2026-02-21) `user:delete` コマンドを実装する
- [ ] (2026-02-21) READMEと受け入れ確認手順を更新する

## Surprises & Discoveries

- 現行CLIには `user:*` コマンドがなく、ユーザー管理は `db:query` で直接更新するしかない。
- 既存CLIは `--yes` や環境変数確認のみで、対話入力の共通仕組みをまだ持っていない。
- ログイン処理は `phpass` / `md5` / `v1` を受け入れるが、`md5` ログイン成功時は自動で `phpass` に再ハッシュされる。
- ロックアウト判定は `modx_user_attributes` の `failedlogincount` / `blocked` / `blockeduntil` に依存する。
- 既存の管理画面削除処理は `manager_users` だけでなく `member_groups` / `user_settings` / `user_attributes` も同時削除している。

## Decision Log

- 2026-02-21 / AI / コマンド群は CRUD を基軸に `user:list`、`user:show`、`user:create`、`user:update`、`user:delete` を中核とし、運用頻度の高い更新系として `user:set-password` と `user:unlock` を併設する。根拠: 学習コストを下げつつ、復旧と通常運用の両方をCLIで完結するため。
- 2026-02-21 / AI / `user:create` は TTY 接続時に対話モード、`--no-interaction` または引数完備時は非対話モードで動作させる。根拠: 人間利用の操作性とAI利用の再現性を同時に満たすため。
- 2026-02-21 / AI / `user:update` も `user:create` と同一の対話規約（TTY時の質問、`--no-interaction` 時は問い合わせ禁止）にそろえる。根拠: コマンド間で操作体験と自動化規約を統一するため。
- 2026-02-21 / AI / 対話モードで収集する入力は `username`、`password`（確認入力あり）、`email` を必須とし、`fullname` は任意にする。根拠: 管理ユーザー作成に必要十分で、入力負担を増やしすぎないため。
- 2026-02-21 / AI / パスワードは平文引数を推奨せず、非対話時は `--password-stdin` を優先提供する。根拠: 履歴・プロセス一覧への露出を下げるため。
- 2026-02-21 / AI / `user:set-password` のデフォルトは `phpass` を使用し、`--hash=md5` は開発用途オプションとして明示的指定時のみ許可する。根拠: 既存認証仕様と安全性の両立。
- 2026-02-21 / AI / 対象ユーザー指定は `--username=<name>` 優先、未指定時は第1引数を後方互換として許容する。根拠: 既存CLI利用者の入力ミスを減らす。
- 2026-02-21 / AI / `user:delete` は既存管理画面の削除整合性に合わせて関連テーブル（`member_groups`、`user_settings`、`user_attributes`）も削除し、`--yes` または対話確認を必須化する。根拠: 孤児データ防止と誤操作防止の両立。
- 2026-02-21 / AI / 出力はレスポンススキーマをJSONに統一しつつ、既定の表示モードは `--format=auto` とし TTYでは `text`、非TTYでは `json` を選ぶ。根拠: 人間の可読性とAI自動処理の両立。
- 2026-02-21 / AI / `user:create` / `user:update` / `user:delete` は `--dry-run` を持ち、実変更なしで検証できるようにする。根拠: AI実行時の安全性と事前検証性を上げるため。
- 2026-02-21 / AI / 複数テーブル更新を伴う処理はDBトランザクションで一括制御し、途中失敗時はロールバックする。根拠: 部分成功による不整合を防ぐため。

## Outcomes & Retrospective

実装完了後に記載。

## Context and Orientation

用語:

- `TTY`: 端末に直接接続された入出力。対話プロンプトを安全に出せる実行環境。
- `非対話モード`: 引数と標準入力だけで完了する実行形態。AIやCI実行で使う。
- `phpass`: 現行の推奨パスワードハッシュ方式。`evo()->phpass->HashPassword()` で生成する。
- `manager_users`: 管理ユーザー本体テーブル。`id`、`username`、`password` を保持する。
- `user_attributes`: ログイン失敗回数やロック状態を保持する属性テーブル。
- `member_groups`: 管理ユーザーと権限グループの関連を保持する中間テーブル。
- `user_settings`: 管理ユーザーの個別設定を保持するテーブル。

対象ファイル:

- `manager/includes/cli/commands/user-list.php`（新規）
- `manager/includes/cli/commands/user-show.php`（新規）
- `manager/includes/cli/commands/user-create.php`（新規）
- `manager/includes/cli/commands/user-update.php`（新規）
- `manager/includes/cli/commands/user-set-password.php`（新規）
- `manager/includes/cli/commands/user-unlock.php`（新規）
- `manager/includes/cli/commands/user-delete.php`（新規）
- `manager/includes/cli/README.md`
- （必要時）`manager/includes/cli/cli-helpers.php`

関連既存コード:

- `evo`（コマンド解決エントリポイント）
- `manager/processors/login.processor.functions.php`（認証ハッシュの挙動）
- `manager/includes/cli/commands/db-query.php`（CLI出力/エラー処理パターン）
- `manager/includes/cli/commands/log-clear.php`（明示確認フラグの運用パターン）
- `manager/processors/permission/delete_user.processor.php`（削除時の関連データ整合性）

## Plan of Work

先に CRUD の責務をコマンド単位で固定する。`user:create` / `user:update` は人間向け質問フローとAI向け非対話を同じ保存層へ集約し、`user:list` / `user:show` は参照系としてレスポンススキーマを統一する。`user:delete` は削除関連テーブルの整合を保証する破壊的操作として、確認プロセスを必須化する。具体的には「入力収集層（対話/非対話）」「正規化・検証層」「保存/削除層（DB書き込み）」に責務を分離し、SSOTとして保存/削除ロジックは1箇所に統一する。`user:set-password` と `user:unlock` は `user:update` を置き換えるものではなく、運用上頻出する更新特化コマンドとして併設する。出力は `--format=auto` を既定にし、TTYでは人間向け `text`、非TTYでは機械向け `json` を返す。更新系は `--dry-run` とトランザクションで安全性を担保する。

対話フローの基本順序は以下とする。

1. `username` 入力（未入力・既存重複は再入力）
2. `password` 入力（表示しない）
3. `password_confirm` 入力（一致しない場合は再入力）
4. `email` 入力（形式不正は再入力）
5. `fullname` 入力（空許可）
6. 確認表示（作成内容サマリー。パスワードはマスク）
7. 実行確認（`[y/N]`）

`--no-interaction` 時は必須値不足を即エラー終了し、追加の問い合わせをしない。これによりAI実行でも決定論的に扱える。

### `user:create` CLIインターフェース仕様（確定案）

基本形:

- `php evo user:create [options]`

オプション一覧:

- `--username=<name>`: 作成するログインID。未指定時は対話モードで質問。非対話では必須。
- `--email=<mail>`: メールアドレス。未指定時は対話モードで質問。非対話では必須。
- `--fullname=<text>`: 表示名。未指定時は対話モードで質問（空許可）。非対話では任意。
- `--role=<id>`: ロールID。既定は `1`（管理者）。存在しないIDはエラー。
- `--password=<plain>`: 平文パスワードを引数で受け取る（互換用途）。`--password-stdin` と同時指定不可。
- `--password-stdin`: 標準入力1行目をパスワードとして受け取る。非対話での推奨方式。
- `--hash=<phpass|md5>`: パスワード保存方式。既定 `phpass`。`md5` は明示指定時のみ許可。
- `--no-interaction`: 追加質問を禁止し、必須値不足は即エラー。
- `--yes`: 最終確認をスキップして作成を実行（対話確認を省略）。
- `--dry-run`: 作成内容を検証して結果だけ表示し、DB更新は行わない。
- `--format=<auto|json|text>`: 出力形式。既定 `auto`（TTYは `text`、非TTYは `json`）。

入力ソース優先順位:

1. 明示オプション（`--username` 等）
2. `--password-stdin`（パスワードのみ）
3. 対話入力（TTYかつ `--no-interaction` なし）

バリデーション規約:

- `username`: 1文字以上100文字以下、既存ユーザー名と重複不可。
- `password`: 8文字以上を推奨。空文字は禁止。対話時は確認入力一致が必須。
- `email`: 既存管理画面と同等の形式チェックを通過すること。
- `role`: `user_roles.id` に存在すること。
- 排他制約: `--password` と `--password-stdin` は同時指定不可。

終了コード:

- `0`: 成功（ユーザー作成完了）
- `2`: 使い方エラー（未知オプション、必須引数不足、排他違反）
- `3`: 入力値検証エラー（重複ユーザー名、不正メール、不正ロール、パスワード不一致）
- `4`: 実行拒否（非TTYで対話必須、`--no-interaction` かつ入力不足、確認で中止）
- `5`: DB更新失敗（insert/update失敗、トランザクション失敗）

出力スキーマ（`json` または `auto` で非TTY時）:

- 成功: `{"ok":true,"code":0,"action":"user:create","data":{"id":<int>,"username":"..."},"meta":{"dry_run":<bool>}}`
- 失敗: `{"ok":false,"code":<2|3|4|5>,"error":{"type":"...","message":"...","field":"..."}}`

## Concrete Steps

1. 既存仕様を確定し、対話入力の実装方針を決める。  
   編集対象ファイル: なし（調査）  
   実行コマンド: `php evo help`、`rg -n "loginPhpass|loginMD5|blockeduntil|failedlogincount" manager/processors/login.processor.functions.php -S`、`rg -n "--yes|EVO_CLI_IMPORT|STDIN|fgets|readline" manager/includes/cli -S`  
   期待される観測結果: ハッシュ互換仕様、ロック解除対象カラム、CLI確認フラグの既存パターンが明確になる。

2. `user:create` / `user:update` / `user:delete` の入出力契約を定義する。  
   編集対象ファイル: `.agent/plans/2026-02-21-evo-cli-user-management.md`（本計画）  
   実行コマンド: `rg -n "user:create|user:update|user:delete|--no-interaction|--password-stdin|--yes" .agent/plans/2026-02-21-evo-cli-user-management.md -S`  
   期待される観測結果: 対話/非対話の分岐条件、必須引数、破壊的操作の確認挙動が文書化される。

3. `user:list` / `user:show` を追加する。  
   編集対象ファイル: `manager/includes/cli/commands/user-list.php`、`manager/includes/cli/commands/user-show.php`  
   実行コマンド: `php -l manager/includes/cli/commands/user-list.php`、`php -l manager/includes/cli/commands/user-show.php`、`php evo user:list --limit=20`、`php evo user:show admin`  
   期待される観測結果: `user:list` で複数ユーザーの要約が表示され、`user:show` で `id`、`username`、`email`、`role`、`failedlogincount`、`blocked`、`blockeduntil` を表示できる。

4. `user:create` を追加する。  
   編集対象ファイル: `manager/includes/cli/commands/user-create.php`、（必要時）`manager/includes/cli/cli-helpers.php`  
   実行コマンド: `php -l manager/includes/cli/commands/user-create.php`、`php evo user:create`、`php evo user:create --no-interaction --username=alice --password-stdin --email=alice@example.com`、`php evo user:create --no-interaction --username=alice --password-stdin --email=alice@example.com --dry-run --format=json`  
   期待される観測結果: TTYでは質問フローが開始し、非TTY/`--no-interaction` では不足項目がエラーになる。必須項目が揃うと `manager_users` と `user_attributes` が作成される。`--dry-run` ではDB更新せず検証結果のみ返る。終了コードは仕様表（`0/2/3/4/5`）と一致する。

5. `user:update` を追加する。  
   編集対象ファイル: `manager/includes/cli/commands/user-update.php`、（必要時）`manager/includes/cli/cli-helpers.php`  
   実行コマンド: `php -l manager/includes/cli/commands/user-update.php`、`php evo user:update --username=alice --email=alice+new@example.com`、`php evo user:update --username=alice`  
   期待される観測結果: 非対話では指定項目のみ更新し、対話では変更対象だけ質問して更新できる。

6. `user:set-password` を追加する。  
   編集対象ファイル: `manager/includes/cli/commands/user-set-password.php`  
   実行コマンド: `php -l manager/includes/cli/commands/user-set-password.php`、`php evo user:set-password --username=admin --password-stdin`  
   期待される観測結果: 既定で `phpass` ハッシュへ更新される。`--hash=md5` 指定時は md5値へ更新される。

7. `user:unlock` を追加する。  
   編集対象ファイル: `manager/includes/cli/commands/user-unlock.php`  
   実行コマンド: `php -l manager/includes/cli/commands/user-unlock.php`、`php evo user:unlock admin`  
   期待される観測結果: `failedlogincount=0`、`blocked=0`、`blockeduntil=0` へ更新される。

8. `user:delete` を追加する。  
   編集対象ファイル: `manager/includes/cli/commands/user-delete.php`  
   実行コマンド: `php -l manager/includes/cli/commands/user-delete.php`、`php evo user:delete alice`、`php evo user:delete alice --yes`  
   期待される観測結果: 対話または `--yes` で確認後、`manager_users` / `member_groups` / `user_settings` / `user_attributes` の対象データが削除される。自身の削除は拒否される。更新はトランザクションで実行され、途中失敗時はロールバックされる。

9. ドキュメントと統合確認を行う。  
   編集対象ファイル: `manager/includes/cli/README.md`  
   実行コマンド: `php evo help`、`rg -n "user:list|user:show|user:create|user:update|user:set-password|user:unlock|user:delete" manager/includes/cli/README.md -S`  
   期待される観測結果: `help` にCRUDコマンド群が出現し、READMEに対話/非対話/削除確認の実行例が追加される。

## Validation and Acceptance

1. `php evo help` に `user:list`、`user:show`、`user:create`、`user:update`、`user:set-password`、`user:unlock`、`user:delete` が表示されること。
2. `php evo user:create` 実行時、`username`→`password`→`password確認`→`email`→`fullname`→最終確認の順で対話入力できること。
3. 対話入力で不正値（空 `username`、不一致パスワード、不正メール）を与えると、該当項目だけ再入力を求めること。
4. `php evo user:list --limit=20` で一覧を表示し、`--username-like=adm%` などの条件で絞り込みできること。
5. `php evo user:create --no-interaction --username=alice --email=alice@example.com` のように必須値不足で実行した場合、問い合わせせずにエラー終了すること。
6. `printf 'StrongPass!123\n' | php evo user:create --no-interaction --username=alice --password-stdin --email=alice@example.com` で作成成功し、`php evo user:show alice` で確認できること。
7. `php evo user:create --no-interaction --username=alice --password=aaa --password-stdin --email=alice@example.com` のような排他違反時、終了コード `2` で失敗すること。
8. `php evo user:create --no-interaction --username=alice --email=bad-email --password=StrongPass!123` で終了コード `3` になること。
9. `php evo user:create --username=alice` を非TTYで実行し入力不足の場合、終了コード `4` になること。
10. `php evo user:create --no-interaction --username=alice --password=StrongPass!123 --email=alice@example.com --dry-run --format=json` 実行時、終了コード `0` かつ `meta.dry_run=true` を返し、ユーザーは作成されないこと。
11. `php evo user:create --no-interaction --username=alice --password=StrongPass!123 --email=alice@example.com` をTTYで実行した場合、既定表示が `text` になること。`--format=json` 指定時のみJSON表示になること。
12. `php evo user:update --username=alice --email=alice+new@example.com` 実行後、`user:show` の `email` が更新されること。
13. `php evo user:set-password --username=alice --password-stdin` 実行後、`user:show` 上のハッシュ形式が `phpass` であること。
14. `php evo user:unlock alice` 実行後、`failedlogincount` / `blocked` / `blockeduntil` が 0 になること。
15. `php evo user:delete alice` は確認プロンプトを表示し、`php evo user:delete alice --yes` は確認なしで実行されること。
16. `user:delete` 実行後、対象ユーザーが `manager_users` / `member_groups` / `user_settings` / `user_attributes` から消えること。

## Idempotence and Recovery

`user:list` / `user:show` / `user:unlock` は同じ引数で再実行しても同じ結果へ収束する。`user:create` は `username` 重複時に新規作成せずエラー終了するため、再実行時は入力値修正か `user:update` へ切り替える。`user:update` と `user:set-password` は復旧用に再実行可能とし、誤更新時は同コマンドで再設定できる。`user:delete` は実行後に復元できないため、誤削除時はバックアップから復旧する。実装途中で問題が出た場合は新規 `user-*` コマンドファイルのみを差し戻せば既存CLIへの影響は限定される。

## Artifacts and Notes

- `manager/includes/cli/README.md`
- `manager/processors/login.processor.functions.php`
- `manager/includes/cli/commands/db-query.php`
- `manager/includes/cli/commands/log-clear.php`
- `manager/processors/permission/delete_user.processor.php`
- `.agent/plans/2026-02-21-manager-language-mix-fix.md`（同日作業ログ）

## Interfaces and Dependencies

- CLIエントリポイント: `evo`
- DBアクセス: `db()` ヘルパー（`[+prefix+]manager_users`, `[+prefix+]user_attributes`, `[+prefix+]member_groups`, `[+prefix+]user_settings`）
- ハッシュ生成: `evo()->loadExtension('phpass')`, `evo()->phpass->HashPassword()`
- 対話入力: `STDIN` / `fgets()`（TTY判定を含む）
- 追加の外部依存は不要。既存CLI基盤で完結する。
