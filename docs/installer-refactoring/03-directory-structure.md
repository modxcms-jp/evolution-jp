# 新しいディレクトリ構造

## 最終形（Phase 10完了後）

```
install/
├── bootstrap.php                     # メインエントリーポイント
├── autoload.php                      # PSR-4オートローダー
├── index.php                         # 後方互換性（bootstrap.phpを呼ぶ）
│
├── src/                              # すべての新しいコード
│   ├── Core/
│   │   ├── Application.php           # アプリケーション本体
│   │   ├── Container.php             # DIコンテナ
│   │   └── Config.php                # 設定管理
│   │
│   ├── Installer/
│   │   ├── InstallerInterface.php
│   │   ├── AbstractInstaller.php     # 共通処理
│   │   ├── NewInstaller.php          # 新規インストール専用
│   │   ├── Upgrader.php              # アップグレード専用
│   │   ├── UpgradeDetector.php       # アップグレード判定
│   │   ├── CleanupService.php        # クリーンアップ処理
│   │   └── InstallDirectoryManager.php # installディレクトリ管理
│   │
│   ├── Database/
│   │   ├── Connection.php            # PDO接続管理
│   │   ├── QueryBuilder.php          # クエリビルダー
│   │   ├── SqlParser.php             # SQLファイル実行
│   │   └── CategoryManager.php       # カテゴリ管理
│   │
│   ├── Asset/
│   │   ├── AssetInstaller.php        # 基底クラス
│   │   ├── AssetCollector.php        # アセット情報収集
│   │   ├── AssetComparator.php       # バージョン比較
│   │   ├── PropertyMerger.php        # プロパティマージ
│   │   ├── TemplateInstaller.php     # テンプレート
│   │   ├── TvInstaller.php           # テンプレート変数
│   │   ├── ChunkInstaller.php        # チャンク
│   │   ├── SnippetInstaller.php      # スニペット
│   │   ├── PluginInstaller.php       # プラグイン
│   │   └── ModuleInstaller.php       # モジュール
│   │
│   ├── Validator/
│   │   ├── EnvironmentValidator.php  # 環境検証
│   │   ├── RequirementChecker.php    # システム要件
│   │   ├── DirectoryChecker.php      # ディレクトリ権限
│   │   ├── DatabaseChecker.php       # DB接続・権限
│   │   ├── PasswordValidator.php     # パスワード強度
│   │   ├── SystemRequirementChecker.php # PHP/拡張チェック
│   │   └── UpgradePathValidator.php  # アップグレードパス検証
│   │
│   ├── Security/
│   │   ├── UpgradeAuthenticator.php  # アップグレード認証
│   │   ├── CsrfProtection.php        # CSRF保護
│   │   └── PasswordHasher.php        # パスワードハッシュ（phpassラッパー）
│   │
│   ├── Session/
│   │   ├── InstallSession.php        # セッション管理
│   │   └── SessionData.php           # セッションデータDTO
│   │
│   ├── Http/
│   │   ├── Request.php               # リクエスト抽象化
│   │   ├── Response.php              # レスポンス抽象化
│   │   └── Controller/
│   │       ├── AbstractController.php
│   │       ├── ModeController.php
│   │       ├── ConnectionController.php
│   │       ├── OptionsController.php
│   │       ├── SummaryController.php
│   │       └── InstallController.php
│   │
│   ├── View/
│   │   ├── Renderer.php              # テンプレートレンダラー
│   │   └── ViewHelper.php            # ビューヘルパー
│   │
│   ├── Backup/
│   │   ├── DatabaseBackup.php        # DBバックアップ
│   │   └── FileBackup.php            # ファイルバックアップ
│   │
│   ├── Migration/
│   │   ├── Utf8mb4Converter.php      # UTF-8mb4変換
│   │   └── MigrationManager.php      # マイグレーション管理
│   │
│   ├── Logger/
│   │   └── InstallLogger.php         # ログ記録
│   │
│   ├── Error/
│   │   └── ErrorFormatter.php        # エラーメッセージ整形
│   │
│   ├── Progress/
│   │   └── ProgressTracker.php       # 進捗追跡
│   │
│   ├── Support/
│   │   ├── FileHelper.php            # ファイル操作
│   │   ├── DocBlockParser.php        # DocBlock解析
│   │   └── LanguageHelper.php        # 言語関連
│   │
│   └── Cli/
│       └── InstallCommand.php        # CLIコマンド
│
├── config/
│   ├── installer.php                 # インストーラ設定
│   ├── routes.php                    # ルート定義
│   └── container.php                 # DI定義
│
├── resources/
│   ├── views/                        # ビューテンプレート
│   │   ├── layouts/
│   │   │   └── main.php
│   │   ├── pages/
│   │   │   ├── mode.php
│   │   │   ├── connection.php
│   │   │   ├── options.php
│   │   │   ├── summary.php
│   │   │   ├── install.php
│   │   │   ├── install_progress.php
│   │   │   ├── upgrade_auth.php
│   │   │   └── upgrade_backup.php
│   │   ├── partials/
│   │   │   ├── session_problem.php
│   │   │   └── error_display.php
│   │   └── templates/
│   │       ├── config.inc.php
│   │       ├── robots.txt
│   │       └── web.config
│   │
│   ├── lang/                         # 言語ファイル
│   │   ├── en.php
│   │   └── ja.php
│   │
│   └── sql/                          # SQLファイル
│       ├── create_tables.sql
│       ├── default_settings.sql
│       ├── default_settings_custom.sql
│       ├── fix_settings.sql
│       └── sample_data.sql
│
├── public/                           # 静的ファイル
│   ├── img/
│   ├── css/
│   │   └── installer.css
│   └── js/
│       └── installer.js
│
├── tests/                            # テスト
│   ├── Unit/
│   │   ├── DatabaseTest.php
│   │   ├── SqlParserTest.php
│   │   ├── InstallerTest.php
│   │   └── Asset/
│   └── Integration/
│       ├── NewInstallTest.php
│       └── UpgradeTest.php
│
├── docs/                             # ドキュメント
│   └── installer-refactoring/
│       ├── 00-overview.md
│       ├── 01-refactoring-plan.md
│       ├── 02-security-improvements.md
│       └── 03-directory-structure.md
│
├── cli.php                           # CLIエントリーポイント
│
└── [既存ファイル - 段階的に削除]
    ├── instprocessor.php
    ├── functions.php
    ├── setup.info.php
    ├── sqlParser.class.php
    ├── convert2utf8mb4.php
    ├── connection.databasetest.php
    ├── connection.servertest.php
    ├── actions/
    ├── processors/
    ├── tpl/
    └── langs/
```

