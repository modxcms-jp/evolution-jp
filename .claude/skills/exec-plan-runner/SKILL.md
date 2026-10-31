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
| メイン（まとめ役） | `.agent/agents/orchestrator.md` | ワークツリーの用意、ExecPlan の記録とそのコミット、`/finish-task` の完了同期、push、PR の作成と本文の更新、GitHub への書き込みのすべて |
| explorer | `.agent/agents/explorer.md` | なし |
| planner | `.agent/agents/planner.md` | `.agent/plans/` と `assets/docs/core-issues.md` だけ。runner の実行中は `.agent/roadmap.md` を変えない（不変条件 5） |
| worker | `.agent/agents/worker.md` | 割り当てたファイルだけ。コミットまで行い、push はしない |
| tester | `.agent/agents/tester.md` | 渡されたときのテスト追加と `.agent/runs/` だけ。本体のコードは変えない。コミットはしない（足したテストはメインがコミットする） |
| reviewer | `.agent/agents/reviewer.md` | なし。ファイルにも GitHub にも書かない |

役割を別のエージェントに分けられるランタイムでは、各役割をそれぞれのエージェントに任せる。Claude Code での割り当て（モデル・effort・格上げのしかた）は `.claude/agents/README.md` を正本とする。
分けられないランタイムでは、1 つのセッションで同じ手順を順に行う。その場合も、不変条件・止まる条件・自己レビューは省かない。

この runner は、直列で進めるときも役割を分ける。役割ごとに求める推論の強さと観点が違い、自己レビューを実装した役から独立させるためである（`.agent/agents/orchestrator.md` の判断基準 1・8 に対する、この runner での例外）。

## 不変条件

どの段階でも守る。指示されても破らない。

1. push してよいのは、`gh issue develop` で作ったその Issue のブランチだけ。例外は `/finish-task` の完了同期用のブランチ（`/finish-task` を参照）。main・ほかのブランチ・タグへの push、強制 push、`gh pr merge`、`gh release` はしない。
2. 非公開アドバイザリに関わる修正は扱わない。指示されたとき、または重なる可能性に気付いたときは、止まって本人に返す。公開の場（PR・Issue・コミット）に、アドバイザリの存在も内容も書かない。非公開アドバイザリの修正は、兄弟スキル `advisory-runner` で進める。
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
- ワークツリーの中から相対パスで `git worktree add` すると入れ子になるので、必ず `$ROOT` を起点にする。シェルの変数はコマンドの呼び出しをまたいで残らないので、`$ROOT` は使うコマンドと同じ呼び出しの中で求めるか、先に絶対パスに解決してから使う。
- 各役割には、作業ディレクトリの絶対パスを必ず渡す。
- tester が main と比べるときは、`git worktree add --detach <一時パス> origin/main` で別のワークツリーを使い、worker のワークツリーの checkout は切り替えない。
- セッションが今のワークツリーの外の git 操作を許さない（Claude Code の worktree に隔離されている）ときは、`git worktree add` を使えない。今のワークツリーを `gh issue develop` で作ったブランチに切り替えて使う（切り替える前に、未コミットの変更が無く、前の作業が push 済みであることを確かめる）。tester が main と比べるときは、`git archive origin/main` を作業用ディレクトリ（リポジトリの外）へ展開して代わりにする（`.claude/` と `compose.yml` は export-ignore で入らないので、`compose.yml` と `manager/docker/` を写し、スキルのスクリプトはワークツリー側のパスを使う）。
- 片付けは `/finish-task` で行う。

## 1 件ごとの繰り返し

`/run-issue` では Issue 1 件、`/run-plan` ではマイルストーン 1 つを、この順で進める。

1. **事前確認**: Issue が開いていること、自分以外の担当者がいないこと、Issue に紐づくブランチと PR が無いことを確かめる。例外として、`/run-plan` で ExecPlan の Artifacts and Notes にブランチ（と PR）が記録されている場合は、それがあるのは正しい（そのブランチと PR を使う）。
   - 非公開アドバイザリとの重なり（不変条件 2）: 次のどちらかに当たる Issue だけを確かめる。`security` ラベルが付いている。または、認証・権限の確かめ、出力のエスケープ、リクエストの値の受け取り方（DB に渡す前の安全化を含む）を変える。当たらない Issue は確かめずに進める（重なっても起きるのはマージ時の衝突だけで、非公開側が合わせる）。確かめるときは、非公開アドバイザリを扱うセッション（または本人）に、Issue の番号だけを送って、非公開の修正と重ならないかを確かめる。アドバイザリの中身は受け取らない。「重なる」と言われた番号は、理由を聞かずに飛ばす。返事が来るまで push しない。確かめる相手がいないときは、本人に確かめるか止まる。照合済みの番号は、照合したときから増えた分だけ確かめればよい。
