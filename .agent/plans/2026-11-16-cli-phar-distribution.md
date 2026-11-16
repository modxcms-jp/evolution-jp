# ExecPlan: CLI を evo.phar にして配布 zip から外す（Issue #1509）

## Purpose / Big Picture

サイトの公開ディレクトリに CLI のファイルを置かないようにする。今はルートの `evo`（拡張子が無いので、Web から開くとソースがそのまま表示される）と `manager/includes/cli/` が配布 zip に入っている。これを zip から外し、リリースのときに 1 つのファイル `evo.phar` を作って zip とは別に添付する。利用者はそれをサイトの外に置き、`php evo.phar --path=/path/to/site health:check` のように対象のサイトを指定して使う。CLI のソースは本体と同じリポジトリに残し、本体と同じ版でリリースする。

Issue: #1509（方針は Issue の 2 つ目のコメント）。対象は 1.3（main）。1.2 系には入れない。

## Progress

- [ ] (2026-11-16) M0: PoC。今の CLI のコードから手で phar を組み、サイトの外から `--path` で `health:check` などが動くことを確かめる。main の出力を記録しておく（コミットしない）
- [ ] M1: CLI のソースを `cli/` へ移して配布 zip から外し、開発の手順の呼び方を `php cli/evo` に改める
- [ ] M2: サイトの外から動く起動処理（`--path`・サイトの探し方・版の確かめ・`help` と `--version`）、phar を組み立てるスクリプト、`health:check` の古い CLI の検出
- [ ] M3: リリースの CI で evo.phar を組み立てて添付する。PR でも組み立てを確かめる。リリースの手順書を合わせる
- [ ] M4: 利用者向けの文言（言語ファイル・マイグレーションのエラー）と文書を evo.phar に書き換え、古い `evo` の扱いを案内する
- [ ] M5: 横断の確認（PHP 7.4、zip の大きさ、`php evo` の残り）

## Surprises & Discoveries

計画時（2026-11-16、main 92099d4f6 で調査）に分かったこと:

- 本体は CLI に依存していない。`EVO_CLI` 定数も `manager/includes/cli/` のパスも、CLI の外からは参照されていない（`grep -rn "EVO_CLI\|includes/cli" manager install index.php`）。CLI は、本体の `define-path.php` と `DocumentParser` を読み込み、`EvoMigrator`・`EvoBackupManager`・`Mysqldumper`・`SystemLogViewer`・`EvoSiteExportJob` を呼ぶ一方向の依存だけ。本体のコードを変えるのは、CLI を案内する文言（言語ファイルの `bkmgr_restore_msg` と `manager/includes/migrations/00000000_baseline.php` のエラー文）だけになる。DocumentParser の `executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()` には影響しない（CLI は `MODX_API_MODE` で `getSettings()` まで呼ぶだけで、ここは変えない）。キャッシュの作り・消し方も変えない。
- 今の起動処理（`manager/includes/cli/bootstrap.php`）は、サイトのルートを `dirname(__DIR__, 3)` で決めている。ただし `MODX_BASE_PATH` が定義済みならそれを使う。phar の中では `__DIR__` が `phar://.../evo.phar` になるため、この計算はそのままでは使えない。`define-path.php` は自分の `__DIR__` から `MODX_BASE_PATH` を決めるので、入口でサイトの `define-path.php` を読み込めば、あとの本体の処理はそのまま動く見込み（M0 で確かめる）。
- コマンドの中で `__DIR__` に頼るのは `skill:*` の `require_once __DIR__ . '/../skill-lib.php'` と `include __DIR__ . '/skill-validate.php'` だけ。`make:command` は `EVO_CLI_COMMANDS_PATH` にファイルを書く（phar の中には書けない）。カレントディレクトリ（`getcwd()`・`chdir()`）を使うコマンドは無い。`--path` という名前の引数を持つコマンドも無い。
- 古い `evo` は「CLI を zip から外すと動かなくなる」とは限らない。利用者のアップデートは「ファイルを差し替える」（`assets/docs/migrations.md` の「利用者がアップデートするとき」）で、上書きで差し替えると古い `manager/includes/cli/` も残る。そのため古い `evo` は古いコマンドのまま、新しい本体に対して動き続ける。`manager/` を丸ごと入れ替えたときだけ `CLI bootstrap not found: ...` で止まる。案内では、`evo` と `manager/includes/cli/` の両方を消すよう書く必要がある。
- 配布 zip は、すでに 1.5MiB を超えている。`git archive --format=zip HEAD` は 1,652,971 バイト（1.576MiB）だった。CLI の 33 ファイル（`evo` と `manager/includes/cli/` の下）は、圧縮後 34,682 バイトに、zip の見出しを足して約 40,500 バイトになる。外したあとは約 1,612,500 バイト（1.538MiB）になる見込み（zip の目録からの計算。実測は M1 で行う）。CLI の README は、もともと `README*` の export-ignore で zip に入っていない。
- 公式イメージ `php:7.4-cli` には `Phar`・`mbstring`・`json` が入っていて、`mysqli` は入っていない。`phar.readonly` は既定で `On`（phar を作るときは `-d phar.readonly=0` が要る）。
- リリースのスキル（`.claude/skills/release/SKILL.md`）は、ワークフローに `files: evo-${{ github.ref_name }}.zip` という文字列があるかを確かめている。添付を足すとこの確認が通らなくなるので、ワークフローと同時に直す必要がある。
- リリースの zip は `git archive "refs/tags/<tag>"` で作る。`cli/` を export-ignore にすると、`git archive` からは CLI のソースを取り出せない。phar は、チェックアウトした作業ツリーから組み立てる（作業ツリーの HEAD がタグのコミットであることを先に確かめる）。
- 本体の版は `manager/includes/version.inc.php` の `$modx_version`（今は `1.3.0rc2`）。`index.php` は、静的なクロージャの中で `include` して版を読んでいる。そのあとの `initialize.inc.php` は `require_once` で同じファイルを読むので、2 回目は読み込まれない。それでも、グローバルの `$modx_version` を読むコードは無い（0 件）ので害は無い。CLI も同じ読み方でよい。
- 「evo system-upgrade コマンド実装」（#668、`.agent/plans/2026-05-06-evo-system-upgrade-command.md`）は、配布 zip の直下に `evo` があること、実行中のコマンドのファイルが `manager/` の中にあること、を前提に書かれている。この計画で、どちらの前提も変わる（Interfaces and Dependencies）。

## Decision Log

- 2026-11-16 / Claude（planner）: CLI のソースは、リポジトリのルートの `cli/` にまとめる。`.gitattributes` に `/cli/ export-ignore` を足して zip から外す。ルートの `evo` はリポジトリからも無くし、開発用の入口は `cli/evo` にする。
  理由: 「配る本体」と「配らない CLI」の境目をディレクトリで表せる。`manager/includes/` の中に配らないコードがあると、本体のコードから CLI のヘルパーを読む誤り（配布物では動かない）を招く。phar の組み立ても「`cli/` の決まった場所をまとめる」だけで済む。サイトのルートの `evo` と `manager/includes/cli/` は「古い CLI の残り」だと一意に言えるので、`health:check` で知らせられる。
  代わりの案: (1) `evo` と `manager/includes/cli/` を今の場所に残して export-ignore にする案。動かすファイルは少ないが、上の境目が見えず、開発の環境のルートに `evo` が残るので、古い残りと見分けられない。(2) 別のリポジトリにする案は、本体と版をそろえる必要があるため採らない（Issue のコメントで決定済み）。
