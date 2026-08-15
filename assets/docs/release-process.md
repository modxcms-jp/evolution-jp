# リリース手順

Evolution CMS JP Edition のリリースパッケージを作成し、GitHub Release として公開する手順。

## 基本手順

### 1. バージョン更新とコミット

リリーススキルの開始前チェックとバージョン入力後の安全確認を完了してから、バージョン情報を更新する。

1. `manager/includes/version.inc.php` の `$modx_version` を新しいバージョンへ更新
2. `$modx_release_date` をリリース日へ更新
3. `git diff --check` と対象ファイルの差分を確認
4. ユーザー確認後、次の形式でコミット

```bash
git diff --check
git diff -- manager/includes/version.inc.php
git add manager/includes/version.inc.php
git commit -m "chore(release): バージョンを 1.3.0J に更新"
```

### 2. リリースタグの作成

```bash
# タグ作成（例: release-1.3.0J）
git tag release-1.3.0J

# バージョン更新コミットを push
git push origin HEAD

# タグを push
git push origin release-1.3.0J
```

### 3. GitHub Actions の自動実行

タグが push されると `.github/workflows/release.yml` が自動実行される。

**処理内容:**

1. リポジトリをチェックアウト
2. `git archive` でタグのコミットから zip ファイルを作成
3. `.gitattributes` の `export-ignore` に従って、配布対象外のファイルを除外
4. `evo-release-1.3.0J.zip` を作成
5. GitHub Release を**ドラフト状態**で自動作成し、zip ファイルを添付（リリースノートは手順4で明示した比較範囲から生成して適用）

リリースパッケージの生成方式は `git archive` です。配布対象外のパスは `.github/workflows/release.yml` ではなく、リポジトリルートの `.gitattributes` に `export-ignore` を追加して管理します。

### 4. リリースノートの生成と適用

GitHub Actions はリリースノートを自動生成しない。完了後、確認済みの比較範囲からAIにリリースノートを生成させ、ドラフトリリースに適用する。

#### リリースノートの生成

リリースノートの比較範囲は、タグの作成日時から推測せず、今回のタグと比較対象のタグを明示する。候補一覧は参考として使い、最終的な `PREV_TAG` はユーザーが確認する。

```bash
# 比較対象の候補をバージョン順に表示（自動選択しない）
git tag --list 'release-*' --sort=-v:refname

# 前回リリースタグがある場合は、ユーザーが確認したタグを設定
PREV_TAG="release-1.3.0J"
CURR_TAG="release-1.4.0J"

# 前回タグがある場合だけ、タグと祖先関係を確認
if [[ -n "${PREV_TAG:-}" ]]; then
    git rev-parse --verify "refs/tags/${PREV_TAG}^{commit}"
    git rev-parse --verify "refs/tags/${CURR_TAG}^{commit}"
    git merge-base --is-ancestor "${PREV_TAG}" "${CURR_TAG}"
else
    BASE_REF="<比較開始コミット>"
    git rev-parse --verify "${BASE_REF}^{commit}"
    git rev-parse --verify "refs/tags/${CURR_TAG}^{commit}"
fi

# 前回タグまたは比較開始コミットから今回タグまでの変更コミット一覧
BASE_REF="${PREV_TAG:-${BASE_REF}}"
git log "${BASE_REF}..${CURR_TAG}" --oneline

# 変更規模
git diff "${BASE_REF}..${CURR_TAG}" --stat | tail -3

# 前回リリースのノート形式を参照（前回タグがある場合のみ）
if [[ -n "${PREV_TAG:-}" ]]; then
    gh release view "${PREV_TAG}"
fi
```

適切な前回リリースタグが存在しない場合は、作成日時順の別タグを代用せず、ユーザーが確認したコミットを `BASE_REF` に設定して比較する。`PREV_TAG` が設定されている場合だけタグの存在確認、祖先関係確認、`gh release view "${PREV_TAG}"` を実行する。

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
# 上の確認済みのタグまたは比較開始コミットを使う（作成日時順から自動取得しない）
# PREV_TAG="release-1.3.0J"
# BASE_REF="<比較開始コミット>"
# CURR_TAG="release-1.4.0J"

BASE_REF="${PREV_TAG:-${BASE_REF}}"
git log "${BASE_REF}..${CURR_TAG}" --format='__COMMIT__%H%x09%s' --name-only | awk '
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

