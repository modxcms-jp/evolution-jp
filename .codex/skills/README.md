# Skills

このディレクトリは、Codex 実行用のアダプタを管理する。
共有スキル本文の正本は `.claude/skills/` にある。主に使うランタイムと正本の置き場所の意味づけは `assets/docs/ai-runtime-governance.md` を正本とし、ここでは Codex 固有の入口とメタデータだけを持つ。

## 正本の分担

- `AGENTS.md`: 共通ルールの正本
- `.agent/agents/`: エージェント責務の正本
- `assets/docs/ai-runtime-governance.md`: ランタイム分担の正本
- `.claude/skills/`: スキル本文の正本
- `CLAUDE.md`: Claude 固有の入口
- `.codex/skills/`: Codex 固有の入口
- `.github/copilot-instructions.md`: Copilot 固有の入口

## 入口の形

各 `SKILL.md` は次の 3 つだけを持つ。

1. frontmatter（`name` と `description`）。Codex はこれでスキルを選ぶので、正本の frontmatter と合わせる。Codex で動きが違う部分（役割を分けるかなど）だけ書き分けてよい
2. 冒頭の 2 行。「このファイルは Codex 実行用の入口とメタデータを管理する。」と、正本のパスと「必ず正本を読み、そのすべてに従う。ここには手順を写さない。」
3. `## Codex での差分`。Codex で手順や歯止めが変わる点があるときだけ置く

手順・ルール・出力形式は、Codex 固有でなければ正本へ書く。正本にない内容を見つけたら、ここではなく正本へ移す。

## 運用原則

- 手順本文の変更は `.claude/skills/` だけを更新する。frontmatter を変えたときと、Codex での差分に関わるときだけ、ここも直す。
- エージェント責務は `.agent/agents/` を参照する。
- 共通ルールは `AGENTS.md` を参照する。
