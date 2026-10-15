# ExecPlan: ExecPlan と Issue を、役割ごとのサブエージェントで自分で進められるようにする

## Purpose / Big Picture

ロードマップの項目や Issue を、AI が人の確認を待たずに「実装 → 検証 → 自己レビュー → push → PR」まで進められるようにする。実装と検証は速く安いモデル、レビューと計画は深く読めるモデルのサブエージェントに任せ、push する前に必ず自己レビューを通す。人が見るのはマージの判断と、途中で出た「要相談」の判断だけにする。

最初の使い道は、ロードマップ「0. 全体点検で見つかった不具合の修正」の Issue（#634〜#665）を順に片付けることである。

## Progress

- [x] (2026-10-15) M1: Claude Code 用のエージェント定義（`.claude/agents/`）と、push を機械的に止める許可設定を作り、モデル・effort・書き込み範囲・許可確認の出方を確かめる（`gh issue develop` と実際の push・PR 作成での許可確認は M5 の最初の 1 件で確かめる）
- [x] (2026-10-15) M2: ExecPlan と Issue を実行するスキル `exec-plan-runner`（`/run-issue`・`/run-plan`・`/finish-task`）を作る。不変条件・止まる条件・確認を省く範囲の一覧をここに置く
- [x] (2026-10-16) M3: `roadmap-next-task` を廃止し、`/next-task` を `roadmap-manager` へまとめる。完了同期は `/roadmap-sync` に一本化する。参照を更新する
- [x] (2026-10-16) M4: 各スキルの確認を「`exec-plan-runner` から呼ばれたときは runner の一覧に従う」に改める。ランタイム分担の文書を「Claude Code が主、Codex は補佐」に改める
- [ ] (2026-10-15) M5: 0 節の Issue 3 件で試運転し、実装役のモデル（Sonnet 5.5 の `medium` と Opus 5.5 の `low`）を比べて決める。手順と止まる条件を直す

## Surprises & Discoveries

