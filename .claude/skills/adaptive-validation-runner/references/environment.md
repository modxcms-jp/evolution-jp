# 検証環境（Docker）

改修した作業ツリーをそのままマウントして動かす、使い捨ての検証環境。
`compose.yml` に `scripts/compose.validation.yml` を重ね、プロジェクト名 `evo-validate` で起動する。
ほかの案件や既存の開発環境（プロジェクト名・コンテナ名・ボリューム）とは分けてある。

## 構成

| サービス | コンテナ名 | 公開ポート | 備考 |
| --- | --- | --- | --- |
| app | `evo-validate-app` | `${EVO_HTTP_PORT:-80}` → 8080 | php:8.4-apache。作業ツリーを `/var/www/html` にマウント |
| db | `evo-validate-db` | なし | mariadb:11（DB `evodb`、ユーザー `user` / `password`） |
| mailpit | `evo-validate-mailpit` | `${EVO_MAILPIT_PORT:-8025}` → 8025 | app の mail() は msmtp 経由で mailpit へ届く |

ポートが空いていないときは `EVO_HTTP_PORT` / `EVO_MAILPIT_PORT` を変える。スクリプト側も同じ環境変数を読む。
公開先は `127.0.0.1` に限定している（既定の管理者パスワードを使うため）。WSL2 では Windows 側のブラウザから `localhost` で開ける（localhostForwarding）。同じネットワーク上のほかの端末からはつながらない。
ほかの案件のコンテナを止めてよいかは、ユーザーに確認する。

## 起動からスモークテストまで

リポジトリ（作業ツリー）のルートで実行する。`<work>` は作業用ディレクトリ（リポジトリの外）。

```bash
# 1. 起動
docker compose -p evo-validate -f compose.yml \
  -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml up -d --build

# 2. Playwright（リポジトリには入れない）
mkdir -p <work>/pw && cd <work>/pw && npm init -y && npm install playwright@1.48.2 && npx playwright install chromium
cd -   # リポジトリのルートへ戻る

# 3. インストールと初期設定（サンプルサイトと権限の弱いユーザー付き）
#    DB が空なのに manager/includes/config.inc.php が残っていると止まる。検証用に作られたものなら --reinstall を付ける
NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/setup-site.js --sample --editor

# 4. スモークテスト
NODE_PATH=<work>/pw/node_modules node .claude/skills/adaptive-validation-runner/scripts/smoke.js
```

ログイン情報（既定。`EVO_ADMIN_USER` / `EVO_ADMIN_PASSWORD` で変えられる）:

- 管理画面: `http://localhost:${EVO_HTTP_PORT:-80}/manager/`（80 のときはポート番号を省略できる）
- 管理者: `admin` / `Passw0rd!2026`（ロール2 ウェブマスター）
- 権限の弱いユーザー（`--editor`）: `editor` / 管理者と同じパスワード（ロール3 投稿担当者、`save_role` なし）
- mailpit: `http://localhost:${EVO_MAILPIT_PORT:-8025}/`（API は `/api/v1/messages`）

## 設定を変えて確かめる

システム設定は DB を直接書き換え、キャッシュを作り直す（`docker exec -w /var/www/html evo-validate-app php evo cache:clear`、または管理画面の「サイトをリフレッシュ」。`lib.js` の `sql()` と `refreshCache()`）。

```bash
docker exec evo-validate-db mariadb -uuser -ppassword evodb -e \
  "REPLACE INTO modx_system_settings (setting_name,setting_value) VALUES ('email_method','smtp'),('smtp_host','mailpit'),('smtp_port','1025'),('smtp_auth','0'),('smtp_secure','')"
```

