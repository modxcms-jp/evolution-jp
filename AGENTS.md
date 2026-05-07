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
* `php evo` はホストから `docker compose exec <app-service> php evo ...` で実行する。ホスト直接実行は `mysqli` 不在で失敗する場合がある

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

* AGENTS.md は **判断基準・スキル一覧・ワークフローを記載（詳細手順は docs へ分離）**
* ExecPlan は `.agent/plans/` に格納
* ロードマップは `.agent/roadmap.md`
* ExecPlan 完了処理プロトコル（skill:complete 含む）は `.agent/PLANS.md` を正本とする

---

## スキル一覧と使い方

### agent-orchestrator スキル - マルチエージェントオーケストレーション

- **用途**: 大きなタスクを調査・計画・実装・レビュー・検証へ役割分担する
- **タイミング**: 「エージェント」「役割分担」「複数担当」などを求めたとき、または担当境界を決める必要があるとき
- **コマンド**: `/orchestrate <タスク>` で開始、レビューは `/agent-review-flow`

### exec-plan スキル - 実行計画の作成・検証・更新

- **用途**: 新機能開発、リファクタリング、バグ修正の設計フェーズ
- **タイミング**: roadmapで`NEXT`ステータスかつExecPlan未作成のタスク着手時
- **コマンド**: `/create-plan <タスク概要>` でプラン作成開始

### project-worker スキル - 開発タスク実行

- **用途**: Evolution CMS JP Editionの開発作業全般
- **コマンド**: `/work`（タスク実行）/ `/start-session`（開発セッション開始）

### issue-resolver スキル - 不具合調査・修正

- **用途**: フォーラムやIssueの不具合報告に対する調査・再現・修正
- **コマンド**: `analyze-issue <URL|テキスト>` から開始（reproduce → implement-fix の順に進む）

### review-agent スキル - コードレビュー

- **用途**: PR差分やローカル差分を日本語でレビューする。GitHub PR のレビュー指摘への対応（分類・方針提示・Worker委譲・resolved化）も行う
- **タイミング**: 「レビュー」「PR確認」「差分確認」「コードレビュー」「レビュー指摘に対応」を依頼したとき
- **コマンド**: `/review-diff`（ローカル差分）/ `/review-pr`（PR）/ `/resolve-review <PR番号>`（レビュー指摘対応）

### roadmap-manager スキル - ロードマップ管理

- **用途**: ロードマップのタスク追加・変更・削除・フォーマット正規化
- **コマンド**: `/roadmap-next`（次タスク着手）/ `/roadmap-add` / `/roadmap-update` / `/roadmap-remove` / `/roadmap-sync` / `/roadmap-normalize`

### roadmap-next-task スキル - 次タスク着手

- **用途**: 依存順で次の未完了タスクを選定し、ExecPlan作成から実装完了・同期まで一貫して行う
- **コマンド**: `/next-task`（着手）/ `/finish-task`（完了処理）/ `/sync-roadmap`（状態同期）

### release スキル - リリース作業

- **用途**: バージョン更新・タグ作成・GitHub Actions によるパッケージビルドまでを対話形式でガイド
- **タイミング**: 「リリース」「release」と依頼されたとき
- **コマンド**: `/release` で開始
- **参照**: `assets/docs/release-process.md`

### doc-maintainer スキル - ドキュメント健全性チェック・修正

- **用途**: AI向けドキュメントの SSOT 違反・表記ゆれ・構造問題の検出と修正。PR概要と差分の乖離チェックも行う
- **タイミング**: 「ドキュメントの整合性を確認」「SSOT違反を探す」「PRの概要を更新したい」を依頼したとき。またドキュメントファイルを変更したコミット前に必ず実行する
- **コマンド**: `/doc-audit [対象]`（問題一覧表示）/ `/doc-fix <問題番号|説明>`（修正）/ `/pr-sync <PR番号>`（PR概要と差分の乖離確認）

### skill-creator スキル - スキル作成支援

- **用途**: 新規スキルの作成・検証・パッケージング

---

## タスク管理ワークフロー

### 実行前の確認

1. **roadmapを確認**: `.agent/roadmap.md` でステータス（NEXT/WIP/DONE/BLOCKED）と依存関係を確認
2. **ExecPlanの存在確認**: 着手予定タスクにExecPlanがあるか確認
3. **AGENTS.mdの遵守**: コーディング規約と判断基準に従う

### コミット前（ドキュメント変更時）

1. `/doc-audit` で変更対象ファイルのドキュメント問題（パス表記ゆれ・構造問題・無効参照・SSOT違反）を確認・修正する
2. 問題が見つかった場合は `/doc-fix <問題番号|説明>` で修正してからコミットする

### 新タスク着手時

**ショートカット**: `roadmap-next-task` スキルの `/next-task` で着手、完了時は `/finish-task` で完了処理・ロードマップ同期まで一貫して行える。

**手動手順**:

1. **roadmap確認**: `.agent/roadmap.md` で次タスクを特定（roadmap-next-task スキル）
2. **依存関係チェック**: 依存タスクがすべて `DONE` か確認
3. **ExecPlan確認**:
   - ExecPlanあり → 内容を読んで `/work` で実装開始（project-worker スキル）
   - ExecPlanなし → `/create-plan <タスク概要>` で計画作成（exec-plan スキル）
4. **roadmap更新**: 実装開始前に、ステータスを `NEXT` → `WIP` へ更新する
5. **実装**: AGENTS.md の規約に従って実装
6. **完了処理**: `roadmap-next-task` スキルの `/finish-task` で完了宣言・ユーザー確認・同期を行う

### レビュー時

1. review-agent スキルを使ってレビューを実行
   - ローカル差分: `/review-diff`
   - PR: `/review-pr`
   - PR のレビュー指摘対応: `/resolve-review <PR番号>`（**必ずこのコマンドを使う**。直接修正した場合も、push 後にスレッドの resolved 化とユーザー確認を忘れず行う）
2. PR 作成直後、および追加コミットをした後は、まず「`/pr-sync <PR番号>` を実行するか？ はい・いいえ」で確認する
3. `はい` の場合のみ `/pr-sync <PR番号>` で概要との乖離を確認・更新する

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

AGENTS.md は「原則・判断基準・スキル一覧・ワークフロー」を記載し、詳細仕様は必ず `assets/docs/` に分離する。
