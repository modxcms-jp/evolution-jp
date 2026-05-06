# ExecPlan: evo system-upgrade コマンド実装

## Purpose / Big Picture

`php evo system-upgrade` 1コマンドで、GitHub最新リリースへのアップグレード前処理（DBバックアップ・ファイルバックアップ・メンテナンス化・ファイル差し替え）を自動実行する。完了後はブラウザでのアップグレード操作を案内して終了する。エラー発生時はジャーナル方式でロールバックし、元の状態に戻す。

## Progress

- [ ] (2026-05-16) Phase 1: 引数パース・事前チェック実装
- [ ] (2026-05-16) Phase 2: GitHub API 取得・zip ダウンロード・展開
- [ ] (2026-05-16) Phase 3: 確認プロンプト
- [ ] (2026-05-16) Phase 4: バックアップ（DB + ファイル）
- [ ] (2026-05-16) Phase 5: メンテナンス化（.htaccess 差し替え）
- [ ] (2026-05-16) Phase 6: ファイル差し替え（manager/ + assets/ + ルートファイル + config.inc.php）
- [ ] (2026-05-16) Phase 7: クリーンアップ・完了通知
- [ ] (2026-05-16) ロールバック処理の実装・検証

## Surprises & Discoveries

## Decision Log

- 2026-05-06 / yamamoto: `compose.yml` は `.gitattributes` に `export-ignore` 指定がないため zipball に含まれる。ルートファイルの上書き対象はパッケージ内容から動的に決定するが、サイト固有・環境依存ファイル（`compose.yml`, `compose.override.yml`, `.env`, `.htaccess`）は明示的な除外リスト（`$rootExclude`）でスキップする（PR #439 レビュー指摘により修正）。
- 2026-05-06 / yamamoto: `assets/images/` と `assets/files/` はバックアップ除外。サイトによっては大容量になるため。
- 2026-05-06 / yamamoto: `.htaccess.maintenance` はコマンドが自動生成する。`/install/` パスは通過させ、ブラウザからのアップグレード操作を可能にする。
- 2026-05-06 / yamamoto: バックアップ先は `temp/backup/migrate/YYYYMMDD_HHMMSS/`。`migrate/` サブディレクトリでDB（`db-backup.php` が使う `temp/backup/`）との混在を回避。
- 2026-05-06 / yamamoto: ロールバックはジャーナル配列に操作を記録し、エラー時に逆順で戻す方式。DBバックアップは自動ロールバック対象外（ファイルのみ戻す）。
- 2026-05-06 / yamamoto: ブラウザ操作が必要なアップグレード画面（install/）の自動化は対象外。

## Outcomes & Retrospective

## Context and Orientation

### ファイル構成（リポジトリルート相対）

- **エントリーポイント**: `evo`（PHP スクリプト、コマンド名をファイル名に変換してrequire）
- **ブートストラップ**: `manager/includes/cli/bootstrap.php`（`MODX_BASE_PATH` 等の定数定義、`$modx` 初期化）
- **ヘルパー関数**: `manager/includes/cli/cli-helpers.php`（`cli_out()`, `cli_err()`, `cli_usage()`, `cli_export_database()`）
- **既存コマンド例**: `manager/includes/cli/commands/db-backup.php`
- **実装対象**: `manager/includes/cli/commands/system-upgrade.php`（新規作成）

### コマンド名とファイル名の対応

`evo` スクリプトはコマンド名の `:` を `-` に変換してファイル検索する。  
`php evo system-upgrade` → `commands/system-upgrade.php`

### 重要な定数（bootstrap.php で定義済み）

- `MODX_BASE_PATH`: リポジトリルートの絶対パス（末尾 `/` 付き）
- `EVO_CLI_PATH`: `manager/includes/cli/` の絶対パス

### バックアップ除外ディレクトリ

- `assets/images/` — 旧MODX からの移行サイトで大容量になりやすい
- `assets/files/` — 同上

### config.inc.php の役割

`manager/includes/config.inc.php` にDB接続情報とサイト設定が記述されている。`manager/` を新版に差し替えた後、バックアップから必ずコピーしないとCMSが起動しない。

### GitHub Release zip の構造

`zipball_url` からダウンロードしたzipは、トップレベルに `modxcms-jp-evolution-jp-XXXXXXX/` のようなディレクトリが1つある。展開後にそのディレクトリを検出して操作する。

