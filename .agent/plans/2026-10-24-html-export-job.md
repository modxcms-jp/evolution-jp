# ExecPlan: HTML エクスポートをジョブにして、止まらずに最後まで進め、進み具合を表示する（Issue #907）

## Purpose / Big Picture

管理画面の「HTML エクスポート」（`index.php?a=83`）で「エクスポートの開始」を 1 回押すだけで、ページ数が数千あっても PHP の制限時間に掛からずに最後まで書き出せるようにし、プログレスバーで進み具合を見られるようにする。今は時間切れで止まるたびに開始を押し直しており、その手間と、押し直しに頼った「続きから」の不確かさをなくす。

## Progress

- [x] (2026-10-24) M1: `EvoSiteExporter` に分けた（出力先を `temp/` の中に限る確認もこのクラスへ）。非キャッシュを含むの件数と書き出しの食い違い・再試行・権限を直し、結果の画面の出力のエスケープをそろえた。4 条件で main と書き出したファイルを比べ、違いは期待した点だけ。時間切れからの再開も main と同じく最後まで進む
- [ ] (2026-10-24) M2: ジョブ化と進捗表示（`a=308` の JSON の窓口、プログレスバー・中止・再開、「最大エクスポート時間」の廃止）
- [ ] (2026-10-24) M3: CLI の `php evo export:site`
- [ ] (2026-10-24) M4: 並列の取得で速くする

## Surprises & Discoveries

- 2026-10-24: 「非キャッシュリソースを含む」は、件数を数える `getTotal()` ではセッションの値を使い、書き出す `run()` ではどこでも設定されない `config('export_includenoncache')` を見ている。そのため「はい」にしても非キャッシュのページは書き出されない。根拠: `grep -rn export_includenoncache manager` で、`config` 側に値を入れる箇所が無い
- 2026-10-24: 「最大エクスポート時間」は `set_time_limit()` を呼んでおらず、PHP の制限時間は変わらない（説明文は「PHP の設定を上書きします」）。`makeFile()` が 1 件ごとに経過時間を見て、超えたら `exit()` する
- 2026-10-24: ヘルプは「同じファイル名は上書き」と書くが、`processRow()` は `!is_file($filename)` のときだけ書き出す（`index.html` を除く）
- 2026-10-24: サイトが非公開のときに使うベアラートークンは、`DocumentParser::verifyBearerToken()` がリクエストのたびに phpass で照合する。ページを 1 件取るたびに照合が 1 回走る。取り消す関数は無い（`saveBearerToken()` だけ）
- 2026-10-24: `verifyBearerToken()` は `getallheaders()` の結果から小文字の `authorization` を探している。Apache の `getallheaders()` は送られたとおりの大文字小文字（curl なら `Authorization`）で返し、小文字にする処理はリポジトリ内に無い。そのため、非公開サイトのエクスポートは照合に失敗し、ページを取得できていない可能性が高い（要再現。M2 で確かめる）。PHP-FPM などでは `Authorization` ヘッダーが PHP に渡らず、`$_SERVER['HTTP_AUTHORIZATION']` か `REDIRECT_HTTP_AUTHORIZATION` にだけ入ることもある
- 2026-10-24: 管理画面のヘッダー（`manager/actions/header.inc.php`）は、`manager/index.php` の一覧に載ったアクション番号のときだけ出力される。一覧に載せない番号にすれば、JSON だけを返せる（保存の処理と同じ扱い）
- 2026-10-24: CLI（`manager/includes/cli/bootstrap.php`）では、固定のサイトの URL が無いと `MODX_SITE_URL` が `http://localhost/` になる。Docker の検証環境では、コンテナの中の 80 番で Apache が待っていないため、そのままではページを取得できない

