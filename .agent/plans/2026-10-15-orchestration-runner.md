# ExecPlan: ExecPlan と Issue を、実装役 Sonnet・レビュー役 Opus のサブエージェントで自分で進められるようにする

## Purpose / Big Picture

ロードマップの項目や Issue を、AI が人の確認を待たずに「実装 → 検証 → 自己レビュー → push」まで進められるようにする。実装は軽快で安い Sonnet、レビューは深く読める Opus のサブエージェントに任せ、push する前に必ず自己レビューを通す。人が見るのはマージの判断と、途中で出た「要相談」の判断だけにする。

最初の使い道は、ロードマップ「0. 全体点検で見つかった不具合の修正」の 32 件（#634〜#665）を一気に片付けることである。

## Progress

- [ ] (2026-10-15) M1: Claude Code 用のエージェント定義（`.claude/agents/`）を作り、モデル・推論の強さ・書き込み範囲が想定どおり効くことを確かめる
- [ ] (2026-10-15) M2: ExecPlan と Issue を実行するスキル `exec-plan-runner`（`/run-plan`・`/run-issue`・`/finish-task`）を作る
- [ ] (2026-10-15) M3: `roadmap-next-task` を廃止し、`/next-task` を `roadmap-manager` へまとめる。参照を更新する
- [ ] (2026-10-15) M4: 自分で進めると止まる確認を、`exec-plan-runner` の実行中だけ省けるようにする。ランタイム分担の文書を「Claude Code が主、Codex は補佐」に改める
- [ ] (2026-10-15) M5: 0 節の小さな Issue 2 件で試運転し、手順と止まる条件を直す

## Surprises & Discoveries

## Decision Log

- 2026-10-15 (yamamoto/Claude): 実装役は Sonnet、レビュー役は Opus とする。Sonnet はサブエージェントとして呼ぶ実装役で費用対効果がよく、Opus はレビューで効果が大きい、という本人の見立てによる。
- 2026-10-15 (yamamoto/Claude): 品質の主な関門は push 前の自己レビュー（Opus のレビュー役）とする。PR に付く Codex のレビューは補助として扱い、妥当なものだけ直す。
- 2026-10-15 (yamamoto/Claude): Claude Code を主なランタイム、Codex を補佐とする。Codex 側に同じ役割分担を作ることは、この計画の対象外とする（入口の参照だけ整える）。
- 2026-10-15 (yamamoto/Claude): モデル名は `.claude/agents/*.md` の frontmatter にだけ書く。`.agent/agents/` と `assets/docs/ai-runtime-governance.md` は抽象的な段階（通常・高推論・低コスト）のまま残す。モデルが変わったときに直すのは 1 か所で済む。モデルは `sonnet`・`opus` などの別名で書き、版番号を固定しない。
- 2026-10-15 (yamamoto/Claude): スキルは「作る」`exec-plan` と「回す」`exec-plan-runner` の対にする。`roadmap-next-task` は廃止し、選定（`/next-task`）は `roadmap-manager` にまとめる。重複していた `/roadmap-next` は消す。`/next-task` という名前は使い慣れているので残す。
- 2026-10-15 (yamamoto/Claude): 役割の責務の正本は `.agent/agents/*.md` のまま。`.claude/agents/*.md` の本文は「対応する `.agent/agents/<役割>.md` と `AGENTS.md` を読んで従う」という薄い入口にし、内容を写さない（#680 と同じ問題を増やさない）。

## Outcomes & Retrospective

## Context and Orientation

用語:

- サブエージェント: メインの Claude Code セッションから仕事を任せる別の Claude。自分の指示文（システムプロンプト）・使えるツール・モデルを持つ。`.claude/agents/<名前>.md` で定義する
- frontmatter: Markdown の先頭の `---` で囲んだ設定欄
- 推論の強さ（effort）: 考える量の設定。`low`・`medium`・`high`・`xhigh`・`max`
- 自己レビュー: push する前に、実装した本人とは別のレビュー役が差分を読み、問題を指摘すること

関係するファイル:

- `.agent/agents/*.md`: 役割（orchestrator・explorer・planner・worker・reviewer・tester・roadmap-curator）の責務の正本
- `assets/docs/ai-runtime-governance.md`: ランタイム分担と推論運用の正本。今は「主運用は Codex」と書いてある
- `.claude/skills/`: スキル本文の正本。`.codex/skills/` は Codex 用の入口（#680 で薄くする予定）
- `.claude/skills/roadmap-next-task/SKILL.md`: 今の `/next-task`・`/finish-task`・`/sync-roadmap`
- `.claude/skills/roadmap-manager/SKILL.md`: ロードマップの追加・変更・削除。`/roadmap-next` が `/next-task` と重複している
- `.claude/skills/project-worker/SKILL.md`: `/work`・`/handoff`。`/handoff` は毎回「コミットして push しますか？」と聞く
- `.claude/skills/review-agent/SKILL.md` と `.agent/agents/reviewer.md`: レビュー。GitHub への返信や resolved 化の前に確認を求める
- `.claude/skills/adaptive-validation-runner/`: Docker の検証環境で動作を確かめるスキル
- `.agent/PLANS.md`: ExecPlan の書式と完了処理