- 2026-11-16 / Claude（planner）: 開発者がリポジトリのソースから動かすときは `php cli/evo <command>` にする。Docker では `docker compose exec <app-service> php cli/evo <command>`（検証環境では `docker exec -w /var/www/html <名前>-app php cli/evo <command>`）。`php cli/evo` と `php evo.phar` は同じ起動処理（`cli/bootstrap.php`）を通り、違うのは版の出どころと、開発用のコマンドの有無だけ。
  理由: 開発の環境は作業ツリーを Web のルートにマウントしているので、ルートに `evo` を置くと、開発の環境でもソースが Web から見える。また、上の検出で、古い残りとの見分けが付かない。
  代わりの案: 開発者も毎回 phar を組み立てて使う案は、コマンドを直すたびに組み立てが要り、手間が大きいので採らない。`evo` をリポジトリのルートに残して zip からだけ外す案は、呼び方は変わらないが上の 2 点が残るので採らない（2026-11-16 に本人が `php cli/evo` に決めた）。
- 2026-11-16 / Claude（planner）: 開発用のコマンドは `cli/dev/commands/` に分け、phar に入れない。対象は `skill:*` の 7 つ（`skill-archive`・`skill-complete`・`skill-init`・`skill-prune`・`skill-status`・`skill-sync`・`skill-validate`）と、それが読む `cli/dev/skill-lib.php`、`make:command`、`make:migration`。phar に入れるのは `cli/commands/` の全部と `cli/bootstrap.php`・`cli/cli-helpers.php` だけにする。
  理由: `skill:*` はリポジトリの `.agent/` を読み書きするが、配布したサイトに `.agent/` は無い。`make:command` は phar の中に書けない。`make:migration` が作るのは本体のマイグレーションで、本体のリポジトリでしか意味が無い。phar に入れるものをディレクトリで決めておけば、除外の一覧を別に持たずに済む。
  代わりの案: すべて phar に入れて、使えない環境では止める案は、利用者の `help` に使えないコマンドが並ぶので採らない。開発用のコマンドを `cli/` の外（`.agent/tools/` など）へ出す案は、動かす入口が別に要るので採らない（2026-11-16 に本人が `cli/dev/` に決めた）。Laravel の artisan では、開発用のパッケージは `require-dev` で入れ、本番で `--no-dev` にするとコマンドごと入らない。「開発の環境にだけあるコマンドは配布物に入れない」という点で同じ考え方。artisan が `make:*` を本番に残せるのは作る先がアプリのソースだからで、この CMS の `make:migration`・`make:command` が書く先は本体と CLI のソースなので、外すほうが合う。
- 2026-11-16 / Claude（planner）: 対象のサイトは、次の順で決める。(1) 引数に `--path=<サイトのルート>` があればそこ。(2) 無ければ、カレントディレクトリから親へ順にたどり、`define-path.php` と `manager/includes/version.inc.php` の両方があるディレクトリ。`--path=` は引数のどこに書いてもよい（コマンドの名前の前でも後でも）。起動処理が取り除いてからコマンドに渡す。コマンドには `--path` という名前の引数を作らない（予約）。`--path` を `=` なしで書いたとき、2 回書いたとき、指したディレクトリにサイトの印が無いときは、終了コード 1 で止める。`chdir()` はしない。
  理由: `--path` を「コマンドの前だけ」にすると、`php evo.phar health:check --path=/site` と後ろに書いた人の `--path` がコマンドに渡って無視され、カレントから見つかった別のサイトで動いてしまう。親へたどるのは、サイトの中のどこ（`assets/templates/` など）からでも動かせるようにするため。`chdir()` しないのは、`db:export --output=backup.sql` や `db:import ./x.sql` の相対パスを、利用者のカレントからのまま解釈するため。
  代わりの案: 環境変数（`EVO_SITE_PATH` など）でも指定できるようにする案は、今の使い方で要る場面が無いので足さない。
- 2026-11-16 / Claude（planner）: CLI とサイトの版は、文字列が完全に一致しなければ止める。比べるのは、phar に組み込んだ版（組み立てたときの `manager/includes/version.inc.php` の `$modx_version`）と、サイトのファイルの `manager/includes/version.inc.php` の `$modx_version`。`php cli/evo` では、`cli/` と同じ作業ツリーの `manager/includes/version.inc.php` を CLI の版とする。確かめるのは本体（`define-path.php` や `DocumentParser`）を読み込む前で、合わなければ次のように出して終了コード 1 で止める。上書きして続けるオプションは作らない。
      Version mismatch: evo.phar is 1.3.1J, but the site at /home/user/public_html is 1.3.0J.
      Download the evo.phar of the same release: https://github.com/modxcms-jp/evolution-jp/releases/download/release-1.3.0J/evo.phar
  理由: CLI は本体のクラスを直接呼ぶ薄い層で、パッチの版でもクラスやメソッドが変わりうる。版が違うまま本体を読み込むと、途中で Fatal error になったり、古いコマンドが新しい本体を誤って扱ったりする。DB の `settings_version` ではなくファイルの版と比べるのは、CLI が呼ぶのはファイルのコードだから（ファイルを差し替えて、アップデーターを流す前でも、合った evo.phar で `migrate:status` などを使える）。同じ版の evo.phar は、各リリースに必ず添付されているので、迂回のオプションは要らない（黙って続ける逃げ道を作らない）。
  代わりの案: 同じ系列（1.3.x どうし）なら通す案は、新しい evo.phar が、古いサイトに無いメソッドを呼んで途中で止まる危険があるので採らない（2026-11-16 に本人が完全な一致に決めた）。
- 2026-11-16 / Claude（planner）: 版を読むのは、`cli/cli-helpers.php` の関数 1 つ（`cli_read_version(string $root): string`）にする。中身は `index.php` と同じく、静的なクロージャの中で `include` して `$modx_version` を返す（無ければ `''`）。サイトを探す関数（`cli_find_site_root(string $start): ?string`）も同じファイルに置く。起動処理と phar の組み立てのスクリプトの両方が、このファイルを読む。
  理由: 版の読み方を CLI の中で 1 か所にする。`cli-helpers.php` は関数の定義だけで、読み込んだ時点では本体を呼ばないので、本体の読み込みより前に使える。
- 2026-11-16 / Claude（planner）: `help`（コマンドを指定しないときも含む）と `--version`、知らないコマンドは、本体を読み込まずに起動処理の中で扱う。`manager/includes/cli/commands/help.php` は無くし、一覧を出す処理を `cli/bootstrap.php` に移す。`help` はサイトが見つからなくても終了コード 0 で一覧を出し、`Site:` の行に、見つかったサイトのパスと版（または見つからない旨）を出す。`--version` は、CLI の版と組み立てたコミット、見つかればサイトのパスと版を出す。
  理由: 一覧や版を見るのに DB の接続は要らない。サイトの外で `php evo.phar help` を打った利用者にも、使い方を見せられる。
- 2026-11-16 / Claude（planner）: 使い方の表示（`Usage: php evo ...`、27 か所）は、定数 `EVO_CLI_COMMAND` を使って組み立てる。値は、phar では `'php ' . basename(Phar::running(false))`（名前を変えて置いても、その名前を出す）、ソースからでは `'php cli/evo'`。phar の中かどうかは `strpos(__DIR__, 'phar://') === 0` で判定する（`Phar` 拡張が無い環境でも、ソースからは動く）。
- 2026-11-16 / Claude（planner）: phar は `cli/build-phar.php` で組み立てる。実行は `php -d phar.readonly=0 cli/build-phar.php --output=<出力先> [--commit=<コミット>]`。中身は次のとおり。
  - 入れるファイル: `bootstrap.php`・`cli-helpers.php`・`commands/*.php`。どれも、`cli/` からの相対パスのまま入れる。それに、組み立てのときに作る `build.php`（`return ['version' => ..., 'commit' => ...];`）を足す。
  - 入れる前に、各ファイルを、組み立てに使う PHP の `php -l` に通す。`cli/commands/` と `cli/dev/commands/` に同じ名前のコマンドがあれば止める。
  - stub（phar の先頭の、起動のためのコード）は `#!/usr/bin/env php`、`Phar::mapPhar('evo.phar')`、`require 'phar://evo.phar/bootstrap.php'`、`__HALT_COMPILER();`。別名（alias）は `evo.phar` にする。
  - 圧縮はしない（約 100KB と小さく、圧縮すると利用者の PHP に zlib が要る）。署名は SHA-256。
  - 一時ファイルに書いてから、出力先へ `rename()` する（失敗したときに、壊れた phar を残さない）。
  CI では、公式イメージ `php:7.4-cli` の中で組み立てる。
  理由: 組み立てた PHP の `php -l` を通すので、PHP 7.4 で読めない構文がコマンドに入ると、その場で落ちる（main の対象は PHP 7.4 以上）。phar の作りは PHP の版に依らない。`docker run` を使うので、新しい Action を足さなくてよい。