- 2026-10-24（M1）: 時間切れの扱いが変わった。main は `makeFile()` の中から `exit()` で止めていたため、画面がタブの組み立ての途中で切れ、ヘルプのタブもフッターも出なかった。M1 では processor が文言を返すので、画面が最後まで描かれる（改善）。経過時間を見る場所も「取得する直前」から「各行の先頭」に移り、飛ばす行やフォルダの行でも止まる。止まったときに出す件数が main より小さくなることがあるが、押し直しで最後まで進むことは検証で確かめた（311 件で main と同じ 6 回）
- 2026-10-24（M1）: 検証環境（`compose.yml` + `.claude/skills/adaptive-validation-runner/scripts/compose.validation.yml`）の Apache は 8080 番だけで待ち受けるため、エクスポートが自サイトのページ（`http://<アドレス>/`）を取得できず、全件「ページを開けません」になる。検証ではコンテナの `/etc/apache2/ports.conf` に `Listen 80` を足して `apache2ctl graceful` した。開発用の環境（ホストの 80 番を 8080 番へ）でも同じことが起きるはずで、エクスポートとは別の課題として #908 で扱う
- 2026-10-24（M1 の自己レビュー）: M2・M4 で気を付けること。basic 認証を検出した印（`basicAuthDetected`）は一度立つと下がらない。curl の `CURLOPT_FAILONERROR` により、404・500・401 のように送り直しても同じ結果になる応答でも 0.3 秒おいて 2 回送り直す。curl が無いときの経路は `Authorization` ヘッダーを送らないので、非公開サイトでは取得できない
- 2026-10-24（M1 の自己レビュー）: `new_file_permissions` / `new_folder_permissions` の読み方が、`document.parser.class.inc.php`・`files.dynamic.php`・`manager/media/browser/evo/api.php` と今回の `EvoSiteExporter` でそれぞれ少しずつ違う。1 つの関数にまとめるのはエクスポートと関係しない箇所に及ぶので、別の Issue（#909）にする
- 2026-10-24（M1 の自己レビュー）: 出力先の設定（`export_dir`）が空のときの動きが変わった。main は `realpath('')` が今いるディレクトリ（`manager/`）を返すため「temp/ の中にしてください」で止まっていた。M1 では `EvoSiteExporter` が空を `temp/export` として扱い、そこへ書き出す。出力先は `temp/` の中なので、安全な側の変化としてこのまま残す

## Decision Log

- 2026-10-24 / メイン（AI）: **出力先を `temp/` の中に限る確認は、削除を行う `EvoSiteExporter` の側に置く**（M1 の自己レビューを受けて、当初の「M2 で Job の `start()` に移す」から変更）。消す処理のそばで守れば、CLI（M3）も同じ確認を通る。processor はその結果を使って文言を出すだけにし、確認を 2 か所に書かない
- 2026-10-24 / メイン（AI）: **M2 で、`exportOne()` の結果ごとに basic 認証の検出を判断し、印を持ち越さない。HTTP のステータスで答えが返った取得（401・404・500 など）は送り直さない。curl が無いときの経路にも `stream_context_create()` で `Authorization` ヘッダーを付ける**（M1 の自己レビューの注意を受けて）

