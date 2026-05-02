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

### `/exec-plan` - 実行計画の作成・検証・更新

- **用途**: 新機能開発、リファクタリング、バグ修正の設計フェーズ
- **タイミング**: roadmapで`NEXT`ステータスかつExecPlan未作成のタスク着手時
- **コマンド**: `/create-plan` でプラン作成開始

### `/project-worker` - 開発タスク実行

- **用途**: Evolution CMS JP Editionの開発作業全般
- **コマンド**:
  - `/work` - タスク実行開始
  - `/start-session` - 開発セッション開始
- **参照**: `AGENTS.md` のコーディング規約を遵守

### `/issue-resolver` - 不具合調査・修正

- **用途**: フォーラムやIssueの不具合報告に対する調査・再現・修正
- **機能**: URLからの情報取得、再現コード作成、修正、記録

### `/skill-creator` - スキル作成支援

- **用途**: 新規スキルの作成・検証・パッケージング

---

## ワークフロー

### 新タスク着手時

1. **roadmap確認**: `.agent/roadmap.md` で次タスクを特定
2. **依存関係チェック**: 依存タスクがすべて `DONE` か確認
3. **ExecPlan確認**:
   - ExecPlanあり → 内容を読んで `/project-worker` で実装開始
   - ExecPlanなし → `/exec-plan` で計画作成
4. **roadmap更新（着手時）**: ステータスを `NEXT` → `WIP`
5. **実装**: `AGENTS.md` の規約に従って実装
6. **roadmap更新（完了時）**: ステータスを `WIP` → `DONE`、完了日を記録

### 不具合対応時

1. `/issue-resolver` で調査・再現・修正を実行
2. 必要に応じてroadmapへ反映（構造的問題の場合）

---

## 重要な注意事項

### CLI実行環境

- `php evo` はアプリコンテナ内で実行する
- ホスト側では `mysqli` 不在で失敗する場合がある

### ログ確認

- システムログ: `temp/logs/system/YYYY/MM/`（JSONLines形式）
- CLI: `php evo log:tail --lines=20` / `php evo log:search "検索語" --level=error --limit=10`

### コミット

- コミットメッセージは日本語、Conventional Commits準拠
- 自動生成時も日本語を使用

### レビュー

- レビューコメントは日本語で記載

---

## roadmapとExecPlanの連携

- roadmapはタスクの**概要と状態管理**を担当
- ExecPlanはタスクの**詳細な実装手順**を担当

### ExecPlan完了処理（`.agent/PLANS.md` 準拠）

1. 実装と検証の完了を明示し、ユーザー確認を取る
2. ユーザー確認後、`.agent/roadmap.md` の `Status: DONE` と `完了日` を更新
3. 親子タスク構造がある場合は親タスクの `Status` も同期
4. ユーザー確認後、ExecPlanを `.agent/plans/archive/` へ移動
5. ファイル名先頭の日付を完了日（`YYYY-MM-DD`）へ更新
6. ロードマップの `ExecPlan:` を移動後のパスへ更新
7. `.agent/roadmap.md` の `最終更新` を同期

---

## 肥大化防止

- このファイルは**Claude Code固有の動作指示のみ**を記載
- 開発ガイドライン詳細は `AGENTS.md` へ
- 実装手順詳細は ExecPlan へ
- アーキテクチャ詳細は `assets/docs/` へ