2. **準備**: 「作業場所」の手順でワークツリーを用意し（既にあれば使う）、Issue の初期設定をそろえる。続けて、このあとの範囲の照合のために、開始時の HEAD を `git -C <パス> rev-parse HEAD` で控える。
   - アサイニー: `gh issue edit <番号> --add-assignee @me`
   - Issue タイプ: `gh issue edit <番号> --type <Bug・Feature・Task>`
   - ラベル: 不具合なら `fix`、必要なら `security`
   - 必要なら explorer に下調べを任せる。explorer の結果は手がかりとして使い、原因の根拠は worker と reviewer が自分で裏を取る。
3. **実装**: worker に、Issue（またはマイルストーン）、作業ディレクトリ、書き込んでよいファイル、確認のしかたを渡す。worker はコミットまで行う。
4. **範囲の照合**: メインが `git -C <パス> diff --name-only <開始時の HEAD>..HEAD` で、この 1 件の変更が割り当てたファイルに収まっていることを確かめる（`/run-plan` では、前のマイルストーンの変更を含めないため）。PHP を変えた場合は、変えたファイルに `php -l` が通ることを確かめる。
5. **検証**: tester が、Issue に書かれた症状が直ったこと（`/run-plan` では、そのマイルストーンの Concrete Steps の期待どおりであること）を確かめる。画面や DB が絡む場合は adaptive-validation-runner を使う。
   - 検証の前に、変えた関数がどの入口（画面・テンプレート・TV・スニペットなど）から呼ばれるかを確かめ（必要なら explorer に任せる）、主な入口ごとに確かめる。Issue の書き方（「TV で」など）と実際の呼び出し経路が違うことがある。
   - 修正前と修正後を比べる。修正前は main の detached ワークツリーと別の検証環境で確かめる。
   - 既存の処理を別の場所へ写したり、いくつかを 1 つにまとめたりした変更では、その処理に入力を渡すそれぞれの入口の実際の出力で、修正前後を比べる（例: 復元の処理なら、mysqldump で作ったファイルと PHP のダンパーで作ったファイルの両方）。「今の動きと同じ」だけで済ませると、写した元にあった不具合を見落とす。
   - 画面を計測して見た目を確かめる変更では、画面の数ではなく、ブロックの組み合わせ（同じ入れ物が続く、ページ送りが出る、2 枚目以降のタブ、権限で部品が消える など）を網羅するように、画面と状態を選ぶ。
   - tester がテストを足したとき（tester はコミットしない）は、メインがそのファイルを範囲の照合（手順 4 と同じ）にかけてからコミットする。
6. **自己レビュー**: メインが、未コミットの変更が残っていない（`git -C <パス> status --porcelain` が空）ことを確かめてから、レビュー前の `git -C <パス> status --porcelain` と `git -C <パス> rev-parse HEAD` を控える。残っていたら、手順 5 のとおりコミットするか、出どころを確かめる。reviewer には main との差分全体、Issue（または ExecPlan）、検証結果を渡す。reviewer は指摘を重大度（高・中・低）順に返し、無ければ「問題なし」と明記する。レビュー後に同じ 2 つを取り、変わっていないことを確かめる。変わっていたら止まる。
7. **修正**: 高・中の指摘があれば、worker が直し、tester が確かめ直し、reviewer が見直す。往復は 2 回まで。残れば worker を 1 回だけ格上げしてやり直し、それでも残れば止まる。低の指摘は直すか、PR 本文の「備考」に残す。
8. **記録**: ExecPlan があれば、メインが Progress（このマイルストーンを完了に）と Surprises & Discoveries を更新してコミットする。Issue へのコメントは、PR で足りるので行わない。
9. **push と PR**: メインがブランチを push し、PR を作る。
   - タイトル・本文・ラベルは `.github/codex-pr-rules.md` に従う。「確認手順」に検証の結果、「備考」に自己レビューの結果（指摘と対応、または問題なし）を書く。
   - `/run-issue` では `Closes #<番号>` を入れ、下書きにしない（自己レビューを通したものだけを出すため）。
   - `/run-plan` では、PR がまだ無ければ最初のマイルストーンで下書きの PR を作り、対象の Issue すべてを `Refs #<番号>` で挙げる。作ったブランチと PR は、次の push の前に ExecPlan の Artifacts and Notes に記録する。2 つめ以降は同じ PR に push し、本文を更新する。最後のマイルストーンで、対象の Issue すべてを `Closes #<番号>` に改める。
   - push のあと `gh pr checks <PR番号> --watch` で CI を待つ。PR を作った直後で「no checks reported」と出たときは失敗とみなさず、少しおいてやり直す。やり直しても変わらないときは、`gh pr view <PR番号> --json mergeable` が `CONFLICTING` でないかを確かめる（main と衝突していると、pull_request のワークフローが動かない。衝突は止まる条件）。