- 2026-10-24 / メイン（AI）: **画面から短いリクエストを繰り返す「ジョブ」方式にする**。開始で対象の ID 一覧と設定を状態ファイルに保存し、画面の JS が「続きを進める」を、1 回あたり決めた秒数ぶんだけ繰り返し呼ぶ。理由: 1 回のリクエストが短いので、PHP の制限時間にも、PHP-FPM の `request_terminate_timeout` やプロキシのタイムアウトにも掛からない。進み具合を JSON で返せる。代替案: 制限時間を外して 1 回で流し切る（PHP-FPM やプロキシで切られ得るので採らない）。裏で動くワーカーや cron（共用サーバーで使えないことが多いので採らない）
- 2026-10-24 / メイン（AI）: **JSON の窓口は新しいアクション `a=308` にし、ヘッダーを出す一覧には載せない**。`a=83` は画面のまま残す。`a=308` は POST だけを受け、`csrfProtectedActions()`（`manager/includes/csrf_token.php`）に載せる。JS は `Evo.post()`（`X-CSRF-Token` を自動で付ける）で呼ぶ。代替案: `a=83` に `ajax=1` のような目印を付けて、`a=114` のように index.php で特別扱いする（特別扱いが増えるので採らない）。`manager/ajax.php`（`a=1&ajaxa=`）に足す（中身が 1 つの分岐だけの古い入口で、権限やメソッドの確認を持たないので採らない）
- 2026-10-24 / メイン（AI）: **状態ファイルは `temp/export_job.json` に 1 つだけ置く**。同時に動くジョブは 1 つ。`flock()` で排他し、別のタブや別の管理者が同時に「続きを進める」を呼んだら、待たずに `busy` を返す。`temp/cache/` はキャッシュの削除で消える恐れがあり、出力先（`temp/export/`）は公開用のファイルが並ぶので使わない。`temp/` は `.htaccess` で外から読めない
- 2026-10-24 / メイン（AI）: **途中で中止できるようにし、押してから長くても 1 ページ分で止まるようにする**（利用者の要望）。中止を押すと、画面の JS は次の「続きを進める」を送らない。あわせて `op=cancel` が中止の印のファイル `temp/export_job.cancel` を書く。印はジョブ本体のロック（`flock()`）を取らずに書けるので、処理中の「続きを進める」を待たない。`step()` は 1 ページごとに印を見て、あれば今の 1 件を終えたところで状態を `cancelled` にし、トークンを取り消して返す。処理中のリクエストが無ければ、`cancel()` がその場で同じ後始末をする。中止したジョブは再開できず、画面には「最初からやり直す」だけを出す（書き出し済みのファイルは消さずに残す）
- 2026-10-24 / メイン（AI）: **1 回の「続きを進める」の持ち時間は、10 秒と PHP の制限時間の 3 分の 1 の小さいほう**（制限時間が 0 なら 10 秒）。持ち時間を過ぎたら、処理中の 1 件を終えてから返す。1 件の取得は今と同じ 10 秒で打ち切る
- 2026-10-24 / メイン（AI）: **非公開サイトのベアラートークンは、「続きを進める」のたびに発行し直し、期限を 10 分にする。終了・中止で取り消す**。状態ファイルに平文のトークンを書かないため。`DocumentParser` に `revokeBearerToken()` を足す（期限を過去にし、ハッシュを空にする）
- 2026-10-24 / メイン（AI）: **Issue #907 の「今の機能の問題点」と、計画づくりや実装の途中で見つけた問題は、範囲外として残さずすべて直す**（利用者の指示）。エクスポートと無関係な箇所で見つけたものは、別の Issue にして記録する
- 2026-10-24 / メイン（AI）: **`verifyBearerToken()` のヘッダーの探し方を直す**（M2）。`getallheaders()` の結果を大文字小文字を区別せずに探し、無ければ `serverv('HTTP_AUTHORIZATION')`、`serverv('REDIRECT_HTTP_AUTHORIZATION')` を見る。直す前に、検証環境で非公開サイトのエクスポートが失敗することを確かめる
- 2026-10-24 / メイン（AI）: **フレンドリーURLが無効なら、開始せずに案内を出す**。今はエクステンダーを読み込むだけで設定をメモリ上で書き換え、キャッシュを消している（`setUrlMode()`）。書き出すファイルの置き場所はエイリアスのパスなのに、取得したページの中のリンクは実際の設定（無効）で作られるので、食い違う。ヘルプも「フレンドリーURLを有効に」と案内している
- 2026-10-24 / メイン（AI）: **開始時に出力先を必ず空にし、同じファイル名は上書きする**。ロックファイル（`temp/cache/export.lock`）と「既にあるファイルを飛ばす」による再開はやめる。再開はジョブの進み具合で行う。ヘルプの記述とも揃う
- 2026-10-24 / メイン（AI）: **開始時のキャッシュの削除はやめる**。今は設定（範囲・置換など）が前回と違うとキャッシュを消すが、置換は取得したあとの文字列に掛けるので、ページのキャッシュとは関係しない。ページのキャッシュは、リソースの保存のたびに CMS が整える
- 2026-10-24 / メイン（AI）: **プラグインのイベントは、ジョブの開始時に `OnExportPreExec`、終了時に `OnExportExec` を 1 回ずつ呼ぶ**。渡す値の名前は今と同じ（`allow_ids`・`ignore_ids`・`repl_before`・`repl_after`・`export_dir`、終了時は `output`）。終了時の所要時間は、綴りの誤りの `totatlime` を `totaltime` に直して渡す。古い名前は互換のために残さない（利用者の方針「気づいたことは全て直す」と、古い作法の互換を重ねない方針による）。読んでいるプラグインがあれば直す必要があるので、M2 の PR とリリース記事に書く。イベントの出力は、今と同じく HTML として画面に出す（プラグインの作者が書く HTML）
- 2026-10-24 / メイン（AI）: **結果の画面は、件数のまとめと失敗したページの一覧だけにする**。今は全ページを 1 行ずつ並べており、数千件では画面が重い。失敗の一覧は 200 件まで出し、残りは件数だけ出す
- 2026-10-24 / メイン（AI）: **中身の部品は 2 つのクラスにする**。`EvoSiteExporter`（対象の一覧・出力先の準備・1 件の書き出し）と `EvoSiteExportJob`（状態ファイル・開始・続きを進める・中止）。名前と置き場所は `EvoBackupManager`（`manager/includes/backup_manager.class.inc.php`）に揃える。エクステンダー `ex_export_site.php` と `loadExtension('EXPORT_SITE')` の分岐は M2 で消す（`$modx->export` を使う箇所はリポジトリ内に processor しか無い）
- 2026-10-24 / メイン（AI）: **M1 は見た目と動きを変えない整理にし、次の 3 つの不具合だけを直す**。件数と書き出しの「非キャッシュリソースを含む」の食い違い（対象を 1 つの問い合わせで決めるので自然に直る）、取得の再試行が成功しても止まらないこと、ファイルとディレクトリの権限を設定（`new_file_permissions` / `new_folder_permissions`）で作ること
- 2026-10-24 / メイン（AI）: **CLI は画面と同じジョブを使い、持ち時間なしで流す**。画面のジョブが途中なら、`--restart` を付けない限り始めない。取得先は `--site-url=` で変えられる（CLI では `MODX_SITE_URL` が `http://localhost/` になるため）
- 2026-10-24 / メイン（AI）: **並列の取得（M4）は最後に分ける**。`curl_multi` で同時に 4 件まで取る。サーバーへの負荷が上がるので、同時数はジョブの設定ではなく定数にし、1 にすれば今の順番どおりの取得になるようにする
- 2026-10-24 / メイン（AI）: **マイルストーンごとに 1 つの PR にする**。中間の PR は `Refs #907`、最後の PR だけ `Closes #907`。M2 は「最大エクスポート時間」の廃止を含むので `仕様変更` のラベルを付ける

