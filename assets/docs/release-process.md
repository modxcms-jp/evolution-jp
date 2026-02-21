# リリース手順

Evolution CMS JP Edition のリリースパッケージを GitHub Release として公開する最短手順。

## 基本方針

- パッケージ構成の SSOT は `.github/workflows/release.yml`。
- このドキュメントには除外パターンを重複記載しない。
- 除外追加・変更は `release.yml` の `Prepare dist directory` の `rsync --exclude` を編集する。

## 基本手順

1. タグを作成して push する（形式: `release-*`）。

```bash
git tag release-1.5.0
git push origin release-1.5.0
```

2. GitHub Actions の `Build Release Package` が自動実行される。
3. Releases 画面で zip 添付付きリリース作成を確認する。

## 除外設定の編集ルール

- 変更箇所: `.github/workflows/release.yml`
- 対象ステップ: `Prepare dist directory`
- 記法:
  - ファイルは `--exclude='filename'`
  - ディレクトリは `--exclude='dirname/'`（末尾 `/` 必須）
  - 全階層は `--exclude='**/dirname/'`
- 追加時は既存パターンと重複させない。

## ローカル事前確認

`release.yml` の `Prepare dist directory` と同じ `rsync` 行を使って確認する（コピペ元は必ず `release.yml`）。

```bash
mkdir dist
# release.yml の rsync 行をそのまま実行
ls -la dist/
rm -rf dist/
```

## トラブル時

### ワークフローが動かない

- タグ名が `release-*` か確認する。
- `.github/workflows/release.yml` が対象ブランチにあるか確認する。

### zip が想定より大きい

- `release.yml` の除外パターン不足を確認する。
- ローカルで `rsync` 事前確認を実施する。

### 失敗したタグをやり直す

```bash
git tag -d release-1.5.0
git push --delete origin release-1.5.0
git tag release-1.5.0
git push origin release-1.5.0
```

## チェックリスト

- [ ] `CHANGELOG.md` 更新
- [ ] `manager/includes/version.inc.php` 更新
- [ ] インストール/アップグレード動作確認
- [ ] 必要テスト実行
- [ ] リリースノート確認

## 参考

- ワークフロー: `.github/workflows/release.yml`
- ロードマップ: `.agent/roadmap.md`
