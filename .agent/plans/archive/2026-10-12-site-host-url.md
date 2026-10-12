# ExecPlan: サイトのホストの設定と、サイト URL のホスト・ポートの決め方（#515）

## Purpose / Big Picture

サイトを 80 番（HTTPS では 443 番）以外のポートで動かしても、リンクやリダイレクトの行き先が正しいポートになるようにする。あわせて、サイトの URL を組み立てるときに、ブラウザなどのクライアントが送ってくるホスト名・ポートをそのまま使わず、サイト側で決めた「サイトのホスト」を使えるようにする。

利用者から見た変化は次のとおり。

- `http://localhost:8181/` のようにポート付きで動かしても、管理画面へのリダイレクトやメニューのリンクに `:8181` が付く
- サイトのホストを設定したサイトでは、どのホスト名・ポートでアクセスされても、サイトの URL は設定したホストのものになる
- 新規インストールでは、インストールしたときのホスト（ポート込み）が、自動でサイトのホストに入る
- 設定していない既存のサイトは、今と同じ動きのまま（システム設定の画面に、設定をすすめる案内が出る）

## Progress

- [x] (2026-10-12) マイルストーン 1：`init::site_host()` と `get_site_url()` の判定を追加。単体 12 ケース、未設定時は main と同じ URL を確認
- [x] (2026-10-12) マイルストーン 2：システム設定 `site_host` を追加（保存時に正規化）。`basicConfig.php` に書き出し、表側の URL・`.env` の優先・今のホストと違うときの注意を Playwright で確認
- [x] (2026-10-12) マイルストーン 3：新規インストールで自動設定。80 番・8181 番の新規インストール（古いキャッシュが残った入れ直しを含む）〜スモークテスト 6/6、別のホスト名でアクセスしても URL が変わらないことを確認
- [x] (2026-10-12) マイルストーン 4：`assets/docs/site-host.md` を追加し、ドキュメントマップと検証スキルの説明（`environment.md`・`compose.validation.yml`）を更新。戻し方（DB で空にして `php evo cache:clear`）を検証環境で確認

## Surprises & Discoveries

- 2026-10-12：`get_host_name()` は `SERVER_NAME` を優先する。Apache の `SERVER_NAME` にはポートが含まれないため、80 番以外ではポートが落ちる（#515 の原因）。
- 2026-10-12：Apache の既定の設定（`UseCanonicalName Off`）では、`SERVER_NAME` と `SERVER_PORT` も、クライアントが送った Host ヘッダーをもとに決まる。バーチャルホストで `ServerName` を固定していない環境では、どちらもサイト側で決めた値とは限らない。
- 2026-10-12：`HTTP_HOST` のポートをそのまま付ける修正を試したが、同じ理由で採用しない。ポートも、サイト側で決めた設定から取る必要がある。
- 2026-10-12：システム設定には `site_url` があり、固定の URL を入れると `cache_sync.class.php` が `basicConfig.php` に `$site_url` として書き出す。ただし、その変数はどこからも使われておらず、`MODX_SITE_URL` は毎回自動で決まる（`DocumentParser::getSettings()` が `config['site_url']` を `MODX_SITE_URL` で上書きする）。
- 2026-10-12：`temp/cache/basicConfig.php` は入れ直しても残る。インストーラが古い `$site_host` を読むと、インストーラの URL が古いホストに向いて進めなくなった。インストール中（インストーラの中、または `config.inc.php` が無いとき）は `basicConfig.php` の値を読まず、アクセスされたホストを使うようにした。
- 2026-10-12：管理画面の HTML の URL はほぼ相対パスのため、今開いているホストと違うサイトのホストを保存しても、管理画面はそのまま使えた。
- 2026-10-12：管理画面は `Accept-Language` ヘッダーの無いリクエストに 404 を返す（既存の仕様。`accesscontrol.inc.php`）。curl で確かめるときは付ける。

## Decision Log

