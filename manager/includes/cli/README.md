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
php evo export:site
php evo export:site --target=allow --ids=1,2,3 --site-url=http://localhost:8080/
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
`db:backup` は管理画面のバックアップ管理と同じ保存先へ SQL スナップショットを保存します。保存先は `snapshot_path` の設定（サイトのフォルダの下にある場合だけ有効）で、未設定または不正な場合は `temp/backup/`（無くて `assets/backup/` がある場合はそちら）です。
- ファイル名は `YYYY-MM-DD-HHMMSS-<バージョン>.sql` の自動の名前になります。
- 書き出すのは、設定したプレフィックスの付いた表だけです。トリガーとストアドルーチンは含めません（管理画面から復元できる形にするため）。
- `--max`（既定は 10）を超えた世代は、自動の名前のファイルだけを古い順に削除します。利用者が名前を付けたファイルは消さず、`--max` の数え方にも含めません（`pre-restore-` は別に新しい 3 件だけ残します）。古い世代を消したときは、1 件ごとに `Removed old backup: <名前>` の行を表示します。
- 世代の上限は管理画面と共有です。`--max` に 10 より大きい値を指定しても、次に管理画面でスナップショットを作ったり復元したりすると、10 件に戻ります。
- 最後に `Backups in <保存先>: auto-named N/M, other K` の行で、自動の名前のファイルの数（N、M は `--max`）とそれ以外の数（K）を表示します。

`export:site` は管理画面の「HTML エクスポート」（a=83）と同じジョブ（状態ファイル `temp/export_job.json`）で、静的 HTML を出力先（`export_dir`、`temp/` の中）へ書き出します。
- 引数: `--target=all|allow|ignore`（既定 all）、`--ids=1,2,3`（allow / ignore のとき必須）、`--include-noncache`、`--replace-before=` / `--replace-after=`（書き出す中身の置換。既定は置換なし）、`--site-url=`（ページを取得する先のサイトの URL）、`--restart`。
- CLI ではサイトの URL が `http://localhost/` になるため、コンテナの中などで取得できないときは `--site-url=` で取得先を指定します。ページを取得する URL の先頭（サイトの URL）と丸ごと入れ替えるので、サイトがサブディレクトリにあるときは、そのパスまで含めて指定します（例: `http://localhost:8080/sub/`）。書き出す中身は変えません（中身を替えるのは `--replace-*`）。取得先はジョブの状態には保存しないので、この CLI を止めて画面から再開したときは、画面の側の取得先（設定のサイトの URL）が使われます。
- 画面のジョブが途中のときは、`--restart` が無ければ始めずに終了コード 1 で終わります。`--restart` を付けると前のジョブを捨てて始めます。始めるときは出力先を空にします。
- 進み具合は、処理が戻るたびに 100 件の区切りを越えていれば `n / 全体 (success, skipped, failed)` で表示します（表示される件数は 100 ちょうどには揃いません）。最後にも表示し、最後に所要時間と失敗の一覧（ID・ページタイトル・理由）を表示します。失敗が 1 件でもあるか、始められなかった・途中で止まったときは終了コード 1、すべて成功なら 0 です。
- 開始は操作ログ（manager_log）に `HTML export: start (CLI)`（ユーザー名 `CLI`）として残ります。`OnExportPreExec` / `OnExportExec` は画面と同じく呼ばれます。
- ほかが処理中（画面のタブで進めているなど）で続きを進められないときは、1 秒おいて送り直します。
- 画面のタブで進めている間に `--restart` で始めると、画面のジョブは止まり、CLI のジョブが最初から進みます。
  - 始める側は、開始待ちの印 `temp/export_job.takeover.<ランダムな値>` を書いて待ちます。待つのは最大で、1 回の処理の持ち時間 + 3 秒ほどです。
  - 処理中の側は、1 件ごとに印を見て、印があれば処理の切れ目で譲ります。
  - 印の期限は、ファイルの更新時刻で決めます。始める側が途中で落ちて印が残っても、期限を過ぎたものは無視して消します。
  - 画面は、別の場所でやり直された旨を出して、今の状態の表示に切り替わります。