### PHP拡張の要件

- `ZipArchive`（zip展開に使用）— php-zip 拡張が必要
- `curl` または `allow_url_fopen=On`（GitHub APIアクセス）

## Plan of Work

1ファイル `commands/system-upgrade.php` に全実装をまとめる（既存コマンドと同じパターン）。

実装は次の7フェーズで構成する。各フェーズはファイル変更を伴う操作を行う前に完了条件を検証し、失敗時はロールバックして終了する。

**ロールバック方式**: `$journal` 配列に実施済み操作（種別・from/to）を記録し、エラー時に逆順で復元する。DBバックアップはロールバック対象外（ファイルのみ復元）。

**コミット分割方針**:
1. `feat(cli): system-upgrade コマンドの骨格実装（引数パース・GitHub取得・展開まで）`
2. `feat(cli): system-upgrade バックアップ・メンテナンス化・ファイル差し替え・ロールバック実装`

## Concrete Steps

### 事前準備

```bash
# Docker経由で実行（hostから直接実行するとmysqliが使えない）
docker compose exec <app-service> php evo system-upgrade --help
```

### Step 1: ファイル作成

対象: `manager/includes/cli/commands/system-upgrade.php`（新規）

**引数パース**:

```php
$yes    = in_array('--yes', $args);
$tag    = '';   // --tag=v1.4.0 形式で取得
$driver = 'mysqldump';
foreach ($args as $arg) {
    if (str_starts_with($arg, '--tag='))    $tag    = substr($arg, 6);
    if (str_starts_with($arg, '--driver=')) $driver = substr($arg, 9);
}
```

**事前チェック**:
- `ZipArchive` クラスが存在するか（`class_exists('ZipArchive')`）
- `MODX_BASE_PATH . 'manager/'` が存在するか
- `MODX_BASE_PATH . '.htaccess'` が存在するか（メンテナンス化の前提）

### Step 2: GitHub API で最新リリース情報取得

エラー抑制演算子（`@`）は使わず、`$http_response_header` と `error_get_last()` で失敗原因をユーザーに提示して中断する。

```php
$apiUrl = $tag !== ''
    ? "https://api.github.com/repos/modxcms-jp/evolution-jp/releases/tags/{$tag}"
    : 'https://api.github.com/repos/modxcms-jp/evolution-jp/releases/latest';

$ctx = stream_context_create(['http' => [
    'method'          => 'GET',
    'header'          => "User-Agent: evo-cli\r\nAccept: application/vnd.github+json\r\n",
    'timeout'         => 30,
    'ignore_errors'   => true,   // 4xx/5xx でも本文を取得してエラー内容を表示できるようにする
]]);
$json = file_get_contents($apiUrl, false, $ctx);
if ($json === false) {
    $err = error_get_last();
    cli_usage('GitHub API 取得失敗: ' . ($err['message'] ?? '不明なエラー'));
}
// HTTP ステータス確認（$http_response_header[0] 例: "HTTP/1.1 404 Not Found"）
if (!isset($http_response_header[0]) || strpos($http_response_header[0], '200') === false) {
    cli_usage('GitHub API エラー: ' . ($http_response_header[0] ?? '不明') . "\n" . substr($json, 0, 200));
}
$release = json_decode($json, true);
if (!is_array($release) || empty($release['zipball_url'])) {
    cli_usage('GitHub API レスポンスが不正です。tag 名を確認してください。');
}
// $release['tag_name'], $release['zipball_url'] を以降で使用
```

取得した `tag_name` と `zipball_url` を表示してユーザーに確認させる。

### Step 3: zip ダウンロード・展開

```php
$tmpZip    = MODX_BASE_PATH . 'temp/upgrade-' . date('YmdHis') . '.zip';
$extractTo = MODX_BASE_PATH . 'temp/upgrade-extract-' . date('YmdHis') . '/';
```

- `file_get_contents($zipball_url, ...)` でダウンロード（リダイレクト追跡が必要: `'follow_location' => 1`）
- `ZipArchive::open()` → `extractTo($extractTo)`
- 展開後のトップレベルディレクトリを検出 → `$pkgRoot`

`glob()` の結果が空の場合は明示的にエラー終了する（未定義オフセット防止）:

