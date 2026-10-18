---
name: adaptive-validation-runner
description: 改修後の動作確認を、AIが自分で環境を立てて最後まで進めるための検証スキル。CLI（curl / php evo / DB）を優先し、画面操作でしか判定できないときだけ Playwright（既定はヘッドレス）に切り替える。Docker の検証環境の起動、インストール、テスト用ユーザー作成、mailpit でのメール確認、main との比較、後片付けまでを扱う。「動作確認」「自走で確認」「実環境で確かめる」「ブラウザで確認」「再現を確認」「Playwright」と依頼されたとき、または修正のあとに実機での裏付けが必要なときに使う。
---

# Adaptive Validation Runner

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/adaptive-validation-runner/SKILL.md` に置く。必ず正本を読み、そのすべてに従う。ここには手順を写さない。検証環境の手順は同じディレクトリの `references/environment.md`、同梱スクリプトは `scripts/` にある。

## Codex での差分

- `docker`、`npm install`、`npx playwright install`、ブラウザの起動は、サンドボックスの中では失敗することがある。次のような失敗が出たときだけ、制限の外での実行へ切り替えて再実行する
  - `sandbox_host_linux.cc`
  - `Operation not permitted`
  - `Target page, context or browser has been closed`
- 上記は毎回必ず起きるものではない。最初から制限の外で実行しない
