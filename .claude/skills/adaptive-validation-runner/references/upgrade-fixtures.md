# 古い版の DB（アップグレードのフィクスチャ）

アップグレード（`install/` のアップデーターと `manager/includes/migrations/`）を、古い版の DB から確かめるための道具。
`scripts/upgrade-fixture.js` が、リリースタグの CREATE TABLE と最小のデータから古い版の DB を作り、アップグレード後のスキーマを今の新規インストールと比べる。
検証環境（`environment.md`）は先に立ち上げ、`setup-site.js` で今の版を入れておく。

## 使い方

リポジトリ（作業ツリー）のルートで実行する。`environment.md` の「並走するとき」のとおり、毎回の先頭に `EVO_VALIDATE_NAME` と `EVO_BIND_IP` を付ける。`playwright` は使わないので `NODE_PATH` は要らない。

```bash
# <uf> = EVO_VALIDATE_NAME=<名前> EVO_BIND_IP=<アドレス> EVO_WORK_DIR=<work> node .claude/skills/adaptive-validation-runner/scripts/upgrade-fixture.js
<uf> snapshot <work>/ref.tsv          # 1. 今の版の新規インストール直後の形（比べる元）
<uf> build 1.0.0J                      # 2. <work>/fixture-1.0.0J.sql を作る
<uf> load <work>/fixture-1.0.0J.sql    # 3. 検証用 DB を古い版の DB に置き換える
<uf> migrate                           # 4. （M3 以降）ランナーを流す
<uf> create-tables                     #    install/sql/create_tables.sql を流す
<uf> snapshot <work>/after.tsv         # 5. 上げた後の形
<uf> diff <work>/ref.tsv <work>/after.tsv
```

| 副コマンド | 役割 |
| --- | --- |
| `build <tag> [--out <file>]` | `git show release-<tag>:<path>` で CREATE TABLE を取り出し、`upgrade-fixture-data.sql` を足して SQL を作る。出力先は `$EVO_WORK_DIR/fixture-<tag>.sql`（作業用ディレクトリはリポジトリの外に置く） |
| `load <file>` | 接頭辞（`EVO_TABLE_PREFIX`、既定 `modx_`）の表をすべて消してから、ファイルを流し込む。管理者（ID 1）のパスワードは、流し込む前の DB の値を引き継ぐので、`admin` / `Passw0rd!2026` でログインできる |
| `migrate` | アップデーターと同じく、CLI のガード（`EvoMigrator::requiresInstaller()`）を通さずに、作業ツリーのランナーを流す。アプリのコンテナで `manager/includes/cli/bootstrap.php` を読み込んでから `(new EvoMigrator())->run()` を呼ぶ。適用した ID を `Applied <ID>` で 1 行ずつ出し、失敗は ID とエラーを出して終了コード 1。キャッシュは消さない（`system_cache` の無い古い DB では `clearCache()` が処理ごと止めるため） |
| `create-tables` | `install/sql/create_tables.sql` を、インストーラ（`install/sqlParser.class.php`）と同じ置き換え（`{PREFIX}`、`{TABLE_OPTION}` を `CHARSET=utf8mb4 COLLATE utf8mb4_general_ci`）で流す。アップデーターの順（ランナー → `create_tables.sql`）を手で再現するときに使う |
| `snapshot <out>` | 列と索引の定義を、1 行 1 項目の TSV に書く |
| `diff <ref> <target>` | 2 つの `snapshot` を比べる。違いがあれば終了コード 1、無ければ 0。`<ref>` に無い表は「残す表」として別に数え、違いには入れない |

アップデーターを実際に通すとき（M5 以降）は、`load` のあと Playwright で `install/` を進める。`migrate`・`create-tables` は、アップデーターに組み込まれる前の段階と、ランナー単体の確認のための手順。