```php
$dirs = glob($extractTo . '*/');
if (empty($dirs)) {
    cli_usage('zip 展開後にパッケージディレクトリが見つかりません。アーカイブ構造を確認してください。');
}
$pkgRoot = $dirs[0];
// 最低限 manager/ が含まれていることを確認
if (!is_dir($pkgRoot . 'manager/')) {
    cli_usage("展開先 ({$pkgRoot}) に manager/ が存在しません。想定外のアーカイブ構造です。");
}
```

### Step 4: 確認プロンプト（--yes でスキップ）

```
リリース: v1.4.0
変更対象:
  manager/          → 新版に差し替え
  assets/           → 上書き（images/, files/ は除外）
  index.php 他      → 上書き（パッケージ内のルートファイル）
バックアップ先: temp/backup/migrate/20260516_120000/

続行しますか？ [y/N]:
```

### Step 5: バックアップディレクトリ作成

```php
$backupDir = MODX_BASE_PATH . 'temp/backup/migrate/' . date('Ymd_His') . '/';
mkdir($backupDir, 0755, true);
// .htaccess で外部アクセス拒否
file_put_contents($backupDir . '.htaccess', "order deny,allow\ndeny from all\n");
```

### Step 6: DB バックアップ

```php
$dbFile = $backupDir . 'db-' . date('Ymd_His') . '.sql';
cli_export_database($driver, $dbFile, [], true);
cli_out("DB backup: {$dbFile}");
```

`cli_export_database` は `cli-helpers.php` に定義済み。`--driver=php` でフォールバック可。

### Step 7: ファイルバックアップ（ジャーナル記録開始）

```php
$journal = [];

// manager/ を移動
rename(MODX_BASE_PATH . 'manager/', $backupDir . 'manager/');
$journal[] = ['type' => 'moved', 'from' => MODX_BASE_PATH . 'manager/', 'to' => $backupDir . 'manager/'];

// assets/ を再帰コピー（images/, files/ 除外）
$excludeDirs = ['images', 'files'];
upgrade_copy_dir(MODX_BASE_PATH . 'assets/', $backupDir . 'assets/', $excludeDirs);
$journal[] = ['type' => 'copied_dir', 'to' => $backupDir . 'assets/'];

// ルートファイル（パッケージ内ファイルのみ、除外リストを適用）をコピー
$rootExclude  = ['compose.yml', 'compose.override.yml', '.env', '.htaccess', '.htaccess.maintenance'];
$pkgRootFiles = upgrade_list_root_files($pkgRoot);  // glob($pkgRoot . '*') でファイルのみ
mkdir($backupDir . 'root/', 0755, true);
foreach ($pkgRootFiles as $file) {
    $name = basename($file);
    if (in_array($name, $rootExclude)) continue;
    if (is_file(MODX_BASE_PATH . $name)) {
        copy(MODX_BASE_PATH . $name, $backupDir . 'root/' . $name);
    }
}
$journal[] = ['type' => 'copied_root', 'to' => $backupDir . 'root/'];
```

`upgrade_copy_dir()` は再帰コピーで除外ディレクトリをスキップするローカル関数。

### Step 8: メンテナンス化

`.htaccess` はリネームではなくバックアップディレクトリへコピーしてから上書きする。これにより再実行時のファイル名衝突を防ぐ（`$backupDir` はタイムスタンプで一意）。

```php
// 元の .htaccess をバックアップディレクトリへコピー
copy(MODX_BASE_PATH . '.htaccess', $backupDir . '.htaccess.orig');

// .htaccess をメンテナンス内容で上書き
$maintenance = <<<'HTACCESS'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/install/
    RewriteRule ^ - [R=503,L]
</IfModule>
ErrorDocument 503 "System under maintenance. Please try again later."
HTACCESS;
file_put_contents(MODX_BASE_PATH . '.htaccess', $maintenance);
$journal[] = ['type' => 'overwritten', 'path' => MODX_BASE_PATH . '.htaccess', 'backup' => $backupDir . '.htaccess.orig'];
```

`/install/` パスのみ通過させ、アップグレード操作をブラウザから実行可能にする。

### Step 9: ファイル差し替え

assets/ とルートファイルの上書きもジャーナルに記録し、ロールバック時にバックアップから復元できるようにする。