## Outcomes & Retrospective

実装後に記載

## Context and Orientation

### 用語

- **HTML エクスポート**: サイトの公開ページを 1 件ずつ取得し、静的な HTML ファイルとして出力先のディレクトリ（既定は `temp/export/`、システム設定 `export_dir`）に書き出す機能。権限 `export_static` が要る。
- **ジョブ**: 1 回のエクスポートの全体。対象の ID 一覧・設定・何件目まで進んだか・失敗の一覧を状態ファイルに持つ。
- **続きを進める（step）**: ジョブを、決めた秒数ぶんだけ進めるリクエスト。画面の JS が、終わるまで繰り返し呼ぶ。
- **シェル**: 管理画面の外枠（`manager/media/script/shell.js`）。画面の切り替えでは HTML の断片を `#mainPane` に差し込む。
- **ベアラートークン**: サイトが非公開（`site_status` が 0）のときに、エクスポートがページを取得できるようにする合言葉。`Authorization: Bearer <トークン>` で送り、`DocumentParser::verifyBearerToken()` が照合する。

### 今の作り

- 画面: `manager/actions/tool/export_site.static.php`（`a=83`）。フォームを POST すると、同じ画面の中で processor を `include` し、結果の HTML をそのまま出す
- 処理の入口: `manager/processors/export_site.processor.php`。出力先を `temp/` の中に限る確認、セッションへの設定の保存、イベント `OnExportPreExec` / `OnExportExec` の呼び出しを行う
- 本体: `manager/includes/extenders/ex_export_site.php`（クラス `EXPORT_SITE`）。`run()` が対象を 1 回の問い合わせで取り、`processRow()` → `makeFile()` → `get_contents()`（curl を使い回す）で 1 件ずつ取得して書き出す。結果は HTML の文字列で積み上げる。読み込みは `DocumentParser::loadExtension()` の `case 'export_site'`（`manager/includes/document.parser.class.inc.php`）
- 対象の条件: `deleted=0 AND ((published=1 AND type='document') OR (isfolder=1))`、非キャッシュを含まないときは `AND cacheable=1`、範囲の指定で `AND id IN (…)` または `AND NOT id IN (…)`
- フォルダの扱い: フォルダのリソースはディレクトリを作り、システム設定 `make_folders` が 1 なら書き出したファイルを `<フォルダ>/index.html` に移す。`suffix_mode` が 1 でエイリアスに `.` を含むときはディレクトリを作らない
- 文言: `manager/includes/lang/english.inc.php` と `japanese-utf8.inc.php` の `export_site_*`。画面の一部（「出力するリソース」など）は直書き
- 管理画面のアクションの振り分け: `manager/index.php` の `switch (manager()->action)`。ヘッダーを出すアクションの一覧も同じファイルにある
- CSRF: POST は一覧によらずトークンを確かめる。`csrfProtectedActions()` に載せたアクションは POST でしか受け付けない（GET は 405）
- CLI のコマンド: `manager/includes/cli/commands/<名前>.php`（`db-backup.php` が `php evo db:backup`）。`$args` で引数を受け、`cli_out()` / `cli_usage()` で出力する（`manager/includes/cli/cli-helpers.php`）

