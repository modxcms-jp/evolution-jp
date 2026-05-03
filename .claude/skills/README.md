# Skills

このディレクトリは、Claude と Copilot 系で共有するスキル本文の正本を管理する。
各スキルでは、手順、判断基準、参照順、開始条件、終了条件を定義する。

## 正本の分担

- `AGENTS.md`: 共通ルールの正本
- `.agent/agents/`: エージェント責務の正本
- `.claude/skills/`: スキル本文の正本
- `CLAUDE.md`: Claude 固有の入口
- `.codex/skills/`: Codex 固有の入口
- `.github/copilot-instructions.md`: Copilot 固有の入口

## 運用原則

- スキル本文の変更は、このディレクトリを先に更新する。
- エージェント責務は `.agent/agents/` を参照する。
- 共通ルールは `AGENTS.md` を参照する。
- ランタイム固有の起動方法やメタデータは、各ランタイム側にのみ記載する。
- Claude / Codex / Copilot 固有の記述を、本文へ混在させない。