| 確かめたいこと | 設定 |
| --- | --- |
| SMTP 送信（アップデートしたサイトに多い） | 上の `email_method=smtp` 一式 |
| mail() 送信（クリーンインストールの既定） | `email_method=mail`、または行を消す |
| ドキュメントグループによる権限 | `use_udperms=1`、`udperms_allowroot=0` |
| PHP と DB のタイムゾーンのずれ | `SET GLOBAL time_zone='+13:00'`（root で実行。PHP は Asia/Tokyo）。終わったら `'SYSTEM'` に戻す |
| 編集ロック | `modx_active_users` に別ユーザーの行（`action='27'`、`id=<リソース>`、`lasthit=UNIX_TIMESTAMP()`）を入れる |

DB の `time_zone` は `+14:00` を受け付けない（MariaDB の上限は `+13:00`）。

## 既知の落とし穴

- **MySQL 公式イメージが取得できない**: Docker Engine 29 系では `failed to register layer: openat dev/ptmx: no such file or directory` で失敗する。
  MySQL 公式イメージ（8.3 / 8.4 とも）は Oracle Linux が土台で、最初のレイヤーにキャラクタデバイス `dev/ptmx`（5,2）を含む。
  ptmx のデバイスファイルを普通に開くと、カーネルは同じディレクトリの `pts`（devpts）を探して端末を割り当てようとし、見つからなければ ENOENT を返す。
  Docker が展開時に、作ったデバイスファイルを開き直しているためと見られる（`docker import` で、置き場所に関係なく 5,2 のデバイスだけが失敗し、`dev/null` などほかのデバイスや FIFO は成功することを確かめた）。
  MariaDB 公式イメージは Ubuntu が土台で、このデバイスを含まないため問題ない
- **インストール直後は表側が 503（メンテナンス画面）**: `settings_version` が `0` のままだと、コードの版と食い違うため。システム設定（`a=17`）を一度保存すると更新される。それまでは管理画面もシステム設定へ誘導され、エラー後の戻り先も `a=17` になる。`setup-site.js` は保存まで済ませる
- **新規インストールには `install/install-config.php` が要る**: `setup-site.js` は一時的に置き、終わったら消す
- **インストールすると作業ツリーに `manager/includes/config.inc.php` ができる**（git の無視対象）。`down -v` で DB だけ消すと、設定ファイルだけが残ってサイトが 500 になる。`setup-site.js` は DB のテーブルでインストール済みかを判定し、この状態なら止まる。開発者自身の設定ファイルを消さないよう、`--reinstall` を付けたときだけ退避（`config.inc.php.bak-<時刻>`）して入れ直す
- **システム設定を保存すると `sample.htaccess` が `.htaccess` に名前を変える**（`manager/processors/save_settings.processor.php`。フレンドリーURLのため）。作業ツリーに削除差分が残るので戻す。`setup-site.js` は保存前の中身で自動的に書き戻す（`git checkout` で戻すと、作業中の未コミットの変更が失われるため使わない）。`.htaccess` は git の無視対象
- **キャッシュはファイルを直接消さない**: `temp/cache/*.php` を直接消すと、プラグインのイベント表（`plugin.siteCache.idx.php` など）がアクセスしても作り直されず、FMP などのプラグインが動かなくなる（表側は 200 のままなので気づきにくい）。`php evo cache:clear` か「サイトをリフレッシュ」（`a=26`）を使う。どちらもその場で作り直す
- **ブランチを切り替えた直後は古いコードが動くことがある**: OPcache は `revalidate_freq=2`。切り替えてから数秒待ってから確かめる
- **管理画面は `Accept-Language` が無いと 404**: curl でも Playwright でも付ける（`lib.js` の `newPage()` は付けている）
- **セーフモードのログイン画面の見分け方**: `manager/media/style/common/login.tpl` はテーマの `style.css` を読み込まない。`.loginLicense` は両方にあるので目印にならない（`lib.js` の `isSafeModeLogin()`）
- **シェルの jAlert は潰れて OK ボタンが画面外に出る**: `body.evo-shell` がグリッドで、jquery.alerts が追加時の幅で `min-width` / `max-width` を固定するため。`page.click()` では押せないので DOM でクリックする（`lib.js` の `clickAlertOk()`）
- **シェルの断片かどうかは応答ヘッダーで見る**: shell.js は `X-Evo-Pane: 1` の有無だけで、断片として差し込むかを決める。fetch の応答を記録して確かめる（`lib.js` の `watchFetch()`）
- **POST の応答で画面全体を差し替えると**、`WebFXTabPane` の二重宣言エラーがコンソールに出る（既存の挙動。判定から除外してよい）

