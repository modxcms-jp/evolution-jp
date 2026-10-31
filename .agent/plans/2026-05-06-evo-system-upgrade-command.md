# ExecPlan: evo system:upgrade コマンド実装

## Purpose / Big Picture

サイトの管理者が `php evo system:upgrade` を 1 回実行すると、同じ系列の新しいリリースを GitHub から取得して検証する。続けて DB とファイルのバックアップを取り、`manager/`・`install/`・ルートのファイル・`assets/` を差し替える。そのあとはブラウザでアップデーター（`install/`）を実行するだけで済む。差し替えの途中で失敗したときは、ファイルを実行前の状態に自動で戻す。

`assets/docs/migrations.md` の「利用者がアップデートするとき」に照らすと、この計画が自動化するのは、手順 1（ファイルを差し替える）と手順 2 の前半（`install/` を置く）である。アップデーターを動かして DB を上げる作業は、今までどおり人がブラウザで行う。

## Progress

- [x] (2026-10-31) 前提の誤り（install/ の差し替え漏れ、Latest によるダウングレード、配布 zip の構造、PHP 7.4 非対応の関数、#933 による更新の流れの変化）を受けて計画を作り直し、自己レビューの指摘（サイト固有のファイルの引き継ぎ、ジャーナルの記録の時点、入れ替えの順序、終了の経路、実行する利用者、検証手順）を反映した
- [ ] M1: 取得と確認（引数・事前確認・版の判定・リリースの選択・ダウンロード・検証・展開・確認の問い合わせ。サイトのファイルは変えない）
- [ ] M2: バックアップと差し替え・自動ロールバック
- [ ] M3: ドキュメントの更新と、アップデーターまでの通しの検証

## Surprises & Discoveries

- 2026-10-31: GitHub の Latest は保守系列の `release-1.2.4J` を指す。main の版は `1.3.0rc2` で、1.3 系のリリースはまだ無い（`gh release list`）。旧計画のように Latest を取ると、1.3 のサイトが 1.2 に下がる。
- 2026-10-31: 公式の配布物は、リリースに添付された `evo-<tag>.zip` である。`.github/workflows/release.yml` が `git archive --format=zip "refs/tags/${GITHUB_REF_NAME}"` で作るので、zip の中にトップのディレクトリは無く、`manager/` などが直下にある。旧計画は、トップのディレクトリがある zipball を前提にしていた。リリースの API は、添付ファイルの `digest`（`sha256:<16 進>`）を返す。
- 2026-10-31: 配布 zip の直下にあるのは `assets/ content/ install/ manager/ temp/ index.php define-path.php evo sample.htaccess` である（`git archive origin/main | tar -t` で確認）。旧計画は `install/` を差し替えず、`content/` と `temp/` の扱いも決めていなかった。
- 2026-10-31: #933 のマージにより、未適用のマイグレーションがある間は、公開側が `manager/includes/update_guard.php` で HTTP 503 のメンテナンス表示を返す。管理画面も、settings 権限のある人をアップデーターへ案内する（`assets/docs/migrations.md`「アップデート待ちの判定」）。旧計画の「`.htaccess` を書き換えてメンテナンス化する」は、この仕組みと役割が重なる。
- 2026-10-31: `$modx_version` の書き方は系列で違う。1.2.4J では `1.2.4J`、main では `1.3.0rc2` である。PHP 7.4 の `version_compare('1.3.0J', '1.3.0rc2')` は `-1` を返すので、末尾の `J` を外さないと正しく比べられない。`version_compare('1.3.0', '1.3.0rc2')` と `version_compare('1.3.0rc2', '1.3.0rc1')` は、どちらも `1` を返す（`php:7.4-cli` で確認）。
- 2026-10-31: 旧計画のコード例にある `str_starts_with()` は PHP 8 の関数である。`manager/includes/helpers.php` に代わりの実装はあるが、既存のコマンド（`manager/includes/cli/commands/db-backup.php` など）にそろえて `strpos(...) === 0` を使う。
- 2026-10-31: CLI のコマンド名は、ファイル名の `-` を `:` に読み替えて一覧に出る（`manager/includes/cli/commands/help.php`）。`manager/includes/cli/commands/system-upgrade.php` は `system:upgrade` になる。既存のコマンドは、表示をすべて英語で出す。
- 2026-10-31: このコマンドを動かすのは、サイトに今入っている版のコードである。そのため、使えるのはこのコマンドを含む版（1.3.0 以降）からの更新に限られる。1.2 系のサイトは、今までどおり手で 1.3 へ上げる。
- 2026-10-31: 配布 zip の `manager/` にあるのは `ht.access` で、利用者はこれを `.htaccess` に名前を変えて、管理画面を保護する（Basic 認証や IP の制限）。テーマには `manager/media/style/common/config.php.sample` があり、テーマのフォルダに `config.php` を置いて使う。`manager/` を丸ごと入れ替えると、これらが知らせなく消える。
- 2026-10-31: CLI では `DocumentParser::phpError()` が `true` を返す（`manager/includes/document.parser.class.inc.php` の `phpError()`）。このため、警告のあとで `error_get_last()` を呼んでも `NULL` が返り、`rename()` や `copy()` が失敗した理由を取れない。
- 2026-10-31: `cli_export_database()` は、失敗すると `cli_usage()` を呼んで `exit` する（`manager/includes/cli/cli-helpers.php`）。呼んだ側の後片付けは飛ばされる。
- 2026-10-31: インストーラは、`manager/includes/config.inc.php` が無ければ新規インストールと判定し、アップデートのときの認証（管理者のログイン）を求めない（`install/index.php`、`install/functions.php` の `managerConfigExists()`）。`config.inc.php` が無い新しい `manager/` を先に置くと、`install/` が既にあるサイトでは、その間だけ認証なしで新規インストールに入れてしまう。
- 2026-10-31: アップデーターは `config.inc.php` を書き直し、権限を 0404 にする（`install/instprocessor.php`）。CLI を Web サーバーと別の利用者で動かすと、新しいファイルの持ち主が違ってしまい、アップデーターの書き込みや `install/` の削除に失敗する。開発用の Docker は UID を合わせているので、この問題は出ない。
- 2026-10-31: 開発用の検証環境は、作業ツリーを `/var/www/html` にマウントする。コマンドを動かすと、マウント元の作業ツリーの `manager/` が実際に移される。`manager/docker/` は配布 zip に入らない（`export-ignore`）ので、差し替えたあとの作業ツリーでは `compose.yml` が参照する `manager/docker/Dockerfile`・`php.ini` が無くなり、環境を作り直せない。
- 2026-10-31: PHP の `ZipArchive::extractTo()` は、項目の名前の `../` を自分で外して展開する。そのため、「`../` の項目で外にファイルができない」ことを確かめても、このコマンドの検査が働いたかどうかは分からない。また、`php:7.4-cli` の公式イメージには `zip` 拡張が無い。