- 2026-10-12（Claude・利用者の確認済み）：ポートは `SERVER_PORT` からは取らない。リバースプロキシの内側では内部のポート（例：8080）になり、表のポートと食い違うため。
- 2026-10-12（同）：設定は、`.env`（環境変数）の `SITE_HOST` と、システム設定の `site_host` の両方でできるようにする。`.env` を優先する。サーバー管理者は `.env`、非エンジニアの利用者は管理画面で設定できるようにするため。代替案として、`.env` だけ（実装は最小だが管理画面から変えられない）と、システム設定だけ（設定を誤ると戻しにくい）を検討した。
- 2026-10-12（同）：設定していない既存のサイトは、今と同じ動きにする（互換性を保つ）。新規インストールでは、インストールしたときのホストを自動で設定し、未設定のサイトには管理画面で案内を出す。代替案の「未設定ならホストが食い違うときはキャッシュしない」は、キャッシュの仕組みに手が入り重いので見送った。
- 2026-10-12（同）：クライアントが送るホスト名・ポートをそのまま使わない点は、この計画の一部として公開の Issue・PR で扱う。コミット・PR・ドキュメントには、悪用の手順を書かない。
- 2026-10-12（利用者）：はじめは複数のホストを許す「信頼するホスト」の一覧として作ったが、正式なホスト 1 つだけを設定する形に改めた。設定が分かりやすく、サイトの URL が常に 1 つに決まり、ページのキャッシュに残る URL も 1 つになるため。一覧の形で入れていた「保存時に今のホストを自動で加える」締め出し対策はなくなるので、代わりに、今開いているホストと違う値を保存したときに注意を出し、戻し方（`.env` の `SITE_HOST`）を設定の説明に書いた。2013 年に、固定したサイトの URL を誤るとログインできなくなるため `site_url` の設定を効かなくした経緯がある（843d21710）。
- 2026-10-12（Claude）：形式の正しくない値を保存したときは、空（自動）として保存する。誤った値で URL を作らないため。

## Outcomes & Retrospective

- 2026-10-12：完了。80 番以外のポートでも新規インストールからスモークテストまで通り、サイトのホストを設定したサイトでは、別のホスト名でアクセスされてもサイトの URL が変わらなくなった。設定していない既存のサイトは今と同じ動き。
- はじめに複数のホストを許す一覧の形で作り、途中で利用者の判断により 1 つの形に改めた。一覧の形のために入れていた「先頭の意味」や「保存時の自動追加」が不要になり、設定・説明とも単純になった。設計の段階で「本当に複数が要るか」を利用者に確かめておけば、作り直しは避けられた。
- 検証環境の `temp/cache/` が入れ直しても残ることで、インストール中の扱いの漏れが見つかった。新規インストールの確認は、きれいな状態だけでなく、前のキャッシュが残った状態でも行うとよい。

## Context and Orientation

用語:

- サイトの URL：`MODX_SITE_URL`（例：`http://example.com/`）。テンプレートの `[(site_url)]` やリンク、リダイレクトの行き先に使う。
- Host ヘッダー：ブラウザが「どのホスト名・ポートにアクセスしたか」をサーバーに伝える値。PHP では `$_SERVER['HTTP_HOST']`。クライアントが自由に書ける。
- サイトのホスト：サイト側で決めた正式なホスト名（必要ならポート付き）。この計画で追加する。
- `basicConfig.php`：システム設定の一部を書き出したキャッシュファイル（`temp/cache/basicConfig.php`）。

関係するコード:

- `manager/includes/initialize.functions.inc.php`：`init::get_host_name()`・`init::get_site_url()`・`init::is_ssl()`。サイトの URL を組み立てる。
- `manager/includes/initialize.inc.php`：`.env` を読み込んだあとで `MODX_SITE_URL` を定義する（`define-path.php` で定義済みならそれを使う）。
- `manager/includes/cache_sync.class.php`：`basicConfig.php` を書き出す。
- `manager/includes/default.config.php`・`manager/actions/tool/mutate_settings/tab1_site_settings.inc.php`・`manager/includes/lang/*.inc.php`・`manager/processors/save_settings.processor.php`：システム設定の追加先（手順は `assets/docs/global-settings.md`）。
- `install/instprocessor.php`：新規インストールの処理。

前提: リポジトリのルートで作業する。検証は `.claude/skills/adaptive-validation-runner/` の Docker 環境で行う（並走時は `EVO_VALIDATE_NAME` と `EVO_BIND_IP` を付ける）。

## Plan of Work

判定は `init::get_site_url()` の 1 か所に集める。`define-path.php` で `MODX_SITE_URL` が定義されていれば、今までどおりそれを使い、判定は通らない。

サイトのホストは、`.env` の `SITE_HOST`、無ければ `basicConfig.php` の `$site_host` から読む（`init::site_host()`）。書式は `ホスト名[:ポート]`（例：`example.com`、`localhost:8181`）。小文字にそろえ、既定のポートは省く。`basicConfig.php` は入口によって初期化より後に読まれるため、ファイルの該当行だけを直接読む。

サイトのホストがあれば、常にそのホストで URL を作る。スキーム（http / https）は今までどおり `init::is_ssl()` で決める。無ければ、インストール中はアクセスされたホスト（ポート込み）、それ以外は今の `get_host_name()` の動きをそのまま使う。

管理画面のシステム設定に「サイトのホスト」を加え、保存すると `basicConfig.php` に書き出されるようにする。新規インストールでは、インストールしたときの Host ヘッダーの値を登録する（`.env` で決めている場合は登録しない）。システム設定の画面には、未設定のときは設定をすすめる案内を、今開いているホストと違うときは注意を出す。

