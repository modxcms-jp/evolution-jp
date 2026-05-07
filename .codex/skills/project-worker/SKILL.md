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
6. `php evo` はアプリコンテナに対してホストから実行する（`docker compose exec <app-service> php evo ...` を使用。ホスト直接実行は `mysqli` 不在で失敗する場合がある）。

## コマンド

詳細な手順は `.claude/skills/project-worker/SKILL.md` の各コマンドを参照。

### /start-session / /sync-status / /work / /validate / /handoff

詳細は `.claude/skills/project-worker/SKILL.md` を参照。

## 意思決定の閾値

詳細は `.claude/skills/project-worker/SKILL.md` の `## 意思決定の閾値` を参照。