## Decision Log

- 2026-10-31 / yamamoto: 引数なしで選ぶリリースは、今の版と同じ系列（`X.Y`）にある、今より新しい正式版（プレリリースを除く）のうち最も新しいものとする。系列をまたぐ更新は、利用者が版を明示したとき（`--tag`、`--zip`）だけ行う。どの経路でも、今の版以下への差し替えは中止する。代替案の「`--tag` を必須にする」は、日常の小さな更新でタグを調べる手間が増えるので採らない。「系列を問わず最新を取る」は、意図しない大きな更新が起きるので採らない。
- 2026-10-31 / yamamoto: メンテナンス表示は `manager/includes/update_guard.php` に任せ、`.htaccess` には触れない。Apache 以外のサーバーやサブディレクトリのサイトでも同じように動き、解除を忘れることもない。マイグレーションの無い版へ上げるときは公開側が止まらないが、その版のコードは今の DB でそのまま動くので、止めなくてよい。旧計画の `.htaccess` の書き換えは取り消す。
- 2026-10-31 / yamamoto: ExecPlan は同じファイルを書き直す。ロードマップと Issue #668 の参照はそのままにし、旧版は git の履歴に残す。
- 2026-10-31 / yamamoto（Claude が起案）: 取得するのは、リリースに添付された `evo-<tag>.zip`（利用者が手で入れるときと同じ配布物）とする。API が返す `digest` の sha256 と一致しなければ中止する。zipball は使わない。
- 2026-10-31 / yamamoto（Claude が起案）: `--zip=<パス>` で、手元の配布 zip を使えるようにする。外へ通信できないサーバーで使うためと、まだ存在しない版を作って検証するためである。このときは sha256 を表示するだけで、照合はしない（照合する相手が無い）。利用者が版を明示したものとして、`--tag` と同じく系列をまたいでよい。
- 2026-10-31 / yamamoto（Claude が起案）: 差し替えの対象は次の 4 つとする。`content/` と `temp/` は利用者のデータと作業用の領域なので、触れない。配布 zip の直下にこの 6 つ以外のディレクトリがあれば、想定外の構成として止める（配布物の作りが変わったら、このコマンドも合わせて直す）。
  - `manager/`: 丸ごと入れ替える。古い版にだけあるファイルが残ると、読み込みの順によっては古いコードが動くため。
  - `install/`: 丸ごと入れ替える。無ければ置く。
  - 配布 zip の直下にあるファイル: 上書きする。`.htaccess` という名前のものは扱わない。
  - `assets/`: 配布 zip にあるファイルだけを上書きし、利用者が足したファイルは残す。テンプレートやスニペットなど、利用者のファイルが同じ場所にあるため。インストーラは、同梱の要素を `assets/` の各ディレクトリから読む（`install/setup.info.php`）。
