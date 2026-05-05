# ExecPlan: マイグレーション機構のモダン化

## Purpose / Big Picture

DBスキーマ変更の適用履歴を `modx_migrations` テーブルで一元管理し、連番マイグレーションファイルで変更を記述する仕組みを導入する。現在の「バージョン不一致時に全処理を強制実行する二重経路」を廃止し、適用済みかどうかを判定してから実行する形に移行することで、アップグレード時の差分適用を安全かつ再現可能にする。

## Progress

- [ ] (2026-05-06) `modx_migrations` テーブルを `create_tables.sql` に追加する
- [ ] (2026-05-06) マイグレーションランナーを実装する
- [ ] (2026-05-06) `00000000_baseline.php` を作成して既存処理を移植する
- [ ] (2026-05-06) `mutate_settings.dynamic.php` のトリガーをランナーに差し替える
- [ ] (2026-05-06) 旧 `upgrades.php` / `upd_db_structure.php` を削除する
- [ ] (2026-05-06) 検証（新規インストール・旧バージョンからのアップグレード・再実行）

## Surprises & Discoveries

## Decision Log

- 2026-05-06 / ロールバック（down）は不要。マイグレーションは一方向（up）のみ。
- 2026-05-06 / 既存処理はすべて `00000000_baseline.php` 一本にまとめる（分割はしない）。
- 2026-05-06 / 「毎回実行」の関数群（`update_tbl_user_roles` 等）も一度だけ実行すれば十分とし、マイグレーションに統合する。ユーザーが意図的にデフォルトを変更するのはシステムが関知しない。
- 2026-05-06 / ブートストラップ: `modx_migrations` が存在しない状態で `system_settings` に `settings_version` キーが存在する → 旧バージョンからのアップグレードと判定し、baselineを実行する。存在しない → 新規インストールと判定し、baselineを実行せず適用済みとして記録する。`site_content` の有無より意味が明確で、インストール状態の正式な指標。
- 2026-05-06 / トリガー統一（インストーラ・CLIからも呼べるようにする）は次フェーズとし、今回は既存のトリガーポイント（設定ページを開いたとき）を維持する。
- 2026-05-06 / マイグレーションIDは `YYYYMMDD_説明` 形式（例: `20260601_add_new_column`）。baselineは特別扱いで `00000000_baseline`。
- 2026-05-06 / `default.config.php` のバージョン別分岐（1.0.5J-r11 等の極旧JP版向け）はスコープ外。既存実装のまま残す。
- 2026-05-06 / baseline の実装は `upd_db_structure.php` のコピーではなく、`release-1.0.0J:install/setup.sql` と現在の `create_tables.sql` の差分を正として書き起こす。`upd_db_structure.php` は15年近くの積み重ねで漏れがある（`site_plugins.error_reporting`、`site_snippets.error_reporting` 等）。差分比較スクリプトをExecPlan策定時に実施し、結果を Plan of Work に記録済み。
- 2026-05-06 / `00000000_baseline.php` のエラーハンドリングは現状と同じく DBラッパー任せ。`db()->exec()` は errno 1060/1061/1091/1054/1064 を黙って無視し、それ以外は `messageQuit()` で停止する。`db()->fieldExists()` 等の存在チェックは無駄クエリ削減のためであり、移植時もそのまま維持する。

## Outcomes & Retrospective

## Context and Orientation

**現状のファイル構成（変更前）**

```
manager/includes/upgrades/
  upgrades.php          ← バージョン比較ゲート付きのデータ変換（毎回実行含む）
  upd_db_structure.php  ← ALTER TABLE + データ修正（カラム存在チェック付き）
install/sql/
  create_tables.sql     ← CREATE TABLE IF NOT EXISTS（新規・アップグレード共用）
```

**トリガーポイント**

`manager/actions/tool/mutate_settings.dynamic.php` 23行目:

```php
if ($settings_version && $settings_version != $modx_version) {
    include_once(MODX_CORE_PATH . 'upgrades/upgrades.php');
}
```