PR に付くほかのレビュー（Codex など）への対応は、この繰り返しに含めない。次の `/run-issue` に入る前に、runner が前の PR について review-agent の `/resolve-review <PR番号>` を行う（このときは runner の実行中として、下の「確認を省く範囲」に従う）。仕分けは reviewer、修正は worker、push と GitHub への書き込みはメインが行う。review-agent が「ユーザー判断を仰ぐ」に分類した指摘があれば、止まって人に返す。人が runner とは別に `/resolve-review` を頼んだときは、review-agent の通常の確認に従う。

Codex のレビューは、PR を開いたときと「@codex review」のコメントで動き、push しただけでは見直されない。指摘に対応したあと見直しを頼むのは、必要と判断できるとき（対応で手順や判定のしかたを変えた、影響の広い変更をした など）だけにする。文言の手直しや小さな修正では頼まない。完了したかは、Codex のサマリーコメントに埋め込まれた `codex-review-state` の JSON で、`code_review.commit_sha` が今の head と同じで `status` が `completed` かを見る（サマリーの本文に出る文字では判定しない）。

## 止まる条件

次のどれかに当たったら、先へ進まずに止まる。

- 「要相談」に当たる判断が出た（DB スキーマの変更、互換性を損なう変更、Issue で決まっていない挙動の選択、Issue の「直し方の案」と違う直し方が要る など）
- Issue が閉じている。自分以外の担当者、紐づくブランチ、PR のどれかが既にある（`/run-plan` で ExecPlan に記録されたブランチと PR は除く）
- 対象のロードマップ項目が一意に決まらない（`/finish-task`）
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

この runner の実行中は、ほかのスキルやエージェント定義にある次の確認を省いて先へ進む。runner を呼んだこと自体（roadmap-manager の `/next-task --run` から渡された場合を含む）を、その Issue または ExecPlan についての実装・ブランチ作成・コミット・push・PR 作成の明示指示とみなす。`--run` の無い `/next-task` は、明示指示にならない。

各スキルにある「`exec-plan-runner` から呼ばれたとき」と「`exec-plan-runner` の実行中」は同じ意味で、同じセッションで `/run-issue`・`/run-plan`・`/finish-task`（`/next-task --run` から渡されたものを含む）、または兄弟スキル `advisory-runner` の `/run-advisory` を実行している間に限る。人が runner とは別に頼んだ作業（単独の `/resolve-review`・`/handoff`・`/create-plan` など）は、各スキルの確認に従う。「スキップ禁止」などと書かれた確認も、下の表にあれば省く。

