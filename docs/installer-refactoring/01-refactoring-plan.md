# 段階的リファクタリング計画

## 基本方針

1. **既存コードとの互換性を維持**
2. **段階的な移行（ビッグバン方式を避ける）**
3. **各Phaseでテスト可能な状態を維持**
4. **新旧コードの共存期間を設ける**

## Phase 1: 基礎構築（1-2週間）

### 目標

新しいアーキテクチャの基盤を構築し、既存コードと共存できる状態にする。

### 作業内容

1. **ディレクトリ構造の作成**

```
install/
├── bootstrap.php                 # 新しいエントリーポイント
├── autoload.php                  # PSR-4オートローダー
├── index.php                     # 既存（保持、将来的にbootstrap.phpを呼ぶ）
├── src/                          # 新規作成
│   ├── Core/
│   │   ├── Application.php
│   │   ├── Container.php
│   │   └── Config.php
│   └── .gitkeep
├── config/                       # 新規作成
│   ├── installer.php
│   └── .gitkeep
└── [既存ファイル・ディレクトリ]
```

2. **オートローダーの実装**

```php
// install/autoload.php
spl_autoload_register(function ($class) {
    $prefix = 'EvolutionCMS\\Install\\';
    $base_dir = __DIR__ . '/src/';
    // PSR-4実装
});
```

3. **基本クラスの作成**
   - `Application.php` - アプリケーション本体
   - `Container.php` - 軽量DIコンテナ
   - `Config.php` - 設定管理

### 成果物

- 新しいディレクトリ構造
- 動作する基本クラス
- 既存コードは引き続き動作

### テスト

- 新しいbootstrap.phpが正常に読み込まれる
- オートローダーが正しく動作する

## Phase 2: Database層の移行（1週間）

### 目標

コアに依存しない独立したDB抽象化レイヤーを作成。

### 作業内容

1. **Eloquent風クエリビルダーの実装**

```
src/
└── Database/
    ├── Connection.php          # PDOラッパー
    ├── QueryBuilder.php        # Eloquent風クエリビルダー
    ├── Grammar.php             # SQL文法生成
    ├── Expression.php          # Raw SQL式
    └── SqlParser.php           # sqlParser.class.phpを移行
```

2. **Eloquent風クエリビルダーの設計**

```php
// メソッドチェーンによるクエリ生成
$users = DB::table('manager_users')
    ->where('role', 1)
    ->where('blocked', 0)
    ->orderBy('username')
    ->get();

// 挿入
DB::table('site_templates')
    ->insert([
        'templatename' => 'MyTemplate',
        'content' => $content,
        'category' => 1
    ]);

// 更新
DB::table('system_settings')
    ->where('setting_name', 'site_name')
    ->update(['setting_value' => 'New Site']);

// 削除
DB::table('active_users')->delete();

// 複雑なクエリ
$result = DB::table('site_content as c')
    ->leftJoin('document_groups as dg', 'dg.document', '=', 'c.id')
    ->where('c.published', 1)
    ->whereNotNull('dg.id')
    ->select(['c.*', 'dg.document_group'])
    ->get();
```

3. **既存db()からの段階的移行**
   - インストーラ内で新しいクエリビルダーを優先使用
   - 成功後、コア全体への展開を検討
   - 既存のdb()ヘルパーは当面維持（後方互換性）

4. **sqlParser.class.phpの移行**
   - `SqlParser.class.php` → `src/Database/SqlParser.php`
   - 名前空間の追加
   - 内部でQueryBuilderを活用

### 成果物

- **モダンなクエリビルダー**（Laravel Eloquentスタイル）
- テスト可能なDB層
- SQLインジェクション対策の強化
- コア全体への展開の足がかり

## Phase 3: Installer/Upgraderの分離（2週間）

### 目標

新規インストールとアップグレードのロジックを完全に分離。

### 作業内容

1. **インターフェースと抽象クラスの作成**

```
src/
└── Installer/
    ├── InstallerInterface.php
    ├── AbstractInstaller.php
    ├── NewInstaller.php        # instprocessor.phpの新規インストール部分
    └── Upgrader.php            # instprocessor.phpのアップグレード部分
```

2. **instprocessor.phpのロジックを分離**
   - `if (sessionv('is_upgradeable'))` の分岐をクラスレベルに
   - 共通処理を`AbstractInstaller`に
   - 新規インストール専用処理を`NewInstaller`に
   - アップグレード専用処理を`Upgrader`に

3. **既存のinstprocessor.phpは保持**
   - 新しいクラスへのファサードとして機能
   - 段階的に削除予定

### 成果物

- 新規インストールとアップグレードが独立
- テスト可能な構造
- 既存のフローは変更なし

## Phase 4: Assetインストーラの移行（1-2週間）

### 目標

6つのアセットインストーラをクラス化し、グローバル変数を削除。

### 作業内容

1. **アセットインストーラの基底クラス作成**

```
src/
└── Asset/
    ├── AssetInstaller.php          # 基底クラス
    ├── AssetCollector.php          # setup.info.phpの機能を移行
    ├── TemplateInstaller.php       # prc_insTemplates.inc.php
    ├── TvInstaller.php             # prc_insTVs.inc.php
    ├── ChunkInstaller.php          # prc_insChunks.inc.php
    ├── SnippetInstaller.php        # prc_insSnippets.inc.php
    ├── PluginInstaller.php         # prc_insPlugins.inc.php
    └── ModuleInstaller.php         # prc_insModules.inc.php
```