- 2026-11-16 / Claude（planner）: リリースに添付するファイル名は、どの版でも `evo.phar` にする。版は `php evo.phar --version` で分かる。各リリースの URL が `https://github.com/modxcms-jp/evolution-jp/releases/download/release-<版>/evo.phar` に決まるので、版が合わないときの案内に、そのまま出せる。
  代わりの案: `evo-release-1.3.0J.phar` のように版を名前に入れる案は、使うたびに長い名前を打つことになるので採らない。
- 2026-11-16 / Claude（本人の指示で WP-CLI のインストール手順 https://make.wordpress.org/cli/handbook/guides/installing/ と比べた）: 利用者向けの使い方の案内（M4 の `cli/README.md` と `assets/docs`）は、WP-CLI と同じ流れにする。リリースから `evo.phar` を取る → `php evo.phar --version` で動くか確かめる → 必要なら `chmod +x evo.phar` してサイトの外の `PATH` の通った場所（`~/bin/evo` など）に置き、`evo --path=/path/to/site health:check` のように打てるようにする。stub の `#!/usr/bin/env php` は、このために置く。`--version` には、CLI の版・組み立てたコミット・サイトのパスと版に加えて、使っている PHP（`PHP_BINARY` と版）も出し、問い合わせのときに貼ってもらえる 1 つの表示にする（WP-CLI の `--info` にあたる）。
  取り入れないもの: 自分を最新の版に上げるコマンド（`wp cli update`）は作らない。WP-CLI は 1 つの phar で多くの WordPress の版を扱えるように、本体の公開 API だけを呼ぶ作りだが、evo.phar は本体の内部のクラスを呼ぶので、版はサイトに合わせるもので「最新」に上げるものではない（版が違えば止まって、合う版の URL を出す）。GPG の署名、nightly の版、Composer での全体へのインストール、Windows 向けの `wp.bat` の包み、設定ファイル（`wp-cli.yml`）でのサイトの指定も入れない。どれも今の使い方で要る場面が無いか、手順書が古い時期の作法で、`--path` とカレントからの探し方で足りる。改ざんの確かめは、GitHub のリリースが添付ごとに示す SHA-256 で足りるので、別のチェックサムのファイルも作らない。
- 2026-11-16 / Claude（planner）: 既存のサイトに残る古い `evo` と `manager/includes/cli/` を消す仕組みは作らない（Issue のコメントで決定済み）。案内は 3 か所に置く。(1) `assets/docs/migrations.md` の「利用者がアップデートするとき」に、消す手順を足す。(2) この変更を含む最初のリリースのリリースノートの「アップグレード時の注意」。(3) `health:check` に `Legacy CLI files removed` の行を足し、残っていれば FAIL（`remove evo, manager/includes/cli/ from the site root` のように、残っているものを出す）にする。管理画面の警告（`config_check.inc.php`）には足さない。
  理由: 残った `evo` の中身は、`bootstrap.php` を読み込むだけのコードで秘密は無い。`manager/includes/cli/` は、配布物の `manager/includes/.htaccess` で Web から閉じている。全言語の言語ファイルに文言を足すほどの重さは無い。CLI を使う人は `health:check` で気づける。
- 2026-11-16 / Claude（planner）: 本体を読み込む前のエラー（サイトが見つからない、版が合わない、引数の誤り）は、標準エラーに出すだけにして、`evo()->logEvent()` には残さない。本体を読み込んだあとのコマンドのログの残し方は変えない。
  理由: `logEvent()` は本体を読み込まないと使えない。版が合わない本体を、ログのためだけに読み込むのは避けたい。
- 2026-11-16 / Claude（planner）: `cli/` に `.htaccess` は置かない。
  理由: この計画が守る対象は、配布物（zip）を置いたサイト。リポジトリを丸ごと clone して公開するやり方では、`.agent/`・`compose.yml`・`AGENTS.md` なども見えるので、`cli/` だけ閉じても意味が薄い。phar の中のコマンドは起動処理を通らないと動かず、起動処理は `php_sapi_name() !== 'cli'` で止まる。
- 2026-11-16 / Claude（planner）: PR でも phar の組み立てを確かめるワークフロー `.github/workflows/cli-build.yml` を足す。`cli/**`・`manager/includes/version.inc.php`・そのワークフロー自身が変わった PR と、main への push で動かす。組み立てた phar は、Actions の成果物（artifact、7 日）として残す。必須のチェックにはしない。
  理由: リリースのタグを打ってから組み立てが壊れていると分かると、タグを消して打ち直すことになる。
- 2026-11-16 / Claude（planner）: PR は、M1 から M4 までを順に、それぞれ main 宛てにする（M2 は M1、M3 は M2 のあと）。途中の main では、利用者向けの文言が `php evo` のままになる。それでも、1.3 のリリースは、ロードマップのこの項目が WIP のあいだはリリースのスキルの開始前チェックで止まる。そのため、途中の状態は配られない。M1 では、開発の手順の書き換えも同じ PR に入れる（main で開発の手順が動かない時間を作らない）。

## Outcomes & Retrospective

実装後に記載

## Context and Orientation

用語:

- **CLI**: コマンドラインから管理作業をするための道具。今は、サイトのルートで `php evo <コマンド>` と打つ（例: `php evo health:check`、`php evo db:backup`）。
- **phar**: PHP のプログラム一式を 1 つのファイルにまとめる、PHP 標準の形式。`php evo.phar <コマンド>` のように、ファイル 1 つで実行できる。先頭に「stub」と呼ぶ起動のためのコードがあり、中のファイルは `phar://<ファイル>/<中のパス>` という形で読み込む。
- **配布 zip**: リリースに添付する `evo-release-<版>.zip`。`.github/workflows/release.yml` が `git archive` で作る。`.gitattributes` で `export-ignore` を付けたパスは入らない。
- **サイトのルート**: `index.php` と `define-path.php` があるディレクトリ。`define-path.php` が、自分の場所から定数 `MODX_BASE_PATH`（末尾 `/` 付き）を決める。
- **DocumentParser**: 本体の中心のクラス（`manager/includes/document.parser.class.inc.php`）。CLI は、これを作って設定を読んでから、`evo()`・`db()` を使うコマンドを動かす。
- **検証環境**: `.claude/skills/adaptive-validation-runner/` の Docker の環境。アプリのコンテナ（php:8.4-apache）が作業ツリーを `/var/www/html` にマウントする。名前は `EVO_VALIDATE_NAME`（例: `evo-validate-1509`）、コンテナ名は `<名前>-app`。起動とインストールの手順は、同スキルの `references/environment.md` の「起動からスモークテストまで」。

今の作り（main 92099d4f6）:

- `evo`（ルート、拡張子なし）: 入口。`EVO_CLI`・`MODX_API_MODE` を定義して `manager/includes/cli/bootstrap.php` を読み込む。`$argv[1]` のコマンド名の `:` を `-` に替え、`EVO_CLI_COMMANDS_PATH` の下の同じ名前の `.php` を `require` する。コマンドのファイルからは、`$args`（コマンド名より後の引数）が見える。見つからなければ `help.php` を出して終了コード 1。
- `manager/includes/cli/bootstrap.php`: CLI 以外からの実行を止める。`$_SERVER` の足りない値を埋め、`EVO_CLI_IMPORT` を環境変数から定義する。`dirname(__DIR__, 3) . '/define-path.php'` を読み、`mysqli`・`mbstring` の拡張を確かめる。最後に `DocumentParser` を作って `getSettings()` を呼び、`EVO_CLI_PATH`・`EVO_CLI_COMMANDS_PATH` を定義して `cli-helpers.php` を読む。
- `manager/includes/cli/cli-helpers.php`: `cli_out()`・`cli_err()`・`cli_usage()`・DB の書き出し（`cli_export_database()` など）。
- `manager/includes/cli/commands/`: 27 のコマンド（`cache-clear`・`config-show`・`db-backup`・`db-console`・`db-count`・`db-describe`・`db-export`・`db-import`・`db-query`・`db-tables`・`export-site`・`health-check`・`help`・`log-clear`・`log-search`・`log-tail`・`make-command`・`make-migration`・`migrate-status`・`migrate`・`skill-archive`・`skill-complete`・`skill-init`・`skill-prune`・`skill-status`・`skill-sync`・`skill-validate`）。
- `manager/includes/cli/skill-lib.php`: `skill:*` の共通の関数。
- `manager/includes/cli/README.md`: 使い方。zip には `README*` の export-ignore で入っていない。
- `.gitattributes`: export-ignore の正本。`assets/docs/release-process.md` の「除外ファイル一覧」と、ドラフトの zip の確認の正規表現も、同じ一覧を持つ。
- `.github/workflows/release.yml`: `release-*` のタグの push で、zip を作る → 大きさを報告 → `softprops/action-gh-release@v2` で下書きのリリースに `evo-${{ github.ref_name }}.zip` を添付する。
- `compose.yml` / `manager/docker/Dockerfile`: 開発の環境。アプリは php:8.4-apache で、作業ツリーを `/var/www/html` にマウントする（作業ディレクトリも `/var/www/html`）。

`php evo` と `manager/includes/cli` を書いている箇所の棚卸し（2026-11-16、行数。アーカイブは除く）と、置き換えの決まり:

| 種類 | ファイル（行数） | 置き換え |
| --- | --- | --- |
| CLI 自身 | `evo`、`manager/includes/cli/` の下の全ファイル（README 41、コマンドの使い方 27 など） | M1 で `cli/` へ移す。使い方の表示は M2 で `EVO_CLI_COMMAND` に。README は M4 で書き直す |
| 開発の手順（リポジトリのソースから動かす） | `AGENTS.md`（2）、`.agent/PLANS.md`（3）、`.agent/agents/README.md`（1）、`.agent/agents/tester.md`（1）、`.agent/runs/README.md`（5）、`.agent/roadmap.md`（「DocumentParser責務別トレイト分割」の 1 行）、`.claude/skills/issue-resolver/SKILL.md`（24）、`.claude/skills/adaptive-validation-runner/`（`SKILL.md` 3、`references/environment.md` 3、`references/upgrade-fixtures.md` 3、`scripts/lib.js` 1、`scripts/upgrade-fixture.js` 3、`scripts/form-snapshot.js` の `appExec(['php', 'evo', ...])` 1）、`.claude/skills/exec-plan-runner/SKILL.md`（2）、`.claude/skills/exec-plan/SKILL.md`（1）、`.claude/skills/project-worker/SKILL.md`（1）、`.codex/skills/adaptive-validation-runner/SKILL.md`（1）、`.codex/skills/project-worker/agents/openai.yaml`（2）、進行中の ExecPlan（`2026-07-08-pdo-migration-dbapi-refactor.md` 10、`2026-07-14-document-parser-trait-split.md` 7、`2026-10-10-manager-input-and-csrf-hardening.md` 1、`2026-10-12-dbapi-null-handling.md` 6、`2026-10-18-mm-jquery-removal.md` 1、`2026-11-10-request-source-narrowing.md` 3） | M1 で `php evo` → `php cli/evo`、`manager/includes/cli/` → `cli/`（`skill:*`・`make:*` は `cli/dev/`） |
| 利用者向け（配布物の中の文言） | `manager/includes/lang/english.inc.php`・`japanese-utf8.inc.php` の `bkmgr_restore_msg`（各 1）、`manager/includes/migrations/00000000_baseline.php`（1） | M4 で `php evo db:query` → `php evo.phar db:query` |
| 利用者の操作を説明する文書 | `assets/docs/migrations.md`（9）、`assets/docs/site-url.md`（2）、`assets/docs/events-and-plugins.md`（1）、`.agent/roadmap.md` の #668・#485 の項目（3） | M4 で、利用者の操作は `php evo.phar`、開発の操作（`make:migration`）は `php cli/evo` |
| #668 の ExecPlan | `.agent/plans/2026-05-06-evo-system-upgrade-command.md`（32） | M4 で本文は書き換えず、Surprises & Discoveries に、この計画で変わった前提を書き足す（着手時に読み替える） |
| 変えない（記録） | `.agent/plans/archive/`、`.agent/roadmap-archive.md`、`.agent/runs/<run>/` の下 | そのまま |

## Plan of Work

M0 では、本番の作りに手を入れる前に、「phar の中から、サイトの外のカレントで、別の場所の本体を読み込んで動く」ことを確かめる。今のコードを作業用ディレクトリ（リポジトリの外）にコピーし、15 行ほどの stub と手で組んだ phar で試す。今の bootstrap は `MODX_BASE_PATH` が定義済みなら `define-path.php` を探さないので、stub でサイトの `define-path.php` を先に読めば、ほぼ手を入れずに試せる。同時に、M1 以降の結果と比べるために、main の `php evo` の出力を記録する。

M1 では、ソースを `cli/` に移す（`git mv` で履歴を保つ）。動きは変えず、サイトのルートの決め方も「`cli/` の 1 つ上」に替えるだけにする。開発の手順の `php evo` を `php cli/evo` に書き換え、`.gitattributes` に `/cli/` を足す。この段階で、配布 zip から CLI が消え、開発者は `php cli/evo` で今までどおり動かせる。

M2 で、本題の起動処理を作る。入口の `cli/evo` と phar の stub は、どちらも定数を少し決めて `cli/bootstrap.php` を読み込むだけにする。起動処理は、次の順に進む。

1. CLI 以外からの実行を止める
2. 引数から `--path=` を取り出す
3. CLI の版を決める（phar なら組み込みの `build.php`、ソースなら作業ツリーの `version.inc.php`）
4. `--version` と `help` を扱う
5. サイトを決める
6. サイトの版と比べる
7. 今と同じ本体の起動（`$_SERVER` の補い → `define-path.php` → 拡張の確認 → `DocumentParser`）
8. コマンドを探して `require` する

コマンドの `require` は、今と同じくファイルのトップレベル（関数の外）で行う。コマンドのファイルが `$args` やグローバルの `$modx` を前提にしているため。phar の組み立ては、同じ `cli-helpers.php` の版の読み方を使う別のスクリプトにする。

M3 で、リリースのワークフローに phar の組み立てと添付を足し、PR 用のワークフローを足す。リリースのスキルと手順書の確認項目を、添付が 2 つになる形に直す。

M4 で、利用者に見える文言と、利用者の操作を説明する文書を `evo.phar` に書き換える。CLI の README は、利用者が evo.phar を使い始める手順・版の決まり・開発者向けの節を持つ形に書き直す。

