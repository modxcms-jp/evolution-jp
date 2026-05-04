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

---

## リリースノート生成（手順 3）

タグ push 後、GitHub Actions の完了を待ってからリリースノートを生成する。

### 実行コマンド

```bash
# 前回タグを特定
PREV_TAG=$(git tag --sort=-creatordate | grep '^release-' | sed -n '2p')
CURR_TAG=$(git tag --sort=-creatordate | grep '^release-' | sed -n '1p')

# コミット一覧（内部変更を除外）
git log ${PREV_TAG}..${CURR_TAG} --oneline | grep -v "^\S* chore\|^\S* docs\|^\S* fix(skill)\|^\S* fix(roadmap)\|Merge pull request"

# 変更規模
git diff ${PREV_TAG}..${CURR_TAG} --stat | tail -3

# 前回リリースのノート形式を参照
gh release view ${PREV_TAG}
```

### 生成ルール

- `assets/docs/release-process.md` の「リリースノートの構成」セクションのフォーマットに従う
- skill/agent/roadmap 関連コミットはリリースノートに含めない
- ユーザーへの提示後、確認・編集を経てから `gh release edit` で適用する

### ドラフトへの適用

```bash
gh release edit <タグ名> --notes "$(cat <<'EOF'
（リリースノート本文）
EOF
)"
```

適用後、ユーザーに Releases ページの確認と「Publish release」を案内して完了。
