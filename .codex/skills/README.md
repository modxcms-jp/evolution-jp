# Skills

このディレクトリは、Codex 実行用のアダプタを管理する。
スキル本文の正本は `.claude/skills/` にあり、ここでは Codex 固有の入口とメタデータだけを持つ。

## 正本の分担

- `AGENTS.md`: 共通ルールの正本
- `.agent/agents/`: エージェント責務の正本
- `.claude/skills/`: スキル本文の正本
- `CLAUDE.md`: Claude 固有の入口
- `.codex/skills/`: Codex 固有の入口
- `.github/copilot-instructions.md`: Copilot 固有の入口

## 運用原則

- 手順本文の変更は `.claude/skills/` を先に更新する。
- このディレクトリでは Codex 固有の差分だけを管理する。実行環境の差異（`docker compose exec` 形式など）があるコマンドはここに手順を持つことができる。
- エージェント責務は `.agent/agents/` を参照する。
- 共通ルールは `AGENTS.md` を参照する。