- 2026-10-31 / yamamoto（Claude が起案）: 新しい `manager/`・`install/` へ引き継ぐサイト固有のファイルは、決まった一覧で持つ。一覧は `manager/includes/config.inc.php`、`manager/includes/extra.config.php`、`manager/.htaccess`、`manager/.user.ini`、`manager/media/style/*/config.php`、`install/install-config.php` で、あるものだけをコピーする。一覧に無く、新しい版にも無い古い `manager/`・`install/` のファイルは引き継がない。かわりに、確認の画面と完了の表示で一覧にして、バックアップの場所を示す。代替案の「新しい版に無いファイルをすべて引き継ぐ」は、古い版のコアのファイルが残り、丸ごと入れ替える意味が無くなるので採らない。
- 2026-10-31 / yamamoto（Claude が起案）: 引き継ぐファイルは、サイトへ移す前に、展開先（`package/manager/`・`package/install/`）へコピーしておく。サイトに `config.inc.php` の無い `manager/` が置かれる時間を作らないためである。展開先での準備はサイトを変えないので、ジャーナルにも記録しない。入れ替えは `manager/` を先に、`install/` を後に行う。
- 2026-10-31 / yamamoto（Claude が起案）: ディレクトリの入れ替えは、コピーではなく同じファイルシステム内の `rename()` で行う。入れ替えが一瞬で済み、戻すときも `rename()` を逆にたどるだけで済む。作業用の領域（`temp/upgrade/`）がサイトと別のファイルシステムにあると `rename()` が失敗するので、事前の確認で `stat()` の `dev` を比べて止める。
- 2026-10-31 / yamamoto（Claude が起案）: DB のバックアップは、`EvoBackupManager` の保存先へ `pre-upgrade-<自動の名前>.sql` として書く。管理画面のバックアップ管理からそのまま戻せ、`prune()` の対象（自動の名前と `pre-restore-`）にも入らない。書き出しは `manager/includes/cli/commands/db-backup.php` と同じく `cli_export_database_file()` を使う。失敗すると `exit` するが、下の終了時の処理で後片付けする。旧計画の「`temp/backup/migrate/` に SQL も置く」は取り消す。
- 2026-10-31 / yamamoto（Claude が起案）: ファイルのバックアップ・ダウンロードした zip・展開先は、すべて `temp/upgrade/<YYYYmmdd-HHMMSS>-<ランダムな 8 文字>/` の下に置く。`temp/upgrade/.htaccess` には `EvoBackupManager::HTACCESS` と同じ内容を書く。`temp/.htaccess` とあわせて二重に守り、名前を推測しにくくする。`assets/` のバックアップは、上書きするファイルだけを取る。旧計画の「`assets/images/`・`assets/files/` を除いて丸ごと複写」は取り消す。
- 2026-10-31 / yamamoto（Claude が起案）: ロールバックはジャーナル方式とする。操作は `dir_moved`・`file_overwritten`・`file_created`・`dir_created` の 4 種で、戻し方は各操作の逆に限る。記録する時点は次のとおり。DB は自動では戻さない（ファイルを戻せば、古いコードは古い DB でそのまま動くため）。
  - `rename()` は一度で終わるので、成功したあとに記録する。
  - ファイルの上書きと作成は、途中まで書かれたファイルも戻せるように、操作の前に記録する。そのため戻し方は冪等にする。`file_overwritten` はバックアップから書き戻し、`file_created` はファイルがあれば消す。
- 2026-10-31 / yamamoto（Claude が起案）: 実行中のコマンドのファイルは `manager/` の中にある。入れ替えのあとに古いファイルを遅れて読み込みに行かないよう、入れ替えの処理は PHP の組み込み関数とこのファイルの関数だけで書く。`evo()->logEvent()` を呼ぶのは 2 か所だけにする。1 つは入れ替えの前の開始で、もう 1 つは、ロールバックで古い `manager/` がすべて戻ったときの失敗の記録である。戻しきれなかったときは記録しない（`send_errormail` が有効だと、メールの処理を遅れて読み込むため）。成功の記録は、このあとアップデーターが残す。
- 2026-10-31 / yamamoto（Claude が起案）: 終了の経路をすべて押さえる。入れ替えの最中は `cli_usage()` も `exit` も呼ばない。最初に `register_shutdown_function()` で終了時の処理を登録し、状態の変数で次のように分ける。
  - 入れ替えの前に終わった（`exit` を含む）: 作業用のディレクトリを消す。
  - 入れ替えの最中に終わった（Fatal を含む）: ジャーナルで戻す。
  - 完了した: 何もしない。
  - 例外は `Throwable` で捕まえる。
  - 取得と入れ替えの間だけ、警告を `ErrorException` に変えるエラーハンドラを `set_error_handler()` で置き、失敗の理由を表示する。終わったら `restore_error_handler()` で戻す。
- 2026-10-31 / yamamoto（Claude が起案）: POSIX 関数が使えるサーバーでは、実行する利用者（`posix_geteuid()`）が `manager/` の持ち主（`fileowner()`）と違えば止める。新しいファイルの持ち主がずれると、アップデーターが `config.inc.php` を書き直せず、Web から `install/` も消せなくなるためである。止めるときは、`sudo -u <持ち主> php evo system:upgrade` のように持ち主で実行するよう案内する。POSIX 関数が無い環境（Windows など）では、この確認を飛ばす。引き継ぐファイルは、コピーしたあと元の権限（`fileperms()`）にそろえる。
- 2026-10-31 / yamamoto（Claude が起案）: 前回のアップデーターが済んでいない（未適用のマイグレーションがある。`EvoMigrator::hasPending()`）ときは止める。前の更新を終えてから次へ進むようにするためである。
- 2026-10-31 / yamamoto（Claude が起案）: 表示は、既存の CLI にそろえて英語にする。次の手順の案内は、サイトの URL を決め打ちせず、「サイトの URL の後ろに `install/?action=mode`」と書く。CLI の `MODX_SITE_URL` は `http://localhost/` の仮の値であるため。
- 2026-05-06 / yamamoto: ブラウザでのアップデーター（`install/`）の操作は自動化しない（引き続き有効）。
- 2026-05-06 / yamamoto: 旧計画で決めた `.htaccess.maintenance` の生成、`temp/backup/migrate/` の構成、`assets/images/`・`assets/files/` の除外、ルートの除外リスト `$rootExclude` は、2026-10-31 の判断で取り消した。

## Outcomes & Retrospective

## Context and Orientation

### 用語

