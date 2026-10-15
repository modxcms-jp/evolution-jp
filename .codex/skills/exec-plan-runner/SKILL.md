---
name: exec-plan-runner
description: Issue または ExecPlan を、人の確認を待たずに「準備 → 実装 → 範囲の照合 → 検証 → 自己レビュー → push → PR」まで進めるスキル。`/run-issue <番号>`・`/run-plan <ExecPlan のパス>`・`/finish-task` を提供する。ユーザーが「/run-issue」「/run-plan」「Issue を自走で直して」「ExecPlan を最後まで進めて」と依頼したときに使う。ExecPlan を作るだけなら exec-plan、次のタスクを選ぶだけなら roadmap-manager を使う。
---

# Exec Plan Runner

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/exec-plan-runner/SKILL.md` に置く。必ず正本を読み、その手順・不変条件・止まる条件・確認を省く範囲に従う。ここには手順を写さない。

## Codex での回し方

- 正本の「役割」（explorer・planner・worker・tester・reviewer）は、役割を別のエージェントに分けずに、1 つのセッションで同じ順に行う。役割ごとのモデルや推論の強さの割り当て（`.claude/agents/`）は Claude Code 用で、Codex では使わない。
- 分けずに行う場合も、自己レビューは省かない。実装を終えたら、いったん実装の文脈から離れて、`.agent/agents/reviewer.md` の観点で main との差分全体を読み直し、重大度順の指摘を書き出してから直す。
- 正本で「メイン」が行うこと（ワークツリーの用意、push、PR、GitHub への書き込み）は、このセッションが行う。
- 不変条件（main への push・強制 push・`gh pr merge`・`gh release` をしない、非公開アドバイザリに関わる修正を扱わない など）と止まる条件は、正本のとおりに守る。
