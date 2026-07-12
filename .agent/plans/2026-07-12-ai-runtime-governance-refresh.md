# ExecPlan: AIランタイム運用設計の棚卸しと再整理

## Purpose / Big Picture

Codex 主運用へ実態が移っている一方、AI向け文書の正本配置は Claude 起点の歴史を引きずっている。このねじれを可視化し、役割・ランタイム・スキル本文・推論運用を分離した共通方針へ整理する。

## Progress

- [x] (2026-07-12) 現行の AGENTS / `.agent/agents/` / `.claude/skills/` / `.codex/skills/` / Copilot 指示を棚卸しし、責務境界と古い前提を抽出した
- [x] (2026-07-12) 中立な共通ポリシー文書 `assets/docs/ai-runtime-governance.md` を追加し、Codex 主運用と共有本文の扱いを定義した
- [x] (2026-07-12) AGENTS / skills README / orchestrator / Copilot 指示を新ポリシーへ整合させた

## Surprises & Discoveries

- `.codex/skills/README.md` と `.claude/skills/README.md` は責務分担を丁寧に定義しているが、どちらも「なぜ共有本文の正本が `.claude/skills/` にあるのか」を説明していない。
- `agent-orchestrator` は役割分担の条件を持っている一方で、ランタイム選択や高推論への昇格条件は明文化されていない。
- `CLAUDE.md` 自体はすでに薄い入口に整理されており、問題は Claude 固有入口の厚みではなく、共有ポリシーの置き場が不足していることだった。

## Decision Log

- 2026-07-12 / Codex: 共有スキル本文の正本を直ちに `.claude/skills/` から別ディレクトリへ移設しない。参照パスの一斉置換よりも、まず「Codex 主運用だが共有本文は歴史的理由で当面 `.claude/skills/` に置く」という運用方針を中立文書へ切り出す。
- 2026-07-12 / Codex: 新しい正本は `assets/docs/ai-runtime-governance.md` とし、ランタイム間の役割分担、スキル本文の位置づけ、sub-agent 化条件、推論レベル運用をここへ集約する。
- 2026-07-12 / Codex: orchestrator の改善は「役割」と「実行ランタイム」を分離する粒度に留める。具体的なモデル名は変動が大きいため文書へ固定せず、`通常` / `高推論` / `低コスト` の抽象レベルで定義する。

## Outcomes & Retrospective

`assets/docs/ai-runtime-governance.md` を新設し、Codex 主運用と共有スキル本文の歴史的配置を矛盾なく説明できる状態にした。あわせて AGENTS、skills README、Copilot 指示、orchestrator へ参照を通し、役割設計とランタイム設計を分離する基準を共通化した。

## Context and Orientation

対象ファイル:

- `AGENTS.md`
- `.agent/roadmap.md`
- `.agent/agents/README.md`
- `.agent/agents/orchestrator.md`
- `.claude/skills/README.md`
- `.codex/skills/README.md`
- `.github/copilot-instructions.md`
- `CLAUDE.md`
- `assets/docs/ai-runtime-governance.md`（新規）

前提:

- 共通ルールの正本は `AGENTS.md`
- エージェント責務の正本は `.agent/agents/`
- 共有スキル本文は現時点で `.claude/skills/` にある
- 実運用は Codex 主体、Claude は補助利用

## Plan of Work

正本移設のような大規模改造は避け、先に中立な設計文書を追加してから各入口をその文書へ寄せる。これにより、既存の参照構造を壊さずに「なぜこの構造なのか」を説明可能にする。

## Concrete Steps

1. `assets/docs/ai-runtime-governance.md` を新規作成し、次を定義する:
   - Codex / Claude / Copilot の役割
   - 共通ルール、エージェント責務、共有スキル本文、ランタイム固有入口の責務分担
   - sub-agent 化条件
   - `通常` / `高推論` / `低コスト` の使い分け
   編集対象ファイル: `assets/docs/ai-runtime-governance.md`
   実行コマンド: `sed -n '1,220p' assets/docs/ai-runtime-governance.md`
   期待される観測結果: Codex 主運用と共有本文の歴史的配置が同時に説明されている

2. `AGENTS.md` のドキュメントマップと AI 文書運用方針を更新する。
   編集対象ファイル: `AGENTS.md`
   実行コマンド: `rg -n "ai-runtime-governance|AIランタイム|スキル本文" AGENTS.md`
   期待される観測結果: 共通ルールから新文書へ到達できる

3. `.claude/skills/README.md`、`.codex/skills/README.md`、`.github/copilot-instructions.md`、`CLAUDE.md` を共通ポリシーへ整合させる。
   編集対象ファイル: `.claude/skills/README.md`, `.codex/skills/README.md`, `.github/copilot-instructions.md`, `CLAUDE.md`
   実行コマンド: `rg -n "ai-runtime-governance|Codex 主運用|共有スキル本文" .claude/skills/README.md .codex/skills/README.md .github/copilot-instructions.md CLAUDE.md`
   期待される観測結果: どの入口から読んでも共通理解へ辿れる

4. `.agent/agents/README.md` と `.agent/agents/orchestrator.md` に、役割とランタイムを分けて扱う原則と昇格条件を追記する。
   編集対象ファイル: `.agent/agents/README.md`, `.agent/agents/orchestrator.md`
   実行コマンド: `rg -n "ランタイム|高推論|sub-agent|複数AI|役割" .agent/agents/README.md .agent/agents/orchestrator.md`
   期待される観測結果: 役割定義とランタイム運用が混同されていない

## Validation and Acceptance

- `AGENTS.md` から `assets/docs/ai-runtime-governance.md` へ到達できる
- `.codex/skills/README.md` と `.claude/skills/README.md` の双方で、Codex 主運用と共有本文の扱いが矛盾なく説明されている
- `.agent/agents/README.md`、`.agent/agents/orchestrator.md`、`CLAUDE.md`、`.agent/roadmap.md`、`assets/docs/ai-runtime-governance.md` の相互参照と内容が矛盾していない
- `.agent/agents/orchestrator.md` に、sub-agent 化条件と推論レベル運用の抽象方針が追加されている
- `.github/copilot-instructions.md` が新しい共通ポリシー文書を参照している
- Markdown 変更後に `/doc-audit` を実行し、問題が出た場合は `/doc-fix` で修正して再監査し、文書構造・参照整合性に問題がない

## Idempotence and Recovery

すべて文書変更のみ。途中中断時は対象ファイルを再読し、`ai-runtime-governance` の参照がそろっているかを確認して再開する。再開時は `/doc-audit` を実行し、問題があれば `/doc-fix` で修正してから再度監査する。

## Artifacts and Notes

- 起点: 2026-07-12 の AI運用設計棚卸し
- 関連タスク: `.agent/roadmap.md` の基盤整備セクションへ追記する新規タスク

## Interfaces and Dependencies

外部依存なし。既存のスキル参照パスは維持するため、実装コードや CI への影響はない。
