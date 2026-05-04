---
name: "review-agent"
description: "`.agent/agents/reviewer.md` を入口に、PR差分やローカル差分を日本語でレビューするスキル。ユーザーが「レビュー」「PR確認」「差分確認」「コードレビュー」「reviewer」「レビュー指摘に対応」などを依頼したときに使う。GitHubレビュー指摘の対応（分類・方針提示・Worker委譲・resolved化）も扱う。"
---

# Review Agent

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/review-agent/SKILL.md` に置く。

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

正本の実行ルールは `.claude/skills/review-agent/SKILL.md` の `## 実行ルール` を参照。

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

詳細な手順は `.claude/skills/review-agent/SKILL.md` の `/resolve-review` を参照。

## 出力形式

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
