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
* **DB操作のセキュリティ**: 既存の DBAPI は値をエスケープしない。呼び出し側で、DB操作の直前に `db()->escape()` か `(int)` で安全化してから渡す。

  * `db()->insert(db()->escape($data), $table)` / `db()->update(db()->escape($data), $table, $where)`
  * 条件も同じ: `where('id', '=', (int) $id)` / `where('name', '=', db()->escape($name))`。`$field`・`$op` にリクエストの値を渡さない
  * 注意: `where_in()` / `and_where_in()` は取り決めから外れて、各値を内部でエスケープしている（実装時の揃え漏れ。外部のコードが前提にしている可能性があるので、新 API で解消するまで残す）。エスケープ済みの値を渡さない（二重エスケープになる）。同じ作りのヘルパーを増やさない
  * 値を内部で無害化する方式は PDO ベースの新 API（#453）で導入する。既存 API には足さない（二重エスケープになるため）
  * ❌ 変数宣言時の個別エスケープ / ❌ `compact()` 関数
* **出力のエスケープ**: 出力する文脈に合ったヘルパーを、出力の直前で使う。

  * HTML → `hsc()` / `<script>` 内の JS の値 → `js_literal()`（引用符付きの文字列リテラルを返す）
  * ❌ JS への埋め込みに `addslashes()` や `db()->escape()` を使う（`</script>` などを防げない）
* **コーディングスタイル**:

  * 配列は `[]`（`array()` 非推奨）/ デフォルト値は `?:` / 早期リターン
  * 文字列補間 `"{$var}"` / インデント: スペース4つ / `?>` 不要
* **ログ**: `evo()->logEvent()` を使用
* **レビュー**: 日本語
* **コミットメッセージ**: 日本語で生成（Conventional Commits 準拠）
* **Pull Request**: タイトルと本文は日本語で作成し、詳細は `.github/codex-pr-rules.md` に従う
* **検証環境**: 動作確認はできるだけ Docker 環境（`compose.yml` / `manager/docker/Dockerfile`）で行う。この CMS の動作を保証する標準の環境は Docker 側であり、ホスト（開発者の WSL など）固有の事情で動く・動かないは判断材料にしない。

  * 関数単体の確認も、ホストの PHP ではなく検証用イメージの `php` で動かす（ホストには zip などの拡張が無いことがある）
  * 「ホストで動かせないので未確認」で済ませない。Docker で確かめられないときだけ、その理由を書く
  * 画面や DB を含む確認は `adaptive-validation-runner` スキルに従う
  * 標準の環境は 1 つの版（PHP・DB）に固定されている。`README.md` の動作要件にある版との互換性に関わる変更（新しい PHP でしか使えない構文や関数、特定の DB に固有の SQL など）は、対象の版の公式イメージ（例: `php:7.4-cli`、`mysql:5.7`）を使い、これも Docker で確かめる

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
* frame/frameset 禁止（HTML で廃止された要素。iframe は対象外）

### データベース

* `db()` ヘルパー厳守
* 生SQL自粛

### CLI / ログ

* 管理機能は CLI 実行可能
* ログはコンテキスト配列形式
* `php evo` はホストから `docker compose exec <app-service> php evo ...` で実行する（補助ルールの「検証環境」を参照）

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
| `ai-runtime-governance.md` | AIランタイム運用 |
| `jquery-migration.md`   | jQuery 置き換え |
| `.agent/roadmap.md`     | ロードマップ |
| `global-settings.md`    | 設定追加   |
| `site-url.md`           | サイトのURL |
| `theme-variables.md`    | テーマの色の変数 |
| `theme-spacing.md`      | テーマの縦の余白 |
| `core-issues.md`        | コア課題   |
| `migrations.md`         | マイグレーション・アップグレード |

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

* AGENTS.md は **判断基準・ワークフローを記載（詳細手順は docs へ、スキルの詳細は各 SKILL.md へ分離）**
* ExecPlan は `.agent/plans/` に格納
* ロードマップは `.agent/roadmap.md`
* ExecPlan 完了処理プロトコル（skill:complete 含む）は `.agent/PLANS.md` を正本とする
* AIランタイム分担と共有スキル本文の位置づけは `assets/docs/ai-runtime-governance.md` を正本とする