## Plan of Work

M1 では、`ex_export_site.php` の中身を `manager/includes/site_exporter.class.inc.php` の `EvoSiteExporter` に移し、画面に出す HTML・`global $_lang`・セッション・`exit()` を持たない部品にする。設定は配列でコンストラクタに渡す（`target`: `all` / `allow` / `ignore`、`ids`: 整数の配列、`include_noncache`、`repl_before`、`repl_after`、`export_dir`）。公開する操作は、対象の ID 一覧を返す `targetIds()`、出力先を空にする `clearOutputDir()`、1 件を書き出して結果の配列（`id`・`pagetitle`・`status`: `success` / `skipped` / `failed`・`reason`）を返す `exportOne($id)` の 3 つ。M1 では今の動きを保つため、「既にあるファイルを飛ばす」をオプション（`skip_existing`）で残し、時間切れの確認とロックファイル、結果の HTML 作りは processor に移す。エクステンダーは `EvoSiteExporter` を呼ぶだけの薄い形にして残す（M2 で消す）。

M2 では、`manager/includes/site_export_job.class.inc.php` に `EvoSiteExportJob` を作る。`start($options)` は、フレンドリーURLの確認、出力先の確認（M1 で `EvoSiteExporter` に置いた確認の結果を使う）、`clearOutputDir()`、対象の ID 一覧の保存、`OnExportPreExec` を行う。`step($budgetSeconds)` は、トークンの発行、持ち時間ぶんの `exportOne()`、進み具合の保存を行い、最後の 1 件で `OnExportExec` とトークンの取り消しを行う。`status()` は保存した状態を返し、`cancel()` は中止の印を書く（処理中の `step()` が無ければ、その場で状態を `cancelled` にしてトークンを取り消す）。`step()` は 1 ページごとに中止の印を見る。processor は `manager/processors/export_site.processor.php` を JSON の窓口に書き換え、`a=308` で `op`（`start` / `step` / `status` / `cancel`）を受ける。画面は、フォームの下にプログレスバー（`<progress>`）・件数・中止のボタン・結果の欄を置き、`Evo.post('index.php?a=308', …)` で開始と「続きを進める」を繰り返す。画面を開いたときに途中のジョブがあれば、「続きから再開」と「最初からやり直す」を出す。「最大エクスポート時間」の欄と文言、ロックファイル、`setUrlMode()`、エクステンダーを消す。直書きの文言を言語ファイルへ移し、入力欄の値は `hsc()` で出す。

M3 では、`manager/includes/cli/commands/export-site.php` を作る。引数は `--target=all|allow|ignore`、`--ids=1,2,3`、`--include-noncache`、`--replace-before=`、`--replace-after=`、`--site-url=`、`--restart`。`EvoSiteExportJob` を持ち時間なしで動かし、100 件ごとと最後に進み具合を出す。

M4 では、`EvoSiteExporter` に複数の ID をまとめて取得する `exportMany(array $ids)` を足し、`curl_multi` で同時に `EvoSiteExporter::CONCURRENCY`（4）件まで取る。`step()` は `exportMany()` を使う。

## Concrete Steps

M1（整理）