## Phase 1実装時（初期構造）

```
install/
├── src/
│   └── .gitkeep
├── config/
│   └── .gitkeep
├── resources/
│   ├── views/
│   │   └── .gitkeep
│   ├── lang/
│   │   └── .gitkeep
│   └── sql/              # 既存のsql/から移動
├── tests/
│   ├── Unit/
│   │   └── .gitkeep
│   └── Integration/
│       └── .gitkeep
├── docs/
│   └── installer-refactoring/
│       ├── 00-overview.md
│       ├── 01-refactoring-plan.md
│       ├── 02-security-improvements.md
│       └── 03-directory-structure.md
├── bootstrap.php         # 新規作成
├── autoload.php          # 新規作成
└── [既存のファイル全て保持]
```

## 名前空間

すべての新しいクラスは以下の名前空間を使用：

```
EvolutionCMS\Install\{サブ名前空間}\{クラス名}
```

例：
- `EvolutionCMS\Install\Core\Application`
- `EvolutionCMS\Install\Installer\NewInstaller`
- `EvolutionCMS\Install\Database\Connection`
- `EvolutionCMS\Install\Security\UpgradeAuthenticator`

## オートロード

PSR-4準拠：

```php
// install/autoload.php
spl_autoload_register(function ($class) {
    $prefix = 'EvolutionCMS\\Install\\';
    $base_dir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
```

## ファイル命名規則

- **クラスファイル**: PascalCase (例: `NewInstaller.php`)
- **設定ファイル**: snake_case (例: `installer.php`)
- **ビューファイル**: snake_case (例: `install_progress.php`)
- **テストファイル**: `{ClassName}Test.php` (例: `InstallerTest.php`)

## コーディング規約

- PSR-12準拠
- 名前空間の使用
- タイプヒンティング
- 戻り値の型宣言
- strict_types有効化

```php
<?php
declare(strict_types=1);

namespace EvolutionCMS\Install\Installer;

class NewInstaller extends AbstractInstaller
{
    public function install(): bool
    {
        // ...
    }
}
```
