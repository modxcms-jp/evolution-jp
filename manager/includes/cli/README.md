# EVO CLI

Evolution CMS JP Edition の最小 CLI。コマンドは `manager/includes/cli/commands/` に配置されます。

## 使い方

```bash
php evo.php help
php evo.php db:query "SELECT 1"
php evo.php db:query SELECT * FROM modx_site_content
php evo.php config:show
php evo.php config:show site_name
php evo.php cache:clear
php evo.php db:tables
php evo.php db:tables --pattern=site_%
php evo.php db:describe site_content
php evo.php db:count site_content
php evo.php db:count site_content --where=published=1
php evo.php db:export --output=/tmp/backup.sql
php evo.php db:export --tables=site_content,site_templates --output=/tmp/content.sql
php evo.php db:export --driver=php --output=/tmp/backup.sql
EVO_CLI_IMPORT=1 php evo.php db:import /tmp/backup.sql
php evo.php db:backup
php evo.php db:backup --max=20
php evo.php db:backup --driver=php
php evo.php export:site
php evo.php export:site --target=allow --ids=1,2,3 --site-url=http://localhost:8080/
php evo.php content:extract-datauri --dry-run
php evo.php content:extract-datauri --ids=1,2,3 --limit=50
php evo.php health:check
php evo.php log:tail --lines=20
php evo.php log:search "インストーラー" --level=error --limit=10
php evo.php log:tail --level=min:error
php evo.php log:clear --before=2026-01-01
php evo.php db:console
php evo.php make:command cache:clear
php evo.php migrate
php evo.php migrate:status
php evo.php make:migration add_example_column
php evo.php skill:init --plan=2026-05-02-agent-skill-growth-loop --skill=issue-resolver
php evo.php skill:validate --run-dir=.agent/runs/2026-05-02-agent-skill-growth-loop-001
php evo.php skill:complete --run-dir=.agent/runs/2026-05-02-agent-skill-growth-loop-001
php evo.php skill:status --skill=issue-resolver
php evo.php skill:prune --skill=issue-resolver
php evo.php skill:archive --run-dir=.agent/runs/2026-05-02-agent-skill-growth-loop-001
php evo.php skill:sync --skill=issue-resolver
```

注意: シェル展開が必要な記号（`*` など）を含む場合は引用符で囲むことを推奨します。
`db:count`・`db:describe`・`db:export --tables=` の表の名前は、接頭辞の付いた名前でも、接頭辞を除いた名前でも渡せます。このサイトの表（接頭辞の付いた表）のうち、渡した名前のとおりの表があればそれを、無ければ接頭辞を付けた名前の表を使います。どちらも無ければエラーで終わります。