M5 で、PHP 7.4 での実行、zip の大きさ、`php evo` の残りを、まとめて確かめる。

## Concrete Steps

以下、コマンドは作業ツリーのルートで実行する。`<名前>` は検証環境の名前（例: `evo-validate-1509`）、`<work>` はリポジトリの外の作業用ディレクトリ。検証環境の起動とインストールは `.claude/skills/adaptive-validation-runner/references/environment.md` に従う（並走の確認も含む）。

### M0: PoC（コミットしない）

1. 検証環境を起動してインストールし、main の出力を記録する。各コマンドの標準出力・標準エラー・終了コードを、`<work>/baseline/<コマンド>.txt` に保存する。

       docker exec -w /var/www/html <名前>-app php evo help
       docker exec -w /var/www/html <名前>-app php evo health:check
       docker exec -w /var/www/html <名前>-app php evo config:show site_name
       docker exec -w /var/www/html <名前>-app php evo db:describe site_content
       docker exec -w /var/www/html <名前>-app php evo db:count site_content
       docker exec -w /var/www/html <名前>-app php evo migrate:status
       docker exec -w /var/www/html <名前>-app php evo log:tail --lines=5
       docker exec -w /var/www/html <名前>-app php evo cache:clear
       docker exec -w /var/www/html <名前>-app php evo db:backup
       docker exec -w /var/www/html <名前>-app php evo export:site --site-url=http://localhost/

2. `<work>/poc/src/` に `manager/includes/cli/` の中身をコピーする。`<work>/poc/stub.php` を書く。stub は次のことをする。
   - `$argv` から `--path=` を取り除く
   - `EVO_CLI`・`MODX_API_MODE` を定義する
   - `require <path>/define-path.php` する
   - `require 'phar://evo.phar/bootstrap.php'` する
   - `evo` と同じ振り分けをする
   - 最後に `__HALT_COMPILER();`
   組み立ては `php:7.4-cli` で行う。`new Phar(<出力>, 0, 'evo.phar')` に `src/` を `buildFromDirectory()` で入れ、`setStub()`、`setSignatureAlgorithm(Phar::SHA256)` を呼ぶ。

       docker run --rm -v <work>/poc:/w -w /w php:7.4-cli php -d phar.readonly=0 build.php

   期待: `<work>/poc/evo.phar` ができる（約 100KB）。

3. phar をアプリのコンテナの `/tmp` に置き、サイトの外のカレントから動かす。

       docker cp <work>/poc/evo.phar <名前>-app:/tmp/evo.phar
       docker exec -w /tmp <名前>-app php /tmp/evo.phar --path=/var/www/html health:check
       docker exec -w /tmp <名前>-app php /tmp/evo.phar --path=/var/www/html skill:status

   期待: 手順 1 の各コマンドの出力が、`--path` 付き・カレント `/tmp` でも、記録と同じになる（時刻・件数・ファイル名の日時のように、実行ごとに変わる値を除く）。`skill:status` で、phar の中の `__DIR__ . '/../skill-lib.php'` が読める。名前を変えた phar（`cp /tmp/evo.phar /tmp/x.phar`）でも動く。

4. PHP 7.4 で動かす。検証環境のネットワーク名は `docker network ls` で確かめる（`<名前>` を含むもの）。

       docker run --rm --network <ネットワーク> -v "$PWD":/var/www/html -v <work>/poc:/opt/evo php:7.4-cli sh -c 'docker-php-ext-install mysqli >/dev/null 2>&1 && php /opt/evo/evo.phar --path=/var/www/html health:check'

   期待: `PHP version` の行が 7.4.x で、DB の接続が OK。

5. 分かったこと（カレントに頼る本体の処理が見つかった、など）を、この計画の Surprises & Discoveries に書く。カレントに頼る処理が見つかったら、`chdir()` で隠さず、その処理を `MODX_BASE_PATH` 基準に直す手順を M2 に足す。

### M1: ソースを `cli/` へ移し、配布 zip から外す

編集対象: `evo` → `cli/evo`、`manager/includes/cli/*` → `cli/`、`.gitattributes`、上の表の「開発の手順」の各ファイル。

1. 移す（`git mv` は移し先のディレクトリを作らないので、先に作る）。

       mkdir -p cli/dev/commands
       git mv evo cli/evo
       git mv manager/includes/cli/bootstrap.php cli/bootstrap.php
       git mv manager/includes/cli/cli-helpers.php cli/cli-helpers.php
       git mv manager/includes/cli/README.md cli/README.md
       git mv manager/includes/cli/commands cli/commands
       git mv manager/includes/cli/skill-lib.php cli/dev/skill-lib.php
       git mv cli/commands/skill-archive.php cli/dev/commands/skill-archive.php

   `skill-complete`・`skill-init`・`skill-prune`・`skill-status`・`skill-sync`・`skill-validate`・`make-command`・`make-migration` も同じく `cli/dev/commands/` へ移す。`skill-*.php` の `__DIR__ . '/../skill-lib.php'` は、移したあとも `cli/dev/skill-lib.php` を指すので直さない。

2. `cli/evo` の bootstrap の場所を `__DIR__ . '/bootstrap.php'` にする。`cli/bootstrap.php` のサイトのルートを `dirname(__DIR__) . '/'` にする。`EVO_CLI_DEV_COMMANDS_PATH`（`EVO_CLI_PATH . 'dev/commands/'`）を定義する。`cli/evo` の振り分けで、`EVO_CLI_COMMANDS_PATH` に無ければ `EVO_CLI_DEV_COMMANDS_PATH` を探すようにする。`help.php` は両方の一覧を出し、開発用には `(dev)` を付ける。`make:command` は今までどおり `EVO_CLI_COMMANDS_PATH`（`cli/commands/`）に作る。

3. `.gitattributes` の export-ignore の並びに `/cli/ export-ignore` を足す（先頭の `/` で、ルートの `cli/` だけに限る）。

4. 上の表の「開発の手順」の各ファイルで、`php evo` → `php cli/evo`、`manager/includes/cli/` → `cli/`（`skill:*`・`skill-lib.php`・`make:*` を指すものは `cli/dev/`）に置き換える。機械的に置換したあと、1 行ずつ目で確かめる。`AGENTS.md` の「`php evo` はホストから…」は、「CLI はホストから `docker compose exec <app-service> php cli/evo ...` で実行する（配布する `evo.phar` の作りと使い方は `cli/README.md`）」にする。`cli/README.md` の中の `cli` のパスもここで直す（本文の書き直しは M4）。置換をもう一度流しても、`php cli/evo` は `php evo ` に当たらないので二重にはならない。

5. 確かめる。

       docker exec -w /var/www/html <名前>-app php cli/evo help
       docker exec -w /var/www/html <名前>-app php cli/evo health:check
       git archive --format=zip HEAD -o <work>/m1.zip
       unzip -Z1 <work>/m1.zip | grep -E '^(evo$|cli/|manager/includes/cli/)'
       stat -c %s <work>/m1.zip

   期待: `help` に今の 27 のコマンドが出て、開発用のものに `(dev)` が付く。M0 の手順 1 の各コマンドを `php cli/evo` で流すと、記録と同じ出力になる。`unzip` の `grep` は何も出さない。zip は、main より約 40KB 小さい（値を Artifacts and Notes に書く）。`php evo` は `Could not open input file: evo` になる。
   `git archive` は、コミットしたものを固める。コミットしてから実行する。

### M2: サイトの外から動く起動処理と phar

編集対象: `cli/evo`、`cli/bootstrap.php`、`cli/cli-helpers.php`、`cli/commands/*.php`（使い方の表示）、`cli/commands/help.php`（削除）、`cli/commands/health-check.php`、`cli/build-phar.php`（新規）。

