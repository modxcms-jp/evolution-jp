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

## リリース後のロードマップ整理（手順 4）

「Publish release」完了後、`assets/docs/release-process.md` の「ロードマップ整理手順」に従い、`Status: DONE` のタスクを `roadmap.md` から `roadmap-archive.md` へ移動してコミットする。

---

## リリースノート生成（手順 3）

タグ push 後、GitHub Actions の完了を待ってからリリースノートを生成する。

### 実行コマンド

```bash
# 前回タグを特定
PREV_TAG=$(git tag --sort=-creatordate | grep '^release-' | sed -n '2p')
CURR_TAG=$(git tag --sort=-creatordate | grep '^release-' | sed -n '1p')

# コミット一覧（内部変更を除外）
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

# 変更規模
git diff ${PREV_TAG}..${CURR_TAG} --stat | tail -3

# 前回リリースのノート形式を参照
gh release view ${PREV_TAG}
```

### 生成ルール

- `assets/docs/release-process.md` の「リリースノートの構成」セクションのフォーマットに従う
- `(skill)` / `(agent)` / `(roadmap)` スコープのコミットや `.agent/`、`.claude/`、`.codex/` 配下のみを変更するコミットはリリースノートに含めない（開発者向け内部変更のため）
- `chore`、`docs` プレフィックスのコミットは除外する
- **非エンジニアファーストで書く**（エンジニアも読むので技術用語・技術詳細を入れること自体は構わない）
  - "What（何を変えたか）"より "Why（なぜ変えたか）・どう嬉しいか" を先に書く
  - 技術用語は補足として添える形にし、説明の主軸は非エンジニアにも伝わる言葉で書く
  - トレードオフや制限事項があれば正直に記載する
- 生成後はユーザーへ提示し、**レビューチェックリストで確認**を促す

### レビューチェックリスト（ユーザーへ提示する）

生成したリリースノートをユーザーに見せる際、以下の確認を依頼する:

```
【リリースノート レビューチェックリスト】
□ 対象読者（非エンジニア）に伝わる言葉になっているか
□ 各改修の「なぜ変えたのか」が説明されているか
□ トレードオフや制限事項を隠していないか
□ 事実と異なる記述はないか
□ 概要・ハイライトの優先順位は適切か
```

ユーザーの修正指示を受けて内容を更新し、最終確認後に `gh release edit` で適用する。

### ドラフトへの適用

```bash
gh release edit <タグ名> --notes "$(cat <<'EOF'
（リリースノート本文）
EOF
)"
```

適用後、ユーザーに Releases ページの確認と「Publish release」を案内して完了。
