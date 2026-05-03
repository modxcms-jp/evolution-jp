---
name: release
description: Evolution CMS JP Edition のリリース作業を対話形式でガイドするスキル。バージョン更新・タグ作成・GitHub Actions によるパッケージビルドまでを順を追って進める。「リリース」「release」と依頼されたときに使用する。
---

# リリーススキル

## コマンド

### `/release`

対話形式でリリース作業を進める。以下のステップを順番に実行し、各ステップで必ずユーザー確認を取ること。

#### ステップ 1：事前確認

次の情報を収集してユーザーに提示する：

1. 現在のブランチ（`git branch --show-current`）
2. `git status` で未コミット変更の有無
3. 現在のバージョン（`manager/includes/version.inc.php` の `$modx_version`）
4. 直近のリリースタグ（`git tag --sort=-creatordate | grep '^release-' | head -5`）
5. `.agent/roadmap.md` に `WIP` タスクが残っていないか

問題がある場合（未コミット変更、WIP タスクなど、または現在のブランチが `main` でない場合）はユーザーに警告し、続行するか確認する。リリース作業は `main` ブランチ上で行うことを強く推奨する。

#### ステップ 2：新バージョン番号の決定

- 現在のバージョンとタグ形式（`release-{version}`）を示す
- タグ命名規則: `release-X.Y.ZJ`（例: `release-1.3.0J`）
- ユーザーに新バージョン番号を質問する（例: `1.3.0J`）

#### ステップ 3：バージョンファイルの更新

`manager/includes/version.inc.php` を更新する：

- `$modx_version` を新バージョンに変更
- `$modx_release_date` を今日の日付（YYYY-MM-DD）に変更

変更内容を提示してユーザー確認を取る。

#### ステップ 4：バージョン更新のコミット

Conventional Commits 準拠でコミットする：

- 例: `chore(release): バージョンを 1.3.0J に更新`

コマンドを提示してユーザー確認後に実行する。

#### ステップ 5：タグの作成とプッシュ

- 作成するタグ名を提示: `release-{バージョン}`
- 実行するコマンドを提示:
  ```
  git tag release-{バージョン}
  git push origin HEAD
  git push origin release-{バージョン}
  ```
- ユーザーに確認を取り、承認後に実行する
- タグのプッシュで GitHub Actions `Build Release Package` がトリガーされる旨を伝える

#### ステップ 6：ドラフト確認と公開

- GitHub の Actions タブで `Build Release Package` ワークフローの完了を確認するよう伝える
- ワークフロー完了後、GitHub Releases にドラフト状態でリリースが作成される（一般非公開）
- ZIPをダウンロードして内容を確認するよう案内する
- 問題がなければ GitHub Releases 画面で「Publish release」を押して公開するよう伝える
