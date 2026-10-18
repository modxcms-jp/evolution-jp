---
name: "agent-orchestrator"
description: "`.agent/agents/` のエージェント定義を入口に、タスクを調査・計画・実装・レビュー・検証へ分担するスキル。ユーザーが「エージェント」「マルチエージェント」「orchestrator」「役割分担」「複数担当」などを求めたとき、または大きな変更で担当境界を決める必要があるときに使う。"
---

# Agent Orchestrator

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/agent-orchestrator/SKILL.md` に置く。必ず正本を読み、そのすべてに従う。ここには手順を写さない。

## Codex での差分

- 正本の参照順 5 の「関連する `.claude/skills/*/SKILL.md`」は、対応する `.codex/skills/*/SKILL.md` の「Codex での差分」もあわせて読む。
- 担当を分けると決めた場合も、`assets/docs/ai-runtime-governance.md` のとおり、Codex では 1 つのセッションで担当の順に行う。開始時の宣言では、選んだ担当と順序を示す。
