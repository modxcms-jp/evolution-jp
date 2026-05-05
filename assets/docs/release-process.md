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
2. `dist/` ディレクトリを作成
3. 除外ファイルを除いてプロジェクトファイルを `dist/` にコピー
4. `evo-release-1.3.0J.zip` を作成
5. GitHub Release を**ドラフト状態**で自動作成し、zip ファイルを添付（自動リリースノート生成あり）

### 3. リリースノートの生成と適用

GitHub Actions が完了したら、AI にリリースノートを生成させてドラフトリリースに適用する。

#### リリースノートの生成

以下の情報を使って日本語リリースノートを生成する:

```bash
# 前回タグからの変更コミット一覧
git log <前回タグ>..<今回タグ> --oneline

# 変更規模
git diff <前回タグ>..<今回タグ> --stat | tail -3

# 前回リリースのノート形式を参照
gh release view <前回タグ>
```

#### リリースノートの構成

1.2.0J リリースのフォーマットに準拠する（`gh release view release-1.2.0J` で確認可）:

```markdown
## 概要
（1〜2 文でバージョンの位置付けを説明）

---

## 主な変更点（ハイライト）
（箇条書きで 3〜5 項目）

---

## 新機能・改善内容
（セクションごとに詳述）

---

## バグ修正
（修正内容を説明）

---

## 開発規模
（ファイル変更数・追加行数・削除行数）

---

## アップグレード時の注意
（必要な場合のみ記載）
```

**注意事項:**
- `(skill)` / `(agent)` / `(roadmap)` スコープのコミットや `.agent/`、`.claude/`、`.codex/` 配下のみを変更するコミットはリリースノートに記載しない（開発者向け内部変更のため）
- `chore`、`docs` プレフィックスのコミットは除外する
- **非エンジニアファーストで書く**（エンジニアも読むので技術用語・技術詳細を入れること自体は構わない）
  - 「何を変えたか」より「なぜ変えたか・どう嬉しいか」を先に説明する
  - 技術用語は補足として添える形にし、説明の主軸は非エンジニアにも伝わる言葉で書く
  - トレードオフや制限事項は隠さず正直に書く

コミット一覧を抽出するときは、内部変更だけのコミットが混ざらないように以下のようにフィルタする:

```bash
# タグを変数にセット
PREV_TAG=$(git tag --sort=-creatordate | grep '^release-' | sed -n '2p')
CURR_TAG=$(git tag --sort=-creatordate | grep '^release-' | sed -n '1p')

git log "${PREV_TAG}..${CURR_TAG}" --format='__COMMIT__%H%x09%s' --name-only | awk '
BEGIN {
    RS="__COMMIT__"
    FS="\n"
}
NR == 1 {
    next
}
{
    header = $1
    sub(/^\n/, "", header)
    split(header, parts, "\t")
    hash = substr(parts[1], 1, 7)
    subject = parts[2]
    internalOnly = 1

    for (i = 2; i <= NF; i++) {
        if ($i == "") {
            continue
        }
        if ($i !~ /^(\.agent\/|\.claude\/|\.codex\/)/) {
            internalOnly = 0
        }
    }

    if (subject ~ /^Merge pull request/) {
        next
    }
    if (subject ~ /^(chore|docs)(\(|:)/) {
        next
    }
    if (subject ~ /^(feat|fix|refactor|perf|style|test|ci|chore|docs)\((skill|skills|agent|agents|roadmap|claude|codex)\):/) {
        next
    }
    if (internalOnly) {
        next
    }

    print hash " " subject
}'
```

#### ドラフトリリースへの適用

生成したリリースノートをユーザーが確認・編集した後、`gh` コマンドで適用する:

```bash
gh release edit <タグ名> --notes "$(cat <<'EOF'
## 概要
...（リリースノート本文）...
EOF
)"
```

### 4. ドラフト確認と公開

1. GitHub の Actions タブでワークフローの完了を確認
2. Releases ページでドラフトリリースを開き、zip をダウンロードして内容を確認
3. 問題がなければ「Publish release」を押して一般公開する

## 除外ファイル一覧

リリースパッケージから除外されるファイル・ディレクトリ:

```
.git/
.github/
.agent/
.agents/
.claude/
.codex/
.vscode/
.work/
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
CLAUDE.md
compose.yml
custom-instructions/
manager/docker/
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
# dist ディレクトリを作成
mkdir dist

# rsync で除外設定を適用してコピー
rsync -a ./ dist/ \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.agent/' \
  --exclude='.agents/' \
  --exclude='.claude/' \
  --exclude='.codex/' \
  --exclude='.vscode/' \
  --exclude='.work/' \
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
  --exclude='CLAUDE.md' \
  --exclude='compose.yml' \
  --exclude='custom-instructions/' \
  --exclude='manager/docker/'

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