`load` が途中で失敗すると、管理者のパスワードの元の値は失われる（フィクスチャの仮の値 `x` が残る）。次の `load` が止まって知らせるので、`setup-site.js --reinstall` で今の版を入れ直す（DB の表が残っているとインストール済みと見なされるので、先に表を消すか `down -v` する）。

## タグと CREATE TABLE の元

| タグ | ファイル |
| --- | --- |
| 1.0.0J〜1.0.6J | `install/setup.sql`（CREATE TABLE のあとの `upgrade-able[[` の ALTER の塊は使わない） |
| 1.0.7J〜1.0.16J | `install/sql/both_createtables.sql` |
| 1.0.17J 以降 | `install/sql/create_tables.sql` |

`build` は CREATE TABLE の文だけを使い、同じ表が 2 回出てきたら最初のものを使う。次の置き換えをする。

- `{PREFIX}` を接頭辞に、`TYPE=MyISAM` を `ENGINE=MyISAM` に（今の MariaDB は `TYPE=` を受け付けない）
- `{TABLE_OPTION}` を `DEFAULT CHARSET=utf8mb3` に。1.0.16J までは文字セットを書かないので、`ENGINE=` のあとに同じものを足す。古いサイトの文字セットを再現する（インストーラの utf8mb4 への変換より前の状態）。utf8mb4 のままだと、MyISAM の索引の長さの上限（1000 バイト）で `aliasidx` が `alias(250)` に切られ、長い UNIQUE が HASH になって、本物の古いサイトと違う形になる
- エンジンは定義のとおり。1.0.0J〜1.1.0J は MyISAM、1.2.0J 以降は `ENGINE=` が無いので DB の既定（InnoDB）

### 動作確認済みのタグ

`build` と `load` は、次の 15 個のすべてでエラー無しで終わる（Docker の mariadb:11）。

1.0.0J・1.0.5J・1.0.6J・1.0.7J・1.0.10J・1.0.14J.r9・1.0.16J・1.0.17J・1.0.20J・1.0.26J・1.1.0J・1.2.0J・1.2.1J・1.2.2J・1.2.3J

1.0.0J のフィクスチャと今の新規インストールの差は、ExecPlan `.agent/plans/2026-05-06-migration-mechanism-modernization.md` の Context「1.0.0J と今の最終形の差」の表と同じ項目になる。

## フィクスチャのデータ

`scripts/upgrade-fixture-data.sql`。1.0.0J から今まである列だけを使い、アップグレードの処理が読む古い値を入れる。

- システム設定: `settings_version`・`install_version`（どちらもそのタグの版）・`manager_theme`（`MODxCarbon`）・`which_editor`（`TinyMCE7`）・`site_id`（配布の既定値）・`site_url`・`emailsender`（`you@example.com`）・`custom_contenttype`・`topmenu_site`（古い既定）・`validate_referer`・`upload_maxsize`（`1048576`）・`use_udperms`・`csrf_token`
- 管理者（ID 1）と、ロール 1（`empty_trash=0`。利用者が外した権限）、ロール 2（`save_role=1` で一部の権限が 0）
- 文書: `type=''` の 5 件（`http://example.com/` の中身、`feed.xml`・`site.js`・`site.css`・どれにも当たらないもの）、alias が文字列 `NULL` の 1 件
- テンプレート: `content` が NULL の 1 件
- スニペット: プロパティに `&NULL=undefined;;` を含む 1 件
- プラグイン: 有効な `Inherit Parent Template`（`&inheritTemplate=...From First Sibling`、`auto_template_logic` の行は無い）と `Bottom Button Bar`、ふつうの 1 件
- ユーザー設定: `user_settings` の `csrf_token`

重複行（キーを持たない古い表にだけ入る。消すべき件数）:

| 表 | 入れる行 | 1.0.0J | 1.0.5J | 1.0.6J 以降 |
| --- | --- | --- | --- | --- |
| `site_plugin_events` | `(pluginid=1, evtid=1)` が 2 行（`priority` 5 と 2。小さい 2 を残す） | 1 | 1 | 0 |
| `member_groups` | `(user_group=1, member=1)` が 3 行（`id` の小さい行を残す） | 2 | 0 | 0 |
| `web_groups` | `(webgroup=1, webuser=1)` が 2 行 | 1 | 0 | 0 |
| `user_settings` | `(user=1, tinymce_editor_theme)` が 2 行（`simple` のあと `advanced`。後の行が残る） | 1 | 1 | 0 |
| `web_user_settings` | `(webuser=1, lang)` が 2 行（`en` のあと `ja`。後の行が残る） | 1 | 1 | 0 |

1.0.5J の `member_groups`・`web_groups` は、定義に UNIQUE が既にあるので重複が入らない。1.0.6J 以降は主キー・UNIQUE が既にあり、重複は入らない（1.0.6J の `user_settings` / `web_user_settings` は主キーが `setting_name` だけなので、同じ名前の行は 1 行しか入らない）。重複を消す処理を確かめるのは、1.0.0J と 1.0.5J。

## 比べる項目と比べない項目

`snapshot` / `diff` が比べるのは次のとおり。

- 列: 型（`COLUMN_TYPE`）・NULL 可否・既定値・`EXTRA`（`auto_increment` など）
- 索引: 名前・一意かどうか・種類・列の並び（前置き長を含む）。種類は FULLTEXT かそれ以外かだけ（MariaDB は長い UNIQUE を HASH と報告するなど、サーバーの事情で変わるため）

比べない項目と理由:

- 列のコメント・表のコメント: 動作に関係しない
- 列の順: ALTER で足した列の位置は AFTER で決まるが、読む側は列名で読む
- 文字セットと照合順序: 利用者がインストーラで選ぶ utf8mb4 への変換（`convert2utf8mb4()`）の役
- ストレージエンジン: 古いサイトの MyISAM を変える処理がもともと無く、この計画の非対象

`diff` の出力は、`なし`（`<ref>` にあって `<target>` に無い列・索引）、`余分`（`<ref>` に無い列・索引）、`相違`（定義が違う）、`表なし`（`<ref>` にあって `<target>` に無い表）の 4 種類。`<ref>` に無い表は「残す表」（消えた表 `event_log`・`keyword_xref`・`site_content_metatags`・`site_keywords`・`site_metatags` やサンプルの `jot_*`。利用者のデータが入っているので消さない）として別に出す。

## 古い版のコードを動かさない理由

1.0.0J〜1.0.15J は `mysql_*` 関数を使い（mysqli は 1.0.16J から）、PHP 7.0 以降には無い。標準の検証環境（php:8.4）では動かせないので、そのタグのコードを入れるのではなく、DB だけをフィクスチャで再現する。
1.0.16J〜1.1.0J を PHP 7.x のイメージで実際に入れるのは、手間に比べて得るものが少ない（スキーマはフィクスチャで同じに作れる）ので行わない。
1.2.0J〜1.2.3J は、今の検証環境で実際に入れて上げる手順（`environment.md` の「1.2 系で検証するとき」）も使える。

## DB の版

標準は mariadb:11。古い DB の版で試すときは、`EVO_DB_IMAGE` で変えて環境を作り直す（ボリュームごと作り直すので、`down -v` してから `up`）。

```bash
EVO_DB_IMAGE=mariadb:10.4 EVO_VALIDATE_NAME=<名前> EVO_BIND_IP=<アドレス> docker compose -f compose.yml \
  -f .claude/skills/adaptive-validation-runner/scripts/compose.validation.yml up -d --build
```

MySQL 公式イメージ（5.7・8.0 など）は、Docker Engine 29 系ではレイヤーを展開できず取得できない（`environment.md` の「既知の落とし穴」）。MySQL の版で確かめられない部分は、取れない理由を検証の記録に書く。
