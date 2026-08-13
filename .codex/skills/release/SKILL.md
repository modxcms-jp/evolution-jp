---
name: release
description: Evolution CMS JP Edition のリリース作業を対話形式でガイドするスキル。バージョン更新・タグ作成・GitHub Actions によるパッケージビルドまでを順を追って進める。「リリース」「release」と依頼されたときに使用する。
---

# リリーススキル

このファイルは Codex 実行用の入口とメタデータを管理する。
手順本文の正本は `.claude/skills/release/SKILL.md` に置き、実行時は `assets/docs/release-process.md` を参照する。

## コマンド

### /release

引数なしで呼び出された場合は、まず次の内容を簡単に説明する。

> このスキルは、バージョン情報の更新、リリースタグの作成、GitHub Actions による配布ZIPの作成、ドラフトReleaseとZIPの確認、リリースノートの適用、公開までを順に案内します。確認を取りながら進めるため、回答が固まるまではファイル変更・コミット・タグ作成・push・公開を行いません。

その後、`.claude/skills/release/SKILL.md` の「引数なしで開始した場合」に定める項目を1問ずつ質問する。回答が曖昧な場合はその項目だけを聞き直し、全項目を確認してから開始前チェックへ進む。
