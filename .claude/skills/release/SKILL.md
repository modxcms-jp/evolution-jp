---
name: release
description: Evolution CMS JP Edition のリリース作業を対話形式でガイドするスキル。バージョン更新・タグ作成・GitHub Actions によるパッケージビルドまでを順を追って進める。「リリース」「release」と依頼されたときに使用する。
---

# リリーススキル

手順の正本は `assets/docs/release-process.md`。このスキルはその手順を対話形式で実行するラッパー。リリースパッケージは `git archive` で生成し、配布対象外のパスは `.gitattributes` の `export-ignore` で管理する。

## コマンド

### `/release`

`assets/docs/release-process.md` の「基本手順」を読み込み、各ステップでユーザー確認を取りながら進める。

#### 引数なしで開始した場合

最初に、このスキルが行うことを次のように簡単に説明する。

> バージョン情報を更新し、リリースタグを作成してGitHub Actionsで配布ZIPを作成します。その後、ドラフトRelease・ZIP・リリースノートを確認し、ユーザーの最終確認後に公開します。回答が固まるまでは変更や公開操作を行いません。

続けて、次の項目を1問ずつ質問する。1つの回答を受けてから次の質問へ進み、既に回答済みの項目は再質問しない。回答が曖昧な場合は、その項目だけを聞き直す。

1. リリースするバージョン（`X.Y.ZJ` 形式、例: `1.4.0J`）。回答後、既存の `release-*` タグをバージョン順で確認する
2. リリース日（指定がなければ今日の日付）
3. リリース対象は、リリース準備PRがマージされた後の `main` の先端で固定することを確認する。`main` へ直接コミットしない
4. 候補タグを確認したうえで、「比較対象の前回リリースタグは `release-X.Y.ZJ` で合っていますか？」と質問する。`はい` ならそのタグを使い、`いいえ` なら別のタグまたは比較開始コミットを質問する
5. リリースノートを日本語のドラフトとして生成し、ユーザー確認後に適用するか
6. ZIP確認後に公開まで進めるか、ドラフト作成・検証までで止めるか

前回タグの候補は、作成日時ではなくバージョン順で表示する。候補がない場合、またはユーザーがタグを使わない場合は、比較開始コミットを `BASE_REF` として指定する。候補を自動確定せず、ユーザーの `はい` を受けて初めて `PREV_TAG` に設定する。

```bash
git tag --list 'release-*' --sort=-v:refname
```

回答を受けたら、バージョン、リリース日、`main` の先端、`PREV_TAG` または `BASE_REF`、`CURR_TAG`、リリースノートの扱い、公開範囲を要約して再確認する。ユーザーが要件を確定するまで、開始前チェック後の更新・コミット・タグ作成・push・Release編集を開始しない。

**開始前チェック**（この順序を崩さない）:

1. `git fetch origin main` 後の `origin/main` をリリース準備の基準にする。現在のブランチが `main` であること自体は問題にしないが、`git log --oneline origin/main..main` で未 push コミットが見つかれば、失われないよう処理方針を確認して停止する
2. `git status` — 未コミット変更があれば保留する。既存ブランチを勝手に切り替えたり、変更をstash・破棄したりしない
3. `git show origin/main:.agent/roadmap.md` で `Status: WIP` / `Status: BLOCKED` タスクを実体のあるタスクブロックとして確認する。未対応のものがあれば、原則としてリリースを保留し、対象タスクと保留理由を提示する。ユーザーが明示的に進行を許可した場合だけ例外扱いにする
4. `git show origin/main:manager/includes/version.inc.php` で現在のバージョン（`$modx_version`）とリリースタグ候補を確認する
5. `origin/main` の対象コミットから `.github/workflows/release.yml` を読み、`release-*` タグトリガー、`git archive --format=zip`、ドラフトRelease、想定したZIP添付を設定していることを確認する
6. 対象バージョンのローカル・リモートタグが存在しないことを確認する

保留条件が1つでもある場合は、バージョンファイル更新、リリース準備ブランチ作成、コミット、タグ作成、push、Release編集を開始しない。保留後に再開する場合は、改めて `origin/main` と作業ツリー、`WIP` / `BLOCKED`、タグを確認する。

問題がなければ `assets/docs/release-process.md` の手順に従いリリースを進める。

## リリース準備ブランチとPR

開始前チェックを通過した後、`origin/main` から `chore/release-{version}` 形式のリリース準備ブランチを作成する。バージョン更新とコミットはこのブランチで行い、`main` へ直接コミットしない。push とPR作成はユーザー確認後に行う。