### 5. ドラフト確認と公開

タグ push 後は、Actions の成功とドラフト Release の内容を確認してから公開する。公開前に問題が見つかった場合は「Publish release」を押さず、修正方針を決める。

#### Actions の完了確認

```bash
# 対象タグのワークフロー実行を一覧表示し、対象の run ID を確認
gh run list --workflow release.yml --limit 10

# 対象の run ID を指定して完了まで待機（成功以外は終了コード 1）
gh run watch <run-id> --exit-status
```

`Build Release Package` が `success` になったことを確認する。失敗した場合は、ZIPやReleaseの確認へ進まず、Actionsのログを調査する。

#### ドラフト Release とZIPの確認

```bash
VERSION="1.3.0J"
TAG="release-${VERSION}"
CHECK_DIR=$(mktemp -d)
ZIP_PATH="${CHECK_DIR}/evo-${TAG}.zip"

# ドラフト状態、タグ、添付ファイルを確認
gh release view "${TAG}" --json isDraft,tagName,assets,url

# GitHub Release のZIPを取得
gh release download "${TAG}" --pattern "evo-${TAG}.zip" --dir "${CHECK_DIR}"

# 必須ファイルを確認（未検出時は終了）
if ! unzip -Z1 "${ZIP_PATH}" | rg -q '^index\.php$'; then
    echo 'index.php が見つかりません'
    exit 1
fi
if ! unzip -Z1 "${ZIP_PATH}" | rg -q '^manager/includes/version\.inc\.php$'; then
    echo 'manager/includes/version.inc.php が見つかりません'
    exit 1
fi

# 配布対象外のパスが含まれていないことを確認（該当時は終了）
if unzip -Z1 "${ZIP_PATH}" | rg '(^|/)(\.github|\.agent|\.agents|\.claude|\.codex|\.vscode|\.work|docs|custom-instructions|manager/docker)(/|$)|(^|/)(\.gitignore|\.gitkeep|\.editorconfig|\.coderabbit\.yaml|\.gitattributes|AGENTS\.md|CLAUDE\.md|compose\.yml|readme[^/]*|README[^/]*)$'; then
    echo '配布対象外のパスが含まれています'
    exit 1
fi

# 確認後に一時ファイルを削除
rm -rf "${CHECK_DIR}"
```

`gh release view` の `isDraft` が `true` であること、添付ZIPが1つ存在すること、必須ファイルが含まれること、配布対象外のパスが含まれないことを確認する。問題がなければ Releases 画面または `gh release edit "${TAG}" --draft=false` で公開する。

## 除外ファイル一覧

`.git/` は `git archive` の仕様で常に含まれない。その他、リリースパッケージから除外される主なファイル・ディレクトリの正本は [.gitattributes](../../.gitattributes) である:

```
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
.coderabbit.yaml
.editorconfig
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
| `prefix* export-ignore` | プレフィックスパターン | `readme* export-ignore` |
| `path/to/file export-ignore` | 相対パス | `temp/cache/ export-ignore` |

**追加例:**

```gitattributes
# テストファイルを除外
**/test/ export-ignore
**/tests/ export-ignore
*.test.php export-ignore

# 開発用ファイルを除外
.env export-ignore
.env.local export-ignore
composer.json export-ignore
composer.lock export-ignore
package.json export-ignore
package-lock.json export-ignore

# ログ・キャッシュを除外
*.log export-ignore
temp/cache/ export-ignore
temp/backup/ export-ignore
```

**注意事項:**

- パターンの末尾に `export-ignore` を記述する
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
3. ロードマップの整理（下記手順）

   `Status: DONE` のタスクを `.agent/roadmap.md` から `.agent/roadmap-archive.md` へ移動する。

   1. `.agent/roadmap.md` から `Status: DONE` のタスクをすべて抽出する
   2. `.agent/roadmap-archive.md` の対応セクションに追記する（セクションがない場合は新設）
   3. `.agent/roadmap.md` から抽出したタスクを削除する
   4. `.agent/roadmap.md` の `最終更新` を更新する
   5. コミットする（例: `docs(roadmap): release-1.3.0J リリースに伴い完了タスクをアーカイブ`）

## 参考リンク

- GitHub Actions ワークフロー: [.github/workflows/release.yml](../../.github/workflows/release.yml)
- ロードマップ: [`../../.agent/roadmap.md`](../../.agent/roadmap.md)
