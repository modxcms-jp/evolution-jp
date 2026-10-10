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

- 管理画面: `http://localhost/manager/`
- 管理者: `admin` / `Passw0rd!2026`（ロール2 ウェブマスター）
- 権限の弱いユーザー（`--editor`）: `editor` / 管理者と同じパスワード（ロール3 投稿担当者、`save_role` なし）
- mailpit: `http://localhost:8025/`（API は `/api/v1/messages`）

## 設定を変えて確かめる

システム設定は DB を直接書き換え、管理画面の「サイトをリフレッシュ」でキャッシュを作り直す（`lib.js` の `sql()` と `refreshCache()`）。

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
- **システム設定を保存すると `sample.htaccess` が `.htaccess` に名前を変える**（`manager/processors/save_settings.processor.php`。フレンドリーURLのため）。作業ツリーに削除差分が残るので、`git checkout -- sample.htaccess` で戻す（`setup-site.js` は自動で戻す）。`.htaccess` は git の無視対象
- **キャッシュはファイルを消さない**: `temp/cache/*.php` を消すとプラグインのイベント表が失われ（FMP などが動かない）、表側も 503 になる。「サイトをリフレッシュ」（`a=26`）で作り直す
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
