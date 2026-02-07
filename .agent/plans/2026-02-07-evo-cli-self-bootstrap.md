# ExecPlan: EVO CLI Self-Bootstrap (Phase 0-1)

## Purpose / Big Picture
Evolution CMS JP Edition に最小限の CLI エントリポイントと DB コマンドを追加し、後続の CLI 機能を自分自身で生成できる土台を作る。結果として、管理作業や開発補助が CLI で再現可能になる。

## Progress
- [x] (2026-02-07) 既存コードと初期化フローを確認し、CLI 配置先を `manager/includes/cli/` に決定
- [x] (2026-02-07) ルート `evo` エントリポイントを追加し、CLI ルーティングを実装
- [x] (2026-02-07) CLI ブートストラップを追加し、`DocumentParser` 初期化を有効化
- [x] (2026-02-07) 最小コマンド（`help`/`db:console`/`db:query`/`make:command`）を実装
- [x] (2026-02-07) CLI 補助機能（出力ヘルパー、`cache:clear`/`config:show`/`db:tables`/`db:describe`/`db:count`）を追加
- [x] (2026-02-07) CLI での実行時の安定化（セッション抑止、`mb_internal_encoding` ガード）を実施
- [x] (2026-02-07) Docker 環境で CLI 動作確認（`help`/`db:query`/`make:command`/`db:tables`/`db:describe`/`db:count`/`cache:clear`）
- [x] (2026-02-07) CLI README と ExecPlan を更新
- [ ] (2026-02-07) 未実装コマンドの候補を整理し、優先順位を決定（`db:export`/`db:import`/`db:backup`/`db:restore`/`health:check`）
- [ ] (2026-02-07) CLI 共通のエラー/出力形式を固め、`--json` などの方針を決める
- [ ] (2026-02-07) `evo()->logEvent()` を使った実行ログ出力方針を検討する

## Surprises & Discoveries
なし（実装中に更新）

## Decision Log
2026-02-07: コマンド名は `evo` を採用する。短く入力しやすく、既存のブランド名を保持できるため。代替案は `evolution` と `evocli`。
2026-02-07: CLI 本体は公開領域の URL 競合を避けるため `manager/includes/` 配下に配置する。`evo` エントリポイントはルート配置を許容する。

## Outcomes & Retrospective
実装後に記載

## Context and Orientation
CLI エントリポイントは未実装。ロードマップでは `cli.php` の整備とコマンドルーティングが Phase 1 に位置付けられている。`AGENTS.md` に従い、グローバルアクセスは `evo()` / `db()` / `manager()` を経由し、スーパーグローバルは禁止。CLI では `$_SERVER` が最小限なので `serverv()` などのラッパーは前提にせず、必要な情報は CLI 専用の引数や設定から取得する。CLI ブートストラップで `$_SERVER` の必要最小限を補完する場合は、互換目的で `serverv()` を使ってもよい。既存のフロントエンドは `DocumentParser` 中心だが、CLI はフロントルーティングとは独立して初期化を行う必要がある。関連ドキュメントは `assets/docs/architecture.md` と `assets/docs/events-and-plugins.md` を参照する。

用語:
CLI はコマンドラインから実行する小さなプログラム群。ブートストラップは最小限の初期化処理。コマンドルーティングは入力されたコマンド名を対応する処理に振り分ける仕組み。DocumentParser はフロントエンドのリクエスト解析と出力を担う中心クラス。

## Plan of Work
最小の CLI ディレクトリ構成を作り、`cli/evo` から `cli/bootstrap.php` を読み込む。`bootstrap.php` では必要最小限の設定読み込みと `evo()` / `db()` 初期化を行い、コマンドは `cli/commands/*.php` の手続き型で実装する。最初は `help`、`db:console`、`db:query`、`make:command` を用意し、以降は `make:command` で拡張できる状態にする。

## Concrete Steps
1. 既存コードから初期化パターンを確認する。`index.php` と `manager/includes/document.parser.class.inc.php` の読み込み順と必要定数を把握し、CLI 用の最小初期化セットを整理する。
2. `manager/includes/cli/` を作成し、`manager/includes/cli/bootstrap.php`、`manager/includes/cli/README.md`、`manager/includes/cli/commands/help.php`、`manager/includes/cli/commands/db-console.php`、`manager/includes/cli/commands/db-query.php`、`manager/includes/cli/commands/make-command.php` を追加する。`evo` エントリポイントはリポジトリルートに配置する。

```bash
chmod +x evo
```
3. `evo` で `EVO_CLI` と `EVO_CLI_START` を定義し、`manager/includes/cli/bootstrap.php` を読み込む。`$argv` からコマンド名と引数を取り、`:` を `-` に変換してファイルをロードする。
4. `bootstrap.php` で必要な設定ファイルを読み込み、`evo()` / `db()` を利用可能にする。スーパーグローバルは使用せず、`serverv()` などのラッパーを使う。
5. `db-console.php` は MySQL クライアントを安全に起動する。パスワード露出を避けるため、`--defaults-file` 用の一時ファイルを作り、終了時に削除する。
6. `db-query.php` はクエリを受け取り、`db()` を用いて実行し、結果を整形して表示する。空クエリ時は Usage を返す。
7. `make-command.php` は `command:name` を `command-name.php` に変換し、雛形を生成する。
8. `manager/includes/cli/README.md` に使い方の例を追記する。CLI を有効にする前提条件と注意点も明記する。

## Validation and Acceptance
以下のコマンドを実行し、期待結果が得られること。

```bash
php evo help
```

期待: 既存コマンド一覧が表示される。

```bash
php evo db:query "SELECT 1"
```

期待: `1` を含む結果が表示される。

```bash
php evo make:command cache:clear
```

期待: `manager/includes/cli/commands/cache-clear.php` が生成され、作成メッセージが出る。

```bash
php evo db:console
```

期待: MySQL クライアントが起動し、終了後に一時ファイルが削除される。

## Idempotence and Recovery
作成したファイルは再実行で上書きしない設計にする。`make:command` は既存ファイルがある場合に中断する。失敗時は `manager/includes/cli/` 配下と `evo` を削除すれば元の状態に戻せる。

## Artifacts and Notes
関連ファイル: `evo`, `manager/includes/cli/bootstrap.php`, `manager/includes/cli/commands/*.php`, `manager/includes/cli/README.md`  
関連ドキュメント: `assets/docs/architecture.md`, `assets/docs/events-and-plugins.md`, `assets/docs/roadmap.md`, `AGENTS.md`

## Interfaces and Dependencies
外部依存は PHP CLI と MySQL クライアント。DB 接続情報は既存設定を利用する。イベント発火や `DocumentParser` との連携が必要になった場合は `evo()->invokeEvent()` を使用する。
