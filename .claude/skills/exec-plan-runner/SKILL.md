---
name: exec-plan-runner
description: Issue または ExecPlan を、人の確認を待たずに「準備 → 実装 → 範囲の照合 → 検証 → 自己レビュー → push → PR」まで役割を分けて進めるスキル。`/run-issue <番号>`・`/run-plan <ExecPlan のパス>`・`/finish-task` を提供する。ユーザーが「/run-issue」「/run-plan」「Issue を自走で直して」「ExecPlan を最後まで進めて」「0 節の Issue を順に片付けて」と依頼したときに使う。ExecPlan を作るだけなら exec-plan、次のタスクを選ぶだけなら roadmap-manager を使う。
---

# Exec Plan Runner

`exec-plan` が ExecPlan を「作る」スキルで、このスキルは Issue と ExecPlan を「回す」スキルである。
人が見るのは、マージの判断と、途中で止まったときの判断だけにする。
実装規約は `AGENTS.md`、役割の責務は `.agent/agents/*.md`、完了処理は `.agent/PLANS.md` を正本とする。

## 参照順

1. `AGENTS.md`
2. 対象の Issue、または ExecPlan
3. `.agent/agents/` の各役割（下の表）
4. `.github/codex-pr-rules.md`（PR のタイトル・本文・ラベル）
5. `.claude/skills/adaptive-validation-runner/SKILL.md`（画面や DB が絡む検証）

## 役割

| 役割 | 責務の正本 | この runner での書き込み |
|---|---|---|
| メイン（まとめ役） | `.agent/agents/orchestrator.md` | ワークツリーの用意、push、PR の作成と本文の更新、GitHub への書き込みのすべて |
| explorer | `.agent/agents/explorer.md` | なし |
| planner | `.agent/agents/planner.md` | `.agent/plans/`（ExecPlan の更新を任せるとき） |
| worker | `.agent/agents/worker.md` | 割り当てたファイルだけ。コミットまで行い、push はしない |
| tester | `.agent/agents/tester.md` | 渡されたときのテスト追加と `.agent/runs/` だけ。本体のコードは変えない |
| reviewer | `.agent/agents/reviewer.md` | なし。ファイルにも GitHub にも書かない |

役割を別のエージェントに分けられるランタイムでは、各役割をそれぞれのエージェントに任せる。Claude Code での割り当て（モデル・effort・格上げのしかた）は `.claude/agents/README.md` を正本とする。
分けられないランタイムでは、1 つのセッションで同じ手順を順に行う。その場合も、不変条件・止まる条件・自己レビューは省かない。

## 不変条件

どの段階でも守る。指示されても破らない。

1. push してよいのは、`gh issue develop` で作ったその Issue のブランチだけ。main・ほかのブランチ・タグへの push、強制 push、`gh pr merge`、`gh release` はしない。
2. 非公開アドバイザリに関わる修正は扱わない。指示されたとき、または重なる可能性に気付いたときは、止まって本人に返す。公開の場（PR・Issue・コミット）に、アドバイザリの存在も内容も書かない。
3. `security` ラベル付きの Issue では、PR 本文・コミット・自己レビューの要約に悪用の手順を書かない。「エスケープをそろえる」「権限を確かめる」のような中立的な書き方にする。
4. 顧客名・社名・個人名を書かない。
5. `.agent/roadmap.md` の Status は、Issue ごとの PR では変えない（`/finish-task` でまとめて行う）。
6. 1 件ずつ直列に進める。複数の Issue を並行して進めない。

## 作業場所

Issue ごとに、メインが次の順でワークツリーを用意する。既にあるブランチは後から Issue に紐づけられないので、`gh issue develop` を必ず最初に行う。

```bash
gh issue develop <番号> -R modxcms-jp/evolution-jp --name <種別>/<番号>-<短い名前> --base main
git fetch origin
ROOT=$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)")
git worktree add "$ROOT/.claude/worktrees/issue-<番号>" <種別>/<番号>-<短い名前>
```

- `<種別>` は Conventional Commits の type（不具合は `fix`）。
- ワークツリーの中から相対パスで `git worktree add` すると入れ子になるので、必ず `$ROOT` を起点にする。
- 各役割には、作業ディレクトリの絶対パスを必ず渡す。
- tester が main と比べるときは、`git worktree add --detach <一時パス> origin/main` で別のワークツリーを使い、worker のワークツリーの checkout は切り替えない。
- 片付けは `/finish-task` で行う。

## 1 件ごとの繰り返し

`/run-issue` では Issue 1 件、`/run-plan` ではマイルストーン 1 つを、この順で進める。