- 2026-10-15: 自己レビュー（Opus のレビュー役、読み取り専用）で、確認を省く対象の漏れ、push とマージを文章だけで禁じている点、レビュー役の書き込み禁止の実効性、ワークツリーと並行実行の具体性不足、役割表と `.agent/agents/` の食い違いなどが見つかった。この版はそれを反映している。
- 2026-10-15: 本人の個人設定（`~/.claude/settings.json`）は `model: opus[1m]`、`effortLevel: high`。`CLAUDE_CODE_EFFORT_LEVEL`・`CLAUDE_CODE_SUBAGENT_MODEL`・`CLAUDE_CODE_SUBAGENT_MODEL_FORCE` は未設定。サブエージェントの frontmatter の `model`・`effort` はセッションの設定より優先されるので、そのまま効く見込み（公式ドキュメントによる。実際の効き方は M1 で確かめる）。
- 2026-10-15 (M1): `/agents` の代わりに、ワークツリーで `claude -p --output-format stream-json --verbose` の入れ子セッションを起動して確かめた。evo- で始まる 5 つが subagent_type として認識された。`claude agents --json` はセッションの一覧で、定義の一覧ではない。
- 2026-10-15 (M1): 使われたモデルと effort は、入れ子セッションの記録 `~/.claude/projects/<プロジェクト>/<セッションID>/subagents/agent-*.jsonl` の `"model"`・`"effort"` と、同じ場所の `agent-*.meta.json` の `agentType` で確かめられる。結果: evo-reviewer は `claude-opus-5-5`／`high`、evo-worker は `claude-sonnet-5-5`／`medium`、evo-explorer は `claude-haiku-5-5`／`medium` で、表のとおり。`model: opus` は 1M 版ではなく `claude-opus-5-5` になる（メインは `opus[1m]`）。差分のレビューなら困らない。
- 2026-10-15 (M1): evo-reviewer にファイルの作成を頼むと断り、`git status --porcelain` も前後で変わらなかった。ただし使えるツールは Read・Bash などで、Bash のリダイレクトによる書き込みは仕組みとしては止まっていない（指示に従った結果）。そのため、手順 6 の前後照合（`status` と `HEAD`）は省かない。
- 2026-10-15 (M1): `.claude/settings.json` の deny で、`git push --dry-run` の 6 形（`origin HEAD:main`、`origin main`、`HEAD:refs/heads/main`、`--force`、`-f`、`+HEAD:…`）がすべて拒否され、作業ブランチへの通常の push は通った（入れ子セッションの `permission_denials` で確認）。
- 2026-10-15 (M1): ワークツリーの中で `git worktree add .claude/worktrees/<名前>` を相対パスで実行すると、そのワークツリーの中に入れ子で作られる。作業場所の手順は、本体のルートを起点にした絶対パスに改めた。また `.claude/worktrees/` はこのクローンの `.git/info/exclude` でしか除外されていなかったので、`.gitignore` に足した。
- 2026-10-15 (M1): 自動モードのセッションでは、`git fetch`・`git worktree add/remove`・`gh pr create --dry-run`・`docker compose exec` は許可確認なしで実行された（検証環境は止まっていたので、docker は実行が拒否されないことまでしか確かめていない）。`gh issue develop` と実際の push・PR 作成は、公開リポジトリに跡が残るので試しでは行わず、M5 の最初の 1 件で確かめる。なお、ワークツリーで分離したバックグラウンドのセッションでは、git のワークツリー操作を入れ子セッションに頼むと分離の仕組みに断られた。runner のワークツリー操作はメインのセッションが普通のコマンドとして行う。
- 2026-10-15 (M2): M2 の差分を、入れ子のセッションから evo-reviewer に自己レビューさせた（reviewer の実地の試験も兼ねた）。中 7 件・低 6 件の指摘があり、すべて根拠を確かめて直した。レビューの前後で `git status --porcelain` と HEAD は変わらなかった。主な指摘: `/finish-task` の変更を置くブランチが無い、`/run-plan` の 2 つめ以降で範囲の照合が前のマイルストーンの変更まで拾う、`/run-plan` の PR が `Refs` のままで Issue が閉じない、`/finish-task` が「`Issue:` がすべて閉じた」を確かめていない、WIP にする規則との優先関係が無い、`orchestrator.md` の判断基準 8 と合わない、deny の `git -C` の形に抜けがある、`gh issue edit --type` で Issue タイプを設定できる（gh 2.97.0）。
- 2026-10-15 (M2): deny に `git -C <パス> push …` の形（main の後ろに続きがある形、`refs/heads/main`、`-f`、`+`）を足した。手動確認モード（default）の入れ子セッションで、dry-run の push を `--settings` で allow に入れても、main 宛てと強制 push は拒否され、通常の push だけが通った。auto モードの判定ではなく、deny のルールが allow より優先して効いていることを確かめた（auto モードのままでは区別できない）。
- 2026-10-15 (M2): 2 回目の自己レビューで、`git push -f origin …` のように `-f` が push の直後に来る形が deny をすり抜けると指摘された。試すと実際に通っていた（`* -f *` は `push` と `-f` の間に 1 語以上が要るため）。それまでの試験は `git push --dry-run -f …` の形で、`--dry-run` が `*` に当たって抜けを隠していた。`git push -f*` と `git -C * push -f*` を足し、`--dry-run` を後ろに置いた形で、4 形の強制 push が拒否され、通常の push だけが通ることを確かめた。`-uf` のようなまとめ書きは止まらない（README に限界として記録）。
- 2026-10-16 (M3): ヘッドレス Playwright の自走確認を、入れ子のセッションの evo-tester に任せて行った。本体のルートを起点に main の detached ワークツリーを作り、`EVO_VALIDATE_NAME=evo-validate-probe684`・`EVO_BIND_IP=127.0.0.2` で検証環境を起動した。続けて Playwright を作業用ディレクトリに入れ、`setup-site.js --sample --editor` でインストールした。`smoke.js` は 6/6 PASS、`/manager/` は 200、表側の title も出た。そのあと `down -v --rmi local` と `config.inc.php` の削除で片付けた。許可確認や拒否は 0 件で、所要は約 1 分半（イメージとブラウザはキャッシュ済み）。別の作業ツリーの既定名 `evo-validate` の環境（停止中）には触れなかった。片付けのあと、コンテナ・ボリューム・イメージが残っていないこと、ワークツリーを `git worktree remove` で消せたことを、メインで確かめた。tester 役が、人の手を借りずに検証環境の起動から片付けまでを完走できることを確かめられた。
- 2026-10-16 (M3): ワークツリーで分離したセッションでは、`ROOT=$(…) && git worktree add "$ROOT/…"` の 1 行は、分離の仕組みに断られた。`git rev-parse` で先にパスを得て、絶対パスを直接書いた `git worktree add` は通った。分離していない普段のメインのセッションでは、この制約は無い。
- 2026-10-16 (M3): M3 の差分を evo-reviewer で自己レビューし、中 3 件・低 4 件を直した。中は次の 3 件。(1) 「次のタスク」と言うだけで push まで進んでしまう。(2) Status を WIP にしないので、進行中の項目を選び直してしまう。(3) `/next-task` が書き換えたファイルの置き場が無い。続けて、`--run` を付けない `/next-task` を入れ子のセッションで実際に動かした。進行中の #684 の項目を外し、次の項目を選んで示し、ファイルを書き換えずに止まった。このとき、`Issue:` 欄が番号順ではなく優先順に並んでいて、モデルがその順に従うことが分かった。スキルの「番号順」はこれと食い違っていたので、直した。
- 2026-10-16 (M4): Codex CLI（0.156.1）を読み取り専用のサンドボックス（`codex exec -s read-only --ephemeral`）で動かし、`.codex/skills/exec-plan-runner/` の入口が読まれるか確かめた。Codex は `exec-plan-runner` と `roadmap-manager` をスキルとして認識した。入口から正本を辿り、`/run-issue` の手順 9 つと、止まったときに返す 4 項目を正しく答えた。ファイルの変更は無かった。Codex で runner を実際に 1 周させる確認は、この計画の到達条件に含めない（#680 と合わせた将来の課題）。
- 2026-10-16 (M4): M4 の差分を evo-reviewer で自己レビューし、中 2 件・低 7 件を直した。中の 1 件目は「runner から呼ばれたとき」の範囲があいまいで、人が単独で頼んだ `/resolve-review` まで確認が省かれる読み方ができたこと。runner に範囲の定義を置き、人が単独で頼んだ作業は各スキルの確認に従う、と明記した。中の 2 件目は、ドキュメントを変える Issue での `/doc-audit`・`/doc-fix` の確認が「確認を省く範囲」の表に無かったこと。表に行を足した。低では、Codex で格上げをしないこと、Codex には deny が効かないことの注記、例外の理由を runner の 1 か所に寄せることなどを直した。
- 2026-10-15 (M2): 2 回目の自己レビューの中 4 件・低 5 件はすべて直した。3 回目のレビューは行っていない（runner の往復上限の 2 回に合わせた）。

## Decision Log