- **リリース**: GitHub の Releases に公開した版。タグは `release-<版>`（例 `release-1.2.4J`）。下書き（draft）は、認証なしの API には出てこない。プレリリースには `prerelease: true` が付く。
- **配布 zip**: リリースに添付された `evo-<tag>.zip`。`git archive` で作られ、`.gitattributes` の `export-ignore` に挙げたもの（`.github/`・`.agent/`・`compose.yml`・`manager/docker/`・`README*`・`docs/` など）は入っていない。
- **版**: `manager/includes/version.inc.php` の `$modx_version`（例 `1.3.0rc2`、`1.2.4J`）。
- **系列**: 版の先頭 2 つの数（`1.3.0rc2` なら `1.3`）。
- **アップデーター**: `install/` のインストーラを、既存のサイトに対して動かすもの。管理者でログインした状態で `/install/?action=mode` から進める。マイグレーション、表の作成、イベント名の同期、同梱の要素の入れ直しを行い、最後にキャッシュを消す。
- **マイグレーション**: DB を 1 段階新しくする処理（`manager/includes/migrations/`）。未適用のものがあると、公開側は `update_guard.php` で 503 を返す。仕組みは `assets/docs/migrations.md` に書いてある。
- **ジャーナル**: 差し替えで行った操作を、順に記録した配列。失敗したら逆順にたどって元に戻す。
- **使い捨てのワークツリー**: 検証のためだけに作る git のワークツリー。コマンドが中のファイルを差し替えるので、検証が終わったら捨てる。

### 関係するファイル（リポジトリのルートから）

- `evo`: CLI の入口。`php evo system:upgrade` は `manager/includes/cli/commands/system-upgrade.php` を読み込む（`:` を `-` に読み替える）。コマンドのファイルでは `$args`（コマンド名より後の引数の配列）が使える。
- `manager/includes/cli/bootstrap.php`: `define-path.php` で `MODX_BASE_PATH`（サイトのルート、末尾 `/` 付き）を定義し、`DocumentParser` を作って設定を読む。`MODX_CORE_PATH` は、その中で `manager/includes/initialize.inc.php` が定義する。
- `manager/includes/cli/cli-helpers.php`: `cli_out()`・`cli_err()`・`cli_usage($message, $code = 1)`（表示して `exit`）・`cli_export_database_file($driver, $path, $fullTables, $options)`。
- `manager/includes/cli/commands/db-backup.php`: DB のバックアップの既存の作り。
- `manager/includes/backup_manager.class.inc.php`: `EvoBackupManager`。`ensureDir()`、`dir()`、`autoFilename($prefix)`（`<prefix><日時>-<版>.sql`）、定数 `HTACCESS`。
- `manager/includes/migrator.class.inc.php`: `EvoMigrator`（`hasPending()`）。
- `manager/includes/version.inc.php`: 版。
- `manager/includes/update_guard.php`: 未適用のマイグレーションがあるときの 503。
- `manager/includes/traits/document.parser.subparser.trait.php`: `logEvent($evtid, $type, $msg, $title = 'Parser', array $context = [])`。
- `assets/docs/migrations.md`: 利用者がアップデートするときの手順の正本（「利用者がアップデートするとき」）。
- `manager/includes/cli/README.md`: CLI のコマンドの説明。
- `.github/workflows/release.yml`: 配布 zip の作り方。
- `.claude/skills/adaptive-validation-runner/`: 検証環境の立て方（`.claude/skills/adaptive-validation-runner/references/environment.md`）。

### DocumentParser とキャッシュへの影響

CLI だけの変更で、`executeParser()`・`prepareResponse()`・`parseDocumentSource()`・`postProcess()` のどこも変えない。差し替えのあと、マイグレーションのある版では、アップデーターが終わるまで公開側が 503 になる。マイグレーションの無い版では、アップデーターが終わるまで、新しいコードが古い `temp/cache/` のまま動く。ページのキャッシュは版で分けられていて（`index.php` が、コードの版とキャッシュの版を比べる）、アップデーターが最後にキャッシュを消す。そのため、このコマンドではキャッシュを消さない。

### 前提

- PHP 7.4 以上で動くこと。`str_starts_with()`・`str_contains()`・`match`・名前付き引数など PHP 8 の書き方は使わない（オプションの判定は `strpos($arg, '--tag=') === 0`）。
- 必要な拡張は `zip`（`ZipArchive`）。GitHub から取るときは、さらに `allow_url_fopen=On` と、`https` のストリーム（`openssl`）が要る。開発用の Docker イメージ（`manager/docker/Dockerfile`）には、どちらも入っている。
- 認証なしの GitHub API は、1 時間に 60 回まで呼べる。1 回の実行で呼ぶのは 1〜2 回である。

## Plan of Work

1 つのコマンドファイル `manager/includes/cli/commands/system-upgrade.php` に、処理とその関数（`upgrade_` で始める）をまとめる。ほかのコマンドと同じく、手続きを上から順に書き、関数はファイルの末尾に置く。

実行の流れは次のとおり。サイトのファイルを変えるのは 8 からである。それより前に終わったときは、終了時の処理が作業用のディレクトリを消すだけで済む。

1. **引数を読む。** オプションは次のとおり。
   - `--tag=<tag>`: 値は `^release-[0-9A-Za-z.]+$` に合わなければ止める（URL に埋め込むため）。
   - `--zip=<パス>`: `--tag` と同時には使えない。
   - `--yes`
   - `--driver=mysqldump|php`: `db:backup` と同じ。
   - `--help`: 使い方、差し替えの対象、戻し方を表示する。README は配布 zip に入らないので、ここで戻し方まで分かるようにする。
2. **終了時の処理を登録する。** `register_shutdown_function()` に、このファイルで定義した関数を渡す。状態の変数（`prepare`・`swapping`・`done`）で、作業用のディレクトリを消すか、ジャーナルで戻すかを分ける。
3. **事前に確かめる。** どれか 1 つでも満たさなければ止める。
   - サイトがインストール済みであること（`manager/includes/config.inc.php` がある）。
   - 未適用のマイグレーションが無いこと（`EvoMigrator::hasPending()`）。
   - `ZipArchive` があること。GitHub から取るなら、`allow_url_fopen` が有効で、`stream_get_wrappers()` に `https` があること。
   - POSIX 関数があるときは、実行する利用者が `manager/` の持ち主と同じであること。
   - サイトのルート、`manager/`、`install/`（あれば）、それぞれの親に書き込めること。
   - `temp/upgrade/` を作れること。作業用の領域がサイトと同じファイルシステムにあること（`stat()` の `dev`）。
   - 同時に 2 つ動かさないよう、`temp/upgrade/.lock` を `flock(LOCK_EX | LOCK_NB)` で取れること。
