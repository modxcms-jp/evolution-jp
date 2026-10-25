# #667 マイグレーション機構の通しの検証（M7 前半）

日付: 2026-10-26 / 対象: `refactor/667-migrations` HEAD 2514d8632 / 検証者: tester
ExecPlan: `.agent/plans/2026-05-06-migration-mechanism-modernization.md` の「Validation and Acceptance」

## 環境とコマンドの要点

- 標準環境: php:8.4-apache + mariadb:11（`compose.yml` + `compose.validation.yml`、プロジェクト名と IP は並走のため専用）
- 最終形 `ref-final.tsv`: この作業ツリーで `setup-site.js` による新規インストール直後の `upgrade-fixture.js snapshot`（列 344 行・索引 63 行）
- 各行列の流れ: `upgrade-fixture.js build <tag>` → `load` → 利用者の選択を入れる（`manager_theme=SoftSlate`、`which_editor=none`、`auto_template_logic=parent`、`topmenu_site` を並べ替え、`emailsender` を別のアドレス、ロール 1 の `empty_trash=0`、ロール 2 の `delete_document=0`）→ Playwright でアップデーター（`admin` でログイン → `/install/?action=mode` から最後まで）→ `snapshot` / `diff ref-final.tsv` → 選択が残っているか → 2 回目のアップデーター（一般ログを有効にして `ALTER TABLE` の数と、`mariadb-dump` の差を見る）
- 実サイトの 1.2.3J: `release-1.2.3J` の detached 作業ツリーを別プロジェクトで起動し、`environment.md` の「1.2 系で検証するとき」のとおり `setup-site.js` で入れた DB を `mariadb-dump` し、標準環境の DB に `load` してこの作業ツリーのコードでアップデーター
- 今の main（b686bb12c）の新規インストール: 同様に detached 作業ツリーで入れて dump → この作業ツリーでアップデーター
- 検証後はすべて `down -v --rmi local`、作業ツリー（追跡対象）に差分なし

## 1. 行列の結果（上げたあとの diff が 0 件か）

すべて: アップデーター画面は成功、上げる前の公開側 503 → 上げたあと 200、`migrate:status` は `applied 00000000_baseline` / `0 pending`、インストールログに `Migrations applied`、利用者の選択は全て残った（KEPT）、2 回目のアップデーターで `ALTER TABLE` 0 本・ダンプ差 0 行（ログイン由来の行を除く）。

| 元の DB | 作り方 | diff | 残す表 | 重複行を消した件数（ログ） |
| --- | --- | --- | --- | --- |
| 1.0.0J | フィクスチャ（smoke は下記） | 0 件 | 5 | `site_plugin_events`=1 `user_settings`=1 `web_user_settings`=1 `member_groups`=2 `web_groups`=1 |
| 1.0.5J | フィクスチャ | 0 件 | 5 | `site_plugin_events`=1 `user_settings`=1 `web_user_settings`=1 |
| 1.0.6J | フィクスチャ | 0 件 | 5 | なし |
| 1.0.7J | フィクスチャ | 0 件 | 1 | なし |
| 1.0.10J | フィクスチャ | 0 件 | 1 | なし |
| 1.0.14J.r9 | フィクスチャ | 0 件 | 1 | なし |
| 1.0.16J | フィクスチャ | 0 件 | 1 | なし |
| 1.0.17J | フィクスチャ | 0 件 | 1 | なし |
| 1.0.20J | フィクスチャ | 0 件 | 1 | なし |
| 1.0.26J | フィクスチャ | 0 件 | 1 | なし |
| 1.0.0J + `doc_encoding` + 1.0.14J.r9 の `site_revision` | フィクスチャ + 追加の ALTER / CREATE | 0 件 | 5 | 1.0.0J と同じ |
| 1.1.0J | フィクスチャ | 0 件 | 1 | なし |
| 1.0.0J utf8mb4 | `build --charset utf8mb4` | 0 件 | 5 | 1.0.0J と同じ |
| 1.1.0J utf8mb4 | 同上 | 0 件 | 1 | なし |
| 1.2.3J utf8mb4 | 同上 | 0 件 | 0 | なし |
| 1.2.0J | フィクスチャ | 0 件 | 1 | なし |
| 1.2.1J | フィクスチャ | 0 件 | 0 | なし |
| 1.2.2J | フィクスチャ | 0 件 | 0 | なし |
| 1.2.3J | フィクスチャ | 0 件 | 0 | なし |
| 1.2.3J の実サイト | 1.2.3J の作業ツリーで実際にインストール | 0 件 | 0 | なし |
| 今の main（b686bb12c）の新規インストール | detached 作業ツリーで入れた DB | 0 件 | 0 | なし |