`$settings_version` はDBの `system_settings.setting_name='settings_version'` の値。
`$modx_version` はコードの現在バージョン（`manager/includes/version.inc.php` で定義、現在 `1.2.1J`）。

**変更後のファイル構成**

```
manager/includes/migrations/
  runner.php            ← 新規: マイグレーションランナー
  00000000_baseline.php ← 新規: 旧 upgrades.php + upd_db_structure.php の全内容を移植
  YYYYMMDD_xxx.php      ← 以後の新規マイグレーション（将来追加）
install/sql/
  create_tables.sql     ← modx_migrations テーブルを追加
```

**`modx_migrations` テーブル**

```sql
CREATE TABLE IF NOT EXISTS `{prefix}modx_migrations` (
    `id`         VARCHAR(100) NOT NULL,
    `applied_at` DATETIME     NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Plan of Work

### マイグレーションランナーの責務

1. `modx_migrations` テーブルが存在しなければ作成する（ブートストラップ）
2. ブートストラップ直後、`site_content` テーブルの存在でアップグレードか新規かを判定し、baselineの実行要否を決定する
3. `migrations/` ディレクトリをスキャンし、ファイル名の辞書順（= タイムスタンプ順）で並べる
4. `modx_migrations` に記録されていないIDのみ順次実行する
5. 実行成功後、`modx_migrations` にIDと実行日時を INSERT する

### baselineの実装方針

`00000000_baseline.php` の内容は `upd_db_structure.php` のコピーではなく、**v1.0.0J 新規インストール時のスキーマ（`release-1.0.0J` タグの `install/setup.sql`）と現在の `create_tables.sql` の差分** を正として実装する。`upd_db_structure.php` は15年近くの積み重ねで漏れが生じており（`site_plugins.error_reporting` 等）正とすべきでない。

差分の正確な把握には、`release-1.0.0J:install/setup.sql` を Git から取得して現在版と比較したスクリプト（本ExecPlan策定時に実施）の結果を使う。

実装対象の差分（確認済み）:

#### ADD COLUMN（カラム存在チェック付きで実行）

| テーブル | 追加するカラム | 定義 |
| --- | --- | --- |
| `site_content` | `alias_visible` | `INT(2) NOT NULL DEFAULT '1'` |
| `site_htmlsnippets` | `published` | `int(1) NOT NULL DEFAULT '1'` |
| `site_htmlsnippets` | `pub_date` | `int(20) NOT NULL DEFAULT '0'` |
| `site_htmlsnippets` | `unpub_date` | `int(20) NOT NULL DEFAULT '0'` |
| `site_plugins` | `error_reporting` | `varchar(8) NOT NULL DEFAULT 'inherit'` |
| `site_snippets` | `error_reporting` | `varchar(8) NOT NULL DEFAULT 'inherit'` |
| `site_templates` | `parent` | `int(10) NOT NULL DEFAULT '0'` |
| `user_attributes` | `street` | `varchar(255) NOT NULL DEFAULT ''` |
| `user_attributes` | `city` | `varchar(255) NOT NULL DEFAULT ''` |
| `web_user_attributes` | `street` | `varchar(255) NOT NULL DEFAULT ''` |
| `web_user_attributes` | `city` | `varchar(255) NOT NULL DEFAULT ''` |
| `user_roles` | `move_document` | `int(1) NOT NULL DEFAULT '0'` |
| `user_roles` | `remove_locks` | `int(1) NOT NULL DEFAULT '0'` |
| `user_roles` | `view_schedule` | `int(1) NOT NULL DEFAULT '0'` |

#### MODIFY COLUMN（実害のある型変更のみ対象）

| テーブル | カラム | 変更内容 |
| --- | --- | --- |
| `active_users` | `ip` | `varchar(20)` → `varchar(50)`（IPv6対応） |
| `site_content` | `alias` | `varchar(255)` → `varchar(245)` |
| `site_tmplvar_contentvalues` | `value` | `text` → `mediumtext` |
| `user_attributes` | `comment` | `varchar(255)` → `text` |
| `web_user_attributes` | `comment` | `varchar(255)` → `text` |
| `web_user_attributes` | `country` | `varchar(5)` → `varchar(25)` |

#### その他（`upd_db_structure.php` から継続して扱う処理）

- `site_content_ft_idx` インデックスの削除・`typeidx` の追加
- `site_plugin_events` / `site_tmplvar_templates` のPRIMARY KEY再定義
- `site_content.type` が空のレコードへのコンテンツタイプ推定UPDATE

#### データ変換（`upgrades.php` から引き継ぐ処理）

- `validate_referer` 設定削除、`upload_maxsize` / `emailsender` デフォルト修正
- `custom_contenttype` / `auto_template_logic` 設定の更新
- 旧プラグイン無効化（`Bindings機能の有効無効`・`Bottom Button Bar`・`Inherit Parent Template` 等）
- `topmenu_site` のデフォルト値更新
- `update_tbl_user_roles`（`save_role=1` のロールへの権限付与）
- `disableOldCarbonTheme` / `disableOldFckEditor`

#### スコープ外（差分はあるが対処不要）

- `integer` → `int(11)`、`tinyint` → `tinyint(4)`、`DEFAULT 0` → `DEFAULT '0'` 等のコメント・表記上の差異（MySQLでは同義）
- テーブルエンジン（MyISAM→InnoDB）: インストーラの `{TABLE_OPTION}` 展開で処理済み、アップグレード時は `convert2utf8mb4()` で対処
- 削除されたテーブル（`event_log`, `keyword_xref`, `site_content_metatags`, `site_keywords`, `site_metatags`）: 旧DBに残存しても実害なし

### トリガーの差し替え

`mutate_settings.dynamic.php` の `include_once('upgrades/upgrades.php')` を `include_once` + ランナー呼び出しに差し替える。バージョン不一致チェックの条件式は**残す**（バージョンが一致している場合はマイグレーション不要のため）。

### settings_version の更新タイミング

現状は `save_settings.processor.php` 内で `settings_version = getNewVersion()` を保存することでバージョン不一致を解消している。この挙動はそのまま維持する。

## Concrete Steps

### Step 1: `create_tables.sql` に `modx_migrations` テーブルを追加

編集対象: `install/sql/create_tables.sql`

末尾に追加:

```sql
CREATE TABLE IF NOT EXISTS `[+prefix+]modx_migrations` (
    `id`         VARCHAR(100) NOT NULL,
    `applied_at` DATETIME     NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

確認: `docker compose exec <app> php evo db:tables --pattern=modx_migrations` でテーブルが存在すること。

### Step 2: マイグレーションランナーを作成

新規作成: `manager/includes/migrations/runner.php`

```php
<?php
function evo_run_migrations(): void
{
    $prefix = db()->config['table_prefix'];
    $migrationsTable = "{$prefix}modx_migrations";

    // ブートストラップ: テーブルが存在しない場合は作成してbaselineの実行要否を決定
    $tableExists = db()->tableExists($migrationsTable);
    $isUpgrade = null;
    if (!$tableExists) {
        $settingsVersion = db()->getValue(
            db()->select('setting_value', "{$prefix}system_settings", "setting_name='settings_version'")
        );
        $isUpgrade = !empty($settingsVersion);
    }

    if (!$tableExists) {
        db()->query(
            "CREATE TABLE IF NOT EXISTS `{$migrationsTable}` ("
            . "`id` VARCHAR(100) NOT NULL, "
            . "`applied_at` DATETIME NOT NULL, "
            . "PRIMARY KEY (`id`)"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // 適用済みIDを取得
    $rs = db()->select('id', $migrationsTable);
    $applied = [];
    while ($row = db()->getRow($rs)) {
        $applied[$row['id']] = true;
    }

    // マイグレーションファイルを辞書順でスキャン
    $dir = __DIR__;
    $files = glob($dir . '/*.php');
    if (!$files) {
        return;
    }
    sort($files);

    foreach ($files as $file) {
        if (basename($file) === 'runner.php') {
            continue;
        }
        $id = basename($file, '.php');

        // ブートストラップ: 新規インストールの場合はbaselineをスキップして記録のみ
        if ($id === '00000000_baseline' && $isUpgrade === false) {
            evo_mark_migration_applied($migrationsTable, $id);
            continue;
        }

        if (isset($applied[$id])) {
            continue;
        }

        $fn = require $file;
        if (is_callable($fn)) {
            $fn();
        }

        evo_mark_migration_applied($migrationsTable, $id);
    }
}

function evo_mark_migration_applied(string $table, string $id): void
{
    db()->query(
        "INSERT IGNORE INTO `{$table}` (`id`, `applied_at`) VALUES ("
        . db()->escape($id) . ", NOW())"
    );
}
```

### Step 3: `00000000_baseline.php` を作成

新規作成: `manager/includes/migrations/00000000_baseline.php`

内容は `upd_db_structure.php` のコピーではなく、**`release-1.0.0J:install/setup.sql` と現在の `create_tables.sql` の差分** を正として実装する。実装対象の一覧は「Plan of Work → baselineの実装方針」を参照。

クロージャ形式で返す（グローバル関数汚染を避けるため）:

```php
<?php
return function () {
    $prefix = db()->config['table_prefix'];

    // 1. ADD COLUMN（カラム存在チェック付き）
    // 2. MODIFY COLUMN（実害のある型変更）
    // 3. インデックス再定義（site_content, site_plugin_events 等）
    // 4. site_content.type の推定UPDATE
    // 5. upgrades.php のデータ変換（設定値更新・旧プラグイン無効化等）
};
```

実装時の注意:

- `upd_db_structure.php` はパターン（`db()->fieldExists()` によるガード等）の参考にできるが、正としない
- `upgrades.php` の `run_update()` 内のバージョンゲート処理（`version_compare`）も取り込む
- `global $default_config` は `include_once(MODX_CORE_PATH . 'default.config.php')` に置き換え
- `$prefix` は `db()->config['table_prefix']` から取得

### Step 4: `mutate_settings.dynamic.php` のトリガーを差し替え

編集対象: `manager/actions/tool/mutate_settings.dynamic.php` 23〜25行目

変更前:

```php
if ($settings_version && $settings_version != $modx_version) {
    include_once(MODX_CORE_PATH . 'upgrades/upgrades.php');
}
```

変更後:

```php
if ($settings_version && $settings_version != $modx_version) {
    include_once(MODX_CORE_PATH . 'migrations/runner.php');
    evo_run_migrations();
    evo()->clearCache();
}
```

`clearCache()` を `upgrades.php` から移動させる（旧ファイル内に記述されていた）。

### Step 5: 旧ファイルを削除

削除対象:

- `manager/includes/upgrades/upgrades.php`
- `manager/includes/upgrades/upd_db_structure.php`

ディレクトリ `manager/includes/upgrades/` も空になれば削除する。

## Validation and Acceptance

### 事前確認: baseline の網羅性チェック（実装前）

baseline に移植漏れがないかを Git 履歴で確認する。

```bash
git log --oneline -- manager/includes/upgrades/upd_db_structure.php
git log --oneline -- manager/includes/upgrades/upgrades.php
```

各コミットで加えられたカラム追加・データ変換が `00000000_baseline.php` に含まれていることを目視確認する。
あわせて `install/sql/create_tables.sql` の現在の状態と baseline の内容を突き合わせ、「新規インストール時に作られるスキーマ」と「baseline 実行後のスキーマ」が一致することを確認する。

### テストケース 1: 現在の開発環境（アップグレードパス）

現在の開発環境は `modx_migrations` テーブルなし・`system_settings` に `settings_version` あり、という状態であり、「新機構導入前の既存インストール」そのものである。追加の準備なしにアップグレードパスを検証できる。

1. 実装後、設定ページ（管理画面 → システム設定）を開く
2. `modx_migrations` テーブルが作成され、`id='00000000_baseline'` の行が1件記録されている

   ```bash
   docker compose exec <app> php evo db:describe modx_migrations
   # SELECT * FROM modx_migrations で確認
   ```

3. 設定ページを再度開いても INSERT が走らない（`modx_migrations` のレコード数が増えない）
4. スキーマが壊れていないこと

   ```bash
   docker compose exec <app> php evo db:describe site_content
   docker compose exec <app> php evo health-check
   ```

### テストケース 2: v1.0.0J からのアップグレード（フィクスチャ使用）

v1.0.0J の新規インストール直後のスキーマを再現したフィクスチャを使って動作確認する。

フィクスチャは `test/fixtures/v1.0.0j_fresh_install.sql` として作成する。ベースは Git タグ `release-1.0.0J` の `install/setup.sql` から `upd_db_structure.php` が操作するテーブルのみ抜粋し、`system_settings` に `settings_version='1.0.0J'` を追加したもの。

v1.0.0J では以下のカラムが欠けており、baseline の ADD COLUMN が実際に実行されることを確認する：

| テーブル | 欠けているカラム |
| --- | --- |
| `site_content` | `alias_visible` |
| `site_htmlsnippets` | `published`, `pub_date`, `unpub_date` |
| `site_templates` | `parent` |
| `user_attributes` | `street`, `city` |
| `web_user_attributes` | `street`, `city` |
| `user_roles` | `remove_locks`, `view_schedule`, `move_document` |

確認手順:

1. 別の DB（または別スキーマ）にフィクスチャを流し込む
2. その DB を向いた状態で設定ページを開く
3. baseline が実行され、上記カラムが追加されている
4. `modx_migrations` に `id='00000000_baseline'` が記録されている
5. 設定ページを再度開いても再実行されない

### テストケース 3: 新規インストール

1. まっさらな DB でインストーラを新規実行する
2. `modx_migrations` テーブルが存在し `id='00000000_baseline'` が1件ある
3. `system_settings` に `settings_version` が書き込まれていること（インストーラが書く）
4. 設定ページを開いても baseline が再実行されない

### テストケース 3: 新規マイグレーションの追加

1. テスト用ファイル `manager/includes/migrations/20260505_test.php` を作成する（副作用のない処理、例: `db()->query("SELECT 1")`）
2. 設定ページを開くと実行され、`modx_migrations` に `id='20260505_test'` が記録される
3. 再度開いても記録が増えない
4. テストファイルを削除する（本番コードには含めない）

## Idempotence and Recovery

- `00000000_baseline.php` の内部処理は元々冪等（カラム存在チェック・`IF NOT EXISTS`・条件付きUPDATE）
- ランナーは `INSERT IGNORE` を使うため、二重登録は発生しない
- 途中でマイグレーションが失敗した場合、そのIDは `modx_migrations` に記録されないため、次回再実行される
- ランナー自体はトランザクションを張らない（既存DBラッパーが対応していないため）

## Artifacts and Notes

- `manager/actions/tool/mutate_settings.dynamic.php`: トリガーポイント（23行目）
- `manager/includes/version.inc.php`: `$modx_version` 定義（現在 `1.2.1J`）
- `manager/processors/save_settings.processor.php`: `settings_version` の更新箇所（変更なし）
- `install/sql/create_tables.sql`: テーブル定義の追加先
- `manager/includes/default.config.php` 123〜136行: 極旧JP版向けのバージョン分岐（今回スコープ外、既存コードのまま）

## Interfaces and Dependencies

- **依存**: `db()` グローバルヘルパー（`tableExists`・`fieldExists`・`query`・`select`・`getRow`・`escape` を使用）
- **依存**: `evo()` グローバルヘルパー（`clearCache`・`logEvent`・`regOption` 等をbaselineで使用）
- **影響なし**: インストーラ（`create_tables.sql` の追加のみで既存フローに影響しない）
- **将来の統合先**: CLIコマンド・インストーラからも `evo_run_migrations()` を呼び出せる設計にしてある
