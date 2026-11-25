# リリース手順

Evolution CMS JP Edition のリリースパッケージを作成し、GitHub Release として公開する手順。

## 基本手順

### 1. リリースタグの作成

```bash
# タグ作成（例: release-1.3.0J）
git tag release-1.3.0J

# タグを push
git push origin release-1.3.0J
```

### 2. GitHub Actions の自動実行

タグが push されると `.github/workflows/release.yml` が自動実行される。

**処理内容:**

1. リポジトリをチェックアウト
2. `git archive` でタグのコミットから zip ファイルを作成
3. `.gitattributes` の `export-ignore` に従って、配布対象外のファイルを除外
4. `evo-release-1.3.0J.zip` を作成し、サイズと同じ系列の直前のリリースとの差をジョブの概要に出す
5. GitHub Release を**ドラフト状態**で自動作成し、zip ファイルを添付（リリースノートは自動で作らない。本文は公開前に Releases 画面か `gh release edit` で入れる）

リリースパッケージの生成方式は `git archive` です。配布対象外のパスは `.github/workflows/release.yml` ではなく、リポジトリルートの `.gitattributes` に `export-ignore` を追加して管理します。

### 3. ドラフト確認と公開

1. GitHub の Actions タブでワークフローの完了を確認
2. Releases ページでドラフトリリースを開き、zip をダウンロードして内容を確認
3. 問題がなければ「Publish release」を押して一般公開する

## 除外ファイル一覧

リリースパッケージから除外されるファイル・ディレクトリの正本は [.gitattributes](../../.gitattributes) の `export-ignore` の行である。`.git/` は `git archive` の仕様で常に含まれない。

### 除外設定の追加方法

除外ファイル・ディレクトリを追加する場合は、リポジトリルートの `.gitattributes` を編集する。

```gitattributes
新しい除外パターン export-ignore
```

**パターンの書き方:**

| パターン | 説明 | 例 |
|---------|------|-----|
| `filename export-ignore` | ファイル名 | `AGENTS.md export-ignore` |
| `dirname/ export-ignore` | ディレクトリ | `manager/docker/ export-ignore` |
| `**/dirname/ export-ignore` | すべての階層のディレクトリ | `**/docs/ export-ignore` |
| `*.ext export-ignore` | 拡張子パターン | `*.log export-ignore` |
| `prefix* export-ignore` | プレフィックスパターン | `README* export-ignore` |
| `path/to/file export-ignore` | 相対パス | `manager/includes/cli/skill-lib.php export-ignore` |

**注意事項:**

- パターンの末尾に `export-ignore` を記述する
- ディレクトリを除外する場合は末尾に `/` を付ける（例: `manager/docker/`）
- `**/` は「すべての階層」を意味する（例: `**/docs/` は `assets/docs/` も `manager/media/docs/` も除外）

## トラブルシューティング

### タグの命名規則

- **必須形式**: `release-*`（例: `release-1.3.0J`）
- 形式が異なるとワークフローが実行されない

### GitHub Actions が失敗した場合

#### 1. ビルドログの確認

```
GitHub リポジトリ → Actions タブ → 失敗したワークフロー → ログを確認
```

#### 2. タグの削除と再作成

```bash
# ローカルのタグを削除
git tag -d release-1.3.0J

# リモートのタグを削除（GitHub Release も削除される）
git push --delete origin release-1.3.0J

# 修正後、再度タグを作成して push
git tag release-1.3.0J
git push origin release-1.3.0J
```

#### 3. 手動でワークフローを再実行

GitHub の Actions タブから失敗したワークフローを開き、「Re-run jobs」をクリック。

### リリースパッケージの内容確認

ローカルでリリースパッケージの内容を事前確認する場合:

```bash
# 現在のコミットから、Actions と同じ方式で zip を作成
git archive --format=zip HEAD -o evo-test.zip

# zip の内容を確認
unzip -l evo-test.zip

# 確認後に zip を削除
rm evo-test.zip
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

1. ローカルで `git archive` を実行して内容確認（上記手順参照）
2. 除外パターンを `.gitattributes` に追加

#### Q. リリースノートを後から編集したい

**対応:**

GitHub の Releases ページから該当リリースを開き、「Edit release」で編集可能。

## バージョニング規則

- **メジャーリリース**: `release-2.0.0J`（破壊的変更を含む）
- **マイナーリリース**: `release-1.3.0J`（機能追加、下位互換性あり）
- **パッチリリース**: `release-1.2.1J`（バグ修正のみ）

## リリース前チェックリスト

- [ ] `manager/includes/version.inc.php` のバージョン番号更新
- [ ] ローカルでの動作確認（インストール・アップグレード）
- [ ] テストケースの実行
- [ ] ドキュメントの更新（`.agent/roadmap.md` など）
- [ ] リリースノートの準備（GitHub Release の説明文）

## リリース後の対応

1. リリース告知（フォーラム、SNS など）
2. 次期バージョンの開発ブランチ作成（必要に応じて）
3. `.agent/roadmap.md` の更新

## 参考リンク

- GitHub Actions ワークフロー: [.github/workflows/release.yml](../../.github/workflows/release.yml)
- ロードマップ: [`../../.agent/roadmap.md`](../../.agent/roadmap.md)
