# プルリクエスト: インストーラリファクタリング Phase 1

## タイトル
インストーラリファクタリング: Phase 1 - 基礎構造と計画ドキュメント

## 本文

## 概要

インストーラの段階的リファクタリングとセキュリティ改善のための計画ドキュメントと基礎構造を追加します。

## 背景

現在のインストーラ（`install/`ディレクトリ）は以下の問題を抱えています：

### セキュリティ上の問題
- **誰でもアップグレードを実行できる**（認証なし）
- パスワードがMD5でハッシュ化
- CSRF保護なし
- SQLインジェクション対策が不十分

### 保守性の問題
- グローバル変数の多用（`global $tplTemplates` など）
- インストールとアップグレードのロジックが混在
- 手続き型コード、クラス化されていない
- functions.phpが559行の巨大ファイル
- テストが困難

## この PR の内容

### 📚 ドキュメント

`docs/installer-refactoring/` に以下を追加：

1. **00-overview.md** - 問題点と目標の概要
2. **01-refactoring-plan.md** - 10フェーズの段階的実装計画
3. **02-security-improvements.md** - セキュリティ強化の詳細提案
4. **03-directory-structure.md** - 新しいディレクトリ構造とコーディング規約
5. **README.md** - ドキュメント全体のインデックス

### 🏗️ Phase 1基礎構造

新しいアーキテクチャの土台を構築：

- **install/autoload.php** - PSR-4準拠のオートローダー
- **install/bootstrap.php** - 新しいエントリーポイント（将来的に使用）
- **install/REFACTORING.md** - 進捗状況トラッキング

### 📁 ディレクトリ構造

```
install/
├── src/                    # 新しいコード（EvolutionCMS\Install\）
├── config/                 # 設定ファイル
├── resources/              # リソースファイル
│   ├── views/              # ビューテンプレート
│   ├── lang/               # 言語ファイル
│   └── sql/                # SQLファイル（移行予定）
├── tests/                  # テスト
│   ├── Unit/
│   └── Integration/
├── public/                 # 静的ファイル
│   ├── css/
│   └── js/
├── bootstrap.php
├── autoload.php
└── [既存ファイル]          # 段階的に移行予定
```

## 主な改善提案

### 🔒 セキュリティ（高優先度）

1. **アップグレード時の認証** - 管理者ログイン必須化
2. **CSRF保護** - 全フォームに実装
3. **SQLインジェクション対策強化** - プリペアドステートメント徹底
4. **パスワードハッシュ改善** - MD5からphpassへ移行
5. **インストールディレクトリ自動削除** - セキュリティリスク軽減

### 🏛️ アーキテクチャ

1. **インストールとアップグレードの完全分離**
   - `NewInstaller` クラス - 新規インストール専用
   - `Upgrader` クラス - アップグレード専用
2. **クラスベースアーキテクチャへの移行**
3. **依存性注入の導入**
4. **テスト可能な構造**

### 💡 UX改善

1. **進捗表示の改善** - リアルタイム進捗バー
2. **バックアップ機能** - アップグレード前の自動バックアップ
3. **エラーメッセージ改善** - ユーザーフレンドリーな表示と解決策提示
4. **システム要件チェック強化** - 詳細な環境検証

## 段階的実装計画（10フェーズ）

| Phase | 期間 | 内容 |
|-------|------|------|
| **Phase 1** ✅ | 1-2週 | **基礎構築**（このPR） |
| Phase 2 | 1週 | Database層の移行 |
| Phase 3 | 2週 | Installer/Upgraderの分離 |
| Phase 4 | 1-2週 | Assetインストーラの移行 |
| Phase 5 | 1-2週 | Controller/Viewの分離 |
| Phase 6 | 1週 | Session抽象化 |
| Phase 7 | 1週 | Validator分離 |
| Phase 8 | 1-2週 | functions.php解体 |
| Phase 9 | 1週 | テスト |
| Phase 10 | 1週 | ドキュメント |

## 互換性

✅ **既存のインストーラと完全に共存**
- 既存の `index.php` は引き続き動作
- 段階的な移行により既存機能への影響なし
- 各Phaseごとにテスト可能な状態を維持

## 次のステップ

Phase 1完了後：
- [ ] `Core\Application` クラスの実装
- [ ] `Core\Container` クラスの実装（軽量DIコンテナ）
- [ ] `Core\Config` クラスの実装
- [ ] 基本クラスの単体テスト

Phase 2以降は個別のPRで実装します。

## レビュー依頼

以下の点についてフィードバックをお願いします：

1. 📋 **計画全体の方向性** - 10フェーズの段階的アプローチは適切か？
2. 🔒 **セキュリティ改善の優先順位** - 他に重要な項目はあるか？
3. 🏗️ **ディレクトリ構造とアーキテクチャ** - より良い設計案はあるか？
4. 📚 **ドキュメントの内容** - 追加すべき情報はあるか？

## 関連ドキュメント

- [リファクタリング計画全体](https://github.com/modxcms-jp/evolution-jp/blob/claude/refactor-installer-structure-01Wg6BZxgfZQAMG7iFJkdjhA/docs/installer-refactoring/01-refactoring-plan.md)
- [セキュリティ改善提案](https://github.com/modxcms-jp/evolution-jp/blob/claude/refactor-installer-structure-01Wg6BZxgfZQAMG7iFJkdjhA/docs/installer-refactoring/02-security-improvements.md)
- [ディレクトリ構造](https://github.com/modxcms-jp/evolution-jp/blob/claude/refactor-installer-structure-01Wg6BZxgfZQAMG7iFJkdjhA/docs/installer-refactoring/03-directory-structure.md)

---

## GitHub上でのPR作成方法

以下のURLにアクセスしてプルリクエストを作成してください：

https://github.com/modxcms-jp/evolution-jp/pull/new/claude/refactor-installer-structure-01Wg6BZxgfZQAMG7iFJkdjhA

上記の「本文」セクションの内容をコピー＆ペーストしてください。
