# ExecPlan: .gitattributes export-ignore 整備と release.yml の git archive 移行

## Purpose / Big Picture
リリースパッケージの除外パス定義を `.gitattributes`（`export-ignore`）に一元化し、`release.yml` を `git archive` ベースへシンプル化する。現状は `.gitattributes` の `export-ignore`（3 エントリ）と `release.yml` の rsync `--exclude`（15 以上）が二重管理になっており、どちらかを更新した際にもう一方を更新し忘れる保守リスクがある。

## Progress
- [ ] (2026-05-06) `.gitattributes` に不足している `export-ignore` エントリを追加
- [ ] (2026-05-06) `release.yml` を `git archive` ベースへ書き換え
- [ ] (2026-05-06) ローカルで `git archive` を実行してアーカイブ内容を確認

## Surprises & Discoveries

## Decision Log
- 2026-05-06 / yamamoto: `git archive` を採用。`git archive` は `.gitattributes` の `export-ignore` を自動的に尊重するため SSOT が実現できる。rsync はファイルシステムからコピーするため untracked ファイルも含むが、CI 環境（GitHub Actions）ではチェックアウト後の状態のため問題にならない。

## Outcomes & Retrospective

## Context and Orientation

**関連ファイル（リポジトリルート相対）:**
- `.gitattributes` — `export-ignore` が 3 エントリのみ（`.gitattributes`、`.gitignore`、`.editorconfig`）
- `.github/workflows/release.yml` — rsync + 多数の `--exclude` でビルドディレクトリを構築後 zip 化

**rsync --exclude と gitattributes の対照表:**

| rsync --exclude | .gitattributes での対応 |
|----------------|------------------------|
| `.git/` | `git archive` が自動除外（対応不要） |
| `.github/` | `.github export-ignore` |
| `.agent/` | `.agent export-ignore` |
| `.agents/` | `.agents export-ignore` |
| `.claude/` | `.claude export-ignore` |
| `.codex/` | `.codex export-ignore` |
| `.vscode/` | `.vscode export-ignore` |
| `.work/` | `.work export-ignore` |
| `.gitignore` | `.gitignore export-ignore`（既存） |
| `.gitkeep` | `.gitkeep export-ignore` |
| `.gitattributes` | `.gitattributes export-ignore`（既存） |
| `.editorconfig` | `.editorconfig export-ignore`（既存） |
| `dist/` | git 非管理のため不要 |
| `docs/` / `**/docs/` | `docs export-ignore` |
| `readme*` / `README*` | `readme* export-ignore` / `README* export-ignore` |
| `AGENTS.md` | `AGENTS.md export-ignore` |
| `CLAUDE.md` | `CLAUDE.md export-ignore` |
| `compose.yml` | `compose.yml export-ignore` |
| `custom-instructions/` | `custom-instructions export-ignore` |
| `manager/docker/` | `manager/docker export-ignore` |

## Plan of Work

`.gitattributes` の `export-ignore` 属性は `git archive` が自動的に参照するため、除外定義をここに集約し `release.yml` から rsync の除外リストを削除する。

移行後の `release.yml` は checkout → `git archive` → GitHub Release 作成の 3 ステップになる。

`.gitattributes` のパターンマッチング（git 仕様）:
- スラッシュを含まないパターン（`.github`、`AGENTS.md` 等）はツリー内の任意の位置にマッチ
- スラッシュを含むパターン（`manager/docker`）はリポジトリルートからのパスに固定
- 大文字小文字は区別される（`readme*` と `README*` は別パターン）

## Concrete Steps

### Step 1: `.gitattributes` に export-ignore を追加

対象ファイル: `.gitattributes`

既存エントリの末尾に以下を追記する:

    .github export-ignore
    .agent export-ignore
    .agents export-ignore
    .claude export-ignore
    .codex export-ignore
    .vscode export-ignore
    .work export-ignore
    .gitkeep export-ignore
    docs export-ignore
    readme* export-ignore
    README* export-ignore
    AGENTS.md export-ignore
    CLAUDE.md export-ignore
    compose.yml export-ignore
    custom-instructions export-ignore
    manager/docker export-ignore

確認:
```bash
git check-attr export-ignore AGENTS.md CLAUDE.md compose.yml
# 各ファイルに "export-ignore: ignore" が表示される
git check-attr export-ignore manager/docker/docker-compose.yml
# "export-ignore: ignore" が表示される
```

### Step 2: `release.yml` を git archive ベースへ書き換え

対象ファイル: `.github/workflows/release.yml`

「Prepare dist directory」ステップ（mkdir + rsync）と「Create zip archive」ステップ（cd dist + zip）を削除し、以下の 1 ステップに置き換える:

    - name: Create zip archive
      run: git archive --format=zip HEAD -o evo-${GITHUB_REF_NAME}.zip

書き換え後の steps 全体:
```yaml
    steps:
      - name: Checkout repository
        uses: actions/checkout@v4

      - name: Create zip archive
        run: git archive --format=zip HEAD -o evo-${GITHUB_REF_NAME}.zip

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v2
        with:
          files: evo-${{ github.ref_name }}.zip
          generate_release_notes: true
          draft: true
```

### Step 3: ローカル検証

```bash
# HEAD の内容で zip を生成
git archive --format=zip HEAD -o /tmp/evo-test.zip

# 除外されるべきファイルが含まれていないか確認（何も出力されなければ OK）
unzip -l /tmp/evo-test.zip | grep -E '(AGENTS|CLAUDE|\.github|\.agent/|compose\.yml|manager/docker)'

# 含まれるべきファイルが存在するか確認
unzip -l /tmp/evo-test.zip | grep 'manager/index.php'
```

## Validation and Acceptance

**ローカル検証（必須）:**
- `git archive --format=zip HEAD -o /tmp/evo-test.zip` が正常終了する
- `unzip -l /tmp/evo-test.zip` で `.github/`、`.agent/`、`AGENTS.md`、`CLAUDE.md`、`compose.yml`、`manager/docker/` が含まれない
- `manager/index.php` や `index.php` など配布対象ファイルは含まれる

**CI 検証（タグプッシュ後）:**
- GitHub Actions の `Build Release Package` ワークフローが正常終了する
- 生成された zip に除外対象ファイルが含まれない

## Idempotence and Recovery

- `.gitattributes` への追記は冪等。git の属性マッチは最後のマッチが優先されるため、重複があっても動作上の問題はない
- `release.yml` の書き換えが意図通りでない場合は `git checkout .github/workflows/release.yml` で復元可能
- CI で失敗した場合は rsync ベースのステップに差し戻し、`.gitattributes` を修正してから再試行する

## Artifacts and Notes

**想定コミット（2 分割）:**

1. `chore(gitattributes): リリース除外パスの export-ignore を整備`
   - 対象: `.gitattributes`
   - 不足していた 16 エントリを追加し、rsync --exclude と対照して網羅

2. `ci(release): rsync から git archive ベースへリファクタリング`
   - 対象: `.github/workflows/release.yml`
   - Prepare dist + zip ステップを `git archive` コマンド 1 行に置き換え

## Interfaces and Dependencies

- GitHub Actions `actions/checkout@v4`（変更なし）
- `softprops/action-gh-release@v2`（変更なし）
- `git archive` コマンド（ubuntu-latest 標準搭載、追加インストール不要）