| 場所 | 省く確認 | runner での扱い |
|---|---|---|
| `.claude/skills/project-worker/SKILL.md` の `/handoff` | 「コミットして push しますか？」 | worker がコミットし、メインが push する |
| `.claude/skills/project-worker/SKILL.md` の `/work` 手順 2 | 初回着手時の `skill:init` | 行わない。スキル成長ループは `/finish-task` で `.agent/PLANS.md` に従って行う |
| `.claude/skills/issue-resolver/SKILL.md` の規則 4・14 と `implement-fix` の手順 1 | 調査報告へのエンジニア承認 | 承認を待たずに実装へ進む。調査の結果は PR 本文に書く。直し方が Issue の案と違えば止まる |
| `.claude/skills/exec-plan/SKILL.md` の `/create-plan` 手順 7 と「明示指示が必要」の規則 | 実装・ブランチ作成・コミット・PR 作成の前の明示指示 | runner の呼び出しを明示指示とみなす。ExecPlan を新しく作る必要が出たら止まる |
| `.claude/skills/review-agent/SKILL.md` の規則 11・12 と `/resolve-review` のステップ 2・5・6 | 対応方針、push、GitHub への返信と resolved 化の確認 | 確認せずに進める。push と GitHub への書き込みはメインが行う |
| `.agent/agents/reviewer.md` の「書き込み権限」 | GitHub への返信と resolved 化の前の確認 | reviewer は書かない。メインが確認せずに行う |
| `AGENTS.md` の「レビュー時」手順 1 の resolved 化 | push 後の resolved 化のユーザー確認 | 確認せずに進める（review-agent の行と同じ） |
| `AGENTS.md` の「レビュー時」手順 2・3 と `.claude/skills/doc-maintainer/SKILL.md` の `/pr-sync` 手順 1・5 | 追加コミット後の `/pr-sync` 実行と、乖離の一覧への確認 | メインが確認せずに PR 本文を更新する |
| `AGENTS.md` の「コミット前（ドキュメント変更時）」と `.claude/skills/doc-maintainer/SKILL.md` の `/doc-audit` 手順 4・`/doc-fix` 手順 4 | ドキュメントの問題の修正方針とコミットの確認 | worker が確認せずに直してコミットする。直せない問題は、自己レビューの指摘として扱う |
| `AGENTS.md` の「新タスク着手時」手順 4、`.claude/skills/exec-plan/SKILL.md` の「ロードマップ連携」4 と `/update-plan` 手順 7、`.claude/skills/project-worker/SKILL.md` の `/sync-status` | 着手時に `Status` を `WIP` にする規則 | runner の実行中は `Status` を変えない（不変条件 5）。`/finish-task` でまとめて同期する |

省かないもの:

- `.agent/PLANS.md` の完了処理プロトコルの手順 1（完了処理の前のユーザー確認）と、手順 8-5（学びをスキルへ反映するかの確認）
- 不変条件
- 止まる条件

## コマンド

ここに挙げたコマンドは、このスキルの中の手順の名前で、Claude Code の入力欄からは直接呼べない（`Unknown command` になる）。入力欄では `/exec-plan-runner finish-task 667` のようにスキル名に続けて書くか、「#667 の完了処理をして」と言葉で頼む。ほかのスキルの手順（roadmap-manager の `/next-task` など）も同じ。人に案内するときも、この呼び方で書く。

### /run-issue <番号>

ExecPlan の無い Issue を 1 件直す。

1. 「1 件ごとの繰り返し」を手順 1 から 9 まで行う。ExecPlan が無いので、手順 8 では ExecPlan の代わりに次を行う: 対象のロードマップ項目の `ExecPlan:` 行が `着手時に判断` のままで、その項目の Issue の PR がまだどれも無ければ、`不要` に書き換えてコミットする（`Status` は変えない。ほかの PR と衝突させないため、最初の PR でだけ行う）。
2. CI が通ったら、PR の URL、自己レビューの結果、検証の結果を短く報告して終える。マージはしない。

### /run-plan <ExecPlan のパス>

ExecPlan をマイルストーンごとに進める。

1. ExecPlan を読み、Progress の最初の未完了のマイルストーンを特定する。対象の Issue は、ExecPlan の Artifacts and Notes に挙がった Issue（無ければ、その ExecPlan を指すロードマップ項目の `Issue:`）のすべてとする。事前確認は、対象の Issue すべてに対して行う。
2. ExecPlan の Artifacts and Notes にブランチ（と PR）が記録されていれば、それとそのワークツリーを使う（ワークツリーが無ければ、そのブランチで `git worktree add` する）。roadmap-manager の `/next-task --run` で作った ExecPlan は、この形でブランチが記録されている。記録が無ければ、「作業場所」の手順で新しく用意する。`gh issue develop` で紐づけるのは、対象の Issue のうち先頭に書かれたもの 1 件にする（ロードマップの `Issue:` は優先順に並んでいる）。
3. マイルストーンごとに「1 件ごとの繰り返し」を行う。worker に渡す書き込み範囲は、そのマイルストーンの Plan of Work と Concrete Steps に挙がったファイルにする（ExecPlan の記録はメインが行う）。
4. マイルストーンが終わるたびに同じ PR へ push し、次のマイルストーンへ進む。
5. すべてのマイルストーンが終わり CI が通ったら、`gh pr ready <PR番号>` で下書きを解除し、報告して終える。マージはしない。完了処理は `/finish-task` で行う。