---

## スキル

スキルの用途・起動タイミング・コマンドは、各 `.claude/skills/<name>/SKILL.md` を正本とする（Codex の入口は `.codex/skills/`）。ここには一覧を複製しない。ドキュメント変更時に必ず実行する doc-maintainer は「コミット前（ドキュメント変更時）」を参照。

---

## タスク管理ワークフロー

### 実行前の確認

1. **roadmapを確認**: `.agent/roadmap.md` でステータス（NEXT/WIP/DONE/BLOCKED）と依存関係を確認
2. **ExecPlan・Issue の確認**: 着手予定タスクの `Issue:` と `ExecPlan:` を確認する
3. **AGENTS.mdの遵守**: コーディング規約と判断基準に従う

### コミット前（ドキュメント変更時）

1. `/doc-audit` で変更対象ファイルのドキュメント問題（パス表記ゆれ・構造問題・無効参照・SSOT違反）を確認・修正する
2. 問題が見つかった場合は `/doc-fix <問題番号|説明>` で修正してからコミットする

`exec-plan-runner` の実行中は、worker が確認せずに直してコミットする（`.claude/skills/exec-plan-runner/SKILL.md` の「確認を省く範囲」）。

### 新タスク着手時

**ショートカット**: `roadmap-manager` スキルの `/next-task` で次タスクを選び（`--run` を付けると、そのまま実行に渡す）、`exec-plan-runner` スキル（`/run-plan`・`/run-issue`）で実装から PR までを進める。PR のマージ後は `exec-plan-runner` スキルの `/finish-task <Issue 番号 | ExecPlan のパス>` で完了処理・ロードマップ同期を行う。

**手動手順**:

1. **roadmap確認**: `.agent/roadmap.md` で次タスクを特定（roadmap-manager スキルの `/next-task`）
2. **依存関係チェック**: 依存タスクがすべて `DONE` か確認
3. **ExecPlan確認**:
   - ExecPlanあり → 内容を読んで `/work` で実装開始（project-worker スキル）
   - `着手時に判断` → `.agent/roadmap.md` の目安で要否を決める。要るなら `/create-plan <タスク概要>` で計画作成（exec-plan スキル）、要らないなら `不要` にして Issue を正に実装（issue-resolver スキル）
4. **roadmap更新**: 実装開始前に、ステータスを `NEXT` → `WIP` へ更新する（`exec-plan-runner` で進めるときは変えず、`/finish-task` でまとめて同期する）
5. **実装**: AGENTS.md の規約に従って実装
6. **完了処理**: `exec-plan-runner` スキルの `/finish-task <Issue 番号 | ExecPlan のパス>` で完了宣言・ユーザー確認・同期を行う

### レビュー時

1. review-agent スキルを使ってレビューを実行
   - ローカル差分: `/review-diff`
   - PR: `/review-pr`
   - PR のレビュー指摘対応: `/resolve-review <PR番号>`（**必ずこのコマンドを使う**。直接修正した場合も、push 後にスレッドの resolved 化とユーザー確認を忘れず行う）
2. PR 作成後に追加コミットをしたときは、まず「`/pr-sync <PR番号>` を実行するか？ はい・いいえ」で確認する
3. `はい` の場合のみ `/pr-sync <PR番号>` で概要との乖離を確認・更新する

`exec-plan-runner` の実行中は、手順 1〜3 の確認を省く（`.claude/skills/exec-plan-runner/SKILL.md` の「確認を省く範囲」）。

### 不具合対応時

1. issue-resolver スキルを使って調査・再現・修正を実行（`analyze-issue <URL|テキスト>` から開始）
2. 必要に応じてroadmapへ反映（roadmap-manager スキルを使用）

### マルチエージェント分担時

1. agent-orchestrator スキルの `/orchestrate <タスク>` で担当と書き込み範囲を宣言してから開始する

---

## ログ確認

- システムログ: `temp/logs/system/YYYY/MM/`（JSONLines形式）
- CLI: `php evo log:tail --lines=20` / `php evo log:search "検索語" --level=error --limit=10`

---

## 肥大化防止ポリシー

AGENTS.md は「原則・判断基準・ワークフロー」を記載し、詳細仕様は必ず `assets/docs/` に分離する。