2. **グローバル変数の削除**
   - `global $tplTemplates` などをクラスのプロパティに
   - 依存性注入で渡す

3. **setup.info.phpの機能をAssetCollectorに移行**
   - `collectTpls()`, `parse_docblock()` などを統合

### 成果物

- 6つのアセットインストーラクラス
- グローバル変数の削除
- テスト可能な構造

## Phase 5: Controller/Viewの分離（1-2週間）

### 目標

actions/のロジックをControllerに、HTMLをViewに分離。

### 作業内容

1. **Controllerクラスの作成**

```
src/
└── Http/
    ├── Request.php
    ├── Response.php
    └── Controller/
        ├── AbstractController.php
        ├── ModeController.php          # actions/mode.php
        ├── ConnectionController.php    # actions/connection.php
        ├── OptionsController.php       # actions/options.php
        ├── SummaryController.php       # actions/summary.php
        └── InstallController.php       # actions/install.php
```

2. **Viewレンダラーの作成**

```
src/
└── View/
    ├── Renderer.php
    └── ViewHelper.php

resources/
└── views/                          # tpl/から移行
    ├── layouts/
    │   └── main.php
    ├── pages/
    │   ├── mode.php
    │   ├── connection.php
    │   ├── options.php
    │   ├── summary.php
    │   └── install.php
    └── partials/
```

3. **ルーティングの追加**

```php
// config/routes.php
return [
    'mode' => ModeController::class,
    'connection' => ConnectionController::class,
    // ...
];
```

### 成果物

- MVCパターンの実現
- ビューとロジックの完全分離
- テスト可能なController

## Phase 6: Session抽象化（1週間）

### 目標

セッションへの直接アクセスを抽象化し、テスト可能にする。

### 作業内容

1. **Sessionクラスの作成**

```
src/
└── Session/
    ├── InstallSession.php      # セッション管理
    └── SessionData.php         # DTO
```

2. **sessionv()等の関数を段階的に置き換え**
   - `sessionv('key')` → `$session->get('key')`
   - 既存の関数は互換レイヤーとして残す

### 成果物

- テスト可能なセッション管理
- 依存性注入でSessionを渡す

## Phase 7: Validator分離（1週間）

### 目標

summary.phpからバリデーションロジックを抽出。

### 作業内容

1. **Validatorクラスの作成**

```
src/
└── Validator/
    ├── EnvironmentValidator.php
    ├── RequirementChecker.php
    ├── DirectoryChecker.php
    ├── DatabaseChecker.php
    └── PasswordValidator.php
```

2. **summary.phpのバリデーションコードを移行**
   - PHPバージョンチェック
   - ディレクトリ権限チェック
   - DBチェック

### 成果物

- 独立したバリデーションロジック
- 再利用可能
- テスト可能

## Phase 8: functions.php解体（1-2週間）

### 目標

559行のfunctions.phpを機能ごとに分割・クラス化。

### 作業内容

1. **機能ごとに適切なクラスに移行**

| 既存関数 | 移行先 |
|---------|--------|
| `setOption()`, `getOption()` | `Session/InstallSession.php` |
| `browser_lang()`, `includeLang()` | `Support/LanguageHelper.php` |
| `parse_docblock()` | `Support/DocBlockParser.php` |
| `isUpGradeable()` | `Installer/UpgradeDetector.php` |
| `compare_check()` | `Asset/AssetComparator.php` |
| `clean_up()` | `Installer/CleanupService.php` |
| `propUpdate()` | `Asset/PropertyMerger.php` |
| `getCreateDbCategory()` | `Database/CategoryManager.php` |
| `convert2utf8mb4()` | `Migration/Utf8mb4Converter.php` |
| `collectTpls()` | `Asset/AssetCollector.php` |

2. **後方互換性のための関数ファサードは保持**

```php
// install/functions.php（縮小版）
function sessionv($key, $default = null) {
    return app()->session->get($key, $default);
}
```

### 成果物

- 機能ごとに整理されたクラス
- テスト可能な小さなクラス
- 既存コードとの互換性

## Phase 9: テスト（1週間）

### 作業内容

1. **単体テストの作成**
   - Database層
   - Installer/Upgrader
   - Assetインストーラ
   - Validator

2. **統合テストの作成**
   - 新規インストールフロー
   - アップグレードフロー

3. **既存機能のリグレッションテスト**

## Phase 10: ドキュメント（1週間）

### 作業内容

1. **開発者向けドキュメント**
   - アーキテクチャ概要
   - クラス図
   - シーケンス図

2. **移行ガイド**
   - 既存コードから新しいコードへの移行手順

3. **API リファレンス**
   - 各クラスの使い方

## 最終成果物

```
install/
├── bootstrap.php                     # メインエントリーポイント
├── autoload.php                      # PSR-4オートローダー
├── index.php                         # 互換性のため残す
│
├── src/                              # 全ての新しいコード
│   ├── Core/
│   ├── Installer/
│   ├── Database/
│   ├── Asset/
│   ├── Validator/
│   ├── Session/
│   ├── Http/
│   ├── View/
│   ├── Migration/
│   └── Support/
│
├── config/
│   ├── installer.php
│   ├── routes.php
│   └── container.php
│
├── resources/
│   ├── views/
│   ├── lang/
│   └── sql/
│
├── tests/                            # 新規追加
│   ├── Unit/
│   └── Integration/
│
└── docs/                             # このドキュメント
```

## 次のステップ

1. Phase 1から順次実装
2. 各Phaseごとにプルリクエスト作成
3. レビュー・マージ
4. 次のPhaseへ