1. 編集: `manager/includes/site_exporter.class.inc.php` を作り、上の `EvoSiteExporter` を実装する。対象は `targetIds()` の 1 つの問い合わせで決める。ID の条件は `(int)` にしてから並べる（今の `db()->escape()` と引用符の組み立てをやめる）。取得の再試行は、成功したらすぐ抜ける。権限は `octdec(config('new_file_permissions'))` と `octdec(config('new_folder_permissions'))` を使う
2. 編集: `manager/includes/extenders/ex_export_site.php` を `EvoSiteExporter` を呼ぶ形にし、`manager/processors/export_site.processor.php` に時間切れの確認・ロックファイル・結果の HTML 作りを移す
3. 実行: `docker exec <app> php -l` で 3 ファイルを確かめる。期待: `No syntax errors detected`
4. 実行: 検証環境で、全リソース・一部を出力・一部を除外・非キャッシュを含むのそれぞれでエクスポートし、`temp/export/` のファイル一覧（`find temp/export -type f | sort`）と各ファイルの `md5sum` を main と比べる。期待: 非キャッシュを含むを「はい」にしたときだけ、main より非キャッシュのページのファイルが増え、ほかは一致する

M2（ジョブ化と進捗表示）

1. 編集: `manager/includes/site_export_job.class.inc.php` を作り、`EvoSiteExportJob` を実装する
2. 実行と編集: 検証環境を立てたら、まずコンテナの `/etc/apache2/ports.conf` に `Listen 80` を足して `apache2ctl graceful` する（#908。これが無いと、ポートのせいで全件の取得に失敗し、トークンのせいかを見分けられない。手順 7・8 の環境でも同じ）。先に main の検証環境でサイトを非公開（`site_status` を 0）にしてエクスポートし、ページを取得できないこと（失敗の表示、または出力されたファイルがメンテナンスの画面）を確かめる。`manager/includes/document.parser.class.inc.php` の `verifyBearerToken()` を Decision Log のとおり直し、`revokeBearerToken()` を足し、`loadExtension()` の `case 'export_site'` を消す。`manager/includes/extenders/ex_export_site.php` を消す
3. 編集: `manager/processors/export_site.processor.php` を JSON の窓口に書き換える。権限 `export_static` が無ければ 403 の JSON。POST 以外は 405。応答は `{"state":"running|done|cancelled|busy|error","total":…,"done":…,"failed":…,"failures":[…],"messages":[…]}`
4. 編集: `manager/index.php` に `case 308` を足す（ヘッダーの一覧には載せない）。`manager/includes/actionlist.inc.php` に `'308' => 'HTML export job'`、`manager/includes/csrf_token.php` の `csrfProtectedActions()` に `308 => true` を足す
5. 編集: `manager/actions/tool/export_site.static.php` を書き換える（フォーム・プログレスバー・中止・再開・結果の欄。JS は素の JS で、jQuery を使わない）
6. 編集: 言語ファイル 2 つ。`export_site_maxtime`・`export_site_maxtime_message`・`export_site_exit_maxtime` を消し、進み具合・中止・再開・フレンドリーURLの案内・直書きだった文言のキーを足す。`export_site_message` の上書きの記述は残す（実際の動きと一致するようになる）
7. 実行: 検証環境で 3,000 件のリソースを作る（`a=4` を繰り返すと遅いので、`docker exec <db> mariadb … -e "INSERT INTO modx_site_content (pagetitle, alias, published, template, content, parent, cacheable) SELECT CONCAT('p', seq), CONCAT('p', seq), 1, 1, 'x', 0, 1 FROM seq_1_to_3000"`。MariaDB の Sequence エンジン）。続けて `php evo cache:clear`
8. 実行: PHP の制限時間を 5 秒にした状態（`manager/docker/php.ini` の `max_execution_time = 5` を一時的に足してコンテナを再起動）で、Playwright により画面から開始を 1 回だけ押す。期待: プログレスバーが進み、3,0xx 件すべてのファイルが書き出され、失敗が 0 件で終わる。終わったら足した行を消す

M3（CLI）

1. 編集: `manager/includes/cli/commands/export-site.php` を作る。`php evo help` の一覧に出るよう、既存のコマンドの書き方に揃える
2. 実行: `docker compose exec <app-service> php evo export:site --site-url=http://localhost:8080/`（検証環境のコンテナを名前で指すときは `docker exec -w /var/www/html <名前>-app php evo …`）。期待: 進み具合が出て、画面からのエクスポートと同じファイルの一覧になる
3. 実行: 画面でジョブを途中で止めた状態で、`--restart` なしで実行。期待: 途中のジョブがある旨を出して始めない（終了コード 1）