4. **版を読む。** 今の版は `manager/includes/version.inc.php` から、新しい版は配布 zip の中の同じファイルから読む。どちらも `upgrade_read_version()` で、`$modx_version = '...'` を正規表現で取り出す（ファイルを `include` しない）。比べる前に `upgrade_normalize_version()` で、先頭の `release-` と末尾の `J` を外す。
5. **配布 zip を手に入れる。** 取り方は引数で 3 通りに分かれる。
   - `--zip` のとき: そのファイルを作業用のディレクトリへコピーし、sha256 を表示する。
   - `--tag` のとき: タグから分かる版が今の版以下なら、通信の前に止める。そのあと `GET /repos/modxcms-jp/evolution-jp/releases/tags/<tag>` で取る。
   - どちらも無いとき: `GET /repos/modxcms-jp/evolution-jp/releases?per_page=100` から選ぶ。タグが `release-` で始まり、`prerelease` が偽で、今の版と系列が同じで、今の版より新しいもののうち、正規化した版が最も新しいものを取る。無ければ `Already up to date: <今の版> is the latest release in the <系列> series.` と表示して、終了コード 0 で終わる。
   - GitHub から取ったときは、添付ファイルのうち名前が `evo-<tag>.zip` のものをダウンロードする（無ければ止める）。`digest` が `sha256:` で始まれば、`hash_file('sha256')` と比べる。合えば `SHA-256 verified.` と表示し、違えば止める。`digest` が無いときは、警告を出して続ける。
   - 通信は、`file_get_contents()` と `fopen()` にストリームのコンテキストを付けて行う。付けるのは `User-Agent`、`Accept: application/vnd.github+json`、`timeout`、`ignore_errors`。応答の状態は、`$http_response_header` のうち最後の `HTTP/` 行で判定する（リダイレクトのあとの行）。
   - ダウンロードは `stream_copy_to_stream()` でファイルへ直接書き、メモリに載せない。書く前に、`disk_free_space()` で、添付ファイルの大きさの 4 倍（zip・展開先・バックアップ）が空いていることを確かめる。