1. `cli/cli-helpers.php` に、次の 2 つを足す。
   - `cli_read_version(string $root): string`: `<root>/manager/includes/version.inc.php` を静的なクロージャの中で `include` し、`$modx_version` を返す。無ければ `''`。
   - `cli_find_site_root(string $start): ?string`: `$start` から親へたどる。`define-path.php` と `manager/includes/version.inc.php` が両方ある最初のディレクトリを、`realpath()` して末尾 `/` 付きで返す。ファイルシステムの根まで無ければ `null`。

2. `cli/bootstrap.php` を、Plan of Work の 1〜8 の順に書き直す。
   - `EVO_CLI_START` は、どこからも使われていないので定義しない。
   - CLI の版: phar のとき（`strpos(__DIR__, 'phar://') === 0`）は `require __DIR__ . '/build.php'` の `version` と `commit`。ソースのときは `cli_read_version(dirname(__DIR__))` と `source`。
   - `--path=` の扱い: 値が空・`=` なし・2 回は、`Usage` を出して終了コード 1。指したディレクトリにサイトの印が無ければ、`Not an Evolution CMS site: <path> (define-path.php and manager/includes/version.inc.php are required)` で終了コード 1。`--path` が無くて見つからないときは、`Site not found. Run inside the site folder, or pass --path=<site root>.` で終了コード 1（`help` と `--version` を除く）。
   - 版の比べ方と、合わないときの表示は Decision Log のとおり。ソースから動かしているときは URL の行を出さず、`The site at <path> is <版>, but this source tree is <版>.` だけにする。
   - 本体の起動は、今の処理をそのまま使う。サイトの `define-path.php` を `require_once` する。拡張の確認の文言の `Run \`php evo\` inside the app container.` は、`EVO_CLI_COMMAND` を使った形にする（ソースのときだけ、Docker の中で動かす案内を添える）。
   - コマンドの探し方は、M1 の振り分けを移す。`help` の一覧（M1 の `help.php` の内容に、`Usage` の 2 行と `Site:` の行を足したもの）も、ここに移す。

3. `cli/evo` は、`#!/usr/bin/env php` と `require __DIR__ . '/bootstrap.php';` だけにする。

4. `cli/commands/*.php` と `cli/dev/commands/*.php` の `Usage: php evo ...` を、`'Usage: ' . EVO_CLI_COMMAND . ' ...'` にする（27 か所。`grep -rn "php evo" cli/` が 0 件になること）。

5. `cli/commands/health-check.php` に 2 行を足す。
   - `info('Site path', MODX_BASE_PATH)`
   - `check('Legacy CLI files removed', <無い>, 'remove ' . <残っているもの> . ' from the site root')`。残っているかは、`MODX_BASE_PATH . 'evo'` が `is_file`、`MODX_BASE_PATH . 'manager/includes/cli'` が `is_dir` で判定する。

6. `cli/build-phar.php` を書く（Decision Log の決まりのとおり）。CLI 以外や `phar.readonly` が有効なときは、`Run: php -d phar.readonly=0 cli/build-phar.php --output=<path>` を出して終了コード 1。版は `cli_read_version(<リポジトリのルート>)` で読み、空なら止める。成功したら `Built: <出力先> (version <版>, commit <コミット>, <件数> files, <バイト> bytes)` を出す。

7. 確かめる（phar は `temp/` に置く。`temp/` は `.gitignore` 済みで、`temp/.htaccess` で Web から閉じている）。

       docker run --rm -v "$PWD":/src -w /src php:7.4-cli php -d phar.readonly=0 cli/build-phar.php --output=temp/evo.phar --commit=$(git rev-parse --short=9 HEAD)
       docker exec -w /tmp <名前>-app cp /var/www/html/temp/evo.phar /tmp/evo.phar
       docker exec -w /tmp <名前>-app php /tmp/evo.phar --version
       docker exec -w /tmp <名前>-app php /tmp/evo.phar help
       docker exec -w /tmp <名前>-app php /tmp/evo.phar health:check
       docker exec -w /tmp <名前>-app php /tmp/evo.phar --path=/var/www/html health:check
       docker exec -w /tmp <名前>-app php /tmp/evo.phar health:check --path=/var/www/html
       docker exec -w /var/www/html/assets/templates <名前>-app php /tmp/evo.phar health:check
       docker exec -w /tmp <名前>-app php /tmp/evo.phar --path=/var/www/html skill:status

   期待（Validation and Acceptance の V3〜V7 の表のとおり）: 次のとおりになる。
   - `--version` は `evo.phar 1.3.0rc2 (commit <9 桁>)` と `Site: not found` を出す。
   - `help` は、サイトが無くても終了コード 0 で一覧を出し、`skill:`・`make:` を含まない。
   - `--path` が無い `health:check` は、`Site not found...` で終了コード 1。
   - `--path` を前に書いても後ろに書いても、サイトの中のサブディレクトリから動かしても、同じ結果になる。結果は M0 の記録に `Site path` と `Legacy CLI files removed` の行が増えた形。
   - `skill:status` は `Unknown command: skill:status` で終了コード 1。

8. 版が合わないときと、古い残りを確かめる。

       docker exec <名前>-app sh -c "mkdir -p /tmp/oldsite/manager/includes /tmp/oldsite/temp && cp /var/www/html/define-path.php /tmp/oldsite/ && echo '<?php \$modx_version = \"1.2.4J\";' > /tmp/oldsite/manager/includes/version.inc.php"
       docker exec -w /tmp <名前>-app php /tmp/evo.phar --path=/tmp/oldsite health:check
       docker exec <名前>-app touch /var/www/html/evo
       docker exec -w /tmp <名前>-app php /tmp/evo.phar --path=/var/www/html health:check
       docker exec <名前>-app rm /var/www/html/evo

   期待: 1 つ目の `health:check` は、`Version mismatch: evo.phar is 1.3.0rc2, but the site at /tmp/oldsite/ is 1.2.4J.` と URL の 2 行を出し、終了コード 1。`/tmp/oldsite` には本体が無いので、本体を読み込もうとしていれば `Failed opening` などの別のエラーになる。この表示になれば、読み込む前に止まっている。2 つ目の `health:check` は `[FAIL] Legacy CLI files removed -- remove evo from the site root` を出す。

### M3: CI とリリース

編集対象: `.github/workflows/release.yml`、`.github/workflows/cli-build.yml`（新規）、`assets/docs/release-process.md`、`.claude/skills/release/SKILL.md`。

1. `release.yml` の zip を作る段のあとに、`Build evo.phar` の段を足す。中身は次の順。
   - `git rev-parse HEAD` と `git rev-parse "refs/tags/${GITHUB_REF_NAME}^{commit}"` が同じことを確かめる（違えば失敗）。
   - `docker run --rm -v "$PWD":/src -w /src php:7.4-cli php -d phar.readonly=0 cli/build-phar.php --output=evo.phar --commit="$(git rev-parse --short=9 HEAD)"` で組み立てる。
   - `docker run ... php evo.phar --version` の 1 行目が `evo.phar ${GITHUB_REF_NAME#release-} ` で始まることを確かめる。
   - `docker run ... php evo.phar help` に `skill:` が無いことを確かめる。
   大きさを報告する段には、`evo.phar` のバイト数の行を足す。`Create GitHub Release` の `files:` は、複数行で `evo-${{ github.ref_name }}.zip` と `evo.phar` にする。

2. `cli-build.yml` を作る。
   - 動かす場面: `pull_request` と `push`（main）。`paths` は `cli/**`・`manager/includes/version.inc.php`・`.github/workflows/cli-build.yml`。`permissions: contents: read`。
   - 中身: 上の組み立てと 2 つの確かめ（`--version` の版は、`manager/includes/version.inc.php` の版と比べる）。最後に `actions/upload-artifact@v4` で `evo.phar` を残す（`retention-days: 7`）。