Claude Code のサブエージェント定義で使える主な欄（公式ドキュメント https://code.claude.com/docs/en/sub-agents.md）:

- `name`・`description`（必須）、`model`（`sonnet`・`opus`・`haiku`・`inherit` など）、`effort`、`tools`・`disallowedTools`、`isolation: worktree`、`maxTurns`、`permissionMode`
- `tools` を省くと親のツールをすべて受け継ぐ。`Bash` を残すとファイルを書けるので、書き込み禁止は `disallowedTools: Write, Edit` だけでは完全ではない
- `isolation: worktree` は一時的なワークツリーで動かす。分岐元は既定でリポジトリの既定ブランチ（main）
- サブエージェントからさらにサブエージェントを呼べる（既定で 3 階層まで）

## Plan of Work

### 役割とモデルの対応（M1）

`.claude/agents/` に次の 5 つを置く。本文は「`AGENTS.md` と `.agent/agents/<役割>.md` を読み、それに従う。返す成果物の形」だけを書く。

| 名前 | model | effort | 書き込み | 主な用途 |
|---|---|---|---|---|
| `evo-explorer` | `haiku` | `low` | なし（Read・Grep・Glob・読み取り用の Bash） | 既存実装や影響範囲の下調べ |
| `evo-planner` | `opus` | `high` | `.agent/plans/` だけ | ExecPlan の作成と更新 |
| `evo-worker` | `sonnet` | `medium` | 割り当てたファイルだけ | 実装・コミット・push |
| `evo-tester` | `sonnet` | `medium` | なし（検証環境の操作は可） | 再現と動作確認（adaptive-validation-runner） |
| `evo-reviewer` | `opus` | `high` | なし（`disallowedTools: Write, Edit`） | push 前の自己レビュー、Codex 指摘の仕分け |

名前に `evo-` を付けるのは、Claude Code 組み込みの `Explore`・`Plan` などや、利用者の個人用エージェントと区別するため。orchestrator はメインのセッションが受け持つ（振り分けと統合だけを行い、自分では大きな実装をしない）。

worker のワークツリーの扱い: Issue ごとのブランチは `gh issue develop` で先に作る（Issue に紐づけるため）。worker は `isolation: worktree` を使わず、メインのセッションが用意した Issue 用のワークツリーで作業する。並行して動かすときは、Issue ごとに別のワークツリーを用意する。M1 の試験で、`isolation: worktree`（分岐元 main）で作業して紐づけ済みブランチへ push する方式と比べ、扱いやすい方に決めて Decision Log に残す。

### 実行の繰り返し（M2）

新しいスキル `exec-plan-runner` に次のコマンドを置く。

- `/run-issue <番号>`: ExecPlan の無い Issue を 1 件直す
- `/run-plan <ExecPlan のパス>`: ExecPlan をマイルストーンごとに進める
- `/finish-task`: 完了処理（今の `roadmap-next-task` から移す）。中身は `.agent/PLANS.md` の完了処理を正本とする

1 件（Issue 1 件、またはマイルストーン 1 つ）ごとの繰り返し:

1. 準備: Issue の初期設定（`gh issue develop`、アサイニー、タイプ、ラベル）。必要なら evo-explorer に下調べを任せる
2. 実装: evo-worker に、Issue（または ExecPlan のマイルストーン）、書き込んでよいファイル、検証のしかたを渡す。worker はコミットまで行い、まだ push しない
3. 検証: evo-tester が、Issue に書いた症状が直ったことを確かめる。画面や DB が絡む場合は adaptive-validation-runner を使う
4. 自己レビュー: evo-reviewer が、main との差分全体・Issue・検証結果を読み、重大度順に指摘を返す。指摘が無ければ「問題なし」と明記する
5. 修正: 指摘があれば worker が直し、reviewer が見直す。往復は 2 回まで
6. push と PR: 自己レビューを通ったら push し、PR を作る（本文に `Closes #番号` と自己レビューの結果の要約）
7. 補助のレビュー: Codex のレビューが付いたら evo-reviewer が仕分け、妥当なものだけ worker が直す。返信と resolved 化まで行う
8. 記録: ExecPlan があれば Progress を更新する。Issue に結果をコメントする

止まる条件（人に返す）:

- 「要相談」に当たる判断が出た（DB スキーマの変更、互換性を損なう変更、挙動の選び方が複数あり Issue で決まっていない など）
- 自己レビューで 2 往復しても重大な指摘が残る
- 検証できない（環境が立たない、再現しない）
- 割り当てた範囲の外を直す必要が出た