## 後片付け

```bash
# コンテナ・ボリューム・ネットワーク・ビルドしたイメージを消す（ベースイメージは残す）
docker compose -p evo-validate -f compose.yml \
  -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml down -v --rmi local

# 作業ツリーに残ったものを確かめる（manager/includes/config.inc.php と .htaccess は無視対象で、表示されない）
git status --short
```

- 検証用のインストールで作られた `manager/includes/config.inc.php`（と `--reinstall` の退避ファイル）は、`down -v` と一緒に消す。残すと、次に起動したとき空の DB に対して動いてしまう。開発者自身の設定ファイルを使っている作業ツリーでは消さない

- 確認のために止めたほかのコンテナは、ユーザーの指示に従って戻すか止めたままにする
- 作業用ディレクトリ（Playwright・確認スクリプト）は、リポジトリの外なので PR には入らない。不要なら消す
- ユーザーが目視で確かめたい場合は、`down` せずにログイン情報と URL を伝える

## 1.2 系（保守ブランチ・リリースタグ）で検証するとき

1.2 系の作業ツリーには、このスキルが含まれていない。main の `.claude/skills/adaptive-validation-runner/` を作業用ディレクトリ（リポジトリの外）へ取り出し、1.2 系の作業ツリーのルートで実行する（`git show origin/main:<path> > <work>/<path>` で 1 ファイルずつ取り出す。`git archive` は `.claude/` が export-ignore のため空になる）。

```bash
docker compose -p evo-validate -f compose.yml -f <work>/scripts/compose.validation.yml up -d --build
NODE_PATH=<work>/pw/node_modules node <work>/scripts/setup-site.js --sample --editor
NODE_PATH=<work>/pw/node_modules node <work>/scripts/smoke-1.2.js
```

- **管理画面はフレームセット**: ログイン後は `frame[name="main"]` などのフレームセットで、シェル（`#mainPane`）は無い。`lib.js` の `login()` はどちらでも通る。`smoke.js` と `shellState()` はシェル前提なので使えず、代わりに `scripts/smoke-1.2.js` を使う
- **バージョンを切り替えたら、システム設定を保存し直す**: DB の `settings_version` とコードの版が食い違うと、表側がメンテナンス画面（503）になる。修正前（1.2.xJ のタグ）と修正後を行き来するたびに、`setup-site.js`（インストール済みなら設定の保存だけ行う）を流し直す
- **auth_token は表側の入口でだけ効く**: 1.2 系では `token_auth()` が `getSettings()` から呼ばれるため、`/manager/?auth_token=` ではログインにならない。`/?auth_token=` で確かめる
- **手で組み立てた POST は 403 になる**: 管理画面の POST には CSRF トークン（`csrf_token`、または `X-CSRF-Token` ヘッダー）が要る。画面のフォームから送るか、別の画面のフォームにある `input[name="csrf_token"]` の値を付けて送る
- **メッセージ画面（a=10）は表示できない**: 1.2.1J 以前は `$pager` の未初期化の警告で止まる（main では直っている）。メッセージの送信・削除は、画面を経由せずに `a=66` / `a=65` へ送って確かめる
- **スモークテストの既知差**: 1.2 系（1.2.1J・1.2.2J とも）では、パスワード再設定メールが ISO-2022-JP で届き、再設定リンクが a=28 へ進まない。`smoke-1.2.js` ではこの 2 件を合否判定から外し、参考（`(参考)` 行）として表示する。回帰判定には使わない
- **配布 ZIP**: 1.2 系の `release.yml` は rsync で除外しながら zip にする方式（main の `git archive` とは違う）。手元で再現するときは、`git ls-files` の一覧をコピーしてから、同じ除外指定で rsync する