## Concrete Steps

マイルストーン 1（判定と `.env`）

- 編集：`manager/includes/initialize.functions.inc.php`。`init::site_host()`・`normalize_host()`・`request_host()`・`is_installing()` と、`init::get_site_url()` の判定を追加する。
- 実行：`$_SERVER`・`SITE_HOST`・`$site_host` を差し替えて `init::get_site_url('/')` を呼ぶ PHP スクリプトを、作業用ディレクトリ（リポジトリの外）に作り、`docker run --rm -v <repo>:/repo:ro php:8.4-apache php <script> /repo` で実行する。
- 期待：`SITE_HOST=localhost:8181` なら `http://localhost:8181/`。別のホストでアクセスしても設定したホスト。未設定なら今と同じ結果。

マイルストーン 2（システム設定と `basicConfig.php`）

- 編集：`default.config.php`（`site_host` を空で追加）、言語ファイル、`tab1_site_settings.inc.php`（テキスト入力と案内）、`save_settings.processor.php`（正規化）、`cache_sync.class.php`（値があれば `$site_host = '...';` を `var_export` で書き出す）。
- 実行：検証環境で、管理画面のシステム設定に値を入れて保存し、DB と `temp/cache/basicConfig.php` を確かめる（Playwright）。
- 期待：`Example.COM:80` は `example.com` として保存・書き出され、表側の `<base href>` が `http://example.com/` になる。形式の誤った値は空になり、未設定の案内が出る。

マイルストーン 3（新規インストール）

- 編集：`install/instprocessor.php` の新規インストールの分岐で、`site_host` をインストールしたときのホストで登録する。
- 実行：検証環境を 80 番と 8181 番（`EVO_HTTP_PORT=8181`）で立ち上げ、`setup-site.js --sample --editor` で新規インストールし、`modx_system_settings` の `site_host` を確かめる。続けて `smoke.js`。
- 期待：`127.0.0.2`・`127.0.0.2:8181` が入り、スモークテストが通る。アップデートでは値を足さない。

マイルストーン 4（ドキュメント）

- 編集：`assets/docs/` に、サイトのホストの説明（書式、`.env` とシステム設定のどちらが優先か、ポート付きで動かすときの書き方、設定を誤ったときの戻し方）を加える。`.claude/skills/adaptive-validation-runner/references/environment.md` と `compose.validation.yml` のコメントの「80 番以外のポートでは動かない」を直す。

## Validation and Acceptance

- 検証環境を 80 番以外のポートで公開して新規インストールすると、管理画面へのログインまで、リダイレクト先にポートが付いて最後まで進める。
- サイトのホストを設定したサイトでは、別のホスト名・ポートでアクセスされても、ページの `<base href>` とリンクは設定したホストのものになる。
- リバースプロキシの内側を想定した値（表は 443、内側は 8080、Host にポートなし）では、URL にポートが付かない。
- サイトのホストを設定していないサイトでは、URL が今と同じになる（今の `get_host_name()` の結果と同じ）。システム設定の画面には、設定をすすめる案内が出る。
- 新規インストールしたサイトでは、システム設定の「サイトのホスト」に、インストールしたときのホストが入っている。
- スモークテスト（`smoke.js`）が通る。

## Idempotence and Recovery

各マイルストーンは独立して試せ、同じ手順を繰り返しても結果は変わらない。設定を誤って管理画面に入れなくなった場合は、次のどちらかで戻せる。

- `.env` に正しい `SITE_HOST` を書く（システム設定より優先される）
- DB の `modx_system_settings` で `site_host` を空にしてから、`php evo cache:clear` で `basicConfig.php` を作り直す（空なら今までの動きに戻る）

検証環境は `down -v` で消せる。`temp/cache/` は残るが、インストール中は読まないので入れ直しに支障はない。

## Artifacts and Notes

想定するコミットの分け方（はじめの 2 コミットは「信頼するホスト」の一覧の形で作っていたため、push 前にまとめ直す）:

1. `feat: サイトのホストを設定し、サイトの URL のホスト・ポートを決める`（マイルストーン 1〜3）
2. `docs: サイトのホストの設定を説明する`（マイルストーン 4）

関連: Issue #515、#514（検証環境の並走の分け方）。

## Interfaces and Dependencies

- 環境変数 `SITE_HOST`（`.env` または サーバーの環境変数）。`manager/includes/dotenv-loader.php` が読み込む。
- システム設定 `site_host`。`basicConfig.php` の `$site_host` として書き出され、初期化のときに読まれる。
- `define-path.php` で `MODX_SITE_URL` を定義しているサイトは、この判定を通らない（今までどおり）。
