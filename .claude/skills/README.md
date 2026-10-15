# Skills

このディレクトリは、共有スキル本文の正本を管理する。
主に使うランタイムは Claude Code で、Codex は補佐（PR の補助レビューを含む）。共有本文はどのランタイムからも読むので、Claude Code 固有の癖は書かない。
ランタイム分担の意味づけは `assets/docs/ai-runtime-governance.md` を参照する。

## 正本の分担

- `AGENTS.md`: 共通ルールの正本
- `.agent/agents/`: エージェント責務の正本
- `assets/docs/ai-runtime-governance.md`: ランタイム分担の正本
- `.claude/skills/`: スキル本文の正本
- `.claude/agents/`: Claude Code での役割の割り当て（モデル・effort）の正本
- `CLAUDE.md`: Claude 固有の入口
- `.codex/skills/`: Codex 固有の入口
- `.github/copilot-instructions.md`: Copilot 固有の入口

## 運用原則

- スキル本文の変更は、このディレクトリを先に更新する。
- エージェント責務は `.agent/agents/` を参照する。
- 共通ルールは `AGENTS.md` を参照する。
- ランタイム分担と共有本文の扱いは `assets/docs/ai-runtime-governance.md` に従う。
- ランタイム固有の起動方法やメタデータは、各ランタイム側にのみ記載する。
- Claude / Codex / Copilot 固有の記述を、本文へ混在させない。
