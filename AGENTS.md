# 開発ガイドライン

## 基本原則

SOLID / KISS / YAGNI / DRY / PIE（自己検証可能な実装） / SSOT（真実の単一情報源）を遵守する。

---

## 判断優先順位（迷ったらこれを守る）

1. **設計の健全性を最優先**（改善できるなら改善する。既存実装が不適切なら是正する）
2. **SSOTを壊さない**（責務分散・重複ロジックを作らない）
3. **セキュリティとエスケープは実行直前で担保**
4. **キャッシュ整合性を維持**
5. 局所最適よりも「システム全体の進化」を優先

---

## 補助ルール

* **既存パターンの評価と改善**: 新機能追加時は既存パターンを必ず調査する。ただし不適切・冗長・設計的に弱い場合は改善を優先する。局所的な新方式導入は禁止し、改善は横断的に適用する前提で設計する。
* **ヘルパー利用**: `manager/includes/helpers.php` の `evo()` / `db()` / `manager()` を経由してグローバルオブジェクトへアクセスする。
* **スーパーグローバル変数の禁止**:

  * `$_GET` → `getv($key, $default)` / `$_POST` → `postv($key, $default)` / `$_REQUEST` → `anyv($key, $default)`
  * `$_SERVER` → `serverv($key, $default)` / `$_COOKIE` → `cookiev($key, $default)`
  * `$_SESSION` → `sessionv($key, $default)`（読み取り） / `sessionv('*key', $value)`（書き込み）
  * リクエストメソッド判定 → `is_post()` / `is_get()`
* **DB操作のセキュリティ**: エスケープはDB操作直前で行う。

  * `db()->insert(db()->escape($data), $table)` / `db()->update(db()->escape($data), $table, $where)`
  * ❌ 変数宣言時の個別エスケープ / ❌ `compact()` 関数
* **コーディングスタイル**:

  * 配列は `[]`（`array()` 非推奨）/ デフォルト値は `?:` / 早期リターン
  * 文字列補間 `"{$var}"` / インデント: スペース4つ / `?>` 不要
* **ログ**: `evo()->logEvent()` を使用
* **レビュー**: 日本語
* **コミットメッセージ**: 日本語で生成（Conventional Commits 準拠）

---

## Conventional Commits

### フォーマット

```
<type>(optional scope): <subject>
```

* type は英語固定
* subject は日本語・簡潔に・現在形・句点なし

### type 一覧

| type     | 用途     |
| -------- | ------ |
| feat     | 新機能    |
| fix      | 不具合修正  |
| refactor | 内部改善   |
| perf     | 性能改善   |
| docs     | ドキュメント |
| style    | 形式修正   |
| test     | テスト    |
| chore    | 雑務     |
| ci       | CI変更   |

### 例

```
feat(parser): キャッシュ生成前にフックを追加
```

```
fix(db): 実行直前でエスケープ処理を統一
```

---

## AIが誤りやすいポイント

* 直接 `$modx` やスーパーグローバルへ触れる
* エスケープを代入時に行う
* 既存フック位置を無視して新イベントを追加する
* キャッシュ無効化条件を考慮しない
* 小規模修正で新アーキテクチャを導入する

---

## 移行ルール（v1.3.0+）

### フロントエンド

* jQuery 禁止 → Vanilla JS (ES6+)
* frame/iframe 禁止

### データベース

* `db()` ヘルパー厳守
* 生SQL自粛

### CLI / ログ

* 管理機能は CLI 実行可能
* ログはコンテキスト配列形式

---

## DocumentParser

中心: `manager/includes/document.parser.class.inc.php`
実装時は `executeParser()` / `prepareResponse()` / `parseDocumentSource()` / `postProcess()` のどこに影響するかを必ず明示する。

---

## ドキュメントマップ

`assets/docs/` 配下を必ず参照する。

| ドキュメント                  | 主題     |
| ----------------------- | ------ |
| `architecture.md`       | 処理フロー  |
| `template-system.md`    | タグ解析   |
| `events-and-plugins.md` | イベント   |
| `cache-mechanism.md`    | キャッシュ  |
| `.agent/roadmap.md`     | ロードマップ |
| `global-settings.md`    | 設定追加   |
| `core-issues.md`        | コア課題   |

---

## 推奨ワークフロー

1. 既存実装を grep 検索して確認
2. `architecture.md` で影響範囲整理
3. キャッシュ影響を確認
4. ヘルパー経由で実装

---

## ファイル管理

* メディア: `{rb_base_dir}` 配下
* テンプレート: `assets/templates/`
* 一時領域: `temp/`

除外: `assets/plugins/*/tinymce/` 以下は参照不要

---

## ドキュメント運用

* AGENTS.md は **判断基準のみを記載（詳細は docs へ分離）**
* ExecPlan は `.agent/plans/` に格納
* ロードマップは `.agent/roadmap.md`

---

## 肥大化防止ポリシー

AGENTS.md は「原則と判断基準のみ」を記載し、手順や詳細仕様は必ず `assets/docs/` に分離する。
