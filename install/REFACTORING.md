# インストーラリファクタリング進捗状況

## 現在のPhase: Phase 1 - 基礎構築

### 完了した作業

- [x] プロジェクトドキュメントの作成
  - [x] 概要と問題点の文書化
  - [x] 段階的リファクタリング計画
  - [x] セキュリティ改善提案
  - [x] ディレクトリ構造設計

- [x] 基礎ディレクトリ構造の作成
  - [x] `src/` - 新しいコード用
  - [x] `config/` - 設定ファイル用
  - [x] `resources/` - リソースファイル用
  - [x] `tests/` - テスト用
  - [x] `public/` - 静的ファイル用

- [x] オートローダーの実装
  - [x] PSR-4準拠のオートローダー作成
  - [x] 名前空間 `EvolutionCMS\Install\` の設定

- [x] 基本ファイルの作成
  - [x] `bootstrap.php` - 新しいエントリーポイント
  - [x] `autoload.php` - PSR-4オートローダー

- [x] 基本クラスの実装
  - [x] `src/Core/Application.php` - アプリケーションブートストラップ
  - [x] `src/Core/Container.php` - PSR-11準拠DIコンテナ
  - [x] `src/Core/Config.php` - 設定管理（ドット記法サポート）
  - [x] `src/Core/ContainerException.php` - コンテナ例外
  - [x] `src/Core/NotFoundException.php` - サービス未検出例外
  - [x] `src/Core/ConfigException.php` - 設定例外

- [x] 単体テストの作成
  - [x] `tests/Unit/Core/ApplicationTest.php`
  - [x] `tests/Unit/Core/ContainerTest.php`
  - [x] `tests/Unit/Core/ConfigTest.php`
  - [x] `phpunit.xml` - PHPUnit設定ファイル

### Phase 1 完了

Phase 1の全作業が完了しました！次はPhase 2（Database層の実装）に進みます。

### Phase 2以降の予定

Phase 2からは各Phaseごとに個別のプルリクエストを作成します。

詳細は [docs/installer-refactoring/01-refactoring-plan.md](../docs/installer-refactoring/01-refactoring-plan.md) を参照してください。

## ディレクトリ構造

```
install/
├── src/                    # 新しいコード（名前空間: EvolutionCMS\Install\）
│   └── .gitkeep
├── config/                 # 設定ファイル
│   └── .gitkeep
├── resources/              # リソースファイル
│   ├── views/              # ビューテンプレート
│   ├── lang/               # 言語ファイル
│   └── sql/                # SQLファイル（既存のsql/から移動予定）
├── tests/                  # テスト
│   ├── Unit/
│   └── Integration/
├── public/                 # 静的ファイル
│   ├── css/
│   └── js/
├── docs/                   # ドキュメント（../docs/installer-refactoring/）
├── bootstrap.php           # 新しいエントリーポイント
├── autoload.php            # PSR-4オートローダー
└── [既存ファイル]          # 段階的に移行・削除予定
```

## 既存コードとの互換性

Phase 1では既存のインストーラと完全に共存します：

- 既存の `index.php` は引き続き動作
- 新しい `bootstrap.php` は現在 `index.php` を呼び出す
- 段階的に機能を移行していく

## 貢献

- **ドキュメントレビュー**: [docs/installer-refactoring/](../docs/installer-refactoring/) 内のドキュメントへのフィードバック
- **設計レビュー**: アーキテクチャや設計パターンの提案
- **実装支援**: Phase 1完了後の各Phase実装

## 関連リンク

- [リファクタリング計画全体](../docs/installer-refactoring/01-refactoring-plan.md)
- [セキュリティ改善提案](../docs/installer-refactoring/02-security-improvements.md)
- [ディレクトリ構造](../docs/installer-refactoring/03-directory-structure.md)