`db:tables --pattern=` は `LIKE` で評価されます。`db:count --where=` は生 SQL をそのまま渡すため、条件に空白がある場合は引用符で囲んでください。
`db:export` はデフォルトで `mysqldump` を使用します。`mysqldump` が利用できない環境では `--driver=php` で組み込みの PHP ダンパーに切り替えられます。`--output` なしで実行すると SQL が標準出力へ流れるため、必要に応じてリダイレクトしてください。標準出力へは書きながら流すので、途中で失敗したときは途中までの SQL が出たあと、終了コード 1 で終わります（`mysqldump` と同じ）。
`db:backup` は管理画面のバックアップ管理と同じ保存先へ SQL スナップショットを保存します。中身は管理画面のスナップショットと同じです（ログの表は構造だけ）。保存先はグローバル設定の「ファイル」タブの「スナップショットの保存先」（`snapshot_path`。既定は `temp/backup/`、相対パスはサイトのフォルダから）です。サイトのフォルダの外を指しているときや、作れない・書き込めないときは、別の場所に切り替えずにエラーで終わります（`db:import` も、復元の前の控えを取れないので何もしません）。
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
- ページの取得でのリダイレクトは、curl の有無や `open_basedir` に関わらず、書き出し側が同じサイト（scheme・host・port が同じ）の中だけ最大 3 回辿ります。同じホスト・既定のポートでの http から https への切り替えも辿ります。`--site-url` で取得先を置き換えているときは、設定のサイトの URL へのリダイレクト（Web リンクのフォルダなど）も同じサイトとして、先頭を取得先に読み替えて辿ります。それ以外の別のサイトへのリダイレクトは失敗（`HTTP 302 → another site`。転送先に合わせて `--site-url`（画面ではグローバル設定のサイトの URL）を直す案内を添えます）で、合言葉のヘッダー（`X-Evo-Export-Token`）と Basic 認証の ID・パスワードは元のサイトへの要求にしか付けません。
- 取得するのは、取得先のサイト（`--site-url`、無ければ設定のサイトの URL）の http・https の URL だけです。サイトの外の URL は取得しません（失敗の詳細は `not fetched: the URL is outside the site`）。Web リンクのフォルダは、リンク先の URL が取得先のサイトの中のときだけページを取得し、外部の URL などのときはフォルダだけを作ります。未公開のフォルダもフォルダだけを作り（ページは取得せず、スキップとして数えます）、下の公開のページは通常どおり書き出します。
- サイトが非公開（公開ステータスがオフ）のときは、続きを進めるたびに期限 10 分の合言葉（ベアラートークン）を発行し、ページの取得に専用のヘッダー `X-Evo-Export-Token` で付けます（`DocumentParser` が照合し、非公開の門を通します。`Authorization: Bearer` も受け付けます）。ジョブが終わると取り消します。
- 取得先が Basic 認証（HTTP ベーシック認証）の場所のときは、ID とパスワードを次のどちらかで渡します。引数では受けません（プロセスの一覧やシェルの履歴に残るため）。
  - 端末（STDIN が対話）で動かす: 401 に当たったところで ID とパスワードを聞きます（パスワードは表示しません）。いちばん安全なので、手で動かすときはこれを使います。違っていれば聞き直し、3 回通らなければエラーで止まります。`stty` で表示を止められない環境（Windows など）では聞かずに止まります。
  - 環境変数 `EVO_EXPORT_BASIC_AUTH=ID:パスワード`: 端末の無い自動の実行（CI のシークレットなど）向けです。コマンドの行に値を書くと、シェルの履歴やプロセスの一覧に残るので書きません。Docker では、ホストで `read -rs` などで変数に入れて `export` してから、`docker compose exec -e EVO_EXPORT_BASIC_AUTH app php evo.php export:site`（`-e` には名前だけを書き、値はホストの環境から渡す）のように動かします。読んだあとは、コマンドの中で消します（子のプロセスへ渡しません）。通らなければ聞き直さずに止まります。
  - どちらも無いまま 401 に当たったとき、ID を空や「:」を含むものにしたときは、ジョブを中止して終了コード 1 で止まります。
  - ID とパスワードは、ジョブの状態のファイル・ログには書きません。画面（a=83）では、401 に当たるとダイアログで聞き、そのジョブの間だけ PHP のセッションに持ちます（終わる・中止する・新しく始めると消します。途中で画面を離れたあとにジョブが終わったときは、次にエクスポートの画面を開いたときに消します）。
  - 3 回通らずに止まったときは、401 になったページを失敗の一覧に出します（サイトの一部だけ別の ID の場所があるときなどに、原因のページが分かるように）。