PRがマージされるまでタグを作成しない。マージ後に `git fetch origin main` で更新した `origin/main` の先端、バージョン情報、作業ツリーを確認し、そのコミットにだけ `release-{version}` タグを作成する。

## バージョン入力後の安全確認

新しいバージョン番号を受け取った後、更新前に次を確認する。

1. バージョン番号が `X.Y.ZJ` 形式であること（例: `1.3.0J`）
2. ローカルに `release-{version}` タグが存在しないこと
3. リモート `origin` に `release-{version}` タグが存在しないこと
4. タグ作成対象が、バージョン更新PRをマージした後の `origin/main` の先端になること

タグの存在確認には次を使う。既存タグが見つかった場合は削除や上書きを行わず、ユーザーに対応を確認する。

```bash
VERSION="1.3.0J"
WORKFLOW=".github/workflows/release.yml"
MAIN_REF="origin/main"

set -e

git cat-file -e "${MAIN_REF}:${WORKFLOW}"
git show "${MAIN_REF}:${WORKFLOW}" | rg -q --fixed-strings "      - 'release-*'"
git show "${MAIN_REF}:${WORKFLOW}" | rg -q --fixed-strings "git archive --format=zip"
git show "${MAIN_REF}:${WORKFLOW}" | rg -q --fixed-strings "draft: true"
git show "${MAIN_REF}:${WORKFLOW}" | rg -q --fixed-strings 'files: evo-${{ github.ref_name }}.zip'
git show "${MAIN_REF}:${WORKFLOW}" | rg -q --fixed-strings "generate_release_notes: false"

if git rev-parse --verify "refs/tags/release-${VERSION}" >/dev/null 2>&1; then
    echo "ローカルに既存タグがあります"
    exit 1
else
    status=$?
    if [[ "${status}" -ne 128 ]]; then
        echo "ローカルタグ確認に失敗しました（終了コード: ${status}）"
        exit "${status}"
    fi
fi

if git ls-remote --exit-code --refs origin "refs/tags/release-${VERSION}" >/dev/null 2>&1; then
    echo "リモートに既存タグがあります"
    exit 1
else
    status=$?
    if [[ "${status}" -ne 2 ]]; then
        echo "リモートタグ確認に失敗しました（終了コード: ${status}）"
        exit "${status}"
    fi
fi
```

ローカルタグ確認では終了コード `128`、リモートタグ確認では終了コード `2` の場合だけ「タグなし」と判定する。それ以外の終了コードは認証・ネットワーク・リモート障害などの可能性があるため、バージョンファイルを更新せずに中止する。更新後は `git diff --check` と対象ファイルの差分を提示し、コミット前にユーザー確認を取る。

---

## リリース後のロードマップ整理

「Publish release」完了後、`assets/docs/release-process.md` の「リリース後の対応」手順 3 に従い、`Status: DONE` のタスクを別の整理ブランチで `.agent/roadmap-archive.md` へ移動し、PR経由で `main` に反映する。保護された `main` へ直接コミットしない。

---

## リリースノート生成（手順 4）

タグ push 後、GitHub Actions の完了を待ってからリリースノートを生成する。

コミット抽出コマンド・構成フォーマット・除外ルール・ドラフトへの適用方法は `assets/docs/release-process.md` の「リリースノートの生成と適用」セクションに従う。

リリースノートの比較範囲は、作成日時順から自動推測しない。今回のタグと比較対象のタグをユーザーと確認し、手順書の `PREV_TAG` / `CURR_TAG` に明示してから生成する。適切な前回タグがない場合は、`BASE_REF` から `CURR_TAG` までを比較する。タグ専用の検証と `gh release view` は `PREV_TAG` が指定されている場合だけ実行する。

リリースノート適用後は、同手順書の「ドラフト確認と公開」に従い、Actions の成功、ドラフト状態、添付ZIP、必須ファイル、配布対象外パスの有無を確認する。Actionsが作成するドラフトの説明文は使用せず、確認済みの `PREV_TAG` または `BASE_REF` から生成した本文を `gh release edit` で適用してから検証する。これらの確認が終わるまで公開操作を行わない。

生成後はユーザーへ提示し、以下のチェックリストで確認を促す:

```
【リリースノート レビューチェックリスト】
□ 対象読者（非エンジニア）に伝わる言葉になっているか
□ 各改修の「なぜ変えたのか」が説明されているか
□ トレードオフや制限事項を隠していないか
□ 事実と異なる記述はないか
□ 概要・ハイライトの優先順位は適切か
```

ユーザーの修正指示を受けて内容を更新し、最終確認後に `gh release edit` で適用する。
