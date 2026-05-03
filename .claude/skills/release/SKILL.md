---
name: release
description: Evolution CMS JP Edition のリリース作業を対話形式でガイドするスキル。バージョン更新・タグ作成・GitHub Actions によるパッケージビルドまでを順を追って進める。「リリース」「release」と依頼されたときに使用する。
---

# リリーススキル

手順の正本は `assets/docs/release-process.md`。このスキルはその手順を対話形式で実行するラッパー。

## コマンド

### `/release`

`assets/docs/release-process.md` の「基本手順」を読み込み、各ステップでユーザー確認を取りながら進める。

**開始前チェック**（docs にない確認事項）:

1. 現在のブランチ（`git branch --show-current`）— `main` でない場合は警告し続行確認
2. `git status` — 未コミット変更があれば警告
3. 現在のバージョン（`manager/includes/version.inc.php` の `$modx_version`）
4. 直近のリリースタグ（`git tag --sort=-creatordate | grep '^release-' | head -5`）
5. `.agent/roadmap.md` の WIP タスク有無

問題がなければ `assets/docs/release-process.md` の手順に従いリリースを進める。