- 2026-10-15 (yamamoto/Claude): 役割とモデルの対応は、公式情報に基づいて次のとおりにする。
  - 実装役・検証役は Sonnet 5.5。公式の説明は「速さと知能の最良の組み合わせ」で、速度は Fast、価格は Opus 5.5 の半分（入力 $2 / 出力 $10 per MTok）。移行ガイドでは「実際のリポジトリでの複数手順のエージェント型コーディングが最も得意」。
  - レビュー役・計画役は Opus 5.5。公式の説明は「長時間のエージェント型コーディングと知的作業向け」。移行ガイドでは、最も大きな改善の一つがコードレビューで「より多くのバグを、より少ない誤検知で見つける」。
  - 調査役は Haiku 5.5。公式の説明は「大量・遅延に敏感な作業（分類・抽出・振り分け）向け」で、移行ガイドでは「サブエージェントとしての動作が大きく改善」。Claude Code の公式ドキュメントも、調査を `model: haiku` で安く回す例を示している。
  - Fable 5.1 は使わない（遅く高価。Opus 5.5 で足りないと測れたときだけ検討する）。
  - 代替案: 全役割を Opus 5.5 にして effort だけ変える。公式のコスト指針は、複数モデルを組み合わせる前に「最も能力の高いモデルを低い effort で使う」形を測るよう勧めており、Opus 5.5 は `low` でもコーディング評価で `medium` に近い結果を大幅に安く出すとされる。1 モデルならキャッシュも共有できる。そのため実装役は M5 で「Sonnet 5.5 の `medium`」と「Opus 5.5 の `low`」を比べて決める。
  - 出典: https://platform.claude.com/docs/en/about-claude/models/overview 、Claude API スキル同梱の移行ガイド（Migrating to Claude Opus 5.5 / Sonnet 5.5 / Haiku 5.5、2026-10-06 時点）、https://code.claude.com/docs/en/sub-agents