### /finish-task <Issue 番号 | ExecPlan のパス>

PR がマージされたあとの片付けと完了処理を行う。完了処理の中身は `.agent/PLANS.md` の「完了処理プロトコル」を正本とする。

1. 対象のロードマップ項目を特定する。ExecPlan のパスなら、その ExecPlan を `ExecPlan:` に持つ項目。Issue 番号なら、その番号を `Issue:` に含む項目。一意に決まらなければ、推測で進めずに止まる。
2. その項目の Issue のうち、PR が MERGED のもの（`gh pr view <PR番号> --json state`）について、ワークツリーを片付ける（runner を使わずに進めたなどで、ワークツリーが無ければ飛ばす）。
   `git worktree remove "$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)")/.claude/worktrees/issue-<番号>"`
3. 項目の `Issue:` に挙がった Issue が、すべて閉じたことを確かめる。まだ開いているものがあれば、ここで終える（項目は未完了のまま）。ExecPlan がある場合は、Progress と Validation and Acceptance が完了の状態かも確かめる。
4. 実装と検証の完了を告げ、完了処理へ進む前にユーザー確認を取る（省かない）。
5. 確認が取れたら、メインが完了同期用のブランチとワークツリーを main から作る。ブランチ名には項目の短い名前を入れ、同じ日の同期と重ならないようにする。
   ```bash
   git fetch origin
   ROOT=$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)") && git worktree add -b docs/roadmap-sync-<YYYY-MM-DD>-<短い名前> "$ROOT/.claude/worktrees/roadmap-sync-<短い名前>" origin/main
   ```
6. そのワークツリーで、ロードマップの更新を roadmap-manager の `/roadmap-sync` で、ExecPlan のアーカイブを `.agent/PLANS.md` のとおりに行う。スキル成長ループ（`.agent/PLANS.md` の手順 8）は省かない。次の順に行う。
   - 同期用のワークツリーをマウントした検証環境を、ほかと重ならない `EVO_VALIDATE_NAME`・`EVO_BIND_IP` で立てる（`EVO_VALIDATE_NAME=<名前> EVO_BIND_IP=<アドレス> docker compose --project-directory <同期用のワークツリー> -f <同期用のワークツリー>/compose.yml -f <同期用のワークツリー>/.claude/skills/adaptive-validation-runner/scripts/compose.validation.yml up -d --build`）。シェルの状態はコマンドをまたいで残らないので、`setup-site.js` と片付け（`down -v --rmi local`）のコマンドにも、同じ値を毎回先頭に付ける。起動の前に、`.claude/skills/adaptive-validation-runner/references/environment.md` の「並走するとき」の確認を行う。
   - その環境に `setup-site.js` でインストールする。インストールしていないと、`php evo` は何も出さずに終わる。`setup-site.js` は今いるディレクトリに `sample.htaccess` を書き戻すので、必ず同期用のワークツリーをカレントにして動かす。
   - アプリのコンテナで `php evo skill:init` → `skill:status` → `skill:complete --strict` を行う（`docker exec -w /var/www/html <名前>-app php evo …`）。`learning.json`・`proposal.json`・`trace.jsonl` は、`skill:complete` の前にメインが書く（書式は `.agent/runs/README.md` とテンプレート）。
   - 前例どおり、次の run の雛形（`skill:complete` が作る）と `.agent/skill-metadata/<スキル名>/` も残す（`skill:sync` で更新する）。
   - 手順 8-5（反映するかの確認）は省かない。反映すると決まったものは、完了同期とは別の PR で入れる。
   - 終わったら検証環境を片付け、追跡外の `manager/includes/config.inc.php` を消す。
7. コミットしてブランチを push し、PR を作る（不変条件 1 の例外。マージは人が行う）。PR がマージされたら、同期用のワークツリーを `git worktree remove` で片付ける。
8. 完了処理まで終えたことを告げ、次のタスクの候補を 1 件挙げて終える。