マージは自分ではしない。人がまとめて行う。

### スキルの整理（M3）

- `roadmap-manager` に `/next-task`（次の項目を選び、ExecPlan が要るかを判断して `exec-plan-runner` へ渡す）と `/sync-roadmap` を移す。`/roadmap-next` を消す
- `roadmap-next-task` を削除する（`.claude` と `.codex` の両方）
- 参照を更新する: `AGENTS.md`、`.claude/skills/README.md`、`.codex/skills/README.md`、`agent-orchestrator`、`.agent/agents/orchestrator.md`・`roadmap-curator.md`、`exec-plan`

### 止まる確認を省く範囲と、ランタイム分担（M4）

`exec-plan-runner` の実行中に限り、次の確認を省いてよいと各スキルに書く。それ以外の場面では今の確認を残す。

- `project-worker` の `/handoff` の「コミットして push しますか？」
- `reviewer` の「GitHub 上へ返信・コメントし、review thread を resolved 化しますか？」
- ExecPlan を伴う作業での毎回の `skill:init`（成長ループは完了処理のときにまとめて行う）

`assets/docs/ai-runtime-governance.md` は「主に使うランタイムは Claude Code、Codex は補佐（PR の補助レビューを含む）」に改める。「役割ごとの目安」の節に、Claude Code での具体的な割り当ては `.claude/agents/` を正本とする、と 1 行足す。

### 試運転（M5）

0 節のうち、小さく独立した 2 件（候補: #642 チャンク削除の権限名、#664 DocManager のログ文言）を `/run-issue` で処理する。繰り返しの各段階が想定どおり動くか、止まる条件が適切かを見て、スキルを直す。

## Concrete Steps

M1:

1. `.claude/agents/evo-explorer.md`・`evo-planner.md`・`evo-worker.md`・`evo-tester.md`・`evo-reviewer.md` を作る
2. 新しいセッションで `/agents` を開き、5 つが表示されることを確かめる
3. evo-reviewer に任意のファイルの書き換えを頼み、Write・Edit が使えずに断ることを確かめる
4. evo-worker を呼んだときに Sonnet で動いていることを、応答の自己申告ではなくセッションの表示（使用モデル）で確かめる

M2〜M4:

5. `.claude/skills/exec-plan-runner/SKILL.md` を作る。Codex 用は `.codex/skills/exec-plan-runner/SKILL.md` に正本への参照だけを置く
6. M3・M4 の各ファイルを更新する
7. `grep -rn "roadmap-next-task\|/roadmap-next" .claude .codex .agent AGENTS.md assets/docs` で参照が残っていないことを確かめる（期待: アーカイブ済みの ExecPlan 以外に出てこない）

M5:

8. `/run-issue 642` と `/run-issue 664` を実行し、それぞれ PR ができること、PR 本文に自己レビューの結果があることを確かめる

## Validation and Acceptance

- `/agents` に evo-explorer・evo-planner・evo-worker・evo-tester・evo-reviewer の 5 つが出る
- evo-reviewer はファイルを書き換えられない
- `/run-issue <番号>` で、人が途中で答えなくても「Issue の初期設定 → 実装 → 検証 → 自己レビュー → push → PR」まで進む。PR 本文に自己レビューの結果（問題なし、または直した指摘）が載る
- 止まる条件に当たったときは、何を決めてほしいかを 1 か所にまとめて人に返す
- `roadmap-next-task` への参照が、アーカイブ済みの ExecPlan 以外に残っていない
- `assets/docs/ai-runtime-governance.md` が「Claude Code が主、Codex は補佐」になっている
- 試運転の 2 件の PR が、レビューのうえマージできる品質になっている

## Idempotence and Recovery

- エージェント定義とスキルはファイルの追加・書き換えだけで、何度やり直しても同じ状態になる
- 試運転の PR が想定どおりでなければ、PR を閉じてブランチを消し、スキルを直してからやり直す
- `roadmap-next-task` を消したあとに問題が出たら、この PR のコミットを戻せば元に戻る

## Artifacts and Notes

- Issue: #684
- 関連: #680（Codex 用スキルの複製）、PR #666（ロードマップ運用の見直し。このブランチの分岐元）
- サブエージェントの公式ドキュメント: https://code.claude.com/docs/en/sub-agents.md 、 https://code.claude.com/docs/en/worktrees.md

## Interfaces and Dependencies

- Claude Code のサブエージェント機能（`.claude/agents/`）。モデルの別名（`sonnet`・`opus`・`haiku`）が指す版は Claude Code 側で変わる
- GitHub CLI（`gh issue develop`、`gh pr create`、GraphQL の `resolveReviewThread`）
- 検証環境（Docker、adaptive-validation-runner）
- PR #666 のマージが前提（ロードマップの書式と ExecPlan の要否判断を使う）