「残す表」の 1 は `event_log`（フィクスチャにあって新規インストールに無い表）、5 は `event_log`・`keyword_xref`・`site_content_metatags`・`site_keywords`・`site_metatags`（`modx_migrations` は ref にあるので数えない）。

### 利用者の選択（全行列で KEPT）

`manager_theme`・`which_editor`・`auto_template_logic`（`parent` の行。`Inherit Parent Template` は有効のまま）・`topmenu_site`（並べ替え）・`emailsender`・ロール 1 の `empty_trash=0`・ロール 2 の `delete_document=0`。

### 2 回目のアップデーターで DB が変わらない

全行列で `ALTER TABLE` 0 本。`UPDATE modx_site_content SET privatemgr = 0 / privateweb = 0` の 2 本はアップデーターに元からある文（変更行 0）で、ダンプは同一。

### smoke.js

- 1.2.3J の実サイト（アップデーター後）: PASS 6 / FAIL 0。
- 今の main の実サイト（アップデーター後）: PASS 6 / FAIL 0。ただし、この実サイトの DB を持ち込んだときに `fixed_site_url` が元の環境の IP のままだったため、そのままだと管理画面が到達できないアドレスへ飛ぶ。検証の道具側の問題（`fixed_site_url` を今の IP に直して再実行）。
- 1.0.0J のフィクスチャ: PASS 5 / FAIL 1。FAIL は「メール: UTF-8 で、件名と本文が読める」で、フィクスチャが `manager_language=english` を持ち、パスワード再設定メールが英語（件名 `Password change request`）になるため、`smoke.js` の日本語の検査に合わない。`manager_language` を `japanese-utf8` にして `cache:clear` すると PASS 6 / FAIL 0。アップグレードの不具合ではない。
  - 対応案: フィクスチャの `manager_language` を `japanese-utf8` にする（`upgrade-fixture-data.sql`）。

## 2. 新しいマイグレーションの追加（運用の確認）

- `php evo make:migration check_flow` → `manager/includes/migrations/20261026_*_check_flow.php`。`$m->addColumn('site_content', 'x_check', ...)` を書く。
- 未適用の間、公開側 503、`migrate:status` は `pending ...`。アップデーターを通すと 1 回だけ適用（`Migrations applied {"migrations":["..._check_flow"]}`）、列ができ、`migrate:status` は `2 applied, 0 pending`。もう一度アップデーターを通しても記録は 2 行のまま。
- 2 本目（`check_flow_cli`）を置いて CLI の `php evo migrate` → `Applied ...`（baseline 記録済みのサイト）、もう一度で `Nothing to migrate.`、公開側は 200 に戻る。
- 確認後、ファイルと 2 つの列、記録の行を消した。PHP 7.4 でも同じ手順で通った。

## 3. PHP 7.4

- `php:7.4-apache` から作業用の Dockerfile（本体の `manager/docker/Dockerfile` と同じ拡張。`msmtp` を外し、apt のミラーを `archive.debian.org` に向けた）でイメージを作り、compose の上書きで差し替えた。PHP 7.4.33。
  - 動かせなかった部分: `msmtp` は bullseye-security の古いパッケージが 404 で入れられず、メールの送信（smoke のメールの項目）は 7.4 では確かめていない。
- `php -l` を 7.4 で実行（`migrator`・`migration_helper`・`00000000_baseline`・`update_guard`・CLI の 3 コマンド・`instprocessor.php`）: すべて No syntax errors。
- 7.4 で新規インストール → `ref-final` と diff 0 件。`health:check` は `PHP version -- 7.4.33`、`Database migrations` OK。
- 1.0.0J と 1.2.3J のフィクスチャ: アップデーター成功、diff 0 件、選択は KEPT、2 回目で `ALTER TABLE` 0 本。`php evo migrate`・`migrate:status`・`make:migration` も動いた（上の「運用の確認」と同じ手順）。
- 観察: PHP 7.4 では、メンテナンスの 503（未適用あり）を返すたびにシステムログへ error が 1 件出る。メッセージは `$modx->postProcess() is undefined function`（source `Call undefined method`）。PHP 8.4 では同じ操作で出なかった。表示と挙動には影響しない。

## 4. 古い DB の版

