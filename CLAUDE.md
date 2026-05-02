# Claude Code 実行ガイド

## 基本方針

**開発ガイドラインは `AGENTS.md` を正とする。**

このファイルはClaude Code固有の動作指示とワークフローのみを記載する。

---

## プロジェクト構成

- **開発ガイドライン**: `AGENTS.md`（コーディング規約、判断基準）
- **ロードマップ**: `.agent/roadmap.md`（実行順、依存関係、ExecPlan連携）
- **ExecPlan**: `.agent/plans/`（実装計画の詳細）
- **ドキュメント**: `assets/docs/`（アーキテクチャ、仕様）

---

## 実行前の確認

1. **roadmapを確認**: `.agent/roadmap.md` でステータス（NEXT/WIP/DONE/BLOCKED）と依存関係を確認
2. **ExecPlanの存在確認**: 着手予定タスクにExecPlanがあるか確認
3. **AGENTS.mdの遵守**: コーディング規約と判断基準に従う

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
- **コマンド**:
  - `/work` - タスク実行開始
  - `/start-session` - 開発セッション開始
- **参照**: `AGENTS.md` のコーディング規約を遵守

### issue-resolver スキル - 不具合調査・修正

- **用途**: フォーラムやIssueの不具合報告に対する調査・再現・修正
- **コマンド**: `analyze-issue <URL|テキスト>` から開始（reproduce → implement-fix の順に進む）

### review-agent スキル - コードレビュー

- **用途**: PR差分やローカル差分を日本語でレビューする
- **タイミング**: 「レビュー」「PR確認」「差分確認」「コードレビュー」を依頼したとき
- **コマンド**: `/review-diff`（ローカル差分）/ `/review-pr`（PR）

### roadmap-manager スキル - ロードマップ管理

- **用途**: ロードマップのタスク追加・変更・削除・フォーマット正規化
- **コマンド**: `/roadmap-next`（次タスク着手）/ `/roadmap-add` / `/roadmap-update` / `/roadmap-remove` / `/roadmap-sync` / `/roadmap-normalize`

### roadmap-next-task スキル - 次タスク着手

- **用途**: 依存順で次の未完了タスクを選定し、ExecPlan作成から実装完了・同期まで一貫して行う
- **コマンド**: `/next-task`（着手）/ `/finish-task`（完了処理）/ `/sync-roadmap`（状態同期）

### skill-creator スキル - スキル作成支援

- **用途**: 新規スキルの作成・検証・パッケージング

---

## ワークフロー

### 新タスク着手時

**ショートカット**: `roadmap-next-task` スキルの `/next-task` で着手〜完了同期まで一貫実行できる。

**手動手順**:

1. **roadmap確認**: `.agent/roadmap.md` で次タスクを特定（roadmap-next-task スキル）
2. **依存関係チェック**: 依存タスクがすべて `DONE` か確認
3. **ExecPlan確認**:
   - ExecPlanあり → 内容を読んで `/work` で実装開始（project-worker スキル）
   - ExecPlanなし → `/create-plan <タスク概要>` で計画作成（exec-plan スキル）
4. **roadmap更新**: 実装開始前に、ステータスを `NEXT` → `WIP` へ更新する
5. **実装**: `AGENTS.md` の規約に従って実装
6. **完了処理**: `roadmap-next-task` スキルの `/finish-task` で完了宣言・ユーザー確認・同期を行う

### レビュー時

1. review-agent スキルを使ってレビューを実行
   - ローカル差分: `/review-diff`
   - PR: `/review-pr`

### 不具合対応時

1. issue-resolver スキルを使って調査・再現・修正を実行（`analyze-issue <URL|テキスト>` から開始）
2. 必要に応じてroadmapへ反映（roadmap-manager スキルを使用）

### マルチエージェント分担時

1. agent-orchestrator スキルの `/orchestrate <タスク>` で担当と書き込み範囲を宣言してから開始する

---

## 重要な注意事項

### CLI実行環境

- `php evo` はアプリコンテナ内で実行する
- ホスト側では `mysqli` 不在で失敗する場合がある

### ログ確認

- システムログ: `temp/logs/system/YYYY/MM/`（JSONLines形式）
- CLI: `php evo log:tail --lines=20` / `php evo log:search "検索語" --level=error --limit=10`

### コミット

- Conventional Commits準拠（`type` は英語固定、`subject` は日本語で記載）
- 例: `fix(logger): ログファイル名を実時刻ベースに統一`
- 自動生成時も `subject` は日本語を使用
- 詳細ルールは `AGENTS.md` のコミット規約に従う

### レビュー

- レビューコメントは日本語で記載

---

## roadmapとExecPlanの連携

- roadmapはタスクの**概要と状態管理**を担当
- ExecPlanはタスクの**詳細な実装手順**を担当

### ExecPlan完了処理（`.agent/PLANS.md` 準拠）

1. 実装と検証の完了を明示し、完了処理を進める前にユーザー確認を取る
2. ユーザー確認後にのみ、対象タスクの `.agent/roadmap.md` の `Status: DONE` と `完了日` を更新する
3. 親子タスク構造がある場合は、必要に応じて親タスクの `Status` も同期する
4. ユーザー確認後にのみ、完了した ExecPlan を `.agent/plans/archive/` へ移動する
5. アーカイブ時はファイル名先頭の日付を完了日（`YYYY-MM-DD`）へ更新する
6. ロードマップに `ExecPlan:` を記載している場合は、移動後のパスへ更新する
7. `.agent/roadmap.md` の `最終更新` を同期する

---

## 肥大化防止

- このファイルは**Claude Code固有の動作指示のみ**を記載
- 開発ガイドライン詳細は `AGENTS.md` へ
- 実装手順詳細は ExecPlan へ
- アーキテクチャ詳細は `assets/docs/` へ
