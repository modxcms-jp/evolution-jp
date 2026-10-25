# マイグレーション

DB のスキーマと、設定・データの移行（アップグレードの処理）の仕組みと書き方。サイトを管理する人と、マイグレーションを書く開発者の両方が読む。

CLI の使い方の詳細は [`manager/includes/cli/README.md`](../../manager/includes/cli/README.md)、古い版の DB を作って確かめる道具は [`.claude/skills/adaptive-validation-runner/references/upgrade-fixtures.md`](../../.claude/skills/adaptive-validation-runner/references/upgrade-fixtures.md) を正とする。ここでは参照する。

## 仕組みの全体

用語:

- **マイグレーション**: DB を 1 段階だけ新しい形にする処理。1 本が PHP ファイル 1 つで、SQL だけでなく設定値の書き換えなども書ける。適用したものは記録の表に 1 行ずつ残り、2 回は流れない。
- **ランナー**: まだ適用していないマイグレーションを、ファイル名の順に流して記録する仕組み（`EvoMigrator`。`manager/includes/migrator.class.inc.php`）。
- **baseline**: ランナーを入れる前の DB を、今の最終形にそろえる最初のマイグレーション（`00000000_baseline.php`）。1.0.0J 以降のどの版の DB からでも、この 1 本で今の形になる。
- **最終形**: 今の版を新規インストールした直後の DB の形。

構成:

| 要素 | 場所 | 役割 |
| --- | --- | --- |
| マイグレーション | `manager/includes/migrations/` | 版依存のアップグレード処理の置き場所は、ここだけ |
| 記録の表 | `{prefix}migrations`（既定の接頭辞で `modx_migrations`） | 適用した ID と時刻（`id`・`applied_at`）。定義はランナーだけが持ち、`create_tables.sql` には書かない |
| ランナー | `manager/includes/migrator.class.inc.php` | 未適用を名前順に流して記録する。`GET_LOCK()` で同時に 2 回走らせない（30 秒待って取れなければ失敗）。アップデーターは、流してから記録するまでのあいだはロックを持たない（記録は重なっても壊れない） |
| 補助 | `manager/includes/migration_helper.class.inc.php` | マイグレーションの関数に渡される `EvoMigrationHelper` |
| CLI | `php evo migrate` / `migrate:status` / `make:migration` | 使い方は CLI の README |
| 最終形の定義 | `install/sql/create_tables.sql`、`install/sql/system_eventnames.sql` | 表の定義とイベント名。毎回そろえる |

アップデーター（`install/`）で上げるときの順序は次のとおり。

1. ランナー（未適用のマイグレーションを流す。この時点では記録しない）
2. `install/sql/create_tables.sql`（`CREATE TABLE IF NOT EXISTS`。新しい表を作る）
3. イベント名の同期（`install/sql/system_eventnames.sql`）
4. utf8mb4 への変換（利用者がインストーラで選んだとき）
5. config・同梱の要素の入れ直し
6. エラーが無ければ、流したマイグレーションをまとめて記録する（`recordApplied()`）。記録するまでは未適用のままなので、公開側は止まったまま

ランナーを先に流すのは、列の長さを縮める処理（`alias` 245、`name` 191）を utf8mb4 への変換より前に済ませないと、索引の長さの上限で変換が失敗するため、また、古い形の `site_revision` を baseline が消してから `create_tables.sql` が今の形で作るため。

新規インストールでは、ランナーでマイグレーションを流さず、すべてを適用済みとして記録する（`markAllApplied()`）。記録は上のアップデーターと同じく、最後の手順で、エラーが無いときだけ行う。新規インストールの DB は最初から最終形なので、流す必要がない。

ランナーは、キャッシュを消さない。消すのは呼び出し側（インストーラは最後に、CLI の `migrate` は適用か失敗のあとに）。

## マイグレーションの書き方

### 作り方

```bash
php evo make:migration add_example_column
```

`manager/includes/migrations/<YYYYMMDD_HHMMSS>_<説明>.php` に雛形ができる。

- ID は `YYYYMMDD_HHMMSS_説明` で、ファイル名から `.php` を除いたもの。説明は英小文字・数字・`_`（`make:migration` の説明は 84 文字まで）。ID の上限は 100 文字（記録の表の列と同じ）。超える ID があるとランナーは流す前に止まる。
- ランナーが読むファイルは `00000000_baseline.php` か、`\d{8}_\d{6}_[a-z0-9_]+.php`（日付・時刻・説明）に合うものだけ。時刻の無い ID は `00000000_baseline` だけに使う。
- 適用の順は ID の名前順。同じ日に 2 本足したときの順は、時刻が決める。
- ロールバック（down）は作らない。上げる方向だけ。

ファイルは、関数を 1 つ返す。

```php
<?php

return static function (EvoMigrationHelper $m) {
    // ...
};
```

`static` にするのは、関数が `$this` に結び付いたり、ファイルの変数がランナーの変数を書き換えたりしないようにするため。

### 決まり