- CLI の処理中に画面から「最初からやり直す」を押したときも同じで、CLI は譲ります。CLI は自分が始めたジョブだけを進め、別のジョブに替わったと分かったら終了コード 1 で止まります。

`health:check` はシステム要件と主要設定の簡易健全性チェックを表示します。
`log:tail` / `log:search` は `temp/logs/system/` 配下のJSONLines形式システムログを表示・検索します。
`db:import` は `EVO_CLI_IMPORT=1` を付けたときだけ実行します。
- 取り込みの前に、今の DB を `pre-restore-<自動の名前>.sql` として保存先へ保存します（`mysqldump` を使い、`db:backup` と同じくプレフィックスの表だけ、トリガーなし）。保存できなければ、何も取り込まずに終了コード 1 で終わります。プレフィックスの表がまだ無い DB（空の DB への取り込み）では保存するものが無いので、このスナップショットは作りません（`Pre-restore snapshot: skipped (...)` と表示）。
- 出力は `Pre-restore snapshot: <ファイル名>` と、完了時の `Import completed. Executed: N, Skipped: M` です。
- 途中の文が失敗したら、`Error: statement N failed: ...` と実行した文の数を表示して終了コード 1 で終わり、復元前のスナップショットから戻すよう案内します。
- `system_cache` は取り込み対象から除外し（Skipped に数えます）、事前に `TRUNCATE` します。
- `system_settings` はインポートした上で、`site_url` / `base_url` / `filemanager_path` / `rb_base_dir` / `fixed_site_url` の 5 つを取り込み先の値に戻します。
- 成功したが注意があるとき（設定かキャッシュのテーブルが無く、キャッシュを作り直さなかったとき）は、`Import completed...` の後に `Warning: <内容>` の行を標準エラーに表示します。終了コードは 0 です。
- 以前の `Preserved ...` の行は表示しません。
- `mysqldump` が必要です。

`skill:init` は `.agent/runs/<run_id>/` と `.agent/skill-metadata/<skill>/` の初期ファイルを生成します。
`skill:validate` は run scaffold と skill metadata の JSON 契約を確認します。
ExecPlan 完了後は `skill:complete` を実行し、validate と `learning-request.json` 完了更新、次 run scaffold 準備をまとめて行います。
`skill:complete` は validate、`learning-request.json` の完了更新、次 run scaffold の準備をまとめて実行します。skill 本体への反映は自動ではなく、`learning.json` / `proposal.json` を確認した上でエージェントまたは人が判断します。
`skill:status` は run の一覧と `learning-request.json` / `proposal.json` の状態を表示します。
`skill:prune` は `stats.json` と `history.jsonl` から stale 候補を抽出します。
`skill:archive` は完了済み run を `archive/` へ移し、`proposal.json` を `archived` に更新します。
`skill:sync` は run と archive を再集計し、`inventory.json` / `stats.json` / `history.jsonl` を更新します。
ホスト側で `php evo` が `mysqli` 不在になる環境では、`docker compose exec <app-service> php evo ...` を使ってください。

## スキル自己成長コマンド運用メモ

- `skill:*` 系コマンドの契約は `manager/includes/cli/skill-lib.php` を SSOT とする。
- `--plan` / `--run-id` / `--skill` は識別子形式（`[A-Za-z0-9][A-Za-z0-9._-]*`）のみ許可。
- `skill` 名に `templates` は使えない（予約名）。
- `run` ディレクトリの予約名は `templates` / `archive`。
- `skill:validate --strict` は `trace.jsonl` と metadata (`history.jsonl` 含む) の存在を必須チェックする。
- `learning-request.json` の `evidence` は許可済みファイルのみ指定でき、最低 `trace.jsonl` を含める。
- `trigger=user_feedback` の run は `evidence` に `chat.md` が必要で、`--strict` では実ファイル存在も検証される。
- 推奨フロー: `skill:complete` → `skill:archive` → `skill:sync` → `skill:prune`。
- `skill:validate` / `skill:complete` は `--run-dir` に加え `--plan` + `--run-id` 指定でも実行できる。

## 追加したコマンドの場所

`manager/includes/cli/commands/` 配下に `command-name.php` が生成されます。