M4（並列の取得）

1. 編集: `EvoSiteExporter::exportMany()` を足し、`EvoSiteExportJob::step()` から使う
2. 実行: M2 の 3,000 件で、同時数 1 と 4 の所要時間を比べる。期待: 4 のほうが短く、書き出したファイルの一覧と中身（`md5sum`）は同じ

## Validation and Acceptance

- 3,000 件のサイトで、PHP の制限時間を 5 秒にしても、「エクスポートの開始」を 1 回押すだけで全ページが書き出される
- 処理中はプログレスバーと「n / 全体 件」が進み、終わると所要時間・成功・失敗の件数と、失敗したページの一覧が出る
- 途中でタブを閉じて `a=83` を開き直すと、「続きから再開」で残りだけが書き出される。「最初からやり直す」では出力先を空にして始める。中止を押すと、処理中の 1 件を終えたところで止まり（押してから止まるまでに書かれるファイルは長くても 1 件）、ベアラートークンが取り消される（`modx_system_settings` の `bearer_token_expire` が過去になる）。中止したジョブは「最初からやり直す」だけを選べる
- 範囲の指定（全リソース・一部を出力・一部を除外）、非キャッシュリソースを含む、文字列の置換が、指定どおりに効く
- サイトを非公開にしても書き出せる（main では書き出せないことを先に確かめる）。終わったあとは、同じトークンでページを取得できない
- `OnExportExec` で、所要時間が `totaltime` の名前で渡る
- フレンドリーURLが無効なら、開始せずに案内が出る
- 別のタブで同じジョブを同時に進めても、ファイルが二重に書かれず、片方は `busy` を受けて待つ
- `php evo export:site` で、画面と同じファイルの一覧が書き出される
- 権限 `export_static` の無いユーザーが `a=308` に POST すると 403、GET すると 405 になる

## Idempotence and Recovery

- 状態ファイル `temp/export_job.json` と中止の印 `temp/export_job.cancel` を消せば、ジョブの無い状態に戻る。出力先は次の開始で空にされる
- 検証で作った 3,000 件のリソースは、`DELETE FROM modx_site_content WHERE pagetitle REGEXP '^p[0-9]+$'` と `php evo cache:clear` で消す。検証環境ごと `down -v` で捨ててもよい
- php.ini の `max_execution_time` を一時的に変えたら、確認の後に必ず戻す（`git diff manager/docker/php.ini` が空になること）
- マイルストーンの途中で止まったら、Progress と Decision Log を更新し、次のマイルストーンの PR を前の PR のマージ後に main から作る

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/907
- ブランチ: `feat/907-html-export`（`gh issue develop 907` で作成）。PR: #910（下書き。M1 から M4 までをこの PR に積む）
- 関連: #902（フォームの二度押しの防止。エクスポートの開始の二度押しも、シェルを通る限り防がれる）
- 想定するコミットと PR（マイルストーンごとに 1 PR）
  - M1: `refactor(export): エクスポートの中身を部品に分け、件数と書き出しの食い違いを直す`
  - M2: `feat(export): エクスポートをジョブにして、進み具合を表示する`（`仕様変更`）
  - M3: `feat(cli): php evo export:site を追加する`
  - M4: `perf(export): ページの取得を並列にする`
- 検証の手順と環境の立て方は `.claude/skills/adaptive-validation-runner/SKILL.md` に従う

## Interfaces and Dependencies

- PHP の curl 拡張（無ければ今と同じく `file_get_contents()` で 1 件ずつ取る。M4 の並列の取得も curl が要る）
- プラグインのイベント `OnExportPreExec` / `OnExportExec`（渡す値は Decision Log のとおり。呼ぶ回数はジョブの開始と終了の 1 回ずつで、今と同じ）
- `DocumentParser::saveBearerToken()` / `verifyBearerToken()` と、追加する `revokeBearerToken()`
- `Evo.post()`（`manager/media/script/evo.js`。CSRF トークンを付け、ログインが切れていればログイン画面へ移す）
- システム設定: `export_dir`、`friendly_urls`、`make_folders`、`suffix_mode`、`friendly_url_prefix` / `friendly_url_suffix`、`new_file_permissions` / `new_folder_permissions`、`site_status`