```php
// 新 manager/ を配置（パッケージから）
upgrade_copy_dir($pkgRoot . 'manager/', MODX_BASE_PATH . 'manager/');
$journal[] = ['type' => 'placed_dir', 'path' => MODX_BASE_PATH . 'manager/'];

// config.inc.php をバックアップから復元（新 manager/ 配置直後に実施）
copy($backupDir . 'manager/includes/config.inc.php', MODX_BASE_PATH . 'manager/includes/config.inc.php');

// assets/ を上書き（images/, files/ 除外）
upgrade_copy_dir($pkgRoot . 'assets/', MODX_BASE_PATH . 'assets/', $excludeDirs);
$journal[] = ['type' => 'overwritten_dir', 'src' => $backupDir . 'assets/', 'dst' => MODX_BASE_PATH . 'assets/', 'exclude' => $excludeDirs];

// ルートファイルを上書き（除外リストを適用）
$overwrittenRootFiles = [];
foreach ($pkgRootFiles as $file) {
    $name = basename($file);
    if (in_array($name, $rootExclude)) continue;
    copy($file, MODX_BASE_PATH . $name);
    $overwrittenRootFiles[] = $name;
}
$journal[] = ['type' => 'overwritten_root', 'backup' => $backupDir . 'root/', 'files' => $overwrittenRootFiles];
```

### Step 10: クリーンアップ・完了通知

```php
// 展開用 temp ファイルを削除
unlink($tmpZip);
upgrade_rmdir($extractTo);

cli_out('');
cli_out('=== アップグレード前処理 完了 ===');
cli_out("バックアップ: {$backupDir}");
cli_out('');
cli_out('次の手順:');
cli_out('  1. ブラウザで /install/ を開いてアップグレードを実行してください');
cli_out("  2. 完了後、.htaccess をメンテナンス状態から元に戻してください:");
cli_out("       cp {$backupDir}.htaccess.orig .htaccess");
cli_out('  問題があった場合、上記バックアップから手動リストアできます');
```

### ロールバック実装

エラー発生時に呼び出す `upgrade_rollback($journal)`。ジャーナルの逆順で処理し、各操作を復元する。

```php
function upgrade_rollback(array $journal): void {
    foreach (array_reverse($journal) as $op) {
        switch ($op['type']) {
            case 'moved':
                // 新 manager/ が置かれていれば先に削除してからバックアップを戻す
                if (is_dir($op['from'])) upgrade_rmdir($op['from']);
                if (is_dir($op['to']))   rename($op['to'], $op['from']);
                break;
            case 'overwritten':
                // .htaccess → バックアップから復元
                if (is_file($op['backup'])) copy($op['backup'], $op['path']);
                break;
            case 'placed_dir':
                // 新しく配置したディレクトリを削除（moved の復元で元が戻る）
                if (is_dir($op['path'])) upgrade_rmdir($op['path']);
                break;
            case 'overwritten_dir':
                // assets/ → バックアップから上書き復元
                if (is_dir($op['src'])) upgrade_copy_dir($op['src'], $op['dst'], $op['exclude']);
                break;
            case 'overwritten_root':
                // ルートファイル → バックアップから1ファイルずつ復元
                foreach ($op['files'] as $name) {
                    $src = $op['backup'] . $name;
                    if (is_file($src)) copy($src, MODX_BASE_PATH . $name);
                }
                break;
            // copied_dir / copied_root はバックアップ自体なので復元不要
        }
    }
}
```

各フェーズのエラー処理:

```php
try {
    // Step 7〜9 の処理
} catch (Throwable $e) {
    cli_err('エラー: ' . $e->getMessage());
    cli_err('ロールバック中...');
    upgrade_rollback($journal);
    cli_err('ロールバック完了。元の状態に戻りました。');
    exit(1);
}
```

### ローカル関数一覧

コマンドファイル末尾に定義するローカル関数:

| 関数名 | 役割 |
|--------|------|
| `upgrade_copy_dir($src, $dst, $exclude=[])` | 再帰コピー（除外ディレクトリ対応） |
| `upgrade_rmdir($path)` | 再帰削除 |
| `upgrade_list_root_files($dir)` | ルートのファイルのみ列挙（ディレクトリ除外） |
| `upgrade_rollback(array $journal)` | ジャーナル逆順でロールバック |

