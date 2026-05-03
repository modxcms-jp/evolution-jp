---
name: project-worker
description: Evolution CMS JP Editionの開発タスクを実行するためのワークフロー、コマンド(/work, /start-session等)、およびコーディング規約ガイド。開発作業を開始する際に使用します。
---

# Project Worker

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/project-worker/SKILL.md` に置く。

実装規約は `AGENTS.md` を最優先とする。
ExecPlan の完了処理プロトコルは `.agent/PLANS.md` を正本とする。

## 実行ルール

1. コーディング規約は `AGENTS.md` に従う。
2. ヘルパー関数（`evo()` / `db()` / `manager()`）経由でグローバルオブジェクトへアクセスする。
3. スーパーグローバル変数は直接参照せず、規約のラッパー関数を使う。
4. DB操作のエスケープは操作直前で行う。
5. jQuery / frame / iframe は禁止。
6. `php evo` はアプリコンテナ内で実行する（ホスト側では mysqli 不在で失敗する）。

## コマンド

### /start-session
1. `AGENTS.md`（開発ルール）と `.agent/roadmap.md`（ロードマップ）を読み込む
2. `.agent/roadmap.md` の未完了タスクと現在のブランチ状態を確認
3. 取り組むタスクを特定し、ユーザーに確認

### /work
1. `AGENTS.md` のドキュメントマップから関連ファイルを特定
2. ExecPlan を伴うタスクの初回着手時は `php evo skill:init --plan=<plan-id> --skill=project-worker` で run を初期化する
3. 複雑なタスクはステップごとの計画を提示
4. `AGENTS.md` の規約を厳守して実装
5. 規約チェック: No jQuery / No Frames / Helpers First / Secure SQL

### /validate
1. 構文チェック: `find . -path "./vendor" -prune -o -name "*.php" -exec php -l {} +`
2. エラー時は最大3回まで自律修正・再チェック
3. ブラウザ確認が必要な項目をリストアップ

### /handoff
作業完了報告を以下の形式で出力:
- 実施タスク / 変更内容 / 検証結果 / 次のアクション

ExecPlan を伴うタスクの完了時は、`.agent/PLANS.md` の「完了処理プロトコル」に従い、
`skill:complete` まで含めて完了処理を行う。

## 意思決定の閾値

**自律判断可能**: 規約準拠、リファクタリング、構文エラー修正、ヘルパー関数への置き換え

**要相談**: DBスキーマ変更、互換性を損なう変更、`.agent/roadmap.md` の優先順位変更、大きな設計変更