6. **展開して確かめる。**
   - 展開の前に、すべての項目の名前を調べる。先頭が `/`、`\` を含む、NUL を含む、区切りに `..` がある、ドライブ名で始まる、のどれかに当たれば `Unsafe entry in zip: <名前>` と表示して止める。
   - 展開したあと、`manager/includes/version.inc.php`、`index.php`、`install/index.php`、`assets/` があることを確かめる。直下に、想定の 6 つ（`assets content install manager temp` と、`.` で始まるもの）以外のディレクトリがあれば止める。
   - 新しい版が今の版より新しくなければ止める。`--tag` も `--zip` も無いときは、さらに系列が同じであることを確かめる。GitHub から取ったときは、タグの版と zip の中の版が同じでなければ止める。
7. **準備して、確認を取る。**
   - 引き継ぐファイルを `package/manager/`・`package/install/` へコピーし、元の権限にそろえる。
   - 今の `manager/`・`install/` にあって、新しい版にも引き継ぐ一覧にも無いファイルを数える。
   - 次を表示する。今の版 → 新しい版、取得元、差し替えの対象、引き継ぐファイル、引き継がないファイル（多ければ先頭の 20 件と件数）、バックアップの置き場所。
   - `--yes` が無ければ `Continue? [y/N]` と聞く。`y`・`yes` 以外（入力の終わりを含む）なら、`Aborted.` と表示して終わる。
8. **DB のバックアップを取る。** `EvoBackupManager` の保存先へ `pre-upgrade-<自動の名前>.sql` として書き出す。続けて、開始を記録する。
   - 形は `evo()->logEvent(0, 1, 'system:upgrade started', 'system:upgrade', ['from' => <今の版>, 'to' => <新しい版>, 'source' => <取得元>, 'db_backup' => <パス>, 'backup_dir' => <パス>])`。
   - ここで状態を `swapping` にする。
9. **差し替える。** ここから先の操作は、すべてジャーナルに記録する。`cli_usage()` と `exit` は呼ばない。失敗は返り値で確かめ、`RuntimeException` を投げる。
   - `manager/`: 今の `manager/` を `backup/manager/` へ移し（成功したら `dir_moved` を記録）、準備した `package/manager/` をサイトへ移す（`dir_moved`）。
   - `install/`: 今の `install/` があれば `backup/install/` へ移し（`dir_moved`）、準備した `package/install/` をサイトへ移す（`dir_moved`）。
   - 配布 zip の直下にあるファイル（`.htaccess` は飛ばす）: サイトに同じ名前のファイルがあれば、`backup/root/` へコピーする。そのあと `file_overwritten` を記録してから上書きする。サイトに無ければ、`file_created` を記録してから作る。
   - `assets/`: 展開した `assets/` の下のファイルを 1 つずつ、直下のファイルと同じ手順で扱う（バックアップは `backup/assets/<相対パス>`）。途中のディレクトリが無ければ、作る前に `dir_created` を記録する。同じパスにファイル以外のもの（ディレクトリなど）があれば失敗とする。
10. **失敗したら戻す。** `Throwable` を捕まえる。Fatal のときは、終了時の処理が同じことを行う。
    - ジャーナルを逆順にたどる。`dir_moved` は `to` から `from` へ移す（`to` が無ければ飛ばす）。`file_overwritten` はバックアップから書き戻す。`file_created` は、ファイルがあれば消す。`dir_created` は、空なら消す。
    - 戻せなかった項目は、パスを添えて 1 つずつ表示する。
    - すべて戻せたときだけ、失敗を記録する。形は開始と同じで、`$type` は 3、`$msg` は `system:upgrade failed`、文脈に例外のメッセージを足す。
    - `Rolled back. The site files are back to <今の版>.`（戻しきれなければ `Rollback incomplete.` と、手で戻す場所）を表示し、DB のバックアップの名前を添えて、終了コード 1 で終わる。DB は戻さない。
11. **終わったら片付ける。** 状態を `done` にし、展開の残り（zip と `package/`）を消して、`backup/` は残す。最後に次を表示する。
    - サイトの URL の後ろに `install/?action=mode` を付けて開き、管理者でログインしてアップデーターを最後まで進めること。
    - 終わったら `install/` を消すこと。
    - 戻すときのファイルの場所（`temp/upgrade/<名前>/backup/`）と、DB のバックアップ。DB は、管理画面のバックアップ管理から復元できる。
    - 問題が無ければ、`temp/upgrade/<名前>/` と `pre-upgrade-*.sql` は消してよいこと。

入れ替えの間（数秒）は、古いファイルと新しいファイルが混ざった状態でリクエストを受けることがある。マイグレーションのある版では、入れ替えのあと `update_guard.php` が公開側を止める。混ざる時間を短くするため、`manager/` と `install/` は準備を済ませてから `rename()` で入れ替え、時間のかかる `assets/` は最後に回す。

Windows のサーバーでは、開いているファイルがあるとディレクトリの `rename()` が失敗することがある。その場合も、失敗としてロールバックする。Windows での動作確認は、この計画の範囲に含めない。

## Concrete Steps

コマンドの検証は、必ず使い捨てのワークツリーで行う（Validation and Acceptance の「検証用の場所」）。実装のワークツリーで `--yes` を付けて実行すると、作業中の `manager/` が移されてしまう。

### M1: 取得と確認

編集するファイル: `manager/includes/cli/commands/system-upgrade.php`（新規）。

上の流れの 1〜7 を作る。M1 の時点では、確認の問い合わせで `y` と答えると `Swap is not implemented yet.` と表示して終わる（M2 でこの分岐を置き換える）。ここで作る関数は次のとおり。

- `upgrade_read_version($file)`: `$modx_version` を読む。読めなければ空文字を返す。
- `upgrade_normalize_version($v)`: `release-` と、末尾の `J`（大文字・小文字とも）を外す。
- `upgrade_series($v)`: `X.Y` を返す。
- `upgrade_http_get($url, $saveTo = null)`: 本文を返すか、ファイルへ保存する。状態が 200 でなければ、状態の行と本文の先頭 200 文字を添えて `RuntimeException` を投げる。
- `upgrade_pick_release(array $releases, $current)`
- `upgrade_check_zip_entries(ZipArchive $zip)`
- `upgrade_site_files($managerDir, $installDir)`: 引き継ぐファイルの一覧を返す。
- `upgrade_rmdir($path)`
- `upgrade_on_shutdown()`

確かめるコマンド（使い捨てのワークツリーの検証環境で、`docker compose exec -T app ...` を使う）:

    php -l manager/includes/cli/commands/system-upgrade.php
    php evo system:upgrade --help
      → 使い方を表示する
    php evo system:upgrade
      → 1.3 系のリリースが無いあいだは "Already up to date: 1.3.0rc2 ..." と出て、終了コード 0
        （1.3 系のリリースが出たあとは、その版を選んで確認の問い合わせまで進む）
    php evo system:upgrade --tag=release-1.2.4J
      → 通信の前に、新しい版ではないとして止まる（終了コード 1）
    echo n | php evo system:upgrade --zip=temp/upgrade-fixtures/evo-test-1.3.0.zip
      → 1.3.0rc2 → 1.3.0、差し替えの対象、引き継ぐファイルを表示し、"Aborted." で終わる。temp/upgrade/ の下に作業用のディレクトリが残らない
    php evo system:upgrade --zip=temp/upgrade-fixtures/evil.zip
      → "Unsafe entry in zip: ../escape.txt" と出て止まる

コミット: `feat(cli): system:upgrade でリリースの取得と確認までを行う`

### M2: バックアップと差し替え・自動ロールバック

編集するファイル: `manager/includes/cli/commands/system-upgrade.php`。

上の流れの 8〜11 を作る。足す関数は `upgrade_put_file($src, $dst, $backupDir, $rel, array &$journal)`（バックアップ → 記録 → 書き込み）、`upgrade_move_dir($from, $to, array &$journal)`、`upgrade_overlay_dir($srcDir, $dstDir, $backupDir, array &$journal)`、`upgrade_rollback(array $journal)`（戻せなかったパスの配列を返す）。

確かめるコマンド（使い捨てのワークツリーで）:

    php -l manager/includes/cli/commands/system-upgrade.php
    php evo system:upgrade --zip=temp/upgrade-fixtures/evo-test-1.3.0.zip --yes
      → 版・バックアップ・差し替えの経過を表示し、最後に次の手順を表示する。終了コード 0
    （失敗を起こした別の使い捨てのワークツリーで同じコマンド）
      → "Rolled back. ..." と表示し、終了コード 1

コミット: `feat(cli): system:upgrade でバックアップと差し替え、失敗時の巻き戻しを行う`

### M3: ドキュメントと通しの検証

編集するファイル: `manager/includes/cli/README.md`、`assets/docs/migrations.md`。

- CLI の README に、`system:upgrade` の使い方、オプション、差し替えの対象と引き継ぐファイル、戻し方、後片付けを書く。
- `assets/docs/migrations.md` の「利用者がアップデートするとき」に、1.3.0 以降は `php evo system:upgrade` で手順 1 と手順 2 の前半をまとめて行えることを足し、詳しくは CLI の README を参照するよう書く。手順の正本は `migrations.md` に残す。
- `/doc-audit` で、変えたドキュメントを確かめる。
- Validation and Acceptance のすべてを通す。

コミット: `docs(cli): system:upgrade の使い方とアップデートの手順を書く`

## Validation and Acceptance

### 検証用の場所

コマンドは、マウント元の作業ツリーのファイルを実際に差し替える。差し替えたあとの作業ツリーでは `manager/docker/` が無くなり、検証環境を作り直せない。そのため、場面ごとに新しい使い捨てのワークツリーを作り、終わったら環境ごと捨てる。

    ROOT=$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)")
    git worktree add --detach "$ROOT/.claude/worktrees/upgrade-sandbox-<場面>" <実装のブランチ>

検証環境の立て方とインストールは、`.claude/skills/adaptive-validation-runner/references/environment.md` に従う。そのワークツリーのルートをカレントにして実行し、`EVO_VALIDATE_NAME` と `EVO_BIND_IP` は、場面ごとに、ほかのセッションと重ならない値にする。終わったら `down -v --rmi local` で環境を片付け、`git worktree remove --force` でワークツリーを消す。

### 検証用の配布 zip

まだ無い版を作る。場面ごとの使い捨てのワークツリーで、app コンテナの PHP（`zip` 拡張あり）を使って、`temp/upgrade-fixtures/`（`temp/upgrade/` とは別の場所）に作る。

1. ホストで `git archive --format=tar <実装のブランチ> | tar -x -C <使い捨てのワークツリー>/temp/upgrade-fixtures/pkg` を実行し、中身を取り出す。`.gitattributes` の `export-ignore` が効くので、配布 zip と同じ中身になる。
2. `pkg/manager/includes/version.inc.php` の版を `1.3.0` に変える。
3. 何もしないマイグレーション `pkg/manager/includes/migrations/20991231_000000_upgrade_probe.php` を置く。中身は `<?php return static function (EvoMigrationHelper $m) {};` とする。差し替えのあと、公開側が 503 になることを見るためである。
4. 新しいファイル `pkg/assets/snippets/upgrade_probe.txt` を置く。
5. app コンテナで、`pkg` の中身を zip の直下に入れた `evo-test-1.3.0.zip` を `ZipArchive` で作る。コードは `RecursiveDirectoryIterator` で `pkg` をたどり、`addFile($path, <pkg からの相対パス>)` で足していく、20 行ほどの `php -r` で書ける。
6. 悪い例として、`ZipArchive::addFromString('../escape.txt', 'x')` の 1 項目だけを入れた `evil.zip` も作る。

### 成功すること（場面: success）

使い捨てのワークツリーに今の版（1.3.0rc2）をインストールし、次の 3 つを置く。

- 利用者のファイル `assets/snippets/my_snippet.txt`
- 管理画面の保護 `manager/.htaccess`（中身は `# site protection` の 1 行）
- テーマの設定 `manager/media/style/<既定のテーマ>/config.php`