- 2026-10-15 (yamamoto/Claude): effort は役割ごとに必ず明示する。モデルごとに既定値が違い（Opus 5.5 と Haiku 5.5 は `medium`、Sonnet 5.5 は `high` で段階が見直されている）、省くと意図しない強さで動くため。初期値は Plan of Work の表のとおり。公式の指針: 知能が効く作業は最低 `high`、エージェント型のコーディングは `medium` から、`xhigh`・`max` は効果を測ってからに限る、思考を減らしたいときは指示より effort を下げる。
- 2026-10-15 (yamamoto/Claude): 実装役の指示文に「動かして確かめてから完了と報告する」段落を入れる。公式の移行ガイドに、Sonnet 5.5（主に `low`）と Haiku 5.5（`low`・`medium`）は、動かして確かめずに完了と報告することがあると書かれているため。
- 2026-10-15 (yamamoto/Claude): 品質の主な関門は push 前の自己レビュー（Opus のレビュー役）とする。PR に付く Codex のレビューは補助として扱い、自走の 1 周には含めず、後から `/resolve-review` で扱う。
- 2026-10-15 (yamamoto/Claude): Claude Code を主なランタイム、Codex を補佐とし、Claude Code での動作を優先する。Codex でも同じように動くのが望ましい（本人）。そのため `exec-plan-runner` の本文は抽象的な役割（worker・reviewer など）と、手順・不変条件・止まる条件だけで書き、Claude Code での割り当ては `.claude/agents/` を正本にする（ランタイム分担の文書の原則 3「共有本文にランタイム固有の癖を書かない」にも合う）。Codex では、役割を分けずに 1 つのセッションで同じ手順を順に行えば、同じ不変条件と止まる条件のもとで動く。Codex で役割を別のエージェントに分けられるか（モデルや推論の強さを役割ごとに変えられるか）は未確認で、#680（Codex 用スキルの整理）と合わせた将来の課題とし、この計画の到達条件には含めない。代替案は「runner を Claude Code 専用と宣言して Codex の入口を作らない」。Codex でも動かしたいという本人の希望と、原則 3 に合わないので採らない。
- 2026-10-15 (yamamoto/Claude): モデル名は `.claude/agents/*.md` の frontmatter にだけ書く。`.agent/agents/` と `assets/docs/ai-runtime-governance.md` は抽象的な段階（通常・高推論・低コスト）のまま残す。モデルは `sonnet`・`opus`・`haiku` の別名で書き、版番号を固定しない。
- 2026-10-15 (yamamoto/Claude): 役割の責務の正本は `.agent/agents/*.md` のまま。`.claude/agents/*.md` の本文は「対応する `.agent/agents/<役割>.md` と `AGENTS.md` を読んで従う」という薄い入口にし、内容を写さない（#680 と同じ問題を増やさない）。
- 2026-10-15 (yamamoto/Claude): push・PR の作成・GitHub への書き込み（コメント、返信、resolved 化）は、メインのセッションだけが行う。worker はコミットまで、reviewer はファイルにも GitHub にも書かない。代替案は、`.agent/agents/reviewer.md` のとおり reviewer に GitHub への書き込みを任せる形。書き込み禁止を確かめにくくなるので採らない（`reviewer.md` は M4 で runner 実行時の扱いを追記する）。
- 2026-10-15 (yamamoto/Claude): 許可設定で機械的に止めるのは、main への push と強制 push（`--force`・`-f`・`+` の refspec）だけにする。`.claude/settings.json` はリポジトリ全体に効き、runner の実行中だけに絞れないため。`gh release` はリリース手順（`release` スキル、`assets/docs/release-process.md`）が `view`・`edit`・`download` に使い、タグの push はリリースのタグ作成に使い、`gh pr merge` は本人が Claude 経由でマージするときに使う。これらは runner の不変条件（文章）と、PR をマージする人の判断で守る。代替案は (1) ExecPlan の当初案どおりすべて止める、(2) `gh pr merge` も止める、(3) runner の実行中だけ働く PreToolUse フックで止める。(1)(2) は普段の作業を妨げるので採らない。(3) は試運転で必要と分かったら検討する（本人の選択）。
- 2026-10-15 (Claude, M2): `exec-plan-runner` を書く際に、Plan of Work から次の点を変えた・補った。(1) 事前確認で止まる対象を「自分以外の担当者」にした。本人が先にアサインしてある Issue まで止めないため。(2) アサイニーは個人名を書かず `@me` にした。(3) push のあとに `gh pr checks --watch` で CI を待つ手順を足した（止まる条件「CI が失敗した」を判定するため）。(4) `/run-issue` の PR は下書きにしない（自己レビューを通したものだけを出す）。`/run-plan` は最初のマイルストーンで下書きの PR を作り、全マイルストーンが終わったら `gh pr ready` で解除する。(5) 格上げのモデル名は共有本文に書かず、`.claude/agents/README.md` に置いた。(6) 止まる条件に「ExecPlan を作るべき規模だと分かった」「reviewer の前後で状態が変わった」「ExecPlan に本人の確認を得てから行うと書かれた手順に来た」を足した。(7) 確認を省く一覧に、`issue-resolver` の規則 4 と `review-agent` の `/resolve-review` ステップ 6 を足した（Context の列挙から漏れていた）。(8) 自己レビューを受けて、次も決めた。`/run-plan` の PR は最初 `Refs`、最後のマイルストーンで `Closes` に改める。範囲の照合は「開始時の HEAD..HEAD」で行う。`/finish-task` の完了同期は、main から作る `docs/roadmap-sync-<日付>` のブランチと PR で出す（不変条件 1 の例外）。`/finish-task` は、ロードマップ項目の `Issue:` がすべて閉じてから進める。runner の実行中は WIP にする規則（`AGENTS.md`「新タスク着手時」手順 4、`exec-plan` の「ロードマップ連携」4 と `/update-plan` 手順 7）に従わない。直列でも役割を分けることを、`orchestrator.md` の判断基準 1・8 に対する例外として runner に書いた。(9) 2 回目の自己レビューを受けて、次も決めた。`/run-plan` の対象は、ExecPlan またはロードマップ項目に挙がった Issue のすべてとし、`gh issue develop` で紐づけるのは番号のいちばん小さい 1 件、`Closes` は全件に付ける（ロードマップの項目は Issue を複数持つため）。`/finish-task` は引数（Issue 番号か ExecPlan のパス）から項目を特定し、一意でなければ止まる。ExecPlan の記録は push の前にメインがコミットする。スキル成長ループは、検証環境のコンテナが本体のチェックアウトをマウントしているので本体で実行し、成果物を同期用のワークツリーへコピーする。
- 2026-10-16 (Claude, M3): `roadmap-manager` の `/next-task` は、ExecPlan を新しく作ったら作成を告げて止まり、本人が計画を確かめてから `/run-plan` で進める形にした。既存の ExecPlan があるか `ExecPlan: 不要` のときだけ、そのまま `exec-plan-runner` に渡す。計画は設計の判断を含み、`exec-plan` の `/create-plan` 手順 7 も作成後に止まるとしているため。代替案は、作った計画をそのまま `/run-plan` に渡す形。自走の範囲は広がるが、計画の誤りを人が見る機会がなくなるので採らない。Codex 側の `roadmap-manager`（`SKILL.md` と `agents/openai.yaml`）も同じ文面にそろえた（写しを薄くするのは #680）。
- 2026-10-16 (Claude, M3): 自己レビューを受けて、`/next-task` は選んで示したところで止め、`/next-task --run` と明示されたときだけ runner に渡すことにした。runner に書いた「呼び出しを明示指示とみなす」の範囲を、あいまいな言い方の依頼にまで広げないため。代替案は、`/next-task` を呼んだことそのものを明示指示とみなす形で、自走の範囲が「次のタスク」という一言にまで広がるので採らない。`ExecPlan: 不要` への書き換えは runner の最初の PR に含める。ExecPlan を作るときは、代表の Issue のブランチにコミットして、push せずに止まる。
- 2026-10-16 (yamamoto/Claude, M3): ロードマップの `Issue:` 欄は優先順に並べ、着手もその順にする、と書式に足した。実際の項目がそう並んでいて、`/next-task` の実地の確認でもモデルがその順に従ったため。M2 の (9) で決めた「`gh issue develop` で紐づけるのは番号のいちばん小さい 1 件」も「先頭に書かれた 1 件」に改めた。
- 2026-10-16 (Claude, M3): この項目のロードマップの到達条件「main への push とマージが、許可設定で機械的に止まる」を、M1 の決定（止めるのは main への push と強制 push だけ）に合わせて直した。
- 2026-10-16 (Claude, M4): 各スキルに足す 1 行は、どのファイルも同じ文面にした（「`exec-plan-runner` から呼ばれたときは、このスキルの確認と `Status` の扱いより、runner の『確認を省く範囲』を優先する。完了処理の前のユーザー確認は省かない」）。置き場所は各スキルの導入文の直後で、確認の箇所より先に読まれる位置にした。Codex の写し（SKILL.md と `agents/openai.yaml`）にも同じ 1 行を入れた。reviewer.md・orchestrator.md・AGENTS.md は、文脈に合わせて書き分けた。`ai-runtime-governance.md` は、Codex が主という前提が全体にあったので、全体を書き直した。以前の文書にあった「主運用は Codex なのに、共有本文の正本は `.claude/skills/` にある」というねじれも、正本が主に使うランタイムの側にあることになって解消した。
- 2026-10-15 (yamamoto/Claude): 最初は直列（1 件ずつ）で動かす。並行は M5 のあとに、検証環境の分離（`EVO_VALIDATE_NAME`・`EVO_BIND_IP`）を確かめてから決める。
- 2026-10-15 (yamamoto/Claude): ロードマップの Status 更新は Issue ごとの PR では行わず、`/finish-task` でまとめて行う（PR 同士が `.agent/roadmap.md` で衝突しないようにする）。
- 2026-10-15 (yamamoto/Claude): スキルは「作る」`exec-plan` と「回す」`exec-plan-runner` の対にする。`roadmap-next-task` は廃止し、選定（`/next-task`）は `roadmap-manager` にまとめる。重複していた `/roadmap-next` は消し、`/sync-roadmap` は既存の `/roadmap-sync` に一本化する。`/next-task` という名前は使い慣れているので残す。2026-05-07 の SSOT 整備（`.agent/plans/archive/2026-05-07-ai-doc-ssot-refactor.md`）では両スキルを残して重複を整理したが、実行の受け持ちを `exec-plan-runner` へ移すと `roadmap-next-task` に残る仕事が選定だけになるため、統合する。代替案は `roadmap-next-task` を名前だけ変えて残す形。
- 2026-10-15 (yamamoto/Claude): 確認を省く範囲は `exec-plan-runner` の 1 か所に一覧で置き、各スキルからはそこを参照する（各スキルに例外を散らさない）。完了処理の前のユーザー確認（`.agent/PLANS.md` の完了処理の手順 1）は省かない。

