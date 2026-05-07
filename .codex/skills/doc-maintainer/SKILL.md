---
name: doc-maintainer
description: AI向けマークダウンドキュメント（SKILL.md、AGENTS.md、.agent/*.md など）の健全性チェックと修正を支援するスキル。「ドキュメントの整合性を確認」「SSOT違反を探す」「スキル定義をメンテしたい」「PRの概要を更新したい」と依頼されたときに使用する。
---

# Doc Maintainer

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/doc-maintainer/SKILL.md` に置く。

AI向けドキュメントの SSOT 違反・表記ゆれ・構造問題を検出・修正する。
PR 概要とコミット差分の乖離チェックも扱う。

## 参照順

1. `AGENTS.md`
2. `.claude/skills/doc-maintainer/SKILL.md`
3. `.agent/PLANS.md`
4. `.agent/roadmap.md`
5. 関連する `SKILL.md` / `AGENTS.md` / `.agent/*.md`
6. PR 関連作業では `.github/codex-pr-rules.md`

## 実行ルール

1. ドキュメントの正本を尊重し、重複修正ではなく SSOT へ寄せる。
2. 修正前に参照元と参照先の責務分担を確認する。
3. パス表記はリポジトリルート基準で統一し、既存ルールがある場合はそれに従う。
4. 番号付き手順は途中で見出しを挟まず、補足が必要なら本文へ吸収する。
5. PR 更新系操作は、必ずユーザー確認を取った場合のみ実行する。

## コマンド

### /doc-audit [対象]

詳細な手順は `.claude/skills/doc-maintainer/SKILL.md` の `/doc-audit` を参照。

### /doc-fix <問題番号|説明>

詳細な手順は `.claude/skills/doc-maintainer/SKILL.md` の `/doc-fix` を参照。

### /pr-sync <PR番号>

詳細な手順は `.claude/skills/doc-maintainer/SKILL.md` の `/pr-sync` を参照。

`/pr-sync <PR番号>` は、実行前に必ず `はい・いいえ` で確認し、`はい` の場合のみ進める。

## 出力形式

1. `/doc-audit` は問題一覧を重大度順に並べ、`種別 / 重大度 / ファイル:行番号 / 内容 / 修正方針` を含める。
2. `/doc-fix` は修正対象、適用内容、残件有無、必要ならコミットメッセージ案を簡潔に示す。
3. `/pr-sync` は乖離箇所、更新案、ユーザー確認要否を分けて示す。