置いたら、`php evo system:upgrade --zip=temp/upgrade-fixtures/evo-test-1.3.0.zip --yes` を実行する。次のことを確かめる。

- 終了コードが 0 である。最後に次の手順が表示される（`install/?action=mode` を開く、終わったら `install/` を消す、戻し方、後片付け）。
- 版と引き継いだファイル:
  - `manager/includes/version.inc.php` が `1.3.0` になっている。
  - `manager/includes/config.inc.php`、`manager/.htaccess`、テーマの `config.php` が、実行前と同じ内容（`cmp` で一致）で、権限も同じ（`stat -c %a`）である。
- `assets/` と、触れないもの:
  - `assets/snippets/upgrade_probe.txt` ができていて、`assets/snippets/my_snippet.txt` が残っている。
  - `content/` と `.htaccess` は、実行前と変わらない（`sha256sum` で一致）。
- バックアップと片付け:
  - `temp/upgrade/<名前>/backup/` に、`manager/`、`root/index.php`、上書きした `assets/` のファイルがある。
  - 展開の残り（zip と `package/`）は無い。
  - `temp/upgrade/.htaccess` がある。
  - DB のバックアップの保存先に `pre-upgrade-*.sql` があり、管理画面のバックアップ管理の一覧に出る。
- 公開側とアップデーター:
  - `curl -s -o /dev/null -w '%{http_code}' http://<サイト>/` が `503` を返す（未適用のマイグレーションがあるため）。
  - 管理画面に管理者でログインし、`/install/?action=mode` からアップデーターを最後まで進める（画面の操作は Playwright で行う）。そのあと、公開側が `200` に戻り、`php evo migrate:status` が `0 pending` を表示する。
- 繰り返したとき: 同じ zip でもう一度実行すると、版が新しくないので止まる（終了コード 1）。

### 失敗したら戻ること

実行の前に、`find manager install assets index.php define-path.php evo sample.htaccess -type f -print0 | sort -z | xargs -0 sha256sum > <一時ファイル>` で一覧を取っておく。実行のあとに同じ一覧を取り、`diff` が空であること、終了コードが 1 で `Rolled back.` が表示されることを確かめる。場面は次の 2 つで、それぞれ新しい使い捨てのワークツリーで行う。