- 1 回の処理の持ち時間は 10 秒です（管理画面から進めるときは、進み具合を細かく更新するため 3 秒）。
- 同時に取得する 1 まとまりには 10 秒の締め切りがあります。過ぎると送り直しとリダイレクトの追跡を打ち切り、結果の無い件は通信の失敗にします（通信の失敗は、本文の途中の切断も含めて最大 2 回送り直します）。
- 開始は操作ログ（manager_log）に `HTML export: start (CLI)`（ユーザー名 `CLI`）として残ります。`OnExportPreExec` / `OnExportExec` は画面と同じく呼ばれます。
- ほかが処理中（画面のタブで進めているなど）で続きを進められないときは、1 秒おいて送り直します。
- 画面のタブで進めている間に `--restart` で始めると、画面のジョブは止まり、CLI のジョブが最初から進みます。
  - 始める側は、開始待ちの印 `temp/export_job.takeover.<ランダムな値>` を書いて待ちます。待つのは最大で、1 回の処理の持ち時間（10 秒）+ 1 まとまりの締め切り（10 秒）+ 3 秒です（PHP の制限時間の半分を超えない）。
  - 処理中の側は、同時に取得する 1 まとまり（最大 4 件）ごとに印を見て、印があれば処理の切れ目で譲ります。
  - 印の期限は、ファイルの更新時刻で決めます。始める側が途中で落ちて印が残っても、期限を過ぎたものは無視して消します。
  - 画面は、別の場所でやり直された旨を出して、今の状態の表示に切り替わります。
- CLI の処理中に画面から「最初からやり直す」を押したときも同じで、CLI は譲ります。CLI は自分が始めたジョブだけを進め、別のジョブに替わったと分かったら終了コード 1 で止まります。

`health:check` はシステム要件と主要設定の簡易健全性チェックを表示します。
`migrate` は `manager/includes/migrations/` の未適用のマイグレーションをファイル名の順に適用し、記録の表（`{prefix}migrations`）に 1 本ずつ記録します。
- 適用した ID を `Applied <ID>` と 1 行ずつ表示し、未適用が無ければ `Nothing to migrate.` と表示します。
- 流す前に `00000000_baseline` が適用済みかを確かめます。baseline のファイルがあるのに記録が無い（記録の表が無い DB を含む）ときは、アップデーターを通していない古い版の DB と見なし、何も流さずに `This database has not been upgraded with the installer yet. ...` を標準エラーに表示して終了コード 1 で終わります。古い版の DB はアップデーター（`install/`）で上げてください（イベント名の同期・utf8mb4 への変換・同梱の要素の入れ直しはアップデーターだけが行うため）。baseline のファイルがまだ無いときは、この確認では止めません。記録を読めないときは `Error: ...` を標準エラーに表示して終了コード 1 で終わります。
- 1 本でも適用した、または失敗したときは、最後にキャッシュを消します。失敗の文は、キャッシュを消す前に標準エラーへ出します。
- マイグレーションの ID が 100 文字を超えるなど、一覧を読めないときも、`migrate:status` と同じく標準エラーに文を出して終了コード 1 で終わります。
- 失敗したら、ID とエラーを標準エラーに表示して終了コード 1 で終わります（失敗したマイグレーションは記録せず、次回はそこから流し直します）。
- 同時に 2 回走らないよう `GET_LOCK()` を使い、30 秒待っても取れなければ失敗にします。
マイグレーションの仕組みと書き方は [`assets/docs/migrations.md`](../../../assets/docs/migrations.md) を参照してください。
`migrate:status` は `applied <ID> <日時>` と `pending <ID>` を並べ、最後に `N applied, M pending` を表示します。記録の表がまだ無いときは、先頭に `Migrations table not found (every migration is pending).` を表示します。記録はあるのにファイルが無いマイグレーションは `applied <ID> <日時> (file missing)` と表示します。
`make:migration <説明>` は `manager/includes/migrations/<YYYYMMDD_HHMMSS>_<説明>.php` に雛形を作ります。説明は `a-z`・`0-9`・`_` だけで、合わなければ終了コード 1 です。
`content:extract-datauri` は、本文などに入っている `data:` 画像（`<img>` の `src` の Data URI）を、ファイルピッカーの置き場所 `{rb_base_dir}/images/年/月/` にファイルとして書き、その URL に書き換えます。保存時の変換（`convert_datauri_to_file`）と同じ処理を使います（`assets/docs/datauri-conversion.md`）。ウェブサーバーと同じユーザーで実行してください（別のユーザーで動かすと、作られた `images/年/月/` の所有者が変わり、ウェブ側から書けなくなることがあります）。
- 引数: `--dry-run`（移すものを、文書の ID・欄・大きさ・MIME の一覧にして出す。何も変えず、控えも取らない）、`--ids=1,2,3`（対象の文書の ID。`export:site` と同じ書き方）、`--limit=N`（移す画像のある文書を、ID の小さい順に N 件まで）。
- 対象: `site_content` の `content`・`introtext`、`site_tmplvar_contentvalues` の `value`、`site_revision` の下書き（`element='resource'`、`version=0`）の本文・要約・TV。スニペット・テンプレートなどのコードの表は対象外です。TV の値は、`<img>` の `src` に加えて、値そのものが `data:image/...` のとき（画像の TV）も URL に書き換えます。下書きの中身は、シリアライズされた配列の本文・要約・`tv` の値を直します。
- 置き場所と名前: `images/年/月/pasted-doc-{文書ID}-{連番}.拡張子`。年月は、その文書の更新日時（`editedon`。下書きは下書きの更新日時）で決めるので、何度流しても同じ場所になります。連番は、そのフォルダで同じ ID の空いている次の番号（1 から）です。
- 移さずに残すもの: 復号後に 50MB を超えるもの、壊れた base64、ピッカーの「アップロードできる画像」（`upload_images`。既定では webp は入っていません）にない拡張子。一覧に理由を出し、本文はそのままです。`<img>` の `width`・`height` 属性が元の画像より小さいときは、保存時の変換と同じに縮小します。
- 書き換えの前に、DB の控え（スナップショット。管理画面のスナップショットと同じ PHP の書き手で、このサイトの表の全部）を自動で取り、`Backup written: <パス>` を出します。取れなければ、何もせずに終了コード 1 です。控えの世代の上限（既定 10）を超えると、古い自動の名前の控えが消えます（`Removed old backup: <名前>` を表示）。
- 文書の更新日時（`editedon`）は変えません。直したあとに、ページキャッシュを消します（サイト全体）。
- ファイルを書いてから DB を直します。DB の更新に失敗したら、そのファイルを消し、終了コード 1 で終わります。2 回目は何も変わりません（移せずに残したものは、毎回一覧に出ます）。
- 終了時の出力は `Moved N image(s) in M document(s), K left as is, F failed.` です。

