---
name: "review-agent"
description: "`.agent/agents/reviewer.md` を入口に、PR差分やローカル差分を日本語でレビューするスキル。ユーザーが「レビュー」「PR確認」「差分確認」「コードレビュー」「reviewer」「レビュー指摘に対応」などを依頼したときに使う。GitHubレビュー指摘の対応（分類・方針提示・Worker委譲・resolved化）も扱う。"
---

# Review Agent

`.agent/agents/reviewer.md` を主担当として、バグ・回帰・セキュリティ・キャッシュ整合性・SSOT 違反を優先してレビューする。
PR 作成や説明文の確認では `.github/codex-pr-rules.md` も参照する。

## 参照順

1. `AGENTS.md`
2. `.agent/agents/reviewer.md`
3. `.github/codex-pr-rules.md`
4. 関連する ExecPlan
5. 関連する `assets/docs/`
6. 変更ファイルと `git diff`

## 実行ルール

1. レビューは日本語で行う。
2. 指摘を先に書き、重大度順に並べる。
3. 各指摘にはファイルと行番号を添える（特定できない場合は省略し、ルール10に従って検証不能として報告する）。
4. 問題がない場合は、問題なしと明確に述べ、残るテストギャップを短く添える。
5. 好みのリファクタではなく、バグ・回帰・セキュリティ・運用リスクを優先する。
6. DocumentParser 変更時は、影響フェーズの明示があるか確認する。
7. DB 更新処理では、`db()` ヘルパーと実行直前エスケープを確認する。
8. スーパーグローバル、直接 `$modx` 参照、生SQL、`compact()` の混入を確認する。
9. キャッシュに関係する変更では、無効化条件と再生成条件を確認する。
10. 指摘対象のファイル・行・シンボルが差分またはワークスペースに存在しない場合、推測で指摘を作らず「検証不能」として事実のみ報告する。
11. GitHub への返信、PR コメント、review thread の resolved 化は、修正と push の完了後に「GitHub 返信と review thread の resolved 化を行いますか？ はい・いいえ」で確認し、`はい` の場合のみ実行する。

## コマンド

### /review-diff

1. `git diff` と変更ファイルを確認する。
2. 変更領域に応じて関連 docs を読む。
3. `.agent/agents/reviewer.md` の観点でレビューする。
4. 指摘を重大度順にまとめる。
5. テスト不足と残リスクをまとめる。

### /review-pr

1. PR の目的、差分、関連 Issue、CI 結果を確認する。
2. `.github/codex-pr-rules.md` に照らしてタイトル、説明、ラベル方針を確認する。
3. `.agent/agents/reviewer.md` の観点でレビューする。
4. 指摘を重大度順にまとめる。

### /resolve-review <PR番号>

GitHub PR のレビューコメントを取得し、各指摘を評価・対応・解決する。
実装・コミット・push を伴う場合は Worker エージェントに委譲する。

**ステップ 1: 指摘の収集と評価**

まず GraphQL で未解決スレッド一覧を取得する（REST `/comments` には `isResolved` がないため、resolved 済みスレッドを分類対象から除外するために必要）:

```bash
# PR のレビュースレッドを全件取得し、未解決（isResolved == false）のものだけを対象にする
# GitHub GraphQL API は reviewThreads にフィルタ引数がないため全件取得後に選別する
# pageInfo.hasNextPage が true の場合は after: "<endCursor>" を追加して再実行する
gh api graphql -f query='
{
  repository(owner: "<owner>", name: "<repo>") {
    pullRequest(number: <PR番号>) {
      reviewThreads(first: 100) {
        pageInfo { hasNextPage endCursor }
        nodes {
          id
          isResolved
          comments(first: 1) {
            nodes { databaseId body }
          }
        }
      }
    }
  }
}'
# isResolved == false のスレッドのみを分類・解決対象にする
# 取得した databaseId は REST /comments のスレッド先頭コメント（in_reply_to_id が null）の id と一致する
# 返信コメント（in_reply_to_id がある）は in_reply_to_id を先頭コメントの id と照合してスレッドを特定する
```

次に、インラインレビューコメントとレビュー本文を取得する:

```bash
# インラインレビューコメント（差分行への指摘、スレッド単位で管理される）
gh api --paginate "/repos/<owner>/<repo>/pulls/<PR番号>/comments"

# レビュー本文（スレッドを持たないため resolved 化不可。対応要否を評価し、対応する場合はステップ2の方針一覧に含める）
gh api --paginate "/repos/<owner>/<repo>/pulls/<PR番号>/reviews"
```

取得した `/reviews` の結果は、`state` が `DISMISSED` のものを除外する。`APPROVED` は state のみで除外せず、本文がある場合は評価対象に含める（本文が空の `APPROVED` は除外してよい）。`CHANGES_REQUESTED`・`COMMENTED` は従来どおり対象とし、同一レビュアーが複数回投稿している場合は全件を評価対象とする。

`/resolve-review` を再実行する場合は、PR の既存コメント一覧（`gh pr view <PR番号> --comments`）を確認し、同じ review_id で既に返答済みの review body はこの段階で評価対象から外す（ステップ2の方針一覧に重複項目を残さないため）。

インラインコメントの各スレッドおよび `/reviews` の各レビュー本文をそれぞれ1単位として以下の基準で分類する（スレッド内の複数コメントは一括で判断する）:

| 判定 | 基準 |
|------|------|
| **対応する** | バグ・セキュリティ・動作不正・明確な設計誤り |
| **対応を検討** | 可読性・保守性に影響するが実装シンプルなもの |
| **対応しない** | 好みのスタイル・過剰なエッジケース対応・シンプルさを損なう変更 |

**「対応しない」と判断する典型例:**
- 命名の好みや文体の指摘（機能・意味に影響しない）
- 実運用で発生しない理論上のエッジケース対応要求
- コードを複雑にしてまで型を厳密化する要求
- 既存パターンと統一するだけのリファクタ要求（設計改善でない場合）
- テストカバレッジのためだけに実装を変える要求

**ステップ 2: 対応方針の提示（実施前に確認）**

ユーザーに対応方針の一覧を提示し、確認を取ってから実装を進める。出力形式は `## 出力形式` を参照。

**ステップ 3: 修正の実装**

対応するスレッドの修正を実装する（Worker エージェントに委譲）。
Worker への委譲時は「修正内容、検証手順、検証結果の記録」を成果物として求める。
- 変更はシンプルさを最優先とする
- 対応しないスレッドは理由を明確に持つ

**ステップ 4: 検証**

コミット前に変更内容を確認する。
- 修正が指摘の意図を満たしているか確認
- 関連する動作や既存ロジックへの影響がないか確認

**ステップ 5: コミットと push**

```bash
git add -p  # または対象ファイルを指定
# type は変更内容に応じて選択（fix / refactor / docs / chore など）
# scope は省略可能
git commit -m "<type>: レビュー指摘に対応"
git push
```

実行前に、まずユーザーに次の1問だけ確認する。

```text
コミットして push しますか？ はい・いいえ
```

`はい` の場合のみ、このステップを実行する。`いいえ` の場合はローカル修正と検証結果だけを報告し、GitHub への書き込みは行わない。

**ステップ 6: GitHub上でのコメント解決**

ステップ5の完了後、まずユーザーに次の1問だけ確認する。

```text
GitHub 返信と review thread の resolved 化を行いますか？ はい・いいえ
```

`はい` の場合のみ、このステップを実行する。`いいえ` の場合は GitHub への書き込みを行わず、ローカル修正と push の完了だけを報告して終了する。

対応したスレッドを resolved にする（コードへの書き込みではなく GitHub 上のスレッド状態変更のため review-agent が直接実行する）。ステップ1で取得した thread の id を使う:

```bash
# 対応済みスレッドを resolve（id はステップ1で取得した thread の id）
gh api graphql -f query='
  mutation {
    resolveReviewThread(input: {threadId: "<thread_id>"}) {
      thread { isResolved }
    }
  }
'
```

対応しないスレッドは、スレッドへの返信として理由を残した上で resolved にする（再実行時の重複を防ぐため）:

```bash
# 各スレッドに返信（comment_id はステップ1で取得した先頭コメントの databaseId）
gh api /repos/<owner>/<repo>/pulls/<PR番号>/comments/<comment_id>/replies \
  -f body="見送り理由の説明"
# 返信後に resolved 化（対応済みスレッドと同様）
```

`/reviews` 取得のレビュー本文に対する対応完了・見送り理由は PR コメントとして残す。本文には対象 review の `id`（GitHub が発行する一意な数値 ID）を含め、再実行時にステップ1で除外できるようにする:

```bash
gh pr comment <PR番号> --body "@レビュアー名（review_id: 123456789）: 対応内容または見送り理由"
```

**ステップ 7: 完了報告**

対応済み・見送りの一覧をユーザーに報告する。

## 出力形式

**差分レビュー（/review-diff / /review-pr）:**

セクション見出し（`Findings` / `Open Questions` / `Tests`）はツール間で共通の固定ラベルとして英語のまま使用する。指摘内容・確認事項・テスト説明は日本語で記載する。

指摘がある場合:

```text
**Findings**
- [High] path/to/file.php:123: 指摘内容。影響と修正方針。
- [Medium] path/to/file.php:45: 指摘内容。影響と修正方針。

**Open Questions**
- 確認事項

**Tests**
- 実行済み、または未実行の理由
```

指摘がない場合:

```text
重大な問題は見つかりませんでした。

**Tests**
- 実行済み、または未実行の理由
```

**/resolve-review の出力:**

方針確認フェーズ（ステップ2）:

```text
レビュー対応方針:

- 対応する
  - スレッド xxxx（先頭コメント概要）→ [修正方針]
  - /reviews本文 @レビュアー名（review_id: XXXX）→ [修正方針]

- 対応を検討（ユーザー判断を仰ぐ）
  - スレッド xxxx（先頭コメント概要）→ [対応した場合の影響と見送りのリスク]
  - /reviews本文 @レビュアー名（review_id: XXXX）→ [対応した場合の影響と見送りのリスク]

- 対応しない（理由付き）
  - スレッド xxxx（先頭コメント概要）→ 見送り理由
  - /reviews本文 @レビュアー名（review_id: XXXX）→ 見送り理由
```

完了報告（ステップ7）:

```text
対応完了:
- スレッド xxxx（先頭コメント概要）→ 対応内容
- /reviews本文 @レビュアー名（review_id: XXXX）→ 対応内容
見送り:
- スレッド xxxx（先頭コメント概要）→ 見送り理由
- /reviews本文 @レビュアー名（review_id: XXXX）→ 見送り理由
```
