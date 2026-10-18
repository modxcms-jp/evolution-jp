---
name: roadmap-manager
description: ロードマップ（`.agent/roadmap.md`）を運用するスキル。タスクの追加・変更・削除、次タスクの選定（`/next-task`）、完了同期（`/roadmap-sync`）を扱い、着手時に改修規模で ExecPlan の要否を判断する。選んだタスクの実装から PR までは exec-plan-runner に渡す。「次のタスク」「/next-task」「ロードマップに追加」「ロードマップを同期」と依頼されたときに使う。
---

# Roadmap Manager

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/roadmap-manager/SKILL.md` に置く。必ず正本を読み、そのすべてに従う。ここには手順を写さない。
