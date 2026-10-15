# AIランタイム運用方針

この文書は、AI向け運用設計におけるランタイム分担の正本を定義する。
共通ルールは `AGENTS.md`、エージェント責務は `.agent/agents/`、ここでは「どの AI をどう使い分けるか」と「共有本文をどこに置くか」を整理する。

## 現在の運用モデル

- 主に使うランタイムは Claude Code とする（2026-10-15 から。経緯は `.agent/plans/2026-10-15-orchestration-runner.md` の Decision Log）
- Codex は補佐として併用する。PR の補助レビューと、Codex で同じ手順を回す作業を含む
- Copilot 系は補助的な参照先、レビュー補助、指示ファイル読者として扱う

共有スキル本文の正本は `.claude/skills/` に置く。主に使うランタイムの側にあるが、本文はどのランタイムからも読む共有本文として書き、Claude Code 固有の癖を入れない（設計原則 3）。

各ディレクトリの意味付けは次の通り。

- `.claude/skills/` は共有スキル本文の正本
- `.claude/agents/` は Claude Code で役割ごとにモデルと推論の強さを決めて呼ぶための定義
- `.codex/skills/` は Codex 固有の入口とメタデータ
- `CLAUDE.md` は Claude 固有の入口
- `.github/copilot-instructions.md` は Copilot 系の入口

## 正本の分担

- `AGENTS.md`: 共通ルール、判断基準、ワークフロー
- `.agent/agents/`: エージェント責務、成果物、書き込み境界
- `assets/docs/ai-runtime-governance.md`: ランタイム分担、共有本文の位置づけ、推論運用
- `.claude/skills/`: 共有スキル本文の正本
- `.claude/agents/`: Claude Code での役割の割り当て（モデル・effort）の正本
- `.codex/skills/`: Codex 固有の入口と実行時差分
- `CLAUDE.md`: Claude 固有の起動入口
- `.github/copilot-instructions.md`: Copilot 系の入口

### CLAUDE.md から AGENTS.md を取り込む理由

`CLAUDE.md` は `@AGENTS.md` の1行だけにして、`AGENTS.md` を取り込む。Claude 固有の指示が必要になったときだけ、その下に書き足す。

Claude Code は v2.1.277 から `AGENTS.md` を直接読むようになった。ただし既定の設定では、`CLAUDE.md`、`.claude/CLAUDE.md`、`CLAUDE.local.md` のどれかがあると `AGENTS.md` を読まない。テレメトリを無効にした環境や、Bedrock・Vertex・Foundry 経由の環境でも読まれない。
`CLAUDE.md` をなくして `AGENTS.md` だけに頼ると、こうした条件に当たったときに `AGENTS.md` が読まれないまま作業が進み、気付きにくい。
`@AGENTS.md` で取り込んでおけば、設定や環境によらず必ず読まれる。両方を読む設定でも二重には読み込まれない。

## 設計原則

### 1. 役割とランタイムを分離する

`explorer`、`planner`、`worker`、`reviewer` などは「役割」であり、Codex / Claude / Copilot は「実行ランタイム」である。
役割設計とランタイム設計を混同しない。

例:

- `planner` 役は高推論で実行したい場合がある
- `explorer` 役は低コストな広域調査で十分な場合がある
- `reviewer` 役は根拠精査のため高推論へ昇格させる価値がある

### 2. Claude Code での動作を優先し、Codex でも同じ手順で動かす

Claude Code で通常作業が完結することを優先する。
Codex でも同じ手順・不変条件・止まる条件で動くよう、共有本文は役割と手順で書き、Codex 側の入口は正本への参照と実行時差分だけを持つ（Codex 用の写しを薄くする作業は #680）。

### 3. 共有本文にはランタイム固有の癖を書かない

共有スキル本文には、Claude / Codex / Copilot の固有制約を混在させない。
ランタイム固有の起動方法、メタデータ、コマンド差分、出力形式の補足は各入口側に限定する。モデル名も共有本文には書かず、Claude Code では `.claude/agents/` の frontmatter にだけ書く。

## sub-agent と複数AI協調

sub-agent は「別の責務へ仕事を切り分ける戦略」であり、特定ランタイム専用の概念ではない。
どのランタイムで使うときも、同じ責務境界を守る。
Claude Code では `.claude/agents/` の定義で、役割ごとにモデルと推論の強さを変えて呼ぶ。Codex では現時点では役割を分けずに、1 つのセッションで同じ手順を順に行う（分けられるかは #680 で確かめる）。
Claude Code の許可設定（`.claude/settings.json` の deny）は Codex には効かない。Codex で runner を動かすときは、不変条件の文面だけが歯止めになる。

### sub-agent 化する条件

- 調査、計画、実装、レビュー、検証のうち少なくとも2つが独立して並行化できる
- 書き込み範囲を衝突なしで分離できる
- 役割ごとに求める推論強度や観点が明確に異なる
- 単独実行だと見落としや長文コンテキスト化のコストが高い

`exec-plan-runner` と兄弟スキルの `advisory-runner` は例外で、直列で進めるときも役割を分ける（理由は `.claude/skills/exec-plan-runner/SKILL.md` の「役割」）。

### sub-agent 化しない条件

- 単一ファイルの小修正で終わる
- 調査結果を読むだけでそのまま実装できる
- 役割を分けても編集範囲が重なり、統合コストの方が高い

## 推論レベル運用

具体的なモデル名は変動するため、この文書では抽象レベルで定義する。

- `通常`: 日常的な実装、既存パターン追従、軽微な文書修正
- `高推論`: ExecPlan 作成、レビュー、難しい設計判断、切り分けの袋小路
- `低コスト`: 広域 grep、単純整形、参照確認、棚卸しの一次走査

### 役割ごとの目安

- `explorer`: まず `低コスト` または `通常`
- `planner`: 原則 `高推論`
- `worker`: 原則 `通常`、難所だけ `高推論`
- `reviewer`: 原則 `高推論`
- `tester`: 再現調査は `通常`、失敗原因の精査は `高推論`

Claude Code での具体的な割り当て（モデル・effort・格上げのしかた）は、`.claude/agents/` を正本とする（一覧は `.claude/agents/README.md`）。

## 将来の移行条件

共有スキル本文の保管場所を `.claude/skills/` から中立パスへ移すのは、次のいずれかが成り立つ場合に検討する。

- Codex 側の入口差分が増え、共有本文と分離しづらくなった
- 主に使うランタイムが Claude Code でなくなった
- 参照パスの一斉更新を受け入れられるタイミングが来た

それまでは `.claude/skills/` を共有本文の正本とする。
