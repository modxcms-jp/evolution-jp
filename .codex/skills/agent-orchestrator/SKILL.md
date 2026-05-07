---
name: "agent-orchestrator"
description: "`.agent/agents/` のエージェント定義を入口に、タスクを調査・計画・実装・レビュー・検証へ分担するスキル。ユーザーが「エージェント」「マルチエージェント」「orchestrator」「役割分担」「複数担当」などを求めたとき、または大きな変更で担当境界を決める必要があるときに使う。"
---

# Agent Orchestrator

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/agent-orchestrator/SKILL.md` に置く。

`.agent/agents/` を参照して、必要な担当を選び、作業順序と書き込み範囲を決める。
スキルは手順と判断基準、エージェントは責務境界と成果物を定義する。

## 参照順

1. `AGENTS.md`
2. `.agent/agents/README.md`
3. `.agent/agents/orchestrator.md`
4. 必要な担当エージェント定義
5. 関連する `.codex/skills/*/SKILL.md`
6. 関連する `assets/docs/`

## 実行ルール

詳細な実行ルールは `.claude/skills/agent-orchestrator/SKILL.md` の `## 実行ルール` を参照。

## 開始時の宣言

詳細は `.claude/skills/agent-orchestrator/SKILL.md` の `## 開始時の宣言` を参照。

## コマンド

### /orchestrate <タスク>

詳細な手順は `.claude/skills/agent-orchestrator/SKILL.md` の `/orchestrate` を参照。

### /agent-review-flow

詳細な手順は `.claude/skills/agent-orchestrator/SKILL.md` の `/agent-review-flow` を参照。

## 自律判断可能 / 要相談

詳細は `.claude/skills/agent-orchestrator/SKILL.md` の `## 自律判断可能` と `## 要相談` を参照。