| DB | 取得 | 1.0.0J | 1.2.3J |
| --- | --- | --- | --- |
| mariadb:10.4（10.4.34） | 取れた（`EVO_DB_IMAGE=mariadb:10.4`） | アップデーター成功。ただし diff あり（下記） | 同左 |
| mysql:8.0（8.0.46） | 取れた（`docker pull` 成功） | 同左 | 同左 |
| mysql:5.7（5.7.44） | 取れた | 同左 | 同左 |

- この環境の Docker では MySQL 公式イメージを取れた（`environment.md` の既知の落とし穴にある展開の失敗は出なかった）。ただし MySQL のイメージには `mariadb` / `mariadb-dump` コマンドが無く、道具（`lib.js`・`upgrade-fixture.js`）が使うため、作業用の Dockerfile で `mysql` / `mysqldump` への別名を足したイメージで確かめた。`compose.validation.yml` の `MARIADB_*` は MySQL に効かないので、`MYSQL_*` を足す上書きも使った。道具そのものは MySQL 向けではない。
- 結果（3 つの DB で同じ）: アップデーターは成功し、`applied 00000000_baseline`、選択は KEPT、重複行の件数は 1.0.0J で MariaDB 11 と同じ、2 回目は `ALTER TABLE` 0 本。
- **diff が 22 行（11 列）残る**: utf8mb3 のフィクスチャを上げると、インストーラの `convert2utf8mb4()`（`CONVERT TO CHARACTER SET utf8mb4`）が、`text` を `mediumtext` に、`mediumtext` を `longtext` に広げる（`site_content.content` が longtext、`site_content.introtext` が mediumtext など。新規インストールは `introtext`=text、`content`=mediumtext）。最小の SQL で確認した: `CREATE TABLE (a mediumtext, b text) CHARSET utf8mb3` → `CONVERT TO CHARACTER SET utf8mb4` で `a` が longtext、`b` が mediumtext になる（MariaDB 10.4。MySQL も同様）。MariaDB 11 では広がらない。
  - マイグレーションの外（M5 で残した `convert2utf8mb4()`）の動き。広がるのは入れ物が大きくなるだけで、読み書きに支障はない。
  - 2 回目のアップデーターで `system_cache.cache_value` が longtext → mediumtext に変わる（`create_tables.sql` が `system_cache` を作り直すため）。
  - 対応案: 最終形との差として許容するなら文書に書く、変えるなら `convert2utf8mb4()` の後で型をそろえる処理を足す。
- MySQL 5.7 と 8.0 の新規インストールを、MariaDB 11 の `ref-final` と比べると 106 / 329 件の違いがある（照合順序と表示幅などの MySQL 固有の差）。比べる元は、それぞれの DB の新規インストール直後の snapshot にした。

## 5. 文書のための確認

- settings 権限のある人がいないサイト（全ロールの `settings=0`）で未適用があるとき:
  - ログインしていない状態で `/install/` を直接開くと、画面に「アップグレードには管理者認証が必要です / Evolution CMS の管理画面にログインしている必要があります」と出て、アップデーターは実行できない（ログインが要る）。
  - `admin` でログインして `/manager/` を開くとメンテナンス表示（「システムのアップデート中です。…管理者にお問い合わせください」）。アップデーターへは案内されない。
  - 文書に書く内容: この状態から抜けるには、DB でロールに `settings` の権限を戻してから、ログインしてアップデーターを通す。
- 古いルートの `index.php`（`git show release-1.2.3J:index.php`）を置いたまま `manager/` が新しい状態: 未適用なし・ありのどちらでも公開側は 200 / 503 で、500 にならない（1.2.3J の `index.php` は `version_mismatch_guard.php` を読まない）。
- 古い main の `index.php`（`git show b686bb12c:index.php`、`manager/includes/version_mismatch_guard.php` を `include_once`）を置いたまま: 公開側が HTTP 500（本文 `Error`）。未適用の有無に関わらず再現（`version_mismatch_guard.php` が無くなったため）。
  - 文書に書く内容: ルートの `index.php` もアップデートで差し替える（`manager/` だけ差し替えると 500 になる）。

## 期待と違った点のまとめ

1. 1.0.0J フィクスチャの smoke の 1 項目（フィクスチャの `manager_language=english`）。アップグレードの不具合ではなく、道具のデータ。
2. MariaDB 10.4 / MySQL 5.7 / 8.0 で、utf8mb3 のフィクスチャを上げると text 系の列の型が新規インストールより大きくなる（11 列）。MariaDB 11 では 0 件。
3. PHP 7.4 で、503 を返すたびにエラーログが 1 件出る（`postProcess() is undefined function`）。
