---
name: release-support
description: Evolution CMS JP Edition のリリース作業を、準備・版数更新・タグ作成・公開後確認・リリース記事下書き作成まで一貫して進行するスキル。ユーザーが「リリースしたい」「タグを切りたい」「release-* を作りたい」「version.inc.php を更新したい」「リリースノート/記事を作りたい」「前回リリースとの差分をまとめたい」と依頼したときに使う。
---

# Release Support

リリース作業を安全に前進させる。
パッケージ構成と公開導線の SSOT は `assets/docs/release-process.md` と `.github/workflows/release.yml` とする。

## Workflow

1. 目標リリース番号、対象ブランチ、実施日を確認する。
2. `assets/docs/release-process.md` を読み、対象変更が既存手順に乗るか確認する。
3. `CHANGELOG.md`（存在する場合）と `manager/includes/version.inc.php` の更新有無を確認し、必要な場合のみ最小差分で編集する。
4. `references/release-checklist.md` の事前チェックを実行する。
5. ローカル検証が必要な場合は、`.github/workflows/release.yml` の `Prepare dist directory` と同じ `rsync` 行で差分を確認する。
6. `release-*` タグ作成と push を実行し、GitHub Actions の `Build Release Package` 起動を確認する。
7. Release 画面で zip 添付・ノート内容・版数表記を確認する。
8. 実施ログを簡潔にまとめ、必要なら巻き戻し手順を提示する。

## Release Draft

1. 下書き生成は `scripts/generate_release_draft.sh` を使う。
2. 既定では「最新の `release-*` タグから `HEAD` まで」の差分ファイルとマージPRを収集する。
3. 範囲を固定したい場合は `--from` と `--to` を指定する。
4. コミット件名は信頼性に差があるため既定では使わず、必要時のみ `--include-commit-subjects` を使う。
5. 生成後は `references/release-note-template.md` に沿って概要を人手で補完する。

```bash
.codex/skills/release-support/scripts/generate_release_draft.sh --output temp/release-note-draft.md
```

## Editing Rules

1. 除外パターンの編集先は必ず `.github/workflows/release.yml` の `Prepare dist directory` に限定する。
2. 除外設定を別ファイルへ重複転記しない。
3. `version.inc.php` 更新時は `modx_version` と `modx_release_date` の整合を維持する。
4. コミットメッセージは Conventional Commits 準拠で日本語 subject を使う。

## Output Contract

1. 進行報告は「実施済み」「未実施」「要判断」を分けて短く示す。
2. タグ作成前に必ず最終確認項目を列挙する。
3. 失敗時は `references/release-checklist.md` の「失敗時対応」を優先して案内する。

## References

- 実行チェックリスト: `references/release-checklist.md`
- リリース記事テンプレート: `references/release-note-template.md`
- 公式手順 SSOT: `assets/docs/release-process.md`
- ワークフロー: `.github/workflows/release.yml`
