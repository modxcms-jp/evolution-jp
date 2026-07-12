# Skills

このディレクトリは、Codex 実行用のアダプタを管理する。
主運用ランタイムは Codex だが、共有スキル本文の正本は歴史的経緯と既存参照の互換性のため当面 `.claude/skills/` にある。
このねじれの意味づけは `assets/docs/ai-runtime-governance.md` を正本とし、ここでは Codex 固有の入口とメタデータだけを持つ。

## 正本の分担

- `AGENTS.md`: 共通ルールの正本
- `.agent/agents/`: エージェント責務の正本
- `assets/docs/ai-runtime-governance.md`: ランタイム分担の正本
- `.claude/skills/`: スキル本文の正本
- `CLAUDE.md`: Claude 固有の入口
- `.codex/skills/`: Codex 固有の入口
- `.github/copilot-instructions.md`: Copilot 固有の入口

## 運用原則

- 手順本文の変更は `.claude/skills/` を先に更新する。
- このディレクトリでは Codex 固有の差分だけを管理する。実行環境の差異（`docker compose exec` 形式など）があるコマンドはここに手順を持つことができる。
- エージェント責務は `.agent/agents/` を参照する。
- 共通ルールは `AGENTS.md` を参照する。
- Codex 主運用と共有本文の位置づけは `assets/docs/ai-runtime-governance.md` を参照する。