## Outcomes & Retrospective

## Context and Orientation

用語:

- サブエージェント: メインの Claude Code セッションから仕事を任せる別の Claude。自分の指示文・使えるツール・モデルを持つ。`.claude/agents/<名前>.md` で定義する。
- frontmatter: Markdown の先頭の `---` で囲んだ設定欄。
- effort（推論の強さ）: 考える量の設定。`low`・`medium`・`high`・`xhigh`・`max`。
- 自己レビュー: push する前に、実装した役とは別のレビュー役が差分を読み、問題を指摘すること。
- メインのセッション: 人と対話している Claude Code のセッション。この計画ではまとめ役（orchestrator）を兼ねる。

関係するファイル:

- `.agent/agents/*.md`: 役割（orchestrator・explorer・planner・worker・reviewer・tester・roadmap-curator）の責務の正本。書き込み範囲もここが正。
- `assets/docs/ai-runtime-governance.md`: ランタイム分担と推論運用の正本。今は「主運用は Codex」と書いてある。
- `.claude/skills/`: スキル本文の正本。`.codex/skills/` は Codex 用の入口（#680 で薄くする予定）。
- `.claude/skills/roadmap-next-task/SKILL.md`: 今の `/next-task`・`/finish-task`・`/sync-roadmap`。
- `.claude/skills/roadmap-manager/SKILL.md`: ロードマップの追加・変更・削除と `/roadmap-sync`。`/roadmap-next` が `/next-task` と重複している。
- 確認で止まる箇所（M4 で runner の一覧に従う形へ改める）:
  - `.claude/skills/project-worker/SKILL.md`: `/handoff` の「コミットして push しますか？」（妥当な区切りに達したとき）、`/work` の初回着手時の `skill:init`
  - `.claude/skills/issue-resolver/SKILL.md`: 規則 14「エンジニア承認前に修正へ進まない」と implement-fix の手順 1
  - `.claude/skills/exec-plan/SKILL.md`: `/create-plan` の手順 7「プラン作成完了を伝えて停止」、ブランチ作成・コミット・PR には明示指示が必要とする規則
  - `.claude/skills/review-agent/SKILL.md`: 規則 11・12、`/resolve-review` の手順 2（方針の確認）と手順 5（push の確認）
  - `.agent/agents/reviewer.md`: GitHub への返信・resolved 化の前の確認
  - `AGENTS.md` の「レビュー時」手順 1〜3 と `.claude/skills/doc-maintainer/SKILL.md` の `/pr-sync` 手順 1・5: resolved 化の確認、追加コミット後の `/pr-sync` 実行と乖離の確認
  - `AGENTS.md` の「新タスク着手時」手順 4、`.claude/skills/exec-plan/SKILL.md` の「ロードマップ連携」4 と `/update-plan` 手順 7: 着手時に `Status` を `WIP` にする規則（runner の実行中は変えない）
  - `.agent/agents/orchestrator.md` の判断基準 1・8: 直列でも役割を分ける runner の例外を反映する
  - Codex の写し（`.codex/skills/*/SKILL.md` と `agents/openai.yaml`）
- `.claude/skills/adaptive-validation-runner/`: Docker の検証環境で動作を確かめるスキル。並走時は `EVO_VALIDATE_NAME`・`EVO_BIND_IP` を分ける。
- `.github/codex-pr-rules.md`: PR 本文の見出し（概要・変更内容・確認手順・備考）とラベルの決まり。
- `.agent/PLANS.md`: ExecPlan の書式と完了処理。

Claude Code のサブエージェント定義（公式ドキュメント https://code.claude.com/docs/en/sub-agents ）:

- 主な欄は `name`・`description`（必須）、`model`（`sonnet`・`opus`・`haiku`・`fable`・`inherit` またはモデル ID）、`effort`、`tools`・`disallowedTools`、`permissionMode`、`isolation: worktree`、`maxTurns`。
- モデルの決まり方は、呼び出しごとの `model` 指定 → frontmatter の `model` → 環境変数 `CLAUDE_CODE_SUBAGENT_MODEL` → メインのセッションのモデル、の順。
- `effort` はセッションの effort より優先されるが、環境変数 `CLAUDE_CODE_EFFORT_LEVEL` より弱い。使える段階はモデルによる。
- `tools` を省くと親のツールをすべて受け継ぐ。`Bash` を残すとファイルを書けるので、`disallowedTools: Write, Edit` だけでは書き込みを完全には止められない。
- `isolation: worktree` は一時的なワークツリーで動かす。分岐元は `worktree.baseRef` の設定による（既定は既定ブランチ）。この計画では使わない（下の作業場所の決め方を参照）。
- `permissionMode` は、親が `bypassPermissions`・`acceptEdits`・auto モードのときは無視される。

## Plan of Work

### 役割・モデル・effort（M1）

`.claude/agents/` に次の 5 つを置く。書き込みの列は `.agent/agents/` の正本に合わせ、runner での運用上の制限を括弧で添える。

| 名前 | model | effort | 書き込み | 主な用途 |
|---|---|---|---|---|
| `evo-explorer` | `haiku` | `medium` | なし（読み取り専用。`tools: Read, Grep, Glob, Bash`） | 既存実装や影響範囲の下調べ |
| `evo-planner` | `opus` | `high` | `.agent/plans/`・`.agent/roadmap.md`（`planner.md` のとおり） | ExecPlan の作成と更新 |
| `evo-worker` | `sonnet` | `medium` | 割り当てたファイル（runner ではコミットまで。push はしない） | 実装 |
| `evo-tester` | `sonnet` | `medium` | テストの追加・`.agent/runs/`（`tester.md` のとおり。runner では検証環境の操作のみ） | 再現と動作確認 |
| `evo-reviewer` | `opus` | `high` | なし（`disallowedTools: Write, Edit`。GitHub にも書かない） | push 前の自己レビュー、Codex 指摘の仕分け |