- **冪等に書く**: 何度流しても、1 回目のあとは何も変わらないようにする。`INFORMATION_SCHEMA`・`SHOW INDEX`・`IF NOT EXISTS` などの存在確認や、最終形との比較を省かない。CLI の `migrate` では失敗したものだけが記録されず、次回はそこから流し直される。アップデーターでは、あとの手順（`create_tables.sql`・utf8mb4 への変換など）が失敗すると、その回に流したマイグレーションも記録されず、次のアップデーターで全部流し直されるため。
- **表を作るのは `create_tables.sql` だけ**: マイグレーションは既にある表を変えるだけにする。表が無ければ何もしない。新しい表の定義が 1 か所で済み、ランナーが先に動いても問題にならない。
- **同じ変更を両方に書く**: 列や索引を変えるときは、マイグレーション（既存のサイトを上げる）と `create_tables.sql`（新規インストール）の両方に同じ変更を入れる。上げたあとの DB と新規インストールの DB が同じ形になることが、この仕組みの前提。
- **版の条件を書かない**: 「1.2.0 より古ければ」のような版の比較は置かない。条件は DB とファイルの状態（列があるか、値が古い既定値と同じか）で決める。前の版が分からないサイトも同じに扱える。
- **利用者の設定を上書きしない**: 利用者が選んだ値（テーマ・エディタ・外した権限など）は、書き換えない。直すときは、古い既定値のままの行だけを対象にする。
- **ログは補助の `log()` で**: 値に秘密（パスワード、SMTP の設定など）や利用者のデータを入れない。
- **失敗は `EvoMigrationException` で投げる**: ログは取らず投げるだけ。ランナーが ID を足して 1 回だけ記録し、そのマイグレーションを記録せずに止める。補助の `exec()` は、エラーがあれば例外を投げる。
- **DB にある同梱物の入れ替えが要る版**: 同梱プラグインのコードなど、DB に入っている同梱物を入れ替える必要がある版は、その版のマイグレーションに入れ替えを書く。マイグレーションがあれば未適用として数えられ、アップデーターへ送られる。書き方は `install/processors/prc_insPlugins.inc.php` の作りに合わせる（同名の有効な行を無効にして控えとして残し、新しい行を入れる）。入れ替えの元は `assets/` の下の `.tpl` の docblock で、読み取りの関数 `parse_docblock()` は今は `install/functions.php` にある。初めて入れ替えのマイグレーションを書くときに、この関数を `manager/includes/` へ移し、インストーラとマイグレーションの両方から使う。
- **大きな表の `ALTER` は時間がかかる**: 列の型の変更や索引の追加は、行の多い表で時間がかかる。時間のかかる処理は、かかった秒数を `log()` に残す。

SQL の値は、実行の直前に `db()->escape()` か `(int)` で安全化する（AGENTS.md の DB 操作の決まり）。補助の中で値を無害化しているのは `setting()` などの設定の読み書きで、それ以外の `exec()` に渡す SQL は呼ぶ側が安全化する。

### 補助の主な API

`EvoMigrationHelper` の主なものを挙げる。全部の引数は `manager/includes/migration_helper.class.inc.php` を見る。

| 分類 | メソッド |
| --- | --- |
| 実行 | `exec($sql, $summary = null)`（失敗で例外。`$summary` を渡すと SQL の代わりにそれをログと例外に出す。値を含む SQL に使う）、`rows($sql, $summary = null)`、`table($name)`（接頭辞つきの表名） |
| 存在確認 | `tableExists`・`columnExists`・`column`・`index`・`indexExists`・`indexColumns`・`primaryKey` |
| 列・索引 | `addColumn`（足したら true）、`modifyColumn`（最終形と違うときだけ変える）、`syncPrimaryKey`、`addIndex`、`dropIndexIfExists`・`dropColumnIfExists`・`dropTableIfExists` |
| データ | `removeDuplicates($table, $keyColumns, $keepRule)`（主キー・UNIQUE を付ける前に重複行を消す） |
| 設定 | `setting`・`hasSetting`・`saveSetting`・`deleteSetting` |
| ログ | `log($message, array $context = [], $type = 1)` |

## アップデート待ちの判定

「アップデートが済んでいないか」の判定は、未適用のマイグレーションがあるか（`EvoMigrator::hasPending()`）の 1 つだけ。次の場所が同じ判定を使う。

- 公開側（`manager/includes/update_guard.php`）: 未適用があればメンテナンスの表示（HTTP 503）を返す。呼ぶ位置は `index.php` の `executeParser()` より前。
- 管理画面の入口（`manager()->isUpdatePending()`）: 未適用があればアップデーター（`install/`）へ送る。settings 権限のある人は、`install/` があればアップデーターへ送られ、無ければアップデーターを置いて実行するよう案内される。ほかの人には、`install/` の有無に関わらずメンテナンスの表示を出す。
- `php evo health:check`: 未適用の件数を表示する。

記録の表が読めないときは、止める側に倒す（未適用あり、として扱う）。公開側のリクエストごとに、ファイルの一覧（`glob()` 1 回）と記録の表の SELECT 1 回を行う。