3. `assets/docs/release-process.md` を直す。
   - 「GitHub Actions の自動実行」の処理内容に、phar の組み立てと添付を足す。
   - 「ドラフト Release とZIPの確認」で、添付が zip と `evo.phar` の 2 つであることを確かめる。`gh release download "${TAG}" --pattern evo.phar` で取り、`php evo.phar --version` の版が `${VERSION}` と同じことを確かめる手順を足す。
   - 配布対象外のパスの正規表現に、ルートの `cli/` と、ルートの `evo`・`manager/includes/cli/` を足す。
   - 「除外ファイル一覧」に `/cli/` を足す。
   - 「リリースノートの構成」の「アップグレード時の注意」の説明に、次の文を足す。「サイトのルートに `evo` と `manager/includes/cli/` が残っていれば消す旨と、CLI を使う人は同じ版の `evo.phar` に替える旨は、1.3 系のリリースでは必ず書く」。

4. `.claude/skills/release/SKILL.md` の、ワークフローの確認（`rg -q --fixed-strings 'files: evo-${{ github.ref_name }}.zip'`）を、新しい `files:` の 2 行と、`cli/build-phar.php` を呼んでいることの確認に替える。開始前チェック 5 の文（「想定したZIP添付」）を、「zip と evo.phar の添付」にする。

5. 確かめる。
   - `release.yml` の `Build evo.phar` の `run` の中身を、`GITHUB_REF_NAME=release-$(sed -n "s/^\$modx_version = '\(.*\)';.*/\1/p" manager/includes/version.inc.php)` を付けて、手元の bash で流す。タグの一致の確認は、手元では、そのタグを作らずに飛ばす。
   - PR を出し、`cli-build.yml` の実行が成功して、成果物の `evo.phar` を取れること（`gh run download`）。
   - タグを打つリリースでの確かめは、最初のリリースのドラフトの確認（手順書の手順 5）で行う。ドラフトは公開されないので、問題があれば公開の前に直せる。

### M4: 利用者向けの文言と文書

編集対象: `manager/includes/lang/english.inc.php`・`japanese-utf8.inc.php`、`manager/includes/migrations/00000000_baseline.php`、`cli/README.md`、`assets/docs/migrations.md`・`site-url.md`・`events-and-plugins.md`、`.agent/roadmap.md`（#668・#485 の項目）、`.agent/plans/2026-05-06-evo-system-upgrade-command.md`。

1. `bkmgr_restore_msg` を書き換える。英語は `To run other SQL statements, use the CLI (<code>php evo.phar db:query</code>) or a database administration tool.`、日本語は `任意のSQL文を実行するときは、CLI（<code>php evo.phar db:query</code>）やデータベース管理ツールを使ってください。`。baseline のエラー文は `for example with php evo.phar db:query` にする。ほかの言語ファイルの `bkmgr_restore_msg` には `php evo` が無い（`grep -n "php evo" manager/includes/lang/*.php` が 2 件だけ）。

2. `cli/README.md` を、次の節の順に書き直す。
   1. 何か（配布 zip に入らず、リリースに添付する `evo.phar` で配る）
   2. 使えるようにする（同じ版のリリースのページから `evo.phar` を取る。サイトの公開ディレクトリの外に置く。要るもの: PHP 7.4 以上の CLI と、拡張 `phar`・`mysqli`・`mbstring`。`php evo.phar --version` で確かめる）
   3. 対象のサイトの決まり（`--path=` と、カレントから親へ探すこと）
   4. 版の決まり（合わないと止まり、合う evo.phar の URL を出す。1.2 系には CLI が無い）
   5. アップデートしたとき（evo.phar も同じ版に替える。ルートの `evo` と `manager/includes/cli/` を消す。`health:check` の `Legacy CLI files removed` で分かる）
   6. コマンドの一覧と注意（今の内容。例は `php evo.phar`）
   7. 開発（`php cli/evo` と Docker の呼び方。開発用のコマンドは `cli/dev/commands/` にあり phar に入らないこと。phar の組み立て方。追加したコマンドの場所。`skill:*` の運用メモ）

3. `assets/docs/migrations.md` を直す。利用者の操作の `php evo` は `php evo.phar` にする。`make:migration` の例は `php cli/evo` にし、CLI の README への参照は `cli/README.md` にする。「利用者がアップデートするとき」の手順 1 に、次の文を足す。「サイトのルートに `evo` と `manager/includes/cli/` が残っていれば消す。上書きで差し替えると残り、古いコマンドのまま動き続ける。CLI を使うなら、同じ版の `evo.phar` に替える」。`site-url.md` と `events-and-plugins.md` の `php evo` は `php evo.phar` にする。

4. `.agent/roadmap.md` の #668（`php evo system:upgrade`）と #485（`php evo install`・`php evo upgrade`）の項目の文を、`php evo.phar` の形にする。#668 の ExecPlan の Surprises & Discoveries に、次のことを日付付きで書き足す。「#1509 で CLI は `cli/` に移り、`evo.phar` で配るようになった。配布 zip に `evo` は無い。コマンドのファイルは `cli/commands/system-upgrade.php` に作り、phar の中から動く（`manager/` を入れ替えても、実行中のコマンドのファイルは消えない）。版の確かめで、新しい evo.phar から古いサイトに対して動かせるようにする例外が要る」。

5. 確かめる。

       git grep -nE 'php evo([^.]|$)' -- ':!.agent/plans/archive/' ':!.agent/roadmap-archive.md' ':(exclude,glob).agent/runs/*/**' ':!.agent/plans/2026-11-16-cli-phar-distribution.md' ':!.agent/plans/2026-05-06-evo-system-upgrade-command.md'
       git grep -n 'manager/includes/cli' -- ':!.agent/plans/archive/' ':!.agent/roadmap-archive.md' ':(exclude,glob).agent/runs/*/**'

   期待: 1 つ目は何も出ない（#668 の ExecPlan は、本文を残して Surprises に読み替えを書くので除く）。2 つ目に出るのは、古い残りを案内・検出する箇所だけになる。具体的には `cli/commands/health-check.php`・`cli/README.md`・`assets/docs/migrations.md`・`assets/docs/release-process.md`・この計画・#668 の ExecPlan。
   管理画面のバックアップ（`index.php?a=93`）の「リストア」の説明に `php evo.phar db:query` と出ることを、adaptive-validation-runner の手順（管理者でログインして開く）で確かめる。

### M5: 横断の確認

1. PHP 7.4 で、ソースからの入口と phar の両方を動かす（M0 の手順 4 と同じネットワーク・マウントで、`php cli/evo health:check` と `php temp/evo.phar --path=/var/www/html health:check`）。
2. `git archive --format=zip HEAD | wc -c` を main と比べ、差を Artifacts and Notes と PR の本文に書く。
3. `/doc-audit` を、変えた文書に流す。
4. この計画の Progress・Surprises・Outcomes を更新する。

## Validation and Acceptance

