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
GitHub への返信・コメントと review thread の resolved 化は、修正と push の完了後に `はい・いいえ` で確認し、`はい` の場合のみ実行する。

## コマンド

### /review-diff

詳細な手順は `.claude/skills/review-agent/SKILL.md` の `/review-diff` を参照。

### /review-pr

詳細な手順は `.claude/skills/review-agent/SKILL.md` の `/review-pr` を参照。

### /resolve-review <PR番号>

詳細な手順は `.claude/skills/review-agent/SKILL.md` の `/resolve-review` を参照。

## 出力形式

差分レビューおよび /resolve-review の出力形式は `.claude/skills/review-agent/SKILL.md` の `## 出力形式` を参照。