`index.php` の先頭近くにある、コードとキャッシュの版が同じなら DB を見ずにページのキャッシュを返す早い返し方は、ページのキャッシュの互換のための判定で、アップデート待ちの判定ではない。未適用があっても、アップデートの前に作られたページが返ることがある。

`settings_version`（システム設定）は、「システム設定を保存した版」として残す。アップデートの判定には使わず、一度も保存していない（`'0'` か空）サイトの初回に設定画面へ案内する判定にだけ使う。版の表示（メニュー・システム情報・バックアップの名前・`health:check`）はコードの版（`$modx_version`）を使う。

## 利用者がアップデートするとき

1. ファイルを差し替える。`manager/` だけでなく、ルートの `index.php` も必ず差し替える。1.2 系と未リリースの `main` の古い `index.php` は、今は無い `manager/includes/version_mismatch_guard.php` を読み込むため、`manager/` だけを新しくして古い `index.php` が残ると、公開側が HTTP 500 になる（古いガードのファイルが残っていれば読み込めるが、今の判定は使われず、未適用があっても公開側は止まらない）。
2. `install/` を置いて、アップデーターで上げる。管理画面にログインした状態で `/install/?action=mode` から最後まで進める。
3. アップデーターは、ランナー → `create_tables.sql` → イベント名 → utf8mb4 への変換の順に処理し、最後にキャッシュを消す。

ファイルだけを差し替えて `install/` を置かないと、未適用があるあいだ、公開側はメンテナンスの表示になる。管理画面では、settings 権限のある人に、アップデーター（`install/`）を置くよう案内が出る。

settings 権限のある人がいないと、管理画面からはアップデーターへ案内されない。

CLI の `php evo migrate` は、baseline が記録済みのサイトで、開発中などに足したマイグレーションを流すためのもの。古い版からは使えない（baseline が記録されていないと、何も流さずに止まる）。古い版の DB は、必ずアップデーターで上げる（イベント名の同期・utf8mb4 への変換・同梱の要素の入れ直しは、アップデーターだけが行うため）。

## 止まったとき

ログの場所:

- システムログ: `temp/logs/system/YYYY/MM/`（JSONLines。`php evo log:tail` / `log:search` で読める）。マイグレーションの失敗は、ID とエラーがここに残る。
- インストールのログ: `temp/logs/install/YYYY/MM/`。アップデーターの進行と、適用したマイグレーションの ID（`Migrations applied`）が残る。

よくある止まり方:

- **長い値で止まる**: 列を縮めるとき（`site_content.alias` を 245 文字、`documentgroup_names`・`membergroup_names`・`webgroup_names` の `name` を 191 文字）に、収まらない値があると、何も変えずに止まる。該当する行の一覧（表・列・上限・行の ID・今の長さ）が、アップデーターの画面とシステムログに出る。DB の道具か `php evo db:query` で値を短くしてから、アップデーターをやり直す。
- **新規インストールの途中の失敗**: 新規インストールが途中で失敗したときは、アップデーター（`install/`）をもう一度通す。
- **ロック**: ほかの処理が走っていて 30 秒待っても取れないと、失敗になる。ほかのアップデーターや `migrate` が走っていないか確かめて、やり直す。
- **途中の失敗**: アップデーターでは、あとの手順が失敗すると、その回に流したマイグレーションも記録されず、次のアップデーターで全部流し直される。原因を直して、アップデーターをやり直せばよい。冪等に書いてあるので、途中まで進んだ変更があっても流し直せる。アップデーターが途中で失敗したときは、CLI ではなくアップデーターでやり直す（CLI はアップデーターの残りの手順を行わない）。baseline のあとに足したマイグレーション自体の失敗は、`php evo migrate` でもやり直せる。ただし MyISAM の表の変更は、途中で戻せない。

## 既知の差

- **MariaDB 11 以外で text 系の型が 1 段広がる**: MariaDB 10.4・MySQL 5.7・8.0 で、utf8mb3 のサイトを上げると、インストーラの utf8mb4 への変換（`convert2utf8mb4()`。版によらない既存の処理）が、text 系の列の型を 1 段広げる（`text` → `mediumtext`、`mediumtext` → `longtext`）。新規インストールと型が違う列が 11 列残る。4 バイトの文字で容量が足りなくならないための DB の動きで、戻すと値が切れるおそれがあるので戻さない。読み書きには支障が無い。MariaDB 11 では広がらない。
- **MariaDB 11 以外では、2 回目のアップデーターで `system_cache.cache_value` の型が戻る**: `create_tables.sql` が `system_cache` を毎回作り直すため（インストーラの動き）。
- **`alias` の索引**: InnoDB の古い表（行形式が COMPACT・REDUNDANT）では、索引の長さの上限が 767 バイトで、`alias`（245 文字）全体の索引を utf8mb4 で作れない。baseline は、まず `ROW_FORMAT=DYNAMIC` にして作れるか確かめる。`DYNAMIC` にできない環境や、`DYNAMIC` にしても上限が 767 バイトの環境（MySQL 5.6 の Antelope、`innodb_large_prefix=OFF` など）では、`alias` の索引を前置き長のまま残し、ログ（種別 2）で知らせる。最終形との差になるが、動作には支障が無い。
