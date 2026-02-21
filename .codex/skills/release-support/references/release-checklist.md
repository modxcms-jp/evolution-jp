# Release Checklist

## 事前チェック

1. 作業ブランチとリリース対象ブランチを確認する。
2. `CHANGELOG.md` を更新する。
3. `manager/includes/version.inc.php` の `modx_version` と `modx_release_date` を更新する。
4. 必要なテストを実行し、失敗がないことを確認する。
5. インストール/アップグレード動作確認の結果を記録する。

## 実施コマンド

```bash
git tag release-x.y.z
git push origin release-x.y.z
```

## 公開後チェック

1. GitHub Actions の `Build Release Package` が成功していることを確認する。
2. Releases 画面に zip 添付付きで公開されていることを確認する。
3. リリースノートに主要変更が過不足なく記載されていることを確認する。

## 失敗時対応

1. タグ名が `release-*` 形式か再確認する。
2. 対象ブランチに `.github/workflows/release.yml` があるか確認する。
3. 配布物サイズが異常な場合は `Prepare dist directory` の `rsync --exclude` を見直す。
4. タグやり直しが必要な場合のみ、以下を順に実行する。

```bash
git tag -d release-x.y.z
git push --delete origin release-x.y.z
git tag release-x.y.z
git push origin release-x.y.z
```
