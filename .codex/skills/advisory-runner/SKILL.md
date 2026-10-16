---
name: advisory-runner
description: 非公開のセキュリティアドバイザリ（下書き）で扱う修正を、人の確認を待たずに「準備 → 実装 → 範囲の照合 → 検証 → 自己レビュー → 一時的な非公開フォークへの push → フォーク内の PR」まで進めるスキル。`/run-advisory <GHSA-ID> <計画のパス>` を提供する。ユーザーが「/run-advisory」「アドバイザリを自走で直して」「非公開フォークで修正を進めて」と依頼したときに使う。公開の Issue や ExecPlan を回すなら exec-plan-runner を使う。
---

# Advisory Runner

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/advisory-runner/SKILL.md` に置く。必ず正本を読み、そのすべて（不変条件、一時フォーク、1 件ごとの繰り返し、push と PR、止まる条件）に従う。ここには手順を写さない。

## Codex での差分

- `exec-plan-runner` の Codex での差分（`.codex/skills/exec-plan-runner/SKILL.md`）と同じく、役割は 1 つのセッションで正本の順に行い、格上げは行わない。
- Claude Code の許可設定（`.claude/settings.json` の deny）は Codex には効かない。origin への `advisory/` ブランチの push を止めるのは、正本の不変条件の文面だけになる。push の前に、push 先がフォークの URL であることを必ず照らし合わせる。
