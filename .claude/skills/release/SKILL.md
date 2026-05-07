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

## リリース後のロードマップ整理

「Publish release」完了後、`assets/docs/release-process.md` の「ロードマップ整理手順」に従い、`Status: DONE` のタスクを `.agent/roadmap.md` から `.agent/roadmap-archive.md` へ移動してコミットする。

---

## リリースノート生成（手順 3）

タグ push 後、GitHub Actions の完了を待ってからリリースノートを生成する。

コミット抽出コマンド・構成フォーマット・除外ルール・ドラフトへの適用方法は `assets/docs/release-process.md` の「リリースノートの生成と適用」セクションに従う。

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
