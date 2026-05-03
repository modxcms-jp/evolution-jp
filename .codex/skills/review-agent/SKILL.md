---
name: "review-agent"
description: "`.agent/agents/reviewer.md` を入口に、PR差分やローカル差分を日本語でレビューするスキル。ユーザーが「レビュー」「PR確認」「差分確認」「コードレビュー」「reviewer」などを依頼したときに使う。"
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
