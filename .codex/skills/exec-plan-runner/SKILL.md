---
name: exec-plan-runner
description: Issue または ExecPlan を、人の確認を待たずに「準備 → 実装 → 範囲の照合 → 検証 → 自己レビュー → push → PR」まで進めるスキル。`/run-issue <番号>`・`/run-plan <ExecPlan のパス>`・`/finish-task` を提供する。ユーザーが「/run-issue」「/run-plan」「Issue を自走で直して」「ExecPlan を最後まで進めて」「0 節の Issue を順に片付けて」と依頼したときに使う。ExecPlan を作るだけなら exec-plan、次のタスクを選ぶだけなら roadmap-manager を使う。
---

# Exec Plan Runner

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/exec-plan-runner/SKILL.md` に置く。必ず正本を読み、そのすべて（不変条件、1 件ごとの繰り返し、止まる条件、確認を省く範囲）に従う。ここには手順を写さない。

## Codex での差分

- 正本の役割は、別のエージェントに分けずに 1 つのセッションで正本の順に行う。正本で「メイン」が行うことも、このセッションが行う。Claude Code 用の割り当て（`.claude/agents/`）は使わない。
- 正本の手順 7 の「格上げ」は行わない。自己レビューを 2 往復しても高・中の指摘が残れば止まる。
- Claude Code の許可設定（`.claude/settings.json` の deny）は Codex には効かない。main への push などを止めるのは、正本の不変条件の文面だけになる。
