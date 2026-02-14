# 開発ガイドライン

## 基本原則

SOLID / KISS / YAGNI / DRY / PIE（自己検証可能な実装） / SSOT（真実の単一情報源）を遵守する。

### 補助ルール

- **既存パターンの踏襲**: 新機能は必ず既存コードのパターンを調査し同じアプローチを採用する。新方式が必要な場合はシステム全体への適用を前提に設計する。
- **ヘルパー利用**: `manager/includes/helpers.php` の `evo()` / `db()` / `manager()` を経由してグローバルオブジェクトへアクセスする。
- **スーパーグローバル変数の禁止**:
  - `$_GET` → `getv($key, $default)` / `$_POST` → `postv($key, $default)` / `$_REQUEST` → `anyv($key, $default)`
  - `$_SERVER` → `serverv($key, $default)` / `$_COOKIE` → `cookiev($key, $default)`
  - `$_SESSION` → `sessionv($key, $default)`（読み取り） / `sessionv('*key', $value)`（書き込み）
  - リクエストメソッド判定 → `is_post()` / `is_get()`
- **DB操作のセキュリティ**: エスケープはDB操作直前で行う。
  - `db()->insert(db()->escape($data), $table)` / `db()->update(db()->escape($data), $table, $where)`
  - ❌ 変数宣言時の個別エスケープ / ❌ `compact()` 関数
- **コーディングスタイル**:
  - 配列は `[]`（`array()` 非推奨）/ デフォルト値は `?:` で簡潔に / 早期リターンでネスト最小化
  - 文字列補間 `"{$var}"` を活用 / インデント: スペース4つ / `?>` 不要
- **ログ**: `evo()->logEvent()` を使用（管理画面「ツール」→「イベントログ」で確認）
- **レビュー**: 日本語 / **コミットメッセージ**: 英語

## 移行ルール（v1.3.0+）

### フロントエンド

- **jQuery 禁止** → Vanilla JS (ES6+): `querySelectorAll` / `fetch` / `DOMContentLoaded`
- **frame/iframe 禁止** → HTML5 + CSS (Flexbox/Grid) + Ajax

### データベース

- **`db()` ヘルパー厳守**: `db()->query()` / `db()->select()` / `db()->insert()` 等を必ず使用
- **生SQL自粛**: クエリビルダメソッド優先、`mysql_` 関数依存を避ける

### CLI / ログ

- 管理機能は CLI (`php cli.php`) からも実行可能に設計
- ログはコンテキスト配列を伴う形式（将来の PSR-3 準拠のため）

## DocumentParser

中心クラス: `manager/includes/document.parser.class.inc.php`
`index.php` → サニタイズ → リソース特定 → テンプレート解析 → `invokeEvent()` → キャッシュ生成。
実装時は「どの段階（`executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()`）にフックすべきか」を意識する。

## ドキュメントマップ

`assets/docs/` 配下。該当領域の編集前に必ず参照すること。

| ドキュメント | 主題 |
| --- | --- |
| `architecture.md` | DocumentParser の処理フロー・バックエンド判定 |
| `template-system.md` | テンプレート継承・MODX タグ解析・評価順序 |
| `events-and-plugins.md` | イベントライフサイクル・プラグインキャッシュ |
| `cache-mechanism.md` | ページキャッシュ・TTL・無効化手順 |
| `.agent/roadmap.md` | 次期バージョン (v1.3.0–v1.5.0) ロードマップ |
| `global-settings.md` | グローバル設定の追加手順・タブ構成 |
| `core-issues.md` | 改修を通じて発見されたコア側の課題記録 |

### ExecPlan（実行計画）

複雑なタスクは `.agent/PLANS.md` の仕様に従い `.agent/plans/` に ExecPlan を作成する。

## 推奨ワークフロー

1. `architecture.md` と `events-and-plugins.md` で影響範囲を整理
2. テンプレート・TV を触る場合は `template-system.md` で解析順序を確認
3. キャッシュ影響がある変更は `cache-mechanism.md` で `evo()->clearCache()` の条件を確認
4. ヘルパー関数を活用し、直接 `$modx` に触れない

## ファイル管理

| ディレクトリ | 用途 |
| --- | --- |
| `{rb_base_dir}images/` `files/` `media/` | メディアファイル（`rb_base_dir` デフォルト: `content/`） |
| `assets/templates/` | テンプレート・チャンクの物理ファイル |
| `temp/` | キャッシュ・バックアップ・インポート/エクスポート |

**除外対象**: `assets/plugins/*/tinymce/` 以下はベンダー資産（CHANGELOG.md 等）。AI 参照不要。

- パス取得: `evo()->getConfig('rb_base_dir')` / `[(base_path)]` → `MODX_BASE_PATH`

## ドキュメント運用

- `AGENTS.md`（本ファイル）: AI 向けハブ。詳細は `assets/docs/` に分離しリンクする
- `.agent/PLANS.md`: ExecPlan 仕様書。個別計画は `.agent/plans/` に格納
- `.agent/roadmap.md`: プロジェクトロードマップ