## Validation and Acceptance

### 正常系

```bash
# dry runで確認（--yesなし）
docker compose exec <app-service> php evo system-upgrade
# → リリース情報・変更対象一覧が表示され、確認プロンプトで止まる

# 実行
docker compose exec <app-service> php evo system-upgrade --yes
# → 各フェーズのログが順に流れる
# → 完了後に "次の手順:" とブラウザ操作案内が表示される
```

完了後に確認すること:
- `temp/backup/migrate/YYYYMMDD_HHMMSS/` が存在し `db-*.sql`, `manager/`, `assets/`, `root/`, `.htaccess.orig` が格納されている
- `manager/includes/config.inc.php` が存在し、元の接続情報が入っている
- `.htaccess` がメンテナンス内容（503返却）になっている
- `temp/backup/migrate/YYYYMMDD_HHMMSS/.htaccess.orig` に元の `.htaccess` が保存されている
- ブラウザで `/install/` にアクセスできる（サイト本体は503）

### エラー系

展開後のStep 9（ファイル差し替え中）で強制的にエラーを起こし:
- `manager/` が元の場所に戻っていること
- `.htaccess` が元の内容に戻っていること（バックアップの `.htaccess.orig` から復元）
- `assets/` のファイルがバックアップから復元されていること
- `cli_err` に「ロールバック完了」メッセージが出力されていること

### バージョン指定

```bash
docker compose exec <app-service> php evo system-upgrade --tag=v1.3.0
# → v1.3.0 のリリースが取得されることを確認
```

## Idempotence and Recovery

このコマンドはバックアップ先をタイムスタンプで一意化しているため、複数回実行しても既存バックアップを上書きしない。

中断時は:
1. `temp/backup/migrate/YYYYMMDD_HHMMSS/` の中を確認してバックアップ状況を把握する
2. `.htaccess` をメンテナンス解除するには `cp temp/backup/migrate/YYYYMMDD_HHMMSS/.htaccess.orig .htaccess`
3. `manager/` が欠損している場合は `cp -r temp/backup/migrate/.../manager/ manager/` で戻す

なお、`.htaccess` のバックアップは実行ごとにタイムスタンプ付きのバックアップディレクトリ内に `.htaccess.orig` として保存されるため、複数回実行しても衝突しない。

## Artifacts and Notes

- 実装対象: `manager/includes/cli/commands/system-upgrade.php`
- ロードマップ: `.agent/roadmap.md` の「evo system-upgrade コマンド実装」
- 参考コマンド（パターン）: `commands/db-backup.php`, `commands/health-check.php`
- ヘルパー: `manager/includes/cli/cli-helpers.php`（`cli_export_database` 等）

**後続タスクへの参照**:  
このコマンドの設計（バックアップ戦略・ジャーナル方式ロールバック・GitHub API 取得・ファイル差し替え順序・config.inc.php 引き継ぎ）は、ロードマップの「オンラインアップデート機構（基本設計）」タスクの設計入力として活用できる。特に以下が再利用・発展の起点になる：
- `temp/backup/migrate/YYYYMMDD_HHMMSS/` バックアップ構造
- ジャーナル配列によるロールバック方式
- `assets/images/`, `assets/files/` 除外ルール
- `/install/` のみ通過させるメンテナンス `.htaccess` パターン

**想定コミット**:
1. `feat(cli): system-upgrade コマンド骨格（引数・GitHub取得・展開）`  
   対象: `commands/system-upgrade.php`（Phase 1〜3）
2. `feat(cli): system-upgrade バックアップ・差し替え・ロールバック実装`  
   対象: `commands/system-upgrade.php`（Phase 4〜7 + ロールバック）

## Interfaces and Dependencies

- **GitHub API**: `api.github.com/repos/modxcms-jp/evolution-jp/releases/latest`（User-Agent必須）
- **PHP拡張**: `ZipArchive`（php-zip）、`mysqli`（bootstrap経由で検証済み）
- **`cli_export_database()`**: `cli-helpers.php` に定義、`--driver=mysqldump|php` 対応
- **`allow_url_fopen`**: GitHub からのダウンロードに使用（`file_get_contents` + stream context）
- **`MODX_BASE_PATH`**: bootstrap.php で定義済み
