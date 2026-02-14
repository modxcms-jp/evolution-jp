# リリース手順

Evolution CMS JP Edition のリリースパッケージを作成し、GitHub Release として公開する手順。

## 基本手順

### 1. リリースタグの作成

```bash
# タグ作成（例: release-1.5.0）
git tag release-1.5.0

# タグを push
git push origin release-1.5.0
```

### 2. GitHub Actions の自動実行

タグが push されると `.github/workflows/release.yml` が自動実行される。

**処理内容:**

1. リポジトリをチェックアウト
2. `dist/` ディレクトリを作成
3. 除外ファイルを除いてプロジェクトファイルを `dist/` にコピー
4. `evo-release-1.5.0.zip` を作成
5. GitHub Release を自動作成し、zip ファイルを添付

### 3. リリース完了の確認

- GitHub の Releases ページで新しいリリースが作成されていることを確認
- zip ファイルがダウンロード可能であることを確認

## 除外ファイル一覧

リリースパッケージから除外されるファイル・ディレクトリ:

```
.git/
.github/
.gitignore
.gitkeep
.gitattributes
.editorconfig
dist/
docs/
**/docs/
readme*
README*
AGENTS.md
```

### 除外設定の追加方法

除外ファイル・ディレクトリを追加する場合は [.github/workflows/release.yml](../../.github/workflows/release.yml) を編集する。

```yaml
- name: Prepare dist directory
  run: |
    mkdir dist
    rsync -a ./ dist/ \
      --exclude='.git/' \
      --exclude='.github/' \
      --exclude='.gitignore' \
      --exclude='.gitkeep' \
      --exclude='.gitattributes' \
      --exclude='.editorconfig' \
      --exclude='dist/' \
      --exclude='docs/' \
      --exclude='**/docs/' \
      --exclude='readme*' \
      --exclude='README*' \
      --exclude='AGENTS.md' \
      --exclude='新しい除外パターン'    # ← ここに追加
```

**パターンの書き方:**

| パターン | 説明 | 例 |
|---------|------|-----|
| `filename` | ファイル名 | `--exclude='AGENTS.md'` |
| `dirname/` | ディレクトリ（末尾に `/`） | `--exclude='dist/'` |
| `**/dirname/` | すべての階層のディレクトリ | `--exclude='**/docs/'` |
| `*.ext` | 拡張子パターン | `--exclude='*.log'` |
| `prefix*` | プレフィックスパターン | `--exclude='readme*'` |
| `path/to/file` | 相対パス | `--exclude='temp/cache/'` |

**追加例:**

```yaml
# テストファイルを除外
--exclude='**/test/' \
--exclude='**/tests/' \
--exclude='*.test.php' \

# 開発用ファイルを除外
--exclude='.env' \
--exclude='.env.local' \
--exclude='composer.json' \
--exclude='composer.lock' \
--exclude='package.json' \
--exclude='package-lock.json' \

# ログ・キャッシュを除外
--exclude='*.log' \
--exclude='temp/cache/*' \
--exclude='temp/backup/*'
```

**注意事項:**

- 各 `--exclude` の末尾にバックスラッシュ `\` を付けて改行する（最後の行を除く）
- パターンはシングルクォート `'...'` で囲む
- ディレクトリを除外する場合は末尾に `/` を付ける（例: `dist/`）
- `**/` は「すべての階層」を意味する（例: `**/docs/` は `assets/docs/` も `manager/media/docs/` も除外）

## トラブルシューティング

### タグの命名規則

- **必須形式**: `release-*`（例: `release-1.5.0`, `release-2.0.0-beta1`）
- 形式が異なるとワークフローが実行されない

### GitHub Actions が失敗した場合

#### 1. ビルドログの確認

```
GitHub リポジトリ → Actions タブ → 失敗したワークフロー → ログを確認
```

#### 2. タグの削除と再作成

```bash
# ローカルのタグを削除
git tag -d release-1.5.0

# リモートのタグを削除（GitHub Release も削除される）
git push --delete origin release-1.5.0

# 修正後、再度タグを作成して push
git tag release-1.5.0
git push origin release-1.5.0
```

#### 3. 手動でワークフローを再実行

GitHub の Actions タブから失敗したワークフローを開き、「Re-run jobs」をクリック。

### リリースパッケージの内容確認

ローカルでリリースパッケージの内容を事前確認する場合:

```bash
# dist ディレクトリを作成
mkdir dist

# rsync で除外設定を適用してコピー
rsync -a ./ dist/ \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.gitignore' \
  --exclude='.gitkeep' \
  --exclude='.gitattributes' \
  --exclude='.editorconfig' \
  --exclude='dist/' \
  --exclude='docs/' \
  --exclude='**/docs/' \
  --exclude='readme*' \
  --exclude='README*' \
  --exclude='AGENTS.md'

# 内容を確認
ls -la dist/

# zip を作成（オプション）
cd dist
zip -r ../evo-test.zip .
cd ..

# 確認後、dist ディレクトリを削除
rm -rf dist/ evo-test.zip
```

### よくある問題

#### Q. タグを push したのにワークフローが実行されない

**原因:**

- タグ名が `release-*` 形式ではない
- `.github/workflows/release.yml` が該当ブランチに存在しない

**対応:**

```bash
# タグ名を確認
git tag -l

# ワークフローファイルの存在確認
ls -la .github/workflows/release.yml
```

#### Q. zip ファイルが想定より大きい

**原因:**

- 除外設定が正しく適用されていない
- 不要なファイルがリポジトリに含まれている

**対応:**

1. ローカルで dist を作成して内容確認（上記手順参照）
2. 除外パターンを `.github/workflows/release.yml` に追加

#### Q. リリースノートを後から編集したい

**対応:**

GitHub の Releases ページから該当リリースを開き、「Edit release」で編集可能。

## バージョニング規則

- **メジャーリリース**: `release-2.0.0`（破壊的変更を含む）
- **マイナーリリース**: `release-1.5.0`（機能追加、下位互換性あり）
- **パッチリリース**: `release-1.4.1`（バグ修正のみ）
- **プレリリース**: `release-1.5.0-beta1`, `release-1.5.0-rc1`

## リリース前チェックリスト

- [ ] `CHANGELOG.md` の更新（該当バージョンの変更内容を記載）
- [ ] `manager/includes/version.inc.php` のバージョン番号更新
- [ ] ローカルでの動作確認（インストール・アップグレード）
- [ ] テストケースの実行
- [ ] ドキュメントの更新（`assets/docs/roadmap.md` など）
- [ ] リリースノートの準備（GitHub Release の説明文）

## リリース後の対応

1. リリース告知（フォーラム、SNS など）
2. 次期バージョンの開発ブランチ作成（必要に応じて）
3. `assets/docs/roadmap.md` の更新

## 参考リンク

- GitHub Actions ワークフロー: [.github/workflows/release.yml](../../.github/workflows/release.yml)
- ロードマップ: [roadmap.md](roadmap.md)