| # | 場面 | 操作 | 期待（観察できる結果） |
| --- | --- | --- | --- |
| V1 | 配布 zip | `git archive --format=zip HEAD -o x.zip; unzip -Z1 x.zip` | `evo`・`cli/`・`manager/includes/cli/` が無い。大きさは main より約 40KB 小さい |
| V2 | 開発の入口（今の経路） | 検証環境で `php cli/evo <コマンド>`（M0 の手順 1 の 10 個） | main の `php evo` の記録と同じ出力・終了コード（実行ごとに変わる値と、`health:check` で増えた 2 行を除く） |
| V3 | phar をサイトの外から | `/tmp` で `php /tmp/evo.phar --path=/var/www/html health:check` | V2 と同じ結果 |
| V4 | `--path` の位置とサイトの探し方 | `--path` をコマンドの後ろに書く。`assets/templates/` から `--path` なしで動かす | V3 と同じ結果 |
| V5 | サイトが無い | `/tmp` で `php /tmp/evo.phar health:check` / `help` / `--version` | 1 つ目は `Site not found...` で終了コード 1。`help` は一覧を出して 0。`--version` は版と `Site: not found` を出して 0 |
| V6 | 版が合わない | 版が `1.2.4J` の偽のサイトに `--path` で向ける | `Version mismatch: ...` と、`release-1.2.4J/evo.phar` の URL を出して終了コード 1。本体の読み込みのエラーは出ない |
| V7 | 開発用のコマンド | phar の `help`、`php evo.phar skill:status` | 一覧に `skill:`・`make:` が無い。実行は `Unknown command` で 1。`php cli/evo help` には `(dev)` 付きで出る |
| V8 | 名前を変えた phar | `cp evo.phar evo-site.phar; php evo-site.phar --path=/var/www/html db:describe` | 使い方の表示が `Usage: php evo-site.phar db:describe table_name` になる |
| V9 | 古い CLI の残り | サイトのルートに `evo` を作って `health:check` | `[FAIL] Legacy CLI files removed -- remove evo from the site root`。消すと `[OK  ]` |
| V10 | PHP 7.4 | `php:7.4-cli`（mysqli を足したもの）で、ソースからと phar の `health:check` | `PHP version` が 7.4.x で、DB の接続が OK。組み立ても 7.4 で通る |
| V11 | CI（PR） | `cli/` を変えた PR | `cli-build.yml` が成功し、成果物の `evo.phar` を取れる |
| V12 | リリース | 最初のリリースのドラフト | 添付が `evo-release-<版>.zip` と `evo.phar` の 2 つ。取った `evo.phar` の `--version` がその版 |
| V13 | 利用者向けの文言 | 管理画面のバックアップのリストアの説明、文書の `git grep` | `php evo.phar db:query` と出る。`php evo`（`.phar` なし）の残りは、変えない記録だけ |

## Idempotence and Recovery

- M0 は作業用ディレクトリだけを使い、作業ツリーを変えない。やり直すときは `<work>/poc` を消して組み直す。
- M1 の `git mv` は、途中で止まったら `git status` で移った分を確かめて残りを続ける。全部戻すときは `git restore --staged --worktree -- evo manager/includes/cli cli`。置換は、上のとおりもう一度流しても二重にならない。
- `cli/build-phar.php` は一時ファイルに書いてから置き換えるので、何度流してもよく、失敗しても前の phar は壊れない。`temp/evo.phar` は `.gitignore` 済みなので、コミットには混ざらない。
- M2 の手順 8 で作った `/tmp/oldsite` と `/var/www/html/evo` は、その場で消す。消し忘れた `evo` は、`health:check` の FAIL で分かる。
- 検証環境は `environment.md` の手順で止める・作り直す（`down -v` は、自分の環境の名前を付けたときだけ）。
- M3 のワークフローの不具合でリリースの組み立てが失敗したときは、`assets/docs/release-process.md` の「GitHub Actions が失敗した場合」に従って、タグを消して打ち直す。ドラフトのまま公開していなければ、利用者には届いていない。

## Artifacts and Notes

- Issue: https://github.com/modxcms-jp/evolution-jp/issues/1509
- ブランチ: 実装の指示を受けたら `gh issue develop 1509` で Issue に紐づけて作る（例: `feat/1509-cli-phar`）
- 想定するコミット:
  - M1: `refactor(cli): CLI のソースを cli/ に移し、配布 zip から外す` / `docs: 開発の手順の CLI の呼び方を php cli/evo に改める`
  - M2: `feat(cli): 対象のサイトを --path とカレントから決め、版が合わなければ止める` / `feat(cli): evo.phar を組み立てるスクリプトを足す` / `feat(cli): health:check でサイトのルートに残った古い CLI を知らせる`
  - M3: `ci(release): リリースで evo.phar を組み立てて添付する` / `ci: CLI を変えた PR で evo.phar の組み立てを確かめる` / `docs(release): リリースの手順に evo.phar の確認を足す`
  - M4: `refactor(cli): 画面とエラーの CLI の案内を evo.phar に改める` / `docs(cli): CLI の説明と文書を evo.phar の使い方に書き換える`
- zip の大きさ（`git archive --format=zip <rev> | wc -c`）: main 92099d4f6 は 1,652,971 バイト。M1 のあとの実測値をここに書く。
- 最初のリリースのリリースノートの「アップグレード時の注意」に入れる文の案:
  CLI（`php evo`）は配布 zip から外し、リリースに添付する `evo.phar` で配るようにしました。`evo.phar` は、サイトの公開ディレクトリの外に置き、`php evo.phar --path=/path/to/site health:check` のように使います。サイトと同じ版の `evo.phar` を使ってください（版が違うと止まり、合う版の場所を表示します）。アップデートのあと、サイトのルートの `evo` と `manager/includes/cli/` は使わなくなります。上書きでアップデートすると残り、古いコマンドのまま動き続けるので、消してください。

## Interfaces and Dependencies

- CLI のコマンドのファイルとの約束（変えない）: コマンドのファイルはトップレベルで `require` される。`$args`（コマンド名と `--path=` を除いた引数の配列）、`$modx`、`evo()`・`db()`、`cli-helpers.php` の関数が使える。成功は終了コード 0、失敗は 1 以上。`EVO_CLI_PATH`・`EVO_CLI_COMMANDS_PATH` は残る。新しく `EVO_CLI_DEV_COMMANDS_PATH`（ソースのときだけ中身がある）と `EVO_CLI_COMMAND` を足す。`--path` は起動処理が予約する。
- phar の中身: `bootstrap.php`・`cli-helpers.php`・`commands/*.php`・`build.php`。別名は `evo.phar`。
- 本体との境目: CLI が読む本体のファイルは、サイトの `define-path.php`、`autoload.php`（あれば）、`manager/includes/version.inc.php`、`manager/includes/document.parser.class.inc.php` と、各コマンドが読むクラス（`migrator.class.inc.php`・`backup_manager.class.inc.php`・`mysql_dumper.class.inc.php`・`system_log.viewer.inc.php`・`site_export_job.class.inc.php`・`log.class.inc.php`）。本体の側は CLI を参照しない（今と同じ）。
- 利用者の環境の前提: PHP 7.4 以上の CLI と、拡張 `phar`（PHP の標準の構成で有効）・`mysqli`・`mbstring`。`db:console`・`db:export` の既定・`db:import` は、今までどおり `mysql`・`mysqldump` のコマンドが要る。
- GitHub Actions: `actions/checkout@v4`・`softprops/action-gh-release@v2`（既存）、`actions/upload-artifact@v4`（新規、`cli-build.yml` だけ）、ランナーの Docker と公式イメージ `php:7.4-cli`。
- 「evo system-upgrade コマンド実装」（#668）: コマンドは evo.phar の中（`cli/commands/system-upgrade.php`）に作ることになる。新しい evo.phar を、古い版のサイトに対して動かす前提のコマンドなので、着手するときに、版の確かめの例外（たとえば、コマンドのファイルが「版が違っても動く」と宣言できる形）を `cli/bootstrap.php` に足す。例外の決め方は #668 の計画で決める。展開した zip の直下の一覧から `evo` が無くなることも、その計画に反映する。
- 「CLI インストーラとインストール処理の共通化」（#485）: `install` は、設定（`config.inc.php`）の無いサイトに対して動くので、本体の起動を飛ばす形がコマンドの側に要る。この計画の起動処理（版の確かめまでは本体を読まない）の上に足せる。
- 1.2 系: 取り込まない。1.2 系には CLI が無い。
