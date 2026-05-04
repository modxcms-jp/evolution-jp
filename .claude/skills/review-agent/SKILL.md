---
name: "review-agent"
description: "`.agent/agents/reviewer.md` を入口に、PR差分やローカル差分を日本語でレビューするスキル。ユーザーが「レビュー」「PR確認」「差分確認」「コードレビュー」「reviewer」などを依頼したときに使う。GitHubレビュー指摘への対応（修正・コミット・push・resolved）も扱う。"
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
3. 各指摘にはファイルと行番号を添える。
4. 問題がない場合は、問題なしと明確に述べ、残るテストギャップを短く添える。
5. 好みのリファクタではなく、バグ・回帰・セキュリティ・運用リスクを優先する。
6. DocumentParser 変更時は、影響フェーズの明示があるか確認する。
7. DB 更新処理では、`db()` ヘルパーと実行直前エスケープを確認する。
8. スーパーグローバル、直接 `$modx` 参照、生SQL、`compact()` の混入を確認する。
9. キャッシュに関係する変更では、無効化条件と再生成条件を確認する。

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

### /resolve-review [PR番号]

GitHub PR のレビューコメントを取得し、各指摘を評価・対応・解決する。

**ステップ 1: 指摘の収集と評価**

```bash
gh pr view <PR番号> --json reviews,comments
gh api /repos/{owner}/{repo}/pulls/<PR番号>/comments
```

各コメントを以下の基準で分類する:

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

ユーザーに対応方針の一覧を提示し、確認を取ってから実装を進める。

```
## レビュー対応方針

### 対応する
- コメントID xxxx: [内容] → [修正方針]

### 対応しない（理由付き）
- コメントID xxxx: [内容] → スタイルの好みのため見送り
- コメントID xxxx: [内容] → 実運用で発生しないエッジケースのため見送り
```

**ステップ 3: 修正の実装**

対応するコメントの修正を実装する。
- 変更はシンプルさを最優先とする
- 対応しないコメントは理由を明確に持つ

**ステップ 4: コミットと push**

```bash
git add -p  # または対象ファイルを指定
git commit -m "fix(review): <PR番号>のレビュー指摘に対応"
git push
```

**ステップ 5: GitHub上でのコメント解決**

対応したコメントのスレッドを resolved にする。スレッドの node_id は GraphQL で取得する:

```bash
# 1. PR のレビュースレッド一覧とその node_id を取得
gh api graphql -f query='
{
  repository(owner: "<owner>", name: "<repo>") {
    pullRequest(number: <PR番号>) {
      reviewThreads(first: 50) {
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

# 2. 対応済みスレッドを resolve（id は上記で取得した node_id）
gh api graphql -f query='
  mutation {
    resolveReviewThread(input: {threadId: "<thread_node_id>"}) {
      thread { isResolved }
    }
  }
'
```

対応しないコメントは、スレッドへの返信として理由を残す:

```bash
# 各スレッドに返信（comment_id は gh api で取得したコメントのID）
gh api /repos/{owner}/{repo}/pulls/<PR番号>/comments/<comment_id>/replies \
  -f body="見送り理由の説明"
```

**ステップ 6: 完了報告**

対応済み・見送りの一覧をユーザーに報告する。

## 出力形式

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