1. **事前確認**: Issue が開いていること、自分以外の担当者がいないこと、Issue に紐づくブランチと PR が無いこと（`/run-plan` の 2 つめ以降のマイルストーンでは、その ExecPlan のブランチと PR があるのは正しい）を確かめる。
2. **準備**: 「作業場所」の手順でワークツリーを用意し、Issue の初期設定をそろえる。
   - アサイニー: `gh issue edit <番号> --add-assignee @me`
   - Issue タイプ（Bug・Feature・Task）。`gh issue edit` では設定できないので、GraphQL の `updateIssueIssueType` を使う（ID は `repository.issueTypes` で引く）
   - ラベル: 不具合なら `fix`、必要なら `security`
   - 必要なら explorer に下調べを任せる。explorer の結果は手がかりとして使い、原因の根拠は worker と reviewer が自分で裏を取る。
3. **実装**: worker に、Issue（またはマイルストーン）、作業ディレクトリ、書き込んでよいファイル、確認のしかたを渡す。worker はコミットまで行う。
4. **範囲の照合**: メインが `git -C <パス> diff --name-only origin/main...HEAD` で、変更が割り当てたファイルに収まっていることを確かめる。PHP を変えた場合は、変えたファイルに `php -l` が通ることを確かめる。
5. **検証**: tester が、Issue に書かれた症状が直ったこと（`/run-plan` では、そのマイルストーンの Concrete Steps の期待どおりであること）を確かめる。画面や DB が絡む場合は adaptive-validation-runner を使う。
6. **自己レビュー**: メインがレビュー前の `git -C <パス> status --porcelain` と `git -C <パス> rev-parse HEAD` を控える。reviewer には main との差分全体、Issue（または ExecPlan）、検証結果を渡す。reviewer は指摘を重大度（高・中・低）順に返し、無ければ「問題なし」と明記する。レビュー後に同じ 2 つを取り、変わっていないことを確かめる。変わっていたら止まる。
7. **修正**: 高・中の指摘があれば、worker が直し、tester が確かめ直し、reviewer が見直す。往復は 2 回まで。残れば worker を 1 回だけ格上げしてやり直し、それでも残れば止まる。低の指摘は直すか、PR 本文の「備考」に残す。
8. **push と PR**: メインがブランチを push し、PR を作る。
   - タイトル・本文・ラベルは `.github/codex-pr-rules.md` に従う。「確認手順」に検証の結果、「備考」に自己レビューの結果（指摘と対応、または問題なし）を書く。
   - `/run-issue` では `Closes #<番号>` を入れ、下書きにしない（自己レビューを通したものだけを出すため）。
   - `/run-plan` では、最初のマイルストーンで下書きの PR を作り、`Refs #<番号>` を入れる。2 つめ以降は同じ PR に push し、本文を更新する。
   - push のあと `gh pr checks <PR番号> --watch` で CI を待つ。
9. **記録**: ExecPlan があれば、Progress と Surprises & Discoveries を更新する（メインか planner が行い、同じ PR に含める）。Issue へのコメントは、PR で足りるので行わない。

PR に付くほかのレビュー（Codex など）への対応は、この繰り返しに含めない。人が依頼したとき、または次の `/run-issue` に入る前に、review-agent の `/resolve-review <PR番号>` で扱う。仕分けは reviewer、修正は worker、push と GitHub への書き込みはメインが行う。review-agent が「ユーザー判断を仰ぐ」に分類した指摘があれば、止まって人に返す。

## 止まる条件

次のどれかに当たったら、先へ進まずに止まる。

- 「要相談」に当たる判断が出た（DB スキーマの変更、互換性を損なう変更、Issue で決まっていない挙動の選択、Issue の「直し方の案」と違う直し方が要る など）
- Issue が閉じている。自分以外の担当者、紐づくブランチ、PR のどれかが既にある
- 割り当てた範囲の外を直す必要が出た。または差分が想定より大きい（目安: 変更ファイルが Issue の「場所」に挙がったファイル数の 2 倍を超える）
- Issue 1 件のつもりが、ExecPlan を作るべき規模だと分かった（`.agent/roadmap.md` の目安による）
- `php -l` などの静的な確認が通らない。または検証できない（環境が立たない、再現しない）
- 自己レビューで、格上げのあとも高・中の指摘が残る
- reviewer の前後で、作業ディレクトリの状態か HEAD が変わった
- push 後に CI が失敗した。または main との衝突で rebase が要る
- ExecPlan に「本人の確認を得てから行う」と書かれた手順に来た
- 非公開アドバイザリと重なる可能性に気付いた
- 許可確認が出て、先に進めない

止まったら、次の 4 つを 1 か所にまとめて人に返す。