effort の初期値の考え方: explorer は `low` だと長い指示のもとで検索を飛ばしたり早く止まったりしやすい（Haiku 5.5 の移行ガイド）ので `medium`。planner と reviewer は知能が効く作業なので最低の `high`。worker と tester はエージェント型のコーディングの出発点である `medium`。`xhigh`・`max` は効果を測ってから使う。

格上げ: 自己レビューで 2 往復しても重大な指摘が残ったら、同じ作業を worker に `model: opus`（`effort: medium`）を指定して 1 回だけやり直させ、それでも残れば止まる。呼び出しごとの `model` 指定は frontmatter より優先される。

本文は薄い入口にする。例（evo-worker）:

    ---
    name: evo-worker
    description: Evolution CMS JP Edition の実装担当。exec-plan-runner から、Issue またはマイルストーンと、書き込んでよいファイル・作業ディレクトリを渡されて使う。
    model: sonnet
    effort: medium
    tools: Read, Grep, Glob, Edit, Write, Bash
    ---
    AGENTS.md と .agent/agents/worker.md を読み、それに従う。
    作業は渡された作業ディレクトリの中だけで行い、Bash では毎回そのディレクトリを -C や cd で明示する。
    渡されたファイル以外を変更する必要が出たら、変更せずに理由を返す。
    コード（実行・ビルド・型チェックできるもの）を変えたら、完了と報告する前に、その変更を実際に動かす確認（php -l だけでなく、該当する画面・CLI・テスト）を行う。確認できなかった場合は、何を確認できず、なぜかを報告する。
    コミットまで行い、push はしない。返すもの: 変更したファイル、コミットのハッシュ、行った確認とその結果、残った懸念。

### push・マージを機械的に止める（M1）

リポジトリに `.claude/settings.json` を置き、`permissions.deny` で main への push（`git push origin main`、`HEAD:main`、`refs/heads/main`、`git -C <パス> push …` の書き方を含む）と強制 push（`--force`・`-f`・`+` の refspec）を止める。タグの push・`gh pr merge`・`gh release` は止めない（Decision Log を参照。runner の不変条件で守る）。この設定は本人の確認を得て入れた。

あわせて、`/run-issue` の 1 周（`gh issue develop`、`git push`、`gh pr create`、`docker compose exec`）で許可確認が出るかを確かめ、出るものは許可設定（`permissions.allow`）に足すかどうかを本人と決める。結果は Surprises & Discoveries に残す。

### 作業場所（M1 で確かめ、M2 に書く）

Issue ごとに、メインのセッションが次の順でワークツリーを用意する。既存のブランチは後から Issue に紐づけられないので、`gh issue develop` を必ず最初に行う。

    gh issue develop <番号> -R modxcms-jp/evolution-jp --name <種別>/<番号>-<短い名前> --base main
    git fetch origin
    ROOT=$(dirname "$(git rev-parse --path-format=absolute --git-common-dir)")
    git worktree add "$ROOT/.claude/worktrees/issue-<番号>" <種別>/<番号>-<短い名前>

ワークツリーの中から相対パスで `git worktree add` すると入れ子になるので、必ず本体のルート（`$ROOT`）を起点にする。

worker・tester・reviewer には作業ディレクトリの絶対パスを必ず渡す。worker が返したあと、メインのセッションが `git -C <パス> diff --name-only origin/main...HEAD` で、変更が割り当てたファイルに収まっているかを照合する。tester が main と比べるときは、`git worktree add --detach <一時パス> origin/main` で別のワークツリーを使い、worker のワークツリーの checkout は切り替えない。PR がマージされたら `git worktree remove "$ROOT/.claude/worktrees/issue-<番号>"` で片付ける。

`isolation: worktree` は使わない。分岐元が設定に左右され、紐づけ済みブランチへの push とブランチ名の受け渡しが増えるため（Decision Log の代替案として残す）。

### 実行の繰り返し（M2）

M2 以降、この節の手順・不変条件・止まる条件の正本は `.claude/skills/exec-plan-runner/SKILL.md` とする。この節は計画時点の案で、変えた点は Decision Log の M2 の項に記録してある。

新しいスキル `exec-plan-runner` に次のコマンドを置く。本文は抽象的な役割で書き、Claude Code での割り当ては `.claude/agents/` を参照する。

- `/run-issue <番号>`: ExecPlan の無い Issue を 1 件直す。
- `/run-plan <ExecPlan のパス>`: ExecPlan をマイルストーンごとに進める。
- `/finish-task`: 完了処理（今の `roadmap-next-task` から移す）。中身は `.agent/PLANS.md` の完了処理を正本とし、ロードマップの更新は `roadmap-manager` の `/roadmap-sync` を使う。

不変条件（どの段階でも守る）:

- push してよいのは、`gh issue develop` で作ったその Issue のブランチだけ。main・ほかのブランチ・タグへの push、`--force`、`gh pr merge`、`gh release` はしない。
- 非公開アドバイザリに関わる修正は扱わない。指示されても、重なる可能性に気付いても、止まって本人に返す。公開の場（PR・Issue・コミット）にアドバイザリの存在や内容を書かない。
- `security` ラベル付きの Issue では、PR 本文・コミット・自己レビューの要約に悪用の手順を書かない（「エスケープをそろえる」「権限を確かめる」などの中立的な書き方にする）。
- 顧客名・社名・個人名を書かない。
- ロードマップの Status は Issue ごとの PR では変えない。

1 件（Issue 1 件、またはマイルストーン 1 つ）ごとの繰り返し:

1. 事前確認: Issue が開いていて、担当者・既存のブランチ・PR が無いことを確かめる。あれば止まる。
2. 準備: 上の「作業場所」の手順と Issue の初期設定（アサイニー、タイプ、ラベル）。必要なら explorer に下調べを任せる。explorer の結果は手がかりとして使い、原因の根拠には worker・reviewer が自分で裏を取る。
3. 実装: worker に、Issue（またはマイルストーン）、作業ディレクトリ、書き込んでよいファイル、確認のしかたを渡す。worker はコミットまで行う。
4. 範囲の照合: メインのセッションが変更ファイルを照合し、`php -l` が通ることを確かめる。
5. 検証: tester が、Issue に書いた症状が直ったことを確かめる。画面や DB が絡む場合は adaptive-validation-runner を使う。
6. 自己レビュー: メインのセッションがレビュー前の `git -C <パス> status --porcelain` と `git -C <パス> rev-parse HEAD` を控え、reviewer に main との差分全体・Issue・検証結果を渡す。reviewer は重大度順に指摘を返す（無ければ「問題なし」と明記）。レビュー後に同じ 2 つを取り、変化が無いことを確かめる。
7. 修正: 高・中の指摘があれば worker が直し、reviewer が見直す。往復は 2 回まで。残れば格上げ（上記）、それでも残れば止まる。
8. push と PR: メインのセッションが push し、PR を作る。本文は `.github/codex-pr-rules.md` の見出しに従い、「確認手順」に検証の結果、「備考」に自己レビューの結果（指摘と対応、または問題なし）を書く。`Closes #番号` を入れ、ラベルは Issue に合わせる（`fix`、必要なら `security`）。
9. 記録: ExecPlan があれば Progress を更新する。Issue へのコメントは、PR で足りるので行わない。

Codex のレビューへの対応は 1 周に含めない。人が依頼したとき、または次の `/run-issue` に入る前に、`/resolve-review <PR>` で扱う（仕分けは reviewer、修正は worker、GitHub への書き込みはメインのセッション）。review-agent が「ユーザー判断を仰ぐ」に分類した指摘は止まって人に返す。

止まる条件（何を決めてほしいかを 1 か所にまとめて人に返す）:

- 「要相談」に当たる判断が出た（DB スキーマの変更、互換性を損なう変更、Issue で決まっていない挙動の選択、Issue の「直し方の案」と違う直し方が要る など）
- Issue が閉じている、担当者・ブランチ・PR が既にある
- 割り当てた範囲の外を直す必要が出た、または差分が想定より大きい（目安: 変更ファイルが Issue の「場所」の 2 倍を超える）
- `php -l` などの静的な確認が通らない、検証できない（環境が立たない、再現しない）
- 自己レビューで、格上げのあとも高・中の指摘が残る
- push 後に CI が失敗した、main との衝突で rebase が要る
- 非公開アドバイザリと重なる可能性に気付いた
- 許可確認が出て、先に進めない

### スキルの整理（M3）

M3 以降、`/next-task` の手順の正本は `.claude/skills/roadmap-manager/SKILL.md` とする（`--run` の扱いなど、変えた点は Decision Log の M3 の項）。

更新する箇所（行番号は 2026-10-15 時点の `docs/684-orchestration-runner`）:

- `AGENTS.md:193,197,204`: `roadmap-next-task` の記述を、`roadmap-manager` の `/next-task` と `exec-plan-runner` に置き換える
- `.agent/agents/orchestrator.md:13`: 利用スキルの `roadmap-next-task` を外し、`exec-plan-runner` を足す
- `.agent/agents/roadmap-curator.md:11`: 利用スキルの `roadmap-next-task` を外す
- `.claude/skills/roadmap-manager/SKILL.md:33` と `.codex/skills/roadmap-manager/SKILL.md:36`: `/roadmap-next` を `/next-task` に置き換える（選定し、ExecPlan の要否を決めて `exec-plan-runner` へ渡す）
- `.codex/skills/roadmap-manager/agents/openai.yaml:7`: 同上
- `.claude/skills/roadmap-next-task/` と `.codex/skills/roadmap-next-task/`: ディレクトリごと削除

### 確認を省く範囲とランタイム分担（M4）

`exec-plan-runner` に「この runner の実行中は、次の確認を省いて先へ進む」という一覧を置き、Context に挙げた各スキル・エージェント定義には「`exec-plan-runner` から呼ばれたときは、runner の一覧に従う」と 1 行だけ足す。省かないもの: `.agent/PLANS.md` の完了処理の前のユーザー確認、不変条件、止まる条件。

`assets/docs/ai-runtime-governance.md`・`.claude/skills/README.md`・`.codex/skills/README.md` の「主運用は Codex」を「主に使うランタイムは Claude Code、Codex は補佐（PR の補助レビューを含む）」に改める。ランタイム分担の文書の「役割ごとの目安」の節に、Claude Code での具体的な割り当ては `.claude/agents/` を正本とする、と 1 行足す。Codex 用の入口は、`exec-plan-runner` について `.codex/skills/exec-plan-runner/SKILL.md` と `agents/openai.yaml` に正本への参照だけを置く（Codex では役割を分けずに 1 つのセッションで順に行う、と注記する）。

### 試運転（M5）

0 節から次の 3 件を `/run-issue` で直列に処理する。

- #642（チャンク削除の権限名）: 修正は 1 行。権限を絞ったロールを作る検証で、tester と adaptive-validation-runner を試す。
- #664（DocManager のログ文言）: 修正は 1 行。DocManager の画面操作と管理ログの確認を試す。
- #651（`@FILE`・`@INCLUDE` の後ろに改行があると読み込まれない）: 直し方に判断が要り、自己レビューの往復や止まる条件が出やすい。

実装役のモデルを比べるため、#642 と #664 は worker を `sonnet`（`medium`）、#651 は同じ Issue を `sonnet`（`medium`）と `opus`（`low`）の両方で 1 回ずつ実装させ（PR にするのは片方だけ）、品質（自己レビューの指摘の数と重さ）・トークン数・かかった時間を比べる。結果で worker の既定を決め、Decision Log に残す。

`/run-plan` の試運転は、0 節の子 ExecPlan（公開状態とアクセス権、または キャッシュのキー）を最初に作るときに行う。この計画の到達条件には含めない。

