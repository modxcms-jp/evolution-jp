# EVO CLI

Evolution CMS JP Edition の最小 CLI。コマンドは `manager/includes/cli/commands/` に配置されます。

## 使い方

```bash
php evo help
php evo db:query "SELECT 1"
php evo db:query SELECT * FROM modx_site_content
php evo config:show
php evo config:show site_name
php evo cache:clear
php evo db:tables
php evo db:tables --pattern=site_%
php evo db:describe site_content
php evo db:count site_content
php evo db:count site_content --where=published=1
php evo db:export --output=/tmp/backup.sql
php evo db:export --tables=site_content,site_templates --output=/tmp/content.sql
php evo db:export --driver=php --output=/tmp/backup.sql
EVO_CLI_IMPORT=1 php evo db:import /tmp/backup.sql
php evo db:backup
php evo db:backup --max=20
php evo db:backup --driver=php
php evo health:check
php evo log:show
php evo log:show --type=error --limit=50
php evo log:clear --yes
php evo db:console
php evo make:command cache:clear
```

注意: シェル展開が必要な記号（`*` など）を含む場合は引用符で囲むことを推奨します。
`db:tables --pattern=` は `LIKE` で評価されます。`db:count --where=` は生 SQL をそのまま渡すため、条件に空白がある場合は引用符で囲んでください。
`db:export` はデフォルトで `mysqldump` を使用します。`mysqldump` が利用できない環境では `--driver=php` で組み込みの PHP ダンパーに切り替えられます。`--output` なしで実行すると SQL が標準出力へ流れるため、必要に応じてリダイレクトしてください。
`db:backup` は `snapshot_path`（未設定または不正な場合は `temp/backup/` または `assets/backup/`）へ SQL スナップショットを保存し、`--max` を超えた世代を古い順に削除します。
`health:check` はシステム要件と主要設定の簡易健全性チェックを表示します。
`log:show` はイベントログを時系列表示します。`description` 内の HTML は CLI 表示向けに整形されます。
`log:clear` は `event_log` を全削除します。誤実行防止のため `--yes` が必須です。
`db:import` は `system_cache` をインポート対象から除外し、事前に `TRUNCATE` します。`system_settings` はインポートした上で `site_url`/`base_url`/`filemanager_path`/`rb_base_dir` を復元します。

## 追加したコマンドの場所

`manager/includes/cli/commands/` 配下に `command-name.php` が生成されます。
