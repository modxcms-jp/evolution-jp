# Claude Code のサブエージェント定義

このディレクトリは、`.agent/agents/` の役割を Claude Code のサブエージェントとして動かすための入口である。

- 役割の責務と書き込み範囲の正本は `.agent/agents/*.md`。ここの本文は「それを読んで従う」薄い入口にとどめ、内容を写さない。
- モデルと effort の割り当ては、各ファイルの frontmatter を正本とする。`.agent/agents/` と `assets/docs/ai-runtime-governance.md` には、モデル名を書かない。
- 割り当ての理由と出典は `.agent/plans/2026-10-15-orchestration-runner.md` の Decision Log を参照。

| 名前 | 役割の正本 | model | effort |
|---|---|---|---|
| `evo-explorer` | `explorer.md` | `haiku` | `medium` |
| `evo-planner` | `planner.md` | `opus` | `high` |
| `evo-worker` | `worker.md` | `sonnet` | `medium` |
| `evo-tester` | `tester.md` | `sonnet` | `medium` |
| `evo-reviewer` | `reviewer.md` | `opus` | `high` |

`exec-plan-runner` の役割（explorer・planner・worker・tester・reviewer）は、表のそれぞれのエージェントに任せる。メイン（まとめ役）は、人と対話しているセッション自身が務める。

格上げ（`exec-plan-runner` の「修正」の手順）: 自己レビューで 2 往復しても高・中の指摘が残ったら、同じ作業を `evo-worker` に呼び出し時の `model: opus` を指定して 1 回だけやり直させる。呼び出しごとの `model` 指定は frontmatter より優先される。effort は frontmatter の `medium` のまま。

## 確かめ方（2026-10-15 に確認）

定義を変えたら、次の方法で効き方を確かめる。

- 認識されているか: 実行中のセッションの一覧は起動時に固定されるので、新しいセッションで確かめる。対話なら `/agents`。非対話なら、ワークツリーで `claude -p` を起動し、`evo-` で始まる subagent_type を列挙させる。`claude agents --json` はセッションの一覧で、定義の一覧ではない。
- 使われたモデルと effort: `claude -p --output-format stream-json --verbose` で起動し、サブエージェントを呼ばせる。記録 `~/.claude/projects/<プロジェクト>/<セッションID>/subagents/` の `agent-*.meta.json` の `agentType` と、対応する `agent-*.jsonl` の `"model"`・`"effort"` を照らし合わせる。最終行の `modelUsage` はモデルごとの合計なので、メインと同じモデルの役（opus）は見分けられない。サブエージェントに自分のモデル名を答えさせても、それは本人の申告で、裏付けにならない。
- 許可設定: 入れ子のセッションに `git push --dry-run …` を 1 つずつ実行させ、結果の `permission_denials` を見る。`--dry-run` なので、通っても送られない。auto モードのままだと、拒否したのが deny のルールか auto モードの判定かを見分けられない（メッセージは同じ）。deny だけの効果を確かめるときは、`--permission-mode default --settings '{"permissions":{"allow":["Bash(git push --dry-run *)","Bash(git -C * push --dry-run *)"]}}'` で起動する。allow に入っていても拒否されたものが、deny で止まったものである。`--permission-mode bypassPermissions` での入れ子起動は、auto モードの判定に断られる。
- deny のパターンは、`git push …` と `git -C <パス> push …` の両方の形で書く。片方だけだと、もう片方の書き方で抜ける。

## 分かっている限界

- `model: opus` は `claude-opus-5-5`（1M 版ではない）になる。メインのセッションが `opus[1m]` でも、サブエージェントは引き継がない。
- `evo-reviewer` は Write・Edit を持たないが、Bash を持つので、リダイレクトで書くことは仕組みとしては止まっていない。試験ではファイルの作成を頼んでも断ったが、それは指示に従った結果である。レビューの前後に、メインのセッションが `git status --porcelain` と `git rev-parse HEAD` を照合する。
- `.claude/settings.json` の deny は、リポジトリでのすべての Claude Code のセッションに効き、runner の実行中だけに絞れない。そのため、止めるのは main への push と強制 push だけにしている。`gh pr merge`・`gh release`・タグの push は、本人の作業やリリース手順でも使うので止めない。
- ワークツリーの中で `git worktree add .claude/worktrees/<名前>` を相対パスで実行すると、そのワークツリーの中に入れ子で作られる。本体のルート `$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)")` を起点にする。