## Concrete Steps

M1:

1. `.claude/agents/evo-explorer.md`・`evo-planner.md`・`evo-worker.md`・`evo-tester.md`・`evo-reviewer.md` を、Plan of Work の表と雛形に沿って作る。
2. 新しいセッションで `/agents` を開く。期待: 5 つが一覧に出る。
3. evo-reviewer に、任意のファイルを書き換えるよう頼む。期待: Write・Edit が使えず断る。続けてメインのセッションで `git status --porcelain` を実行し、何も変わっていないことを確かめる。
4. evo-worker・evo-reviewer を 1 回ずつ呼び、使われたモデルを確かめる。方法の候補: セッション記録（`~/.claude/projects/<プロジェクト>/` の `*.jsonl`）に残る `model` を grep する。この方法で確かめられるかは未確認。確かめられなければ別の方法を探し、結果を Surprises に残す。
5. 本人の確認を得て `.claude/settings.json` に `permissions.deny` を入れる。`git push origin HEAD:main` を試し、期待: 拒否される。
6. 試しの Issue なしで、ワークツリーの作成（「作業場所」の 3 コマンド）と削除（`git worktree remove`）を一度通す。期待: `git worktree list` に出て、削除後に消える。
7. `gh issue develop`・`git push`（自分の試しブランチ）・`gh pr create --draft`・`docker compose exec` で許可確認が出るかを確かめ、Surprises に残す。試しの PR とブランチは閉じて消す。

M2:

8. `.claude/skills/exec-plan-runner/SKILL.md` を、Plan of Work の「実行の繰り返し」に沿って作る（不変条件、繰り返し、止まる条件、確認を省く一覧、作業場所の手順）。

M3:

9. Plan of Work の「スキルの整理」の各箇所を直し、2 つのディレクトリを削除する。
10. `grep -rn "roadmap-next-task\|/roadmap-next\b\|/sync-roadmap" --include=*.md --include=*.yaml .` を実行する。期待: ヒットするのは `.agent/roadmap-archive.md`、`.agent/plans/archive/` の中、`.agent/roadmap.md` の判断ログ、この ExecPlan だけ。

M4:

11. Plan of Work の「確認を省く範囲とランタイム分担」の各箇所を直す。
12. `grep -rn "主運用" assets/docs .claude/skills/README.md .codex/skills/README.md AGENTS.md` を実行する。期待: 「Codex が主」を意味する記述が残っていない。

M5:

13. #642・#664・#651 を `/run-issue` で順に処理する。期待: それぞれ PR ができ、本文の「備考」に自己レビューの結果がある。止まった場合は、何を決めてほしいかが 1 か所にまとまっている。
14. #651 で実装役の比較を行い、結果を Decision Log に残す。

## Validation and Acceptance

- `/agents` に evo-explorer・evo-planner・evo-worker・evo-tester・evo-reviewer の 5 つが出る。
- evo-reviewer を使った前後で、メインのセッションが確かめた `git status --porcelain` と `git rev-parse HEAD` が変わらない。
- `git push origin HEAD:main` と強制 push が、許可設定で拒否される（`gh pr merge`・`gh release`・タグの push は不変条件で守る）。
- `/run-issue <番号>` で、止まる条件に当たらない限り、人が途中で答えなくても「事前確認 → 準備 → 実装 → 範囲の照合 → 検証 → 自己レビュー → push → PR」まで進む。
- 試運転の 3 件の PR が、自己レビューで高・中の指摘ゼロ、CI 成功、PR 本文の「備考」に自己レビューの結果あり、の状態になっている。止まった場合は、止まる条件のどれに当たったかが人への報告に書いてある。
- 試運転の 3 件のうち最初に閉じたロードマップ項目で、`/finish-task` を一度通す。同期用のブランチと PR ができ、ロードマップの更新とアーカイブがその PR に入っている。
- `roadmap-next-task` と `/roadmap-next`・`/sync-roadmap` への参照が、Concrete Steps 10 の期待どおりにしか残っていない。
- `assets/docs/ai-runtime-governance.md` が「Claude Code が主、Codex は補佐」になっている。

## Idempotence and Recovery

- エージェント定義・スキル・許可設定はファイルの追加と書き換えだけで、何度やり直しても同じ状態になる。
- PR の分け方: M1〜M4 は #684 の PR（このブランチ）にまとめる。M5 の試運転は Issue ごとに別の PR になる。
- 試運転の PR が想定どおりでなければ、PR を閉じてブランチとワークツリーを消し、スキルを直してからやり直す。
- 許可設定が作業の邪魔になった場合は、`.claude/settings.json` のその行を消せば元に戻る。
- `roadmap-next-task` を消したあとに問題が出たら、#684 の PR の該当コミットを戻せば元に戻る。

## Artifacts and Notes

- Issue: #684
- 関連: #680（Codex 用スキルの複製）、PR #666（ロードマップ運用の見直し。このブランチの分岐元）
- 公式情報: https://platform.claude.com/docs/en/about-claude/models/overview 、 https://code.claude.com/docs/en/sub-agents 、 https://code.claude.com/docs/en/worktrees
- 2026-10-15 の自己レビュー（Opus、読み取り専用）の指摘は、この版の Decision Log と Plan of Work に反映済み。
- M1 で分かった確かめ方と限界は、この計画の完了後も使えるよう `.claude/agents/README.md` に残した。

## Interfaces and Dependencies

- Claude Code のサブエージェント機能（`.claude/agents/`）と許可設定（`.claude/settings.json`）。モデルの別名（`sonnet`・`opus`・`haiku`）が指す版は Claude Code 側で変わる。
- GitHub CLI（`gh issue develop`、`gh pr create`、GraphQL の `resolveReviewThread`）。
- 検証環境（Docker、adaptive-validation-runner）。
- PR #666 のマージが前提（ロードマップの書式と ExecPlan の要否判断を使う）。