- **場面 rollback-assets（最後の段階で失敗）**: 配布 zip にある `assets/snippets/upgrade_probe.txt` と同じパスに、ディレクトリを作っておく（root でも書き込みに失敗させられる）。`manager/` と `install/` の入れ替えのあとで失敗するので、ディレクトリの入れ替えを戻せることが分かる。公開側が `200` を返すこと（`manager/` が戻り、未適用のマイグレーションも無い）と、`php evo log:search "system:upgrade"` で開始と失敗の記録が出ることも確かめる。
- **場面 rollback-manager（早い段階で失敗）**: サイトの `install` を、ディレクトリではなく同じ名前の空のファイルにしておく。`manager/` を入れ替えた直後、`install/` を移すところで失敗するので、`manager/` の入れ替えを戻せることが分かる。実行の前に `manager/includes/config.inc.php` が置かれたままであることも、一覧の比較で確かめられる。

途中まで書かれたファイルを戻す動き（容量不足などで `copy()` が途中で止まる）は、実機で確実に起こす方法が無い（`ulimit -f` は PHP ごと SIGXFSZ で終わらせ、終了時の処理も走らない）。そのため、自己レビューで、`file_overwritten`・`file_created` の記録が書き込みより前にあること、戻し方が冪等であることを、コードで確かめる。

### 取得の経路（場面: fetch）

- 引数なしでは `Already up to date` を表示する。`--tag=release-1.2.4J` では、通信の前に止まる。
- ダウンロードと sha256 の照合は、使い捨てのワークツリーの `manager/includes/version.inc.php` の版を `1.2.0` に書き換えてから確かめる。`echo n | php evo system:upgrade --tag=release-1.2.4J` を実行し、`SHA-256 verified.` が表示され、`Aborted.` のあと作業用のディレクトリが残らなければ合格とする。
- `evil.zip` では `Unsafe entry in zip: ../escape.txt` を表示して止まる。判定はこの表示で行う（`extractTo()` は自分で `../` を外すので、ファイルができないことだけでは判定できない）。

### 版の互換

成功の場面を、PHP 7.4 でも実際に動かす。手順は `.claude/skills/adaptive-validation-runner/references/environment.md` の「ほかの PHP の版で検証するとき」に従う。`php -l` だけでは、PHP 8 でしか使えない関数の混入を見つけられないためである。

## Idempotence and Recovery

- 作業用のディレクトリは、実行ごとに `temp/upgrade/<日時>-<ランダム>/` で分かれる。実行を重ねても、前のバックアップを上書きしない。
- 差し替えの前（流れの 1〜8）で終わったときは、終了時の処理が作業用のディレクトリを消すので、サイトは変わらない。DB のバックアップは残る（害は無い）。
- 差し替えの途中で終わったときは、自動で戻す。戻しきれなかった項目は、パスが表示される。そのときは、`temp/upgrade/<名前>/backup/` から手で戻す。
  - `manager/` が欠けていれば、`backup/manager/` を戻す。
  - 上書きしたファイルは、`backup/root/`・`backup/assets/` の同じ相対パスのものを、元の場所へ戻す。
- プロセスが強制終了されて（`kill -9` など）ジャーナルごと失われたときも、`backup/` を見れば、どこまで進んだかが分かる。`manager/includes/version.inc.php` の版で、入れ替えが済んだかを判断する。
- `temp/upgrade/.lock` はロックのためのファイルで、プロセスが終われば OS がロックを外す。ファイルが残っていても、次の実行の妨げにはならない。
- アップデーターの実行前なら、ファイルを `backup/` から戻せば元の版に戻る（DB はまだ変わっていない）。アップデーターの実行後に戻すときは、`pre-upgrade-*.sql` も管理画面のバックアップ管理から復元する。

## Artifacts and Notes

- 実装: `manager/includes/cli/commands/system-upgrade.php`（新規）
- ドキュメント: `manager/includes/cli/README.md`、`assets/docs/migrations.md`
- ロードマップ: `.agent/roadmap.md` の「evo system-upgrade コマンド実装」（Issue #668）
- 後続: ロードマップの「オンラインアップデート機構（基本設計）」（Issue #669）は、この計画の取得・検証・差し替え・ロールバックの作りを、設計の入力にする
- 想定するコミット: M1〜M3 の各節に書いた 3 つ

## Interfaces and Dependencies

- GitHub REST API: `GET https://api.github.com/repos/modxcms-jp/evolution-jp/releases?per_page=100` と `GET .../releases/tags/<tag>`。使う項目は `tag_name`・`prerelease`・`assets[].name`・`assets[].size`・`assets[].browser_download_url`・`assets[].digest`。`User-Agent` が要る。
- 配布 zip: `.github/workflows/release.yml` の作り（`git archive`、トップのディレクトリ無し、名前は `evo-<tag>.zip`）。作り方が変わったら、このコマンドの展開の確認も合わせて変える。
- `EvoBackupManager`（`manager/includes/backup_manager.class.inc.php`）: DB のバックアップの保存先、名前、保護の `.htaccess`。
- `EvoMigrator::hasPending()`（`manager/includes/migrator.class.inc.php`）: 事前の確認で使う。
- `manager/includes/update_guard.php` とマイグレーション（`assets/docs/migrations.md`）: 差し替えたあと、アップデーターを実行するまで公開側を止める役割。このコマンドからは呼ばない。
- インストーラ（`install/`）: `config.inc.php` の有無で新規とアップデートを分ける。引き継ぐファイルを先に置く順序は、これに合わせたもの。
- PHP: 7.4 以上、`zip` 拡張、`allow_url_fopen` と `openssl`（GitHub から取るとき）、POSIX 関数（あれば利用者の確認に使う）。