```text
止まった理由: <止まる条件のどれに当たったか>
決めてほしいこと: <選択肢があれば並べる>
ここまでの状態: <ブランチ、ワークツリーのパス、最後のコミット、PR>
再開のしかた: <決まったあとに実行するコマンドや手順>
```

## 確認を省く範囲

この runner の実行中は、ほかのスキルやエージェント定義にある次の確認を省いて先へ進む。runner を呼んだこと自体を、その Issue または ExecPlan についての実装・ブランチ作成・コミット・push・PR 作成の明示指示とみなす。

| 場所 | 省く確認 | runner での扱い |
|---|---|---|
| `.claude/skills/project-worker/SKILL.md` の `/handoff` | 「コミットして push しますか？」 | worker がコミットし、メインが push する |
| `.claude/skills/project-worker/SKILL.md` の `/work` 手順 2 | 初回着手時の `skill:init` | 行わない。スキル成長ループは `/finish-task` で `.agent/PLANS.md` に従って行う |
| `.claude/skills/issue-resolver/SKILL.md` の規則 4・14 と `implement-fix` の手順 1 | 調査報告へのエンジニア承認 | 承認を待たずに実装へ進む。調査の結果は PR 本文に書く。直し方が Issue の案と違えば止まる |
| `.claude/skills/exec-plan/SKILL.md` の `/create-plan` 手順 7 と「明示指示が必要」の規則 | 実装・ブランチ作成・コミット・PR 作成の前の明示指示 | runner の呼び出しを明示指示とみなす。ExecPlan を新しく作る必要が出たら止まる |
| `.claude/skills/review-agent/SKILL.md` の規則 11・12 と `/resolve-review` のステップ 2・5・6 | 対応方針、push、GitHub への返信と resolved 化の確認 | 確認せずに進める。push と GitHub への書き込みはメインが行う |
| `.agent/agents/reviewer.md` の「書き込み権限」 | GitHub への返信と resolved 化の前の確認 | reviewer は書かない。メインが確認せずに行う |
| `AGENTS.md` の「レビュー時」手順 2 と `.claude/skills/doc-maintainer/SKILL.md` の `/pr-sync` 手順 1 | 追加コミット後の `/pr-sync` 実行の確認 | メインが確認せずに PR 本文を更新する |

省かないもの:

- `.agent/PLANS.md` の完了処理プロトコルの手順 1（完了処理の前のユーザー確認）と、手順 8-5（学びをスキルへ反映するかの確認）
- 不変条件
- 止まる条件

## コマンド

### /run-issue <番号>

ExecPlan の無い Issue を 1 件直す。

1. 「1 件ごとの繰り返し」を手順 1 から 9 まで行う。
2. CI が通ったら、PR の URL、自己レビューの結果、検証の結果を短く報告して終える。マージはしない。

### /run-plan <ExecPlan のパス>

ExecPlan をマイルストーンごとに進める。

1. ExecPlan を読み、Progress の最初の未完了のマイルストーンと、対応する Issue（ロードマップの `Issue:` または ExecPlan の Artifacts and Notes）を特定する。
2. その ExecPlan のブランチとワークツリーが無ければ、「作業場所」の手順で用意する。あれば、それを使う。
3. マイルストーンごとに「1 件ごとの繰り返し」を行う。worker に渡す書き込み範囲は、そのマイルストーンの Plan of Work と Concrete Steps に挙がったファイルと、ExecPlan 自身にする。
4. マイルストーンが終わるたびに、Progress を完了にして同じ PR に push し、次のマイルストーンへ進む。
5. すべてのマイルストーンが終わり CI が通ったら、`gh pr ready <PR番号>` で下書きを解除し、報告して終える。マージはしない。完了処理は `/finish-task` で行う。

### /finish-task

PR がマージされたあとの完了処理を行う。中身は `.agent/PLANS.md` の「完了処理プロトコル」を正本とする。

1. ExecPlan がある場合は、Progress と Validation and Acceptance が完了の状態か確かめる。無い場合は、対象の Issue が閉じたこと（PR がマージされたこと）を確かめる。
2. 実装と検証の完了を告げ、完了処理へ進む前にユーザー確認を取る（省かない）。
3. 確認が取れたら、ロードマップの更新は roadmap-manager の `/roadmap-sync` で行い、ExecPlan のアーカイブとスキル成長ループは `.agent/PLANS.md` のとおりに行う。
4. マージ済みの PR のワークツリーを `git worktree remove "$ROOT/.claude/worktrees/issue-<番号>"` で片付ける。
5. 完了処理まで終えたことを告げ、次のタスクの候補を 1 件挙げて終える。