`log:tail` / `log:search` は `temp/logs/system/` 配下のJSONLines形式システムログを表示・検索します。`--level=error` はその重さだけ、`--level=min:error` はその重さ以上（error・critical・alert・emergency）を出します。
`log:clear` は `temp/logs/system/` 配下のシステムログ（`system-日付.log` とローテーション分）を削除し、空になった `YYYY/MM`・`YYYY` のフォルダも消します。実行前に `[y/N]` で確認し、STDIN が対話でないとき（対話かどうかを判定できない環境も含む）は `--force` がなければ終了コード 1 で拒否します。`n` などで取り消したときも終了コード 1 です。`--before=YYYY-MM-DD` はファイル名の日付がその日より前のものだけを消します（値が空・日付の形が不正なら、何も消さず終了コード 1）。`Deleted: N` と `Failed: M` を表示し、消せなかったパス（読めないフォルダを含む。読めるところは消します）は標準エラーへ出して終了コード 1 にします。削除のあと、システムログに「Cleared system logs」を 1 件記録します。ウェブサーバーと同じユーザーで実行してください（別のユーザーで動かすと、作り直されたログのフォルダとファイルの所有者が変わり、ウェブ側から書けなくなることがあります）。
`db:import` は `EVO_CLI_IMPORT=1` を付けたときだけ実行します。このサイトの接頭辞で始まらない表を含むダンプと、`DELIMITER`（トリガー・ストアドプロシージャー）を含むダンプは、何も変えずに終了コード 1 で断ります（`DELIMITER` を含むダンプは `mysql` コマンドで戻します）。
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
ホスト側で `php evo.php` が `mysqli` 不在になる環境では、`docker compose exec <app-service> php evo.php ...` を使ってください。

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
