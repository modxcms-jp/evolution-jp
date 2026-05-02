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
php evo log:tail --lines=20
php evo log:search "インストーラー" --level=error --limit=10
php evo db:console
php evo make:command cache:clear
php evo skill:init --plan=2026-05-02-agent-skill-growth-loop --skill=issue-resolver
php evo skill:validate --run-dir=.agent/runs/2026-05-02-agent-skill-growth-loop-001
php evo skill:complete --run-dir=.agent/runs/2026-05-02-agent-skill-growth-loop-001
php evo skill:status --skill=issue-resolver
php evo skill:prune --skill=issue-resolver
php evo skill:archive --run-dir=.agent/runs/2026-05-02-agent-skill-growth-loop-001
php evo skill:sync --skill=issue-resolver
```

注意: シェル展開が必要な記号（`*` など）を含む場合は引用符で囲むことを推奨します。
`db:tables --pattern=` は `LIKE` で評価されます。`db:count --where=` は生 SQL をそのまま渡すため、条件に空白がある場合は引用符で囲んでください。
`db:export` はデフォルトで `mysqldump` を使用します。`mysqldump` が利用できない環境では `--driver=php` で組み込みの PHP ダンパーに切り替えられます。`--output` なしで実行すると SQL が標準出力へ流れるため、必要に応じてリダイレクトしてください。
`db:backup` は `snapshot_path`（未設定または不正な場合は `temp/backup/` または `assets/backup/`）へ SQL スナップショットを保存し、`--max` を超えた世代を古い順に削除します。
`health:check` はシステム要件と主要設定の簡易健全性チェックを表示します。
`log:tail` / `log:search` は `temp/logs/system/` 配下のJSONLines形式システムログを表示・検索します。
`db:import` は `system_cache` をインポート対象から除外し、事前に `TRUNCATE` します。`system_settings` はインポートした上で `site_url`/`base_url`/`filemanager_path`/`rb_base_dir` を復元します。
`skill:init` は `.agent/runs/<run_id>/` と `.agent/skill-metadata/<skill>/` の初期ファイルを生成します。
`skill:validate` は run scaffold と skill metadata の JSON 契約を確認します。
ExecPlan 完了後は `skill:validate` を通してから `skill:init` で次の run scaffold を準備します。
`skill:complete` は validate、`learning-request.json` の完了更新、次 run scaffold の準備をまとめて実行します。
`skill:status` は run の一覧と `learning-request.json` / `proposal.json` の状態を表示します。
`skill:prune` は `stats.json` と `history.jsonl` から stale 候補を抽出します。
`skill:archive` は完了済み run を `archive/` へ移し、`proposal.json` を `archived` に更新します。
`skill:sync` は run と archive を再集計し、`inventory.json` / `stats.json` / `history.jsonl` を更新します。
ホスト側で `php evo` が `mysqli` 不在になる環境では、`docker compose exec <app-service> php evo ...` を使ってください。

## 追加したコマンドの場所

`manager/includes/cli/commands/` 配下に `command-name.php` が生成されます。
